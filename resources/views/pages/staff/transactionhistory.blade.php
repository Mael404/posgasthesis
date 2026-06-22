@extends('layouts.app')

@section('content')
    @php
        $transactions = $transactions ?? collect();
        $transactionItems = $transactions instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($transactions->items())
            : collect($transactions);
        $summary = array_merge([
            'records' => 0,
            'amount' => 0,
            'liters' => 0,
            'no_receipt' => 0,
        ], $summary ?? []);
        $filters = array_merge([
            'date_label' => 'Today',
            'start' => now()->startOfDay(),
            'end' => now()->endOfDay(),
            'payment_label' => 'All Payments',
            'receipt_label' => 'All Receipts',
            'payment_type' => request('payment_type', ''),
            'receipt_status' => request('receipt_status', ''),
        ], $filters ?? []);

        $activeDateFilter = request()->filled('date_filter')
            ? request('date_filter')
            : ((request()->filled('start_date') || request()->filled('end_date')) ? '' : 'today');

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $iconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';

        $cleanText = function ($value, $fallback = 'N/A') {
            $value = trim((string) $value);

            return $value !== '' && strtoupper($value) !== 'N/A' ? $value : $fallback;
        };

        $dateLabel = function ($value, $format = 'M d, Y h:i A') {
            if (empty($value)) {
                return 'N/A';
            }

            try {
                return \Carbon\Carbon::parse($value)->format($format);
            } catch (\Throwable) {
                return (string) $value;
            }
        };

        $fuelStyle = function ($fuel) {
            $fuel = strtolower((string) $fuel);

            if (str_contains($fuel, 'diesel')) {
                return ['badge' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400', 'dot' => 'bg-warning-500'];
            }

            if (str_contains($fuel, 'unleaded') || str_contains($fuel, 'regular')) {
                return ['badge' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', 'dot' => 'bg-success-500'];
            }

            if (str_contains($fuel, 'premium')) {
                return ['badge' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400', 'dot' => 'bg-error-500'];
            }

            return ['badge' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400', 'dot' => 'bg-brand-500'];
        };

        $paymentStyle = function ($type) {
            $type = strtolower(trim((string) $type));

            if ($type === 'credit') {
                return ['label' => 'Credit Sale', 'class' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'];
            }

            if ($type === 'collection') {
                return ['label' => 'Collection', 'class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'];
            }

            return ['label' => 'Cash / Walk-In', 'class' => 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300'];
        };
    @endphp

    <x-common.page-breadcrumb pageTitle="Transaction History" />

    <div
        x-data="staffTransactionHistoryModule({
            printUrl: @js(route('staff.sales.print')),
            totalRecords: @js((int) $summary['records']),
            activeDateFilter: @js($activeDateFilter),
            sessionMessage: @js(session('success') ?: session('status')),
            sessionError: @js(session('error'))
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', modal !== null || messageDialog.open)"
        x-on:keydown.escape.window="closeTopLayer()"
        class="space-y-6">
        <style>[x-cloak] { display: none !important; }</style>

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
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
        </div>

        @if ($missingSalesTable ?? false)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                The sales table is not available yet.
            </div>
        @endif

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Staff Module</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Transaction History</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">
                        {{ $filters['date_label'] }} records from {{ $filters['start']->format('M d, Y') }} to {{ $filters['end']->format('M d, Y') }}.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:min-w-[600px]">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Records</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((int) $summary['records']) }}</p>
                    </div>
                    <div class="rounded-xl border border-success-100 bg-success-50/60 px-4 py-3 dark:border-success-500/20 dark:bg-success-500/10">
                        <p class="{{ $labelClass }}">Amount</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-success-700 dark:text-success-500">&#8369;{{ number_format((float) $summary['amount'], 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3 dark:border-brand-500/20 dark:bg-brand-500/10">
                        <p class="{{ $labelClass }}">Liters</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-brand-700 dark:text-brand-400">{{ number_format((float) $summary['liters'], 2) }} L</p>
                    </div>
                    <div class="rounded-xl border border-warning-100 bg-warning-50/60 px-4 py-3 dark:border-warning-500/20 dark:bg-warning-500/10">
                        <p class="{{ $labelClass }}">No Receipt</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-warning-700 dark:text-warning-400">{{ number_format((int) $summary['no_receipt']) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <form id="transactionFilterForm" method="GET" action="{{ route('staff.sales.history') }}" class="space-y-5">
                <input type="hidden" name="date_filter" x-model="dateFilter">

                <div class="flex flex-col gap-3 border-b border-gray-200 pb-5 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Sales Log Filters</h2>
                        <p class="mt-1 {{ $captionClass }}">{{ $filters['payment_label'] }} / {{ $filters['receipt_label'] }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap">
                        <button type="submit" x-on:click="dateFilter = 'today'" class="h-10 rounded-lg px-3 text-theme-sm font-medium transition"
                            :class="dateFilter === 'today' ? 'bg-brand-500 text-white shadow-theme-xs' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]'">
                            Today
                        </button>
                        <button type="submit" x-on:click="dateFilter = 'weekly'" class="h-10 rounded-lg px-3 text-theme-sm font-medium transition"
                            :class="dateFilter === 'weekly' ? 'bg-brand-500 text-white shadow-theme-xs' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]'">
                            This Week
                        </button>
                        <button type="submit" x-on:click="dateFilter = 'monthly'" class="h-10 rounded-lg px-3 text-theme-sm font-medium transition"
                            :class="dateFilter === 'monthly' ? 'bg-brand-500 text-white shadow-theme-xs' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]'">
                            This Month
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 items-end gap-3 md:grid-cols-2 xl:grid-cols-[minmax(150px,180px)_minmax(150px,180px)_minmax(150px,180px)_minmax(150px,180px)_auto_auto_auto]">
                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>Payment</span>
                        <select name="payment_type" class="{{ $selectClass }}">
                            <option value="">All</option>
                            <option value="cash" {{ request('payment_type') === 'cash' ? 'selected' : '' }}>Cash / Walk-In</option>
                            <option value="credit" {{ request('payment_type') === 'credit' ? 'selected' : '' }}>Credit Sale</option>
                            <option value="collection" {{ request('payment_type') === 'collection' ? 'selected' : '' }}>Collection</option>
                        </select>
                    </label>

                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>Receipt Status</span>
                        <select name="receipt_status" class="{{ $selectClass }}">
                            <option value="">All</option>
                            <option value="with_receipt" {{ request('receipt_status') === 'with_receipt' ? 'selected' : '' }}>With Receipt</option>
                            <option value="no_receipt" {{ request('receipt_status') === 'no_receipt' ? 'selected' : '' }}>No Receipt</option>
                        </select>
                    </label>

                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>From</span>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" x-on:input="dateFilter = ''" class="{{ $inputClass }}">
                    </label>

                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>To</span>
                        <input type="date" name="end_date" value="{{ request('end_date') }}" x-on:input="dateFilter = ''" class="{{ $inputClass }}">
                    </label>

                    <button type="submit" class="{{ $primaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        Filter
                    </button>
                    <a href="{{ route('staff.sales.history') }}" class="{{ $secondaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4.75 12a7.25 7.25 0 1 0 2.13-5.13M4.75 5.5v4h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Reset
                    </a>
                    <button type="button" x-on:click="openPrintModal()" class="{{ $secondaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 8V4.75C7 4.336 7.336 4 7.75 4h8.5c.414 0 .75.336.75.75V8M7 17.5H5.75A2.75 2.75 0 0 1 3 14.75v-3A2.75 2.75 0 0 1 5.75 9h12.5A2.75 2.75 0 0 1 21 11.75v3a2.75 2.75 0 0 1-2.75 2.75H17M7.75 14h8.5c.414 0 .75.336.75.75v4.5a.75.75 0 0 1-.75.75h-8.5a.75.75 0 0 1-.75-.75v-4.5c0-.414.336-.75.75-.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Print
                    </button>
                </div>
            </form>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Sales Log</h2>
                    <p class="mt-1 {{ $captionClass }}">{{ number_format((int) $summary['records']) }} transaction{{ (int) $summary['records'] === 1 ? '' : 's' }} found.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">
                    {{ $filters['date_label'] }}
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1080px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[14%]">
                            <col class="w-[12%]">
                            <col class="w-[24%]">
                            <col class="w-[13%]">
                            <col class="w-[10%]">
                            <col class="w-[12%]">
                            <col class="w-[10%]">
                            <col class="w-[5%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Date / Time</th>
                                <th class="{{ $thClass }}">Receipt</th>
                                <th class="{{ $thClass }}">Customer / Company</th>
                                <th class="{{ $thClass }}">Pump / Product</th>
                                <th class="{{ $thNumberClass }}">Liters</th>
                                <th class="{{ $thNumberClass }}">Amount</th>
                                <th class="{{ $thClass }}">Payment</th>
                                <th class="{{ $thClass }}">View</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($transactionItems as $transaction)
                                @php
                                    $businessName = $cleanText($transaction->customer_business_name ?? '', '');
                                    $customerName = $cleanText($transaction->customer_display_name ?? '', '');
                                    $saleCustomerName = $cleanText($transaction->customer_name ?? '', '');
                                    $customerName = $customerName !== '' ? $customerName : ($saleCustomerName !== '' ? $saleCustomerName : 'Walk-In Customer');
                                    $businessName = $businessName !== '' ? $businessName : 'No company';
                                    $payment = $paymentStyle($transaction->payment_type ?? '');
                                    $paymentType = strtolower(trim((string) ($transaction->payment_type ?? '')));
                                    $isCollection = $paymentType === 'collection';
                                    $fuelLabel = $isCollection ? 'Account Payment' : $cleanText($transaction->fuel_type ?? '', 'N/A');
                                    $fuel = $fuelStyle($fuelLabel);
                                    $receiptNumber = trim((string) ($transaction->or_number ?? ''));
                                    $referenceNumber = trim((string) ($transaction->reference_no ?? ''));
                                    $pumpLabel = $isCollection || empty($transaction->pump_id) ? 'N/A' : 'Pump ' . $transaction->pump_id;
                                    $litersLabel = $isCollection ? '-' : number_format((float) ($transaction->liters ?? 0), 2) . ' L';
                                    $priceLabel = $isCollection ? 'N/A' : 'PHP ' . number_format((float) ($transaction->price_applied ?? 0), 2);
                                    $discountTotal = (float) ($transaction->discount ?? 0) + (float) ($transaction->manual_discount ?? 0);
                                    $detailPayload = [
                                        'date' => $dateLabel($transaction->created_at ?? null),
                                        'receipt' => $receiptNumber !== '' ? $receiptNumber : 'No Receipt',
                                        'reference' => $referenceNumber !== '' ? $referenceNumber : 'N/A',
                                        'customer' => $customerName,
                                        'company' => $businessName,
                                        'plate' => $cleanText($transaction->plate_no ?? '', 'N/A'),
                                        'pump' => $pumpLabel,
                                        'product' => $fuelLabel,
                                        'liters' => $litersLabel,
                                        'price' => $priceLabel,
                                        'amount' => 'PHP ' . number_format((float) ($transaction->amount ?? 0), 2),
                                        'payment' => $payment['label'],
                                        'discount' => 'PHP ' . number_format($discountTotal, 2),
                                        'surcharge' => 'PHP ' . number_format((float) ($transaction->surcharge ?? 0), 2),
                                    ];
                                @endphp
                                <tr>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $dateLabel($transaction->created_at ?? null, 'M d, Y') }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $dateLabel($transaction->created_at ?? null, 'h:i A') }}</div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        @if ($receiptNumber !== '')
                                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $receiptNumber }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-error-50 px-2.5 py-1 text-theme-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">No Receipt</span>
                                        @endif
                                        @if ($referenceNumber !== '')
                                            <div class="mt-1 truncate text-theme-xs text-gray-500 dark:text-gray-400" title="{{ $referenceNumber }}">{{ $referenceNumber }}</div>
                                        @endif
                                    </td>
                                    <td class="{{ $tdPrimaryClass }}">
                                        <div class="truncate" title="{{ $customerName }}">{{ $customerName }}</div>
                                        <div class="mt-1 truncate text-theme-xs font-normal text-gray-500 dark:text-gray-400" title="{{ $businessName }}">{{ $businessName }}</div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $pumpLabel }}</div>
                                        <span class="mt-1 inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuel['badge'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $fuel['dot'] }}"></span>
                                            {{ $fuelLabel }}
                                        </span>
                                    </td>
                                    <td class="{{ $tdNumberClass }}">{{ $litersLabel }}</td>
                                    <td class="{{ $tdNumberClass }}">
                                        <div class="font-semibold text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) ($transaction->amount ?? 0), 2) }}</div>
                                        @if (! $isCollection)
                                            <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">&#8369;{{ number_format((float) ($transaction->price_applied ?? 0), 2) }}/L</div>
                                        @endif
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $payment['class'] }}">{{ $payment['label'] }}</span>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <button type="button" x-on:click='openDetails(@js($detailPayload))' class="{{ $iconButtonClass }}" title="View details" aria-label="View transaction details">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M3.75 12s2.75-5.25 8.25-5.25S20.25 12 20.25 12 17.5 17.25 12 17.25 3.75 12 3.75 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M12 14.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" stroke="currentColor" stroke-width="1.7" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                        No transactions found matching your filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($transactions instanceof \Illuminate\Contracts\Pagination\Paginator && $transactions->hasPages())
                <div class="mt-5">
                    {{ $transactions->links() }}
                </div>
            @endif
        </section>

        <div x-show="modal === 'details'" x-cloak class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="modal === 'details'" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="modal === 'details'"
                x-transition
                x-on:click.outside="closeModal()"
                class="relative z-10 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Transaction Details</h3>
                        <p class="mt-1 {{ $captionClass }}" x-text="field('date')"></p>
                    </div>
                    <button type="button" x-on:click="closeModal()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]" aria-label="Close modal">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Customer</p>
                            <p class="mt-1 break-words text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="field('customer')"></p>
                            <p class="mt-1 break-words text-theme-xs text-gray-500 dark:text-gray-400" x-text="field('company')"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Receipt</p>
                            <p class="mt-1 break-words text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="field('receipt')"></p>
                            <p class="mt-1 break-words text-theme-xs text-gray-500 dark:text-gray-400" x-text="'Reference: ' + field('reference')"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Pump / Product</p>
                            <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="field('pump')"></p>
                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-text="field('product')"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Payment</p>
                            <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="field('payment')"></p>
                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-text="'Plate: ' + field('plate')"></p>
                        </div>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                        <div class="grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-800 sm:grid-cols-5">
                            <div class="p-4">
                                <p class="{{ $labelClass }}">Liters</p>
                                <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="field('liters')"></p>
                            </div>
                            <div class="p-4">
                                <p class="{{ $labelClass }}">Price</p>
                                <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="field('price')"></p>
                            </div>
                            <div class="p-4">
                                <p class="{{ $labelClass }}">Discount</p>
                                <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="field('discount')"></p>
                            </div>
                            <div class="p-4">
                                <p class="{{ $labelClass }}">Excess</p>
                                <p class="mt-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="field('surcharge')"></p>
                            </div>
                            <div class="col-span-2 border-t border-gray-200 p-4 dark:border-gray-800 sm:col-span-1 sm:border-t-0">
                                <p class="{{ $labelClass }}">Amount</p>
                                <p class="mt-1 text-theme-sm font-semibold text-success-700 dark:text-success-500" x-text="field('amount')"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}">Close</button>
                </div>
            </div>
        </div>

        <div x-show="modal === 'print'" x-cloak class="fixed inset-0 z-[100010] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="modal === 'print'" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="modal === 'print'"
                x-transition
                x-on:click.outside="closeModal()"
                class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Print Transaction History</h3>
                    <p class="mt-1 {{ $captionClass }}">Current filters will be used for the report.</p>
                </div>
                <div class="space-y-3 p-5">
                    <div class="rounded-xl border border-gray-200 p-4 text-theme-sm dark:border-gray-800">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Period</span>
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $filters['date_label'] }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Payment</span>
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $filters['payment_label'] }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Receipt</span>
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $filters['receipt_label'] }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Records</span>
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ number_format((int) $summary['records']) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="closeModal()" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button type="button" x-on:click="confirmPrint()" class="{{ $primaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 8V4.75C7 4.336 7.336 4 7.75 4h8.5c.414 0 .75.336.75.75V8M7 17.5H5.75A2.75 2.75 0 0 1 3 14.75v-3A2.75 2.75 0 0 1 5.75 9h12.5A2.75 2.75 0 0 1 21 11.75v3a2.75 2.75 0 0 1-2.75 2.75H17M7.75 14h8.5c.414 0 .75.336.75.75v4.5a.75.75 0 0 1-.75.75h-8.5a.75.75 0 0 1-.75-.75v-4.5c0-.414.336-.75.75-.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Print
                    </button>
                </div>
            </div>
        </div>

        <div x-show="messageDialog.open" x-cloak class="fixed inset-0 z-[100020] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="messageDialog.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="messageDialog.open"
                x-transition
                x-on:click.outside="closeMessage()"
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="messageDialog.title"></h3>
                    <p class="mt-1 {{ $captionClass }}" x-text="messageDialog.message"></p>
                </div>
                <div class="flex justify-end px-5 py-4">
                    <button type="button" x-on:click="closeMessage()" class="{{ $primaryButtonClass }}">OK</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function staffTransactionHistoryModule(config) {
            return {
                modal: null,
                dateFilter: config.activeDateFilter || '',
                selectedTransaction: {},
                toast: { open: false, message: '', timer: null },
                messageDialog: { open: false, title: '', message: '' },
                init() {
                    if (config.sessionMessage) {
                        this.showToast(config.sessionMessage);
                    }

                    if (config.sessionError) {
                        this.showMessage('Action Needed', config.sessionError);
                    }
                },
                openDetails(transaction) {
                    this.selectedTransaction = transaction || {};
                    this.modal = 'details';
                },
                openPrintModal() {
                    if (!config.totalRecords || Number(config.totalRecords) < 1) {
                        this.showMessage('No Transactions Found', 'There are no transactions to print for the current filters.');
                        return;
                    }

                    this.modal = 'print';
                },
                confirmPrint() {
                    const form = document.getElementById('transactionFilterForm');
                    const params = form ? new URLSearchParams(new FormData(form)).toString() : '';
                    const printWindow = window.open(config.printUrl + (params ? '?' + params : ''), '_blank');

                    this.closeModal();

                    if (printWindow) {
                        this.showToast('Print report opened.');
                        return;
                    }

                    this.showMessage('Print Window Blocked', 'Please allow pop-ups for this site and try printing again.');
                },
                field(key, fallback = 'N/A') {
                    const value = this.selectedTransaction ? this.selectedTransaction[key] : '';

                    return value === undefined || value === null || value === '' ? fallback : value;
                },
                closeTopLayer() {
                    if (this.messageDialog.open) {
                        this.closeMessage();
                        return;
                    }

                    this.closeModal();
                },
                closeModal() {
                    this.modal = null;
                },
                showMessage(title, message) {
                    this.messageDialog = {
                        open: true,
                        title: title || 'Notice',
                        message: message || 'Please review the current action.',
                    };
                },
                closeMessage() {
                    this.messageDialog = { open: false, title: '', message: '' };
                },
                showToast(message) {
                    this.closeToast();
                    this.toast.message = message || 'Action completed.';
                    this.toast.open = true;
                    this.toast.timer = window.setTimeout(() => this.closeToast(), 3000);
                },
                closeToast() {
                    if (this.toast.timer) {
                        window.clearTimeout(this.toast.timer);
                    }

                    this.toast = { open: false, message: '', timer: null };
                }
            };
        }
    </script>
@endpush
