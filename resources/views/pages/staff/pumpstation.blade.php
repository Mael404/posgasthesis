@extends('layouts.app')

@section('content')
    @php
        $prices = collect($prices ?? []);
        $customers = collect($customers ?? []);
        $tanks = collect($tanks ?? []);
        $missingPumpTables = $missingPumpTables ?? [];
        $recentSales = $recentSales ?? collect();
        $recentItems = $recentSales instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($recentSales->items())
            : collect($recentSales);

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $dangerButtonClass = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-error-200 bg-white px-3 py-2 text-theme-xs font-medium text-error-600 shadow-theme-xs transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-error-500/30 dark:bg-gray-900 dark:text-error-400 dark:hover:bg-error-500/10';
        $smallButtonClass = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-xs font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $successButtonClass = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-success-200 bg-white px-3 py-2 text-theme-xs font-medium text-success-700 shadow-theme-xs transition hover:bg-success-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-success-500/30 dark:bg-gray-900 dark:text-success-400 dark:hover:bg-success-500/10';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thCompactClass = 'px-3 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thCompactNumberClass = 'px-3 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';
        $tdCompactClass = 'px-3 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdCompactPrimaryClass = 'px-3 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdCompactNumberClass = 'px-3 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';

        $standardPrice = function ($fuel) use ($prices) {
            return (float) ($prices->get($fuel) ?? 0);
        };

        $fuelStyle = function ($fuel) {
            $fuel = strtolower((string) $fuel);

            if (str_contains($fuel, 'diesel')) {
                return [
                    'badge' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                    'dot' => 'bg-warning-500',
                    'ring' => 'ring-warning-500/20',
                    'border' => 'border-warning-200 dark:border-warning-500/30',
                    'soft' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
                    'bar' => 'bg-warning-500',
                    'card' => 'bg-gradient-to-br from-warning-50/90 via-white to-white dark:from-warning-500/10 dark:via-gray-900 dark:to-gray-900',
                    'panel' => 'border-warning-100 bg-warning-50/60 dark:border-warning-500/25 dark:bg-warning-500/10',
                    'price' => 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                ];
            }

            if (str_contains($fuel, 'unleaded') || str_contains($fuel, 'regular')) {
                return [
                    'badge' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                    'dot' => 'bg-success-500',
                    'ring' => 'ring-success-500/20',
                    'border' => 'border-success-200 dark:border-success-500/30',
                    'soft' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-500',
                    'bar' => 'bg-success-500',
                    'card' => 'bg-gradient-to-br from-success-50/90 via-white to-white dark:from-success-500/10 dark:via-gray-900 dark:to-gray-900',
                    'panel' => 'border-success-100 bg-success-50/60 dark:border-success-500/25 dark:bg-success-500/10',
                    'price' => 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                ];
            }

            if (str_contains($fuel, 'premium')) {
                return [
                    'badge' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                    'dot' => 'bg-error-500',
                    'ring' => 'ring-error-500/20',
                    'border' => 'border-error-200 dark:border-error-500/30',
                    'soft' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400',
                    'bar' => 'bg-error-500',
                    'card' => 'bg-gradient-to-br from-error-50/90 via-white to-white dark:from-error-500/10 dark:via-gray-900 dark:to-gray-900',
                    'panel' => 'border-error-100 bg-error-50/60 dark:border-error-500/25 dark:bg-error-500/10',
                    'price' => 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                ];
            }

            return [
                'badge' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                'dot' => 'bg-brand-500',
                'ring' => 'ring-brand-500/20',
                'border' => 'border-brand-200 dark:border-brand-500/30',
                'soft' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400',
                'bar' => 'bg-brand-500',
                'card' => 'bg-gradient-to-br from-brand-50/90 via-white to-white dark:from-brand-500/10 dark:via-gray-900 dark:to-gray-900',
                'panel' => 'border-brand-100 bg-brand-50/60 dark:border-brand-500/25 dark:bg-brand-500/10',
                'price' => 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
            ];
        };

        $customerLabel = function ($customer) {
            $businessName = trim((string) ($customer->business_name ?? ''));
            $ownerName = trim((string) ($customer->owner_name ?? ''));

            if ($businessName !== '' && strtoupper($businessName) !== 'N/A') {
                return $ownerName !== '' && strtoupper($ownerName) !== 'N/A'
                    ? strtoupper($businessName) . ' - ' . ucwords(strtolower($ownerName))
                    : strtoupper($businessName);
            }

            return $ownerName !== ''
                ? ucwords(strtolower($ownerName))
                : 'Customer #' . $customer->id;
        };

        $customerFuelPrice = function ($customer, $field, $fuel) use ($standardPrice) {
            $value = (float) ($customer->{$field} ?? 0);

            return $value > 0 ? $value : $standardPrice($fuel);
        };

        $customerPayload = $customers->map(function ($customer) use ($customerLabel, $customerFuelPrice) {
            $label = $customerLabel($customer);
            $businessName = trim((string) ($customer->business_name ?? ''));
            $ownerName = trim((string) ($customer->owner_name ?? ''));
            $contactNumber = trim((string) ($customer->contact_number ?? ''));
            $businessName = strtoupper($businessName) === 'N/A' ? '' : $businessName;
            $ownerName = strtoupper($ownerName) === 'N/A' ? '' : $ownerName;

            return [
                'id' => (int) $customer->id,
                'label' => $label,
                'business_name' => $businessName,
                'owner_name' => $ownerName,
                'contact_number' => $contactNumber,
                'search' => strtolower($label . ' ' . $businessName . ' ' . $ownerName . ' ' . $contactNumber),
                'balance' => round((float) ($customer->current_balance ?? 0), 2),
                'limit' => round((float) ($customer->credit_limit ?? 0), 2),
                'prices' => [
                    'Diesel' => round($customerFuelPrice($customer, 'sp_diesel', 'Diesel'), 2),
                    'Unleaded' => round($customerFuelPrice($customer, 'sp_unleaded', 'Unleaded'), 2),
                    'Premium' => round($customerFuelPrice($customer, 'sp_premium', 'Premium'), 2),
                ],
            ];
        })->values();

        $connectedPumpNumbers = function ($tank) {
            preg_match_all('/\d+/', (string) ($tank->connected_pumps ?? ''), $matches);

            return collect($matches[0] ?? [])->map(fn ($pump) => (int) $pump)->unique()->values();
        };

        $pumpFuelMap = [
            1 => 'Premium',
            2 => 'Unleaded',
            3 => 'Premium',
            4 => 'Unleaded',
            5 => 'Diesel',
            6 => 'Diesel',
        ];

        $pumps = collect($pumpFuelMap)->map(function ($fuel, $pumpNumber) use ($tanks, $connectedPumpNumbers, $standardPrice) {
            $tank = $tanks->first(function ($tank) use ($pumpNumber, $connectedPumpNumbers) {
                return $connectedPumpNumbers($tank)->contains((int) $pumpNumber);
            });
            $currentVolume = $tank ? round((float) ($tank->current_volume ?? 0), 2) : 0;
            $capacity = $tank ? round((float) ($tank->capacity ?? 0), 2) : 0;
            $fillPercentage = $capacity > 0
                ? round(min(100, max(0, ($currentVolume / $capacity) * 100)), 1)
                : 0;

            return [
                'id' => (int) $pumpNumber,
                'label' => 'Pump ' . str_pad((string) $pumpNumber, 2, '0', STR_PAD_LEFT),
                'fuel_type' => $fuel,
                'standard_price' => round($standardPrice($fuel), 2),
                'tank' => $tank ? [
                    'id' => (int) $tank->id,
                    'name' => $tank->name ?? ('Tank ' . $tank->id),
                    'current_volume' => $currentVolume,
                    'capacity' => $capacity,
                    'fill_percentage' => $fillPercentage,
                    'connected_pumps' => $tank->connected_pumps ?? 'Not assigned',
                    'status' => $tank->status ?? 'Active',
                    'low' => $currentVolume < 2000,
                ] : null,
            ];
        })->values();

        $fuelSaleItems = $recentItems->reject(fn ($sale) => strtolower((string) ($sale->payment_type ?? '')) === 'collection');
        $collectionItems = $recentItems->filter(fn ($sale) => (string) ($sale->payment_type ?? '') === 'Collection');
        $pendingCollections = $collectionItems->filter(fn ($sale) => empty($sale->customer_payment_posted_at) && empty($sale->customer_payment_voided_at) && empty($sale->locked_by_shift_reading_id));
        $editableSales = $fuelSaleItems->mapWithKeys(function ($sale) {
            return [(string) $sale->id => [
                'id' => (int) $sale->id,
                'pump_id' => (int) $sale->pump_id,
                'fuel_type' => $sale->fuel_type,
                'payment_type' => $sale->payment_type,
                'customer_id' => $sale->customer_id ? (int) $sale->customer_id : null,
                'customer_name' => $sale->customer_name,
                'plate_no' => $sale->plate_no,
                'liters' => (float) $sale->liters,
                'price_applied' => (float) $sale->price_applied,
                'amount' => (float) $sale->amount,
                'manual_discount' => (float) ($sale->manual_discount ?? 0),
                'surcharge' => (float) ($sale->surcharge ?? 0),
                'or_number' => $sale->or_number,
            ]];
        });
    @endphp

    <style>
        [x-cloak] { display: none !important; }

        .sale-transaction-modal .sale-modal-control {
            height: 2.5rem;
            min-height: 2.5rem;
            min-width: 0;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }

        .sale-transaction-modal .sale-modal-affix {
            height: 2.5rem;
            font-size: 0.875rem;
        }

        .sale-transaction-modal .sale-modal-entry-control {
            height: 3rem;
            min-height: 3rem;
            font-size: 1rem;
            line-height: 1.5rem;
        }

        .sale-transaction-modal .sale-modal-entry-affix {
            height: 3rem;
            font-size: 1rem;
        }

        .sale-transaction-modal .sale-modal-control:focus {
            border-color: #12b76a !important;
            box-shadow: 0 0 0 3px rgba(18, 183, 106, 0.18) !important;
        }

        .sale-transaction-modal input[type="number"] {
            -moz-appearance: textfield;
        }

        .sale-transaction-modal input[type="number"]::-webkit-inner-spin-button,
        .sale-transaction-modal input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .sale-transaction-modal .bg-brand-500 {
            background-color: #12b76a !important;
        }

        .sale-transaction-modal .hover\:bg-brand-600:hover {
            background-color: #0e9f6e !important;
        }

        .sale-modal-digital {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.025em;
        }
    </style>

    <x-common.page-breadcrumb pageTitle="Pump Station" />

    <div
        x-data="staffPumpModule({
            pumps: @js($pumps),
            customers: @js($customerPayload),
            prices: @js($prices),
            editableSales: @js($editableSales),
            routes: {
                store: @js(route('staff.pump.store')),
                updateTemplate: @js(route('staff.pump.update', ['id' => '__ID__'])),
                void: @js(route('staff.pump.void')),
                payment: @js(route('staff.pump.storePayment')),
                bypass: @js(route('staff.pump.bypassPayment')),
                voidPostedPayment: @js(route('staff.pump.voidPostedPayment')),
                salesInvoiceStore: @js(route('staff.sales-invoices.store')),
                printSelected: @js(route('staff.pump.printSelected')),
                unpaidInvoicesBase: @js(url('/staff/pump-station/customers'))
            },
            today: @js(now()->format('Y-m-d')),
            nextSalesInvoiceNumber: @js($nextSalesInvoiceNumber ?? 1),
            authorizedRepresentative: @js(auth()->user()?->name ?? ''),
            csrfToken: @js(csrf_token())
        })"
        x-init="init()"
        x-effect="
            document.body.classList.toggle('overflow-hidden', saleModalOpen || paymentModalOpen || salesInvoiceModalOpen || confirmation.open || messageDialog.open || passwordDialog.open);
            document.documentElement.classList.toggle('overflow-hidden', saleModalOpen || paymentModalOpen || salesInvoiceModalOpen || confirmation.open || messageDialog.open || passwordDialog.open);
        "
        class="space-y-6">

        @if ($missingPumpTables)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing pump table(s): {{ implode(', ', $missingPumpTables) }}.
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
            <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Pump Lanes</h2>
                    <p class="mt-1 {{ $captionClass }}">Open a pump to log a credit or walk-in sale.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row lg:shrink-0">
                    <button type="button" x-on:click="openSalesInvoiceModal" class="{{ $secondaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7.75 3.5h8.5A1.75 1.75 0 0 1 18 5.25v13.5a1.75 1.75 0 0 1-1.75 1.75h-8.5A1.75 1.75 0 0 1 6 18.75V5.25A1.75 1.75 0 0 1 7.75 3.5ZM9 8h6M9 11.5h6M9 15h3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Sales Invoice
                    </button>
                    <a href="{{ route('staff.printed-transactions.index') }}" class="{{ $secondaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 8V4.75C7 4.336 7.336 4 7.75 4h8.5c.414 0 .75.336.75.75V8M7 17.5H5.75A2.75 2.75 0 0 1 3 14.75v-3A2.75 2.75 0 0 1 5.75 9h12.5A2.75 2.75 0 0 1 21 11.75v3a2.75 2.75 0 0 1-2.75 2.75H17M7.75 14h8.5c.414 0 .75.336.75.75v4.5a.75.75 0 0 1-.75.75h-8.5a.75.75 0 0 1-.75-.75v-4.5c0-.414.336-.75.75-.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Printed
                    </a>
                    <button type="button" x-on:click="openPaymentModal" class="{{ $primaryButtonClass }}">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7.75A2.75 2.75 0 0 1 6.75 5h10.5A2.75 2.75 0 0 1 20 7.75v8.5A2.75 2.75 0 0 1 17.25 19H6.75A2.75 2.75 0 0 1 4 16.25v-8.5ZM4 9h16M8 14h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Receive Payment
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($pumps as $pump)
                    @php
                        $style = $fuelStyle($pump['fuel_type']);
                    @endphp
                    <button
                        type="button"
                        x-on:click="openSale(@js($pump))"
                        class="group relative min-h-[250px] overflow-hidden rounded-2xl border p-5 pt-6 text-left shadow-theme-xs ring-1 ring-transparent transition hover:-translate-y-0.5 hover:shadow-theme-md focus:outline-hidden focus:ring-3 {{ $style['card'] }} {{ $style['border'] }} {{ $style['ring'] }}">
                        <span class="absolute inset-x-0 top-0 h-1.5 {{ $style['bar'] }}"></span>
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex size-12 items-center justify-center rounded-xl {{ $style['soft'] }}">
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M7 21V4.75C7 3.784 7.784 3 8.75 3h5.5c.966 0 1.75.784 1.75 1.75V21M5.5 21h12M9.5 7h4M16 8.5h1.25c.966 0 1.75.784 1.75 1.75V18a1.5 1.5 0 0 0 3 0v-4.5c0-.828-.672-1.5-1.5-1.5H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $pump['label'] }}</h3>
                                    <span class="mt-1 inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $style['badge'] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                        {{ $pump['fuel_type'] }}
                                    </span>
                                </div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-theme-xs font-medium tabular-nums {{ $style['price'] }}">
                                &#8369;{{ number_format($pump['standard_price'], 2) }}/L
                            </span>
                        </div>

                        <div class="mt-5 rounded-xl border p-4 {{ $style['panel'] }}">
                            @if ($pump['tank'])
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $pump['tank']['name'] }}</p>
                                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Tank {{ $pump['tank']['id'] }} · {{ $pump['tank']['connected_pumps'] }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $pump['tank']['low'] ? 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400' : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $pump['tank']['low'] ? 'bg-error-500' : 'bg-success-500' }}"></span>
                                        {{ $pump['tank']['low'] ? 'Low' : $pump['tank']['status'] }}
                                    </span>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-theme-xs uppercase text-gray-500 dark:text-gray-400">Available</p>
                                        <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($pump['tank']['current_volume'], 0) }} L</p>
                                    </div>
                                    <div>
                                        <p class="text-theme-xs uppercase text-gray-500 dark:text-gray-400">Capacity</p>
                                        <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($pump['tank']['capacity'], 0) }} L</p>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="mb-1 flex items-center justify-between text-theme-xs text-gray-500 dark:text-gray-400">
                                        <span>Tank fill</span>
                                        <span>{{ number_format($pump['tank']['fill_percentage'], 1) }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                        <div class="h-full rounded-full {{ $pump['tank']['low'] ? 'bg-error-500' : $style['dot'] }}" style="width: {{ $pump['tank']['fill_percentage'] }}%"></div>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-2 text-theme-sm font-medium text-error-600 dark:text-error-400">
                                    <span class="h-2 w-2 rounded-full bg-error-500"></span>
                                    No tank connected
                                </div>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent Transactions & Logs</h2>
                    <p class="mt-1 {{ $captionClass }}">Newest fuel sales and account collections from your shift.</p>
                </div>
                <button type="button" x-on:click="printSelected" class="{{ $secondaryButtonClass }}" :disabled="selectedIds.length === 0">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M7 8V4.75C7 4.336 7.336 4 7.75 4h8.5c.414 0 .75.336.75.75V8M7 17.5H5.75A2.75 2.75 0 0 1 3 14.75v-3A2.75 2.75 0 0 1 5.75 9h12.5A2.75 2.75 0 0 1 21 11.75v3a2.75 2.75 0 0 1-2.75 2.75H17M7.75 14h8.5c.414 0 .75.336.75.75v4.5a.75.75 0 0 1-.75.75h-8.5a.75.75 0 0 1-.75-.75v-4.5c0-.414.336-.75.75-.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span x-text="selectedIds.length ? 'Print Selected (' + selectedIds.length + ')' : 'Print Selected'"></span>
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1100px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[5%]">
                            <col class="w-[9%]">
                            <col class="w-[7%]">
                            <col class="w-[9%]">
                            <col class="w-[9%]">
                            <col class="w-[21%]">
                            <col class="w-[7%]">
                            <col class="w-[19%]">
                            <col class="w-[14%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-5 py-3 text-center text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Print</th>
                                <th class="{{ $thCompactClass }}">Time</th>
                                <th class="{{ $thCompactClass }}">Pump</th>
                                <th class="{{ $thCompactClass }}">Product</th>
                                <th class="{{ $thCompactClass }}">Type</th>
                                <th class="{{ $thCompactClass }}">Customer Details</th>
                                <th class="{{ $thCompactNumberClass }}">Liters</th>
                                <th class="{{ $thNumberClass }}">Status / Amount</th>
                                <th class="{{ $thClass }}">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($recentSales as $sale)
                                @php
                                    $postedCustomerId = $sale->posted_customer_id ?? null;
                                    $printAccountCustomerId = $sale->customer_id ?: $postedCustomerId;
                                    $isCollection = $sale->payment_type === 'Collection';
                                    $isLocked = !empty($sale->locked_by_shift_reading_id);
                                    $isLockedCollection = $isCollection && $isLocked;
                                    $isVoidedPostedCollection = $isCollection && !empty($sale->customer_payment_voided_at);
                                    $isPostedCollection = $isCollection
                                        && !$isVoidedPostedCollection
                                        && (!empty($sale->customer_payment_posted_at) || $isLockedCollection);
                                    $registeredPrintCustomer = in_array($sale->payment_type, ['credit', 'Collection'], true) && !empty($printAccountCustomerId);
                                    $saleStyle = $fuelStyle($sale->fuel_type);
                                    $totalDiscount = (float) ($sale->discount ?? 0) + (float) ($sale->manual_discount ?? 0);
                                    $customerPrimaryName = trim((string) ($sale->owner_name ?: ($sale->customer_name ?: '')));
                                    $customerCompanyName = trim((string) ($sale->business_name ?: ''));
                                    $customerPrimaryName = $customerPrimaryName !== '' ? $customerPrimaryName : 'No customer name';
                                    $customerCompanyName = $customerCompanyName !== '' && strtoupper($customerCompanyName) !== 'N/A'
                                        ? $customerCompanyName
                                        : 'No company name';

                                    if ($registeredPrintCustomer) {
                                        $printCustomerKey = 'account-' . $printAccountCustomerId;
                                        $printCustomerLabel = !empty($sale->business_name) && $sale->business_name !== 'N/A'
                                            ? $sale->business_name
                                            : ($sale->owner_name ?: ($sale->customer_name ?: 'No customer name'));
                                    } else {
                                        $walkinName = $sale->customer_name ?: 'Walk-In Customer';
                                        $walkinPlate = $sale->plate_no ?: '';
                                        $printCustomerKey = 'walkin-' . md5(strtolower(trim($walkinName . '|' . $walkinPlate)));
                                        $printCustomerLabel = $walkinName . ($walkinPlate ? ' / Plate: ' . $walkinPlate : '');
                                    }

                                @endphp
                                <tr :class="selectedIds.includes({{ (int) $sale->id }}) ? 'bg-brand-50/60 dark:bg-brand-500/10' : ''">
                                    <td class="px-5 py-4 text-center align-middle">
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900"
                                            x-on:change="togglePrint({{ (int) $sale->id }}, @js($printCustomerKey), @js($printCustomerLabel), $event)"
                                            @disabled($isVoidedPostedCollection)
                                            title="{{ $isVoidedPostedCollection ? 'Voided payments cannot be printed' : 'Select transaction' }}">
                                    </td>
                                    <td class="{{ $tdCompactClass }}">{{ $sale->created_at ? \Carbon\Carbon::parse($sale->created_at)->format('M d, h:i A') : 'N/A' }}</td>
                                    <td class="{{ $tdCompactClass }}">{{ $isCollection ? 'N/A' : 'Pump ' . $sale->pump_id }}</td>
                                    <td class="{{ $tdCompactClass }}">
                                        @if ($isCollection)
                                            <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Payment</span>
                                        @else
                                            <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $saleStyle['badge'] }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $saleStyle['dot'] }}"></span>
                                                {{ $sale->fuel_type }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="{{ $tdCompactClass }}">
                                        @if ($sale->payment_type === 'credit')
                                            <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Credit Sale</span>
                                        @elseif ($isCollection)
                                            <span class="inline-flex rounded-full bg-success-50 px-2.5 py-1 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Collection</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">Walk-In</span>
                                        @endif
                                    </td>
                                    <td class="{{ $tdCompactPrimaryClass }}">
                                        @if ($sale->payment_type === 'credit' || $isCollection)
                                            <div class="max-w-full truncate font-semibold">{{ $customerPrimaryName }}</div>
                                            <div class="mt-1 truncate text-theme-xs font-normal text-gray-500 dark:text-gray-400">
                                                {{ $customerCompanyName }}
                                            </div>
                                        @else
                                            <div class="max-w-full truncate">{{ $sale->customer_name ?: 'Walk-In Customer' }}</div>
                                            @if ($sale->plate_no)
                                                <div class="mt-1 truncate text-theme-xs font-normal text-gray-500 dark:text-gray-400">Plate: {{ $sale->plate_no }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="{{ $tdCompactNumberClass }}">{{ $isCollection ? '-' : number_format((float) $sale->liters, 2) . ' L' }}</td>
                                    <td class="{{ $tdNumberClass }}">
                                        <div class="{{ $isCollection ? 'text-success-700 dark:text-success-400' : 'text-gray-800 dark:text-white/90' }} font-semibold">
                                            {{ $isCollection ? '+' : '' }}&#8369;{{ number_format((float) $sale->amount, 2) }}
                                        </div>
                                        @if ($isCollection)
                                            <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                                @if ($isVoidedPostedCollection)
                                                    <span class="font-medium text-error-600 dark:text-error-400">Voided from account</span>
                                                @elseif ($isPostedCollection)
                                                    <span class="font-medium text-success-700 dark:text-success-400">Reflected in account</span>
                                                @else
                                                    Pending verification
                                                @endif
                                            </div>
                                        @else
                                            @if ((float) ($sale->surcharge ?? 0) > 0)
                                                <div class="mt-1 text-theme-xs font-medium text-warning-600 dark:text-warning-400">+&#8369;{{ number_format((float) $sale->surcharge, 2) }} excess</div>
                                            @endif
                                            @if ($totalDiscount > 0)
                                                <div class="mt-1 text-theme-xs text-success-700 dark:text-success-400">Discount: &#8369;{{ number_format($totalDiscount, 2) }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        @if ($isCollection)
                                            @if ($isVoidedPostedCollection)
                                                <span class="inline-flex h-9 items-center rounded-lg bg-error-50 px-3 text-theme-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400">Voided</span>
                                            @elseif ($isLockedCollection)
                                                <span class="inline-flex h-9 items-center rounded-lg bg-gray-100 px-3 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">Locked</span>
                                            @elseif ($isPostedCollection)
                                                <div class="flex flex-wrap gap-2">
                                                    <span class="inline-flex h-9 items-center rounded-lg bg-success-50 px-3 text-theme-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Posted</span>
                                                    <button type="button" class="{{ $dangerButtonClass }}" x-on:click="voidPostedPayment({{ (int) $sale->id }}, @js($printCustomerLabel), {{ (float) $sale->amount }})">Void</button>
                                                </div>
                                            @else
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" class="{{ $successButtonClass }}" x-on:click="bypassPayment({{ (int) $sale->id }}, @js($printCustomerLabel), {{ (float) $sale->amount }})">Bypass</button>
                                                    <button type="button" class="{{ $dangerButtonClass }}" x-on:click="voidPayment({{ (int) $sale->id }})">Cancel</button>
                                                </div>
                                            @endif
                                        @elseif ($isLocked)
                                            <span class="inline-flex h-9 items-center rounded-lg bg-gray-100 px-3 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">Locked</span>
                                        @else
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" class="{{ $smallButtonClass }}" x-on:click="openEditSale(editableSales[{{ (int) $sale->id }}])">Edit</button>
                                                <button type="button" class="{{ $dangerButtonClass }}" x-on:click="voidSale({{ (int) $sale->id }})">Void</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No sales or collections logged yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($recentSales instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $recentSales->total() > 0)
                <div class="mt-4 flex flex-col gap-3 text-theme-sm text-gray-500 dark:text-gray-400 md:flex-row md:items-center md:justify-between">
                    <div>
                        Showing {{ $recentSales->firstItem() }} to {{ $recentSales->lastItem() }} of {{ $recentSales->total() }} transactions
                    </div>
                    <div>
                        {{ $recentSales->links() }}
                    </div>
                </div>
            @endif
        </section>

        @include('pages.staff.partials.sales-invoice-modal')

        <template x-teleport="body">
            <div x-show="saleModalOpen" x-cloak class="fixed inset-0 z-[100010] grid place-items-center overflow-hidden bg-gray-950/75 p-1.5 backdrop-blur-md sm:p-3">
                <div
                    x-on:click.outside="if (!feedbackActive()) closeSaleModal()"
                    class="sale-transaction-modal flex h-[95dvh] max-h-[95dvh] w-[calc(100vw-0.75rem)] min-w-0 max-w-[100rem] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 sm:w-[92vw] sm:rounded-2xl">
                    <div class="flex shrink-0 items-start justify-between gap-3 px-4 py-3 text-white sm:px-5 sm:py-3.5" :class="fuelTheme(sale.fuel_type).header">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/20 sm:size-10">
                                <svg class="size-5 sm:size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5.75 20.25h8.5M7 20V5.75C7 4.784 7.784 4 8.75 4h4.5c.966 0 1.75.784 1.75 1.75V20M8.75 8h4.5M15 8.5h1.25c.966 0 1.75.784 1.75 1.75v5.25a1.5 1.5 0 0 0 3 0v-4.25c0-.828-.672-1.5-1.5-1.5H18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/75 sm:text-xs" x-text="sale.id ? 'Edit Transaction' : 'New Transaction'"></p>
                                <h2 class="mt-0.5 flex min-w-0 flex-wrap items-center gap-1.5 text-xl font-bold leading-tight sm:gap-2">
                                    <span class="truncate" x-text="sale.pumpLabel || 'Pump'"></span>
                                    <span class="text-white/65">/</span>
                                    <span class="truncate" x-text="sale.fuel_type || 'Fuel'"></span>
                                </h2>
                                <p class="mt-0.5 hidden truncate text-xs font-medium text-white/85 md:block" x-text="sale.mode === 'credit' ? 'Charge this transaction to a customer account.' : 'Record a walk-in transaction.'"></p>
                            </div>
                        </div>
                        <button type="button" x-on:click="closeSaleModal" class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white/85 transition hover:bg-white/20 hover:text-white sm:size-9" aria-label="Close transaction modal">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m6.75 6.75 10.5 10.5M17.25 6.75 6.75 17.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <form x-on:submit.prevent="submitSale" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                    <div class="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain bg-white p-2.5 dark:bg-gray-950/30 sm:p-4 lg:p-4">
                    <div class="mb-3 grid min-w-0 grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="flex min-w-0 items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
                            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5.75 20.25h8.5M7 20V5.75C7 4.784 7.784 4 8.75 4h4.5c.966 0 1.75.784 1.75 1.75V20M8.75 8h4.5M15 8.5h1.25c.966 0 1.75.784 1.75 1.75v5.25a1.5 1.5 0 0 0 3 0v-4.25c0-.828-.672-1.5-1.5-1.5H18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Pump</p>
                                <select
                                    x-show="sale.id"
                                    x-model.number="sale.pump_id"
                                    x-on:change="changeSalePump($event.target.value)"
                                    class="mt-0.5 h-8 min-w-0 max-w-full rounded-lg border border-gray-300 bg-white px-2 pr-7 text-sm font-semibold text-gray-800 shadow-theme-xs focus:border-brand-500 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                    aria-label="Change transaction pump"
                                    required>
                                    @foreach ($pumps as $pump)
                                        <option value="{{ $pump['id'] }}">{{ $pump['label'] }} / {{ $pump['fuel_type'] }}</option>
                                    @endforeach
                                </select>
                                <p x-show="!sale.id" class="mt-0.5 truncate text-sm font-semibold text-gray-800 dark:text-white/90">
                                    <span x-text="sale.pumpLabel || 'Pump'"></span>
                                    <span class="text-gray-400">/</span>
                                    <span x-text="sale.fuel_type || 'Fuel'"></span>
                                </p>
                            </div>
                        </div>
                        <div class="flex min-w-0 items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
                            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.75 20.25a7.25 7.25 0 0 1 14.5 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Customer</p>
                                <p class="mt-0.5 truncate text-sm font-semibold text-success-700 dark:text-success-400" x-text="sale.mode === 'credit' ? (selectedCreditCustomer?.label || 'Select customer') : (pricingCustomer()?.label || sale.walkin_name || 'Walk-in customer')"></p>
                            </div>
                        </div>
                        <div class="flex min-w-0 items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
                            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 3.75s5.25 5.25 5.25 9.5a5.25 5.25 0 1 1-10.5 0c0-4.25 5.25-9.5 5.25-9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.75 13.5a2.25 2.25 0 0 0 2.25 2.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Liters</p>
                                <p class="sale-modal-digital mt-0.5 truncate text-base font-bold text-success-700 dark:text-success-400" x-text="liters().toFixed(2) + ' L'"></p>
                            </div>
                        </div>
                        <div class="flex min-w-0 items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
                            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4.75 7.75A2.75 2.75 0 0 1 7.5 5h9a2.75 2.75 0 0 1 2.75 2.75v8.5A2.75 2.75 0 0 1 16.5 19h-9a2.75 2.75 0 0 1-2.75-2.75v-8.5ZM4.75 9h14.5M8 15h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Charge Total</p>
                                <p class="sale-modal-digital mt-0.5 truncate text-base font-bold text-success-700 dark:text-success-400" x-text="money(totalAmount())"></p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 grid min-w-0 grid-cols-2 gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">
                        <button type="button" x-on:click="setSaleMode('credit')" class="inline-flex min-h-10 min-w-0 items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition sm:px-4" :class="saleModeButtonClass('credit')">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.75 20.25a7.25 7.25 0 0 1 14.5 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="truncate">Credit Customer</span>
                        </button>
                        <button type="button" x-on:click="setSaleMode('walkin')" class="inline-flex min-h-10 min-w-0 items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm font-semibold transition sm:px-4" :class="saleModeButtonClass('walkin')">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M10.5 5.25h3M9.75 8.25h4.5M10.25 21l.75-5.5-2-3.5 1.5-3.75M13.75 21 13 15.5l2-3.5-1.5-3.75" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="truncate">Walk-In Customer</span>
                        </button>
                    </div>

                    <div class="grid min-w-0 grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.55fr)_minmax(28rem,0.95fr)] xl:gap-4">
                        <div class="min-w-0 space-y-3 rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                            <div x-show="sale.mode === 'credit'" x-cloak>
                                <div class="relative min-w-0" x-on:click.outside="saleCreditCustomerDropdownOpen = false">
                                    <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Credit Account</span>
                                    <div class="relative mt-1.5">
                                        <span class="pointer-events-none absolute left-3 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center text-success-700 dark:text-success-400">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.75 20.25a7.25 7.25 0 0 1 14.5 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <input
                                            type="search"
                                            x-model="saleCreditCustomerSearch"
                                            x-on:focus="saleCreditCustomerDropdownOpen = true"
                                            x-on:input="handleSaleCustomerInput('credit')"
                                            x-on:keydown.escape.prevent="saleCreditCustomerDropdownOpen = false"
                                            class="sale-modal-control {{ $inputClass }} !rounded-xl pl-11 pr-11"
                                            placeholder="Type company or customer name"
                                            autocomplete="off"
                                            :required="sale.mode === 'credit'">
                                        <button
                                            type="button"
                                            x-show="saleCreditCustomerSearch"
                                            x-on:click="clearSaleCustomer('credit')"
                                            class="absolute right-3 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/[0.05] dark:hover:text-gray-200"
                                            aria-label="Clear credit customer search">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        </button>
                                    </div>
                                    <div
                                        x-show="saleCreditCustomerDropdownOpen"
                                        x-transition.origin.top
                                        class="absolute left-0 right-0 z-30 mt-2 max-h-64 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                                        <template x-for="customer in filteredSaleCustomers('credit')" :key="customer.id">
                                            <button
                                                type="button"
                                                x-on:click="selectSaleCustomer(customer, 'credit')"
                                                class="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                                                :class="String(sale.customer_id) === String(customer.id) ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'text-gray-700 dark:text-gray-300'">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="customer.business_name || customer.label"></span>
                                                    <span class="mt-0.5 block truncate text-theme-xs text-gray-500 dark:text-gray-400" x-text="customerSubtext(customer)"></span>
                                                </span>
                                                <span x-show="String(sale.customer_id) === String(customer.id)" class="mt-0.5 text-theme-xs font-medium text-brand-600 dark:text-brand-300">Selected</span>
                                            </button>
                                        </template>
                                        <p x-show="filteredSaleCustomers('credit').length === 0" class="px-3 py-4 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                            No customers match that search.
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-2 text-xs text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300" x-show="selectedCreditCustomer">
                                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                                        <span>Balance: <strong x-text="money(selectedCreditCustomer?.balance || 0)"></strong></span>
                                        <span>Limit: <strong x-text="money(selectedCreditCustomer?.limit || 0)"></strong></span>
                                        <span>Remaining: <strong x-text="money(creditRemaining())"></strong></span>
                                    </div>
                                </div>
                            </div>

                            <div x-show="sale.mode === 'walkin'" x-cloak class="space-y-3">
                                <div class="relative min-w-0" x-on:click.outside="saleWalkinCustomerDropdownOpen = false">
                                    <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Registered Customer</span>
                                    <div class="relative mt-1.5">
                                        <span class="pointer-events-none absolute left-3 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center text-success-700 dark:text-success-400">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.75 20.25a7.25 7.25 0 0 1 14.5 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <input
                                            type="search"
                                            x-model="saleWalkinCustomerSearch"
                                            x-on:focus="saleWalkinCustomerDropdownOpen = true"
                                            x-on:input="handleSaleCustomerInput('walkin')"
                                            x-on:keydown.escape.prevent="saleWalkinCustomerDropdownOpen = false"
                                            class="sale-modal-control {{ $inputClass }} !rounded-xl pl-11 pr-11"
                                            placeholder="Type company or customer name"
                                            autocomplete="off">
                                        <button
                                            type="button"
                                            x-show="saleWalkinCustomerSearch"
                                            x-on:click="clearSaleCustomer('walkin')"
                                            class="absolute right-3 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/[0.05] dark:hover:text-gray-200"
                                            aria-label="Clear registered customer search">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                        </button>
                                    </div>
                                    <div
                                        x-show="saleWalkinCustomerDropdownOpen"
                                        x-transition.origin.top
                                        class="absolute left-0 right-0 z-30 mt-2 max-h-64 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                                        <template x-for="customer in filteredSaleCustomers('walkin')" :key="customer.id">
                                            <button
                                                type="button"
                                                x-on:click="selectSaleCustomer(customer, 'walkin')"
                                                class="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                                                :class="String(sale.walkin_customer_id) === String(customer.id) ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'text-gray-700 dark:text-gray-300'">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="customer.business_name || customer.label"></span>
                                                    <span class="mt-0.5 block truncate text-theme-xs text-gray-500 dark:text-gray-400" x-text="customerSubtext(customer)"></span>
                                                </span>
                                                <span x-show="String(sale.walkin_customer_id) === String(customer.id)" class="mt-0.5 text-theme-xs font-medium text-brand-600 dark:text-brand-300">Selected</span>
                                            </button>
                                        </template>
                                        <p x-show="filteredSaleCustomers('walkin').length === 0" class="px-3 py-4 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                            No customers match that search.
                                        </p>
                                    </div>
                                </div>
                                <div class="grid min-w-0 grid-cols-1 gap-2.5 sm:grid-cols-2">
                                    <label class="block min-w-0 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                        <span>Customer Name</span>
                                        <input type="text" x-model="sale.walkin_name" x-on:input="touchSale" class="sale-modal-control mt-1.5 {{ $inputClass }} !rounded-xl" placeholder="Walk-in customer">
                                    </label>
                                    <label class="block min-w-0 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                        <span>Plate Number</span>
                                        <input type="text" x-model="sale.plate_no" class="sale-modal-control mt-1.5 {{ $inputClass }} !rounded-xl" placeholder="ABC-1234">
                                    </label>
                                </div>
                            </div>

                            <div class="grid min-w-0 grid-cols-1 gap-2.5 sm:grid-cols-2">
                                <label class="block min-w-0 text-xs font-semibold uppercase text-success-700 dark:text-success-400">
                                    <span>Liters Dispensed</span>
                                    <div class="relative mt-1.5">
                                        <span class="pointer-events-none absolute left-3 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center text-success-700 dark:text-success-400">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 3.75s5.25 5.25 5.25 9.5a5.25 5.25 0 1 1-10.5 0c0-4.25 5.25-9.5 5.25-9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <input type="number" x-model.number="sale.liters" x-on:input="touchSale" min="0.1" step="0.01" class="sale-modal-control sale-modal-entry-control sale-modal-digital {{ $inputClass }} !rounded-xl pl-11 pr-10 text-right font-bold" placeholder="0.00" required>
                                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-500 dark:text-gray-400">L</span>
                                    </div>
                                </label>
                                <label class="block min-w-0 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                    <span>OR Number</span>
                                    <div class="relative mt-1.5">
                                        <span class="pointer-events-none absolute left-3 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center text-success-700 dark:text-success-400">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M8 4.75h8A1.25 1.25 0 0 1 17.25 6v13.25L15 18l-2 1.25-2-1.25-2 1.25L6.75 18V6A1.25 1.25 0 0 1 8 4.75ZM9.5 9h5M9.5 12h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <input type="text" x-model="sale.or_number" class="sale-modal-control sale-modal-entry-control {{ $inputClass }} !rounded-xl pl-11" placeholder="Optional">
                                    </div>
                                </label>
                            </div>

                            <div class="grid min-w-0 grid-cols-1 gap-2.5 sm:grid-cols-2">
                                <label class="block min-w-0 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                    <span>Manual Discount</span>
                                    <div class="mt-1.5 flex min-w-0 rounded-xl shadow-theme-xs">
                                        <span class="sale-modal-affix sale-modal-entry-affix inline-flex shrink-0 items-center rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">&#8369;</span>
                                        <input type="number" x-model.number="sale.manual_discount" x-on:input="touchSale" min="0" step="0.01" class="sale-modal-control sale-modal-entry-control sale-modal-digital min-w-0 w-full rounded-r-xl border border-gray-300 bg-transparent px-3 py-2 text-right font-semibold text-gray-800 focus:border-success-400 focus:outline-hidden focus:ring-3 focus:ring-success-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-success-500" placeholder="0.00">
                                    </div>
                                </label>
                                <label class="block min-w-0 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                    <span>Manual Excess</span>
                                    <div class="mt-1.5 flex min-w-0 rounded-xl shadow-theme-xs">
                                        <span class="sale-modal-affix sale-modal-entry-affix inline-flex shrink-0 items-center rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">&#8369;</span>
                                        <input type="number" x-model.number="sale.manual_excess" x-on:input="touchSale" min="0" step="0.01" class="sale-modal-control sale-modal-entry-control sale-modal-digital min-w-0 w-full rounded-r-xl border border-gray-300 bg-transparent px-3 py-2 text-right font-semibold text-gray-800 focus:border-success-400 focus:outline-hidden focus:ring-3 focus:ring-success-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-success-500" placeholder="0.00">
                                    </div>
                                </label>
                            </div>

                            <div x-show="creditWarning" x-cloak class="rounded-lg border border-error-200 bg-error-50 p-2.5 text-sm font-medium text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300" x-text="creditWarning"></div>
                        </div>

                        <div class="min-w-0 rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-success-700 dark:text-success-400">Pricing Details</p>

                            <div class="grid min-w-0 grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-2">
                                <div class="flex min-w-0 items-start gap-2.5 rounded-xl border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-white text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4.75 11.25 12 18.5l7.25-7.25-7.25-7.25H4.75v7.25Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.75 8.5h.01" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Standard Price</p>
                                        <p class="sale-modal-digital mt-1.5 truncate text-lg font-bold text-gray-900 dark:text-white" x-text="money(standardPrice()) + ' / L'"></p>
                                    </div>
                                </div>
                                <div class="flex min-w-0 items-start gap-2.5 rounded-xl border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-white text-success-700 dark:bg-gray-900 dark:text-success-400">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m7.75 12.25 2.75 2.75 5.75-6.25" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.7"/>
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">Applied Price</p>
                                        <p class="sale-modal-digital mt-1.5 truncate text-lg font-bold text-gray-900 dark:text-white" x-text="money(appliedPrice()) + ' / L'"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-xs font-bold uppercase tracking-wide text-success-700 dark:text-success-400">Summary</p>
                                <div class="mt-3 space-y-2 text-sm">
                                    <div class="flex min-w-0 items-center justify-between gap-3">
                                        <span class="text-gray-600 dark:text-gray-300">
                                            Subtotal (<span x-text="liters().toFixed(2) + ' L'"></span>)
                                        </span>
                                        <span class="sale-modal-digital min-w-0 truncate font-semibold text-gray-800 dark:text-white/90" x-text="money(subtotalBeforeManual())"></span>
                                    </div>
                                    <div class="flex min-w-0 items-center justify-between gap-3 text-error-600 dark:text-error-400">
                                        <span>Manual Discount</span>
                                        <span class="sale-modal-digital min-w-0 truncate font-semibold" x-text="'- ' + money(totalDiscount())"></span>
                                    </div>
                                    <div class="flex min-w-0 items-center justify-between gap-3 text-success-700 dark:text-success-400">
                                        <span>Manual Excess</span>
                                        <span class="sale-modal-digital min-w-0 truncate font-semibold" x-text="'+ ' + money(totalExcess())"></span>
                                    </div>
                                </div>

                                <div class="my-3 border-t border-dashed border-gray-300 dark:border-gray-700"></div>

                                <div class="text-center">
                                    <p class="truncate text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-300" x-text="sale.mode === 'credit' ? 'Total Amount to Charge' : 'Total Amount Collected'"></p>
                                    <p class="sale-modal-digital mt-2 truncate text-3xl font-bold text-success-700 dark:text-success-400" x-text="money(totalAmount())"></p>
                                </div>

                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="sticky bottom-0 z-10 flex shrink-0 flex-col gap-2 border-t border-gray-200 bg-white/95 p-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                        <div class="inline-flex min-w-0 items-center gap-2 rounded-lg bg-success-50 px-3 py-1.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400 sm:max-w-lg">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 3.75 4.75 11.5h4L7.5 20.25l7.75-9h-4L12 3.75Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="truncate">Tip: Double-check the liters and customer details before recording.</span>
                        </div>
                        <div class="flex shrink-0 flex-col-reverse gap-2 sm:flex-row sm:items-center">
                            <button type="button" x-on:click="closeSaleModal" class="{{ $secondaryButtonClass }} !h-10 rounded-lg px-4 !text-sm">Cancel</button>
                            <button type="submit" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg !bg-success-600 px-5 py-2 text-sm font-semibold text-white shadow-theme-xs transition hover:!bg-success-700 disabled:cursor-not-allowed disabled:opacity-60" :class="saleSubmitButtonClass()" :disabled="savingSale || !!creditWarning">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 20.25h14A1.25 1.25 0 0 0 20.25 19V7.75L16.5 4H5A1.25 1.25 0 0 0 3.75 5.25V19A1.25 1.25 0 0 0 5 20.25ZM7.75 20v-6.25h8.5V20M7.75 4v5h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span x-text="savingSale ? 'Saving...' : (sale.id ? 'Update Transaction' : 'Record Transaction')"></span>
                            </button>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </template>

        <template x-teleport="body">
            <div x-show="paymentModalOpen" x-cloak class="fixed inset-0 z-[100010] grid h-dvh place-items-center overflow-hidden bg-gray-950/75 p-2 backdrop-blur-md sm:p-4">
                <div x-on:click.outside="if (!feedbackActive()) closePaymentModal()" class="flex max-h-[calc(100dvh-1rem)] w-[calc(100vw-1rem)] min-w-0 max-w-6xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 sm:max-h-[calc(100dvh-2rem)] sm:w-full sm:rounded-2xl">
                    <div class="h-1 bg-success-500"></div>

                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900 sm:px-5">
                        <div class="min-w-0">
                            <p class="{{ $labelClass }}">Account Collection</p>
                            <h2 class="mt-0.5 text-lg font-semibold text-gray-800 dark:text-white/90">Receive Payment</h2>
                        </div>
                        <button type="button" x-on:click="closePaymentModal" class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-white" aria-label="Close payment modal">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m6.75 6.75 10.5 10.5M17.25 6.75 6.75 17.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <form x-on:submit.prevent="submitPayment" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                        <div class="min-h-0 min-w-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain bg-gray-50/60 p-3 dark:bg-gray-950/30 sm:p-4 lg:p-5">
                            <div class="grid min-w-0 grid-cols-1 gap-4 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]">
                                <div class="min-w-0 space-y-3">
                                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="{{ $labelClass }}">1. Select Credit Customer</p>
                                                <p class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">Choose the account receiving the payment.</p>
                                            </div>
                                        </div>
                                        <div class="relative mt-3" x-on:click.outside="paymentCustomerDropdownOpen = false">
                                            <span class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Credit Customer</span>
                                            <div class="relative mt-1.5">
                                                <input
                                                    type="search"
                                                    x-model="paymentCustomerSearch"
                                                    x-on:focus="paymentCustomerDropdownOpen = true"
                                                    x-on:input="handlePaymentCustomerInput()"
                                                    x-on:keydown.escape.prevent="paymentCustomerDropdownOpen = false"
                                                    class="{{ $inputClass }} pr-11"
                                                    placeholder="Type company or customer name"
                                                    autocomplete="off"
                                                    required>
                                                <button
                                                    type="button"
                                                    x-show="paymentCustomerSearch"
                                                    x-on:click="clearPaymentCustomer()"
                                                    class="absolute right-3 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/[0.05] dark:hover:text-gray-200"
                                                    aria-label="Clear customer search">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                                </button>
                                            </div>
                                            <div
                                                x-show="paymentCustomerDropdownOpen"
                                                x-transition.origin.top
                                                class="absolute left-0 right-0 z-30 mt-2 max-h-64 overflow-y-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                                                <template x-for="customer in filteredPaymentCustomers()" :key="customer.id">
                                                    <button
                                                        type="button"
                                                        x-on:click="selectPaymentCustomer(customer)"
                                                        class="flex w-full items-start justify-between gap-3 rounded-lg px-3 py-2 text-left transition hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                                                        :class="String(payment.customer_id) === String(customer.id) ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'text-gray-700 dark:text-gray-300'">
                                                        <span class="min-w-0">
                                                            <span class="block truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="customer.business_name || customer.label"></span>
                                                            <span class="mt-0.5 block truncate text-theme-xs text-gray-500 dark:text-gray-400" x-text="customerSubtext(customer)"></span>
                                                        </span>
                                                        <span x-show="String(payment.customer_id) === String(customer.id)" class="mt-0.5 text-theme-xs font-medium text-brand-600 dark:text-brand-300">Selected</span>
                                                    </button>
                                                </template>
                                                <p x-show="filteredPaymentCustomers().length === 0" class="px-3 py-4 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                                    No customers match that search.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                                        <div class="flex min-w-0 items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="{{ $labelClass }}">2. Customer Balance</p>
                                                <p class="mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedPaymentCustomer?.label || 'No customer selected'"></p>
                                            </div>
                                            <span class="shrink-0 rounded-full border border-brand-100 bg-brand-50 px-2.5 py-1 text-theme-xs font-medium text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">Credit</span>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <div class="min-w-0 rounded-lg border border-error-100 bg-error-50/70 p-3 dark:border-error-500/25 dark:bg-error-500/10">
                                                <p class="text-theme-xs font-medium uppercase text-error-600 dark:text-error-400">Current Balance</p>
                                                <p class="sale-modal-digital mt-1 truncate text-lg font-bold text-error-600 dark:text-error-400" x-text="money(selectedPaymentCustomer?.balance || 0)"></p>
                                            </div>
                                            <div class="min-w-0 rounded-lg border border-brand-100 bg-brand-50/70 p-3 dark:border-brand-500/25 dark:bg-brand-500/10">
                                                <p class="text-theme-xs font-medium uppercase text-brand-600 dark:text-brand-300">Credit Limit</p>
                                                <p class="sale-modal-digital mt-1 truncate text-lg font-bold text-brand-700 dark:text-brand-300" x-text="money(selectedPaymentCustomer?.limit || 0)"></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                                        <p class="{{ $labelClass }}">3. Payment Details</p>
                                        <div class="mt-3 space-y-3">
                                            <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                                <span>Amount Paid</span>
                                                <div class="mt-1.5 flex rounded-lg shadow-theme-xs ring-1 ring-success-500/10">
                                                    <span class="inline-flex h-12 items-center rounded-l-lg border border-r-0 border-success-200 bg-success-50 px-3 text-theme-sm font-semibold text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400">&#8369;</span>
                                                    <input x-ref="paymentAmountInput" type="number" x-model.number="payment.amount" min="1" step="0.01" class="sale-modal-digital h-12 min-w-0 w-full rounded-r-lg border border-success-200 bg-white px-4 py-2.5 text-right text-lg font-bold text-success-700 placeholder:text-gray-400 focus:border-success-400 focus:outline-hidden focus:ring-3 focus:ring-success-500/20 dark:border-success-500/30 dark:bg-gray-900 dark:text-success-400 dark:placeholder:text-white/30" placeholder="0.00" required>
                                                </div>
                                            </label>

                                            <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                                                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                                    <span>Method</span>
                                                    <select x-model="payment.payment_method" class="mt-1.5 {{ $selectClass }}" required>
                                                        <option value="Cash">Cash</option>
                                                        <option value="Check">Check</option>
                                                        <option value="Bank Transfer">Bank Transfer</option>
                                                        <option value="GCash">GCash / E-Wallet</option>
                                                    </select>
                                                </label>
                                                <label class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                                    <span>OR Number</span>
                                                    <input type="text" x-model="payment.or_number" class="mt-1.5 {{ $inputClass }}" placeholder="Optional">
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="min-w-0 space-y-3">
                                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                                        <div class="flex min-w-0 items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="{{ $labelClass }}">4. Invoice Link</p>
                                                <h3 class="mt-0.5 text-theme-sm font-semibold text-gray-800 dark:text-white/90">Optional invoice selection</h3>
                                                <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400">Keep manual payment selected when this is not tied to a specific invoice.</p>
                                            </div>
                                            <span x-show="loadingInvoices" class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">Loading...</span>
                                        </div>

                                        <select x-model="payment.invoice_id" x-on:change="selectInvoice" class="mt-3 {{ $selectClass }}">
                                            <option value="">Manual payment</option>
                                            <template x-for="invoice in invoices" :key="invoice.id">
                                                <option :value="invoice.id" x-text="invoiceLabel(invoice)"></option>
                                            </template>
                                        </select>

                                        <div class="mt-3 grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-3">
                                            <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                                <p class="{{ $labelClass }}">Invoice</p>
                                                <p class="mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedInvoice?.invoice_number || 'Manual'"></p>
                                            </div>
                                            <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                                <p class="{{ $labelClass }}">Status</p>
                                                <p class="mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedInvoice?.status || 'Not linked'"></p>
                                            </div>
                                            <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                                <p class="{{ $labelClass }}">Remaining</p>
                                                <p class="sale-modal-digital mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedInvoice ? money(invoiceRemaining(selectedInvoice)) : money(selectedPaymentCustomer?.balance || 0)"></p>
                                            </div>
                                        </div>

                                        <div class="mt-3 grid min-w-0 grid-cols-2 gap-2">
                                            <button type="button" x-on:click="setPaymentType('partial')" class="{{ $secondaryButtonClass }} !h-10 !px-3 !text-xs" :disabled="!selectedPaymentCustomer">Partial Payment</button>
                                            <button type="button" x-on:click="setPaymentType('full')" class="{{ $successButtonClass }} !h-10 !px-3" :disabled="!selectedPaymentCustomer">Full Payment</button>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                                        <p class="{{ $labelClass }}">5. Payment Summary</p>
                                        <div class="mt-3 grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-3">
                                            <div class="min-w-0 rounded-lg border border-error-100 bg-error-50/70 p-3 dark:border-error-500/25 dark:bg-error-500/10">
                                                <p class="text-theme-xs font-medium uppercase text-error-600 dark:text-error-400">Balance</p>
                                                <p class="sale-modal-digital mt-1 truncate text-base font-bold text-error-600 dark:text-error-400" x-text="money(selectedPaymentCustomer?.balance || 0)"></p>
                                            </div>
                                            <div class="min-w-0 rounded-lg border border-success-100 bg-success-50/70 p-3 dark:border-success-500/25 dark:bg-success-500/10">
                                                <p class="text-theme-xs font-medium uppercase text-success-600 dark:text-success-400">Receiving</p>
                                                <p class="sale-modal-digital mt-1 truncate text-base font-bold text-success-700 dark:text-success-400" x-text="money(paymentAmountValue())"></p>
                                            </div>
                                            <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                                <p class="{{ $labelClass }}">After Balance</p>
                                                <p class="sale-modal-digital mt-1 truncate text-base font-bold" :class="paymentAfterBalanceClass()" x-text="money(paymentAfterBalance())"></p>
                                            </div>
                                        </div>

                                        <div class="mt-3 grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-2">
                                            <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                                <p class="{{ $labelClass }}">Payment Type</p>
                                                <p class="mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedInvoice ? 'Invoice payment' : 'Manual payment'"></p>
                                            </div>
                                            <div class="min-w-0 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                                <p class="{{ $labelClass }}">Method</p>
                                                <p class="mt-1 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="payment.payment_method || 'Cash'"></p>
                                            </div>
                                        </div>

                                        <div x-show="paymentHasOverage()" x-cloak class="mt-3 rounded-lg border border-warning-200 bg-warning-50 p-3 text-theme-sm font-medium text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                                            Payment is higher than the current customer balance. Review before logging.
                                        </div>
                                        <div x-show="paymentAdvanceMessage" x-cloak class="mt-3 rounded-lg border border-warning-200 bg-warning-50 p-3 text-theme-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400" x-text="paymentAdvanceMessage"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 border-t border-gray-100 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900 sm:px-5">
                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <button type="button" x-on:click="closePaymentModal" class="{{ $secondaryButtonClass }} !h-10 px-4 !text-sm">Cancel</button>
                                <button type="submit" class="{{ $primaryButtonClass }} !h-10 px-4 !text-sm" :disabled="!canSubmitPayment()">
                                    <span x-text="savingPayment ? 'Saving...' : 'Log Pending Payment for Review'"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </template>

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
                style="z-index: 100250;">
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
                style="z-index: 100260;">
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

        <template x-teleport="body">
            <div
                x-show="passwordDialog.open"
                x-cloak
                x-on:keydown.escape.window="resolvePassword(false)"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4"
                style="z-index: 100270;">
                <div class="fixed inset-0 bg-gray-950/85 backdrop-blur-md" x-on:click="resolvePassword(false)"></div>
                <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
                    <form x-on:submit.prevent="resolvePassword(true)" class="p-6">
                        <div class="flex items-start gap-4">
                            <span
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                                :class="passwordDialog.tone === 'danger'
                                    ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'
                                    : 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8.75 10.5V8a3.25 3.25 0 0 1 6.5 0v2.5M7.75 10.5h8.5c.966 0 1.75.784 1.75 1.75v5.5c0 .966-.784 1.75-1.75 1.75h-8.5A1.75 1.75 0 0 1 6 17.75v-5.5c0-.966.784-1.75 1.75-1.75Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="passwordDialog.title"></h2>
                                <p class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="passwordDialog.message"></p>
                                <p x-show="passwordDialog.details" x-cloak class="mt-2 text-theme-sm leading-6 text-gray-600 dark:text-gray-400" x-text="passwordDialog.details"></p>
                            </div>
                        </div>

                        <div class="mt-5">
                            <label for="semi-admin-password-input" class="block text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Semi-admin Password</label>
                            <div class="relative mt-1.5">
                                <input
                                    id="semi-admin-password-input"
                                    :type="passwordDialog.showPassword ? 'text' : 'password'"
                                    x-ref="passwordInput"
                                    x-model="passwordDialog.password"
                                    class="{{ $inputClass }} pr-12"
                                    autocomplete="current-password">
                                <button
                                    type="button"
                                    x-on:mousedown.prevent
                                    x-on:click="passwordDialog.showPassword = !passwordDialog.showPassword"
                                    class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-lg text-gray-500 transition hover:text-gray-700 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:text-gray-400 dark:hover:text-gray-200"
                                    :aria-label="passwordDialog.showPassword ? 'Hide semi-admin password' : 'Show semi-admin password'"
                                    :aria-pressed="passwordDialog.showPassword.toString()"
                                    :title="passwordDialog.showPassword ? 'Hide password' : 'Show password'">
                                    <svg x-show="!passwordDialog.showPassword" class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M2.75 12s3.25-6.25 9.25-6.25S21.25 12 21.25 12 18 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 14.75a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <svg x-show="passwordDialog.showPassword" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M4.5 4.5 19.5 19.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M9.9 5.96A8.8 8.8 0 0 1 12 5.75c6 0 9.25 6.25 9.25 6.25a15.8 15.8 0 0 1-2.48 3.36M6.23 7.58A15.6 15.6 0 0 0 2.75 12S6 18.25 12 18.25c1.42 0 2.69-.35 3.81-.89" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M10.24 10.24a2.75 2.75 0 0 0 3.52 3.52" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <p x-show="passwordDialog.error" x-cloak class="mt-2 text-theme-sm text-error-600 dark:text-error-400" x-text="passwordDialog.error"></p>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" x-on:click="resolvePassword(false)" class="{{ $secondaryButtonClass }}" x-text="passwordDialog.cancelText"></button>
                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition disabled:cursor-not-allowed disabled:opacity-60"
                                :class="passwordDialog.tone === 'danger' ? 'bg-error-500 hover:bg-error-600' : 'bg-brand-500 hover:bg-brand-600'"
                                x-text="passwordDialog.confirmText"></button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
@endsection

@push('scripts')
    <script>
        function staffPumpModule(config) {
            return {
                pumps: config.pumps || [],
                customers: config.customers || [],
                prices: config.prices || {},
                editableSales: config.editableSales || {},
                nextSalesInvoiceNumber: Number(config.nextSalesInvoiceNumber || 1),
                customersById: {},
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
                passwordDialog: {
                    open: false,
                    title: '',
                    message: '',
                    details: '',
                    confirmText: 'Continue',
                    cancelText: 'Cancel',
                    tone: 'brand',
                    password: '',
                    showPassword: false,
                    error: '',
                    resolver: null
                },
                selectedIds: [],
                selectedCustomerKey: '',
                selectedCustomerLabel: '',
                saleModalOpen: false,
                paymentModalOpen: false,
                salesInvoiceModalOpen: false,
                savingSale: false,
                savingPayment: false,
                savingSalesInvoice: false,
                loadingInvoices: false,
                invoices: [],
                selectedInvoice: null,
                sale: {},
                payment: {},
                salesInvoice: {},
                saleCreditCustomerSearch: '',
                saleCreditCustomerDropdownOpen: false,
                saleWalkinCustomerSearch: '',
                saleWalkinCustomerDropdownOpen: false,
                paymentCustomerSearch: '',
                paymentCustomerDropdownOpen: false,
                selectedCreditCustomer: null,
                selectedPaymentCustomer: null,
                creditWarning: '',
                paymentAdvanceMessage: '',
                syncingSaleCustomers: false,
                feedbackClosing: false,
                init() {
                    this.customersById = Object.fromEntries(this.customers.map(customer => [String(customer.id), customer]));
                    this.resetSale();
                    this.resetPayment();
                    this.resetSalesInvoice();
                    this.restoreNotice();
                    this.$watch('sale.customer_id', () => this.touchSale());
                    this.$watch('sale.walkin_customer_id', () => this.touchSale());
                    this.$watch('sale.liters', () => this.touchSale());
                    this.$watch('sale.manual_discount', () => this.touchSale());
                    this.$watch('sale.manual_excess', () => this.touchSale());
                    this.$watch('payment.customer_id', () => this.syncPaymentCustomer());
                    this.$watch('payment.amount', () => this.syncPaymentAmount());
                },
                feedbackActive() {
                    return this.feedbackClosing || this.confirmation.open || this.messageDialog.open || this.passwordDialog.open;
                },
                normalizeCustomerSearch(value) {
                    return String(value || '').toLowerCase().trim();
                },
                customerSubtext(customer) {
                    const parts = [];
                    const label = this.normalizeCustomerSearch(customer.label);
                    const businessName = this.normalizeCustomerSearch(customer.business_name);
                    const ownerName = this.normalizeCustomerSearch(customer.owner_name);

                    if (customer.owner_name && ownerName !== businessName && ownerName !== label) {
                        parts.push(customer.owner_name);
                    }

                    if (customer.contact_number) {
                        parts.push(customer.contact_number);
                    }

                    return parts.length ? parts.join(' - ') : `Customer #${customer.id}`;
                },
                customerMatchesSearch(customer, query) {
                    const terms = this.normalizeCustomerSearch(query).split(/\s+/).filter(Boolean);

                    if (!terms.length) {
                        return true;
                    }

                    const haystack = this.normalizeCustomerSearch(customer.search || customer.label);

                    return terms.every((term) => haystack.includes(term));
                },
                filteredPaymentCustomers() {
                    return this.customers
                        .filter((customer) => this.customerMatchesSearch(customer, this.paymentCustomerSearch))
                        .slice(0, 50);
                },
                saleCustomerSearchValue(mode) {
                    return mode === 'credit' ? this.saleCreditCustomerSearch : this.saleWalkinCustomerSearch;
                },
                filteredSaleCustomers(mode) {
                    return this.customers
                        .filter((customer) => this.customerMatchesSearch(customer, this.saleCustomerSearchValue(mode)))
                        .slice(0, 50);
                },
                exactCustomerMatch(query) {
                    const normalizedQuery = this.normalizeCustomerSearch(query);

                    if (!normalizedQuery) {
                        return null;
                    }

                    return this.customers.find((customer) => {
                        return [
                            customer.label,
                            customer.business_name,
                            customer.owner_name,
                        ].some((value) => this.normalizeCustomerSearch(value) === normalizedQuery);
                    }) || null;
                },
                blankSalesInvoiceItem() {
                    return {
                        item_type: '',
                        other_description: '',
                        quantity: '',
                        price: '',
                        amount: ''
                    };
                },
                resetSalesInvoice() {
                    this.salesInvoice = {
                        invoice_date: config.today || new Date().toISOString().slice(0, 10),
                        sold_to: '',
                        tin: '',
                        terms: '',
                        address: '',
                        discount_id_no: '',
                        signature: '',
                        si_number: String(this.nextSalesInvoiceNumber || 1),
                        authorized_representative: String(config.authorizedRepresentative || ''),
                        vat_exempt_sales: '',
                        zero_rated_sales: '',
                        sc_pwd_discount: '',
                        withholding_tax: '',
                        items: Array.from({ length: 7 }, () => this.blankSalesInvoiceItem())
                    };
                    this.savingSalesInvoice = false;
                },
                openSalesInvoiceModal() {
                    this.resetSalesInvoice();
                    this.salesInvoiceModalOpen = true;
                },
                closeSalesInvoiceModal() {
                    if (this.savingSalesInvoice) {
                        return;
                    }

                    this.salesInvoiceModalOpen = false;
                    this.resetSalesInvoice();
                },
                invoiceNumber(value, precision = 2) {
                    const number = Number(value || 0);

                    if (!Number.isFinite(number) || number < 0) {
                        return 0;
                    }

                    const multiplier = 10 ** precision;

                    return Math.round((number + Number.EPSILON) * multiplier) / multiplier;
                },
                invoiceTotalSales() {
                    return this.invoiceNumber((this.salesInvoice.items || []).reduce((total, item) => {
                        return total + this.invoiceNumber(item.amount);
                    }, 0));
                },
                invoiceLessVat() {
                    return this.invoiceNumber((this.invoiceTotalSales() / 1.12) * 0.12);
                },
                invoiceAmountNetOfVat() {
                    return this.invoiceNumber(this.invoiceTotalSales() - this.invoiceLessVat());
                },
                invoiceVatableSales() {
                    return this.invoiceAmountNetOfVat();
                },
                invoiceVatAmount() {
                    return this.invoiceLessVat();
                },
                invoiceAddVat() {
                    return this.invoiceLessVat();
                },
                invoiceTotalAmountDue() {
                    const amountBeforeWithholdingTax = this.invoiceAmountNetOfVat() + this.invoiceAddVat();
                    const withholdingTax = this.invoiceNumber(this.salesInvoice.withholding_tax);

                    return Math.round((amountBeforeWithholdingTax - withholdingTax + Number.EPSILON) * 100) / 100;
                },
                invoiceMoney(value) {
                    const number = Number(value || 0);
                    const amount = Number.isFinite(number) ? number : 0;

                    return amount.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                },
                invoiceItemHasInput(item) {
                    return ['item_type', 'other_description', 'quantity', 'price', 'amount'].some((field) => {
                        return item[field] !== null && item[field] !== undefined && String(item[field]).trim() !== '';
                    });
                },
                fillSalesInvoiceDefaults() {
                    const defaults = {
                        sold_to: 'No Name Inputted',
                        tin: 'No TIN Inputted',
                        terms: 'No Terms Inputted',
                        address: 'No Address Inputted',
                        discount_id_no: 'No ID Number Inputted',
                        signature: 'No Signature Inputted'
                    };

                    Object.entries(defaults).forEach(([field, fallback]) => {
                        this.salesInvoice[field] = String(this.salesInvoice[field] || '').trim() || fallback;
                    });

                    this.salesInvoice.invoice_date = this.salesInvoice.invoice_date || config.today || new Date().toISOString().slice(0, 10);
                    this.salesInvoice.sold_to = this.salesInvoice.sold_to.toUpperCase();
                    this.salesInvoice.address = this.salesInvoice.address.toUpperCase();

                    this.salesInvoice.items.forEach((item) => {
                        if (item.item_type === 'Others' && !String(item.other_description || '').trim()) {
                            item.other_description = 'No Item Description Inputted';
                        }
                    });
                },
                salesInvoicePayload() {
                    return {
                        invoice_date: this.salesInvoice.invoice_date,
                        sold_to: this.salesInvoice.sold_to,
                        tin: this.salesInvoice.tin,
                        terms: this.salesInvoice.terms,
                        address: this.salesInvoice.address,
                        discount_id_no: this.salesInvoice.discount_id_no,
                        signature: this.salesInvoice.signature,
                        vat_exempt_sales: this.invoiceNumber(this.salesInvoice.vat_exempt_sales),
                        zero_rated_sales: this.invoiceNumber(this.salesInvoice.zero_rated_sales),
                        sc_pwd_discount: this.invoiceNumber(this.salesInvoice.sc_pwd_discount),
                        withholding_tax: this.invoiceNumber(this.salesInvoice.withholding_tax),
                        items: this.salesInvoice.items
                            .filter((item) => this.invoiceItemHasInput(item))
                            .map((item) => ({
                                item_type: item.item_type || null,
                                other_description: item.other_description || null,
                                quantity: this.invoiceNumber(item.quantity, 3),
                                price: this.invoiceNumber(item.price),
                                amount: this.invoiceNumber(item.amount)
                            }))
                    };
                },
                async submitSalesInvoice(printAfterSave = false) {
                    if (this.savingSalesInvoice || this.confirmation.open) {
                        return;
                    }

                    const confirmed = await this.askConfirmation({
                        title: printAfterSave ? 'Save and Print Sales Invoice?' : 'Save Sales Invoice?',
                        message: printAfterSave
                            ? 'Are you sure you want to save and print this sales invoice?'
                            : 'Are you sure you want to submit this sales invoice?',
                        details: 'SI No. ' + this.salesInvoice.si_number + ' / Total Amount Due: ' + this.invoiceMoney(this.invoiceTotalAmountDue()),
                        confirmText: printAfterSave ? 'Yes, Save & Print' : 'Yes, Save Invoice',
                        cancelText: 'No',
                        tone: 'success'
                    });

                    if (!confirmed) {
                        return;
                    }

                    this.fillSalesInvoiceDefaults();
                    this.savingSalesInvoice = true;

                    try {
                        const response = await fetch(config.routes.salesInvoiceStore, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken
                            },
                            body: JSON.stringify(this.salesInvoicePayload())
                        });
                        let data = {};

                        try {
                            data = await response.json();
                        } catch (error) {
                            data = {};
                        }

                        if (!response.ok || !data.success) {
                            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                            this.showNotice('error', firstError || data.message || 'Unable to save the sales invoice.');
                            return;
                        }

                        const assignedSiNumber = Number(data.invoice?.si_number || this.salesInvoice.si_number || 0);

                        if (assignedSiNumber > 0) {
                            this.salesInvoice.si_number = String(assignedSiNumber);
                            this.nextSalesInvoiceNumber = assignedSiNumber + 1;
                        }

                        if (printAfterSave) {
                            await new Promise(resolve => this.$nextTick(resolve));
                            window.print();
                        }

                        this.salesInvoiceModalOpen = false;
                        this.resetSalesInvoice();
                        this.showToast(data.message || 'Sales invoice saved successfully.');
                    } catch (error) {
                        this.showNotice('error', 'An unexpected error occurred while saving the sales invoice.');
                    } finally {
                        this.savingSalesInvoice = false;
                    }
                },
                resetSale() {
                    this.sale = {
                        id: null,
                        pump_id: '',
                        pumpLabel: '',
                        fuel_type: '',
                        mode: 'credit',
                        customer_id: '',
                        walkin_customer_id: '',
                        walkin_name: '',
                        plate_no: '',
                        liters: '',
                        manual_discount: '',
                        manual_excess: '',
                        or_number: '',
                        standard_price: 0,
                        original_payment_type: '',
                        original_customer_id: '',
                        original_amount: 0,
                        original_price_applied: null,
                        original_fuel_type: ''
                    };
                    this.saleCreditCustomerSearch = '';
                    this.saleCreditCustomerDropdownOpen = false;
                    this.saleWalkinCustomerSearch = '';
                    this.saleWalkinCustomerDropdownOpen = false;
                    this.selectedCreditCustomer = null;
                    this.creditWarning = '';
                    this.savingSale = false;
                },
                resetPayment() {
                    this.payment = {
                        customer_id: '',
                        invoice_id: '',
                        amount: '',
                        payment_method: 'Cash',
                        or_number: ''
                    };
                    this.invoices = [];
                    this.selectedInvoice = null;
                    this.selectedPaymentCustomer = null;
                    this.paymentCustomerSearch = '';
                    this.paymentCustomerDropdownOpen = false;
                    this.paymentAdvanceMessage = '';
                    this.savingPayment = false;
                    this.loadingInvoices = false;
                },
                openSale(pump) {
                    this.resetSale();
                    this.sale.pump_id = pump.id;
                    this.sale.pumpLabel = pump.label;
                    this.sale.fuel_type = pump.fuel_type;
                    this.sale.standard_price = Number(pump.standard_price || 0);
                    this.saleModalOpen = true;
                    this.$nextTick(() => document.querySelector('[x-model\\.number="sale.liters"]')?.focus());
                },
                openEditSale(row) {
                    const pump = this.pumps.find(item => Number(item.id) === Number(row.pump_id)) || {};
                    this.resetSale();
                    this.sale.id = row.id;
                    this.sale.pump_id = row.pump_id;
                    this.sale.pumpLabel = pump.label || ('Pump ' + String(row.pump_id).padStart(2, '0'));
                    this.sale.fuel_type = row.fuel_type;
                    this.sale.standard_price = Number(pump.standard_price || this.priceForFuel(row.fuel_type));
                    this.sale.mode = row.payment_type === 'credit' ? 'credit' : 'walkin';
                    const registeredCustomerId = row.customer_id ? String(row.customer_id) : '';
                    const registeredCustomer = registeredCustomerId ? this.customersById[registeredCustomerId] : null;
                    this.sale.customer_id = registeredCustomerId;
                    this.sale.walkin_customer_id = registeredCustomerId;
                    this.saleCreditCustomerSearch = registeredCustomer?.label || '';
                    this.saleWalkinCustomerSearch = registeredCustomer?.label || '';
                    this.sale.walkin_name = row.payment_type !== 'credit' && !row.customer_id ? (row.customer_name || '') : '';
                    this.sale.plate_no = row.plate_no || '';
                    this.sale.liters = Number(row.liters || 0);
                    this.sale.manual_discount = Number(row.manual_discount || 0);
                    this.sale.manual_excess = Math.max(0, Number(row.surcharge || 0) - Math.max(0, (Number(row.price_applied || 0) - this.sale.standard_price) * Number(row.liters || 0)));
                    this.sale.or_number = row.or_number || '';
                    this.sale.original_payment_type = row.payment_type;
                    this.sale.original_customer_id = row.customer_id ? String(row.customer_id) : '';
                    this.sale.original_amount = Number(row.amount || 0);
                    this.sale.original_price_applied = Number(row.price_applied || 0);
                    this.sale.original_fuel_type = row.fuel_type || '';
                    this.touchSale();
                    this.saleModalOpen = true;
                    this.$nextTick(() => document.querySelector('[x-model\\.number="sale.liters"]')?.focus());
                },
                changeSalePump(pumpId = this.sale.pump_id) {
                    const pump = this.pumps.find(item => Number(item.id) === Number(pumpId));

                    if (!pump) {
                        this.sale.pumpLabel = '';
                        this.sale.fuel_type = '';
                        this.sale.standard_price = 0;
                        this.touchSale();
                        return;
                    }

                    this.sale.pump_id = Number(pump.id);
                    this.sale.pumpLabel = pump.label;
                    this.sale.fuel_type = pump.fuel_type;
                    this.sale.standard_price = Number(pump.standard_price || this.priceForFuel(pump.fuel_type));
                    this.touchSale();
                },
                closeSaleModal() {
                    this.saleModalOpen = false;
                    this.resetSale();
                },
                setSaleMode(mode) {
                    this.syncRegisteredSaleCustomer(this.sale.mode);
                    this.sale.mode = mode;
                    this.touchSale();
                },
                handleSaleCustomerInput(mode) {
                    const selectedCustomer = this.selectedSaleCustomer(mode);
                    const query = this.saleCustomerSearchValue(mode);

                    if (mode === 'credit') {
                        this.saleCreditCustomerDropdownOpen = true;
                    } else {
                        this.saleWalkinCustomerDropdownOpen = true;
                    }

                    if (
                        selectedCustomer
                        && this.normalizeCustomerSearch(query) === this.normalizeCustomerSearch(selectedCustomer.label)
                    ) {
                        return;
                    }

                    const field = mode === 'credit' ? 'customer_id' : 'walkin_customer_id';
                    this.sale[field] = '';
                    this.touchSale();

                    const exactMatch = this.exactCustomerMatch(query);

                    if (exactMatch) {
                        this.selectSaleCustomer(exactMatch, mode);
                    }
                },
                selectedSaleCustomer(mode) {
                    const customerId = mode === 'credit'
                        ? String(this.sale.customer_id || '')
                        : String(this.sale.walkin_customer_id || '');

                    return customerId ? this.customersById[customerId] : null;
                },
                selectSaleCustomer(customer, mode) {
                    const customerId = String(customer.id);

                    if (mode === 'credit') {
                        this.sale.customer_id = customerId;
                        this.saleCreditCustomerSearch = customer.label;
                        this.saleCreditCustomerDropdownOpen = false;
                    } else {
                        this.sale.walkin_customer_id = customerId;
                        this.saleWalkinCustomerSearch = customer.label;
                        this.saleWalkinCustomerDropdownOpen = false;
                    }

                    this.syncRegisteredSaleCustomer(mode);
                },
                clearSaleCustomer(mode) {
                    if (mode === 'credit') {
                        this.sale.customer_id = '';
                        this.saleCreditCustomerSearch = '';
                        this.saleCreditCustomerDropdownOpen = true;
                    } else {
                        this.sale.walkin_customer_id = '';
                        this.saleWalkinCustomerSearch = '';
                        this.saleWalkinCustomerDropdownOpen = true;
                    }

                    this.touchSale();
                },
                syncRegisteredSaleCustomer(sourceMode) {
                    if (this.syncingSaleCustomers) {
                        return;
                    }

                    const sourceField = sourceMode === 'credit' ? 'customer_id' : 'walkin_customer_id';
                    const targetField = sourceMode === 'credit' ? 'walkin_customer_id' : 'customer_id';
                    const customerId = String(this.sale[sourceField] || '');

                    if (!customerId || !this.customersById[customerId]) {
                        this.touchSale();
                        return;
                    }

                    const customer = this.customersById[customerId];
                    this.syncingSaleCustomers = true;
                    this.sale[targetField] = customerId;
                    if (sourceMode === 'credit') {
                        this.saleWalkinCustomerSearch = customer.label;
                    } else {
                        this.saleCreditCustomerSearch = customer.label;
                    }
                    this.syncingSaleCustomers = false;
                    this.touchSale();
                },
                touchSale() {
                    this.selectedCreditCustomer = this.sale.customer_id ? this.customersById[String(this.sale.customer_id)] : null;
                    const total = this.totalAmount();

                    if (this.sale.mode === 'credit' && this.selectedCreditCustomer) {
                        const projected = this.balanceForCreditLimit(this.selectedCreditCustomer) + total;
                        const limit = Number(this.selectedCreditCustomer.limit || 0);
                        this.creditWarning = projected > limit
                            ? 'Credit limit exceeded. Projected balance is ' + this.money(projected) + '.'
                            : '';
                    } else {
                        this.creditWarning = '';
                    }
                },
                priceForFuel(fuel) {
                    return Number(this.prices[fuel] || 0);
                },
                fuelKey(fuel) {
                    const value = String(fuel || '').toLowerCase();

                    if (value.includes('premium')) {
                        return 'premium';
                    }

                    if (value.includes('diesel')) {
                        return 'diesel';
                    }

                    if (value.includes('unleaded') || value.includes('regular')) {
                        return 'unleaded';
                    }

                    return 'default';
                },
                fuelTheme(fuel) {
                    const themes = {
                        premium: {
                            header: 'bg-error-600 dark:bg-error-700',
                            badge: 'bg-white/15 text-white ring-1 ring-inset ring-white/25'
                        },
                        unleaded: {
                            header: 'bg-success-600 dark:bg-success-700',
                            badge: 'bg-white/15 text-white ring-1 ring-inset ring-white/25'
                        },
                        diesel: {
                            header: 'bg-warning-600 dark:bg-warning-700',
                            badge: 'bg-white/15 text-white ring-1 ring-inset ring-white/25'
                        },
                        default: {
                            header: 'bg-brand-600 dark:bg-brand-700',
                            badge: 'bg-white/15 text-white ring-1 ring-inset ring-white/25'
                        }
                    };

                    return themes[this.fuelKey(fuel)] || themes.default;
                },
                saleModeButtonClass(mode) {
                    if (this.sale.mode === mode) {
                        return 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600';
                    }

                    return 'text-gray-600 hover:bg-white dark:text-gray-300 dark:hover:bg-white/[0.05]';
                },
                saleSubmitButtonClass() {
                    return 'bg-brand-500 hover:bg-brand-600';
                },
                standardPrice() {
                    return Number(this.sale.standard_price || this.priceForFuel(this.sale.fuel_type));
                },
                pricingCustomer() {
                    if (this.sale.mode === 'credit') {
                        return this.sale.customer_id ? this.customersById[String(this.sale.customer_id)] : null;
                    }

                    return this.sale.walkin_customer_id ? this.customersById[String(this.sale.walkin_customer_id)] : null;
                },
                editingOriginalPricingContext() {
                    if (!this.sale.id) {
                        return false;
                    }

                    const currentCustomerId = this.sale.mode === 'credit'
                        ? String(this.sale.customer_id || '')
                        : String(this.sale.walkin_customer_id || '');
                    const originalMode = this.sale.original_payment_type === 'credit' ? 'credit' : 'walkin';

                    return this.sale.mode === originalMode
                        && currentCustomerId === String(this.sale.original_customer_id || '')
                        && this.sale.fuel_type === this.sale.original_fuel_type;
                },
                appliedPrice() {
                    if (this.editingOriginalPricingContext() && Number(this.sale.original_price_applied || 0) > 0) {
                        return Number(this.sale.original_price_applied);
                    }

                    const customer = this.pricingCustomer();
                    const fuel = this.sale.fuel_type;

                    if (customer && customer.prices && Number(customer.prices[fuel] || 0) > 0) {
                        return Number(customer.prices[fuel]);
                    }

                    return this.standardPrice();
                },
                liters() {
                    return Math.max(0, Number(this.sale.liters || 0));
                },
                manualDiscount() {
                    return Math.max(0, Number(this.sale.manual_discount || 0));
                },
                manualExcess() {
                    return Math.max(0, Number(this.sale.manual_excess || 0));
                },
                subtotalBeforeManual() {
                    return this.liters() * this.appliedPrice();
                },
                autoDiscount() {
                    const diff = this.appliedPrice() - this.standardPrice();

                    return diff < 0 ? Math.abs(diff) * this.liters() : 0;
                },
                autoExcess() {
                    const diff = this.appliedPrice() - this.standardPrice();

                    return diff > 0 ? diff * this.liters() : 0;
                },
                totalDiscount() {
                    return this.autoDiscount() + this.manualDiscount();
                },
                totalExcess() {
                    return this.autoExcess() + this.manualExcess();
                },
                totalAmount() {
                    return Math.max(0, this.subtotalBeforeManual() + this.manualExcess() - this.manualDiscount());
                },
                creditRemaining() {
                    if (!this.selectedCreditCustomer) {
                        return 0;
                    }

                    return Number(this.selectedCreditCustomer.limit || 0) - this.balanceForCreditLimit(this.selectedCreditCustomer);
                },
                balanceForCreditLimit(customer) {
                    let balance = Number(customer?.balance || 0);

                    if (
                        this.sale.id
                        && this.sale.original_payment_type === 'credit'
                        && String(this.sale.original_customer_id || '') === String(customer?.id || '')
                    ) {
                        balance -= Number(this.sale.original_amount || 0);
                    }

                    return balance;
                },
                async submitSale() {
                    this.touchSale();

                    if (this.creditWarning) {
                        return;
                    }

                    if (this.sale.mode === 'credit' && !this.sale.customer_id) {
                        this.showNotice('error', 'Please select a credit customer.');
                        return;
                    }

                    if (!this.sale.pump_id) {
                        this.showNotice('error', 'Please select a pump.');
                        return;
                    }

                    if (!this.liters()) {
                        this.showNotice('error', 'Please enter liters dispensed.');
                        return;
                    }

                    if (this.manualDiscount() > (this.subtotalBeforeManual() + this.manualExcess())) {
                        this.showNotice('error', 'Manual discount cannot exceed the transaction amount.');
                        return;
                    }

                    const payload = {
                        pump_id: this.sale.pump_id,
                        fuel_type: this.sale.fuel_type,
                        liters: this.liters(),
                        price: this.appliedPrice(),
                        standard_price: this.standardPrice(),
                        payment_type: this.sale.mode,
                        customer_id: this.sale.mode === 'credit' ? this.sale.customer_id : null,
                        walkin_name: this.sale.mode === 'walkin'
                            ? (this.sale.walkin_customer_id || this.sale.walkin_name || 'Walk-In Customer')
                            : null,
                        plate_no: this.sale.mode === 'walkin' ? this.sale.plate_no : null,
                        manual_discount: this.manualDiscount(),
                        manual_excess: this.manualExcess(),
                        or_number: this.sale.or_number || null
                    };

                    const confirmed = await this.askConfirmation({
                        title: this.sale.id ? 'Update Transaction' : 'Record Transaction',
                        message: (this.sale.id ? 'Update' : 'Record') + ' this transaction for ' + this.money(this.totalAmount()) + '?',
                        details: (this.sale.pumpLabel || 'Pump') + ' / ' + (this.sale.fuel_type || 'Fuel'),
                        confirmText: this.sale.id ? 'Update Transaction' : 'Record Transaction',
                        tone: 'success'
                    });

                    if (!confirmed) {
                        return;
                    }

                    this.savingSale = true;

                    const url = this.sale.id
                        ? config.routes.updateTemplate.replace('__ID__', this.sale.id)
                        : config.routes.store;

                    await this.postJson(url, payload, this.sale.id ? 'Transaction updated.' : 'Transaction recorded.');
                    this.savingSale = false;
                },
                openPaymentModal() {
                    this.resetPayment();
                    this.paymentModalOpen = true;
                },
                closePaymentModal() {
                    this.paymentModalOpen = false;
                    this.resetPayment();
                },
                handlePaymentCustomerInput() {
                    const selectedCustomer = this.selectedPaymentCustomer;
                    this.paymentCustomerDropdownOpen = true;

                    if (
                        selectedCustomer
                        && this.normalizeCustomerSearch(this.paymentCustomerSearch) === this.normalizeCustomerSearch(selectedCustomer.label)
                    ) {
                        return;
                    }

                    this.payment.customer_id = '';
                    this.payment.invoice_id = '';
                    this.selectedInvoice = null;
                    this.selectedPaymentCustomer = null;
                    this.invoices = [];
                    this.paymentAdvanceMessage = '';

                    const exactMatch = this.exactCustomerMatch(this.paymentCustomerSearch);

                    if (exactMatch) {
                        this.selectPaymentCustomer(exactMatch);
                    }
                },
                selectPaymentCustomer(customer) {
                    this.payment.customer_id = String(customer.id);
                    this.paymentCustomerSearch = customer.label;
                    this.paymentCustomerDropdownOpen = false;
                    this.loadInvoices();
                },
                clearPaymentCustomer() {
                    this.payment.customer_id = '';
                    this.payment.invoice_id = '';
                    this.paymentCustomerSearch = '';
                    this.paymentCustomerDropdownOpen = true;
                    this.invoices = [];
                    this.selectedInvoice = null;
                    this.selectedPaymentCustomer = null;
                    this.paymentAdvanceMessage = '';
                },
                syncPaymentCustomer() {
                    this.selectedPaymentCustomer = this.payment.customer_id ? this.customersById[String(this.payment.customer_id)] : null;
                    this.syncPaymentAmount();
                },
                syncPaymentAmount() {
                    if (!this.selectedPaymentCustomer || !Number(this.payment.amount || 0)) {
                        this.paymentAdvanceMessage = '';
                        return;
                    }

                    const maxAmount = this.selectedInvoice
                        ? this.invoiceRemaining(this.selectedInvoice)
                        : Math.max(0, Number(this.selectedPaymentCustomer.balance || 0));
                    const excess = Number(this.payment.amount || 0) - maxAmount;

                    this.paymentAdvanceMessage = excess > 0.01
                        ? 'Includes advance payment of ' + this.money(excess) + '.'
                        : '';
                },
                paymentAmountValue() {
                    return Math.max(0, Number(this.payment.amount || 0));
                },
                paymentBalance() {
                    return Math.max(0, Number(this.selectedPaymentCustomer?.balance || 0));
                },
                paymentAfterBalance() {
                    return Math.max(0, this.paymentBalance() - this.paymentAmountValue());
                },
                paymentHasOverage() {
                    return !!this.selectedPaymentCustomer && this.paymentAmountValue() > this.paymentBalance() + 0.01;
                },
                paymentAfterBalanceClass() {
                    if (!this.selectedPaymentCustomer) {
                        return 'text-gray-700 dark:text-gray-300';
                    }

                    return this.paymentAfterBalance() > 0.01
                        ? 'text-warning-600 dark:text-warning-400'
                        : 'text-success-700 dark:text-success-400';
                },
                validPaymentAmount() {
                    return Number.isFinite(this.paymentAmountValue()) && this.paymentAmountValue() > 0;
                },
                canSubmitPayment() {
                    return !this.savingPayment && !!this.payment.customer_id && this.validPaymentAmount();
                },
                focusPaymentAmount() {
                    this.$nextTick(() => {
                        const input = this.$refs.paymentAmountInput || document.querySelector('[x-model\\.number="payment.amount"]');

                        if (input) {
                            input.focus();
                            input.select?.();
                        }
                    });
                },
                async loadInvoices() {
                    this.syncPaymentCustomer();
                    this.payment.invoice_id = '';
                    this.selectedInvoice = null;
                    this.invoices = [];

                    if (!this.payment.customer_id) {
                        return;
                    }

                    this.focusPaymentAmount();
                    this.loadingInvoices = true;

                    try {
                        const response = await fetch(config.routes.unpaidInvoicesBase + '/' + this.payment.customer_id + '/unpaid-invoices', {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();
                        this.invoices = Array.isArray(data) ? data : [];
                    } catch (error) {
                        this.showNotice('error', 'Unable to load unpaid invoices.');
                    } finally {
                        this.loadingInvoices = false;
                    }
                },
                selectInvoice() {
                    this.selectedInvoice = this.invoices.find(invoice => String(invoice.id) === String(this.payment.invoice_id)) || null;
                    this.syncPaymentAmount();
                },
                setPaymentType(type) {
                    if (!this.selectedPaymentCustomer) {
                        return;
                    }

                    if (type === 'full') {
                        const remaining = this.selectedInvoice
                            ? this.invoiceRemaining(this.selectedInvoice)
                            : this.paymentBalance();

                        this.payment.amount = remaining.toFixed(2);
                    } else {
                        this.payment.amount = '';
                        this.focusPaymentAmount();
                    }

                    this.syncPaymentAmount();
                },
                invoiceRemaining(invoice) {
                    if (!invoice) {
                        return 0;
                    }

                    return Math.max(0, Number(invoice.total_amount_due || 0) - Number(invoice.payments_received || 0));
                },
                invoiceLabel(invoice) {
                    return (invoice.invoice_number || ('Invoice #' + invoice.id)) + ' - Remaining ' + this.money(this.invoiceRemaining(invoice));
                },
                async submitPayment() {
                    if (!this.payment.customer_id) {
                        this.showNotice('error', 'Please select a customer.');
                        return;
                    }

                    if (!this.validPaymentAmount()) {
                        this.showNotice('error', 'Please enter a valid payment amount.');
                        return;
                    }

                    const customer = this.selectedPaymentCustomer;
                    const confirmed = await this.askConfirmation({
                        title: 'Confirm Payment',
                        message: 'Log ' + this.money(this.payment.amount) + ' payment for ' + (customer?.label || 'this customer') + '?',
                        details: this.paymentAdvanceMessage,
                        confirmText: 'Log Payment',
                        tone: 'success'
                    });

                    if (!confirmed) {
                        return;
                    }

                    this.savingPayment = true;

                    await this.postJson(config.routes.payment, {
                        customer_id: this.payment.customer_id,
                        amount: Number(this.payment.amount),
                        payment_method: this.payment.payment_method,
                        invoice_id: this.payment.invoice_id || null,
                        or_number: this.payment.or_number || null
                    }, 'Payment logged for shift verification.');

                    this.savingPayment = false;
                },
                togglePrint(id, key, label, event) {
                    const checked = event.target.checked;
                    const numericId = Number(id);

                    if (checked && this.selectedCustomerKey && this.selectedCustomerKey !== key) {
                        event.target.checked = false;
                        this.showNotice('error', 'Selected transactions must belong to the same customer.');
                        return;
                    }

                    if (checked) {
                        this.selectedCustomerKey = key;
                        this.selectedCustomerLabel = label;
                        this.selectedIds = [...new Set([...this.selectedIds, numericId])];
                        return;
                    }

                    this.selectedIds = this.selectedIds.filter(item => item !== numericId);

                    if (this.selectedIds.length === 0) {
                        this.selectedCustomerKey = '';
                        this.selectedCustomerLabel = '';
                    }
                },
                printSelected() {
                    if (this.selectedIds.length === 0) {
                        this.showNotice('error', 'Please select at least one transaction to print.');
                        return;
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = config.routes.printSelected;
                    form.target = '_blank';

                    const token = document.createElement('input');
                    token.type = 'hidden';
                    token.name = '_token';
                    token.value = config.csrfToken;
                    form.appendChild(token);

                    this.selectedIds.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        form.appendChild(input);
                    });

                    document.body.appendChild(form);
                    form.submit();
                    form.remove();
                },
                async voidSale(id) {
                    const confirmed = await this.askConfirmation({
                        title: 'Void Transaction',
                        message: 'Void this fuel transaction and reverse its account effects?',
                        confirmText: 'Void Transaction',
                        tone: 'danger'
                    });

                    if (!confirmed) {
                        return;
                    }

                    await this.postJson(config.routes.void, { id }, 'Transaction reversed.');
                },
                async voidPayment(id) {
                    const confirmed = await this.askConfirmation({
                        title: 'Cancel Pending Payment',
                        message: 'Cancel this pending payment before shift verification?',
                        confirmText: 'Cancel Payment',
                        tone: 'danger'
                    });

                    if (!confirmed) {
                        return;
                    }

                    await this.postJson(config.routes.void, { id }, 'Pending payment canceled.');
                },
                async bypassPayment(id, customerName, amount) {
                    const password = await this.askPassword({
                        title: 'Reflect Payment',
                        message: 'Enter the semi-admin password to reflect this payment in the customer account.',
                        details: customerName + ' / ' + this.money(amount),
                        confirmText: 'Reflect Payment',
                        tone: 'brand'
                    });

                    if (!password) {
                        return;
                    }

                    await this.postJson(config.routes.bypass, { id, semiadmin_password: password }, 'Payment reflected in account.');
                },
                async voidPostedPayment(id, customerName, amount) {
                    const password = await this.askPassword({
                        title: 'Void Posted Payment',
                        message: 'Enter the semi-admin password to void this posted payment from the customer account.',
                        details: customerName + ' / ' + this.money(amount),
                        confirmText: 'Void Posted Payment',
                        tone: 'danger'
                    });

                    if (!password) {
                        return;
                    }

                    await this.postJson(config.routes.voidPostedPayment, { id, semiadmin_password: password }, 'Posted payment voided.');
                },
                async postJson(url, payload, fallbackSuccess) {
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken
                            },
                            body: JSON.stringify(payload)
                        });
                        let data = {};

                        try {
                            data = await response.json();
                        } catch (error) {
                            data = {};
                        }

                        if (!response.ok || !data.success) {
                            const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                            this.showNotice('error', firstError || data.message || 'Unable to complete the request.');
                            return false;
                        }

                        this.reloadWithToast(data.message || fallbackSuccess);
                        return true;
                    } catch (error) {
                        this.showNotice('error', 'An unexpected error occurred.');
                        return false;
                    }
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
                askPassword(options = {}) {
                    return new Promise(resolve => {
                        this.passwordDialog = {
                            open: true,
                            title: options.title || 'Authorization Required',
                            message: options.message || 'Enter the semi-admin password to continue.',
                            details: options.details || '',
                            confirmText: options.confirmText || 'Continue',
                            cancelText: options.cancelText || 'Cancel',
                            tone: options.tone || 'brand',
                            password: '',
                            showPassword: false,
                            error: '',
                            resolver: resolve
                        };

                        this.$nextTick(() => {
                            if (this.$refs.passwordInput) {
                                this.$refs.passwordInput.focus();
                            }
                        });
                    });
                },
                resolvePassword(confirmed) {
                    const resolver = this.passwordDialog.resolver;

                    if (confirmed) {
                        const password = String(this.passwordDialog.password || '');

                        if (password.trim() === '') {
                            this.passwordDialog.error = 'Semi-admin password is required.';
                            this.$nextTick(() => {
                                if (this.$refs.passwordInput) {
                                    this.$refs.passwordInput.focus();
                                }
                            });
                            return;
                        }

                        this.feedbackClosing = true;
                        this.passwordDialog.open = false;
                        this.passwordDialog.resolver = null;

                        if (resolver) {
                            resolver(password);
                        }

                        this.$nextTick(() => {
                            this.feedbackClosing = false;
                        });

                        return;
                    }

                    this.feedbackClosing = true;
                    this.passwordDialog.open = false;
                    this.passwordDialog.resolver = null;

                    if (resolver) {
                        resolver(null);
                    }

                    this.$nextTick(() => {
                        this.feedbackClosing = false;
                    });
                },
                reloadWithToast(message) {
                    this.persistNotice('success', message);
                    window.location.reload();
                },
                persistNotice(type, message) {
                    if (type !== 'success') {
                        return;
                    }

                    try {
                        window.sessionStorage.setItem('staff-pump-toast', message);
                    } catch (error) {
                        return;
                    }
                },
                restoreNotice() {
                    try {
                        let message = window.sessionStorage.getItem('staff-pump-toast') || '';
                        window.sessionStorage.removeItem('staff-pump-toast');

                        const legacyStored = window.sessionStorage.getItem('staff-pump-notice');
                        window.sessionStorage.removeItem('staff-pump-notice');

                        if (!message && legacyStored) {
                            const legacyNotice = JSON.parse(legacyStored);

                            if (legacyNotice?.type === 'success') {
                                message = legacyNotice.message || '';
                            } else if (legacyNotice?.message) {
                                this.$nextTick(() => this.showNotice(legacyNotice.type || 'error', legacyNotice.message));
                            }
                        }

                        if (message) {
                            this.$nextTick(() => this.showToast(message));
                        }
                    } catch (error) {
                        return;
                    }
                },
                money(value) {
                    return 'PHP ' + Number(value || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            };
        }
    </script>
@endpush
