@extends('layouts.app')

@section('content')
    @php
        $reportData = $reportData ?? [
            'Diesel' => ['gross_sales' => 0, 'discounts' => 0, 'net_sales' => 0, 'liters' => 0],
            'Unleaded' => ['gross_sales' => 0, 'discounts' => 0, 'net_sales' => 0, 'liters' => 0],
            'Premium' => ['gross_sales' => 0, 'discounts' => 0, 'net_sales' => 0, 'liters' => 0],
        ];
        $tanks = collect($tanks ?? []);
        $salesOut = collect($salesOut ?? []);
        $shiftReadings = collect($shiftReadings ?? []);
        $transactions = collect($transactions ?? []);
        $transactionTotals = $transactionTotals ?? ['records' => 0, 'liters' => 0, 'discounts' => 0, 'amount' => 0];
        $staffOptions = collect($staffOptions ?? []);
        $staffId = isset($staffId) ? (int) $staffId : null;
        $selectedStaff = $staffOptions->first(fn ($staff) => (int) $staff->id === $staffId);
        $readingStatus = $readingStatus ?? 'all';
        $activeTab = in_array(request('tab'), ['sales', 'inventory'], true) ? request('tab') : 'sales';
        $fuelNames = collect(array_keys($reportData))->values();
        $inventoryFuels = $tanks->keys()->merge($salesOut->keys())->unique()->values();
        $fuelStyle = function ($fuel) {
            $fuel = strtolower((string) $fuel);

            if (str_contains($fuel, 'diesel')) {
                return [
                    'border' => 'border-l-4 border-l-yellow-500',
                    'badge' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400',
                    'dot' => 'bg-yellow-500',
                    'chart' => '#eab308',
                ];
            }

            if (str_contains($fuel, 'unleaded') || str_contains($fuel, 'regular')) {
                return [
                    'border' => 'border-l-4 border-l-emerald-500',
                    'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
                    'dot' => 'bg-emerald-500',
                    'chart' => '#10b981',
                ];
            }

            if (str_contains($fuel, 'premium')) {
                return [
                    'border' => 'border-l-4 border-l-red-500',
                    'badge' => 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-400',
                    'dot' => 'bg-red-500',
                    'chart' => '#ef4444',
                ];
            }

            return [
                'border' => 'border-l-4 border-l-brand-500',
                'badge' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                'dot' => 'bg-brand-500',
                'chart' => '#465fff',
            ];
        };
        $inventoryRows = $inventoryFuels->map(function ($fuel) use ($tanks, $salesOut, $fuelStyle) {
            $tank = $tanks->get($fuel);
            $style = $fuelStyle($fuel);

            return (object) [
                'fuel' => $fuel,
                'sold_liters' => (float) ($salesOut->get($fuel)->total_liters ?? 0),
                'current_volume' => (float) ($tank->current_volume ?? 0),
                'capacity' => (float) ($tank->capacity ?? 0),
                'badge_class' => $style['badge'],
                'dot_class' => $style['dot'],
            ];
        });
        $totalGrossSales = (float) ($totalGrossSales ?? collect($reportData)->sum(fn ($row) => (float) ($row['gross_sales'] ?? 0)));
        $totalDiscounts = (float) ($totalDiscounts ?? collect($reportData)->sum(fn ($row) => (float) ($row['discounts'] ?? 0)));
        $totalNetSales = (float) ($totalNetSales ?? collect($reportData)->sum(fn ($row) => (float) ($row['net_sales'] ?? 0)));
        $totalLiters = (float) ($totalLiters ?? collect($reportData)->sum(fn ($row) => (float) ($row['liters'] ?? 0)));
        $chartLabels = $fuelNames->all();
        $chartSeries = $fuelNames->map(fn ($fuel) => (float) ($reportData[$fuel]['net_sales'] ?? 0))->all();
        $chartColors = $fuelNames->map(fn ($fuel) => $fuelStyle($fuel)['chart'])->all();
        $cardClass = 'min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $valueClass = 'mt-2 break-words text-title-sm font-bold text-gray-800 dark:text-white/90';
        $captionClass = 'mt-1 text-theme-sm text-gray-500 dark:text-gray-400';
        $dateInputClass = 'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $dateIcon = '<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-6"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path></svg></span>';
    @endphp

    <x-common.page-breadcrumb pageTitle="Transaction Logs" />

    <div
        x-data="{
            activeTab: @js($activeTab),
            switchTab(tab) {
                this.activeTab = tab;
                this.$nextTick(() => window.dispatchEvent(new CustomEvent('report-tab-changed', { detail: { tab } })));
            }
        }"
        x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('report-tab-changed', { detail: { tab: activeTab } })))"
        class="space-y-6">
        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1040px)] xl:items-end">
                <div class="min-w-0">
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Shift Reading Transactions</h1>
                    <p class="{{ $captionClass }}">
                        {{ \Carbon\Carbon::parse($start)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($end)->format('M d, Y') }}
                        &middot; {{ $readingStatus === 'pending' ? 'Pending' : ($readingStatus === 'approved' ? 'Approved' : 'Pending & Approved') }}
                        &middot; {{ $selectedStaff->name ?? 'All Staff' }}
                    </p>
                </div>

                <form method="GET" action="{{ route('admin.sales.index') }}" class="grid min-w-0 grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,190px)_minmax(0,210px)_auto_auto]">
                    <input type="hidden" name="tab" :value="activeTab">
                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>From</span>
                        <span class="relative block custom-datepicker">
                            <input type="text" name="start_date" value="{{ $start }}" placeholder="Select date" autocomplete="off" class="{{ $dateInputClass }}" data-report-datepicker>
                            {!! $dateIcon !!}
                        </span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>To</span>
                        <span class="relative block custom-datepicker">
                            <input type="text" name="end_date" value="{{ $end }}" placeholder="Select date" autocomplete="off" class="{{ $dateInputClass }}" data-report-datepicker>
                            {!! $dateIcon !!}
                        </span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>Reading Status</span>
                        <select name="reading_status" class="{{ $dateInputClass }} pr-10">
                            <option value="all" @selected($readingStatus === 'all')>Pending &amp; Approved</option>
                            <option value="pending" @selected($readingStatus === 'pending')>Pending</option>
                            <option value="approved" @selected($readingStatus === 'approved')>Approved</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>Staff Name</span>
                        <select name="staff_id" class="{{ $dateInputClass }} pr-10">
                            <option value="">All Staff</option>
                            @foreach ($staffOptions as $staff)
                                <option value="{{ $staff->id }}" @selected($staffId === (int) $staff->id)>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="{{ $primaryButtonClass }}">Filter</button>
                    <a href="{{ route('admin.sales.index') }}" class="{{ $secondaryButtonClass }}">Reset</a>
                </form>
            </div>
        </section>

        <section class="{{ $cardClass }} p-2 md:p-2">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button type="button" @click="switchTab('sales')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'sales' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Daily Sales
                </button>
                <button type="button" @click="switchTab('inventory')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'inventory' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Inventory Movement
                </button>
            </div>
        </section>

        <div x-show="activeTab === 'sales'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 items-stretch gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="{{ $cardClass }} flex min-h-[154px] flex-col justify-between">
                    <p class="{{ $labelClass }}">Total Net Sales</p>
                    <div>
                        <p class="{{ $valueClass }}">&#8369;{{ number_format($totalNetSales, 2) }}</p>
                        <p class="{{ $captionClass }}">
                            Gross &#8369;{{ number_format($totalGrossSales, 2) }} &middot; Discounts &#8369;{{ number_format($totalDiscounts, 2) }}
                        </p>
                        <p class="{{ $captionClass }}">{{ number_format($totalLiters, 2) }} liters &middot; {{ number_format($shiftReadings->count()) }} readings</p>
                    </div>
                </article>

                @foreach ($reportData as $fuel => $data)
                    @php
                        $style = $fuelStyle($fuel);
                    @endphp
                    <article class="{{ $cardClass }} {{ $style['border'] }} flex min-h-[154px] flex-col justify-between">
                        <div class="flex h-full items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-col justify-between">
                                <p class="{{ $labelClass }}">{{ $fuel }}</p>
                                <div>
                                    <p class="{{ $valueClass }}">&#8369;{{ number_format((float) ($data['net_sales'] ?? 0), 2) }}</p>
                                    <p class="{{ $captionClass }}">{{ number_format((float) ($data['liters'] ?? 0), 2) }} liters sold</p>
                                </div>
                            </div>
                            <span class="mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $style['badge'] }}">
                                <span class="h-2.5 w-2.5 rounded-full {{ $style['dot'] }}"></span>
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>

            <section class="{{ $cardClass }}">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Net Sales Breakdown</h2>
                </div>
                <div class="mx-auto flex min-h-[340px] w-full max-w-[520px] items-center justify-center">
                    <div id="reportSalesChart" class="min-h-[320px] w-full"></div>
                </div>
            </section>

            <section class="{{ $cardClass }}">
                <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Transaction Log</h2>
                        <p class="{{ $captionClass }}">Individual transactions from the selected pending and approved shifts. Voided and rejected shifts are excluded.</p>
                    </div>
                    <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">
                        {{ number_format((int) $transactionTotals['records']) }} transaction{{ (int) $transactionTotals['records'] === 1 ? '' : 's' }}
                    </span>
                </div>

                <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                        <p class="{{ $labelClass }}">Transactions</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((int) $transactionTotals['records']) }}</p>
                    </div>
                    <div class="rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3 dark:border-brand-500/20 dark:bg-brand-500/10">
                        <p class="{{ $labelClass }}">Total Liters</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-brand-700 dark:text-brand-400">{{ number_format((float) $transactionTotals['liters'], 2) }} L</p>
                    </div>
                    <div class="rounded-xl border border-warning-100 bg-warning-50/60 px-4 py-3 dark:border-warning-500/20 dark:bg-warning-500/10">
                        <p class="{{ $labelClass }}">Total Discounts</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-warning-700 dark:text-warning-400">&#8369;{{ number_format((float) $transactionTotals['discounts'], 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-success-100 bg-success-50/60 px-4 py-3 dark:border-success-500/20 dark:bg-success-500/10">
                        <p class="{{ $labelClass }}">Total Amount</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-success-700 dark:text-success-500">&#8369;{{ number_format((float) $transactionTotals['amount'], 2) }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto">
                        <table class="min-w-[1500px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[12%]">
                                <col class="w-[12%]">
                                <col class="w-[6%]">
                                <col class="w-[13%]">
                                <col class="w-[17%]">
                                <col class="w-[12%]">
                                <col class="w-[10%]">
                                <col class="w-[7%]">
                                <col class="w-[6%]">
                                <col class="w-[7%]">
                            </colgroup>
                            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                <tr>
                                    <th class="{{ $thClass }}">Date / Time</th>
                                    <th class="{{ $thClass }}">Staff</th>
                                    <th class="{{ $thClass }}">Shift</th>
                                    <th class="{{ $thClass }}">Reference / Receipt</th>
                                    <th class="{{ $thClass }}">Customer</th>
                                    <th class="{{ $thClass }}">Pump / Product</th>
                                    <th class="{{ $thClass }}">Payment</th>
                                    <th class="{{ $thNumberClass }}">Liters</th>
                                    <th class="{{ $thNumberClass }}">Discounts</th>
                                    <th class="{{ $thNumberClass }}">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($transactions as $transaction)
                                    @php
                                        $transactionDate = !empty($transaction->created_at) ? \Carbon\Carbon::parse($transaction->created_at) : null;
                                        $paymentType = strtolower(trim((string) ($transaction->payment_type ?? '')));
                                        $isCollection = $paymentType === 'collection';
                                        $productStyle = $fuelStyle($transaction->product_label ?? '');
                                        $paymentClass = match ($paymentType) {
                                            'credit' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                                            'collection' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="{{ $tdClass }}">
                                            @if ($transactionDate)
                                                <div class="font-medium text-gray-800 dark:text-white/90">{{ $transactionDate->format('M d, Y') }}</div>
                                                <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $transactionDate->format('h:i A') }}</div>
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="{{ $tdPrimaryClass }}">{{ $transaction->staff_name }}</td>
                                        <td class="{{ $tdClass }}">{{ $transaction->shift_reading_id ? '#' . $transaction->shift_reading_id : 'N/A' }}</td>
                                        <td class="{{ $tdClass }}">
                                            <div class="font-medium text-gray-800 dark:text-white/90">{{ $transaction->reference_no }}</div>
                                            @if ($transaction->or_number !== '')
                                                <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">OR: {{ $transaction->or_number }}</div>
                                            @else
                                                <div class="mt-1 text-theme-xs text-error-600 dark:text-error-400">No receipt</div>
                                            @endif
                                        </td>
                                        <td class="{{ $tdClass }}">{{ $transaction->customer_display }}</td>
                                        <td class="{{ $tdClass }}">
                                            <div class="font-medium text-gray-800 dark:text-white/90">{{ $transaction->pump_label }}</div>
                                            <span class="mt-1 inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $productStyle['badge'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $productStyle['dot'] }}"></span>
                                                {{ $transaction->product_label }}
                                            </span>
                                        </td>
                                        <td class="{{ $tdClass }}">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $paymentClass }}">{{ $transaction->payment_label }}</span>
                                        </td>
                                        <td class="{{ $tdNumberClass }}">{{ $isCollection ? '-' : number_format((float) $transaction->liters, 2) . ' L' }}</td>
                                        <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $transaction->discounts, 2) }}</td>
                                        <td class="{{ $tdNumberClass }} font-semibold text-success-600 dark:text-success-500">&#8369;{{ number_format((float) $transaction->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                            No individual transactions found for the selected date, staff, and shift status filters.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if ($transactions->isNotEmpty())
                                <tfoot class="border-t border-gray-200 bg-gray-50 font-semibold dark:border-gray-800 dark:bg-white/[0.02]">
                                    <tr>
                                        <td colspan="7" class="{{ $tdPrimaryClass }} text-right">Totals ({{ number_format((int) $transactionTotals['records']) }} transactions)</td>
                                        <td class="{{ $tdNumberClass }}">{{ number_format((float) $transactionTotals['liters'], 2) }} L</td>
                                        <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $transactionTotals['discounts'], 2) }}</td>
                                        <td class="{{ $tdNumberClass }} text-success-600 dark:text-success-500">&#8369;{{ number_format((float) $transactionTotals['amount'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <section x-show="activeTab === 'inventory'" x-cloak class="{{ $cardClass }}">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Inventory Movement</h2>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[720px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[34%]">
                            <col class="w-[22%]">
                            <col class="w-[22%]">
                            <col class="w-[22%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Fuel Type</th>
                                <th class="{{ $thNumberClass }}">Sales (-)</th>
                                <th class="{{ $thNumberClass }}">Current Volume</th>
                                <th class="{{ $thNumberClass }}">Capacity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($inventoryRows as $row)
                                <tr>
                                    <td class="{{ $tdPrimaryClass }}">
                                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $row->badge_class }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $row->dot_class }}"></span>
                                            {{ $row->fuel }}
                                        </span>
                                    </td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format($row->sold_liters, 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format($row->current_volume, 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">{{ $row->capacity > 0 ? number_format($row->capacity, 2) . ' L' : 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                        No inventory movement found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartElement = document.getElementById('reportSalesChart');
            let reportSalesChart = null;
            let reportChartResizeTimer = null;

            function initReportDatePickers() {
                if (!window.flatpickr) {
                    return;
                }

                document.querySelectorAll('[data-report-datepicker]').forEach(function(input) {
                    if (input._reportDatePicker) {
                        return;
                    }

                    input._reportDatePicker = window.flatpickr(input, {
                        static: true,
                        monthSelectorType: 'static',
                        dateFormat: 'Y-m-d',
                        defaultDate: input.value || null,
                        allowInput: false,
                        disableMobile: true
                    });
                });
            }

            function syncReportSalesChart() {
                if (!reportSalesChart || !chartElement || chartElement.offsetParent === null) {
                    return;
                }

                requestAnimationFrame(function() {
                    reportSalesChart.updateOptions({
                        chart: {
                            width: '100%'
                        }
                    }, false, true);
                });
            }

            initReportDatePickers();

            if (!chartElement || !window.ApexCharts) {
                return;
            }

            const labels = @json($chartLabels);
            const series = @json($chartSeries);
            const colors = @json($chartColors);
            const hasData = series.some(function(value) {
                return Number(value) > 0;
            });
            const formatter = new Intl.NumberFormat('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            reportSalesChart = new window.ApexCharts(chartElement, {
                chart: {
                    type: 'donut',
                    height: 320,
                    width: '100%',
                    toolbar: { show: false },
                    fontFamily: 'Roboto, Segoe UI, Helvetica Neue, Arial, sans-serif',
                    parentHeightOffset: 0,
                    redrawOnParentResize: true,
                    redrawOnWindowResize: true
                },
                labels: hasData ? labels : ['No sales'],
                series: hasData ? series.map(Number) : [1],
                colors: hasData ? colors : ['#e5e7eb'],
                stroke: {
                    colors: ['transparent']
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        colors: '#6b7280'
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function(value) {
                        return hasData ? value.toFixed(1) + '%' : '0%';
                    }
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Net Sales',
                                    formatter: function() {
                                        const total = series.reduce(function(sum, value) {
                                            return sum + (Number(value) || 0);
                                        }, 0);

                                        return '\u20b1' + formatter.format(total);
                                    }
                                }
                            }
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return '\u20b1' + formatter.format(hasData ? value : 0);
                        }
                    }
                }
            });

            reportSalesChart.render().then(syncReportSalesChart);

            window.addEventListener('report-tab-changed', function(event) {
                if (event.detail && event.detail.tab === 'sales') {
                    window.clearTimeout(reportChartResizeTimer);
                    reportChartResizeTimer = window.setTimeout(syncReportSalesChart, 40);
                }
            });

            window.addEventListener('resize', function() {
                window.clearTimeout(reportChartResizeTimer);
                reportChartResizeTimer = window.setTimeout(syncReportSalesChart, 80);
            });
        });
    </script>
@endpush
