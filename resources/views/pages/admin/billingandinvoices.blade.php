@extends('layouts.app')

@section('content')
    @php
        $customers = collect($customers ?? []);
        $invoicePaginator = $invoices ?? collect();
        $invoiceRows = $invoicePaginator instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($invoicePaginator->items())
            : collect($invoicePaginator);
        $summaryInvoices = collect($summaryInvoices ?? $invoiceRows);
        $acknowledgementReceipts = collect($acknowledgementReceipts ?? []);
        $canManageBilling = $canManageBilling ?? false;
        $missingBillingTables = $missingBillingTables ?? [];
        $activeBillingTab = $activeBillingTab ?? 'unpaid';
        $activeBillingTab = in_array($activeBillingTab, ['unpaid', 'partial', 'paid', 'receipts'], true) ? $activeBillingTab : 'unpaid';
        $invoiceTableTab = in_array($activeBillingTab, ['unpaid', 'partial', 'paid'], true) ? $activeBillingTab : 'unpaid';
        $invoiceSearch = trim((string) ($invoiceSearch ?? ''));
        $perPageOptions = $perPageOptions ?? [10, 50, 100];
        $perPage = (int) ($perPage ?? 10);
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 10;
        $invoiceTotal = method_exists($invoicePaginator, 'total') ? $invoicePaginator->total() : $invoiceRows->count();
        $invoiceFirstItem = method_exists($invoicePaginator, 'firstItem') ? $invoicePaginator->firstItem() : ($invoiceTotal > 0 ? 1 : null);
        $invoiceLastItem = method_exists($invoicePaginator, 'lastItem') ? $invoicePaginator->lastItem() : $invoiceRows->count();
        $invoiceTabTitles = [
            'unpaid' => 'Unpaid Bills',
            'partial' => 'Partially Paid',
            'paid' => 'Paid Archive',
        ];
        $billingTabUrl = function ($tab) use ($perPage, $invoiceSearch) {
            $params = [
                'tab' => $tab,
                'per_page' => $perPage,
            ];

            if ($tab !== 'receipts' && $invoiceSearch !== '') {
                $params['invoice_search'] = $invoiceSearch;
            }

            return route('admin.billing.index', $params);
        };
        $billingCustomerName = function ($item) {
            $businessName = trim((string) ($item->business_name ?? ''));
            $ownerName = trim((string) ($item->owner_name ?? ''));
            $hasBusinessName = $businessName !== '' && strtoupper($businessName) !== 'N/A';
            $hasOwnerName = $ownerName !== '' && strtoupper($ownerName) !== 'N/A';

            if ($hasBusinessName && $hasOwnerName && strcasecmp($businessName, $ownerName) !== 0) {
                return strtoupper($businessName) . ' - ' . ucwords(strtolower($ownerName));
            }

            if ($hasBusinessName) {
                return strtoupper($businessName);
            }

            return $hasOwnerName ? ucwords(strtolower($ownerName)) : 'Customer #' . ($item->customer_id ?? $item->id ?? '');
        };

        $customerPayload = $customers->map(function ($customer) use ($billingCustomerName) {
            $label = $billingCustomerName($customer);

            return [
                'id' => (int) $customer->id,
                'label' => $label,
                'search' => strtolower($label . ' ' . ($customer->contact_number ?? '')),
            ];
        })->values();

        $invoicePayloadFor = function ($invoice) use ($billingCustomerName) {
            $total = (float) ($invoice->total_amount_due ?? 0);
            $paid = (float) ($invoice->payments_received ?? 0);
            $remaining = max($total - $paid, 0);
            $periodStart = $invoice->period_start ? \Carbon\Carbon::parse($invoice->period_start) : null;
            $periodEnd = $invoice->period_end ? \Carbon\Carbon::parse($invoice->period_end) : null;
            $dueDate = $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date) : null;
            $customerName = $billingCustomerName($invoice);

            return [
                'id' => (int) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => (int) $invoice->customer_id,
                'customer_name' => $customerName,
                'current_balance' => (float) ($invoice->current_balance ?? 0),
                'period_start' => $invoice->period_start,
                'period_end' => $invoice->period_end,
                'period_label' => $periodStart && $periodEnd ? $periodStart->format('M d') . ' - ' . $periodEnd->format('M d, Y') : 'N/A',
                'due_label' => $dueDate ? $dueDate->format('M d, Y') : 'N/A',
                'total_amount_due' => round($total, 2),
                'payments_received' => round($paid, 2),
                'remaining_balance' => round($remaining, 2),
                'status' => $invoice->status ?? 'Unpaid',
                'print_url' => route('admin.billing.print', $invoice->id),
                'search' => strtolower(($invoice->invoice_number ?? '') . ' ' . $customerName),
            ];
        };

        $formatBalanceLabel = function ($value) {
            $amount = (float) ($value ?? 0);
            $label = $amount < 0 ? 'Advance: ' : 'Balance: ';

            return $label . '&#8369;' . number_format(abs($amount), 2);
        };

        $receiptPayload = $acknowledgementReceipts->map(function ($receipt) use ($billingCustomerName) {
            $invoice = $receipt->invoice;
            $payment = $receipt->payment;
            $paymentId = ! empty($payment->id) ? (int) $payment->id : null;
            $receiptUrl = $paymentId
                ? route('admin.billing.acknowledgementReceipt.payment', ['id' => $invoice->id, 'paymentId' => $paymentId])
                : route('admin.billing.acknowledgementReceipt', $invoice->id);
            $issuedUrl = $paymentId
                ? route('admin.billing.acknowledgementReceipt.issued.payment', ['id' => $invoice->id, 'paymentId' => $paymentId])
                : route('admin.billing.acknowledgementReceipt.issued', $invoice->id);
            $receiptDate = $receipt->receipt_date ? \Carbon\Carbon::parse($receipt->receipt_date) : null;
            $periodStart = $invoice->period_start ? \Carbon\Carbon::parse($invoice->period_start) : null;
            $periodEnd = $invoice->period_end ? \Carbon\Carbon::parse($invoice->period_end) : null;

            return [
                'receipt_key' => $receipt->receipt_key,
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'payment_id' => $paymentId,
                'customer_id' => (int) $invoice->customer_id,
                'customer_name' => $billingCustomerName($invoice),
                'period_label' => $periodStart && $periodEnd ? $periodStart->format('M d') . ' - ' . $periodEnd->format('M d, Y') : 'N/A',
                'receipt_date_label' => $receiptDate ? $receiptDate->format('M d, Y') : 'N/A',
                'paid_amount' => (float) $receipt->paid_amount,
                'remaining_balance' => (float) $receipt->remaining_balance,
                'is_full_payment' => (bool) $receipt->is_full_payment,
                'is_issued' => (bool) $receipt->is_issued,
                'issued_at_label' => ! empty($receipt->issued_at) ? \Carbon\Carbon::parse($receipt->issued_at)->format('M d, Y h:i A') : '',
                'receipt_url' => $receiptUrl,
                'issued_url' => $issuedUrl,
            ];
        })->values();

        $summary = [
            'unpaid' => $summaryInvoices->where('status', 'Unpaid')->count(),
            'partial' => $summaryInvoices->where('status', 'Partial')->count(),
            'paid' => $summaryInvoices->whereIn('status', ['Paid', 'Rolled Over'])->count(),
            'receipts' => $acknowledgementReceipts->count(),
            'open_total' => $summaryInvoices->whereIn('status', ['Unpaid', 'Partial'])->sum(fn ($invoice) => max((float) $invoice->total_amount_due - (float) $invoice->payments_received, 0)),
        ];

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $dateInputClass = 'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $textareaClass = 'min-h-[84px] w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $dangerButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60';
        $viewIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
        $payIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-success-200 bg-success-50 text-success-700 shadow-theme-xs transition hover:border-success-300 hover:bg-success-100 dark:border-success-500/25 dark:bg-success-500/10 dark:text-success-400 dark:hover:bg-success-500/15';
        $deleteIconButtonClass = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-600 shadow-theme-xs transition hover:border-error-300 hover:bg-error-100 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400 dark:hover:bg-error-500/15';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $dateIcon = '<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-6"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path></svg></span>';
    @endphp

    <x-common.page-breadcrumb pageTitle="Billing & Invoices" />

    <div
        x-data="billingInvoicesModule({
            activeTab: @js($activeBillingTab),
            customers: @js($customerPayload),
            receipts: @js($receiptPayload),
            canManageBilling: @js($canManageBilling),
            csrfToken: @js(csrf_token()),
            generateUrl: @js(route('admin.billing.generate')),
            destroyUrl: @js(route('admin.billing.destroy')),
            payUrl: @js(route('customers.pay')),
            unpaidBaseUrl: @js(url('/customers')),
            storedToastKey: 'billing-invoices-toast',
            defaultPeriodStart: @js(now()->startOfMonth()->toDateString()),
            defaultPeriodEnd: @js(now()->toDateString())
        })"
        x-init="init()"
        class="space-y-6">
        <style>[x-cloak] { display: none !important; }</style>

        @if ($missingBillingTables)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing billing table(s): {{ implode(', ', $missingBillingTables) }}.
            </div>
        @endif

        <div
            x-show="toast.open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="fixed right-4 w-[calc(100%-2rem)] max-w-sm rounded-xl border border-white/10 p-4 text-white shadow-theme-lg sm:right-6"
            style="top: 5.5rem; z-index: 100300; background-color: #047857;"
            role="status"
            aria-live="polite">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6.75 12.25 3.25 3.25 7.25-7.25" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-theme-sm font-semibold text-white">Success</p>
                    <p class="mt-1 text-theme-sm text-white/90" x-text="toast.message"></p>
                </div>
                <button type="button" x-on:click="closeToast()" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Close success message">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Billing & Invoices</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">Generate billing statements, receive invoice payments, print statements, and track acknowledgement receipts.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap xl:justify-end">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Open Balance</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) $summary['open_total'], 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Receipts</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $summary['receipts'] }}</p>
                    </div>
                    @if ($canManageBilling)
                        <button type="button" x-on:click="openGenerate()" class="{{ $primaryButtonClass }} sm:self-start">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Generate Bill
                        </button>
                    @endif
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }} p-2 md:p-2">
            <div class="grid grid-cols-2 gap-2 xl:grid-cols-4">
                <a href="{{ $billingTabUrl('unpaid') }}" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition xl:text-left {{ $activeBillingTab === 'unpaid' ? 'bg-error-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white' }}">
                    Unpaid Bills
                    <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-theme-xs">{{ $summary['unpaid'] }}</span>
                </a>
                <a href="{{ $billingTabUrl('partial') }}" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition xl:text-left {{ $activeBillingTab === 'partial' ? 'bg-warning-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white' }}">
                    Partially Paid
                    <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-theme-xs">{{ $summary['partial'] }}</span>
                </a>
                <a href="{{ $billingTabUrl('paid') }}" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition xl:text-left {{ $activeBillingTab === 'paid' ? 'bg-success-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white' }}">
                    Paid Archive
                    <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-theme-xs">{{ $summary['paid'] }}</span>
                </a>
                <a href="{{ $billingTabUrl('receipts') }}" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition xl:text-left {{ $activeBillingTab === 'receipts' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white' }}">
                    Receipts
                    <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-theme-xs">{{ $summary['receipts'] }}</span>
                </a>
            </div>
        </section>

        <section x-show="activeTab !== 'receipts'" x-cloak class="{{ $cardClass }}">
            <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $invoiceTabTitles[$invoiceTableTab] ?? 'Unpaid Bills' }}</h2>
                    <p class="mt-1 {{ $captionClass }}">Search invoices by invoice number or customer.</p>
                </div>
                <form method="GET" action="{{ route('admin.billing.index') }}" class="w-full lg:w-[380px]">
                    <input type="hidden" name="tab" value="{{ $invoiceTableTab }}">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Search invoices</span>
                        <input type="search" name="invoice_search" value="{{ $invoiceSearch }}" class="mt-1.5 {{ $inputClass }}" placeholder="Invoice number or customer">
                    </label>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[980px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[14%]">
                            <col class="w-[18%]">
                            <col class="w-[12%]">
                            <col class="w-[9%]">
                            <col class="w-[15%]">
                            <col class="w-[16%]">
                            <col class="w-[16%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Invoice #</th>
                                <th class="{{ $thClass }}">Customer</th>
                                <th class="{{ $thClass }}">Period</th>
                                <th class="{{ $thClass }}">Due Date</th>
                                <th class="{{ $thNumberClass }}">Total</th>
                                <th class="{{ $thNumberClass }}">Remaining</th>
                                <th class="{{ $thClass }}">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($invoiceRows as $invoice)
                                @php
                                    $invoiceRow = $invoicePayloadFor($invoice);
                                    $canReceivePayment = in_array($invoiceRow['status'], ['Unpaid', 'Partial'], true) && (float) $invoiceRow['remaining_balance'] > 0;
                                    $canDeleteInvoice = in_array($invoiceRow['status'], ['Unpaid', 'Partial'], true);
                                @endphp
                                <tr>
                                    <td class="{{ $tdPrimaryClass }}">{{ $invoiceRow['invoice_number'] }}</td>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $invoiceRow['customer_name'] }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{!! $formatBalanceLabel($invoiceRow['current_balance']) !!}</div>
                                    </td>
                                    <td class="{{ $tdClass }}">{{ $invoiceRow['period_label'] }}</td>
                                    <td class="{{ $tdClass }}">{{ $invoiceRow['due_label'] }}</td>
                                    <td class="{{ $tdNumberClass }} font-semibold">&#8369;{{ number_format((float) $invoiceRow['total_amount_due'], 2) }}</td>
                                    <td class="{{ $tdNumberClass }} font-semibold {{ (float) $invoiceRow['remaining_balance'] > 0 ? 'text-error-600 dark:text-error-400' : 'text-success-600 dark:text-success-500' }}">&#8369;{{ number_format((float) $invoiceRow['remaining_balance'], 2) }}</td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex min-w-[8rem] items-center gap-2">
                                            <a href="{{ $invoiceRow['print_url'] }}" target="_blank" class="{{ $viewIconButtonClass }}" title="Print invoice" aria-label="Print invoice">
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8V4h10v4M7 17H5.75A2.75 2.75 0 0 1 3 14.25v-3.5A2.75 2.75 0 0 1 5.75 8h12.5A2.75 2.75 0 0 1 21 10.75v3.5A2.75 2.75 0 0 1 18.25 17H17M7 14h10v6H7v-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </a>
                                            @if ($canManageBilling)
                                                @if ($canReceivePayment)
                                                    <button type="button" x-on:click="openPayment(@js($invoiceRow))" class="{{ $payIconButtonClass }}" title="Receive payment" aria-label="Receive payment">
                                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7.75h16v8.5H4v-8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 12h.01M17 12h.01M12 14.75a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                    </button>
                                                @endif
                                                @if ($canDeleteInvoice)
                                                    <button type="button" x-on:click="confirmDeleteInvoice(@js($invoiceRow))" class="{{ $deleteIconButtonClass }}" title="Delete invoice" aria-label="Delete invoice">
                                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9.5 4.75h5M5.75 7.25h12.5M10 10.75v5.5M14 10.75v5.5M7.25 7.25l.75 12h8l.75-12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8V4h10v4M7 17H5.75A2.75 2.75 0 0 1 3 14.25v-3.5A2.75 2.75 0 0 1 5.75 8h12.5A2.75 2.75 0 0 1 21 10.75v3.5A2.75 2.75 0 0 1 18.25 17H17M7 14h10v6H7v-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </span>
                                        <p class="mt-3 text-theme-sm font-medium text-gray-800 dark:text-white/90">No invoices found.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p class="{{ $captionClass }}">
                        @if ($invoiceTotal > 0 && $invoiceFirstItem)
                            Showing {{ number_format($invoiceFirstItem) }}-{{ number_format($invoiceLastItem) }} of {{ number_format($invoiceTotal) }} invoice{{ $invoiceTotal === 1 ? '' : 's' }}.
                        @else
                            No invoices found.
                        @endif
                    </p>
                    <form method="GET" action="{{ route('admin.billing.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="{{ $invoiceTableTab }}">
                        @if ($invoiceSearch !== '')
                            <input type="hidden" name="invoice_search" value="{{ $invoiceSearch }}">
                        @endif
                        <label for="billing-invoice-per-page" class="text-theme-sm text-gray-500 dark:text-gray-400">Rows</label>
                        <select id="billing-invoice-per-page" name="per_page" class="h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" onchange="this.form.submit()">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" {{ (int) $perPage === (int) $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if (method_exists($invoicePaginator, 'hasPages') && $invoicePaginator->hasPages())
                    @php
                        $invoiceCurrentPage = method_exists($invoicePaginator, 'currentPage') ? (int) $invoicePaginator->currentPage() : 1;
                        $invoiceLastPageNumber = method_exists($invoicePaginator, 'lastPage') ? (int) $invoicePaginator->lastPage() : $invoiceCurrentPage;
                        $invoicePageNumbers = collect([1, $invoiceCurrentPage - 1, $invoiceCurrentPage, $invoiceCurrentPage + 1, $invoiceLastPageNumber])
                            ->filter(fn ($page) => $page >= 1 && $page <= $invoiceLastPageNumber)
                            ->unique()
                            ->sort()
                            ->values();
                        $previousInvoicePage = null;
                    @endphp
                    <nav class="flex flex-wrap items-center gap-2 lg:justify-end" role="navigation" aria-label="Invoice pagination">
                        @if ($invoicePaginator->onFirstPage())
                            <span class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-theme-sm font-medium text-gray-400 dark:border-gray-800 dark:text-gray-600">Prev</span>
                        @else
                            <a href="{{ $invoicePaginator->previousPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">Prev</a>
                        @endif

                        @foreach ($invoicePageNumbers as $page)
                            @if ($previousInvoicePage && $page > $previousInvoicePage + 1)
                                <span class="inline-flex h-9 min-w-9 items-center justify-center px-1 text-theme-sm text-gray-400 dark:text-gray-600">...</span>
                            @endif

                            @if ($page === $invoiceCurrentPage)
                                <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-brand-500 px-3 text-theme-sm font-medium text-white" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $invoicePaginator->url($page) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">{{ $page }}</a>
                            @endif

                            @php $previousInvoicePage = $page; @endphp
                        @endforeach

                        @if ($invoicePaginator->hasMorePages())
                            <a href="{{ $invoicePaginator->nextPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">Next</a>
                        @else
                            <span class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-theme-sm font-medium text-gray-400 dark:border-gray-800 dark:text-gray-600">Next</span>
                        @endif
                    </nav>
                @endif
            </div>
        </section>

        <section x-show="activeTab === 'receipts'" x-cloak class="{{ $cardClass }}">
            <div class="mb-5 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(220px,280px)_minmax(180px,240px)_auto] xl:items-end">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Acknowledgement Receipts</h2>
                    <p class="mt-1 {{ $captionClass }}"><span x-text="filteredReceipts().length"></span> receipt<span x-show="filteredReceipts().length !== 1">s</span> shown.</p>
                </div>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Customer</span>
                    <select x-model="receiptCustomerId" x-on:change="receiptInvoiceNumber = ''; resetReceiptPage()" class="mt-1.5 {{ $selectClass }}">
                        <option value="">All customers</option>
                        <template x-for="customer in customers" :key="customer.id">
                            <option :value="String(customer.id)" x-text="customer.label"></option>
                        </template>
                    </select>
                </label>
                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <span>Invoice #</span>
                    <select x-model="receiptInvoiceNumber" x-on:change="resetReceiptPage()" class="mt-1.5 {{ $selectClass }}" :disabled="receiptInvoiceOptions().length === 0">
                        <option value="">All invoices</option>
                        <template x-for="invoiceNumber in receiptInvoiceOptions()" :key="invoiceNumber">
                            <option :value="invoiceNumber" x-text="invoiceNumber"></option>
                        </template>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Status</span>
                        <select x-model="receiptIssueStatus" x-on:change="resetReceiptPage()" class="mt-1.5 {{ $selectClass }}">
                            <option value="">All</option>
                            <option value="issued">Issued</option>
                            <option value="pending">Pending</option>
                        </select>
                    </label>
                    <button type="button" x-on:click="resetReceiptFilters()" class="{{ $secondaryButtonClass }} self-end">
                        Reset
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1120px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[15%]">
                            <col class="w-[22%]">
                            <col class="w-[14%]">
                            <col class="w-[12%]">
                            <col class="w-[12%]">
                            <col class="w-[10%]">
                            <col class="w-[8%]">
                            <col class="w-[7%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Invoice / Payment</th>
                                <th class="{{ $thClass }}">Customer</th>
                                <th class="{{ $thClass }}">Period</th>
                                <th class="{{ $thClass }}">Receipt Date</th>
                                <th class="{{ $thNumberClass }}">Payment</th>
                                <th class="{{ $thNumberClass }}">Remaining</th>
                                <th class="{{ $thClass }}">Issued</th>
                                <th class="{{ $thClass }}">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="receipt in paginatedReceipts()" :key="receipt.receipt_key">
                                <tr>
                                    <td class="{{ $tdPrimaryClass }}">
                                        <div x-text="receipt.invoice_number"></div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-show="receipt.payment_id" x-text="'Payment #' + receipt.payment_id"></div>
                                    </td>
                                    <td class="{{ $tdClass }}" x-text="receipt.customer_name"></td>
                                    <td class="{{ $tdClass }}" x-text="receipt.period_label"></td>
                                    <td class="{{ $tdClass }}" x-text="receipt.receipt_date_label"></td>
                                    <td class="{{ $tdNumberClass }} font-semibold text-success-600 dark:text-success-500" x-text="formatMoney(receipt.paid_amount)"></td>
                                    <td class="{{ $tdNumberClass }} font-semibold" :class="Number(receipt.remaining_balance) > 0 ? 'text-warning-700 dark:text-warning-400' : 'text-success-600 dark:text-success-500'" x-text="formatMoney(receipt.remaining_balance)"></td>
                                    <td class="{{ $tdClass }}">
                                        <label class="inline-flex cursor-pointer items-center gap-2">
                                            <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" :checked="receipt.is_issued" :disabled="receipt.saving" x-on:change="toggleReceiptIssued(receipt, $event.target.checked)">
                                            <span class="text-theme-xs font-medium" :class="receipt.is_issued ? 'text-success-700 dark:text-success-500' : 'text-warning-700 dark:text-warning-400'" x-text="receipt.is_issued ? 'Issued' : 'Pending'"></span>
                                        </label>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-show="receipt.issued_at_label" x-text="receipt.issued_at_label"></div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <a :href="receipt.receipt_url" target="_blank" class="{{ $viewIconButtonClass }}" title="Print acknowledgement receipt" aria-label="Print acknowledgement receipt">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8V4h10v4M7 17H5.75A2.75 2.75 0 0 1 3 14.25v-3.5A2.75 2.75 0 0 1 5.75 8h12.5A2.75 2.75 0 0 1 21 10.75v3.5A2.75 2.75 0 0 1 18.25 17H17M7 14h10v6H7v-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredReceipts().length === 0">
                                <td colspan="8" class="px-5 py-10 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                            <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 5.75h8M8 10h8M8 14.25h5M6 21.25h12A2.25 2.25 0 0 0 20.25 19V5A2.25 2.25 0 0 0 18 2.75H6A2.25 2.25 0 0 0 3.75 5v14A2.25 2.25 0 0 0 6 21.25Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        </span>
                                        <p class="mt-3 text-theme-sm font-medium text-gray-800 dark:text-white/90">No receipts found.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <p class="{{ $captionClass }}">
                        <span x-show="filteredReceipts().length > 0">
                            Showing <span x-text="receiptFirstItem()"></span>-<span x-text="receiptLastItem()"></span>
                            of <span x-text="filteredReceipts().length"></span> receipt<span x-show="filteredReceipts().length !== 1">s</span>.
                        </span>
                        <span x-show="filteredReceipts().length === 0">No receipts found.</span>
                    </p>
                    <label class="flex items-center gap-2">
                        <span class="text-theme-sm text-gray-500 dark:text-gray-400">Rows</span>
                        <select x-model.number="receiptPerPage" x-on:change="resetReceiptPage()" class="h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <template x-for="option in receiptPerPageOptions" :key="option">
                                <option :value="option" x-text="option"></option>
                            </template>
                        </select>
                    </label>
                </div>

                <nav x-show="receiptTotalPages() > 1" class="flex flex-wrap items-center gap-2 lg:justify-end" role="navigation" aria-label="Receipt pagination">
                    <button type="button" x-on:click="goToReceiptPage(receiptCurrentPage() - 1)" :disabled="receiptCurrentPage() === 1" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-transparent disabled:text-gray-400 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06] dark:disabled:bg-transparent dark:disabled:text-gray-600">
                        Prev
                    </button>

                    <template x-for="item in receiptPaginationItems()" :key="item.key">
                        <span>
                            <span x-show="item.ellipsis" class="inline-flex h-9 min-w-9 items-center justify-center px-1 text-theme-sm text-gray-400 dark:text-gray-600">...</span>
                            <button
                                x-show="!item.ellipsis"
                                type="button"
                                x-on:click="goToReceiptPage(item.page)"
                                class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-3 text-theme-sm font-medium transition"
                                :class="item.page === receiptCurrentPage()
                                    ? 'bg-brand-500 text-white'
                                    : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]'"
                                :aria-current="item.page === receiptCurrentPage() ? 'page' : null"
                                x-text="item.page">
                            </button>
                        </span>
                    </template>

                    <button type="button" x-on:click="goToReceiptPage(receiptCurrentPage() + 1)" :disabled="receiptCurrentPage() === receiptTotalPages()" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-transparent disabled:text-gray-400 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06] dark:disabled:bg-transparent dark:disabled:text-gray-600">
                        Next
                    </button>
                </nav>
            </div>
        </section>

        <div x-show="modal === 'generate'" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center p-4">
            <div x-show="modal === 'generate'" x-transition.opacity class="absolute inset-0 bg-gray-900/55"></div>
            <div
                x-show="modal === 'generate'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-3 scale-95"
                class="relative max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Generate Billing Statement</h3>
                        <p class="mt-1 {{ $captionClass }}">Select the account and billing period.</p>
                    </div>
                    <button type="button" x-on:click="closeModal()" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]" aria-label="Close modal">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="mt-6 space-y-5">
                    <label class="block {{ $labelClass }}">
                        <span>Customer</span>
                        <select x-model="generationForm.customer_id" x-on:change="loadRolloverInvoices()" class="mt-1.5 {{ $selectClass }}">
                            <option value="">Choose customer</option>
                            <template x-for="customer in customers" :key="customer.id">
                                <option :value="String(customer.id)" x-text="customer.label"></option>
                            </template>
                        </select>
                    </label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block {{ $labelClass }}">
                            <span>Start Date</span>
                            <span class="relative mt-1.5 block custom-datepicker">
                                <input type="text" x-model="generationForm.period_start" autocomplete="off" class="{{ $dateInputClass }}" data-billing-date>
                                {!! $dateIcon !!}
                            </span>
                        </label>
                        <label class="block {{ $labelClass }}">
                            <span>End Date</span>
                            <span class="relative mt-1.5 block custom-datepicker">
                                <input type="text" x-model="generationForm.period_end" autocomplete="off" class="{{ $dateInputClass }}" data-billing-date>
                                {!! $dateIcon !!}
                            </span>
                        </label>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Previous Unpaid Balances</p>
                                <p class="mt-1 {{ $captionClass }}">Selected invoices will be rolled into the new statement.</p>
                            </div>
                            <span x-show="rolloverLoading" class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Loading...</span>
                        </div>
                        <div class="mt-3 space-y-2">
                            <template x-for="invoice in rolloverInvoices" :key="invoice.id">
                                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-theme-sm dark:border-gray-800 dark:bg-gray-900">
                                    <span class="min-w-0">
                                        <span class="font-medium text-gray-800 dark:text-white/90" x-text="invoice.invoice_number"></span>
                                        <span class="ml-2 text-gray-500 dark:text-gray-400" x-text="'Remaining: ' + formatMoney(invoiceRemaining(invoice))"></span>
                                    </span>
                                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" :value="String(invoice.id)" x-model="selectedRolloverIds">
                                </label>
                            </template>
                            <p x-show="!rolloverLoading && generationForm.customer_id && rolloverInvoices.length === 0" class="{{ $captionClass }}">No unpaid invoices available for rollover.</p>
                            <p x-show="!generationForm.customer_id" class="{{ $captionClass }}">Choose a customer to check rollover invoices.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}" :disabled="savingGenerate">Cancel</button>
                    <button type="button" x-on:click="submitGenerate()" class="{{ $primaryButtonClass }}" :disabled="savingGenerate">
                        <span x-show="!savingGenerate">Generate Bill</span>
                        <span x-show="savingGenerate">Generating...</span>
                    </button>
                </div>
            </div>
        </div>

        <div x-show="modal === 'payment'" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center p-4">
            <div x-show="modal === 'payment'" x-transition.opacity class="absolute inset-0 bg-gray-900/55"></div>
            <div
                x-show="modal === 'payment'"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-3 scale-95"
                class="relative max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Receive Payment</h3>
                        <p class="mt-1 {{ $captionClass }}" x-text="selectedInvoice ? selectedInvoice.customer_name : ''"></p>
                    </div>
                    <button type="button" x-on:click="closeModal()" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]" aria-label="Close modal">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Invoice #</p>
                        <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedInvoice?.invoice_number || '-'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Period</p>
                        <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedInvoice?.period_label || '-'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Remaining Due</p>
                        <p class="mt-1 text-theme-sm font-semibold text-error-600 dark:text-error-400" x-text="formatMoney(selectedInvoice?.remaining_balance || 0)"></p>
                    </div>
                </div>

                <div class="mt-6 space-y-5">
                    <div>
                        <p class="{{ $labelClass }}">Payment Strategy</p>
                        <div class="mt-1.5 grid grid-cols-2 gap-2">
                            <button type="button" x-on:click="setPaymentType('partial')" class="h-11 rounded-lg border px-4 text-theme-sm font-medium transition"
                                :class="paymentType === 'partial' ? 'border-warning-300 bg-warning-50 text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]'">
                                Partial
                            </button>
                            <button type="button" x-on:click="setPaymentType('full')" class="h-11 rounded-lg border px-4 text-theme-sm font-medium transition"
                                :class="paymentType === 'full' ? 'border-success-300 bg-success-50 text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]'">
                                Full
                            </button>
                        </div>
                    </div>

                    <label class="block {{ $labelClass }}">
                        <span>Payment Amount</span>
                        <div class="mt-1.5 flex rounded-lg shadow-theme-xs">
                            <span class="inline-flex h-11 items-center rounded-l-lg border border-r-0 border-gray-300 px-3 text-theme-sm font-semibold text-gray-500 dark:border-gray-700 dark:text-gray-400">&#8369;</span>
                            <input type="number" x-model="paymentForm.amount" min="1" step="0.01" class="h-11 min-w-0 flex-1 border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="0.00">
                            <button type="button" x-on:click="payMax()" class="inline-flex h-11 items-center rounded-r-lg border border-l-0 border-gray-300 px-4 text-theme-sm font-medium text-success-700 transition hover:bg-success-50 dark:border-gray-700 dark:text-success-400 dark:hover:bg-success-500/10">Max</button>
                        </div>
                        <p x-show="paymentAdvanceAmount() > 0" class="mt-1.5 text-theme-xs font-medium text-blue-light-700 dark:text-blue-light-400">
                            Includes advance payment of <span x-text="formatMoney(paymentAdvanceAmount())"></span>.
                        </p>
                    </label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block {{ $labelClass }}">
                            <span>Date</span>
                            <span class="relative mt-1.5 block custom-datepicker">
                                <input type="text" x-model="paymentForm.payment_date" autocomplete="off" class="{{ $dateInputClass }}" data-billing-date>
                                {!! $dateIcon !!}
                            </span>
                        </label>
                        <label class="block {{ $labelClass }}">
                            <span>Method</span>
                            <select x-model="paymentForm.payment_method" class="mt-1.5 {{ $selectClass }}">
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="GCash/E-Wallet">GCash / E-Wallet</option>
                            </select>
                        </label>
                    </div>

                    <label class="block {{ $labelClass }}">
                        <span>Reference No.</span>
                        <input type="text" x-model="paymentForm.reference_no" class="mt-1.5 {{ $inputClass }}">
                    </label>

                    <label class="block {{ $labelClass }}">
                        <span>Remarks</span>
                        <textarea x-model="paymentForm.remarks" class="mt-1.5 {{ $textareaClass }}" placeholder="Add a note"></textarea>
                    </label>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}" :disabled="savingPayment">Cancel</button>
                    <button type="button" x-on:click="submitPayment()" class="{{ $primaryButtonClass }}" :disabled="savingPayment">
                        <span x-show="!savingPayment">Confirm Payment</span>
                        <span x-show="savingPayment">Saving...</span>
                    </button>
                </div>
            </div>
        </div>

        <div x-show="confirmation.open" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center p-4">
            <div x-show="confirmation.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55"></div>
            <div
                x-show="confirmation.open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-3 scale-95"
                class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start gap-4">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                        :class="confirmation.tone === 'danger' ? 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400' : 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v5M12 16.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="confirmation.title"></h3>
                        <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400" x-text="confirmation.message"></p>
                        <p class="mt-2 text-theme-sm font-medium text-gray-700 dark:text-gray-300" x-show="confirmation.details" x-text="confirmation.details"></p>
                    </div>
                </div>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="resolveConfirmation(false)" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button type="button" x-on:click="resolveConfirmation(true)" class="{{ $primaryButtonClass }}" :class="confirmation.tone === 'danger' ? 'bg-error-500 hover:bg-error-600' : ''" x-text="confirmation.confirmText"></button>
                </div>
            </div>
        </div>

        <div x-show="messageDialog.open" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center p-4">
            <div x-show="messageDialog.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55"></div>
            <div
                x-show="messageDialog.open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-3 scale-95"
                class="relative w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start gap-4">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v5M12 16.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="messageDialog.title"></h3>
                        <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400" x-text="messageDialog.message"></p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" x-on:click="closeMessageDialog()" class="{{ $secondaryButtonClass }}">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function billingInvoicesModule(config) {
            return {
                activeTab: config.activeTab || 'unpaid',
                customers: config.customers || [],
                receipts: (config.receipts || []).map((receipt) => ({ ...receipt, saving: false })),
                receiptCustomerId: '',
                receiptInvoiceNumber: '',
                receiptIssueStatus: '',
                receiptPage: 1,
                receiptPerPage: 10,
                receiptPerPageOptions: [10, 50, 100],
                modal: null,
                selectedInvoice: null,
                generationForm: {
                    customer_id: '',
                    period_start: config.defaultPeriodStart,
                    period_end: config.defaultPeriodEnd
                },
                rolloverInvoices: [],
                selectedRolloverIds: [],
                rolloverLoading: false,
                savingGenerate: false,
                paymentType: 'partial',
                paymentForm: {
                    customer_id: '',
                    invoice_id: '',
                    amount: '',
                    payment_date: config.defaultPeriodEnd,
                    payment_method: 'Cash',
                    reference_no: '',
                    remarks: ''
                },
                savingPayment: false,
                toast: { open: false, message: '', timer: null },
                confirmation: {
                    open: false,
                    title: '',
                    message: '',
                    details: '',
                    confirmText: 'Confirm',
                    tone: 'brand',
                    resolver: null
                },
                messageDialog: {
                    open: false,
                    title: '',
                    message: ''
                },

                init() {
                    this.initDatePickers();
                    this.showStoredToast();
                },

                initDatePickers() {
                    if (!window.flatpickr) return;

                    this.$nextTick(() => {
                        document.querySelectorAll('[data-billing-date]').forEach((input) => {
                            if (input._billingDatePicker) return;

                            input._billingDatePicker = window.flatpickr(input, {
                                static: true,
                                monthSelectorType: 'static',
                                dateFormat: 'Y-m-d',
                                defaultDate: input.value || null,
                                allowInput: false,
                                disableMobile: true,
                                onChange: (selectedDates, dateStr) => {
                                    input.value = dateStr;
                                    input.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            });
                        });
                    });
                },

                receiptInvoiceOptions() {
                    const customerId = String(this.receiptCustomerId || '');
                    const options = this.receipts
                        .filter((receipt) => !customerId || String(receipt.customer_id) === customerId)
                        .map((receipt) => receipt.invoice_number);

                    return [...new Set(options)];
                },

                filteredReceipts() {
                    const customerId = String(this.receiptCustomerId || '');

                    return this.receipts.filter((receipt) => {
                        const customerMatches = !customerId || String(receipt.customer_id) === customerId;
                        const invoiceMatches = !this.receiptInvoiceNumber || receipt.invoice_number === this.receiptInvoiceNumber;
                        const issueMatches = !this.receiptIssueStatus
                            || (this.receiptIssueStatus === 'issued' && receipt.is_issued)
                            || (this.receiptIssueStatus === 'pending' && !receipt.is_issued);

                        return customerMatches && invoiceMatches && issueMatches;
                    });
                },

                receiptTotalPages() {
                    return Math.max(1, Math.ceil(this.filteredReceipts().length / this.receiptPerPage));
                },

                receiptCurrentPage() {
                    return Math.min(Math.max(Number(this.receiptPage) || 1, 1), this.receiptTotalPages());
                },

                paginatedReceipts() {
                    const page = this.receiptCurrentPage();
                    const start = (page - 1) * this.receiptPerPage;

                    return this.filteredReceipts().slice(start, start + this.receiptPerPage);
                },

                receiptFirstItem() {
                    return this.filteredReceipts().length > 0
                        ? ((this.receiptCurrentPage() - 1) * this.receiptPerPage) + 1
                        : 0;
                },

                receiptLastItem() {
                    return Math.min(this.receiptCurrentPage() * this.receiptPerPage, this.filteredReceipts().length);
                },

                receiptPaginationItems() {
                    const current = this.receiptCurrentPage();
                    const last = this.receiptTotalPages();
                    const pages = [...new Set([1, current - 1, current, current + 1, last])]
                        .filter((page) => page >= 1 && page <= last)
                        .sort((a, b) => a - b);
                    const items = [];

                    pages.forEach((page, index) => {
                        if (index > 0 && page > pages[index - 1] + 1) {
                            items.push({ key: `ellipsis-${pages[index - 1]}-${page}`, ellipsis: true });
                        }

                        items.push({ key: `page-${page}`, page, ellipsis: false });
                    });

                    return items;
                },

                goToReceiptPage(page) {
                    this.receiptPage = Math.min(Math.max(Number(page) || 1, 1), this.receiptTotalPages());
                },

                resetReceiptPage() {
                    this.receiptPage = 1;
                },

                resetReceiptFilters() {
                    this.receiptCustomerId = '';
                    this.receiptInvoiceNumber = '';
                    this.receiptIssueStatus = '';
                    this.resetReceiptPage();
                },

                openGenerate() {
                    this.generationForm = {
                        customer_id: '',
                        period_start: config.defaultPeriodStart,
                        period_end: config.defaultPeriodEnd
                    };
                    this.rolloverInvoices = [];
                    this.selectedRolloverIds = [];
                    this.modal = 'generate';
                    this.$nextTick(() => this.initDatePickers());
                },

                async loadRolloverInvoices() {
                    this.rolloverInvoices = [];
                    this.selectedRolloverIds = [];

                    if (!this.generationForm.customer_id) return;

                    this.rolloverLoading = true;

                    try {
                        const response = await fetch(config.unpaidBaseUrl + '/' + this.generationForm.customer_id + '/unpaid-invoices', {
                            headers: { 'Accept': 'application/json' }
                        });
                        this.rolloverInvoices = response.ok ? await response.json() : [];
                    } catch (error) {
                        this.rolloverInvoices = [];
                    } finally {
                        this.rolloverLoading = false;
                    }
                },

                invoiceRemaining(invoice) {
                    return Math.max(0, Number(invoice.total_amount_due || 0) - Number(invoice.payments_received || 0));
                },

                async submitGenerate() {
                    if (!this.generationForm.customer_id || !this.generationForm.period_start || !this.generationForm.period_end) {
                        this.showMessage('Choose a customer and billing period.');
                        return;
                    }

                    if (this.generationForm.period_start > this.generationForm.period_end) {
                        this.showMessage('The start date cannot be later than the end date.');
                        return;
                    }

                    const confirmed = await this.askConfirmation({
                        title: 'Generate Billing Statement',
                        message: 'Generate this billing statement from ledger transactions?',
                        confirmText: 'Generate Bill',
                        tone: 'brand'
                    });

                    if (!confirmed) return;

                    this.savingGenerate = true;
                    const data = await this.postJson(config.generateUrl, {
                        ...this.generationForm,
                        rollover_invoices: this.selectedRolloverIds
                    });
                    this.savingGenerate = false;

                    if (!data.success) {
                        this.showMessage(data.message || 'Unable to generate invoice.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Invoice generated successfully.');
                },

                openPayment(invoice) {
                    if (Number(invoice.remaining_balance || 0) <= 0) {
                        this.showMessage('This invoice has no remaining balance.');
                        return;
                    }

                    this.selectedInvoice = invoice;
                    this.paymentType = 'partial';
                    this.paymentForm = {
                        customer_id: invoice.customer_id,
                        invoice_id: invoice.id,
                        amount: '',
                        payment_date: config.defaultPeriodEnd,
                        payment_method: 'Cash',
                        reference_no: invoice.invoice_number,
                        remarks: ''
                    };
                    this.modal = 'payment';
                    this.$nextTick(() => this.initDatePickers());
                },

                setPaymentType(type) {
                    if (!this.selectedInvoice) return;

                    this.paymentType = type;

                    if (type === 'full') {
                        const remaining = Number(this.selectedInvoice.remaining_balance || 0);
                        const balance = Number(this.selectedInvoice.current_balance || 0);
                        const amount = balance > 0 ? Math.min(remaining, balance) : remaining;
                        this.paymentForm.amount = Number(amount).toFixed(2);
                    } else {
                        this.paymentForm.amount = '';
                    }
                },

                payMax() {
                    if (!this.selectedInvoice) return;

                    this.paymentForm.amount = Number(this.selectedInvoice.remaining_balance || 0).toFixed(2);
                },

                paymentAdvanceAmount() {
                    const amount = Number(this.paymentForm.amount || 0);
                    const max = Number(this.selectedInvoice?.remaining_balance || 0);

                    return amount > max + 0.01 ? amount - max : 0;
                },

                async submitPayment() {
                    const amount = Number(this.paymentForm.amount || 0);

                    if (!this.selectedInvoice || amount < 1) {
                        this.showMessage('Enter a valid payment amount.');
                        return;
                    }

                    const advance = this.paymentAdvanceAmount();
                    const confirmed = await this.askConfirmation({
                        title: 'Confirm Payment',
                        message: 'Record payment of ' + this.formatMoney(amount) + ' for ' + this.selectedInvoice.invoice_number + '?',
                        details: advance > 0 ? 'This includes advance credit of ' + this.formatMoney(advance) + '.' : '',
                        confirmText: 'Record Payment',
                        tone: 'brand'
                    });

                    if (!confirmed) return;

                    this.savingPayment = true;
                    const data = await this.postJson(config.payUrl, this.paymentForm);
                    this.savingPayment = false;

                    if (!data.success) {
                        this.showMessage(data.message || 'Unable to record payment.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Payment recorded successfully.');
                },

                async confirmDeleteInvoice(invoice) {
                    const confirmed = await this.askConfirmation({
                        title: 'Delete Invoice',
                        message: 'Delete invoice ' + invoice.invoice_number + '? Its ledger rows will become billable again.',
                        confirmText: 'Delete Invoice',
                        tone: 'danger'
                    });

                    if (!confirmed) return;

                    const data = await this.postJson(config.destroyUrl, { id: invoice.id });

                    if (!data.success) {
                        this.showMessage(data.message || 'Unable to delete invoice.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Invoice deleted.');
                },

                async toggleReceiptIssued(receipt, checked) {
                    const previous = Boolean(receipt.is_issued);
                    receipt.saving = true;

                    const data = await this.postJson(receipt.issued_url, { is_issued: checked });
                    receipt.saving = false;

                    if (!data.success) {
                        receipt.is_issued = previous;
                        this.showMessage(data.message || 'Unable to update receipt status.');
                        return;
                    }

                    receipt.is_issued = Boolean(data.is_issued);
                    receipt.issued_at_label = data.issued_at || '';
                    this.showToast(data.message || 'Receipt status updated.');
                },

                closeModal() {
                    if (this.savingGenerate || this.savingPayment) return;

                    this.modal = null;
                    this.selectedInvoice = null;
                },

                async postJson(url, payload) {
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await response.json().catch(() => ({}));

                        if (response.ok) {
                            return data;
                        }

                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                        return { success: false, message: data.message || firstError || 'Request failed.' };
                    } catch (error) {
                        return { success: false, message: 'An unexpected error occurred.' };
                    }
                },

                askConfirmation(options = {}) {
                    return new Promise((resolve) => {
                        this.confirmation = {
                            open: true,
                            title: options.title || 'Confirm Action',
                            message: options.message || 'Please confirm this action.',
                            details: options.details || '',
                            confirmText: options.confirmText || 'Confirm',
                            tone: options.tone || 'brand',
                            resolver: resolve
                        };
                    });
                },

                resolveConfirmation(confirmed) {
                    const resolver = this.confirmation.resolver;
                    this.confirmation.open = false;
                    this.confirmation.resolver = null;

                    if (resolver) {
                        resolver(confirmed);
                    }
                },

                showMessage(message, title = 'Unable to Continue') {
                    this.messageDialog = {
                        open: true,
                        title,
                        message
                    };
                },

                closeMessageDialog() {
                    this.messageDialog.open = false;
                },

                showToast(message) {
                    this.closeToast();
                    this.toast.message = message;
                    this.toast.open = true;
                    this.toast.timer = window.setTimeout(() => this.closeToast(), 3000);
                },

                closeToast() {
                    if (this.toast.timer) {
                        window.clearTimeout(this.toast.timer);
                    }

                    this.toast = { open: false, message: '', timer: null };
                },

                showStoredToast() {
                    let message = '';

                    try {
                        message = window.sessionStorage.getItem(config.storedToastKey) || '';
                        window.sessionStorage.removeItem(config.storedToastKey);
                    } catch (error) {
                        message = '';
                    }

                    if (message) {
                        this.$nextTick(() => this.showToast(message));
                    }
                },

                reloadWithToast(message) {
                    try {
                        window.sessionStorage.setItem(config.storedToastKey, message);
                    } catch (error) {
                        this.showToast(message);
                    }

                    window.location.reload();
                },

                formatMoney(value) {
                    return '\u20b1' + new Intl.NumberFormat('en-PH', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(Number(value || 0));
                },

                formatBalance(value) {
                    const amount = Number(value || 0);

                    return amount < 0 ? 'Advance: ' + this.formatMoney(Math.abs(amount)) : 'Balance: ' + this.formatMoney(amount);
                }
            };
        }
    </script>
@endpush
