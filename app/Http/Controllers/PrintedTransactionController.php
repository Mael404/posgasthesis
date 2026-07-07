<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PrintedTransactionController extends Controller
{
    private const ADMIN_ROLES = ['admin', 'vp_admin', 'semiadmin'];

    public function storeSelected(Request $request)
    {
        $batch = $this->createBatchFromSelectedTransactions($request);

        return response()->json([
            'success' => true,
            'message' => 'Selected transactions saved for print tracking.',
            'print_url' => route('staff.printed-transactions.print', $batch->id),
        ]);
    }

    public function printSelected(Request $request)
    {
        if ($request->isMethod('get') && ! $request->has('ids')) {
            return redirect()->route('staff.printed-transactions.index');
        }

        $batch = $this->createBatchFromSelectedTransactions($request);

        return redirect()->route('staff.printed-transactions.print', $batch->id);
    }

    public function index(Request $request): View
    {
        $this->ensureTrackingTableReady();

        $adminView = $request->routeIs('admin.printed-transactions.*');

        if ($adminView && ! $this->canViewAllPrintedTransactions()) {
            abort(403);
        }

        $query = DB::table('printed_transaction_batches')
            ->leftJoin('users as staff_users', 'printed_transaction_batches.staff_id', '=', 'staff_users.id')
            ->leftJoin('users as marked_users', 'printed_transaction_batches.marked_by', '=', 'marked_users.id')
            ->select(
                'printed_transaction_batches.*',
                'staff_users.name as staff_name',
                'marked_users.name as marked_by_name'
            )
            ->orderByDesc('printed_transaction_batches.printed_at')
            ->orderByDesc('printed_transaction_batches.id');

        if (! $adminView) {
            $query->where('printed_transaction_batches.staff_id', auth()->id());
        }

        if ($request->filled('staff_id') && $adminView) {
            $query->where('printed_transaction_batches.staff_id', $request->integer('staff_id'));
        }

        if ($request->filled('status')) {
            if ($request->status === 'marked') {
                $query->where('printed_transaction_batches.is_marked', 1);
            } elseif ($request->status === 'pending') {
                $query->where('printed_transaction_batches.is_marked', 0);
            }
        }

        if ($request->filled('start_date')) {
            try {
                $query->where('printed_transaction_batches.printed_at', '>=', Carbon::parse($request->start_date)->startOfDay());
            } catch (\Throwable) {
                // Ignore malformed filter values and keep the list available.
            }
        }

        if ($request->filled('end_date')) {
            try {
                $query->where('printed_transaction_batches.printed_at', '<=', Carbon::parse($request->end_date)->endOfDay());
            } catch (\Throwable) {
                // Ignore malformed filter values and keep the list available.
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($inner) use ($search) {
                $inner->where('printed_transaction_batches.batch_no', 'like', '%' . $search . '%')
                    ->orWhere('printed_transaction_batches.customer_name', 'like', '%' . $search . '%')
                    ->orWhere('printed_transaction_batches.customer_subtitle', 'like', '%' . $search . '%');
            });
        }

        $prints = $query->paginate(15)->withQueryString();
        $prints->getCollection()->transform(fn ($batch) => $this->hydrateBatchForList($batch));

        $staffList = collect();

        if ($adminView) {
            $staffList = DB::table('users')
                ->whereIn('id', function ($staffQuery) {
                    $staffQuery->select('staff_id')
                        ->from('printed_transaction_batches')
                        ->distinct();
                })
                ->orderBy('name')
                ->get();
        }

        $title = 'Printed Transactions';

        return view($adminView ? 'pages.admin.printedtransactions' : 'pages.staff.printedtransactions', compact(
            'prints',
            'adminView',
            'staffList',
            'title'
        ));
    }

    public function mark(Request $request, $batch)
    {
        $this->ensureTrackingTableReady();

        $request->validate([
            'is_marked' => ['required', 'boolean'],
        ]);

        $printedBatch = $this->findBatchForRoute($request, (int) $batch);
        $isMarked = filter_var($request->input('is_marked'), FILTER_VALIDATE_BOOLEAN);

        DB::table('printed_transaction_batches')
            ->where('id', $printedBatch->id)
            ->update([
                'is_marked' => $isMarked ? 1 : 0,
                'marked_at' => $isMarked ? ($printedBatch->marked_at ?: now()) : null,
                'marked_by' => $isMarked ? ($printedBatch->marked_by ?: auth()->id()) : null,
                'updated_at' => now(),
            ]);

        $message = $isMarked
            ? 'Printed transaction batch marked.'
            : 'Printed transaction batch returned to pending.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function destroy(Request $request, $batch)
    {
        $this->ensureTrackingTableReady();

        $printedBatch = $this->findBatchForRoute($request, (int) $batch);

        DB::table('printed_transaction_batches')
            ->where('id', $printedBatch->id)
            ->delete();

        $message = 'Printed transaction batch deleted.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function bulkMark(Request $request)
    {
        $this->ensureTrackingTableReady();

        if ($request->routeIs('admin.printed-transactions.*') && ! $this->canViewAllPrintedTransactions()) {
            abort(403);
        }

        $request->validate([
            'batch_ids' => ['nullable', 'array'],
            'batch_ids.*' => ['integer'],
        ]);

        $batchIds = collect($request->input('batch_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($batchIds->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select at least one printed transaction batch to mark.',
                ], 422);
            }

            return back()->with('error', 'Please select at least one printed transaction batch to mark.');
        }

        $query = DB::table('printed_transaction_batches')
            ->whereIn('id', $batchIds->all());

        if (! $request->routeIs('admin.printed-transactions.*')) {
            $query->where('staff_id', auth()->id());
        }

        $markableIds = $query->pluck('id');

        if ($markableIds->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No selected printed transaction batches could be marked.',
                ], 422);
            }

            return back()->with('error', 'No selected printed transaction batches could be marked.');
        }

        DB::table('printed_transaction_batches')
            ->whereIn('id', $markableIds->all())
            ->update([
                'is_marked' => 1,
                'marked_at' => DB::raw('COALESCE(marked_at, CURRENT_TIMESTAMP)'),
                'marked_by' => DB::raw('COALESCE(marked_by, ' . (int) auth()->id() . ')'),
                'updated_at' => now(),
            ]);

        $message = $markableIds->count() . ' printed transaction batch' . ($markableIds->count() === 1 ? '' : 'es') . ' marked.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function bulkDestroy(Request $request)
    {
        $this->ensureTrackingTableReady();

        if ($request->routeIs('admin.printed-transactions.*') && ! $this->canViewAllPrintedTransactions()) {
            abort(403);
        }

        $request->validate([
            'batch_ids' => ['nullable', 'array'],
            'batch_ids.*' => ['integer'],
        ]);

        $batchIds = collect($request->input('batch_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($batchIds->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select at least one printed transaction batch to delete.',
                ], 422);
            }

            return back()->with('error', 'Please select at least one printed transaction batch to delete.');
        }

        $query = DB::table('printed_transaction_batches')
            ->whereIn('id', $batchIds->all());

        if (! $request->routeIs('admin.printed-transactions.*')) {
            $query->where('staff_id', auth()->id());
        }

        $deletedCount = $query->delete();

        if ($deletedCount < 1) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No selected printed transaction batches could be deleted.',
                ], 422);
            }

            return back()->with('error', 'No selected printed transaction batches could be deleted.');
        }

        $message = $deletedCount . ' printed transaction batch' . ($deletedCount === 1 ? '' : 'es') . ' deleted.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function printBatch(Request $request, $batch): View
    {
        $this->ensureTrackingTableReady();

        $printedBatch = $this->findBatchForRoute($request, (int) $batch);
        $transactions = $this->transactionsFromBatch($printedBatch);

        if ($transactions->isEmpty()) {
            abort(404, 'No transactions are stored for this print batch.');
        }

        $customer = (object) [
            'name' => $printedBatch->customer_name,
            'subtitle' => $printedBatch->customer_subtitle,
            'contact' => $printedBatch->customer_contact,
        ];

        $totals = $this->calculateTotals($transactions);
        $staff = DB::table('users')->where('id', $printedBatch->staff_id)->first() ?: auth()->user();
        $printedAt = Carbon::parse($printedBatch->printed_at ?: $printedBatch->created_at);
        $isPaidInFullReceipt = $this->isPaidInFullPrintout($transactions);
        $printSignatories = $this->printSignatories((int) $printedBatch->id);
        $signatorySaveUrl = $request->routeIs('admin.printed-transactions.*')
            ? route('admin.printed-transactions.print.signatories', $printedBatch->id)
            : route('staff.printed-transactions.print.signatories', $printedBatch->id);
        $signatorySignedAt = $printSignatories->signed_at ?? now();
        $title = 'Print Transaction Batch';

        return view('pages.printedtransactions-print', compact(
            'transactions',
            'customer',
            'totals',
            'staff',
            'printedAt',
            'isPaidInFullReceipt',
            'printedBatch',
            'printSignatories',
            'signatorySaveUrl',
            'signatorySignedAt',
            'title'
        ));
    }

    public function savePrintSignatories(Request $request, $batch): JsonResponse
    {
        $this->ensureTrackingTableReady();

        $validated = $request->validate([
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'acknowledged_by' => ['nullable', 'string', 'max:255'],
        ]);

        $printedBatch = $this->findBatchForRoute($request, (int) $batch);

        if (! Schema::hasTable('acknowledgement_receipt_signatories')) {
            return response()->json([
                'success' => false,
                'message' => 'The signatories table is not ready. Please run php artisan migrate.',
            ], 500);
        }

        $signedAt = now();
        $receiptKey = $this->printSignatoryKey((int) $printedBatch->id);
        $values = [
            'receipt_key' => $receiptKey,
            'invoice_id' => (int) $printedBatch->id,
            'payment_ledger_id' => null,
            'received_by' => $this->cleanNullableText($validated['prepared_by'] ?? null),
            'paid_by' => $this->cleanNullableText($validated['acknowledged_by'] ?? null),
            'signed_at' => $signedAt,
            'saved_by' => auth()->id(),
            'updated_at' => now(),
        ];

        $this->upsertPrintSignatory($receiptKey, $values);

        return response()->json([
            'success' => true,
            'message' => 'Printed transaction signatories saved.',
            'prepared_by' => $values['received_by'] ?? '',
            'acknowledged_by' => $values['paid_by'] ?? '',
            'signed_at' => $signedAt->format('F d, Y h:i A'),
            'signed_at_input' => $signedAt->format('Y-m-d\TH:i'),
        ]);
    }

    private function createBatchFromSelectedTransactions(Request $request): object
    {
        $this->ensureTrackingTableReady();

        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            abort(422, 'Please select at least one transaction to print.');
        }

        $transactions = $this->selectedTransactionsForCurrentStaff($ids);

        if ($transactions->count() !== $ids->count()) {
            abort(404, 'One or more selected transactions could not be found.');
        }

        $customerKeys = $transactions
            ->map(fn ($sale) => $this->printCustomerKey($sale))
            ->unique()
            ->values();

        if ($customerKeys->count() > 1) {
            abort(422, 'Selected transactions must belong to the same customer.');
        }

        return $this->storePrintedBatch($transactions);
    }

    private function selectedTransactionsForCurrentStaff($ids)
    {
        return DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->leftJoin('customers', function ($join) {
                $join->on('customers.id', '=', DB::raw('COALESCE(sales.customer_id, sales.posted_customer_id)'));
            })
            ->leftJoin('invoices', 'sales.invoice_id', '=', 'invoices.id')
            ->select(
                'sales.*',
                'users.name as staff_name',
                'customers.business_name',
                'customers.owner_name',
                'customers.contact_number',
                'invoices.status as invoice_status',
                'invoices.total_amount_due as invoice_total_amount_due',
                'invoices.payments_received as invoice_payments_received'
            )
            ->where('sales.user_id', auth()->id())
            ->whereIn('sales.id', $ids->all())
            ->orderBy('sales.created_at')
            ->orderBy('sales.id')
            ->get();
    }

    private function storePrintedBatch($transactions): object
    {
        $customer = $this->printCustomerDetails($transactions->first());
        $totals = $this->calculateTotals($transactions);
        $printedAt = now();
        $transactionIds = $transactions->pluck('id')->map(fn ($id) => (int) $id)->values();

        $insert = [
            'staff_id' => auth()->id(),
            'printed_by' => auth()->id(),
            'customer_key' => $this->printCustomerKey($transactions->first()),
            'customer_id' => $this->printCustomerId($transactions->first()),
            'customer_name' => $customer->name,
            'customer_subtitle' => $customer->subtitle,
            'customer_contact' => $customer->contact,
            'transaction_ids' => json_encode($transactionIds->all()),
            'transactions_snapshot' => json_encode($this->transactionSnapshot($transactions)),
            'records_count' => $transactions->count(),
            'fuel_amount' => $totals->fuel_amount,
            'collection_amount' => $totals->collection_amount,
            'liters' => $totals->liters,
            'discounts' => $totals->discounts,
            'surcharge' => $totals->surcharge,
            'total_amount' => $totals->amount,
            'printed_at' => $printedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('printed_transaction_batches')->insertGetId($insert);
        $batchNo = 'SOA-' . $printedAt->format('ymd-His') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);

        DB::table('printed_transaction_batches')
            ->where('id', $id)
            ->update([
                'batch_no' => $batchNo,
                'updated_at' => now(),
            ]);

        return (object) ($insert + [
            'id' => $id,
            'batch_no' => $batchNo,
            'is_marked' => 0,
            'marked_at' => null,
            'marked_by' => null,
        ]);
    }

    private function hydrateBatchForList($batch): object
    {
        $snapshot = collect($this->decodeJsonArray($batch->transactions_snapshot))
            ->map(fn ($sale) => (array) $sale);

        $batch->transaction_summaries = $snapshot
            ->map(function ($sale) {
                $reference = trim((string) ($sale['or_number'] ?? ''));

                if ($reference === '') {
                    $reference = trim((string) ($sale['reference_no'] ?? ''));
                }

                return [
                    'id' => $sale['id'] ?? null,
                    'reference' => $reference !== '' ? $reference : 'Sale #' . ($sale['id'] ?? '?'),
                    'amount' => (float) ($sale['amount'] ?? 0),
                ];
            })
            ->values();

        return $batch;
    }

    private function transactionsFromBatch($batch)
    {
        $snapshot = collect($this->decodeJsonArray($batch->transactions_snapshot))
            ->map(fn ($sale) => (object) $sale)
            ->values();

        if ($snapshot->isNotEmpty()) {
            return $snapshot;
        }

        $ids = collect($this->decodeJsonArray($batch->transaction_ids))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $sales = DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->leftJoin('customers', function ($join) {
                $join->on('customers.id', '=', DB::raw('COALESCE(sales.customer_id, sales.posted_customer_id)'));
            })
            ->leftJoin('invoices', 'sales.invoice_id', '=', 'invoices.id')
            ->select(
                'sales.*',
                'users.name as staff_name',
                'customers.business_name',
                'customers.owner_name',
                'customers.contact_number',
                'invoices.status as invoice_status',
                'invoices.total_amount_due as invoice_total_amount_due',
                'invoices.payments_received as invoice_payments_received'
            )
            ->whereIn('sales.id', $ids->all())
            ->get()
            ->keyBy('id');

        return $ids
            ->map(fn ($id) => $sales->get($id))
            ->filter()
            ->values();
    }

    private function findBatchForRoute(Request $request, int $batchId): object
    {
        $adminRoute = $request->routeIs('admin.printed-transactions.*');

        if ($adminRoute && ! $this->canViewAllPrintedTransactions()) {
            abort(403);
        }

        $query = DB::table('printed_transaction_batches')
            ->where('id', $batchId);

        if (! $adminRoute) {
            $query->where('staff_id', auth()->id());
        }

        $batch = $query->first();

        if (! $batch) {
            abort(404, 'Printed transaction batch not found.');
        }

        return $batch;
    }

    private function calculateTotals($transactions): object
    {
        return (object) [
            'fuel_amount' => $transactions
                ->reject(fn ($sale) => $this->isCollectionSale($sale))
                ->sum('amount'),
            'collection_amount' => $transactions
                ->filter(fn ($sale) => $this->isCollectionSale($sale))
                ->sum('amount'),
            'liters' => $transactions
                ->reject(fn ($sale) => $this->isCollectionSale($sale))
                ->sum('liters'),
            'discounts' => $transactions->sum(function ($sale) {
                return (float) ($sale->discount ?? 0) + (float) ($sale->manual_discount ?? 0);
            }),
            'surcharge' => $transactions->sum(fn ($sale) => (float) ($sale->surcharge ?? 0)),
            'amount' => $transactions->sum('amount'),
        ];
    }

    private function transactionSnapshot($transactions): array
    {
        return $transactions
            ->map(function ($sale) {
                return [
                    'id' => (int) $sale->id,
                    'or_number' => $sale->or_number ?? null,
                    'reference_no' => $sale->reference_no ?? null,
                    'pump_id' => $sale->pump_id ?? null,
                    'user_id' => $sale->user_id ?? null,
                    'staff_name' => $sale->staff_name ?? null,
                    'customer_id' => $sale->customer_id ?? null,
                    'posted_customer_id' => $sale->posted_customer_id ?? null,
                    'customer_name' => $sale->customer_name ?? null,
                    'plate_no' => $sale->plate_no ?? null,
                    'payment_type' => $sale->payment_type ?? null,
                    'fuel_type' => $sale->fuel_type ?? null,
                    'liters' => $sale->liters ?? 0,
                    'price_applied' => $sale->price_applied ?? 0,
                    'amount' => $sale->amount ?? 0,
                    'discount' => $sale->discount ?? 0,
                    'manual_discount' => $sale->manual_discount ?? 0,
                    'surcharge' => $sale->surcharge ?? 0,
                    'invoice_id' => $sale->invoice_id ?? null,
                    'customer_payment_posted_at' => $sale->customer_payment_posted_at ?? null,
                    'created_at' => (string) ($sale->created_at ?? ''),
                    'business_name' => $sale->business_name ?? null,
                    'owner_name' => $sale->owner_name ?? null,
                    'contact_number' => $sale->contact_number ?? null,
                    'invoice_status' => $sale->invoice_status ?? null,
                    'invoice_total_amount_due' => $sale->invoice_total_amount_due ?? null,
                    'invoice_payments_received' => $sale->invoice_payments_received ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function decodeJsonArray($value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function printCustomerKey($sale): string
    {
        $customerId = $this->printCustomerId($sale);

        if ($this->isAccountSale($sale) && ! empty($customerId)) {
            return 'account:' . $customerId;
        }

        $name = strtolower(trim($sale->customer_name ?? 'Walk-In Customer'));
        $plate = strtolower(trim($sale->plate_no ?? ''));

        return 'walkin:' . md5($name . '|' . $plate);
    }

    private function printCustomerId($sale): ?int
    {
        $customerId = $sale->customer_id ?? ($sale->posted_customer_id ?? null);

        if ($this->isAccountSale($sale) && ! empty($customerId)) {
            return (int) $customerId;
        }

        return null;
    }

    private function printCustomerDetails($sale): object
    {
        $customerId = $sale->customer_id ?? ($sale->posted_customer_id ?? null);

        if ($this->isAccountSale($sale) && ! empty($customerId)) {
            return (object) [
                'name' => ! empty($sale->business_name) && strtoupper((string) $sale->business_name) !== 'N/A'
                    ? $sale->business_name
                    : ($sale->owner_name ?: 'No customer name'),
                'subtitle' => $sale->owner_name ?: null,
                'contact' => $sale->contact_number ?? null,
            ];
        }

        return (object) [
            'name' => $sale->customer_name ?: 'Walk-In Customer',
            'subtitle' => ! empty($sale->plate_no) ? 'Plate: ' . $sale->plate_no : null,
            'contact' => null,
        ];
    }

    private function isPaidInFullPrintout($transactions): bool
    {
        if ($transactions->isEmpty()) {
            return false;
        }

        return $transactions->every(fn ($sale) => $this->isPaidInFullPrintableSale($sale));
    }

    private function isPaidInFullPrintableSale($sale): bool
    {
        $paymentType = strtolower(trim((string) ($sale->payment_type ?? '')));

        if (in_array($paymentType, ['walkin', 'cash'], true)) {
            return true;
        }

        if ($paymentType !== 'collection' || empty($sale->invoice_id)) {
            return false;
        }

        if (strtolower(trim((string) ($sale->invoice_status ?? ''))) === 'paid') {
            return true;
        }

        if (! empty($sale->customer_payment_posted_at)) {
            return false;
        }

        $remainingBeforePayment = max(
            0,
            (float) ($sale->invoice_total_amount_due ?? 0) - (float) ($sale->invoice_payments_received ?? 0)
        );

        return $remainingBeforePayment > 0
            && (float) ($sale->amount ?? 0) >= ($remainingBeforePayment - 0.01);
    }

    private function isAccountSale($sale): bool
    {
        $paymentType = strtolower(trim((string) ($sale->payment_type ?? '')));

        return in_array($paymentType, ['credit', 'collection'], true);
    }

    private function isCollectionSale($sale): bool
    {
        return strtolower(trim((string) ($sale->payment_type ?? ''))) === 'collection';
    }

    private function ensureTrackingTableReady(): void
    {
        if (! Schema::hasTable('printed_transaction_batches')) {
            abort(500, 'Printed transaction tracking table is not ready. Please run php artisan migrate.');
        }
    }

    private function printSignatories(int $batchId): object
    {
        $emptySignatories = (object) [
            'prepared_by' => null,
            'acknowledged_by' => null,
            'signed_at' => null,
        ];

        if (! Schema::hasTable('acknowledgement_receipt_signatories')) {
            return $emptySignatories;
        }

        $signatories = DB::table('acknowledgement_receipt_signatories')
            ->where('receipt_key', $this->printSignatoryKey($batchId))
            ->first();

        if (! $signatories) {
            return $emptySignatories;
        }

        return (object) [
            'prepared_by' => $signatories->received_by,
            'acknowledged_by' => $signatories->paid_by,
            'signed_at' => $signatories->signed_at,
        ];
    }

    private function upsertPrintSignatory(string $receiptKey, array $values): void
    {
        $exists = DB::table('acknowledgement_receipt_signatories')
            ->where('receipt_key', $receiptKey)
            ->exists();

        if ($exists) {
            DB::table('acknowledgement_receipt_signatories')
                ->where('receipt_key', $receiptKey)
                ->update($values);

            return;
        }

        DB::table('acknowledgement_receipt_signatories')
            ->insert($values + ['created_at' => now()]);
    }

    private function printSignatoryKey(int $batchId): string
    {
        return 'printed-transaction:' . $batchId . ':print';
    }

    private function cleanNullableText($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function canViewAllPrintedTransactions(): bool
    {
        return auth()->check()
            && in_array(auth()->user()->role ?? null, self::ADMIN_ROLES, true);
    }
}
