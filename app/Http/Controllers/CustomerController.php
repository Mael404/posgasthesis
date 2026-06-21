<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CustomerController extends Controller
{
    private array $tablePresence = [];
    private array $columnPresence = [];

    public function index(Request $request): View
    {
        $request->validate([
            'tab' => 'nullable|in:list,ledger,payments',
        ]);

        $customers = $this->customerQuery()
            ->where(function ($query) {
                $query->where('status', 'Active')
                    ->orWhereNull('status')
                    ->orWhere('status', '');
            })
            ->orderBy('business_name')
            ->get();

        $inactiveCustomers = $this->customerQuery()
            ->where('status', 'Inactive')
            ->orderBy('business_name')
            ->get();

        $adminPayments = $this->adminPaymentRows();

        return view('pages.admin.customersandcredit', [
            'title' => 'Customers & Credit',
            'customers' => $customers,
            'inactiveCustomers' => $inactiveCustomers,
            'adminPayments' => $adminPayments,
            'summary' => $this->summary($customers, $inactiveCustomers),
            'canManageCredit' => auth()->check() && auth()->user()->role !== 'semiadmin',
            'missingCustomerTables' => $this->missingTables(['customers', 'customer_ledgers']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'credit_limit' => 'required|numeric|min:0',
            'payment_terms' => 'nullable|string|max:50',
            'sp_diesel' => 'nullable|numeric|min:0',
            'sp_unleaded' => 'nullable|numeric|min:0',
            'sp_premium' => 'nullable|numeric|min:0',
        ]);

        if (! $this->hasTable('customers')) {
            return $this->jsonError('Customers table is not available.');
        }

        try {
            $this->insertForExistingColumns('customers', [
                'business_name' => $validated['business_name'],
                'owner_name' => $validated['owner_name'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'credit_limit' => (float) $validated['credit_limit'],
                'current_balance' => 0,
                'payment_terms' => $validated['payment_terms'] ?? '15 Days',
                'sp_diesel' => $validated['sp_diesel'] ?? null,
                'sp_unleaded' => $validated['sp_unleaded'] ?? null,
                'sp_premium' => $validated['sp_premium'] ?? null,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer added successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:customers,id',
            'business_name' => 'required|string|max:255',
            'owner_name' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'credit_limit' => 'required|numeric|min:0',
            'payment_terms' => 'nullable|string|max:50',
            'sp_diesel' => 'nullable|numeric|min:0',
            'sp_unleaded' => 'nullable|numeric|min:0',
            'sp_premium' => 'nullable|numeric|min:0',
        ]);

        try {
            $this->updateForExistingColumns('customers', $validated['id'], [
                'business_name' => $validated['business_name'],
                'owner_name' => $validated['owner_name'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'credit_limit' => (float) $validated['credit_limit'],
                'payment_terms' => $validated['payment_terms'] ?? null,
                'sp_diesel' => $validated['sp_diesel'] ?? null,
                'sp_unleaded' => $validated['sp_unleaded'] ?? null,
                'sp_premium' => $validated['sp_premium'] ?? null,
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer profile updated successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function getLedger(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = Carbon::parse($request->from_date);
            $to = Carbon::parse($request->to_date);

            if ($from->gt($to)) {
                return response()->json([
                    'message' => 'The from date cannot be later than the to date.',
                ], 422);
            }
        }

        if (! $this->hasTable('customer_ledgers')) {
            return response()->json([]);
        }

        $ledger = DB::table('customer_ledgers')
            ->where('customer_id', $id)
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('transaction_date', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('transaction_date', '<=', $request->to_date))
            ->orderByDesc('transaction_date')
            ->when($this->hasColumn('customer_ledgers', 'created_at'), fn ($query) => $query->orderByDesc('created_at'))
            ->orderByDesc('id')
            ->get()
            ->map(fn ($entry) => $this->ledgerPayload($entry))
            ->values();

        return response()->json($ledger);
    }

    public function addPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'reference_no' => 'nullable|string|max:50',
            'remarks' => 'nullable|string|max:255',
        ]);

        if (! $this->hasTable('customer_ledgers')) {
            return $this->jsonError('Customer ledger table is not available.');
        }

        try {
            DB::transaction(function () use ($validated) {
                $customer = DB::table('customers')
                    ->where('id', $validated['customer_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $customer) {
                    throw new \RuntimeException('Customer not found.');
                }

                $amount = (float) $validated['amount'];
                $newBalance = round((float) $customer->current_balance - $amount, 2);
                $referenceNo = $validated['reference_no'] ?? null;
                $invoiceId = $validated['invoice_id'] ?? null;
                $invoiceNumber = null;

                if ($invoiceId) {
                    $invoice = $this->invoiceForPayment((int) $invoiceId, (int) $validated['customer_id']);

                    if ($invoice) {
                        $invoiceNumber = $invoice->invoice_number;
                        $referenceNo = $invoice->invoice_number;
                        $paidSoFar = round((float) $invoice->payments_received + $amount, 2);
                        $newStatus = $paidSoFar >= (float) $invoice->total_amount_due ? 'Paid' : 'Partial';

                        DB::table('invoices')
                            ->where('id', $invoice->id)
                            ->update([
                                'payments_received' => $paidSoFar,
                                'status' => $newStatus,
                                'updated_at' => now(),
                            ]);
                    }
                }

                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update([
                        'current_balance' => $newBalance,
                        'updated_at' => now(),
                    ]);

                $description = 'Payment via ' . $validated['payment_method']
                    . ($invoiceNumber ? " for Invoice {$invoiceNumber}" : '')
                    . (! empty($validated['remarks']) ? ' (' . $validated['remarks'] . ')' : '');

                $this->insertForExistingColumns('customer_ledgers', [
                    'customer_id' => $validated['customer_id'],
                    'invoice_id' => $invoiceNumber ? $invoiceId : null,
                    'transaction_date' => Carbon::parse($validated['payment_date'])->toDateString(),
                    'type' => 'Payment',
                    'reference_no' => $referenceNo,
                    'description' => $description,
                    'debit' => 0,
                    'credit' => $amount,
                    'discount' => 0,
                    'running_balance' => $newBalance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function voidPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ledger_id' => 'required|exists:customer_ledgers,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $ledger = DB::table('customer_ledgers')
                    ->where('id', $validated['ledger_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $ledger || $ledger->type !== 'Payment') {
                    throw new \RuntimeException('Invalid payment selected.');
                }

                $customer = DB::table('customers')
                    ->where('id', $ledger->customer_id)
                    ->lockForUpdate()
                    ->first();

                if ($customer) {
                    DB::table('customers')
                        ->where('id', $customer->id)
                        ->update([
                            'current_balance' => round((float) $customer->current_balance + (float) $ledger->credit, 2),
                            'updated_at' => now(),
                        ]);
                }

                if ($ledger->invoice_id && $this->hasTable('invoices')) {
                    $invoice = DB::table('invoices')->where('id', $ledger->invoice_id)->first();

                    if ($invoice) {
                        $newPaymentsReceived = max(0, round((float) $invoice->payments_received - (float) $ledger->credit, 2));
                        $newStatus = $invoice->status;

                        if ($newStatus !== 'Rolled Over') {
                            $newStatus = $newPaymentsReceived <= 0
                                ? 'Unpaid'
                                : ($newPaymentsReceived < (float) $invoice->total_amount_due ? 'Partial' : 'Paid');
                        }

                        DB::table('invoices')
                            ->where('id', $invoice->id)
                            ->update([
                                'payments_received' => $newPaymentsReceived,
                                'status' => $newStatus,
                                'updated_at' => now(),
                            ]);
                    }
                }

                DB::table('customer_ledgers')->where('id', $ledger->id)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment voided and customer balance restored.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function toggleStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:customers,id',
            'status' => 'required|in:Active,Inactive',
        ]);

        try {
            $this->updateForExistingColumns('customers', $validated['id'], [
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer is now ' . $validated['status'] . '.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function getUnpaidInvoices(int $customerId): JsonResponse
    {
        if (! $this->hasTable('invoices')) {
            return response()->json([]);
        }

        $invoices = DB::table('invoices')
            ->where('customer_id', $customerId)
            ->whereIn('status', ['Unpaid', 'Partial'])
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($invoice) => ((float) $invoice->total_amount_due - (float) ($invoice->payments_received ?? 0)) > 0)
            ->values();

        return response()->json($invoices);
    }

    public function printLedger(int $id): View
    {
        $customer = DB::table('customers')->where('id', $id)->first();

        abort_if(! $customer, 404, 'Customer not found');

        $ledger = DB::table('customer_ledgers')
            ->where('customer_id', $id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function ($entry) {
                return $this->normalizeDiscountOnLedgerEntry($entry);
            });

        $printData = $this->cleanPrintableLedger($ledger);

        return view('pages.admin.customerledgerprint', array_merge($printData, [
            'customer' => $customer,
            'ledger' => $ledger,
            'title' => 'Customer Ledger',
        ]));
    }

    public function generateSOA(Request $request): View
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $customer = DB::table('customers')->where('id', $validated['customer_id'])->first();
        $start = Carbon::parse($validated['start_date'])->toDateString();
        $end = Carbon::parse($validated['end_date'])->toDateString();

        $transactions = DB::table('customer_ledgers')
            ->where('customer_id', $validated['customer_id'])
            ->whereBetween('transaction_date', [$start, $end])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(fn ($entry) => $this->normalizeDiscountOnLedgerEntry($entry));

        $previousTotals = DB::table('customer_ledgers')
            ->where('customer_id', $validated['customer_id'])
            ->where('transaction_date', '<', $start)
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        $previousBalance = round((float) ($previousTotals->debit ?? 0) - (float) ($previousTotals->credit ?? 0), 2);
        $totalDiscount = round((float) $transactions->sum('discount'), 2);

        return view('pages.admin.customersoa', [
            'title' => 'Statement of Account',
            'customer' => $customer,
            'transactions' => $transactions,
            'previousBalance' => $previousBalance,
            'startDate' => $start,
            'endDate' => $end,
            'totalDiscount' => $totalDiscount,
        ]);
    }

    private function customerQuery()
    {
        return DB::table('customers');
    }

    private function adminPaymentRows()
    {
        if (! $this->hasTable('customer_ledgers') || ! $this->hasTable('customers')) {
            return collect();
        }

        return DB::table('customer_ledgers')
            ->join('customers', 'customer_ledgers.customer_id', '=', 'customers.id')
            ->select('customer_ledgers.*', 'customers.business_name', 'customers.owner_name')
            ->where('customer_ledgers.type', 'Payment')
            ->where(function ($query) {
                $query->whereNull('customer_ledgers.description')
                    ->orWhere('customer_ledgers.description', 'NOT LIKE', '%Verified from Shift%');
            })
            ->orderByDesc('customer_ledgers.created_at')
            ->orderByDesc('customer_ledgers.id')
            ->get();
    }

    private function summary($customers, $inactiveCustomers): array
    {
        return [
            'active_count' => $customers->count(),
            'inactive_count' => $inactiveCustomers->count(),
            'credit_limit' => round((float) $customers->sum('credit_limit'), 2),
            'receivables' => round((float) $customers->sum(fn ($customer) => max((float) $customer->current_balance, 0)), 2),
            'advance_balance' => round((float) $customers->sum(fn ($customer) => max(abs((float) $customer->current_balance), 0) * ((float) $customer->current_balance < 0 ? 1 : 0)), 2),
            'over_limit_count' => $customers->filter(fn ($customer) => (float) $customer->current_balance > (float) $customer->credit_limit)->count(),
        ];
    }

    private function invoiceForPayment(int $invoiceId, int $customerId)
    {
        if (! $this->hasTable('invoices')) {
            return null;
        }

        return DB::table('invoices')
            ->where('id', $invoiceId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['Unpaid', 'Partial'])
            ->first();
    }

    private function ledgerPayload($entry): array
    {
        $transactionDate = $entry->transaction_date ? Carbon::parse($entry->transaction_date) : null;
        $transactionDateTime = 'N/A';

        if ($transactionDate) {
            $hasTransactionTime = $transactionDate->format('H:i:s') !== '00:00:00';

            if (! $hasTransactionTime && ! empty($entry->created_at)) {
                $createdAt = Carbon::parse($entry->created_at);
                $transactionDate = $transactionDate->setTime(
                    (int) $createdAt->format('H'),
                    (int) $createdAt->format('i'),
                    (int) $createdAt->format('s')
                );
            }

            $transactionDateTime = $transactionDate->format('M d, Y - h:i A');
        }

        return [
            'id' => (int) $entry->id,
            'transaction_date' => $entry->transaction_date,
            'transaction_datetime' => $transactionDateTime,
            'date_display' => $transactionDateTime,
            'type' => $entry->type ?? 'Ledger',
            'reference_no' => $entry->reference_no,
            'or_number' => $entry->or_number ?? null,
            'description' => $entry->description,
            'debit' => round((float) ($entry->debit ?? 0), 2),
            'credit' => round((float) ($entry->credit ?? 0), 2),
            'discount' => round((float) ($entry->discount ?? 0), 2),
            'running_balance' => round((float) ($entry->running_balance ?? 0), 2),
        ];
    }

    private function normalizeDiscountOnLedgerEntry($entry)
    {
        $entry->discount_amount = (float) ($entry->discount ?? 0);

        if (preg_match('/\((?:Total )?Discount:\s*(?:₱|PHP)?\s*([\d,.]+)\)/i', (string) $entry->description, $matches)) {
            if ($entry->discount_amount == 0) {
                $entry->discount_amount = (float) str_replace(',', '', $matches[1]);
            }

            $entry->description = trim(preg_replace('/\((?:Total )?Discount:\s*(?:₱|PHP)?\s*[\d,.]+\)/i', '', (string) $entry->description));
        }

        $entry->discount = $entry->discount_amount;

        return $entry;
    }

    private function cleanPrintableLedger($ledger): array
    {
        $skipIds = [];
        $ledgerCollection = collect($ledger)->values();
        $reversalTypes = ['Void', 'Edit Reversal'];
        $moneyEquals = fn ($left, $right) => abs(round((float) $left, 2) - round((float) $right, 2)) <= 0.004;
        $entryDate = fn ($entry) => Carbon::parse($entry->transaction_date)->toDateString();

        foreach ($ledgerCollection as $reversal) {
            if (! in_array($reversal->type, $reversalTypes, true) || (float) ($reversal->credit ?? 0) <= 0) {
                continue;
            }

            $candidates = $ledgerCollection->filter(function ($entry) use ($reversal, $skipIds, $moneyEquals) {
                return ! in_array((int) $entry->id, $skipIds, true)
                    && (float) ($entry->debit ?? 0) > 0
                    && (float) ($entry->credit ?? 0) <= 0
                    && ($entry->type ?? null) !== 'Payment'
                    && $moneyEquals($entry->debit, $reversal->credit);
            });

            $sameDateCandidates = $candidates->filter(fn ($entry) => $entryDate($entry) === $entryDate($reversal));
            $original = $sameDateCandidates->filter(fn ($entry) => (int) $entry->id < (int) $reversal->id)->last()
                ?? $candidates->filter(fn ($entry) => (int) $entry->id < (int) $reversal->id)->last()
                ?? $sameDateCandidates->filter(fn ($entry) => (int) $entry->id > (int) $reversal->id)->first();

            if ($original) {
                $skipIds[] = (int) $reversal->id;
                $skipIds[] = (int) $original->id;
            }
        }

        $filteredLedger = $ledgerCollection
            ->reject(fn ($entry) => in_array((int) $entry->id, array_unique($skipIds), true))
            ->values();

        $runningBalance = 0;

        foreach ($filteredLedger as $entry) {
            $runningBalance = round($runningBalance + (float) ($entry->debit ?? 0) - (float) ($entry->credit ?? 0), 2);
            $entry->print_running_balance = $runningBalance;
        }

        return [
            'filteredLedger' => $filteredLedger,
            'cleanTotalDebits' => round((float) $filteredLedger->sum('debit'), 2),
            'cleanTotalCredits' => round((float) $filteredLedger->sum('credit'), 2),
            'currentBalance' => round($runningBalance, 2),
            'totalDiscount' => round((float) $filteredLedger->sum('discount'), 2),
        ];
    }

    private function insertForExistingColumns(string $table, array $values): void
    {
        DB::table($table)->insert($this->onlyExistingColumns($table, $values));
    }

    private function updateForExistingColumns(string $table, int $id, array $values): void
    {
        DB::table($table)->where('id', $id)->update($this->onlyExistingColumns($table, $values));
    }

    private function onlyExistingColumns(string $table, array $values): array
    {
        return collect($values)
            ->filter(fn ($value, $column) => $this->hasColumn($table, $column))
            ->all();
    }

    private function missingTables(array $tables): array
    {
        return collect($tables)
            ->reject(fn ($table) => $this->hasTable($table))
            ->values()
            ->all();
    }

    private function hasTable(string $table): bool
    {
        if (! array_key_exists($table, $this->tablePresence)) {
            $this->tablePresence[$table] = Schema::hasTable($table);
        }

        return $this->tablePresence[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        if (! array_key_exists($key, $this->columnPresence)) {
            $this->columnPresence[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }

        return $this->columnPresence[$key];
    }

    private function jsonError(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 422);
    }
}
