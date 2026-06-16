@extends('layouts.app')

@section('content')
    @php
        $tanks = collect($tanks ?? []);
        $deliveryLogs = collect($deliveryLogs ?? []);
        $tankTransactions = collect($tankTransactions ?? []);
        $prices = collect($prices ?? []);
        $missingInventoryTables = $missingInventoryTables ?? [];
        $canManagePrices = $canManagePrices ?? false;
        $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag();
        $activeTab = in_array(request('tab'), ['tanks', 'stock', 'pricing'], true) ? request('tab') : 'tanks';
        $activeTab = $activeTab === 'pricing' && ! $canManagePrices ? 'tanks' : $activeTab;
        $activeTankId = (string) optional($tanks->first())->id;
        $summary = $summary ?? [
            'tank_count' => 0,
            'current_volume' => 0,
            'capacity' => 0,
            'fill_percentage' => 0,
            'low_tanks' => 0,
            'delivered_liters' => 0,
            'sold_liters' => 0,
            'sold_amount' => 0,
        ];

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $dateInputClass = 'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $dateIcon = '<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-6"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path></svg></span>';

        $fuelStyle = function ($fuel) {
            $fuel = strtolower((string) $fuel);

            if (str_contains($fuel, 'diesel')) {
                return [
                    'badge' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                    'dot' => 'bg-warning-500',
                    'bar' => 'bg-warning-500',
                    'border' => 'border-l-4 border-l-warning-500',
                ];
            }

            if (str_contains($fuel, 'unleaded') || str_contains($fuel, 'regular')) {
                return [
                    'badge' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                    'dot' => 'bg-success-500',
                    'bar' => 'bg-success-500',
                    'border' => 'border-l-4 border-l-success-500',
                ];
            }

            if (str_contains($fuel, 'premium')) {
                return [
                    'badge' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                    'dot' => 'bg-error-500',
                    'bar' => 'bg-error-500',
                    'border' => 'border-l-4 border-l-error-500',
                ];
            }

            return [
                'badge' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                'dot' => 'bg-brand-500',
                'bar' => 'bg-brand-500',
                'border' => 'border-l-4 border-l-brand-500',
            ];
        };
    @endphp

    <x-common.page-breadcrumb pageTitle="Inventory & Tanks" />

    <div
        x-data="inventoryModule({
            activeTab: @js($activeTab),
            activeTank: @js($activeTankId),
            canManagePrices: @js($canManagePrices),
            stockUrl: @js(route('inventory.stockIn')),
            priceUrl: @js(route('inventory.updatePrice')),
            csrfToken: @js(csrf_token())
        })"
        x-init="init()"
        class="space-y-6">
        @if ($missingInventoryTables)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing inventory table(s): {{ implode(', ', $missingInventoryTables) }}.
            </div>
        @endif

        @if ($viewErrors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300">
                {{ $viewErrors->first() }}
            </div>
        @endif

        <template x-if="notice.message">
            <div
                class="rounded-xl border p-4 text-theme-sm"
                :class="notice.type === 'success'
                    ? 'border-success-200 bg-success-50 text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400'
                    : 'border-error-200 bg-error-50 text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300'">
                <span x-text="notice.message"></span>
            </div>
        </template>

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Admin Inventory</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Inventory & Tank Management</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">Current tank levels, delivery entries, pump transactions, and active pump prices.</p>
                </div>

                <button type="button" onclick="window.print()" class="{{ $secondaryButtonClass }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M7 8V4.75C7 4.336 7.336 4 7.75 4h8.5c.414 0 .75.336.75.75V8M7 17.5H5.75A2.75 2.75 0 0 1 3 14.75v-3A2.75 2.75 0 0 1 5.75 9h12.5A2.75 2.75 0 0 1 21 11.75v3a2.75 2.75 0 0 1-2.75 2.75H17M7.75 14h8.5c.414 0 .75.336.75.75v4.5a.75.75 0 0 1-.75.75h-8.5a.75.75 0 0 1-.75-.75v-4.5c0-.414.336-.75.75-.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Print Report
                </button>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="{{ $cardClass }} flex min-h-[150px] flex-col justify-between">
                <p class="{{ $labelClass }}">Current Stock</p>
                <div>
                    <p class="text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($summary['current_volume'], 2) }} L</p>
                    <p class="mt-1 {{ $captionClass }}">{{ number_format($summary['fill_percentage'], 1) }}% of {{ number_format($summary['capacity'], 2) }} L</p>
                </div>
            </article>

            <article class="{{ $cardClass }} flex min-h-[150px] flex-col justify-between">
                <p class="{{ $labelClass }}">Active Tanks</p>
                <div>
                    <p class="text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($summary['tank_count']) }}</p>
                    <p class="mt-1 {{ $captionClass }}">{{ number_format($summary['low_tanks']) }} low-level tank{{ $summary['low_tanks'] === 1 ? '' : 's' }}</p>
                </div>
            </article>

            <article class="{{ $cardClass }} flex min-h-[150px] flex-col justify-between">
                <p class="{{ $labelClass }}">Deliveries In</p>
                <div>
                    <p class="text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($summary['delivered_liters'], 2) }} L</p>
                    <p class="mt-1 {{ $captionClass }}">{{ $deliveryLogs->count() }} delivery record{{ $deliveryLogs->count() === 1 ? '' : 's' }}</p>
                </div>
            </article>

            <article class="{{ $cardClass }} flex min-h-[150px] flex-col justify-between">
                <p class="{{ $labelClass }}">Pump Sales Out</p>
                <div>
                    <p class="text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($summary['sold_liters'], 2) }} L</p>
                    <p class="mt-1 {{ $captionClass }}">&#8369;{{ number_format($summary['sold_amount'], 2) }}</p>
                </div>
            </article>
        </div>

        <section class="{{ $cardClass }}">
            <form method="GET" action="{{ route('inventory.index') }}" class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,520px)] xl:items-end">
                <input type="hidden" name="tab" :value="activeTab">

                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Date Filter</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (['today' => 'Today', 'weekly' => 'This Week', 'monthly' => 'This Month'] as $filter => $label)
                            <button
                                type="submit"
                                name="date_filter"
                                value="{{ $filter }}"
                                class="{{ request('date_filter') === $filter ? $primaryButtonClass : $secondaryButtonClass }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="grid min-w-0 grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>From</span>
                        <span class="relative block custom-datepicker">
                            <input type="text" name="start_date" value="{{ request('start_date') }}" placeholder="Select date" autocomplete="off" class="{{ $dateInputClass }}" data-inventory-datepicker>
                            {!! $dateIcon !!}
                        </span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>To</span>
                        <span class="relative block custom-datepicker">
                            <input type="text" name="end_date" value="{{ request('end_date') }}" placeholder="Select date" autocomplete="off" class="{{ $dateInputClass }}" data-inventory-datepicker>
                            {!! $dateIcon !!}
                        </span>
                    </label>
                    <button type="submit" class="{{ $primaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                        Filter
                    </button>
                    <a href="{{ route('inventory.index') }}" class="{{ $secondaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6.5 8A7 7 0 1 1 5 12.333M6.5 8H3.75M6.5 8V5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="{{ $cardClass }} p-2 md:p-2">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button type="button" x-on:click="switchTab('tanks')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'tanks' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Tank Management
                </button>
                <button type="button" x-on:click="switchTab('stock')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                    :class="activeTab === 'stock' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                    Stock In
                </button>
                @if ($canManagePrices)
                    <button type="button" x-on:click="switchTab('pricing')" class="rounded-xl px-4 py-3 text-center text-theme-sm font-medium transition sm:text-left"
                        :class="activeTab === 'pricing' ? 'bg-brand-500 text-white shadow-theme-xs' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white'">
                        Price Management
                    </button>
                @endif
            </div>
        </section>

        <div x-show="activeTab === 'tanks'" x-cloak class="space-y-6">
            <section class="{{ $cardClass }}">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tank Management</h2>
                    <p class="mt-1 {{ $captionClass }}">Current tank volume and connected pump assignments.</p>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto">
                        <table class="min-w-[980px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[19%]">
                                <col class="w-[16%]">
                                <col class="w-[18%]">
                                <col class="w-[18%]">
                                <col class="w-[17%]">
                                <col class="w-[12%]">
                            </colgroup>
                            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                <tr>
                                    <th class="{{ $thClass }}">Tank Name</th>
                                    <th class="{{ $thClass }}">Fuel Type</th>
                                    <th class="{{ $thNumberClass }}">Current Volume</th>
                                    <th class="{{ $thNumberClass }}">Capacity</th>
                                    <th class="{{ $thClass }}">Connected Pumps</th>
                                    <th class="{{ $thClass }}">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($tanks as $tank)
                                    @php
                                        $style = $fuelStyle($tank->fuel_type);
                                    @endphp
                                    <tr>
                                        <td class="{{ $tdPrimaryClass }}">{{ $tank->name }}</td>
                                        <td class="{{ $tdClass }}">
                                            <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $style['badge'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                                {{ $tank->fuel_type }}
                                            </span>
                                        </td>
                                        <td class="{{ $tdNumberClass }}">
                                            <div>{{ number_format($tank->current_volume, 2) }} L</div>
                                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                                <div class="h-full rounded-full {{ $style['bar'] }}" style="width: {{ $tank->fill_percentage }}%"></div>
                                            </div>
                                        </td>
                                        <td class="{{ $tdNumberClass }}">{{ number_format($tank->capacity, 2) }} L</td>
                                        <td class="{{ $tdClass }}">{{ $tank->connected_pumps_display }}</td>
                                        <td class="{{ $tdClass }}">
                                            <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">{{ $tank->status }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No tanks found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="{{ $cardClass }}">
                <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Pump Transaction History Per Tank</h2>
                        <p class="mt-1 {{ $captionClass }}">Fuel sales grouped by each tank's connected pumps.</p>
                    </div>
                </div>

                @if ($tanks->isNotEmpty())
                    <div class="mb-5 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($tanks as $tank)
                            @php
                                $transactions = $tankTransactions[$tank->id] ?? collect();
                                $style = $fuelStyle($tank->fuel_type);
                            @endphp
                            <button type="button" x-on:click="activeTank = @js((string) $tank->id)" class="shrink-0 rounded-xl border px-4 py-3 text-left text-theme-sm font-medium transition"
                                :class="activeTank === @js((string) $tank->id) ? 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400 dark:bg-brand-500/15 dark:text-brand-400' : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]'">
                                <span class="block text-gray-800 dark:text-white/90">{{ $tank->name }}</span>
                                <span class="mt-1 inline-flex items-center gap-2 rounded-full px-2.5 py-0.5 text-theme-xs {{ $style['badge'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                    {{ $transactions->count() }} transactions
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif

                @foreach ($tanks as $tank)
                    @php
                        $transactions = $tankTransactions[$tank->id] ?? collect();
                        $style = $fuelStyle($tank->fuel_type);
                        $totalLiters = $transactions->sum('liters');
                        $totalAmount = $transactions->sum('amount');
                    @endphp

                    <div x-show="activeTank === @js((string) $tank->id)" x-cloak class="space-y-4">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $tank->name }}</h3>
                                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-0.5 text-theme-xs font-medium {{ $style['badge'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                            {{ $tank->fuel_type }}
                                        </span>
                                    </div>
                                    <p class="mt-1 {{ $captionClass }}">{{ $tank->connected_pumps_display }}</p>
                                </div>
                                <div class="grid grid-cols-3 gap-3 text-right">
                                    <div>
                                        <p class="{{ $labelClass }}">Count</p>
                                        <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $transactions->count() }}</p>
                                    </div>
                                    <div>
                                        <p class="{{ $labelClass }}">Liters</p>
                                        <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($totalLiters, 2) }} L</p>
                                    </div>
                                    <div>
                                        <p class="{{ $labelClass }}">Amount</p>
                                        <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;{{ number_format($totalAmount, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="max-h-[520px] max-w-full overflow-auto custom-scrollbar">
                                <table class="min-w-[1120px] w-full table-fixed">
                                    <colgroup>
                                        <col class="w-[15%]">
                                        <col class="w-[12%]">
                                        <col class="w-[8%]">
                                        <col class="w-[13%]">
                                        <col class="w-[19%]">
                                        <col class="w-[10%]">
                                        <col class="w-[9%]">
                                        <col class="w-[7%]">
                                        <col class="w-[7%]">
                                    </colgroup>
                                    <thead class="sticky top-0 z-1 border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                                        <tr>
                                            <th class="{{ $thClass }}">Date / Time</th>
                                            <th class="{{ $thClass }}">Ref</th>
                                            <th class="{{ $thClass }}">Pump</th>
                                            <th class="{{ $thClass }}">Staff</th>
                                            <th class="{{ $thClass }}">Customer</th>
                                            <th class="{{ $thClass }}">Payment</th>
                                            <th class="{{ $thNumberClass }}">Liters</th>
                                            <th class="{{ $thNumberClass }}">Price/L</th>
                                            <th class="{{ $thNumberClass }}">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @forelse ($transactions as $transaction)
                                            <tr>
                                                <td class="{{ $tdClass }}">{{ $transaction->created_at ? \Carbon\Carbon::parse($transaction->created_at)->format('M d, Y h:i A') : 'N/A' }}</td>
                                                <td class="{{ $tdPrimaryClass }}">{{ $transaction->or_number ?: 'TXN-' . $transaction->id }}</td>
                                                <td class="{{ $tdClass }}">P{{ $transaction->pump_id }}</td>
                                                <td class="{{ $tdClass }}">{{ $transaction->staff_name }}</td>
                                                <td class="{{ $tdClass }}">{{ $transaction->customer_display }}</td>
                                                <td class="{{ $tdClass }}">
                                                    @if (strtolower((string) $transaction->payment_type) === 'credit')
                                                        <span class="inline-flex rounded-full bg-warning-50 px-2.5 py-0.5 text-theme-xs font-medium text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">Credit</span>
                                                    @else
                                                        <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Walk-In</span>
                                                    @endif
                                                </td>
                                                <td class="{{ $tdNumberClass }}">{{ number_format((float) $transaction->liters, 2) }} L</td>
                                                <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $transaction->price_applied, 2) }}</td>
                                                <td class="{{ $tdNumberClass }} font-semibold text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) $transaction->amount, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No pump transactions found for this tank.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        </div>

        <div x-show="activeTab === 'stock'" x-cloak class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <section class="{{ $cardClass }} xl:col-span-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">New Delivery Entry</h2>

                <form id="stockInForm" class="mt-5 space-y-4" x-on:submit.prevent="confirmStockIn">
                    @csrf
                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Delivery Date</span>
                        <span class="relative mt-1.5 block custom-datepicker">
                            <input type="text" name="delivery_date" value="{{ now()->toDateString() }}" autocomplete="off" class="{{ $dateInputClass }}" data-inventory-datepicker required>
                            {!! $dateIcon !!}
                        </span>
                    </label>

                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Supplier</span>
                        <input type="text" name="supplier_name" id="supplierName" class="mt-1.5 uppercase {{ $inputClass }}" placeholder="SUPPLIER NAME" autocomplete="off" required>
                    </label>

                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                        <span>Target Tank</span>
                        <select name="tank_id" class="mt-1.5 {{ $selectClass }}" required>
                            @foreach ($tanks as $tank)
                                <option value="{{ $tank->id }}">{{ $tank->name }} - {{ $tank->fuel_type }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <span>Volume</span>
                            <div class="mt-1.5 flex rounded-lg shadow-theme-xs">
                                <input type="number" name="liters" id="inputLiters" min="1" step="0.01" class="h-11 w-full rounded-l-lg border border-r-0 border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="0" required>
                                <span class="inline-flex h-11 items-center rounded-r-lg border border-gray-300 bg-gray-50 px-3 text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">L</span>
                            </div>
                        </label>

                        <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                            <span>Total Cost</span>
                            <div class="mt-1.5 flex rounded-lg shadow-theme-xs">
                                <span class="inline-flex h-11 items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">&#8369;</span>
                                <input type="number" name="cost" id="inputCost" min="1" step="0.01" class="h-11 w-full rounded-r-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="0.00" required>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="w-full {{ $primaryButtonClass }}" :disabled="savingStock">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12.5 9.5 17 19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span x-text="savingStock ? 'Saving...' : 'Save Record'"></span>
                    </button>
                </form>
            </section>

            <section class="{{ $cardClass }} xl:col-span-7">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent Deliveries</h2>
                    <p class="mt-1 {{ $captionClass }}">Latest tank replenishment logs.</p>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-h-[620px] max-w-full overflow-auto custom-scrollbar">
                        <table class="min-w-[880px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[15%]">
                                <col class="w-[23%]">
                                <col class="w-[22%]">
                                <col class="w-[14%]">
                                <col class="w-[13%]">
                                <col class="w-[13%]">
                            </colgroup>
                            <thead class="sticky top-0 z-1 border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                                <tr>
                                    <th class="{{ $thClass }}">Delivery Date</th>
                                    <th class="{{ $thClass }}">Supplier</th>
                                    <th class="{{ $thClass }}">Tank / Fuel</th>
                                    <th class="{{ $thNumberClass }}">Volume Added</th>
                                    <th class="{{ $thNumberClass }}">Total Cost</th>
                                    <th class="{{ $thClass }}">Logged At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($deliveryLogs as $log)
                                    @php
                                        $style = $fuelStyle($log->fuel_type);
                                    @endphp
                                    <tr>
                                        <td class="{{ $tdClass }}">{{ \Carbon\Carbon::parse($log->delivery_date)->format('M d, Y') }}</td>
                                        <td class="{{ $tdPrimaryClass }}">{{ $log->supplier_name }}</td>
                                        <td class="{{ $tdClass }}">
                                            <div class="font-medium text-gray-800 dark:text-white/90">{{ $log->tank_name }}</div>
                                            <span class="mt-1 inline-flex items-center gap-2 rounded-full px-2.5 py-0.5 text-theme-xs {{ $style['badge'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                                {{ $log->fuel_type }}
                                            </span>
                                        </td>
                                        <td class="{{ $tdNumberClass }} font-semibold text-success-600 dark:text-success-500">+{{ number_format((float) $log->liters_delivered, 2) }} L</td>
                                        <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $log->total_cost, 2) }}</td>
                                        <td class="{{ $tdClass }}">{{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('M d, h:i A') : 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No delivery records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        @if ($canManagePrices)
            <section x-show="activeTab === 'pricing'" x-cloak class="{{ $cardClass }}">
                <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Price Management</h2>
                        <p class="mt-1 {{ $captionClass }}">Current fuel prices used by future pump transactions.</p>
                    </div>
                    <button type="button" x-on:click="updateAllPrices" class="{{ $primaryButtonClass }}" :disabled="savingPrices">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12.5 9.5 17 19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span x-text="savingPrices ? 'Updating...' : 'Update All Prices'"></span>
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto">
                        <table class="min-w-[720px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[34%]">
                                <col class="w-[33%]">
                                <col class="w-[33%]">
                            </colgroup>
                            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                <tr>
                                    <th class="{{ $thClass }}">Fuel Type</th>
                                    <th class="{{ $thClass }}">Current Price/Liter</th>
                                    <th class="{{ $thClass }}">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($prices as $price)
                                    @php
                                        $style = $fuelStyle($price->fuel_type);
                                    @endphp
                                    <tr>
                                        <td class="{{ $tdPrimaryClass }}">
                                            <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $style['badge'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                                {{ $price->fuel_type }}
                                            </span>
                                        </td>
                                        <td class="{{ $tdClass }}">
                                            <div class="flex max-w-[220px] rounded-lg shadow-theme-xs">
                                                <span class="inline-flex h-11 items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">&#8369;</span>
                                                <input type="number" min="1" step="0.01" class="fuel-price-input h-11 w-full rounded-r-lg border border-gray-300 bg-transparent px-4 py-2.5 text-right text-theme-sm font-semibold text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" data-fuel="{{ $price->fuel_type }}" value="{{ number_format((float) $price->price_per_liter, 2, '.', '') }}">
                                            </div>
                                        </td>
                                        <td class="{{ $tdClass }}">{{ $price->updated_at ? \Carbon\Carbon::parse($price->updated_at)->format('F d, Y h:i A') : 'Not set' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No fuel prices found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        function inventoryModule(config) {
            return {
                activeTab: config.activeTab || 'tanks',
                activeTank: config.activeTank || '',
                notice: { type: '', message: '' },
                savingStock: false,
                savingPrices: false,
                init() {
                    this.initDatePickers();

                    const supplierInput = document.getElementById('supplierName');
                    if (supplierInput) {
                        supplierInput.addEventListener('input', function() {
                            const cursorStart = this.selectionStart;
                            const cursorEnd = this.selectionEnd;
                            this.value = this.value.toUpperCase();
                            this.setSelectionRange(cursorStart, cursorEnd);
                        });
                    }
                },
                initDatePickers() {
                    if (!window.flatpickr) {
                        return;
                    }

                    document.querySelectorAll('[data-inventory-datepicker]').forEach(function(input) {
                        if (input._inventoryDatePicker) {
                            return;
                        }

                        input._inventoryDatePicker = window.flatpickr(input, {
                            static: true,
                            monthSelectorType: 'static',
                            dateFormat: 'Y-m-d',
                            defaultDate: input.value || null,
                            allowInput: false,
                            disableMobile: true
                        });
                    });
                },
                switchTab(tab) {
                    if (tab === 'pricing' && !config.canManagePrices) {
                        return;
                    }

                    this.activeTab = tab;
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tab);
                    window.history.replaceState({}, '', url);
                },
                showNotice(type, message) {
                    this.notice = { type, message };
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                async confirmStockIn() {
                    const form = document.getElementById('stockInForm');
                    const liters = document.getElementById('inputLiters')?.value || '0';

                    if (!form || !form.reportValidity()) {
                        return;
                    }

                    if (!window.confirm('Add ' + Number(liters).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' liters to inventory?')) {
                        return;
                    }

                    this.savingStock = true;
                    this.notice = { type: '', message: '' };

                    try {
                        const response = await fetch(config.stockUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: new FormData(form)
                        });
                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            this.showNotice('error', data.message || 'Unable to save delivery.');
                            return;
                        }

                        this.showNotice('success', data.message || 'Delivery saved.');
                        window.setTimeout(function() {
                            window.location.reload();
                        }, 700);
                    } catch (error) {
                        this.showNotice('error', 'An unexpected error occurred while saving the delivery.');
                    } finally {
                        this.savingStock = false;
                    }
                },
                async updateAllPrices() {
                    const prices = {};

                    document.querySelectorAll('.fuel-price-input').forEach(function(input) {
                        prices[input.dataset.fuel] = input.value;
                    });

                    if (Object.keys(prices).length === 0) {
                        this.showNotice('error', 'No fuel prices are available to update.');
                        return;
                    }

                    if (!window.confirm('Apply these fuel prices to future transactions?')) {
                        return;
                    }

                    this.savingPrices = true;
                    this.notice = { type: '', message: '' };

                    try {
                        const response = await fetch(config.priceUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ prices })
                        });
                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            this.showNotice('error', data.message || 'Unable to update fuel prices.');
                            return;
                        }

                        this.showNotice('success', data.message || 'Fuel prices updated.');
                        window.setTimeout(function() {
                            window.location.reload();
                        }, 700);
                    } catch (error) {
                        this.showNotice('error', 'An unexpected error occurred while updating fuel prices.');
                    } finally {
                        this.savingPrices = false;
                    }
                }
            };
        }
    </script>
@endpush
