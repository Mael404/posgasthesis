@extends('layouts.app')

@section('content')
    @php
        $reportData = $reportData ?? [
            'Diesel' => ['revenue' => 0, 'liters' => 0],
            'Unleaded' => ['revenue' => 0, 'liters' => 0],
            'Premium' => ['revenue' => 0, 'liters' => 0],
        ];
        $tanks = collect($tanks ?? []);
        $salesOut = collect($salesOut ?? []);
        $financials = collect($financials ?? []);
        $activeTab = in_array(request('tab'), ['sales', 'inventory', 'financial'], true) ? request('tab') : 'sales';
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
        $totalRevenue = collect($reportData)->sum(fn ($row) => (float) ($row['revenue'] ?? 0));
        $totalLiters = collect($reportData)->sum(fn ($row) => (float) ($row['liters'] ?? 0));
        $chartLabels = $fuelNames->all();
        $chartSeries = $fuelNames->map(fn ($fuel) => (float) ($reportData[$fuel]['revenue'] ?? 0))->all();
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
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,680px)] xl:items-end">
                <div class="min-w-0">
                    <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Reports & Analytics</h1>
                    <p class="{{ $captionClass }}">
                        {{ \Carbon\Carbon::parse($start)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($end)->format('M d, Y') }}
                    </p>
                </div>

                <form method="GET" action="{{ route('admin.sales.index') }}" class="grid min-w-0 grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
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
                    <button type="submit" class="{{ $primaryButtonClass }}">Filter</button>
                    <a href="{{ route('admin.sales.index') }}" class="{{ $secondaryButtonClass }}">Reset</a>
                </form>
            </div>
        </section>

        <section class="{{ $cardClass }} p-2 md:p-2">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button type="button" @click="switchTab('sales')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'sales' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Daily Sales
                </button>
                <button type="button" @click="switchTab('inventory')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'inventory' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Inventory Movement
                </button>
                <button type="button" @click="switchTab('financial')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'financial' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Revenue Inflow
                </button>
            </div>
        </section>

        <div x-show="activeTab === 'sales'" x-cloak class="space-y-6">
            <div class="grid grid-cols-1 items-stretch gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="{{ $cardClass }} flex min-h-[154px] flex-col justify-between">
                    <p class="{{ $labelClass }}">Total Revenue</p>
                    <div>
                        <p class="{{ $valueClass }}">&#8369;{{ number_format($totalRevenue, 2) }}</p>
                        <p class="{{ $captionClass }}">{{ number_format($totalLiters, 2) }} liters sold</p>
                    </div>
                </article>

                @foreach ($reportData as $fuel => $data)
                    @php($style = $fuelStyle($fuel))
                    <article class="{{ $cardClass }} {{ $style['border'] }} flex min-h-[154px] flex-col justify-between">
                        <div class="flex h-full items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-col justify-between">
                                <p class="{{ $labelClass }}">{{ $fuel }}</p>
                                <div>
                                    <p class="{{ $valueClass }}">&#8369;{{ number_format((float) ($data['revenue'] ?? 0), 2) }}</p>
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
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Daily Revenue Breakdown</h2>
                    <p class="{{ $captionClass }}">Sales grouped by fuel type for the selected period.</p>
                </div>
                <div class="mx-auto flex min-h-[340px] w-full max-w-[520px] items-center justify-center">
                    <div id="reportSalesChart" class="min-h-[320px] w-full"></div>
                </div>
            </section>
        </div>

        <section x-show="activeTab === 'inventory'" x-cloak class="{{ $cardClass }}">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Inventory Movement</h2>
                <p class="{{ $captionClass }}">Sales outflow compared with current tank volume.</p>
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

        <div x-show="activeTab === 'financial'" x-cloak class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <section class="{{ $cardClass }} xl:col-span-8">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Revenue Inflow</h2>
                    <p class="{{ $captionClass }}">Approved cash sales from verified shift readings.</p>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto">
                        <table class="min-w-[820px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[16%]">
                                <col class="w-[18%]">
                                <col class="w-[42%]">
                                <col class="w-[24%]">
                            </colgroup>
                            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                <tr>
                                    <th class="{{ $thClass }}">Date</th>
                                    <th class="{{ $thClass }}">Category</th>
                                    <th class="{{ $thClass }}">Description</th>
                                    <th class="{{ $thNumberClass }}">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($financials as $fin)
                                    <tr>
                                        <td class="{{ $tdClass }}">{{ \Carbon\Carbon::parse($fin->date)->format('Y-m-d') }}</td>
                                        <td class="{{ $tdClass }}">
                                            @if ($fin->category === 'Cash Sales')
                                                <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Cash Sales</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-theme-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Credit Payment</span>
                                            @endif
                                        </td>
                                        <td class="{{ $tdClass }}">Shift Remittance - {{ $fin->description }}</td>
                                        <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $fin->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                            No verified revenue inflow found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <aside class="{{ $cardClass }} xl:col-span-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Financial Summary</h2>
                <div class="mt-5 space-y-4">
                    <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-4 dark:border-gray-800">
                        <span class="text-theme-sm text-gray-500 dark:text-gray-400">Total Cash Sales</span>
                        <span class="text-theme-sm font-semibold tabular-nums text-success-600 dark:text-success-500">&#8369;{{ number_format($totalCash, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-4 dark:border-gray-800">
                        <span class="text-theme-sm text-gray-500 dark:text-gray-400">Total Credit Collections</span>
                        <span class="text-theme-sm font-semibold tabular-nums text-brand-600 dark:text-brand-400">&#8369;{{ number_format($totalCredit, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Total Inflow</span>
                        <span class="text-lg font-bold tabular-nums text-gray-800 dark:text-white/90">&#8369;{{ number_format($totalInflow, 2) }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartElement = document.getElementById('reportSalesChart');
            let reportSalesChart = null;

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
                    window.dispatchEvent(new Event('resize'));
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
                                    label: 'Revenue',
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
                    setTimeout(syncReportSalesChart, 40);
                }
            });

            window.addEventListener('resize', function() {
                setTimeout(syncReportSalesChart, 40);
            });
        });
    </script>
@endpush
