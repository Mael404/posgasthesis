@extends('layouts.app')

@section('content')
    @php
        $lines = collect($calculation['lines'] ?? []);
        $deliveries = collect($calculation['deliveries'] ?? []);
        $totals = $calculation['totals'] ?? [];
        $warnings = collect($calculation['warnings'] ?? [])->unique()->values();
        $trackingPeriod = $calculation['tracking_period'] ?? null;
        $setupIssues = array_merge($missingTables ?? [], $missingColumns ?? []);
        $money = fn ($value, $decimals = 2) => 'PHP ' . number_format((float) $value, $decimals);
        $number = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
        $panel = 'rounded-lg border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
        $input = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
        $primary = 'inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondary = 'inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]';
        $th = 'whitespace-nowrap px-4 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumber = $th . ' text-right';
        $td = 'whitespace-nowrap px-4 py-3 text-theme-sm text-gray-700 dark:text-gray-300';
        $tdNumber = $td . ' text-right tabular-nums';
        $fuelClass = fn ($fuel) => match ($fuel) {
            'Premium' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
            'Unleaded' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
            default => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        };
        $periodStatus = strtolower((string) ($trackingPeriod['status'] ?? $selectedPeriod->status ?? ''));
        $initialOpeningTotal = collect($manualOpening ?? [])->sum(
            fn ($line) => (float) ($line['liters'] ?? 0) * (float) ($line['unit_cost'] ?? 0)
        );
    @endphp

    <x-common.page-breadcrumb pageTitle="Liters Movement & Inventory" />

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-theme-sm text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 text-theme-sm text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($setupIssues)
            <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Run the pending database migrations before using this report. Missing: {{ implode(', ', $setupIssues) }}.
            </div>
        @endif

        <section class="{{ $panel }} px-5 py-5 md:px-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Fuel Inventory Report</h1>
                        @if ($viewingSavedReport)
                            <span class="rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">
                                Finalized - {{ $calculation['report_code'] }}
                            </span>
                        @elseif ($selectedPeriod)
                            <span class="rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $periodStatus === 'active' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400' : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500' }}">
                                {{ $periodStatus === 'active' ? 'Live period' : 'Generated period' }}
                            </span>
                        @endif
                    </div>
                    <p class="mt-2 max-w-3xl text-theme-sm leading-6 text-gray-500 dark:text-gray-400">
                        Previous ending stock + received deliveries = current stock / limit. Current stock - included Liters Movement dispensing = new ending stock.
                    </p>
                    @if ($trackingPeriod)
                        <p class="mt-2 text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $trackingPeriod['period_code'] ?: 'Liters Movement #' . $trackingPeriod['id'] }}
                            <span class="font-normal text-gray-500 dark:text-gray-400">
                                - {{ \Carbon\Carbon::parse($trackingPeriod['started_at'])->format('M d, Y h:i A') }}
                                to {{ $trackingPeriod['ended_at'] ? \Carbon\Carbon::parse($trackingPeriod['ended_at'])->format('M d, Y h:i A') : 'Now' }}
                            </span>
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-3 print:hidden">
                    <a href="{{ route('admin.liters-movement.index') }}" class="{{ $secondary }}">Liters Movement</a>
                    @if ($viewingSavedReport)
                        <a href="{{ route('inventory.reports.index') }}" class="{{ $secondary }}">Current preview</a>
                    @endif
                    <button type="button" onclick="window.print()" class="{{ $secondary }}">Print report</button>
                </div>
            </div>
        </section>

        @unless ($viewingSavedReport)
            <form method="GET" action="{{ route('inventory.reports.index') }}" class="{{ $panel }} overflow-hidden print:hidden">
                <div class="grid grid-cols-1 gap-4 px-5 py-5 md:px-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                    <label class="block">
                        <span class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Liters Movement period</span>
                        <select name="period_id" class="mt-1.5 {{ $input }}" required>
                            @forelse ($periods as $period)
                                @php
                                    $status = strtolower(trim((string) $period->status));
                                    $periodEnd = $period->ended_at ? \Carbon\Carbon::parse($period->ended_at)->format('M d, Y h:i A') : 'Now';
                                @endphp
                                <option value="{{ $period->id }}" @selected((int) old('period_id', $selectedPeriod->id ?? 0) === (int) $period->id)>
                                    {{ $period->period_code }} - {{ ucfirst($status) }} - {{ \Carbon\Carbon::parse($period->started_at)->format('M d, Y h:i A') }} to {{ $periodEnd }}
                                </option>
                            @empty
                                <option value="">No Liters Movement periods available</option>
                            @endforelse
                        </select>
                    </label>
                    <button type="submit" class="{{ $primary }}" {{ collect($periods)->isEmpty() ? 'disabled' : '' }}>Refresh preview</button>
                </div>
            </form>

            @if (! $latestReport && $selectedPeriod)
                <form method="POST" action="{{ route('inventory.reports.opening') }}" class="{{ $panel }} overflow-hidden print:hidden">
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $selectedPeriod->id }}">
                    <div>
                        <div class="px-5 py-4 md:px-6">
                            <h2 class="font-semibold text-gray-800 dark:text-white/90">Initial Ending Stock - Last Report</h2>
                            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                                On the first report, quantity comes from the tank volume captured when this Liters Movement period started. Enter only the starting price per liter; cost is Quantity &times; Price.
                            </p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[680px]">
                                <thead class="bg-gray-50 dark:bg-white/[0.02]">
                                    <tr>
                                        <th class="{{ $th }}">Product</th>
                                        <th class="{{ $thNumber }}">Quantity (L)</th>
                                        <th class="{{ $thNumber }}">Price / L</th>
                                        <th class="{{ $thNumber }}">Opening Cost</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($products as $fuel => $definition)
                                        @php
                                            $openingLiters = old("opening.{$fuel}.liters", $manualOpening[$fuel]['liters'] ?? 0);
                                            $openingCost = old("opening.{$fuel}.unit_cost", $manualOpening[$fuel]['unit_cost'] ?? 0);
                                        @endphp
                                        <tr>
                                            <td class="{{ $td }}">
                                                <span class="rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuelClass($fuel) }}">{{ $definition['product_name'] }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" min="0" step="0.001" value="{{ $openingLiters }}" class="{{ $input }} cursor-not-allowed bg-gray-50 text-right tabular-nums dark:bg-gray-800" readonly aria-label="{{ $definition['product_name'] }} opening quantity from tank">
                                            </td>
                                            <td class="px-4 py-3">
                                                <input type="number" name="opening[{{ $fuel }}][unit_cost]" min="0" step="0.000001" value="{{ $openingCost }}" class="{{ $input }} text-right tabular-nums" required>
                                            </td>
                                            <td class="{{ $tdNumber }} font-semibold">{{ $money((float) $openingLiters * (float) $openingCost) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]">
                                    <tr><td colspan="3" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($initialOpeningTotal) }}</td></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 dark:border-gray-800 md:flex-row md:items-center md:justify-between md:px-6">
                            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Save this once before recording the first shifts. Later reports carry their opening quantity and cost automatically.</p>
                            <button type="submit" class="{{ $primary }}" {{ $setupIssues ? 'disabled' : '' }}>Save Initial Stock</button>
                        </div>
                    </div>
                </form>
            @elseif ($latestReport)
                <div class="{{ $panel }} px-5 py-4 text-theme-sm text-gray-500 dark:text-gray-400 md:px-6">
                    Opening stock is carried automatically from {{ $latestReport->report_code }}.
                </div>
            @endif
        @endunless

        @if ($calculation)
            @if ($warnings->isNotEmpty())
                <div class="rounded-lg border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                    <p class="text-theme-sm font-semibold text-warning-800 dark:text-warning-300">Reconciliation checks</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-theme-sm text-warning-700 dark:text-warning-300">
                        @foreach ($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="{{ $panel }} overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-800 dark:text-white/90">Liters Movement Sales Summary</h2>
                    <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Matches the workbook Sales summary. Average price per liter = net sales &divide; dispensed liters.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px]">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Liters</th><th class="{{ $thNumber }}">Current Stock / Limit</th><th class="{{ $thNumber }}">Net Sales</th><th class="{{ $thNumber }}">Avg Price / L</th><th class="{{ $thNumber }}">Ending Stock</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($lines as $line)
                                <tr>
                                    <td class="{{ $td }}">{{ $line['product_name'] }}</td>
                                    <td class="{{ $tdNumber }}">{{ $number($line['dispensed_liters']) }}</td>
                                    <td class="{{ $tdNumber }}">{{ $number($line['tank_limit_liters']) }}</td>
                                    <td class="{{ $tdNumber }}">{{ $money($line['sales_revenue']) }}</td>
                                    <td class="{{ $tdNumber }}">{{ $money($line['average_selling_price']) }}</td>
                                    <td class="{{ $tdNumber }} font-semibold">{{ $number($line['ending_liters']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]">
                            <tr><td class="{{ $td }}">Total</td><td class="{{ $tdNumber }}">{{ $number($totals['dispensed_liters'] ?? 0) }}</td><td class="{{ $tdNumber }}">{{ $number($totals['tank_limit_liters'] ?? 0) }}</td><td class="{{ $tdNumber }}">{{ $money($totals['sales_revenue'] ?? 0) }}</td><td class="{{ $tdNumber }}">&mdash;</td><td class="{{ $tdNumber }}">{{ $number($totals['ending_liters'] ?? 0) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="{{ $panel }} overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-800 dark:text-white/90">Tank Progress</h2>
                    <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                        Pending, Verified, and Approved readings are included. Rejected and voided readings are excluded. Ending stock = current stock - dispensed.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px]">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $th }}">Product</th>
                                <th class="{{ $thNumber }}">Dispensed (L)</th>
                                <th class="{{ $thNumber }}">Current Stock / Limit (L)</th>
                                <th class="{{ $thNumber }}">Ending Stock (L)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($lines as $line)
                                <tr>
                                    <td class="{{ $td }}"><span class="rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuelClass($line['fuel_type']) }}">{{ $line['product_name'] }}</span></td>
                                    <td class="{{ $tdNumber }}">{{ $number($line['dispensed_liters']) }}</td>
                                    <td class="{{ $tdNumber }}">{{ $number($line['tank_limit_liters']) }}</td>
                                    <td class="{{ $tdNumber }} font-semibold {{ $line['ending_liters'] < 0 ? 'text-error-600' : 'text-gray-900 dark:text-white' }}">{{ $number($line['ending_liters']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]">
                            <tr>
                                <td class="{{ $td }}">Total</td>
                                <td class="{{ $tdNumber }}">{{ $number($totals['dispensed_liters'] ?? 0) }}</td>
                                <td class="{{ $tdNumber }}">{{ $number($totals['tank_limit_liters'] ?? 0) }}</td>
                                <td class="{{ $tdNumber }}">{{ $number($totals['ending_liters'] ?? 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 2xl:grid-cols-2">
                <section class="{{ $panel }} overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Ending Stock - Last Report</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">The previous finalized ending stock carried into this period.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px]">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]">
                                <tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Qty (L)</th><th class="{{ $thNumber }}">Price / L</th><th class="{{ $thNumber }}">Cost</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($lines as $line)
                                    <tr>
                                        <td class="{{ $td }}">{{ $line['product_name'] }}</td>
                                        <td class="{{ $tdNumber }}">{{ $number($line['opening_liters']) }}</td>
                                        <td class="{{ $tdNumber }}">{{ $money($line['opening_unit_cost']) }}</td>
                                        <td class="{{ $tdNumber }} font-semibold">{{ $money($line['opening_value']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]">
                                <tr><td colspan="3" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['opening_value'] ?? 0) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section class="{{ $panel }} overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Placed Orders / Deliveries</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Delivery logs whose effective date and time fall inside this movement period.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px]">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]">
                                <tr><th class="{{ $th }}">Date</th><th class="{{ $th }}">Delivery</th><th class="{{ $th }}">Product</th><th class="{{ $th }}">Supplier</th><th class="{{ $thNumber }}">Qty (L)</th><th class="{{ $thNumber }}">Price / L</th><th class="{{ $thNumber }}">Cost</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($deliveries as $delivery)
                                    <tr>
                                        <td class="{{ $td }}">{{ \Carbon\Carbon::parse($delivery['delivery_date'])->format('M d, Y') }}</td>
                                        <td class="{{ $td }}">{{ $delivery['delivery_code'] ?: '#' . $delivery['id'] }}</td>
                                        <td class="{{ $td }}">{{ $delivery['product_name'] }}</td>
                                        <td class="{{ $td }}">{{ $delivery['supplier'] }}</td>
                                        <td class="{{ $tdNumber }}">{{ $number($delivery['liters']) }}</td>
                                        <td class="{{ $tdNumber }}">{{ $money($delivery['unit_cost']) }}</td>
                                        <td class="{{ $tdNumber }} font-semibold">{{ $money($delivery['total_cost']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No delivery logs fall inside this period.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]">
                                <tr><td colspan="6" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['purchase_value'] ?? 0) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>

            <section class="{{ $panel }} overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-800 dark:text-white/90">Current Stock</h2>
                    <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                        Last report quantity and cost plus placed orders. Price is the periodic weighted-average inventory cost.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px]">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Qty (L)</th><th class="{{ $thNumber }}">Price / L</th><th class="{{ $thNumber }}">Cost</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($lines as $line)
                                <tr>
                                    <td class="{{ $td }}"><span class="rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuelClass($line['fuel_type']) }}">{{ $line['product_name'] }}</span></td>
                                    <td class="{{ $tdNumber }}">{{ $number($line['available_liters']) }}</td>
                                    <td class="{{ $tdNumber }}">{{ $money($line['periodic_average_cost']) }}</td>
                                    <td class="{{ $tdNumber }} font-semibold">{{ $money($line['available_value']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]">
                            <tr><td colspan="3" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['available_value'] ?? 0) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 2xl:grid-cols-2">
                <section class="{{ $panel }} overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Price Guide</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Pump and bulk margins use the Current Stock price per liter.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px]">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]">
                                <tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Cost / L</th><th class="{{ $thNumber }}">Pump Margin</th><th class="{{ $thNumber }}">Pump Price</th><th class="{{ $thNumber }}">Bulk Margin</th><th class="{{ $thNumber }}">Bulk Price</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($lines as $line)
                                    <tr>
                                        <td class="{{ $td }}">{{ $line['product_name'] }}</td>
                                        <td class="{{ $tdNumber }}">{{ $money($line['periodic_average_cost']) }}</td>
                                        <td class="{{ $tdNumber }} {{ $line['pump_margin'] < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $money($line['pump_margin']) }}</td>
                                        <td class="{{ $tdNumber }}">{{ $money($line['pump_price']) }}</td>
                                        <td class="{{ $tdNumber }} {{ $line['bulk_margin'] < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $money($line['bulk_margin']) }}</td>
                                        <td class="{{ $tdNumber }}">{{ $money($line['bulk_price']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="{{ $panel }} overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Ending Stock Value</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">The Tank Progress ending liters valued at the Current Stock cost per liter.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]">
                                <tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Qty (L)</th><th class="{{ $thNumber }}">Price / L</th><th class="{{ $thNumber }}">Cost</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($lines as $line)
                                    <tr>
                                        <td class="{{ $td }}">{{ $line['product_name'] }}</td>
                                        <td class="{{ $tdNumber }}">{{ $number($line['ending_liters']) }}</td>
                                        <td class="{{ $tdNumber }}">{{ $money($line['ending_unit_cost']) }}</td>
                                        <td class="{{ $tdNumber }} font-semibold">{{ $money($line['ending_value']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]">
                                <tr><td colspan="3" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['ending_value'] ?? 0) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>

            <section class="{{ $panel }} overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Fuel Profit</h2>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Net fuel sales less the weighted inventory cost of dispensed liters, before operating expenses.</p>
                    </div>
                    <p class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ \Carbon\Carbon::parse($calculation['period_start'])->format('M d, Y') }} to {{ \Carbon\Carbon::parse($calculation['period_end'])->format('M d, Y') }}
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px]">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr><th class="{{ $th }}">Product</th><th class="{{ $thNumber }}">Sale Liters</th><th class="{{ $thNumber }}">Avg Selling Price</th><th class="{{ $thNumber }}">Cost / L</th><th class="{{ $thNumber }}">Profit Margin / L</th><th class="{{ $thNumber }}">Net Sales</th><th class="{{ $thNumber }}">Profit</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($lines as $line)
                                <tr>
                                    <td class="{{ $td }}">{{ $line['product_name'] }}</td>
                                    <td class="{{ $tdNumber }}">{{ $number($line['dispensed_liters']) }}</td>
                                    <td class="{{ $tdNumber }}">{{ $money($line['average_selling_price']) }}</td>
                                    <td class="{{ $tdNumber }}">{{ $money($line['periodic_average_cost']) }}</td>
                                    <td class="{{ $tdNumber }} {{ $line['gross_margin_per_liter'] < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $money($line['gross_margin_per_liter']) }}</td>
                                    <td class="{{ $tdNumber }}">{{ $money($line['sales_revenue']) }}</td>
                                    <td class="{{ $tdNumber }} font-semibold {{ $line['gross_profit'] < 0 ? 'text-error-600' : 'text-success-600' }}">{{ $money($line['gross_profit']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.02]">
                            <tr><td colspan="5" class="{{ $tdNumber }}">Total</td><td class="{{ $tdNumber }}">{{ $money($totals['sales_revenue'] ?? 0) }}</td><td class="{{ $tdNumber }}">{{ $money($totals['gross_profit'] ?? 0) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            @unless ($viewingSavedReport)
                <section class="{{ $panel }} px-5 py-5 print:hidden md:px-6">
                    @if ($linkedReport)
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="font-semibold text-gray-800 dark:text-white/90">Period already finalized</h2>
                                <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">This movement period is saved as {{ $linkedReport->report_code }}.</p>
                            </div>
                            <a href="{{ route('inventory.reports.index', ['report_id' => $linkedReport->id]) }}" class="{{ $secondary }}">Open finalized report</a>
                        </div>
                    @elseif ($periodStatus === 'active')
                        <div>
                            <h2 class="font-semibold text-gray-800 dark:text-white/90">Live preview</h2>
                            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Generate this period in Liters Movement when the shift range is complete. It can then be finalized here as an inventory snapshot.</p>
                        </div>
                    @else
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 class="font-semibold text-gray-800 dark:text-white/90">Finalize this inventory report</h2>
                                <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">The ending liters and weighted cost shown above become the opening stock of the next report.</p>
                            </div>
                            <form method="POST" action="{{ route('inventory.reports.store') }}" onsubmit="return confirm('Finalize this inventory report and carry its ending stock into the next report?')">
                                @csrf
                                <input type="hidden" name="period_id" value="{{ $selectedPeriod->id }}">
                                <button type="submit" class="{{ $primary }}" {{ ! $canFinalize || $setupIssues ? 'disabled' : '' }}>Finalize report</button>
                            </form>
                        </div>
                    @endif
                </section>
            @endunless
        @elseif (! $viewingSavedReport && collect($periods)->isEmpty())
            <section class="{{ $panel }} px-5 py-10 text-center md:px-6">
                <h2 class="font-semibold text-gray-800 dark:text-white/90">No Liters Movement period found</h2>
                <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">Start a Liters Movement period to preview tank progress and build the inventory report.</p>
            </section>
        @endif

        @if (collect($history)->isNotEmpty())
            <section class="{{ $panel }} overflow-hidden print:hidden">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-800 dark:text-white/90">Finalized Report History</h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($history as $report)
                        <a href="{{ route('inventory.reports.index', ['report_id' => $report->id]) }}" class="flex flex-col gap-2 px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $report->report_code }}</p>
                                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Periodic weighted average - Finalized</p>
                            </div>
                            <span class="text-theme-sm text-gray-600 dark:text-gray-300">{{ \Carbon\Carbon::parse($report->period_start)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($report->period_end)->format('M d, Y') }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
