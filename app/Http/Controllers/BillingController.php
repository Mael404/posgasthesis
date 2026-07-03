<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BillingController extends Controller
{
    private array $tablePresence = [];
    private array $columnPresence = [];

    public function index(Request $request): View
    {
        $missingBillingTables = $this->missingTables(['customers', 'customer_ledgers', 'invoices']);
        $canManageBilling = auth()->check() && auth()->user()->role !== 'semiadmin';
        $perPageOptions = [10, 50, 100];
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 10;
        $activeBillingTab = in_array($request->query('tab'), ['unpaid', 'partial', 'paid', 'receipts'], true)
            ? $request->query('tab')
            : 'unpaid';
        $invoiceTableTab = in_array($activeBillingTab, ['unpaid', 'partial', 'paid'], true) ? $activeBillingTab : 'unpaid';
        $invoiceSearch = trim((string) $request->query('invoice_search', ''));
        $customers = collect();
        $invoices = collect();
        $summaryInvoices = collect();
        $acknowledgementReceipts = collect();

        if ($missingBillingTables === []) {
            $customers = DB::table('customers')
                ->where(function ($query) {
                    $query->where('status', 'Active')
                        ->orWhereNull('status')
                        ->orWhere('status', '');
                })
                ->where(function ($query) {
                    $query->where('owner_name', '!=', 'Celyn Grace')
                        ->orWhereNull('owner_name');
                })
                ->orderBy('business_name')
                ->get();

            $summaryInvoices = $this->billingInvoiceQuery()
                ->orderByDesc('invoices.created_at')
                ->get()
                ->map(fn ($invoice) => $this->refreshInvoiceTotalsFromLedger($invoice, null, true, true));

            $invoiceQuery = $this->billingInvoiceQuery();
            $this->applyInvoiceTabFilter($invoiceQuery, $invoiceTableTab);

            if ($invoiceSearch !== '') {
                $invoiceQuery->where(function ($query) use ($invoiceSearch) {
                    $search = '%' . $invoiceSearch . '%';

                    $query->where('invoices.invoice_number', 'like', $search)
                        ->orWhere('customers.business_name', 'like', $search)
                        ->orWhere('customers.owner_name', 'like', $search);
                });
            }

            $invoices = $invoiceQuery
                ->orderByDesc('invoices.created_at')
                ->paginate($perPage, ['*'], 'invoice_page')
                ->withQueryString();

            $invoices->getCollection()->transform(
                fn ($invoice) => $this->refreshInvoiceTotalsFromLedger($invoice, null, true, true)
            );

            $acknowledgementReceipts = $this->acknowledgementReceiptRowsForInvoices($summaryInvoices);
        }

        return view('pages.admin.billingandinvoices', [
            'title' => 'Billing & Invoices',
            'customers' => $customers,
            'invoices' => $invoices,
            'summaryInvoices' => $summaryInvoices,
            'acknowledgementReceipts' => $acknowledgementReceipts,
            'canManageBilling' => $canManageBilling,
            'missingBillingTables' => $missingBillingTables,
            'activeBillingTab' => $activeBillingTab,
            'invoiceSearch' => $invoiceSearch,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function staffIndex(Request $request): View
    {
        $missingBillingTables = $this->missingTables(['customers', 'customer_ledgers', 'invoices']);
        $perPageOptions = [10, 50, 100];
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 10;
        $activeBillingTab = in_array($request->query('tab'), ['unpaid', 'partial'], true)
            ? $request->query('tab')
            : 'unpaid';
        $invoiceSearch = trim((string) $request->query('invoice_search', ''));
        $summaryInvoices = collect();
        $invoices = new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            1,
            [
                'path' => $request->url(),
                'pageName' => 'invoice_page',
            ]
        );

        if ($missingBillingTables === []) {
            $summaryInvoices = $this->billingInvoiceQuery()
                ->orderByDesc('invoices.created_at')
                ->get()
                ->map(fn ($invoice) => $this->refreshInvoiceTotalsFromLedger($invoice, null, true, true))
                ->filter(fn ($invoice) => in_array($invoice->status, ['Unpaid', 'Partial'], true))
                ->values();

            $filteredInvoices = $summaryInvoices
                ->filter(fn ($invoice) => $activeBillingTab === 'partial'
                    ? $invoice->status === 'Partial'
                    : $invoice->status === 'Unpaid')
                ->values();

            if ($invoiceSearch !== '') {
                $search = strtolower($invoiceSearch);

                $filteredInvoices = $filteredInvoices
                    ->filter(function ($invoice) use ($search) {
                        return str_contains(strtolower((string) ($invoice->invoice_number ?? '')), $search)
                            || str_contains(strtolower((string) ($invoice->business_name ?? '')), $search)
                            || str_contains(strtolower((string) ($invoice->owner_name ?? '')), $search);
                    })
                    ->values();
            }

            $total = $filteredInvoices->count();
            $page = max((int) $request->query('invoice_page', 1), 1);
            $lastPage = max((int) ceil($total / $perPage), 1);
            $page = min($page, $lastPage);

            $invoices = new LengthAwarePaginator(
                $filteredInvoices->slice(($page - 1) * $perPage, $perPage)->values(),
                $total,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'pageName' => 'invoice_page',
                ]
            );
            $invoices->appends($request->except('invoice_page'));
        }

        return view('pages.staff.billing', [
            'title' => 'Billing',
            'invoices' => $invoices,
            'summaryInvoices' => $summaryInvoices,
            'missingBillingTables' => $missingBillingTables,
            'activeBillingTab' => $activeBillingTab,
            'invoiceSearch' => $invoiceSearch,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'rollover_invoices' => 'nullable|array',
            'rollover_invoices.*' => 'integer|exists:invoices,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $customerId = (int) $validated['customer_id'];
                $start = Carbon::parse($validated['period_start'])->toDateString();
                $end = Carbon::parse($validated['period_end'])->toDateString();
                $requestedRolloverInvoiceIds = array_values(array_unique((array) ($validated['rollover_invoices'] ?? [])));

                $customer = DB::table('customers')->where('id', $customerId)->lockForUpdate()->first();

                if (! $customer) {
                    throw new \RuntimeException('Customer not found.');
                }

                $periodLedger = DB::table('customer_ledgers')
                    ->where('customer_id', $customerId)
                    ->whereBetween('transaction_date', [$start, $end])
                    ->whereNull('invoice_id')
                    ->lockForUpdate()
                    ->orderBy('transaction_date')
                    ->orderBy('id')
                    ->get();

                if ($periodLedger->isEmpty() && $requestedRolloverInvoiceIds === []) {
                    throw new \RuntimeException('Cannot generate invoice: No new transactions or rollovers found for this period.');
                }

                $cleanLedger = $this->ledgerRowsWithoutReversedCharges($periodLedger);
                $billableLedger = $cleanLedger->filter(fn ($row) => $this->isBillableChargeRow($row));
                $billableLedgerIds = $billableLedger->pluck('id')->map(fn ($id) => (int) $id)->all();
                $previousBalance = 0.0;
                $rolledOverAmount = 0.0;
                $rolledOverNumbers = [];

                if ($requestedRolloverInvoiceIds !== []) {
                    $invoicesToRoll = DB::table('invoices')
                        ->whereIn('id', $requestedRolloverInvoiceIds)
                        ->where('customer_id', $customerId)
                        ->whereIn('status', ['Unpaid', 'Partial'])
                        ->lockForUpdate()
                        ->get();

                    if ($invoicesToRoll->count() !== count($requestedRolloverInvoiceIds)) {
                        throw new \RuntimeException('Some selected rollover invoices are no longer available. Please refresh and try again.');
                    }

                    foreach ($invoicesToRoll as $oldInvoice) {
                        $remainingDebt = $this->moneyValue((float) $oldInvoice->total_amount_due - (float) $oldInvoice->payments_received);
                        $rolledOverAmount += $remainingDebt;
                        $rolledOverNumbers[] = $oldInvoice->invoice_number;

                        DB::table('invoices')->where('id', $oldInvoice->id)->update([
                            'status' => 'Rolled Over',
                            'updated_at' => now(),
                        ]);
                    }
                }

                $previousBalance += $rolledOverAmount;
                $currentCharges = $this->moneyValue($billableLedger->sum('debit'));

                if ($currentCharges <= 0 && $rolledOverNumbers === []) {
                    throw new \RuntimeException('Cannot generate invoice: There are no new fuel charges in this period to bill.');
                }

                $totalDiscounts = $billableLedger->sum(function ($row) {
                    $discount = (float) ($row->discount ?? 0);

                    if ($discount == 0 && preg_match('/\((?:Total )?Discount:\s*(?:PHP|P)?\s*([\d,.]+)\)/i', (string) $row->description, $matches)) {
                        $discount = (float) str_replace(',', '', $matches[1]);
                    }

                    return $discount;
                });

                $paymentsReceived = 0.0;
                $totalAmountDue = $this->moneyValue($previousBalance + $currentCharges);
                $status = $totalAmountDue <= 0 ? 'Paid' : 'Unpaid';
                $invoiceNumber = $this->nextInvoiceNumberForDate(Carbon::now());
                $terms = (int) filter_var((string) ($customer->payment_terms ?? '15 Days'), FILTER_SANITIZE_NUMBER_INT) ?: 15;
                $dueDate = Carbon::parse($end)->addDays($terms)->toDateString();

                $invoiceId = DB::table('invoices')->insertGetId([
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $customerId,
                    'period_start' => $start,
                    'period_end' => $end,
                    'previous_balance' => $this->moneyValue($previousBalance),
                    'rolled_over_from' => $rolledOverNumbers === [] ? null : implode(', ', $rolledOverNumbers),
                    'current_charges' => $currentCharges,
                    'total_discounts' => $this->moneyValue($totalDiscounts),
                    'payments_received' => $paymentsReceived,
                    'total_amount_due' => $totalAmountDue,
                    'due_date' => $dueDate,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($billableLedgerIds !== []) {
                    DB::table('customer_ledgers')
                        ->whereIn('id', $billableLedgerIds)
                        ->update([
                            'invoice_id' => $invoiceId,
                            'updated_at' => now(),
                        ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function markPaid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:invoices,id',
        ]);

        DB::table('invoices')->where('id', $validated['id'])->update([
            'status' => 'Paid',
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice marked as paid.',
        ]);
    }

    public function updateInvoiceRows(Request $request, int $id): JsonResponse
    {
        if (! $this->canEditBillingRows()) {
            return $this->jsonError('You are not allowed to edit billing rows.', 403);
        }

        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.id' => 'required|integer|exists:customer_ledgers,id',
            'rows.*.transaction_date' => 'required|date',
            'rows.*.or_number' => 'nullable|string|max:255',
            'rows.*.description' => 'nullable|string|max:255',
            'rows.*.discount' => 'nullable|numeric|min:0',
            'rows.*.debit' => 'required|numeric|min:0',
        ]);

        $updatedRows = 0;

        try {
            DB::transaction(function () use ($validated, $id, &$updatedRows) {
                $invoice = DB::table('invoices')->where('id', $id)->lockForUpdate()->first();

                if (! $invoice) {
                    throw new \RuntimeException('Invoice not found.');
                }

                if ($invoice->status === 'Rolled Over') {
                    throw new \RuntimeException('Rolled over invoices cannot be edited from this print view.');
                }

                $payloadRows = collect($validated['rows'])->mapWithKeys(fn ($row) => [(int) $row['id'] => $row]);
                $ledgerRows = DB::table('customer_ledgers')
                    ->where('invoice_id', $invoice->id)
                    ->whereIn('id', $payloadRows->keys()->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($ledgerRows->count() !== $payloadRows->count()) {
                    throw new \RuntimeException('One or more rows do not belong to this invoice.');
                }

                foreach ($payloadRows as $ledgerId => $row) {
                    $ledger = $ledgerRows->get($ledgerId);

                    if (! $ledger || ! $this->isBillableChargeRow($ledger)) {
                        throw new \RuntimeException('Only billing charge rows can be edited here.');
                    }

                    $oldValues = [
                        'transaction_date' => Carbon::parse($ledger->transaction_date)->toDateString(),
                        'or_number' => $ledger->or_number,
                        'description' => $ledger->description,
                        'discount' => $this->moneyValue($ledger->discount ?? 0),
                        'debit' => $this->moneyValue($ledger->debit ?? 0),
                    ];

                    $newValues = [
                        'transaction_date' => Carbon::parse($row['transaction_date'])->toDateString(),
                        'or_number' => $this->cleanReferenceNo($row['or_number'] ?? null),
                        'description' => $this->cleanNullableText($row['description'] ?? null),
                        'discount' => $this->moneyValue($row['discount'] ?? 0),
                        'debit' => $this->moneyValue($row['debit'] ?? 0),
                    ];

                    $changedFields = $this->changedBillingFields($oldValues, $newValues);

                    if ($changedFields === []) {
                        continue;
                    }

                    $sale = $this->findMatchingSaleForLedger($ledger);
                    $oldSaleValues = $sale ? [
                        'id' => $sale->id,
                        'or_number' => $sale->or_number,
                        'created_at' => $sale->created_at,
                        'amount' => $this->moneyValue($sale->amount ?? 0),
                        'discount' => $this->moneyValue($sale->discount ?? 0),
                        'manual_discount' => $this->moneyValue($sale->manual_discount ?? 0),
                    ] : null;

                    DB::table('customer_ledgers')
                        ->where('id', $ledger->id)
                        ->update($newValues + ['updated_at' => now()]);

                    $salesNewValues = [];

                    if ($sale) {
                        if (in_array('or_number', $changedFields, true)) {
                            $salesNewValues['or_number'] = $newValues['or_number'];
                        }

                        if (in_array('transaction_date', $changedFields, true)) {
                            $salesNewValues['created_at'] = Carbon::parse($newValues['transaction_date'])
                                ->setTimeFrom(Carbon::parse($sale->created_at))
                                ->format('Y-m-d H:i:s');
                        }

                        if (in_array('debit', $changedFields, true)) {
                            $salesNewValues['amount'] = $newValues['debit'];
                        }

                        if (in_array('discount', $changedFields, true)) {
                            $salesNewValues['discount'] = $newValues['discount'];
                            $salesNewValues['manual_discount'] = 0;
                        }

                        if ($salesNewValues !== []) {
                            DB::table('sales')
                                ->where('id', $sale->id)
                                ->update($salesNewValues + ['updated_at' => now()]);
                        }
                    }

                    $this->insertBillingEditLog([
                        'invoice_id' => $invoice->id,
                        'customer_ledger_id' => $ledger->id,
                        'sale_id' => $sale->id ?? null,
                        'edited_by' => auth()->id(),
                        'action' => 'row_update',
                        'changed_fields' => $this->encodeLogValue($changedFields),
                        'old_values' => $this->encodeLogValue([
                            'ledger' => $oldValues,
                            'sale' => $oldSaleValues,
                        ]),
                        'new_values' => $this->encodeLogValue([
                            'ledger' => $newValues,
                            'sale' => $salesNewValues ?: null,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $updatedRows++;
                }

                if ($updatedRows > 0) {
                    $this->recomputeCustomerLedgerBalances((int) $invoice->customer_id);
                    $this->recalculateInvoiceTotals((int) $invoice->id);
                }
            });

            return response()->json([
                'success' => true,
                'message' => $updatedRows > 0
                    ? "{$updatedRows} billing row(s) updated successfully."
                    : 'No billing row changes were detected.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function deleteInvoiceRow(Request $request, int $id): JsonResponse
    {
        if (! $this->canEditBillingRows()) {
            return $this->jsonError('You are not allowed to delete billing rows.', 403);
        }

        $validated = $request->validate([
            'row_id' => 'required|integer|exists:customer_ledgers,id',
        ]);

        try {
            DB::transaction(function () use ($validated, $id) {
                $invoice = DB::table('invoices')->where('id', $id)->lockForUpdate()->first();

                if (! $invoice) {
                    throw new \RuntimeException('Invoice not found.');
                }

                if ($invoice->status === 'Rolled Over') {
                    throw new \RuntimeException('Rolled over invoices cannot be edited from this print view.');
                }

                $ledger = DB::table('customer_ledgers')
                    ->where('id', (int) $validated['row_id'])
                    ->where('invoice_id', $invoice->id)
                    ->lockForUpdate()
                    ->first();

                if (! $ledger) {
                    throw new \RuntimeException('The selected row does not belong to this invoice.');
                }

                if (! $this->isBillableChargeRow($ledger)) {
                    throw new \RuntimeException('Only billing charge rows can be deleted here.');
                }

                $sale = $this->findMatchingSaleForLedger($ledger);
                $ledgerValues = get_object_vars($ledger);
                $saleValues = $sale ? get_object_vars($sale) : null;

                if ($this->hasTable('deleted_billing_rows')) {
                    DB::table('deleted_billing_rows')->insert([
                        'invoice_id' => $invoice->id,
                        'customer_id' => $ledger->customer_id,
                        'customer_ledger_id' => $ledger->id,
                        'sale_id' => $sale->id ?? null,
                        'deleted_by' => auth()->id(),
                        'transaction_date' => $ledger->transaction_date,
                        'reference_no' => $ledger->reference_no,
                        'description' => $ledger->description,
                        'debit' => $this->moneyValue($ledger->debit ?? 0),
                        'discount' => $this->moneyValue($ledger->discount ?? 0),
                        'delete_reason' => 'Deleted from billing print',
                        'ledger_row' => $this->encodeLogValue($ledgerValues),
                        'sale_row' => $saleValues ? $this->encodeLogValue($saleValues) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->insertBillingEditLog([
                    'invoice_id' => $invoice->id,
                    'customer_ledger_id' => $ledger->id,
                    'sale_id' => $sale->id ?? null,
                    'edited_by' => auth()->id(),
                    'action' => 'row_delete',
                    'changed_fields' => $this->encodeLogValue(['deleted']),
                    'old_values' => $this->encodeLogValue([
                        'ledger' => $ledgerValues,
                        'sale' => $saleValues,
                    ]),
                    'new_values' => $this->encodeLogValue(null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($sale) {
                    DB::table('sales')->where('id', $sale->id)->delete();
                }

                DB::table('customer_ledgers')->where('id', $ledger->id)->delete();
                $this->recomputeCustomerLedgerBalances((int) $invoice->customer_id);
                $this->recalculateInvoiceTotals((int) $invoice->id);
            });

            return response()->json([
                'success' => true,
                'message' => 'Billing row deleted and archived successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function printInvoice(int $id): View
    {
        $invoice = DB::table('invoices')->where('id', $id)->first();
        abort_if(! $invoice, 404, 'Invoice not found');

        $customer = DB::table('customers')->where('id', $invoice->customer_id)->first();
        abort_if(! $customer, 404, 'Customer not found');

        $filteredTransactions = $this->billingRowsForInvoice($invoice);
        $invoice = $this->refreshInvoiceTotalsFromLedger($invoice, $filteredTransactions, true, true);

        $invoicePaymentRows = $filteredTransactions->filter(fn ($row) => $this->isPaymentAppliedToInvoice($row, $invoice));
        $payments = $invoicePaymentRows
            ->map(fn ($row) => (object) [
                'date' => $row->transaction_date,
                'amount' => $row->credit,
                'desc' => $row->description,
            ])
            ->sortBy('date')
            ->values();

        $invoicePaymentsReceived = $this->moneyValue($invoicePaymentRows->sum('credit'));
        $periodPayments = $invoicePaymentsReceived;

        foreach ($filteredTransactions as $row) {
            $row->raw_description = preg_replace('/^\s*Edited Credit Charge\s*:/i', 'Credit Charge:', $row->description ?? '');
            $row->description = $row->raw_description;
            $row->discount_amount = (float) ($row->discount ?? 0);

            if ($row->discount_amount == 0 && preg_match('/\((?:Total )?Discount:\s*(?:PHP|P)?\s*([\d,.]+)\)/i', (string) $row->description, $matches)) {
                $row->discount_amount = (float) str_replace(',', '', $matches[1]);
                $row->description = trim(preg_replace('/\((?:Total )?Discount:\s*(?:PHP|P)?\s*[\d,.]+\)/i', '', (string) $row->description));
            }
        }

        $rolledInvoices = collect();
        $rolledTotal = 0.0;

        if (! empty($invoice->rolled_over_from)) {
            $rolledNumbers = array_map('trim', explode(',', $invoice->rolled_over_from));
            $rolledInvoices = DB::table('invoices')->whereIn('invoice_number', $rolledNumbers)->get();

            foreach ($rolledInvoices as $rolledInvoice) {
                $rolledInvoice->remaining_balance = $this->moneyValue((float) $rolledInvoice->total_amount_due - (float) $rolledInvoice->payments_received);
                $rolledTotal += $rolledInvoice->remaining_balance;
            }
        }

        $unbilledCarryOver = $this->moneyValue((float) $invoice->previous_balance - $rolledTotal);
        $printSignatories = $this->billingPrintSignatories((int) $invoice->id);
        $signatorySaveUrl = request()->routeIs('staff.billing.*')
            ? route('staff.billing.print.signatories', $invoice->id)
            : route('admin.billing.print.signatories', $invoice->id);
        $signatorySignedAt = $printSignatories->signed_at ?? now();
        $title = $invoice->invoice_number . ' - Billing Statement';

        return view('pages.admin.billinginvoiceprint', compact(
            'invoice',
            'customer',
            'filteredTransactions',
            'periodPayments',
            'payments',
            'invoicePaymentsReceived',
            'rolledInvoices',
            'unbilledCarryOver',
            'printSignatories',
            'signatorySaveUrl',
            'signatorySignedAt',
            'title'
        ));
    }

    public function savePrintInvoiceSignatories(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'prepared_by' => 'nullable|string|max:255',
            'acknowledged_by' => 'nullable|string|max:255',
        ]);

        $invoice = DB::table('invoices')->where('id', $id)->first();

        if (! $invoice) {
            return $this->jsonError('Invoice not found.', 404);
        }

        if (! $this->hasTable('acknowledgement_receipt_signatories')) {
            return $this->jsonError('The signatories table is not ready. Please run php artisan migrate.', 500);
        }

        $signedAt = now();
        $receiptKey = $this->billingPrintSignatoryKey((int) $invoice->id);
        $values = [
            'receipt_key' => $receiptKey,
            'invoice_id' => (int) $invoice->id,
            'payment_ledger_id' => null,
            'received_by' => $this->cleanNullableText($validated['prepared_by'] ?? null),
            'paid_by' => $this->cleanNullableText($validated['acknowledged_by'] ?? null),
            'signed_at' => $signedAt,
            'saved_by' => auth()->id(),
            'updated_at' => now(),
        ];

        $this->upsertReceiptSignatory($receiptKey, $values);

        return response()->json([
            'success' => true,
            'message' => 'Invoice signatories saved.',
            'prepared_by' => $values['received_by'] ?? '',
            'acknowledged_by' => $values['paid_by'] ?? '',
            'signed_at' => $signedAt->format('F d, Y h:i A'),
            'signed_at_input' => $signedAt->format('Y-m-d\TH:i'),
        ]);
    }

    public function printAcknowledgementReceipt(int $id, ?int $paymentId = null): View
    {
        $invoice = DB::table('invoices')->where('id', $id)->first();
        abort_if(! $invoice, 404, 'Invoice not found');
        abort_if(! in_array($invoice->status, ['Paid', 'Partial'], true), 404, 'Acknowledgement receipts are only available for paid or partially paid invoices.');

        $customer = DB::table('customers')->where('id', $invoice->customer_id)->first();
        abort_if(! $customer, 404, 'Customer not found');

        $allPayments = $this->paymentRowsForInvoice($invoice);
        $selectedPayment = $paymentId !== null
            ? $allPayments->first(fn ($payment) => (int) $payment->id === (int) $paymentId)
            : $allPayments->last();

        if ($paymentId !== null && ! $selectedPayment) {
            abort(404, 'Payment not found for this invoice.');
        }

        $payments = collect();
        $receiptAmount = 0.0;
        $receiptDate = $invoice->updated_at ?? now();
        $remainingBalance = max($this->moneyValue((float) $invoice->total_amount_due - (float) $invoice->payments_received), 0);
        $isFullPayment = $remainingBalance <= 0.004;

        if ($selectedPayment) {
            $payments = collect([$selectedPayment]);
            $receiptAmount = $this->moneyValue($selectedPayment->credit);
            $receiptDate = $selectedPayment->transaction_date ?? $receiptDate;

            $cumulativePaid = 0.0;

            foreach ($allPayments as $payment) {
                $cumulativePaid = $this->moneyValue($cumulativePaid + (float) $payment->credit);

                if ((int) $payment->id === (int) $selectedPayment->id) {
                    break;
                }
            }

            $remainingBalance = max($this->moneyValue((float) $invoice->total_amount_due - $cumulativePaid), 0);
            $isFullPayment = $remainingBalance <= 0.004;
        } else {
            $receiptAmount = $this->moneyValue($invoice->payments_received ?: $invoice->total_amount_due);
        }

        $receiptPaymentId = $selectedPayment->id ?? null;
        $receiptSignatories = $this->acknowledgementReceiptSignatories((int) $invoice->id, $receiptPaymentId ? (int) $receiptPaymentId : null);
        $signatorySaveUrl = $receiptPaymentId
            ? route('admin.billing.acknowledgementReceipt.signatories.payment', ['id' => $invoice->id, 'paymentId' => $receiptPaymentId])
            : route('admin.billing.acknowledgementReceipt.signatories', $invoice->id);
        $receiptSignedAt = $receiptSignatories->signed_at ?? now();
        $title = 'Acknowledgement Receipt - ' . $invoice->invoice_number;

        return view('pages.admin.billingacknowledgementreceipt', compact(
            'invoice',
            'customer',
            'payments',
            'receiptAmount',
            'receiptDate',
            'isFullPayment',
            'remainingBalance',
            'receiptSignatories',
            'signatorySaveUrl',
            'receiptSignedAt',
            'title'
        ));
    }

    public function saveAcknowledgementReceiptSignatories(Request $request, int $id, ?int $paymentId = null): JsonResponse
    {
        $validated = $request->validate([
            'received_by' => 'nullable|string|max:255',
            'paid_by' => 'nullable|string|max:255',
        ]);

        $invoice = DB::table('invoices')->where('id', $id)->first();

        if (! $invoice) {
            return $this->jsonError('Invoice not found.', 404);
        }

        if ($paymentId !== null) {
            $payment = $this->paymentRowsForInvoice($invoice)
                ->first(fn ($payment) => (int) $payment->id === (int) $paymentId);

            if (! $payment) {
                return $this->jsonError('Payment not found for this invoice.', 404);
            }
        }

        if (! $this->hasTable('acknowledgement_receipt_signatories')) {
            return $this->jsonError('The acknowledgement receipt signatories table is not ready. Please run php artisan migrate.', 500);
        }

        $signedAt = now();
        $receiptKey = $this->acknowledgementReceiptKey((int) $invoice->id, $paymentId ? (int) $paymentId : null);
        $values = [
            'receipt_key' => $receiptKey,
            'invoice_id' => (int) $invoice->id,
            'payment_ledger_id' => $paymentId ? (int) $paymentId : null,
            'received_by' => $this->cleanNullableText($validated['received_by'] ?? null),
            'paid_by' => $this->cleanNullableText($validated['paid_by'] ?? null),
            'signed_at' => $signedAt,
            'saved_by' => auth()->id(),
            'updated_at' => now(),
        ];

        $this->upsertReceiptSignatory($receiptKey, $values);

        return response()->json([
            'success' => true,
            'message' => 'Acknowledgement receipt signatories saved.',
            'received_by' => $values['received_by'] ?? '',
            'paid_by' => $values['paid_by'] ?? '',
            'signed_at' => $signedAt->format('F d, Y h:i A'),
            'signed_at_input' => $signedAt->format('Y-m-d\TH:i'),
        ]);
    }

    public function updateAcknowledgementReceiptIssued(Request $request, int $id, ?int $paymentId = null): JsonResponse
    {
        $request->validate([
            'is_issued' => 'required|boolean',
        ]);

        $invoice = DB::table('invoices')->where('id', $id)->first();

        if (! $invoice) {
            return $this->jsonError('Invoice not found.', 404);
        }

        if (! in_array($invoice->status, ['Paid', 'Partial'], true)) {
            return $this->jsonError('Acknowledgement receipts are only available for paid or partially paid invoices.', 422);
        }

        if ($paymentId !== null) {
            $payment = $this->paymentRowsForInvoice($invoice)
                ->first(fn ($payment) => (int) $payment->id === (int) $paymentId);

            if (! $payment) {
                return $this->jsonError('Payment not found for this invoice.', 404);
            }
        }

        if (! $this->acknowledgementReceiptIssuedColumnsReady()) {
            return $this->jsonError('The acknowledgement receipt issued fields are not ready. Please run php artisan migrate.', 500);
        }

        $isIssued = filter_var($request->input('is_issued'), FILTER_VALIDATE_BOOLEAN);
        $receiptKey = $this->acknowledgementReceiptKey((int) $invoice->id, $paymentId ? (int) $paymentId : null);
        $existingReceipt = DB::table('acknowledgement_receipt_signatories')
            ->where('receipt_key', $receiptKey)
            ->first();
        $issuedAt = null;
        $issuedBy = null;

        if ($isIssued) {
            $issuedAt = $existingReceipt && ! empty($existingReceipt->issued_at)
                ? $existingReceipt->issued_at
                : now();
            $issuedBy = $existingReceipt && ! empty($existingReceipt->issued_by)
                ? $existingReceipt->issued_by
                : auth()->id();
        }

        $values = [
            'receipt_key' => $receiptKey,
            'invoice_id' => (int) $invoice->id,
            'payment_ledger_id' => $paymentId ? (int) $paymentId : null,
            'is_issued' => $isIssued ? 1 : 0,
            'issued_at' => $issuedAt,
            'issued_by' => $issuedBy,
            'updated_at' => now(),
        ];

        $this->upsertReceiptSignatory($receiptKey, $values);

        return response()->json([
            'success' => true,
            'message' => $isIssued ? 'Acknowledgement receipt marked as issued.' : 'Acknowledgement receipt marked as pending.',
            'is_issued' => $isIssued,
            'issued_at' => $issuedAt ? Carbon::parse($issuedAt)->format('M d, Y h:i A') : '',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:invoices,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $invoiceToDelete = DB::table('invoices')->where('id', $validated['id'])->lockForUpdate()->first();

                if (! $invoiceToDelete) {
                    throw new \RuntimeException('Invoice not found.');
                }

                if (! empty($invoiceToDelete->rolled_over_from)) {
                    $rolledNumbers = array_map('trim', explode(',', $invoiceToDelete->rolled_over_from));
                    $oldInvoices = DB::table('invoices')->whereIn('invoice_number', $rolledNumbers)->lockForUpdate()->get();

                    foreach ($oldInvoices as $oldInvoice) {
                        $paidSoFar = (float) $oldInvoice->payments_received;
                        $totalDue = (float) $oldInvoice->total_amount_due;
                        $restoredStatus = 'Unpaid';

                        if ($paidSoFar > 0 && $paidSoFar < $totalDue) {
                            $restoredStatus = 'Partial';
                        } elseif ($paidSoFar >= $totalDue) {
                            $restoredStatus = 'Paid';
                        }

                        DB::table('invoices')->where('id', $oldInvoice->id)->update([
                            'status' => $restoredStatus,
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('customer_ledgers')
                    ->where('invoice_id', $invoiceToDelete->id)
                    ->update([
                        'invoice_id' => null,
                        'updated_at' => now(),
                    ]);

                DB::table('invoices')->where('id', $invoiceToDelete->id)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted and previous balances restored.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    private function billingInvoiceQuery()
    {
        return DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->select('invoices.*', 'customers.business_name', 'customers.owner_name', 'customers.current_balance')
            ->where(function ($query) {
                $query->where('customers.owner_name', '!=', 'Celyn Grace')
                    ->orWhereNull('customers.owner_name');
            });
    }

    private function applyInvoiceTabFilter($query, string $tab): void
    {
        if ($tab === 'paid') {
            $query->whereIn('invoices.status', ['Paid', 'Rolled Over']);

            return;
        }

        $query->where('invoices.status', $tab === 'partial' ? 'Partial' : 'Unpaid');
    }

    private function nextInvoiceNumberForDate(Carbon $date): string
    {
        $prefix = 'INV-' . $date->format('ymd') . '-';
        $lastSequence = DB::table('invoices')
            ->where('invoice_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('invoice_number')
            ->map(function ($invoiceNumber) use ($prefix) {
                $suffix = substr((string) $invoiceNumber, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        return $prefix . str_pad((string) ($lastSequence + 1), 3, '0', STR_PAD_LEFT);
    }

    private function billingRowsForInvoice($invoice)
    {
        $contextRows = DB::table('customer_ledgers')
            ->where('customer_id', $invoice->customer_id)
            ->where(function ($query) use ($invoice) {
                $query->where('invoice_id', $invoice->id)
                    ->orWhere(function ($query) use ($invoice) {
                        $query->whereNull('invoice_id')
                            ->whereBetween('transaction_date', [$invoice->period_start, $invoice->period_end]);
                    });
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $visibleIds = $this->ledgerRowsWithoutReversedCharges($contextRows)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $contextRows
            ->filter(fn ($row) => (int) ($row->invoice_id ?? 0) === (int) $invoice->id
                && in_array((int) $row->id, $visibleIds, true))
            ->values();
    }

    private function refreshInvoiceTotalsFromLedger($invoice, $invoiceRows = null, bool $persist = false, bool $preservePaidStatus = false)
    {
        if (! $invoice) {
            return $invoice;
        }

        $invoiceRows = $invoiceRows ?? $this->billingRowsForInvoice($invoice);
        $currentCharges = $this->moneyValue($invoiceRows->filter(fn ($row) => $this->isBillableChargeRow($row))->sum('debit'));
        $paymentsReceived = $this->netPaymentsReceivedForInvoice($invoiceRows, $invoice);
        $totalDiscounts = $this->moneyValue($invoiceRows->filter(fn ($row) => $this->isBillableChargeRow($row))->sum('discount'));
        $totalAmountDue = $this->moneyValue((float) $invoice->previous_balance + $currentCharges);
        $remainingDue = $totalAmountDue - $paymentsReceived;
        $status = $invoice->status;
        $hasPaymentReversal = $invoiceRows->contains(fn ($row) => $this->isPaymentReversalAppliedToInvoice($row, $invoice));

        if ($status !== 'Rolled Over' && ! ($preservePaidStatus && $status === 'Paid' && ! $hasPaymentReversal)) {
            if ($remainingDue <= 0.004) {
                $status = 'Paid';
            } elseif ($paymentsReceived > 0) {
                $status = 'Partial';
            } else {
                $status = 'Unpaid';
            }
        }

        $updates = [
            'current_charges' => $currentCharges,
            'total_discounts' => $totalDiscounts,
            'payments_received' => $paymentsReceived,
            'total_amount_due' => $totalAmountDue,
            'status' => $status,
        ];

        $shouldPersist = $persist && $this->invoiceTotalsChanged($invoice, $updates);

        foreach ($updates as $field => $value) {
            $invoice->{$field} = $value;
        }

        if ($shouldPersist) {
            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update($updates + ['updated_at' => now()]);
        }

        return $invoice;
    }

    private function invoiceTotalsChanged($invoice, array $updates): bool
    {
        foreach ($updates as $field => $value) {
            $currentValue = $invoice->{$field} ?? null;

            if (is_numeric($value)) {
                if (abs((float) $currentValue - (float) $value) > 0.004) {
                    return true;
                }

                continue;
            }

            if ((string) $currentValue !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    private function paymentRowsForInvoice($invoice)
    {
        $invoiceNumber = trim((string) ($invoice->invoice_number ?? ''));

        $payments = DB::table('customer_ledgers')
            ->where('customer_id', $invoice->customer_id)
            ->where('type', 'Payment')
            ->where('credit', '>', 0)
            ->where(function ($query) use ($invoice, $invoiceNumber) {
                $query->where('invoice_id', $invoice->id);

                if ($invoiceNumber !== '') {
                    $query->orWhere('reference_no', $invoiceNumber)
                        ->orWhere('description', 'like', '%' . $invoiceNumber . '%');
                }
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $payment->receipt_description = $this->cleanReceiptPaymentDescription($payment->description ?? '', $invoiceNumber);
        }

        return $payments;
    }

    private function acknowledgementReceiptRowsForInvoices($invoices)
    {
        $receiptRows = collect();

        foreach ($invoices->whereIn('status', ['Paid', 'Partial']) as $invoice) {
            $payments = $this->paymentRowsForInvoice($invoice);
            $cumulativePaid = 0.0;

            foreach ($payments as $payment) {
                $cumulativePaid = $this->moneyValue($cumulativePaid + (float) $payment->credit);
                $remainingBalance = max($this->moneyValue((float) $invoice->total_amount_due - $cumulativePaid), 0);
                $receiptKey = $this->acknowledgementReceiptKey((int) $invoice->id, (int) $payment->id);

                $receiptRows->push((object) [
                    'invoice' => $invoice,
                    'payment' => $payment,
                    'receipt_key' => $receiptKey,
                    'receipt_date' => $payment->transaction_date ?? $invoice->updated_at,
                    'paid_amount' => $this->moneyValue($payment->credit),
                    'remaining_balance' => $remainingBalance,
                    'is_full_payment' => $remainingBalance <= 0.004,
                    'is_issued' => false,
                    'issued_at' => null,
                    'issued_by' => null,
                ]);
            }
        }

        $receiptRows = $receiptRows
            ->sortByDesc(fn ($receipt) => sprintf('%s-%010d', $receipt->receipt_date, (int) ($receipt->payment->id ?? 0)))
            ->values();

        $issuedStatuses = $this->acknowledgementReceiptIssueStatuses($receiptRows->pluck('receipt_key')->all());

        return $receiptRows->map(function ($receipt) use ($issuedStatuses) {
            $issuedStatus = $issuedStatuses->get($receipt->receipt_key);
            $receipt->is_issued = (bool) ($issuedStatus->is_issued ?? false);
            $receipt->issued_at = $issuedStatus->issued_at ?? null;
            $receipt->issued_by = $issuedStatus->issued_by ?? null;

            return $receipt;
        });
    }

    private function acknowledgementReceiptIssueStatuses(array $receiptKeys)
    {
        $receiptKeys = array_values(array_unique(array_filter($receiptKeys)));

        if ($receiptKeys === [] || ! $this->acknowledgementReceiptIssuedColumnsReady()) {
            return collect();
        }

        return DB::table('acknowledgement_receipt_signatories')
            ->whereIn('receipt_key', $receiptKeys)
            ->select('receipt_key', 'is_issued', 'issued_at', 'issued_by')
            ->get()
            ->keyBy('receipt_key');
    }

    private function acknowledgementReceiptIssuedColumnsReady(): bool
    {
        return $this->hasTable('acknowledgement_receipt_signatories')
            && $this->hasColumn('acknowledgement_receipt_signatories', 'is_issued')
            && $this->hasColumn('acknowledgement_receipt_signatories', 'issued_at')
            && $this->hasColumn('acknowledgement_receipt_signatories', 'issued_by');
    }

    private function acknowledgementReceiptSignatories(int $invoiceId, ?int $paymentId)
    {
        if (! $this->hasTable('acknowledgement_receipt_signatories')) {
            return (object) [
                'received_by' => null,
                'paid_by' => null,
                'signed_at' => null,
            ];
        }

        $signatories = DB::table('acknowledgement_receipt_signatories')
            ->where('receipt_key', $this->acknowledgementReceiptKey($invoiceId, $paymentId))
            ->first();

        return $signatories ?: (object) [
            'received_by' => null,
            'paid_by' => null,
            'signed_at' => null,
        ];
    }

    private function billingPrintSignatories(int $invoiceId)
    {
        $emptySignatories = (object) [
            'prepared_by' => null,
            'acknowledged_by' => null,
            'signed_at' => null,
        ];

        if (! $this->hasTable('acknowledgement_receipt_signatories')) {
            return $emptySignatories;
        }

        $signatories = DB::table('acknowledgement_receipt_signatories')
            ->where('receipt_key', $this->billingPrintSignatoryKey($invoiceId))
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

    private function upsertReceiptSignatory(string $receiptKey, array $values): void
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

    private function cleanReceiptPaymentDescription(string $description, string $invoiceNumber): string
    {
        $description = preg_replace('/\s*\((?:Verified from Shift|Bypass posted by|authorized by)[^)]*\)/i', '', $description);
        $description = preg_replace('/\s+/', ' ', trim($description ?? ''));

        return $description !== '' ? $description : 'Payment for Invoice ' . $invoiceNumber;
    }

    private function acknowledgementReceiptKey(int $invoiceId, ?int $paymentId): string
    {
        return $paymentId
            ? 'invoice:' . $invoiceId . ':payment:' . $paymentId
            : 'invoice:' . $invoiceId . ':summary';
    }

    private function billingPrintSignatoryKey(int $invoiceId): string
    {
        return 'invoice:' . $invoiceId . ':billing-print';
    }

    private function isPaymentAppliedToInvoice($ledger, $invoice): bool
    {
        if ((float) ($ledger->credit ?? 0) <= 0 || ($ledger->type ?? null) !== 'Payment') {
            return false;
        }

        if ((int) ($ledger->invoice_id ?? 0) === (int) ($invoice->id ?? 0)) {
            return true;
        }

        $referenceNo = trim((string) ($ledger->reference_no ?? ''));
        $invoiceNumber = trim((string) ($invoice->invoice_number ?? ''));

        if ($referenceNo !== '' && $invoiceNumber !== '' && strcasecmp($referenceNo, $invoiceNumber) === 0) {
            return true;
        }

        $description = (string) ($ledger->description ?? '');

        if ($invoiceNumber !== '' && stripos($description, 'for Invoice ' . $invoiceNumber) !== false) {
            return true;
        }

        if (empty($ledger->created_at) || empty($invoice->created_at)) {
            return false;
        }

        return Carbon::parse($ledger->created_at)->greaterThanOrEqualTo(Carbon::parse($invoice->created_at));
    }

    private function isPaymentReversalAppliedToInvoice($ledger, $invoice): bool
    {
        if ((float) ($ledger->debit ?? 0) <= 0 || ($ledger->type ?? null) !== 'Void Payment') {
            return false;
        }

        if ((int) ($ledger->invoice_id ?? 0) === (int) ($invoice->id ?? 0)) {
            return true;
        }

        $invoiceNumber = trim((string) ($invoice->invoice_number ?? ''));
        $referenceNo = trim((string) ($ledger->reference_no ?? ''));
        $orNumber = trim((string) ($ledger->or_number ?? ''));
        $description = (string) ($ledger->description ?? '');

        return $invoiceNumber !== ''
            && (
                strcasecmp($referenceNo, $invoiceNumber) === 0
                || strcasecmp($orNumber, $invoiceNumber) === 0
                || stripos($description, $invoiceNumber) !== false
            );
    }

    private function netPaymentsReceivedForInvoice($invoiceRows, $invoice): float
    {
        $paymentCredits = $invoiceRows
            ->filter(fn ($row) => $this->isPaymentAppliedToInvoice($row, $invoice))
            ->sum('credit');

        $voidDebits = $invoiceRows
            ->filter(fn ($row) => $this->isPaymentReversalAppliedToInvoice($row, $invoice))
            ->sum('debit');

        return $this->moneyValue(max(0, (float) $paymentCredits - (float) $voidDebits));
    }

    private function isBillableChargeRow($ledger): bool
    {
        return (float) ($ledger->debit ?? 0) > 0
            && (float) ($ledger->credit ?? 0) <= 0
            && ! in_array($ledger->type ?? null, ['Payment', 'Void Payment'], true);
    }

    private function ledgerRowsWithoutReversedCharges($ledgerRows)
    {
        $skipIds = [];
        $reversalTypes = ['Void', 'Edit Reversal'];

        foreach ($ledgerRows as $reversal) {
            if (! in_array($reversal->type, $reversalTypes, true) || (float) ($reversal->credit ?? 0) <= 0) {
                continue;
            }

            $candidates = $ledgerRows->filter(function ($row) use ($reversal, $skipIds) {
                return ! in_array((int) $row->id, $skipIds, true)
                    && $this->isBillableChargeRow($row)
                    && abs($this->moneyValue($row->debit) - $this->moneyValue($reversal->credit)) <= 0.004;
            });

            $sameDateCandidates = $candidates->filter(function ($row) use ($reversal) {
                return Carbon::parse($row->transaction_date)->toDateString()
                    === Carbon::parse($reversal->transaction_date)->toDateString();
            });

            $referenceTokens = $this->reversalReferenceTokens($reversal);
            $referencedCandidates = $this->reversalReferencedChargeCandidates($candidates, $reversal, $referenceTokens);

            if ($referencedCandidates->isNotEmpty()) {
                $original = $referencedCandidates->filter(fn ($row) => (int) $row->id < (int) $reversal->id)->last()
                    ?? $referencedCandidates->first();
            } elseif ($this->hasStrictReversalReferenceToken($referenceTokens)) {
                continue;
            } else {
                $original = $sameDateCandidates->filter(fn ($row) => (int) $row->id < (int) $reversal->id)->last()
                    ?? $candidates->filter(fn ($row) => (int) $row->id < (int) $reversal->id)->last()
                    ?? $sameDateCandidates->filter(fn ($row) => (int) $row->id > (int) $reversal->id)->first();
            }

            if ($original) {
                $skipIds[] = (int) $reversal->id;
                $skipIds[] = (int) $original->id;
            }
        }

        $skipIds = array_unique($skipIds);

        return $ledgerRows
            ->reject(fn ($row) => in_array((int) $row->id, $skipIds, true))
            ->values();
    }

    private function reversalReferencedChargeCandidates($candidates, $reversal, ?array $referenceTokens = null)
    {
        $referenceTokens = $referenceTokens ?? $this->reversalReferenceTokens($reversal);

        if ($referenceTokens === []) {
            return collect();
        }

        return $candidates->filter(function ($row) use ($referenceTokens) {
            $rowReferences = array_values(array_filter([
                trim((string) ($row->reference_no ?? '')),
                trim((string) ($row->or_number ?? '')),
            ], fn ($value) => $value !== ''));

            foreach ($rowReferences as $rowReference) {
                foreach ($referenceTokens as $referenceToken) {
                    if (strcasecmp($rowReference, $referenceToken) === 0) {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    private function hasStrictReversalReferenceToken(array $referenceTokens): bool
    {
        foreach ($referenceTokens as $referenceToken) {
            if (! preg_match('/^\d+$/', $referenceToken)) {
                return true;
            }
        }

        return false;
    }

    private function reversalReferenceTokens($reversal): array
    {
        $tokens = [];
        $referenceNo = trim((string) ($reversal->reference_no ?? ''));
        $description = (string) ($reversal->description ?? '');

        if (preg_match('/^EDIT-\d+$/i', $referenceNo)) {
            $tokens[] = $referenceNo;
        }

        foreach ([
            '/\bRef(?:erence)?(?:\s*#|\s*:)?\s*([A-Z0-9][A-Z0-9-]*)/i',
            '/\bRef(?:erence)?(?:\s*#|\s*:)?\s*(.+)$/i',
            '/\bfrom\s+([A-Z0-9][A-Z0-9-]*)/i',
            '/\bfrom\s+(.+)$/i',
        ] as $pattern) {
            if (preg_match_all($pattern, $description, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleanToken = $this->cleanReversalReferenceToken($match);

                    if ($cleanToken !== null) {
                        $tokens[] = $cleanToken;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($tokens, fn ($token) => $token !== '')));
    }

    private function cleanReversalReferenceToken($value): ?string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B.,;:");

        do {
            $previous = $value;
            $value = preg_replace('/\s+\((?:Total\s+)?(?:Discount|Excess):[^)]*\)\s*$/i', '', $value);
        } while ($value !== $previous);

        $value = trim((string) $value, " \t\n\r\0\x0B.,;:");

        return $value === '' ? null : $value;
    }

    private function findMatchingSaleForLedger($ledger)
    {
        if (! $this->hasTable('sales')) {
            return null;
        }

        $customerId = (int) $ledger->customer_id;
        $referenceNo = trim((string) ($ledger->reference_no ?? ''));
        $orNumber = trim((string) ($ledger->or_number ?? ''));

        if (preg_match('/^EDIT-(\d+)$/i', $referenceNo, $matches)) {
            $sale = DB::table('sales')
                ->where('id', (int) $matches[1])
                ->where(function ($query) use ($customerId) {
                    $query->where('customer_id', $customerId)
                        ->orWhere('posted_customer_id', $customerId);
                })
                ->where('payment_type', 'credit')
                ->lockForUpdate()
                ->first();

            if ($sale) {
                return $sale;
            }
        }

        $lookupNumbers = array_values(array_filter(array_unique([$referenceNo, $orNumber]), fn ($number) => $number !== ''));

        if ($lookupNumbers !== []) {
            $sale = DB::table('sales')
                ->where(function ($query) use ($customerId) {
                    $query->where('customer_id', $customerId)
                        ->orWhere('posted_customer_id', $customerId);
                })
                ->where('payment_type', 'credit')
                ->where(function ($query) use ($lookupNumbers) {
                    $query->whereIn('reference_no', $lookupNumbers)
                        ->orWhereIn('or_number', $lookupNumbers);
                })
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($sale) {
                return $sale;
            }
        }

        $candidates = DB::table('sales')
            ->where(function ($query) use ($customerId) {
                $query->where('customer_id', $customerId)
                    ->orWhere('posted_customer_id', $customerId);
            })
            ->where('payment_type', 'credit')
            ->whereDate('created_at', Carbon::parse($ledger->transaction_date)->toDateString())
            ->where('amount', $ledger->debit)
            ->lockForUpdate()
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function recomputeCustomerLedgerBalances(int $customerId): void
    {
        $runningBalance = 0.0;
        $entries = DB::table('customer_ledgers')
            ->where('customer_id', $customerId)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($entries as $entry) {
            $runningBalance = $this->moneyValue($runningBalance + (float) $entry->debit - (float) $entry->credit);

            if (abs((float) $entry->running_balance - $runningBalance) > 0.004) {
                DB::table('customer_ledgers')
                    ->where('id', $entry->id)
                    ->update([
                        'running_balance' => $runningBalance,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('customers')
            ->where('id', $customerId)
            ->update([
                'current_balance' => $runningBalance,
                'updated_at' => now(),
            ]);
    }

    private function recalculateInvoiceTotals(int $invoiceId): void
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->lockForUpdate()->first();

        if ($invoice) {
            $this->refreshInvoiceTotalsFromLedger($invoice, null, true);
        }
    }

    private function insertBillingEditLog(array $values): void
    {
        if ($this->hasTable('billing_edit_logs')) {
            DB::table('billing_edit_logs')->insert($values);
        }
    }

    private function changedBillingFields(array $oldValues, array $newValues): array
    {
        $changed = [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;

            if (in_array($field, ['discount', 'debit'], true)) {
                if (abs((float) $oldValue - (float) $newValue) > 0.004) {
                    $changed[] = $field;
                }

                continue;
            }

            if ((string) ($oldValue ?? '') !== (string) ($newValue ?? '')) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    private function cleanReferenceNo($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strtoupper($value) === 'N/A') {
            return null;
        }

        return $value;
    }

    private function cleanNullableText($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function moneyValue($value): float
    {
        return round((float) $value, 2);
    }

    private function encodeLogValue($value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    private function canEditBillingRows(): bool
    {
        return auth()->check() && auth()->user()->role !== 'semiadmin';
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

    private function jsonError(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
