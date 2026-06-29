    @php
        $tanks = collect($tanks ?? []);
        $deliveryLogs = collect($deliveryLogs ?? []);
        $tankTransactionPaginator = $tankTransactions ?? collect();
        $tankTransactionRows = $tankTransactionPaginator instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($tankTransactionPaginator->items())
            : collect($tankTransactionPaginator);
        $tankTransactionStats = collect($tankTransactionStats ?? []);
        $tankTransactionPerPageOptions = $tankTransactionPerPageOptions ?? [10, 20, 50, 100];
        $tankTransactionPerPage = (int) ($tankTransactionPerPage ?? 10);
        $tankTransactionPerPage = in_array($tankTransactionPerPage, $tankTransactionPerPageOptions, true)
            ? $tankTransactionPerPage
            : 10;
        $prices = collect($prices ?? []);
        $missingInventoryTables = $missingInventoryTables ?? [];
        $canManagePrices = $canManagePrices ?? false;
        $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag();
        $fuelTypes = \App\Models\TankDeliveryRecord::FUEL_TYPES;
        $checklistItems = [
            'security_seals_passed' => 'Security Seals',
            'product_level_passed' => 'Product Level',
            'ethanol_blend_passed' => 'Ethanol Blend',
            'free_of_water_passed' => 'Free of Water',
            'fully_drained_compartment_passed' => 'Fully Drained Compartment',
        ];
        $inventoryPage = $inventoryPage ?? null;
        $activeTab = in_array($inventoryPage, ['tanks', 'stock', 'pricing'], true)
            ? $inventoryPage
            : (in_array(request('tab'), ['tanks', 'stock', 'pricing'], true) ? request('tab') : 'tanks');
        $activeTab = $activeTab === 'pricing' && ! $canManagePrices ? 'tanks' : $activeTab;
        $activeTankId = (string) ($activeTankId ?? optional($tanks->first())->id);
        $activeTank = $tanks->first(fn ($tank) => (string) $tank->id === $activeTankId) ?? $tanks->first();
        $inventoryTitle = $inventoryTitle ?? match ($activeTab) {
            'stock' => 'Stock In',
            'pricing' => 'Price Management',
            default => 'Tank Management',
        };
        $inventoryDescription = $inventoryDescription ?? match ($activeTab) {
            'stock' => 'Record new fuel deliveries and review recent tank replenishment logs.',
            'pricing' => 'Maintain the active fuel prices used by future pump transactions.',
            default => 'Current tank levels, connected pump assignments, and pump transaction history.',
        };
        $filterRoute = $filterRoute ?? route(match ($activeTab) {
            'stock' => 'inventory.stock.index',
            'pricing' => 'inventory.prices.index',
            default => 'inventory.tanks.index',
        });
        $resetRoute = $resetRoute ?? $filterRoute;
        $tankTransactionTotal = method_exists($tankTransactionPaginator, 'total') ? $tankTransactionPaginator->total() : $tankTransactionRows->count();
        $tankTransactionFirstItem = method_exists($tankTransactionPaginator, 'firstItem') ? $tankTransactionPaginator->firstItem() : ($tankTransactionTotal > 0 ? 1 : null);
        $tankTransactionLastItem = method_exists($tankTransactionPaginator, 'lastItem') ? $tankTransactionPaginator->lastItem() : $tankTransactionRows->count();
        $tankTransactionQuery = collect(request()->except(['tank_transaction_page', 'tank_id', 'tank_per_page']))
            ->filter(fn ($value) => is_scalar($value) && (string) $value !== '');
        $tankTransactionUrl = function ($tankId) use ($filterRoute, $tankTransactionPerPage, $tankTransactionQuery) {
            $params = $tankTransactionQuery
                ->merge([
                    'tank_id' => $tankId,
                    'tank_per_page' => $tankTransactionPerPage,
                ])
                ->all();

            return $filterRoute . (empty($params) ? '' : '?' . http_build_query($params));
        };
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
        $iconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $viewIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15 disabled:cursor-not-allowed disabled:opacity-45';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $dateIcon = '<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="size-6"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path></svg></span>';

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

        $timeLabel = function ($value) {
            if (empty($value)) {
                return 'N/A';
            }

            try {
                return \Carbon\Carbon::parse($value)->format('h:i A');
            } catch (\Throwable) {
                return (string) $value;
            }
        };

        $numberLabel = function ($value, $suffix = '') {
            if ($value === null || $value === '') {
                return 'N/A';
            }

            return number_format((float) $value, 2) . $suffix;
        };

        $statusLabel = function ($status) {
            $status = trim((string) $status);

            if ($status === \App\Models\InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED) {
                return 'Completed';
            }

            if ($status === \App\Models\InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD) {
                return 'Pending';
            }

            return $status !== '' ? $status : 'Pending';
        };

        $statusClass = function ($status) {
            return $status === \App\Models\InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED
                ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'
                : 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
        };

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

        $checklistLabelForFuel = function ($field, $label, $fuelType) {
            $fuelType = strtolower((string) $fuelType);

            if ($field === 'ethanol_blend_passed' && str_contains($fuelType, 'diesel')) {
                return 'Visual Appearance';
            }

            return $label;
        };

        $fuelKeyForFuelType = function ($fuelType) use ($fuelTypes) {
            $fuelType = strtolower(trim((string) $fuelType));

            if ($fuelType === '') {
                return null;
            }

            if (str_contains($fuelType, 'regular')) {
                return 'unleaded';
            }

            foreach ($fuelTypes as $fuel => $label) {
                $fuelLabel = strtolower((string) $label);

                if ($fuelType === $fuel || $fuelType === $fuelLabel || str_contains($fuelType, $fuel) || str_contains($fuelType, $fuelLabel)) {
                    return $fuel;
                }
            }

            return null;
        };

        $fuelTypesForDelivery = function ($delivery) use ($fuelTypes, $fuelKeyForFuelType) {
            $fuel = $fuelKeyForFuelType($delivery->fuel_type ?? null);

            return $fuel && array_key_exists($fuel, $fuelTypes)
                ? [$fuel => $fuelTypes[$fuel]]
                : $fuelTypes;
        };
    @endphp

    <x-common.page-breadcrumb pageTitle="{{ $inventoryTitle }}" />

    <div
        x-data="inventoryModule({
            activeTab: @js($activeTab),
            activeTank: @js($activeTankId),
            canManagePrices: @js($canManagePrices),
            stockUrl: @js(route('inventory.stockIn')),
            priceUrl: @js(route('inventory.updatePrice')),
            csrfToken: @js(csrf_token()),
            initialError: @js($viewErrors->first()),
            tanks: @js($tanks->map(fn ($tank) => [
                'id' => (string) $tank->id,
                'name' => $tank->name,
                'fuel_type' => $tank->fuel_type,
            ])->values())
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', deliveryModalOpen || deliveryDetailsModal !== null || confirmation.open || messageDialog.open)"
        class="space-y-6">
        <style>
            [x-cloak] { display: none !important; }
        </style>

        @if ($missingInventoryTables)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing inventory table(s): {{ implode(', ', $missingInventoryTables) }}.
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

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Admin Inventory</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">{{ $inventoryTitle }}</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">{{ $inventoryDescription }}</p>
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
            <form method="GET" action="{{ $filterRoute }}" class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,520px)] xl:items-end">
                @if ($activeTab === 'tanks' && $activeTankId !== '')
                    <input type="hidden" name="tank_id" value="{{ $activeTankId }}">
                    <input type="hidden" name="tank_per_page" value="{{ $tankTransactionPerPage }}">
                @endif

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
                    <a href="{{ $resetRoute }}" class="{{ $secondaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6.5 8A7 7 0 1 1 5 12.333M6.5 8H3.75M6.5 8V5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Reset
                    </a>
                </div>
            </form>
        </section>

        @if ($activeTab === 'tanks')
        <div class="space-y-6">
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
                                $stats = $tankTransactionStats->get((int) $tank->id, ['count' => 0, 'liters' => 0, 'amount' => 0]);
                                $style = $fuelStyle($tank->fuel_type);
                                $isActiveTank = $activeTank && (string) $activeTank->id === (string) $tank->id;
                            @endphp
                            <a href="{{ $tankTransactionUrl($tank->id) }}" class="shrink-0 rounded-xl border px-4 py-3 text-left text-theme-sm font-medium transition {{ $isActiveTank ? 'border-brand-500 bg-brand-50 text-brand-700 dark:border-brand-400 dark:bg-brand-500/15 dark:text-brand-400' : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]' }}">
                                <span class="block text-gray-800 dark:text-white/90">{{ $tank->name }}</span>
                                <span class="mt-1 inline-flex items-center gap-2 rounded-full px-2.5 py-0.5 text-theme-xs {{ $style['badge'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                    {{ number_format((int) $stats['count']) }} transactions
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($activeTank)
                    @php
                        $style = $fuelStyle($activeTank->fuel_type);
                        $activeTankStats = $tankTransactionStats->get((int) $activeTank->id, ['count' => 0, 'liters' => 0, 'amount' => 0]);
                        $totalLiters = (float) $activeTankStats['liters'];
                        $totalAmount = (float) $activeTankStats['amount'];
                    @endphp

                    <div class="space-y-4">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $activeTank->name }}</h3>
                                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-0.5 text-theme-xs font-medium {{ $style['badge'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                            {{ $activeTank->fuel_type }}
                                        </span>
                                    </div>
                                    <p class="mt-1 {{ $captionClass }}">{{ $activeTank->connected_pumps_display }}</p>
                                </div>
                                <div class="grid grid-cols-3 gap-3 text-right">
                                    <div>
                                        <p class="{{ $labelClass }}">Count</p>
                                        <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((int) $activeTankStats['count']) }}</p>
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
                                        @forelse ($tankTransactionRows as $transaction)
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

                        <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-gray-800 lg:flex-row lg:items-center lg:justify-between">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <p class="{{ $captionClass }}">
                                    @if ($tankTransactionTotal > 0 && $tankTransactionFirstItem)
                                        Showing {{ number_format($tankTransactionFirstItem) }}-{{ number_format($tankTransactionLastItem) }} of {{ number_format($tankTransactionTotal) }} transaction{{ $tankTransactionTotal === 1 ? '' : 's' }}.
                                    @else
                                        No pump transactions found.
                                    @endif
                                </p>
                                <form method="GET" action="{{ $filterRoute }}" class="flex items-center gap-2">
                                    @foreach ($tankTransactionQuery as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                    <input type="hidden" name="tank_id" value="{{ $activeTankId }}">
                                    <label for="tank-transaction-per-page" class="text-theme-sm text-gray-500 dark:text-gray-400">Rows</label>
                                    <select id="tank-transaction-per-page" name="tank_per_page" class="h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800" onchange="this.form.submit()">
                                        @foreach ($tankTransactionPerPageOptions as $option)
                                            <option value="{{ $option }}" {{ (int) $tankTransactionPerPage === (int) $option ? 'selected' : '' }}>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>

                            @if (method_exists($tankTransactionPaginator, 'hasPages') && $tankTransactionPaginator->hasPages())
                                @php
                                    $tankTransactionCurrentPage = method_exists($tankTransactionPaginator, 'currentPage') ? (int) $tankTransactionPaginator->currentPage() : 1;
                                    $tankTransactionLastPageNumber = method_exists($tankTransactionPaginator, 'lastPage') ? (int) $tankTransactionPaginator->lastPage() : $tankTransactionCurrentPage;
                                    $tankTransactionPageNumbers = collect([1, $tankTransactionCurrentPage - 1, $tankTransactionCurrentPage, $tankTransactionCurrentPage + 1, $tankTransactionLastPageNumber])
                                        ->filter(fn ($page) => $page >= 1 && $page <= $tankTransactionLastPageNumber)
                                        ->unique()
                                        ->sort()
                                        ->values();
                                    $previousTankTransactionPage = null;
                                @endphp
                                <nav class="flex flex-wrap items-center gap-2 lg:justify-end" role="navigation" aria-label="Tank transaction pagination">
                                    @if ($tankTransactionPaginator->onFirstPage())
                                        <span class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-theme-sm font-medium text-gray-400 dark:border-gray-800 dark:text-gray-600">Prev</span>
                                    @else
                                        <a href="{{ $tankTransactionPaginator->previousPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">Prev</a>
                                    @endif

                                    @foreach ($tankTransactionPageNumbers as $page)
                                        @if ($previousTankTransactionPage && $page > $previousTankTransactionPage + 1)
                                            <span class="inline-flex h-9 min-w-9 items-center justify-center px-1 text-theme-sm text-gray-400 dark:text-gray-600">...</span>
                                        @endif

                                        @if ($page === $tankTransactionCurrentPage)
                                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-brand-500 px-3 text-theme-sm font-medium text-white" aria-current="page">{{ $page }}</span>
                                        @else
                                            <a href="{{ $tankTransactionPaginator->url($page) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">{{ $page }}</a>
                                        @endif

                                        @php $previousTankTransactionPage = $page; @endphp
                                    @endforeach

                                    @if ($tankTransactionPaginator->hasMorePages())
                                        <a href="{{ $tankTransactionPaginator->nextPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">Next</a>
                                    @else
                                        <span class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-200 px-3 text-theme-sm font-medium text-gray-400 dark:border-gray-800 dark:text-gray-600">Next</span>
                                    @endif
                                </nav>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 px-5 py-8 text-center text-theme-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        No tanks found.
                    </div>
                @endif
            </section>
        </div>
        @endif

        @if ($activeTab === 'stock')
            <section class="{{ $cardClass }}">
                <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent Deliveries</h2>
                        <p class="mt-1 {{ $captionClass }}">Latest tank replenishment logs.</p>
                    </div>
                    <button type="button" class="{{ $primaryButtonClass }}" x-on:click="openDeliveryModal">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        New Delivery Entry
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-h-[620px] max-w-full overflow-auto custom-scrollbar">
                        <table class="min-w-[1120px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[16%]">
                                <col class="w-[10%]">
                                <col class="w-[13%]">
                                <col class="w-[14%]">
                                <col class="w-[10%]">
                                <col class="w-[9%]">
                                <col class="w-[11%]">
                                <col class="w-[10%]">
                                <col class="w-[7%]">
                            </colgroup>
                            <thead class="sticky top-0 z-1 border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                                <tr>
                                    <th class="{{ $thClass }}">Delivery Code</th>
                                    <th class="{{ $thClass }}">Delivery Date</th>
                                    <th class="{{ $thClass }}">Supplier</th>
                                    <th class="{{ $thClass }}">Tank / Fuel</th>
                                    <th class="{{ $thNumberClass }}">Volume Added</th>
                                    <th class="{{ $thNumberClass }}">Total Cost</th>
                                    <th class="{{ $thClass }}">Status</th>
                                    <th class="{{ $thClass }}">Logged At</th>
                                    <th class="{{ $thClass }}">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($deliveryLogs as $log)
                                    @php
                                        $style = $fuelStyle($log->fuel_type);
                                        $deliveryCode = trim((string) ($log->delivery_code ?? ''));
                                        $deliveryStatus = trim((string) ($log->delivery_status ?? '')) ?: \App\Models\InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD;
                                        $deliveryCompleted = $deliveryStatus === \App\Models\InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED;
                                        $deliveryDetailsId = 'delivery-details-' . $log->id;
                                        $hasDeliveryDetails = ! empty($log->tank_delivery_record_id);
                                    @endphp
                                    <tr>
                                        <td class="{{ $tdPrimaryClass }}">
                                            @if ($deliveryCode !== '')
                                                <span class="inline-flex max-w-full rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ $deliveryCode }}</span>
                                            @else
                                                <span class="text-theme-xs text-gray-400 dark:text-gray-500">Not assigned</span>
                                            @endif
                                        </td>
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
                                        <td class="{{ $tdClass }}">
                                            <span class="inline-flex max-w-[150px] whitespace-normal rounded-full px-2.5 py-1 text-theme-xs font-medium leading-5 {{ $statusClass($deliveryStatus) }}">
                                                {{ $statusLabel($deliveryStatus) }}
                                            </span>
                                        </td>
                                        <td class="{{ $tdClass }}">{{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('M d, h:i A') : 'N/A' }}</td>
                                        <td class="{{ $tdClass }}">
                                            @if ($hasDeliveryDetails)
                                                <button type="button" class="{{ $viewIconButtonClass }}" x-on:click="openDeliveryDetails(@js($deliveryDetailsId))" title="View delivery details" aria-label="View delivery details for {{ $deliveryCode ?: 'delivery log ' . $log->id }}">
                                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M2.25 12s3.5-6.75 9.75-6.75S21.75 12 21.75 12s-3.5 6.75-9.75 6.75S2.25 12 2.25 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                                        <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke="currentColor" stroke-width="1.7" />
                                                    </svg>
                                                </button>
                                            @elseif ($deliveryCompleted)
                                                <span class="text-theme-xs text-error-500 dark:text-error-400">Missing</span>
                                            @else
                                                <span class="text-theme-xs text-gray-400 dark:text-gray-500">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No delivery records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            @foreach ($deliveryLogs as $log)
                @if (! empty($log->tank_delivery_record_id))
                    @php
                        $deliveryDetailsId = 'delivery-details-' . $log->id;
                        $deliveryStatus = trim((string) ($log->delivery_status ?? '')) ?: \App\Models\InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD;
                    @endphp

                    <template x-teleport="body">
                        <div
                            x-cloak
                            x-show="deliveryDetailsModal === @js($deliveryDetailsId)"
                            x-transition.opacity
                            class="fixed inset-0 flex items-center justify-center overflow-y-auto bg-gray-900/55 p-4 backdrop-blur-sm"
                            style="z-index: 2147483647;"
                            x-on:keydown.escape.window="closeDeliveryDetails()">
                            <div class="absolute inset-0" x-on:click="closeDeliveryDetails()"></div>

                            <div
                                x-show="deliveryDetailsModal === @js($deliveryDetailsId)"
                                x-transition
                                class="relative mx-auto flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950">
                                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                                    <div class="min-w-0">
                                        <p class="{{ $labelClass }}">Delivery Details</p>
                                        <h2 class="mt-1 truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $log->delivery_code ?: 'Delivery Log #' . $log->id }}</h2>
                                    </div>
                                    <button type="button" class="{{ $iconButtonClass }}" x-on:click="closeDeliveryDetails()" aria-label="Close delivery details">
                                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="min-h-0 flex-1 overflow-y-auto p-5 custom-scrollbar sm:p-6">
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Status</p>
                                            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $statusClass($deliveryStatus) }}">{{ $statusLabel($deliveryStatus) }}</span>
                                        </div>
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Record Date</p>
                                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $dateLabel($log->record_date) }}</p>
                                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $timeLabel($log->record_time) }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Plate Number</p>
                                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $log->plate_number ?: 'N/A' }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Submitted By</p>
                                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $log->staff_name ?? 'Staff' }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-5">
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Supplier</p>
                                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $log->supplier_name }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Tank / Fuel</p>
                                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $log->tank_name }} - {{ $log->fuel_type }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Admin Delivery Log</p>
                                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $dateLabel($log->delivery_date) }}</p>
                                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $dateLabel($log->created_at, 'M d, h:i A') }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Volume Added</p>
                                            <p class="mt-2 text-theme-sm font-semibold text-success-600 dark:text-success-500">+{{ $numberLabel($log->liters_delivered, ' L') }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                            <p class="{{ $labelClass }}">Total Cost</p>
                                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">&#8369;{{ $numberLabel($log->total_cost) }}</p>
                                        </div>
                                    </div>

                                    <section class="mt-5 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                        <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Inspection Checklist</h3>
                                        <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
                                            @foreach ($checklistItems as $field => $label)
                                                @php
                                                    $displayLabel = $checklistLabelForFuel($field, $label, $log->fuel_type);
                                                    $passed = (bool) ($log->{$field} ?? false);
                                                @endphp
                                                <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/[0.03]">
                                                    <span class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">{{ $displayLabel }}</span>
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $passed ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500' : 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400' }}">
                                                        {{ $passed ? 'Passed' : 'Not Passed' }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </section>

                                    <section class="mt-5 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                        <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Fuel Dipstick Summary</h3>
                                        <div class="mt-3 max-w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                                            <table class="min-w-[920px] w-full table-fixed">
                                                <colgroup>
                                                    <col class="w-[14%]">
                                                    <col class="w-[17%]">
                                                    <col class="w-[17%]">
                                                    <col class="w-[19%]">
                                                    <col class="w-[16%]">
                                                    <col class="w-[17%]">
                                                </colgroup>
                                                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                                    <tr>
                                                        <th class="{{ $thClass }}">Fuel</th>
                                                        <th class="{{ $thClass }} text-right">Before</th>
                                                        <th class="{{ $thClass }} text-right">After</th>
                                                        <th class="{{ $thClass }} text-right">Dispensed</th>
                                                        <th class="{{ $thClass }} text-right">After - Before</th>
                                                        <th class="{{ $thClass }} text-right">Summary</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                    @foreach ($fuelTypesForDelivery($log) as $fuel => $label)
                                                        @php
                                                            $beforeField = "before_unloading_dipstick_{$fuel}";
                                                            $afterField = "after_unloading_dipstick_{$fuel}";
                                                            $dispensedField = "total_dispensed_{$fuel}";
                                                            $totalField = "after_before_total_{$fuel}";
                                                            $summaryValue = $log->{$dispensedField} !== null && $log->{$totalField} !== null
                                                                ? (float) $log->{$dispensedField} + (float) $log->{$totalField}
                                                                : null;
                                                        @endphp
                                                        <tr>
                                                            <td class="{{ $tdPrimaryClass }}">{{ $label }}</td>
                                                            <td class="{{ $tdNumberClass }}">{{ $numberLabel($log->{$beforeField}) }}</td>
                                                            <td class="{{ $tdNumberClass }}">{{ $numberLabel($log->{$afterField}) }}</td>
                                                            <td class="{{ $tdNumberClass }}">{{ $numberLabel($log->{$dispensedField}) }}</td>
                                                            <td class="{{ $tdNumberClass }} font-semibold">{{ $numberLabel($log->{$totalField}) }}</td>
                                                            <td class="{{ $tdNumberClass }} font-semibold">{{ $numberLabel($summaryValue) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                </div>

                                <div class="flex justify-end border-t border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                                    <button type="button" class="{{ $secondaryButtonClass }}" x-on:click="closeDeliveryDetails()">Close</button>
                                </div>
                            </div>
                        </div>
                    </template>
                @endif
            @endforeach

            <template x-teleport="body">
                <div
                    x-cloak
                    x-show="deliveryModalOpen"
                    x-transition.opacity
                    class="fixed inset-0 flex items-center justify-center bg-gray-900/45 p-4 backdrop-blur-sm"
                    style="z-index: 2147483647;"
                    x-on:keydown.escape.window="closeDeliveryModal">
                    <div class="absolute inset-0" x-on:click="closeDeliveryModal"></div>

                    <div
                        x-show="deliveryModalOpen"
                        x-transition
                        class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950">
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                            <div class="min-w-0">
                                <p class="{{ $labelClass }}">Admin Inventory</p>
                                <h2 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">New Delivery Entry</h2>
                                <p class="mt-1 {{ $captionClass }}">Fuel replenishment</p>
                            </div>
                            <button type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.04]" x-on:click="closeDeliveryModal" title="Close" aria-label="Close">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>

                        <form id="stockInForm" class="flex min-h-0 flex-1 flex-col" x-on:submit.prevent="confirmStockIn">
                            @csrf
                            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 custom-scrollbar sm:px-6">
                                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                        <span>Delivery Code</span>
                                        <input type="text" value="Generated after saving" class="mt-1.5 {{ $inputClass }} font-semibold text-brand-600 dark:text-brand-400" readonly>
                                    </label>

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
                                </div>

                                <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Delivery Lines</p>
                                        <button type="button" class="{{ $secondaryButtonClass }} h-9 px-3 text-theme-xs" x-on:click="addDeliveryRow" :disabled="deliveryRows.length >= maxDeliveryRows">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            </svg>
                                            Add Tank
                                        </button>
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-3">
                                        <template x-for="(row, index) in deliveryRows" :key="row.key">
                                            <div class="rounded-xl border p-4 shadow-theme-xs transition-colors" :class="deliveryLineClass(row.tank_id)">
                                                <div class="mb-3 flex items-center justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p class="text-theme-xs font-semibold uppercase text-gray-700 dark:text-gray-300" x-text="'Tank Entry ' + (index + 1)"></p>
                                                        <p class="mt-0.5 text-theme-xs font-medium" :class="deliveryLineFuelClass(row.tank_id)" x-text="selectedTank(row.tank_id)?.fuel_type || 'Select tank'"></p>
                                                    </div>
                                                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-600 transition hover:bg-error-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400" x-on:click="removeDeliveryRow(index)" :disabled="deliveryRows.length === 1" title="Remove tank entry" aria-label="Remove tank entry">
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M6 12h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                                        </svg>
                                                    </button>
                                                </div>

                                                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                                    <span>Target Tank</span>
                                                    <select x-model="row.tank_id" :name="'deliveries[' + index + '][tank_id]'" class="mt-1.5 {{ $selectClass }}" required>
                                                        <template x-for="tank in availableTanksForRow(index)" :key="tank.id">
                                                            <option :value="tank.id" x-text="tank.name + ' - ' + tank.fuel_type"></option>
                                                        </template>
                                                    </select>
                                                </label>

                                                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                                        <span>Volume</span>
                                                        <div class="mt-1.5 flex rounded-lg shadow-theme-xs">
                                                            <input type="number" x-model="row.liters" :name="'deliveries[' + index + '][liters]'" min="1" step="0.01" class="h-11 w-full rounded-l-lg border border-r-0 border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="0" required>
                                                            <span class="inline-flex h-11 items-center rounded-r-lg border border-gray-300 bg-gray-50 px-3 text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">L</span>
                                                        </div>
                                                    </label>

                                                    <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                                        <span>Total Cost</span>
                                                        <div class="mt-1.5 flex rounded-lg shadow-theme-xs">
                                                            <span class="inline-flex h-11 items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-theme-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">&#8369;</span>
                                                            <input type="number" x-model="row.cost" :name="'deliveries[' + index + '][cost]'" min="1" step="0.01" class="h-11 w-full rounded-r-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="0.00" required>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                                <button type="button" class="{{ $secondaryButtonClass }}" x-on:click="closeDeliveryModal">Cancel</button>
                                <button type="submit" class="{{ $primaryButtonClass }}" :disabled="savingStock">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 12.5 9.5 17 19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span x-text="savingStock ? 'Saving...' : 'Save Delivery'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        @endif

        @if ($activeTab === 'pricing' && $canManagePrices)
            <section class="{{ $cardClass }}">
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

        <template x-teleport="body">
            <div
                x-show="messageDialog.open"
                x-cloak
                x-on:keydown.escape.window="closeMessageDialog()"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
                style="z-index: 2147483647;">
                <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md" x-on:click="closeMessageDialog()"></div>
                <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                    <button type="button" x-on:click="closeMessageDialog()" class="absolute right-4 top-4 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400" aria-label="Close message">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
                    </button>

                    <div class="p-6 pr-14">
                        <div class="flex items-start gap-4">
                            <span
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                                :class="messageDialog.type === 'info'
                                    ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'
                                    : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.25h.01M11.25 11.25h1.5v5h-1.5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="messageDialog.title"></h2>
                                <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="messageDialog.message"></p>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="button" x-on:click="closeMessageDialog()" class="{{ $primaryButtonClass }}">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template x-teleport="body">
            <div
                x-show="confirmation.open"
                x-cloak
                x-on:keydown.escape.window="resolveConfirmation(false)"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
                style="z-index: 2147483647;">
                <div class="fixed inset-0 bg-gray-950/85 backdrop-blur-md" x-on:click="resolveConfirmation(false)"></div>
                <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <span
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                                :class="confirmation.tone === 'danger'
                                    ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                                    : confirmation.tone === 'success'
                                        ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500'
                                        : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8.5v4.25M12 16h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="confirmation.title"></h2>
                                <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="confirmation.message"></p>
                                <p x-show="confirmation.details" x-cloak class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="confirmation.details"></p>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" x-on:click="resolveConfirmation(false)" class="{{ $secondaryButtonClass }}" x-text="confirmation.cancelText"></button>
                            <button
                                type="button"
                                x-ref="confirmPrimary"
                                x-on:click="resolveConfirmation(true)"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition disabled:cursor-not-allowed disabled:opacity-60"
                                :class="confirmation.tone === 'danger'
                                    ? 'bg-error-500 hover:bg-error-600'
                                    : confirmation.tone === 'success'
                                        ? 'bg-success-500 hover:bg-success-600'
                                        : 'bg-brand-500 hover:bg-brand-600'"
                                x-text="confirmation.confirmText"></button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

@push('scripts')
    <script>
        function inventoryModule(config) {
            return {
                activeTab: config.activeTab || 'tanks',
                activeTank: config.activeTank || '',
                tanks: config.tanks || [],
                maxDeliveryRows: Math.min(3, Math.max(1, (config.tanks || []).length)),
                deliveryRows: [],
                deliveryModalOpen: false,
                deliveryDetailsModal: null,
                toast: { open: false, message: '', timer: null },
                messageDialog: { open: false, type: 'error', title: '', message: '' },
                confirmation: {
                    open: false,
                    title: '',
                    message: '',
                    details: '',
                    confirmText: 'Confirm',
                    cancelText: 'Cancel',
                    tone: 'brand',
                    resolver: null
                },
                reloadTimer: null,
                feedbackClosing: false,
                savingStock: false,
                savingPrices: false,
                init() {
                    this.deliveryRows = [this.newDeliveryRow()];
                    this.initDatePickers();
                    this.showStoredToast();

                    if (config.initialError) {
                        this.$nextTick(() => this.showMessage('error', config.initialError));
                    }

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
                newDeliveryRow() {
                    const availableTank = this.tanks.find((tank) => !this.deliveryRows.some((row) => row.tank_id === tank.id));

                    return {
                        key: Date.now().toString() + '-' + Math.random().toString(16).slice(2),
                        tank_id: availableTank ? availableTank.id : (this.tanks[0]?.id || ''),
                        liters: '',
                        cost: ''
                    };
                },
                addDeliveryRow() {
                    if (this.deliveryRows.length >= this.maxDeliveryRows) {
                        return;
                    }

                    this.deliveryRows.push(this.newDeliveryRow());
                },
                removeDeliveryRow(index) {
                    if (this.deliveryRows.length === 1) {
                        return;
                    }

                    this.deliveryRows.splice(index, 1);
                },
                availableTanksForRow(currentIndex) {
                    const currentTankId = this.deliveryRows[currentIndex]?.tank_id || '';
                    const selectedByOtherRows = new Set(
                        this.deliveryRows
                            .filter((row, index) => index !== currentIndex)
                            .map((row) => row.tank_id)
                            .filter(Boolean)
                    );

                    return this.tanks.filter((tank) => tank.id === currentTankId || !selectedByOtherRows.has(tank.id));
                },
                selectedTank(tankId) {
                    return this.tanks.find((tank) => tank.id === tankId) || null;
                },
                selectedFuelType(tankId) {
                    return (this.selectedTank(tankId)?.fuel_type || '').toLowerCase();
                },
                deliveryLineClass(tankId) {
                    const fuelType = this.selectedFuelType(tankId);

                    if (fuelType.includes('premium')) {
                        return 'border-error-200 bg-error-50/80 dark:border-error-500/25 dark:bg-error-500/10';
                    }

                    if (fuelType.includes('unleaded') || fuelType.includes('regular')) {
                        return 'border-success-200 bg-success-50/80 dark:border-success-500/25 dark:bg-success-500/10';
                    }

                    if (fuelType.includes('diesel')) {
                        return 'border-warning-200 bg-warning-50/80 dark:border-warning-500/25 dark:bg-warning-500/10';
                    }

                    return 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900';
                },
                deliveryLineFuelClass(tankId) {
                    const fuelType = this.selectedFuelType(tankId);

                    if (fuelType.includes('premium')) {
                        return 'text-error-700 dark:text-error-400';
                    }

                    if (fuelType.includes('unleaded') || fuelType.includes('regular')) {
                        return 'text-success-700 dark:text-success-500';
                    }

                    if (fuelType.includes('diesel')) {
                        return 'text-warning-700 dark:text-warning-400';
                    }

                    return 'text-gray-500 dark:text-gray-400';
                },
                hasDuplicateDeliveryTanks() {
                    const selected = this.deliveryRows.map((row) => row.tank_id).filter(Boolean);

                    return selected.length !== new Set(selected).size;
                },
                openDeliveryModal() {
                    this.deliveryModalOpen = true;

                    window.setTimeout(() => {
                        this.initDatePickers();
                        document.getElementById('supplierName')?.focus({ preventScroll: true });
                    }, 50);
                },
                closeDeliveryModal() {
                    if (this.savingStock || this.confirmation.open || this.messageDialog.open || this.feedbackClosing) {
                        return;
                    }

                    this.deliveryModalOpen = false;
                },
                openDeliveryDetails(id) {
                    this.deliveryDetailsModal = id;
                },
                closeDeliveryDetails() {
                    this.deliveryDetailsModal = null;
                },
                responseMessage(data, fallback) {
                    const firstError = data && data.errors ? Object.values(data.errors).flat()[0] : '';

                    return (data && data.message) || firstError || fallback;
                },
                showNotice(type, message) {
                    if (type === 'success') {
                        this.showToast(message);
                        return;
                    }

                    this.showMessage(type, message);
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
                        message = window.sessionStorage.getItem('inventory-toast') || '';
                        window.sessionStorage.removeItem('inventory-toast');

                        if (!message) {
                            const legacyNotice = window.sessionStorage.getItem('inventoryNotice') || '';
                            window.sessionStorage.removeItem('inventoryNotice');

                            if (legacyNotice) {
                                const parsedNotice = JSON.parse(legacyNotice);

                                if (parsedNotice.type === 'success') {
                                    message = parsedNotice.message || '';
                                } else if (parsedNotice.message) {
                                    this.$nextTick(() => this.showMessage('error', parsedNotice.message));
                                }
                            }
                        }
                    } catch (error) {
                        message = '';
                    }

                    if (message) {
                        this.$nextTick(() => this.showToast(message));
                    }
                },
                showMessage(type, message, title = '') {
                    const dialogType = type === 'info' ? 'info' : 'error';
                    this.messageDialog = {
                        open: true,
                        type: dialogType,
                        title: title || (dialogType === 'info' ? 'Information' : 'Unable to Continue'),
                        message
                    };
                },
                closeMessageDialog() {
                    this.feedbackClosing = true;
                    this.messageDialog.open = false;

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },
                askConfirmation(options = {}) {
                    return new Promise(resolve => {
                        this.confirmation = {
                            open: true,
                            title: options.title || 'Confirm Action',
                            message: options.message || 'Please confirm this action.',
                            details: options.details || '',
                            confirmText: options.confirmText || 'Confirm',
                            cancelText: options.cancelText || 'Cancel',
                            tone: options.tone || 'brand',
                            resolver: resolve
                        };

                        this.$nextTick(() => {
                            if (this.$refs.confirmPrimary) {
                                this.$refs.confirmPrimary.focus();
                            }
                        });
                    });
                },
                resolveConfirmation(confirmed) {
                    const resolver = this.confirmation.resolver;
                    this.feedbackClosing = true;
                    this.confirmation.open = false;
                    this.confirmation.resolver = null;

                    if (resolver) {
                        resolver(confirmed);
                    }

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },
                reloadWithToast(message, delay = 0) {
                    try {
                        window.sessionStorage.setItem('inventory-toast', message);
                    } catch (error) {
                        this.showToast(message);
                    }

                    this.scheduleReload(delay);
                },
                scheduleReload(delay = 0) {
                    if (this.reloadTimer) {
                        window.clearTimeout(this.reloadTimer);
                    }

                    if (delay <= 0) {
                        window.location.reload();
                        return;
                    }

                    this.reloadTimer = window.setTimeout(() => window.location.reload(), delay);
                },
                async confirmStockIn() {
                    const form = document.getElementById('stockInForm');

                    if (!form || !form.reportValidity()) {
                        return;
                    }

                    if (this.hasDuplicateDeliveryTanks()) {
                        this.showNotice('error', 'Each delivery entry must use a different tank.');
                        return;
                    }

                    const totalLiters = this.deliveryRows.reduce(function(total, row) {
                        return total + Number(row.liters || 0);
                    }, 0);
                    const entryLabel = this.deliveryRows.length === 1 ? 'delivery entry' : 'delivery entries';

                    const confirmed = await this.askConfirmation({
                        title: 'Save Delivery',
                        message: 'Save ' + this.deliveryRows.length + ' ' + entryLabel + ' totaling ' + totalLiters.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' liters under one delivery code?',
                        details: 'A delivery code will be generated for the staff Tank Delivery Record module.',
                        confirmText: 'Save Delivery',
                        tone: 'success'
                    });

                    if (!confirmed) {
                        return;
                    }

                    this.savingStock = true;

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
                            this.showNotice('error', this.responseMessage(data, 'Unable to save delivery.'));
                            return;
                        }

                        const successMessage = data.delivery_code
                            ? 'Delivery Code: ' + data.delivery_code + '. ' + (data.message || 'Delivery saved.')
                            : (data.message || 'Delivery saved.');

                        this.reloadWithToast(successMessage, 700);
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

                    const confirmed = await this.askConfirmation({
                        title: 'Update Fuel Prices',
                        message: 'Apply these fuel prices to future transactions?',
                        confirmText: 'Update Prices',
                        tone: 'brand'
                    });

                    if (!confirmed) {
                        return;
                    }

                    this.savingPrices = true;

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
                            this.showNotice('error', this.responseMessage(data, 'Unable to update fuel prices.'));
                            return;
                        }

                        this.reloadWithToast(data.message || 'Fuel prices updated.', 700);
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
