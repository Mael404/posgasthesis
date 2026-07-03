@extends('layouts.app')

@section('content')
    @php
        $invoicePaginator = $invoices ?? collect();
        $invoiceRows = $invoicePaginator instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($invoicePaginator->items())
            : collect($invoicePaginator);
        $summaryInvoices = collect($summaryInvoices ?? $invoiceRows);
        $missingBillingTables = $missingBillingTables ?? [];
        $activeBillingTab = $activeBillingTab ?? 'unpaid';
        $activeBillingTab = in_array($activeBillingTab, ['unpaid', 'partial'], true) ? $activeBillingTab : 'unpaid';
        $invoiceSearch = trim((string) ($invoiceSearch ?? ''));
        $perPageOptions = $perPageOptions ?? [10, 50, 100];
        $perPage = (int) ($perPage ?? 10);
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 10;
        $invoiceTotal = method_exists($invoicePaginator, 'total') ? $invoicePaginator->total() : $invoiceRows->count();
        $invoiceFirstItem = method_exists($invoicePaginator, 'firstItem') ? $invoicePaginator->firstItem() : ($invoiceTotal > 0 ? 1 : null);
        $invoiceLastItem = method_exists($invoicePaginator, 'lastItem') ? $invoicePaginator->lastItem() : $invoiceRows->count();

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $dateInputClass = 'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $textareaClass = 'min-h-[84px] w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $printIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
        $payIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-success-200 bg-success-50 text-success-700 shadow-theme-xs transition hover:border-success-300 hover:bg-success-100 dark:border-success-500/25 dark:bg-success-500/10 dark:text-success-400 dark:hover:bg-success-500/15';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thCompactClass = 'px-3 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thCompactNumberClass = 'px-3 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdCustomerClass = 'px-3 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $tdCompactClass = 'px-3 py-4 align-middle text-theme-xs text-gray-700 dark:text-gray-300';
        $tdCompactNumberClass = 'px-3 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $dateIcon = '<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 5.25V3.75M16 5.25V3.75M4.75 9.25h14.5M6 20.25h12A2.25 2.25 0 0 0 20.25 18V7A2.25 2.25 0 0 0 18 4.75H6A2.25 2.25 0 0 0 3.75 7v11A2.25 2.25 0 0 0 6 20.25Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';

        $dateLabel = function ($value, $format = 'M d, Y') {
            if (empty($value)) {
                return 'N/A';
            }

            try {
                return \Carbon\Carbon::parse($value)->format($format);
            } catch (\Throwable) {
                return (string) $value;
            }
        };

        $billingCustomerName = function ($invoice) {
            $businessName = trim((string) ($invoice->business_name ?? ''));
            $ownerName = trim((string) ($invoice->owner_name ?? ''));
            $hasBusinessName = $businessName !== '' && strtoupper($businessName) !== 'N/A';
            $hasOwnerName = $ownerName !== '' && strtoupper($ownerName) !== 'N/A';

            if ($hasBusinessName && $hasOwnerName && strcasecmp($businessName, $ownerName) !== 0) {
                return strtoupper($businessName) . ' - ' . ucwords(strtolower($ownerName));
            }

            if ($hasBusinessName) {
                return strtoupper($businessName);
            }

            return $hasOwnerName ? ucwords(strtolower($ownerName)) : 'Customer #' . ($invoice->customer_id ?? $invoice->id ?? '');
        };

        $invoicePayloadFor = function ($invoice) use ($billingCustomerName, $dateLabel) {
            $total = (float) ($invoice->total_amount_due ?? 0);
            $paid = (float) ($invoice->payments_received ?? 0);
            $remaining = max($total - $paid, 0);
            $periodStart = ! empty($invoice->period_start) ? $dateLabel($invoice->period_start, 'M d') : 'N/A';
            $periodEnd = ! empty($invoice->period_end) ? $dateLabel($invoice->period_end, 'M d, Y') : 'N/A';

            return [
                'id' => (int) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => (int) $invoice->customer_id,
                'customer_name' => $billingCustomerName($invoice),
                'current_balance' => (float) ($invoice->current_balance ?? 0),
                'period_start' => $invoice->period_start,
                'period_end' => $invoice->period_end,
                'period_label' => $periodStart !== 'N/A' && $periodEnd !== 'N/A' ? $periodStart . ' - ' . $periodEnd : 'N/A',
                'due_label' => $dateLabel($invoice->due_date ?? null),
                'total_amount_due' => round($total, 2),
                'payments_received' => round($paid, 2),
                'remaining_balance' => round($remaining, 2),
                'status' => $invoice->status ?? 'Unpaid',
                'print_url' => route('staff.billing.print', $invoice->id),
            ];
        };

        $summary = [
            'unpaid' => $summaryInvoices->where('status', 'Unpaid')->count(),
            'partial' => $summaryInvoices->where('status', 'Partial')->count(),
            'open_total' => $summaryInvoices->sum(fn ($invoice) => max((float) ($invoice->total_amount_due ?? 0) - (float) ($invoice->payments_received ?? 0), 0)),
            'due_today' => $summaryInvoices->filter(function ($invoice) {
                if (empty($invoice->due_date)) {
                    return false;
                }

                try {
                    return \Carbon\Carbon::parse($invoice->due_date)->isToday();
                } catch (\Throwable) {
                    return false;
                }
            })->count(),
        ];

        $billingTabUrl = function ($tab) use ($perPage, $invoiceSearch) {
            $params = [
                'tab' => $tab,
                'per_page' => $perPage,
            ];

            if ($invoiceSearch !== '') {
                $params['invoice_search'] = $invoiceSearch;
            }

            return route('staff.billing.index', $params);
        };

        $formatBalanceLabel = function ($value) {
            $amount = (float) ($value ?? 0);
            $label = $amount < 0 ? 'Advance: ' : 'Balance: ';

            return $label . '&#8369;' . number_format(abs($amount), 2);
        };

        $statusBadgeClass = function ($status) {
            return $status === 'Partial'
                ? 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400'
                : 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400';
        };
    @endphp

    <x-common.page-breadcrumb pageTitle="Billing" />

    <div
        x-data="staffBillingModule({
            csrfToken: @js(csrf_token()),
            payUrl: @js(route('staff.pump.storePayment')),
            storedToastKey: 'staff-billing-toast',
            defaultPaymentDate: @js(now()->toDateString()),
            sessionMessage: @js(session('success') ?: session('status')),
            sessionError: @js(session('error'))
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', modal !== null || confirmation.open || messageDialog.open)"
        x-on:keydown.escape.window="closeTopLayer()"
        class="space-y-6">
        <style>
            [x-cloak] { display: none !important; }
            .staff-billing-module input[type="number"] {
                appearance: textfield;
                -moz-appearance: textfield;
            }
            .staff-billing-module input[type="number"]::-webkit-inner-spin-button,
            .staff-billing-module input[type="number"]::-webkit-outer-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
        </style>

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
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m6.75 12.25 3.25 3.25 7.25-7.25" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7" />
                    </svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-theme-sm font-semibold text-white">Success</p>
                    <p class="mt-1 text-theme-sm text-white/90" x-text="toast.message"></p>
                </div>
                <button type="button" x-on:click="closeToast()" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Close success message">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
                </button>
            </div>
        </div>

        @if ($missingBillingTables)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing billing table(s): {{ implode(', ', $missingBillingTables) }}.
            </div>
        @endif

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Staff Billing</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Billing</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">Review unpaid and partially paid statements, print invoices, and log customer collections for shift verification.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:min-w-[620px]">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Open Balance</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) $summary['open_total'], 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-error-100 bg-error-50/60 px-4 py-3 dark:border-error-500/20 dark:bg-error-500/10">
                        <p class="{{ $labelClass }}">Unpaid</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-error-700 dark:text-error-400">{{ number_format($summary['unpaid']) }}</p>
                    </div>
                    <div class="rounded-xl border border-warning-100 bg-warning-50/60 px-4 py-3 dark:border-warning-500/20 dark:bg-warning-500/10">
                        <p class="{{ $labelClass }}">Partial</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-warning-700 dark:text-warning-400">{{ number_format($summary['partial']) }}</p>
                    </div>
                    <div class="rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3 dark:border-brand-500/20 dark:bg-brand-500/10">
                        <p class="{{ $labelClass }}">Due Today</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-brand-700 dark:text-brand-400">{{ number_format($summary['due_today']) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }} p-2 md:p-2">
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ $billingTabUrl('unpaid') }}" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left {{ $activeBillingTab === 'unpaid' ? 'bg-error-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white' }}">
                    Unpaid Bills
                    <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-theme-xs">{{ $summary['unpaid'] }}</span>
                </a>
                <a href="{{ $billingTabUrl('partial') }}" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left {{ $activeBillingTab === 'partial' ? 'bg-warning-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white' }}">
                    Partially Paid
                    <span class="ml-1 rounded-full bg-white/20 px-2 py-0.5 text-theme-xs">{{ $summary['partial'] }}</span>
                </a>
            </div>
        </section>

        <section class="{{ $cardClass }} staff-billing-module">
            <div class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $activeBillingTab === 'partial' ? 'Partially Paid' : 'Unpaid Bills' }}</h2>
                    <p class="mt-1 {{ $captionClass }}">Search by invoice number, business name, or account owner.</p>
                </div>

                <form method="GET" action="{{ route('staff.billing.index') }}" class="w-full lg:w-[380px]">
                    <input type="hidden" name="tab" value="{{ $activeBillingTab }}">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <label class="block {{ $labelClass }}">
                        <span>Search invoices</span>
                        <input type="search" name="invoice_search" value="{{ $invoiceSearch }}" class="mt-1.5 {{ $inputClass }}" placeholder="Invoice number or customer">
                    </label>
                </form>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="w-full table-fixed">
                        @if ($activeBillingTab === 'partial')
                            <colgroup>
                                <col class="w-[13%]">
                                <col class="w-[25%]">
                                <col class="w-[13%]">
                                <col class="w-[13%]">
                                <col class="w-[14%]">
                                <col class="w-[10%]">
                                <col class="w-[12%]">
                            </colgroup>
                        @else
                            <colgroup>
                                <col class="w-[13%]">
                                <col class="w-[25%]">
                                <col class="w-[16%]">
                                <col class="w-[11%]">
                                <col class="w-[13%]">
                                <col class="w-[10%]">
                                <col class="w-[12%]">
                            </colgroup>
                        @endif
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thCompactClass }}">Invoice #</th>
                                <th class="{{ $thCompactClass }}">Customer</th>
                                @if ($activeBillingTab === 'partial')
                                    <th class="{{ $thCompactNumberClass }}">Total Billed</th>
                                    <th class="{{ $thCompactNumberClass }}">Paid So Far</th>
                                    <th class="{{ $thCompactNumberClass }}">Remaining Bal.</th>
                                @else
                                    <th class="{{ $thCompactClass }}">Period Covered</th>
                                    <th class="{{ $thCompactClass }}">Due Date</th>
                                    <th class="{{ $thCompactNumberClass }}">Amount Due</th>
                                @endif
                                <th class="{{ $thCompactClass }}">Status</th>
                                <th class="{{ $thCompactClass }}">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($invoiceRows as $invoice)
                                @php
                                    $invoiceRow = $invoicePayloadFor($invoice);
                                @endphp
                                <tr>
                                    <td class="{{ $tdCompactClass }} font-medium text-gray-800 dark:text-white/90">{{ $invoiceRow['invoice_number'] }}</td>
                                    <td class="{{ $tdCustomerClass }}">
                                        <div class="break-words font-medium leading-snug text-gray-800 dark:text-white/90">{{ $invoiceRow['customer_name'] }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{!! $formatBalanceLabel($invoiceRow['current_balance']) !!}</div>
                                    </td>
                                    @if ($activeBillingTab === 'partial')
                                        <td class="{{ $tdCompactNumberClass }} font-semibold">&#8369;{{ number_format((float) $invoiceRow['total_amount_due'], 2) }}</td>
                                        <td class="{{ $tdCompactNumberClass }} font-semibold text-success-600 dark:text-success-500">&#8369;{{ number_format((float) $invoiceRow['payments_received'], 2) }}</td>
                                        <td class="{{ $tdCompactNumberClass }} font-semibold text-error-600 dark:text-error-400">&#8369;{{ number_format((float) $invoiceRow['remaining_balance'], 2) }}</td>
                                    @else
                                        <td class="{{ $tdCompactClass }}">{{ $invoiceRow['period_label'] }}</td>
                                        <td class="{{ $tdCompactClass }} font-medium text-error-600 dark:text-error-400">{{ $invoiceRow['due_label'] }}</td>
                                        <td class="{{ $tdCompactNumberClass }} font-semibold text-error-600 dark:text-error-400">&#8369;{{ number_format((float) $invoiceRow['remaining_balance'], 2) }}</td>
                                    @endif
                                    <td class="{{ $tdCompactClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-semibold {{ $statusBadgeClass($invoiceRow['status']) }}">{{ strtoupper($invoiceRow['status']) }}</span>
                                    </td>
                                    <td class="{{ $tdCompactClass }}">
                                        <div class="flex items-center gap-1.5">
                                            <a href="{{ $invoiceRow['print_url'] }}" target="_blank" class="{{ $printIconButtonClass }}" title="Print invoice" aria-label="Print invoice">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8V4h10v4M7 17H5.75A2.75 2.75 0 0 1 3 14.25v-3.5A2.75 2.75 0 0 1 5.75 8h12.5A2.75 2.75 0 0 1 21 10.75v3.5A2.75 2.75 0 0 1 18.25 17H17M7 14h10v6H7v-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </a>
                                            <button type="button" x-on:click="openPayment(@js($invoiceRow))" class="{{ $payIconButtonClass }}" title="Process payment" aria-label="Process payment">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7.75h16v8.5H4v-8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 12h.01M17 12h.01M12 14.75a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                            </button>
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
                                            <p class="mt-1 {{ $captionClass }}">{{ $invoiceSearch !== '' ? 'Try a different search term.' : 'There are no bills in this status right now.' }}</p>
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
                    <form method="GET" action="{{ route('staff.billing.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="{{ $activeBillingTab }}">
                        @if ($invoiceSearch !== '')
                            <input type="hidden" name="invoice_search" value="{{ $invoiceSearch }}">
                        @endif
                        <label for="staff-billing-per-page" class="text-theme-sm text-gray-500 dark:text-gray-400">Rows</label>
                        <select id="staff-billing-per-page" name="per_page" class="h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" onchange="this.form.submit()">
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

        <div x-show="modal === 'payment'" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="modal === 'payment'" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm" x-on:click="closeModal()"></div>
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

                <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Invoice #</p>
                        <p class="mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedInvoice?.invoice_number || '-'"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Period</p>
                        <p class="mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedInvoice?.period_label || '-'"></p>
                    </div>
                    <div class="rounded-xl border border-error-100 bg-error-50/60 p-4 dark:border-error-500/20 dark:bg-error-500/10">
                        <p class="{{ $labelClass }}">Remaining Due</p>
                        <p class="mt-1 text-theme-sm font-semibold text-error-700 dark:text-error-400" x-text="formatMoney(selectedInvoice?.remaining_balance || 0)"></p>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Account</p>
                        <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="formatBalance(selectedInvoice?.current_balance || 0)"></p>
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
                            <input type="number" x-model="paymentForm.amount" min="1" step="0.01" class="h-11 min-w-0 flex-1 border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:disabled:bg-white/[0.03]" placeholder="0.00" :readonly="paymentType === 'full'">
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
                                <input type="text" x-model="paymentForm.payment_date" autocomplete="off" class="{{ $dateInputClass }}" data-staff-billing-date>
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
                        <span>OR / Invoice No.</span>
                        <input type="text" x-model="paymentForm.or_number" class="mt-1.5 {{ $inputClass }} bg-gray-50 text-gray-500 dark:bg-white/[0.03]" readonly>
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

        <div x-show="confirmation.open" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="confirmation.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
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
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">
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
                    <button type="button" x-on:click="resolveConfirmation(true)" class="{{ $primaryButtonClass }}" x-text="confirmation.confirmText"></button>
                </div>
            </div>
        </div>

        <div x-show="messageDialog.open" x-cloak class="fixed inset-0 z-999999 flex items-center justify-center overflow-y-auto p-4">
            <div x-show="messageDialog.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
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
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="messageDialog.type === 'info' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400' : 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400'">
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
        function staffBillingModule(config) {
            return {
                modal: null,
                selectedInvoice: null,
                paymentType: 'partial',
                paymentForm: {
                    customer_id: '',
                    invoice_id: '',
                    amount: '',
                    payment_date: config.defaultPaymentDate,
                    payment_method: 'Cash',
                    or_number: '',
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
                    resolver: null
                },
                messageDialog: { open: false, type: 'error', title: '', message: '' },

                init() {
                    this.initDatePickers();
                    this.showStoredToast();

                    if (config.sessionMessage) {
                        this.showToast(config.sessionMessage);
                    }

                    if (config.sessionError) {
                        this.showMessage(config.sessionError);
                    }
                },

                initDatePickers() {
                    if (!window.flatpickr) return;

                    this.$nextTick(() => {
                        document.querySelectorAll('[data-staff-billing-date]').forEach((input) => {
                            if (input._staffBillingDatePicker) return;

                            input._staffBillingDatePicker = window.flatpickr(input, {
                                static: true,
                                monthSelectorType: 'static',
                                dateFormat: 'Y-m-d',
                                defaultDate: input.value || config.defaultPaymentDate,
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

                openPayment(invoice) {
                    const remaining = Number(invoice.remaining_balance || 0);

                    if (remaining <= 0) {
                        this.showMessage('This invoice has no remaining balance.', 'Already Paid', 'info');
                        return;
                    }

                    this.selectedInvoice = invoice;
                    this.paymentType = 'partial';
                    this.paymentForm = {
                        customer_id: invoice.customer_id,
                        invoice_id: invoice.id,
                        amount: '',
                        payment_date: config.defaultPaymentDate,
                        payment_method: 'Cash',
                        or_number: invoice.invoice_number,
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

                    this.paymentType = 'partial';
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
                        this.showMessage('Enter a payment amount of at least ' + this.formatMoney(1) + '.');
                        return;
                    }

                    const advance = this.paymentAdvanceAmount();
                    const confirmed = await this.askConfirmation({
                        title: 'Confirm Payment',
                        message: 'Record ' + this.formatMoney(amount) + ' for invoice ' + this.selectedInvoice.invoice_number + '?',
                        details: advance > 0 ? 'This includes advance credit of ' + this.formatMoney(advance) + ' for the customer account.' : '',
                        confirmText: 'Confirm Payment'
                    });

                    if (!confirmed) return;

                    this.savingPayment = true;
                    const data = await this.postJson(config.payUrl, this.paymentForm);
                    this.savingPayment = false;

                    if (!data.success) {
                        this.showMessage(data.message || 'Unable to record payment.');
                        return;
                    }

                    this.reloadWithToast(data.message || 'Payment logged for shift verification.');
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

                closeModal() {
                    if (this.savingPayment) return;

                    this.modal = null;
                    this.selectedInvoice = null;
                },

                closeTopLayer() {
                    if (this.confirmation.open) {
                        this.resolveConfirmation(false);
                        return;
                    }

                    if (this.messageDialog.open) {
                        this.closeMessageDialog();
                        return;
                    }

                    if (this.modal) {
                        this.closeModal();
                    }
                },

                showMessage(message, title = 'Unable to Continue', type = 'error') {
                    this.messageDialog = { open: true, type, title, message };
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

                    return amount < 0 ? 'Advance: ' + this.formatMoney(Math.abs(amount)) : this.formatMoney(amount);
                }
            };
        }
    </script>
@endpush
