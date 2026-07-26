@extends('layouts.app')

@section('content')
    @php
        $lines = collect($calculation['lines'] ?? []);
        $deliveries = collect($calculation['deliveries'] ?? []);
        $totals = $calculation['totals'] ?? [];
        $warnings = collect($calculation['warnings'] ?? [])->unique()->values();
        $money = fn ($value, $decimals = 2) => '₱' . number_format((float) $value, $decimals);
        $number = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
        $card = 'rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
        $input = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $primary = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondary = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]';
        $th = 'whitespace-nowrap px-4 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumber = $th . ' text-right';
        $td = 'whitespace-nowrap px-4 py-3 text-theme-sm text-gray-700 dark:text-gray-300';
        $tdNumber = $td . ' text-right tabular-nums';
        $fuelClass = fn ($fuel) => match ($fuel) {
            'Premium' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
            'Unleaded' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
            default => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        };
    @endphp

    <x-common.page-breadcrumb pageTitle="Fuel Inventory Report" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 text-theme-sm text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($missingTables)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Run the pending database migrations before using this report. Missing: {{ implode(', ', $missingTables) }}.
            </div>
        @endif

        <section class="{{ $card }} p-5 md:p-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Inventory costing</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Fuel Inventory Report</h1>
                        @if ($viewingSavedReport)
                            <span class="rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">
                                Finalized · {{ $calculation['report_code'] }}
                            </span>
                        @else
                            <span class="rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Live preview</span>
                        @endif
                    </div>
                    <p class="mt-2 max-w-3xl text-theme-sm leading-6 text-gray-500 dark:text-gray-400">
                        Previous ending stock plus logged deliveries, less verified meter dispensing. Costs are assigned chronologically using moving weighted average, so later deliveries do not change earlier gross profit.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3 print:hidden">
                    @if ($viewingSavedReport)
                        <a href="{{ route('inventory.reports.index') }}" class="{{ $secondary }}">Current preview</a>
                    @endif
                    <button type="button" onclick="window.print()" class="{{ $secondary }}">Print report</button>
                </div>
            </div>
        </section>

        @unless ($viewingSavedReport)
            <form method="GET" action="{{ route('inventory.reports.index') }}" class="{{ $card }} p-5 md:p-6 print:hidden">
                @unless ($reportPeriodAvailable)
                    <div class="mb-5 rounded-xl border border-brand-200 bg-brand-50 p-4 text-theme-sm text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
                        The latest inventory report already covers activity through {{ \Carbon\Carbon::parse($latestReport->period_end)->format('M d, Y') }}.
                        The next report period becomes available on {{ \Carbon\Carbon::parse($expectedStart)->format('M d, Y') }}.
                    </div>
                @endunless

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-end">
                    <label class="block">
                        <span class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Report start</span>
                        <input id="inventory-report-start-date" type="date" name="start_date" value="{{ old('start_date', $startDate) }}" max="{{ now()->toDateString() }}" class="mt-1.5 {{ $input }}" required {{ $reportPeriodAvailable ? '' : 'disabled' }}>
                    </label>
                    <label class="block">
                        <span class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Report end</span>
                        <input id="inventory-report-end-date" type="date" name="end_date" value="{{ old('end_date', $endDate) }}" min="{{ $startDate }}" max="{{ now()->toDateString() }}" class="mt-1.5 {{ $input }}" required {{ $reportPeriodAvailable ? '' : 'disabled' }}>
                    </label>
                    <button type="submit" class="{{ $primary }}" {{ $reportPeriodAvailable ? '' : 'disabled' }}>Refresh preview</button>
                </div>

                @if (! $latestReport)
                    <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Initial Last Report / Opening Stock</h2>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Required only for the first report. Future reports carry these values forward automatically.</p>
                        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                            @foreach ($products as $fuel => $definition)
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuelClass($fuel) }}">{{ $definition['product_name'] }}</span>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <label>
                                            <span class="text-theme-xs text-gray-500 dark:text-gray-400">Quantity (L)</span>
                                            <input type="number" name="opening[{{ $fuel }}][liters]" min="0" step="0.001" value="{{ old("opening.{$fuel}.liters", $manualOpening[$fuel]['liters'] ?? 0) }}" class="mt-1.5 {{ $input }}">
                                        </label>
                                        <label>
                                            <span class="text-theme-xs text-gray-500 dark:text-gray-400">Cost/L</span>
                                            <input type="number" name="opening[{{ $fuel }}][unit_cost]" min="0" step="0.000001" value="{{ old("opening.{$fuel}.unit_cost", $manualOpening[$fuel]['unit_cost'] ?? 0) }}" class="mt-1.5 {{ $input }}">
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="mt-4 text-theme-sm text-gray-500 dark:text-gray-400">
                        Opening stock is carried from {{ $latestReport->report_code }}. The next continuous report should begin on {{ \Carbon\Carbon::parse($expectedStart)->format('M d, Y') }}.
                    </p>
                @endif
            </form>
        @endunless

        @if ($calculation)
            @if ($warnings->isNotEmpty())
                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                    <p class="text-theme-sm font-semibold text-warning-800 dark:text-warning-300">Reconciliation notes</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-theme-sm text-warning-700 dark:text-warning-300">
                        @foreach ($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 2xl:grid-cols-2">
                <section class="{{ $card }} overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Ending Stock — Last Report</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Unsold inventory carried into {{ \Carbon\Carbon::parse($calculation['period_start'])->format('M d, Y') }}.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[620px] w-full">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Qty (L)</th><th class="{{ $thNumber }}">Avg Cost/L</th><th class="{{ $thNumber }}">Inventory Value</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($lines as $line)
                                    <tr><td class="{{ $td }}"><span class="rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuelClass($line['fuel_type']) }}">{{ $line['product_name'] }}</span></td><td class="{{ $tdNumber }}">{{ $number($line['opening_liters']) }}</td><td class="{{ $tdNumber }}">{{ $money($line['opening_unit_cost']) }}</td><td class="{{ $tdNumber }} font-semibold">{{ $money($line['opening_value']) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]"><tr><td colspan="3" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['opening_value'] ?? 0) }}</td></tr></tfoot>
                        </table>
                    </div>
                </section>

                <section class="{{ $card }} overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Logged Deliveries</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Admin delivery logs are included by delivery date.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[760px] w-full">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="{{ $th }}">Date</th><th class="{{ $th }}">Delivery</th><th class="{{ $th }}">Product</th><th class="{{ $th }}">Supplier</th><th class="{{ $thNumber }}">Qty (L)</th><th class="{{ $thNumber }}">Cost/L</th><th class="{{ $thNumber }}">Cost</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($deliveries as $delivery)
                                    <tr><td class="{{ $td }}">{{ \Carbon\Carbon::parse($delivery['delivery_date'])->format('M d, Y') }}</td><td class="{{ $td }}">{{ $delivery['delivery_code'] ?: '#' . $delivery['id'] }}</td><td class="{{ $td }}">{{ $delivery['product_name'] }}</td><td class="{{ $td }}">{{ $delivery['supplier'] }}</td><td class="{{ $tdNumber }}">{{ $number($delivery['liters']) }}</td><td class="{{ $tdNumber }}">{{ $money($delivery['unit_cost']) }}</td><td class="{{ $tdNumber }} font-semibold">{{ $money($delivery['total_cost']) }}</td></tr>
                                @empty
                                    <tr><td colspan="7" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No logged deliveries in this report period.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]"><tr><td colspan="6" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['purchase_value'] ?? 0) }}</td></tr></tfoot>
                        </table>
                    </div>
                </section>
            </div>

            <section class="{{ $card }} overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-800 dark:text-white/90">Total Available Stock Before Dispensing</h2>
                    <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">The worksheet's “Current Stock”: opening stock plus logged deliveries before liters are deducted.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[720px] w-full">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Available Qty (L)</th><th class="{{ $thNumber }}">Periodic Avg Cost/L</th><th class="{{ $thNumber }}">Available Value</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($lines as $line)
                                <tr><td class="{{ $td }}"><span class="rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuelClass($line['fuel_type']) }}">{{ $line['product_name'] }}</span></td><td class="{{ $tdNumber }}">{{ $number($line['available_liters']) }}</td><td class="{{ $tdNumber }}">{{ $money($line['periodic_average_cost']) }}</td><td class="{{ $tdNumber }} font-semibold">{{ $money($line['available_value']) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]"><tr><td colspan="3" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['available_value'] ?? 0) }}</td></tr></tfoot>
                    </table>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 2xl:grid-cols-2">
                <section class="{{ $card }} overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Price Guide</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Margins use the current ending moving-average cost.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[780px] w-full">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Cost/L</th><th class="{{ $thNumber }}">Pump Margin</th><th class="{{ $thNumber }}">Pump Price</th><th class="{{ $thNumber }}">Bulk Margin</th><th class="{{ $thNumber }}">Bulk Price</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($lines as $line)
                                    <tr><td class="{{ $td }}">{{ $line['product_name'] }}</td><td class="{{ $tdNumber }}">{{ $money($line['ending_unit_cost']) }}</td><td class="{{ $tdNumber }} {{ $line['pump_margin'] < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $money($line['pump_margin']) }}</td><td class="{{ $tdNumber }}">{{ $money($line['pump_price']) }}</td><td class="{{ $tdNumber }} {{ $line['bulk_margin'] < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $money($line['bulk_margin']) }}</td><td class="{{ $tdNumber }}">{{ $money($line['bulk_price']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="{{ $card }} overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Tank Progress</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Calculated inventory movement; compare ending liters with the physical dipstick/tank reading.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[660px] w-full">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Available (L)</th><th class="{{ $thNumber }}">Dispensed (L)</th><th class="{{ $thNumber }}">Ending Stock (L)</th><th class="{{ $thNumber }}">Ending Value</th></tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($lines as $line)
                                    <tr><td class="{{ $td }}">{{ $line['product_name'] }}</td><td class="{{ $tdNumber }}">{{ $number($line['available_liters']) }}</td><td class="{{ $tdNumber }}">{{ $number($line['dispensed_liters']) }}</td><td class="{{ $tdNumber }} font-semibold">{{ $number($line['ending_liters']) }}</td><td class="{{ $tdNumber }} font-semibold">{{ $money($line['ending_value']) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]"><tr><td colspan="4" class="{{ $tdNumber }}">Ending inventory value</td><td class="{{ $tdNumber }}">{{ $money($totals['ending_value'] ?? 0) }}</td></tr></tfoot>
                        </table>
                    </div>
                </section>
            </div>

            <section class="{{ $card }} overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:flex-row md:items-end md:justify-between">
                    <div><h2 class="font-semibold text-gray-800 dark:text-white/90">Gross Fuel Profit</h2><p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Before salaries, utilities, rent, transportation, maintenance, tax, and other operating expenses.</p></div>
                    <p class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($calculation['period_start'])->format('M d, Y') }} – {{ \Carbon\Carbon::parse($calculation['period_end'])->format('M d, Y') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[880px] w-full">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Sale Liters</th><th class="{{ $thNumber }}">Avg Selling Price</th><th class="{{ $thNumber }}">Cost of Sold Fuel/L</th><th class="{{ $thNumber }}">Gross Margin/L</th><th class="{{ $thNumber }}">Revenue</th><th class="{{ $thNumber }}">Gross Profit</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($lines as $line)
                                @php $soldCostPerLiter = $line['dispensed_liters'] > 0 ? $line['cost_of_goods_sold'] / $line['dispensed_liters'] : 0; @endphp
                                <tr><td class="{{ $td }}">{{ $line['product_name'] }}</td><td class="{{ $tdNumber }}">{{ $number($line['dispensed_liters']) }}</td><td class="{{ $tdNumber }}">{{ $money($line['average_selling_price']) }}</td><td class="{{ $tdNumber }}">{{ $money($soldCostPerLiter) }}</td><td class="{{ $tdNumber }} {{ $line['gross_margin_per_liter'] < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $money($line['gross_margin_per_liter']) }}</td><td class="{{ $tdNumber }}">{{ $money($line['sales_revenue']) }}</td><td class="{{ $tdNumber }} font-semibold {{ $line['gross_profit'] < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $money($line['gross_profit']) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]"><tr><td colspan="5" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['sales_revenue'] ?? 0) }}</td><td class="{{ $tdNumber }}">{{ $money($totals['gross_profit'] ?? 0) }}</td></tr></tfoot>
                    </table>
                </div>
            </section>

            @unless ($viewingSavedReport)
                <section class="{{ $card }} p-5 md:p-6 print:hidden">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div><h2 class="font-semibold text-gray-800 dark:text-white/90">Finalize this report</h2><p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">This saves the exact costs and profits shown above. Its ending inventory becomes the next report's opening inventory.</p></div>
                        <form method="POST" action="{{ route('inventory.reports.store') }}" onsubmit="return confirm('Finalize this inventory report and carry its ending stock into the next report?')">
                            @csrf
                            <input type="hidden" name="start_date" value="{{ $startDate }}">
                            <input type="hidden" name="end_date" value="{{ $endDate }}">
                            @unless ($latestReport)
                                @foreach ($products as $fuel => $definition)
                                    <input type="hidden" name="opening[{{ $fuel }}][liters]" value="{{ $manualOpening[$fuel]['liters'] ?? 0 }}">
                                    <input type="hidden" name="opening[{{ $fuel }}][unit_cost]" value="{{ $manualOpening[$fuel]['unit_cost'] ?? 0 }}">
                                @endforeach
                            @endunless
                            <button type="submit" class="{{ $primary }}" {{ $missingTables ? 'disabled' : '' }}>Finalize report snapshot</button>
                        </form>
                    </div>
                </section>
            @endunless
        @endif

        @if (collect($history)->isNotEmpty())
            <section class="{{ $card }} overflow-hidden print:hidden">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800"><h2 class="font-semibold text-gray-800 dark:text-white/90">Finalized Report History</h2></div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($history as $report)
                        <a href="{{ route('inventory.reports.index', ['report_id' => $report->id]) }}" class="flex flex-col gap-2 px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $report->report_code }}</p><p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Moving weighted average · Finalized</p></div>
                            <span class="text-theme-sm text-gray-600 dark:text-gray-300">{{ \Carbon\Carbon::parse($report->period_start)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($report->period_end)->format('M d, Y') }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const startDate = document.getElementById('inventory-report-start-date');
            const endDate = document.getElementById('inventory-report-end-date');

            startDate?.addEventListener('change', () => {
                endDate.min = startDate.value;

                if (endDate.value < startDate.value) {
                    endDate.value = startDate.value;
                }
            });
        });
    </script>
@endpush
