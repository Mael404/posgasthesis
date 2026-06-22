@extends('layouts.app')

@section('content')
    @php
        $pendingDeliveries = collect($pendingDeliveries ?? []);
        $records = $records ?? collect();
        $recordItems = collect(method_exists($records, 'items') ? $records->items() : $records);
        $fuelTypes = $fuelTypes ?? \App\Models\TankDeliveryRecord::FUEL_TYPES;
        $checklistItems = $checklistItems ?? [
            'security_seals_passed' => 'Security Seals',
            'product_level_passed' => 'Product Level',
            'ethanol_blend_passed' => 'Ethanol Blend',
            'free_of_water_passed' => 'Free of Water',
            'fully_drained_compartment_passed' => 'Fully Drained Compartment',
        ];
        $pendingDate = $pendingDate ?? request('pending_date');
        $pendingTankType = $pendingTankType ?? request('pending_tank_type');
        $hasPendingFilters = $hasPendingFilters ?? (request()->filled('pending_date') || request()->filled('pending_tank_type'));
        $selectedDelivery = $selectedDelivery ?? null;

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $selectClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $dangerButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600';
        $iconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $viewIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-brand-200 bg-brand-50 text-brand-600 shadow-theme-xs transition hover:border-brand-300 hover:bg-brand-100 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/15';
        $editIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-warning-200 bg-warning-50 text-warning-700 shadow-theme-xs transition hover:border-warning-300 hover:bg-warning-100 dark:border-warning-500/25 dark:bg-warning-500/10 dark:text-warning-400 dark:hover:bg-warning-500/15';
        $deleteIconButtonClass = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-error-200 bg-error-50 text-error-600 shadow-theme-xs transition hover:border-error-300 hover:bg-error-100 dark:border-error-500/25 dark:bg-error-500/10 dark:text-error-400 dark:hover:bg-error-500/15';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';

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

        $totalRecords = method_exists($records, 'total') ? $records->total() : $recordItems->count();
        $completedOnPage = $recordItems->filter(fn ($record) => ($record->delivery_status ?? null) === \App\Models\InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED)->count();
        $selectedModal = request()->filled('delivery_code') && $selectedDelivery ? 'create-' . $selectedDelivery->id : null;
    @endphp

    <x-common.page-breadcrumb pageTitle="Tank Delivery Record" />

    <div
        x-data="tankDeliveryRecordsModule({
            fuels: @js(array_keys($fuelTypes)),
            sessionMessage: @js(session('success') ?: session('status')),
            reopenForm: @js(old('_tdr_form')),
            selectedModal: @js($selectedModal)
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', modal !== null)"
        x-on:keydown.escape.window="closeModal()"
        class="tdr-module space-y-6">
        <style>
            [x-cloak] { display: none !important; }
            .tdr-module input[type="number"] {
                appearance: textfield;
                -moz-appearance: textfield;
            }
            .tdr-module input[type="number"]::-webkit-inner-spin-button,
            .tdr-module input[type="number"]::-webkit-outer-spin-button {
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
            x-transition:leave="transition ease-in duration-150"
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

        @if ($errors->any())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300">
                {{ $errors->first() }}
            </div>
        @endif

        @if (request('delivery_code') && ! $selectedDelivery)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                The selected delivery code is no longer pending or has already been completed.
            </div>
        @endif

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Staff Inventory</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Tank Delivery Record</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">Pending delivery inspections and submitted tanker records.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:min-w-[320px]">
                    <div class="rounded-xl border border-warning-100 bg-warning-50/50 p-3 dark:border-warning-500/20 dark:bg-warning-500/10">
                        <p class="{{ $labelClass }}">Pending</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-warning-700 dark:text-warning-400">{{ number_format($pendingDeliveries->count()) }}</p>
                    </div>
                    <div class="rounded-xl border border-success-100 bg-success-50/50 p-3 dark:border-success-500/20 dark:bg-success-500/10">
                        <p class="{{ $labelClass }}">Submitted</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-success-700 dark:text-success-500">{{ number_format($totalRecords) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="flex flex-col gap-3 border-b border-gray-200 pb-5 dark:border-gray-800 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Pending Deliveries</h2>
                    <p class="mt-1 {{ $captionClass }}">Delivery codes generated from Inventory & Tanks.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-warning-50 px-3 py-1 text-theme-xs font-semibold text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                    {{ $pendingDeliveries->count() }} pending
                </span>
            </div>

            <form method="GET" action="{{ route('staff.tank-delivery-records.index') }}" class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(150px,220px)_minmax(150px,220px)_minmax(260px,1fr)_auto] xl:items-end">
                <label class="block">
                    <span class="{{ $labelClass }}">Date</span>
                    <input type="date" name="pending_date" value="{{ $pendingDate }}" class="mt-1.5 {{ $inputClass }}" x-on:change="submitPendingFilters($event)">
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">Type of Tank</span>
                    <select name="pending_tank_type" class="mt-1.5 {{ $selectClass }}" x-on:change="submitPendingFilters($event)">
                        <option value="">All tank types</option>
                        @foreach ($fuelTypes as $fuel => $label)
                            <option value="{{ $label }}" {{ strtolower((string) $pendingTankType) === strtolower($label) ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="{{ $labelClass }}">Delivery Code</span>
                    <select name="delivery_code" id="pendingDeliverySelect" class="mt-1.5 {{ $selectClass }}" x-on:change="openDeliveryFromSelect($event)">
                        <option value="">Choose pending delivery code</option>
                        @foreach ($pendingDeliveries as $delivery)
                            <option value="{{ $delivery->delivery_code }}" data-modal-id="create-{{ $delivery->id }}" {{ request('delivery_code') === $delivery->delivery_code ? 'selected' : '' }}>
                                {{ $delivery->delivery_code }} - {{ $dateLabel($delivery->delivery_date) }} - {{ $delivery->supplier_name }} - {{ $delivery->tank_name }} ({{ $delivery->fuel_type }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <a href="{{ route('staff.tank-delivery-records.index') }}" class="{{ $secondaryButtonClass }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6.5 8A7 7 0 1 1 5 12.333M6.5 8H3.75M6.5 8V5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Reset
                </a>
            </form>

            @if ($pendingDeliveries->isEmpty())
                <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-5 text-center text-theme-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                    {{ $hasPendingFilters ? 'No pending deliveries match the selected filters.' : 'No pending deliveries available.' }}
                </div>
            @else
                <div class="mt-5 grid grid-cols-1 gap-3 xl:grid-cols-3">
                    @foreach ($pendingDeliveries as $delivery)
                        @php $style = $fuelStyle($delivery->fuel_type); @endphp
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="inline-flex max-w-full rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ $delivery->delivery_code }}</span>
                                    <h3 class="mt-3 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $delivery->supplier_name }}</h3>
                                    <p class="mt-1 {{ $captionClass }}">{{ $dateLabel($delivery->delivery_date) }}</p>
                                </div>
                                <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $style['badge'] }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                    {{ $delivery->fuel_type }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2 text-theme-sm">
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/[0.03]">
                                    <p class="{{ $labelClass }}">Tank</p>
                                    <p class="mt-1 truncate font-medium text-gray-800 dark:text-white/90">{{ $delivery->tank_name }}</p>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3 text-right dark:bg-white/[0.03]">
                                    <p class="{{ $labelClass }}">Liters</p>
                                    <p class="mt-1 font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) $delivery->liters_delivered, 2) }} L</p>
                                </div>
                            </div>
                            <button type="button" class="mt-4 w-full {{ $primaryButtonClass }}" x-on:click="openModal('create-{{ $delivery->id }}')">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                                Complete Record
                            </button>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="{{ $cardClass }} overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Submitted Tank Delivery Records</h2>
                    <p class="mt-1 {{ $captionClass }}">{{ $completedOnPage }} completed record{{ $completedOnPage === 1 ? '' : 's' }} on this page.</p>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="min-w-[1120px] w-full table-fixed">
                    <colgroup>
                        <col class="w-[15%]">
                        <col class="w-[13%]">
                        <col class="w-[12%]">
                        <col class="w-[15%]">
                        <col class="w-[15%]">
                        <col class="w-[12%]">
                        <col class="w-[10%]">
                        <col class="w-[8%]">
                    </colgroup>
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            <th class="{{ $thClass }}">Delivery Code</th>
                            <th class="{{ $thClass }}">Date / Time</th>
                            <th class="{{ $thClass }}">Plate</th>
                            <th class="{{ $thClass }}">Supplier</th>
                            <th class="{{ $thClass }}">Tank / Fuel</th>
                            <th class="{{ $thClass }}">Submitted By</th>
                            <th class="{{ $thClass }}">Status</th>
                            <th class="{{ $thClass }}">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($records as $record)
                            @php
                                $style = $fuelStyle($record->fuel_type);
                            @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                <td class="{{ $tdPrimaryClass }}">
                                    <span class="inline-flex max-w-full rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ $record->delivery_code }}</span>
                                </td>
                                <td class="{{ $tdClass }}">
                                    <span class="block font-medium text-gray-800 dark:text-white/90">{{ $dateLabel($record->record_date) }}</span>
                                    <span class="mt-1 block text-theme-xs text-gray-500 dark:text-gray-400">{{ $timeLabel($record->record_time) }}</span>
                                </td>
                                <td class="{{ $tdPrimaryClass }}">{{ $record->plate_number }}</td>
                                <td class="{{ $tdClass }}">{{ $record->supplier_name }}</td>
                                <td class="{{ $tdClass }}">
                                    <div class="font-medium text-gray-800 dark:text-white/90">{{ $record->tank_name }}</div>
                                    <span class="mt-1 inline-flex items-center gap-2 rounded-full px-2.5 py-0.5 text-theme-xs {{ $style['badge'] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                        {{ $record->fuel_type }}
                                    </span>
                                </td>
                                <td class="{{ $tdClass }}">{{ $record->staff_name ?? 'Staff' }}</td>
                                <td class="{{ $tdClass }}">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $statusClass($record->delivery_status) }}">{{ $statusLabel($record->delivery_status) }}</span>
                                </td>
                                <td class="{{ $tdClass }}">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="{{ $viewIconButtonClass }}" x-on:click="openModal('view-{{ $record->id }}')" title="View" aria-label="View {{ $record->delivery_code }}">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M2.75 12s3.25-6.25 9.25-6.25S21.25 12 21.25 12 18 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                                <path d="M14.75 12a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z" stroke="currentColor" stroke-width="1.6" />
                                            </svg>
                                        </button>
                                        <button type="button" class="{{ $editIconButtonClass }}" x-on:click="openModal('edit-{{ $record->id }}')" title="Update" aria-label="Update {{ $record->delivery_code }}">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M4.75 19.25h4.1L18.5 9.6a2.33 2.33 0 0 0-3.3-3.3l-9.65 9.65-.8 3.3Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="m13.75 7.75 2.5 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                            </svg>
                                        </button>
                                        <button type="button" class="{{ $deleteIconButtonClass }}" x-on:click="openModal('delete-{{ $record->id }}')" title="Delete" aria-label="Delete {{ $record->delivery_code }}">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M5.75 7.25h12.5M9.75 7.25V5.5c0-.414.336-.75.75-.75h3c.414 0 .75.336.75.75v1.75M9 10.75v6M12 10.75v6M15 10.75v6M7.5 7.25l.58 11.03c.05.83.73 1.47 1.56 1.47h4.72c.83 0 1.51-.64 1.56-1.47l.58-11.03" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">No Tank Delivery Records submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($records, 'links'))
                <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                    {{ $records->links() }}
                </div>
            @endif
        </section>

        @foreach ($pendingDeliveries as $delivery)
            @php $createFormKey = 'create-' . $delivery->id; @endphp
            <div
                x-show="modal === 'create-{{ $delivery->id }}'"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 overflow-y-auto bg-gray-900/55 px-4 py-6 backdrop-blur-[2px]"
                style="z-index: 100200;"
                x-on:click.self="closeModal()">
                <div class="mx-auto flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900" data-modal-panel="create-{{ $delivery->id }}">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div class="min-w-0">
                            <p class="{{ $labelClass }}">Complete Delivery</p>
                            <h2 class="mt-1 truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $delivery->delivery_code }}</h2>
                        </div>
                        <button type="button" class="{{ $iconButtonClass }}" x-on:click="closeModal()" aria-label="Close modal">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('staff.tank-delivery-records.store') }}" class="flex min-h-0 flex-1 flex-col js-tdr-form" x-on:submit="if (!validateTdrForm($event.target)) { $event.preventDefault(); $event.stopPropagation(); }">
                        @csrf
                        <div class="min-h-0 flex-1 overflow-y-auto p-5 custom-scrollbar">
                            @include('pages.staff.partials.tank-delivery-record-form', [
                                'delivery' => $delivery,
                                'record' => null,
                                'inventoryLogId' => $delivery->id,
                                'formKey' => $createFormKey,
                                'fuelTypes' => $fuelTypes,
                                'checklistItems' => $checklistItems,
                            ])
                        </div>
                        <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                            <button type="button" class="{{ $secondaryButtonClass }}" x-on:click="closeModal()">Cancel</button>
                            <button type="submit" class="{{ $primaryButtonClass }}">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 12.5 9.5 17 19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Submit Tank Delivery Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        @foreach ($records as $record)
            @php
                $editFormKey = 'edit-' . $record->id;
                $recordStatusClass = $statusClass($record->delivery_status);
                $recordStatusLabel = $statusLabel($record->delivery_status);
            @endphp

            <div
                x-show="modal === 'view-{{ $record->id }}'"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 overflow-y-auto bg-gray-900/55 px-4 py-6 backdrop-blur-[2px]"
                style="z-index: 100200;"
                x-on:click.self="closeModal()">
                <div class="mx-auto flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900" data-modal-panel="view-{{ $record->id }}">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div class="min-w-0">
                            <p class="{{ $labelClass }}">Delivery Details</p>
                            <h2 class="mt-1 truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $record->delivery_code }}</h2>
                        </div>
                        <button type="button" class="{{ $iconButtonClass }}" x-on:click="closeModal()" aria-label="Close modal">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-5 custom-scrollbar">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Status</p>
                                <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $recordStatusClass }}">{{ $recordStatusLabel }}</span>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Record Date</p>
                                <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $dateLabel($record->record_date) }}</p>
                                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $timeLabel($record->record_time) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Plate Number</p>
                                <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $record->plate_number }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Submitted By</p>
                                <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $record->staff_name ?? 'Staff' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Supplier</p>
                                <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $record->supplier_name }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Tank / Fuel</p>
                                <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $record->tank_name }} - {{ $record->fuel_type }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Admin Delivery Log</p>
                                <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $dateLabel($record->delivery_date) }}</p>
                            </div>
                        </div>

                        <section class="mt-5 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <h3 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">Inspection Checklist</h3>
                            <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
                                @foreach ($checklistItems as $field => $label)
                                    @php $passed = (bool) $record->{$field}; @endphp
                                    <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/[0.03]">
                                        <span class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
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
                                        @foreach ($fuelTypes as $fuel => $label)
                                            @php
                                                $beforeField = "before_unloading_dipstick_{$fuel}";
                                                $afterField = "after_unloading_dipstick_{$fuel}";
                                                $dispensedField = "total_dispensed_{$fuel}";
                                                $totalField = "after_before_total_{$fuel}";
                                                $summaryField = "delivery_summary_{$fuel}";
                                            @endphp
                                            <tr>
                                                <td class="{{ $tdPrimaryClass }}">{{ $label }}</td>
                                                <td class="{{ $tdNumberClass }}">{{ $numberLabel($record->{$beforeField}) }}</td>
                                                <td class="{{ $tdNumberClass }}">{{ $numberLabel($record->{$afterField}) }}</td>
                                                <td class="{{ $tdNumberClass }}">{{ $numberLabel($record->{$dispensedField}) }}</td>
                                                <td class="{{ $tdNumberClass }} font-semibold">{{ $numberLabel($record->{$totalField}) }}</td>
                                                <td class="{{ $tdNumberClass }} font-semibold">{{ $numberLabel($record->{$summaryField}) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                    <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                        <button type="button" class="{{ $secondaryButtonClass }}" x-on:click="closeModal()">Close</button>
                    </div>
                </div>
            </div>

            <div
                x-show="modal === 'edit-{{ $record->id }}'"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 overflow-y-auto bg-gray-900/55 px-4 py-6 backdrop-blur-[2px]"
                style="z-index: 100200;"
                x-on:click.self="closeModal()">
                <div class="mx-auto flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900" data-modal-panel="edit-{{ $record->id }}">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div class="min-w-0">
                            <p class="{{ $labelClass }}">Update Delivery</p>
                            <h2 class="mt-1 truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $record->delivery_code }}</h2>
                        </div>
                        <button type="button" class="{{ $iconButtonClass }}" x-on:click="closeModal()" aria-label="Close modal">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('staff.tank-delivery-records.update', $record->id) }}" class="flex min-h-0 flex-1 flex-col js-tdr-form" x-on:submit="if (!validateTdrForm($event.target)) { $event.preventDefault(); $event.stopPropagation(); }">
                        @csrf
                        @method('PUT')
                        <div class="min-h-0 flex-1 overflow-y-auto p-5 custom-scrollbar">
                            @include('pages.staff.partials.tank-delivery-record-form', [
                                'delivery' => $record,
                                'record' => $record,
                                'inventoryLogId' => $record->inventory_log_id,
                                'formKey' => $editFormKey,
                                'fuelTypes' => $fuelTypes,
                                'checklistItems' => $checklistItems,
                            ])
                        </div>
                        <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                            <button type="button" class="{{ $secondaryButtonClass }}" x-on:click="closeModal()">Cancel</button>
                            <button type="submit" class="{{ $primaryButtonClass }}">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 12.5 9.5 17 19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Update Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div
                x-show="modal === 'delete-{{ $record->id }}'"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 overflow-y-auto bg-gray-900/55 px-4 py-6 backdrop-blur-[2px]"
                style="z-index: 100200;"
                x-on:click.self="closeModal()">
                <div class="mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900" data-modal-panel="delete-{{ $record->id }}">
                    <form method="POST" action="{{ route('staff.tank-delivery-records.destroy', $record->id) }}">
                        @csrf
                        @method('DELETE')
                        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 8v5M12 16.5h.01M10.64 4.78 3.42 17.2a1.75 1.75 0 0 0 1.51 2.63h14.14a1.75 1.75 0 0 0 1.51-2.63L13.36 4.78a1.57 1.57 0 0 0-2.72 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Delete Tank Delivery Record</h2>
                                    <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">{{ $record->delivery_code }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-theme-sm text-gray-700 dark:text-gray-300">Delete this record and return the delivery to the pending list?</p>
                        </div>
                        <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                            <button type="button" class="{{ $secondaryButtonClass }}" x-on:click="closeModal()">Cancel</button>
                            <button type="submit" class="{{ $dangerButtonClass }}">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5.75 7.25h12.5M9.75 7.25V5.5c0-.414.336-.75.75-.75h3c.414 0 .75.336.75.75v1.75M9 10.75v6M12 10.75v6M15 10.75v6M7.5 7.25l.58 11.03c.05.83.73 1.47 1.56 1.47h4.72c.83 0 1.51-.64 1.56-1.47l.58-11.03" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script>
        function tankDeliveryRecordsModule(config) {
            return {
                modal: null,
                toast: { open: false, message: '', timer: null },
                init() {
                    this.$nextTick(() => {
                        this.calculateAllForms();

                        if (config.sessionMessage) {
                            this.showToast(config.sessionMessage);
                        }

                        if (config.reopenForm) {
                            this.openModal(config.reopenForm);
                            return;
                        }

                        if (config.selectedModal) {
                            this.openModal(config.selectedModal);
                        }
                    });
                },
                openModal(id) {
                    if (!id) {
                        return;
                    }

                    this.modal = id;

                    this.$nextTick(() => {
                        const panel = document.querySelector('[data-modal-panel="' + id + '"]');
                        const form = panel ? panel.querySelector('form.js-tdr-form') : null;

                        if (form) {
                            this.calculateAllFuels(form);
                        }

                        const focusTarget = panel ? panel.querySelector('[data-autofocus], input:not([type="hidden"]):not([readonly]), select, button') : null;
                        if (focusTarget) {
                            focusTarget.focus({ preventScroll: true });
                        }
                    });
                },
                closeModal() {
                    this.modal = null;
                },
                submitPendingFilters(event) {
                    const form = event.target.form;

                    if (!form) {
                        return;
                    }

                    const deliverySelect = form.querySelector('[name="delivery_code"]');
                    if (deliverySelect) {
                        deliverySelect.value = '';
                    }

                    form.submit();
                },
                openDeliveryFromSelect(event) {
                    const select = event.target;
                    const option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
                    const modalId = option ? option.dataset.modalId : '';

                    if (modalId) {
                        this.openModal(modalId);
                    }
                },
                readNumber(form, name) {
                    const input = form ? form.querySelector('[name="' + name + '"]') : null;

                    if (!input || input.value === '') {
                        return null;
                    }

                    const value = Number.parseFloat(input.value);
                    return Number.isFinite(value) ? value : null;
                },
                displayNumber(value) {
                    return value === null ? '' : value.toFixed(2);
                },
                calculateFuel(form, fuel) {
                    const before = this.readNumber(form, 'before_unloading_dipstick_' + fuel);
                    const after = this.readNumber(form, 'after_unloading_dipstick_' + fuel);
                    const dispensed = this.readNumber(form, 'total_dispensed_' + fuel);
                    const totalField = form ? form.querySelector('[data-tdr-total="' + fuel + '"]') : null;
                    const summaryField = form ? form.querySelector('[data-tdr-summary="' + fuel + '"]') : null;

                    let total = null;
                    let summary = null;

                    if (before !== null && after !== null) {
                        total = after - before;
                    }

                    if (total !== null && dispensed !== null) {
                        summary = total - dispensed;
                    }

                    if (totalField) {
                        totalField.value = this.displayNumber(total);
                    }

                    if (summaryField) {
                        summaryField.value = this.displayNumber(summary);
                    }
                },
                calculateAllFuels(form) {
                    (config.fuels || []).forEach((fuel) => this.calculateFuel(form, fuel));
                },
                calculateAllForms() {
                    document.querySelectorAll('form.js-tdr-form').forEach((form) => this.calculateAllFuels(form));
                },
                clearFieldError(input) {
                    if (!input) {
                        return;
                    }

                    input.classList.remove('border-error-300', 'focus:border-error-300', 'focus:ring-error-500/20', 'dark:border-error-500/50');

                    const shell = input.closest('[data-field-shell]');
                    const feedback = shell ? shell.querySelector('.js-field-error') : null;

                    if (feedback) {
                        feedback.textContent = '';
                        feedback.classList.add('hidden');
                    }
                },
                setFieldError(input, message) {
                    if (!input) {
                        return;
                    }

                    input.classList.add('border-error-300', 'focus:border-error-300', 'focus:ring-error-500/20', 'dark:border-error-500/50');

                    const shell = input.closest('[data-field-shell]');
                    const feedback = shell ? shell.querySelector('.js-field-error') : null;

                    if (feedback) {
                        feedback.textContent = message;
                        feedback.classList.remove('hidden');
                    }
                },
                clearChecklistError(group) {
                    if (!group) {
                        return;
                    }

                    group.classList.remove('border-error-300', 'bg-error-50/70', 'dark:border-error-500/50', 'dark:bg-error-500/10');

                    const feedback = group.parentElement ? group.parentElement.querySelector('.js-checklist-error') : null;

                    if (feedback) {
                        feedback.textContent = '';
                        feedback.classList.add('hidden');
                    }
                },
                setChecklistError(group, message) {
                    if (!group) {
                        return;
                    }

                    group.classList.add('border-error-300', 'bg-error-50/70', 'dark:border-error-500/50', 'dark:bg-error-500/10');

                    const feedback = group.parentElement ? group.parentElement.querySelector('.js-checklist-error') : null;

                    if (feedback) {
                        feedback.textContent = message;
                        feedback.classList.remove('hidden');
                    }
                },
                validateChecklist(form) {
                    let firstInvalidInput = null;

                    form.querySelectorAll('[data-checklist-group]').forEach((group) => {
                        this.clearChecklistError(group);

                        const field = group.dataset.checklistGroup;
                        const checkedInput = form.querySelector('[name="' + field + '"]:checked');

                        if (checkedInput) {
                            return;
                        }

                        this.setChecklistError(group, 'Please mark ' + group.dataset.checklistLabel + ' as Passed or Not Passed.');

                        if (!firstInvalidInput) {
                            firstInvalidInput = group.querySelector('input[type="radio"]');
                        }
                    });

                    return firstInvalidInput;
                },
                validateFuelTable(form) {
                    let firstInvalidInput = null;

                    (config.fuels || []).forEach((fuel) => {
                        const beforeInput = form.querySelector('[name="before_unloading_dipstick_' + fuel + '"]');
                        const afterInput = form.querySelector('[name="after_unloading_dipstick_' + fuel + '"]');
                        const dispensedInput = form.querySelector('[name="total_dispensed_' + fuel + '"]');
                        const requiredInputs = [beforeInput, afterInput, dispensedInput].filter(Boolean);

                        requiredInputs.forEach((input) => {
                            this.clearFieldError(input);

                            if (input.value !== '') {
                                return;
                            }

                            this.setFieldError(input, input.dataset.fieldLabel + ' is required.');

                            if (!firstInvalidInput) {
                                firstInvalidInput = input;
                            }
                        });

                        const hasMissingRequired = requiredInputs.some((input) => input.value === '');

                        if (!beforeInput || !afterInput || hasMissingRequired) {
                            return;
                        }

                        const before = Number.parseFloat(beforeInput.value);
                        const after = Number.parseFloat(afterInput.value);

                        if (!Number.isFinite(before) || !Number.isFinite(after) || after >= before) {
                            return;
                        }

                        const label = afterInput.dataset.fuelLabel || fuel;
                        this.setFieldError(afterInput, label + ' after unloading dipstick cannot be lower than before unloading dipstick.');

                        if (!firstInvalidInput) {
                            firstInvalidInput = afterInput;
                        }
                    });

                    return firstInvalidInput;
                },
                validateTdrForm(form) {
                    const checklistInput = this.validateChecklist(form);
                    const tableInput = this.validateFuelTable(form);
                    const firstInvalidInput = checklistInput || tableInput;

                    if (!firstInvalidInput) {
                        return true;
                    }

                    firstInvalidInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalidInput.focus({ preventScroll: true });
                    return false;
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
                }
            };
        }
    </script>
@endpush
