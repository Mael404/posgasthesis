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
        $dateInputClass = 'h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent py-2.5 pl-4 pr-11 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
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

        $statusMeta = function ($status, $row = null) {
            $status = trim((string) $status);
            $statusKey = strtolower($status);

            if (str_contains($statusKey, 'reject')) {
                return [
                    'label' => 'Rejected',
                    'class' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                    'can_edit' => true,
                    'can_delete' => true,
                ];
            }

            if (str_contains($statusKey, 'verified') || str_contains($statusKey, 'approved')) {
                return [
                    'label' => 'Verified',
                    'class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                    'can_edit' => false,
                    'can_delete' => false,
                ];
            }

            if ($statusKey === 'in progress') {
                return [
                    'label' => 'In Progress',
                    'class' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                    'can_edit' => false,
                    'can_delete' => true,
                ];
            }

            if ($status === \App\Models\InventoryLog::STATUS_PENDING_ADMIN_VERIFICATION
                || $status === \App\Models\InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED) {
                return [
                    'label' => 'Pending Admin Verification',
                    'class' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                    'can_edit' => true,
                    'can_delete' => true,
                ];
            }

            if ($status === \App\Models\InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD) {
                return [
                    'label' => 'Pending',
                    'class' => 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300',
                    'can_edit' => false,
                    'can_delete' => true,
                ];
            }

            return [
                'label' => $status !== '' ? $status : 'Pending',
                'class' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                'can_edit' => false,
                'can_delete' => true,
            ];
        };

        $statusLabel = function ($status) use ($statusMeta) {
            return $statusMeta($status)['label'];
        };

        $statusClass = function ($status) use ($statusMeta) {
            return $statusMeta($status)['class'];
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

        $batchLabel = function ($row) {
            $count = (int) ($row->delivery_batch_count ?? 1);

            if ($count <= 1) {
                return null;
            }

            $line = max(1, (int) ($row->delivery_batch_line ?? 1));

            return "Batch line {$line} of {$count}";
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

        $lineLabel = function ($row) {
            $count = max(1, (int) ($row->delivery_batch_count ?? 1));
            $line = min($count, max(1, (int) ($row->delivery_batch_line ?? 1)));

            return "Line {$line} of {$count}";
        };

        $batchProgressLabel = function ($row, $verb = 'submitted') {
            $total = max(1, (int) ($row->delivery_batch_count ?? 1));
            $submitted = min($total, max(0, (int) ($row->delivery_batch_submitted_count ?? $row->delivery_batch_completed_count ?? 0)));

            return number_format($submitted) . ' of ' . number_format($total) . " {$verb}";
        };

        $batchIsInProgress = function ($row) {
            $total = max(1, (int) ($row->delivery_batch_count ?? 1));
            $submitted = min($total, max(0, (int) ($row->delivery_batch_submitted_count ?? $row->delivery_batch_completed_count ?? 0)));

            return $total > 1 && $submitted > 0 && $submitted < $total;
        };

        $pendingDeliveryBatchLines = collect($pendingDeliveryBatchLines ?? $pendingDeliveries);
        $pendingDeliveryBatches = $pendingDeliveries->groupBy('delivery_code');
        $pendingDeliveryBatchLineGroups = $pendingDeliveryBatchLines->groupBy('delivery_code');
        $pendingDeliveryIds = $pendingDeliveries->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => true]);
        $pendingBatchCount = $pendingDeliveryBatches->count();
        $pendingLineCount = $pendingDeliveries->count();
        $totalRecords = method_exists($records, 'total') ? $records->total() : $recordItems->count();
        $selectedModal = (request()->filled('delivery_id') || request()->filled('delivery_code')) && $selectedDelivery ? 'create-' . $selectedDelivery->id : null;
    @endphp

    <x-common.page-breadcrumb pageTitle="Tank Delivery Record" />

    <div
        x-data="tankDeliveryRecordsModule({
            fuels: @js(array_keys($fuelTypes)),
            sessionMessage: @js(session('success') ?: session('status')),
            initialError: @js($errors->first()),
            selectedMissingMessage: @js((request('delivery_id') || request('delivery_code')) && ! $selectedDelivery ? 'The selected delivery code is no longer pending or has already been submitted.' : ''),
            reopenForm: @js(old('_tdr_form')),
            selectedModal: @js($selectedModal)
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', modal !== null || confirmation.open || messageDialog.open)"
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

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Staff Inventory</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Tank Delivery Record</h1>
                    <p class="mt-2 max-w-3xl {{ $captionClass }}">Pending delivery inspections and submitted tanker records.</p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:min-w-[520px]">
                    <div class="rounded-xl border border-warning-100 bg-warning-50/50 p-3 dark:border-warning-500/20 dark:bg-warning-500/10">
                        <p class="{{ $labelClass }}">Pending Batches</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-warning-700 dark:text-warning-400">{{ number_format($pendingBatchCount) }}</p>
                    </div>
                    <div class="rounded-xl border border-brand-100 bg-brand-50/50 p-3 dark:border-brand-500/20 dark:bg-brand-500/10">
                        <p class="{{ $labelClass }}">Pending Tank Lines</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-brand-700 dark:text-brand-400">{{ number_format($pendingLineCount) }}</p>
                    </div>
                    <div class="rounded-xl border border-success-100 bg-success-50/50 p-3 dark:border-success-500/20 dark:bg-success-500/10">
                        <p class="{{ $labelClass }}">Submitted Records</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-success-700 dark:text-success-500">{{ number_format($totalRecords) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="flex flex-col gap-3 border-b border-gray-200 pb-5 dark:border-gray-800 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Pending Deliveries</h2>
                    <p class="mt-1 {{ $captionClass }}">Grouped by batch delivery code; each tank line is submitted separately.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-warning-50 px-3 py-1 text-theme-xs font-semibold text-warning-700 dark:bg-warning-500/15 dark:text-warning-400">
                    {{ $pendingBatchCount }} pending batch{{ $pendingBatchCount === 1 ? '' : 'es' }} / {{ $pendingLineCount }} pending line{{ $pendingLineCount === 1 ? '' : 's' }}
                </span>
            </div>

            <form method="GET" action="{{ route('staff.tank-delivery-records.index') }}" class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(150px,220px)_minmax(150px,220px)_minmax(260px,1fr)_auto] xl:items-end">
                <label class="block">
                    <span class="{{ $labelClass }}">Date</span>
                    <span class="relative mt-1.5 block custom-datepicker">
                        <input type="text" name="pending_date" value="{{ $pendingDate }}" placeholder="Select date" autocomplete="off" class="{{ $dateInputClass }}" data-tdr-datepicker x-on:change="submitPendingFilters($event)">
                        {!! $dateIcon !!}
                    </span>
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
                    <select name="delivery_id" id="pendingDeliverySelect" class="mt-1.5 {{ $selectClass }}" x-on:change="openDeliveryFromSelect($event)">
                        <option value="">Choose pending delivery code</option>
                        @foreach ($pendingDeliveries as $delivery)
                            <option value="{{ $delivery->id }}" data-modal-id="create-{{ $delivery->id }}" {{ (string) request('delivery_id') === (string) $delivery->id ? 'selected' : '' }}>
                                {{ $delivery->delivery_code }} | {{ $delivery->tank_name }} {{ $delivery->fuel_type }} | {{ $lineLabel($delivery) }} | {{ $delivery->supplier_name }} | {{ $dateLabel($delivery->delivery_date) }}
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
                <div class="mt-5 max-w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                    <table class="min-w-[1040px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[19%]">
                            <col class="w-[12%]">
                            <col class="w-[16%]">
                            <col class="w-[11%]">
                            <col class="w-[12%]">
                            <col class="w-[14%]">
                            <col class="w-[8%]">
                            <col class="w-[8%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Delivery Code</th>
                                <th class="{{ $thClass }}">Delivery Date</th>
                                <th class="{{ $thClass }}">Supplier</th>
                                <th class="{{ $thClass }}">Tank Lines</th>
                                <th class="{{ $thClass }} text-right">Total Liters</th>
                                <th class="{{ $thClass }}">Progress</th>
                                <th class="{{ $thClass }}">Status</th>
                                <th class="{{ $thClass }}">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($pendingDeliveryBatches as $deliveryCode => $batch)
                                @php
                                    $batchFirst = $batch->first();
                                    $batchLines = $pendingDeliveryBatchLineGroups->get($deliveryCode, $batch);
                                    $totalLines = max((int) ($batchFirst->delivery_batch_count ?? $batchLines->count()), $batchLines->count());
                                    $pendingLines = min($totalLines, max(0, (int) ($batchFirst->delivery_batch_pending_count ?? $batch->count())));
                                    $submittedLines = min($totalLines, max(0, (int) ($batchFirst->delivery_batch_submitted_count ?? $batchFirst->delivery_batch_completed_count ?? ($totalLines - $pendingLines))));
                                    $batchLiters = (float) ($batchFirst->delivery_batch_total_liters ?? $batchLines->sum('liters_delivered'));
                                    $batchStatus = $submittedLines > 0 && $pendingLines > 0
                                        ? 'In Progress'
                                        : ($pendingLines > 0
                                        ? \App\Models\InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD
                                        : \App\Models\InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED);
                                @endphp
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    <td class="{{ $tdPrimaryClass }}">
                                        <span class="inline-flex max-w-full rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ $deliveryCode }}</span>
                                    </td>
                                    <td class="{{ $tdClass }}">{{ $dateLabel($batchFirst->delivery_date) }}</td>
                                    <td class="{{ $tdClass }}">
                                        <span class="block truncate font-medium text-gray-800 dark:text-white/90">{{ $batchFirst->supplier_name }}</span>
                                    </td>
                                    <td class="{{ $tdClass }}">{{ number_format($totalLines) }} tank{{ $totalLines === 1 ? '' : 's' }}</td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format($batchLiters, 2) }} L</td>
                                    <td class="{{ $tdClass }}">{{ number_format($submittedLines) }} of {{ number_format($totalLines) }} submitted</td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $statusClass($batchStatus) }}">{{ $statusLabel($batchStatus) }}</span>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <button type="button" class="{{ $secondaryButtonClass }} h-9 px-3 py-2 text-theme-xs" x-on:click="openModal('batch-{{ $batchFirst->id }}')">
                                            View More
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @foreach ($pendingDeliveryBatches as $deliveryCode => $batch)
            @php
                $batchFirst = $batch->first();
                $batchLines = $pendingDeliveryBatchLineGroups->get($deliveryCode, $batch);
                $totalLines = max((int) ($batchFirst->delivery_batch_count ?? $batchLines->count()), $batchLines->count());
                $pendingLines = min($totalLines, max(0, (int) ($batchFirst->delivery_batch_pending_count ?? $batch->count())));
                $submittedLines = min($totalLines, max(0, (int) ($batchFirst->delivery_batch_submitted_count ?? $batchFirst->delivery_batch_completed_count ?? ($totalLines - $pendingLines))));
                $batchLiters = (float) ($batchFirst->delivery_batch_total_liters ?? $batchLines->sum('liters_delivered'));
            @endphp
            <div
                x-show="modal === 'batch-{{ $batchFirst->id }}'"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 overflow-y-auto bg-gray-900/55 px-4 py-6 backdrop-blur-[2px]"
                style="z-index: 100200;"
                x-on:click.self="closeModal()">
                <div class="mx-auto flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900" data-modal-panel="batch-{{ $batchFirst->id }}">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div class="min-w-0">
                            <p class="{{ $labelClass }}">Pending Delivery Batch</p>
                            <h2 class="mt-1 truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $deliveryCode }}</h2>
                            <p class="mt-1 {{ $captionClass }}">{{ $batchFirst->supplier_name }} - {{ $dateLabel($batchFirst->delivery_date) }}</p>
                        </div>
                        <button type="button" class="{{ $iconButtonClass }}" x-on:click="closeModal()" aria-label="Close modal">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-5 custom-scrollbar">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Delivery Code</p>
                                <p class="mt-2 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $deliveryCode }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Supplier</p>
                                <p class="mt-2 truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $batchFirst->supplier_name }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Delivery Date</p>
                                <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $dateLabel($batchFirst->delivery_date) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Total Tank Lines</p>
                                <p class="mt-2 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($totalLines) }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Total Liters</p>
                                <p class="mt-2 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($batchLiters, 2) }} L</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <p class="{{ $labelClass }}">Progress</p>
                                <p class="mt-2 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format($submittedLines) }} of {{ number_format($totalLines) }} submitted</p>
                            </div>
                        </div>

                        <div class="mt-5 max-w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                            <table class="min-w-[820px] w-full table-fixed">
                                <colgroup>
                                    <col class="w-[15%]">
                                    <col class="w-[18%]">
                                    <col class="w-[16%]">
                                    <col class="w-[17%]">
                                    <col class="w-[14%]">
                                    <col class="w-[20%]">
                                </colgroup>
                                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <tr>
                                        <th class="{{ $thClass }}">Line</th>
                                        <th class="{{ $thClass }}">Tank</th>
                                        <th class="{{ $thClass }}">Fuel Type</th>
                                        <th class="{{ $thClass }} text-right">Liters Delivered</th>
                                        <th class="{{ $thClass }}">Status</th>
                                        <th class="{{ $thClass }}">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($batchLines as $delivery)
                                        @php
                                            $style = $fuelStyle($delivery->fuel_type);
                                            $lineStatus = $delivery->delivery_status ?? \App\Models\InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD;
                                            $lineIsPending = isset($pendingDeliveryIds[(int) $delivery->id]);
                                            $lineStatusLabel = !empty($delivery->tank_delivery_record_id) ? 'Pending Admin Verification' : $statusLabel($lineStatus);
                                            $lineStatusClass = !empty($delivery->tank_delivery_record_id)
                                                ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'
                                                : $statusClass($lineStatus);
                                        @endphp
                                        <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                            <td class="{{ $tdClass }}">{{ $lineLabel($delivery) }}</td>
                                            <td class="{{ $tdPrimaryClass }}">{{ $delivery->tank_name }}</td>
                                            <td class="{{ $tdClass }}">
                                                <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $style['badge'] }}">
                                                    <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                                                    {{ $delivery->fuel_type }}
                                                </span>
                                            </td>
                                            <td class="{{ $tdNumberClass }}">{{ number_format((float) $delivery->liters_delivered, 2) }} L</td>
                                            <td class="{{ $tdClass }}">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $lineStatusClass }}">{{ $lineStatusLabel }}</span>
                                            </td>
                                            <td class="{{ $tdClass }}">
                                                @if ($lineIsPending)
                                                    <button type="button" class="{{ $primaryButtonClass }} h-9 px-3 py-2 text-theme-xs" x-on:click="openModal('create-{{ $delivery->id }}')">
                                                        Inspect Tank Line
                                                    </button>
                                                @else
                                                    <span class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Already submitted</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                        <button type="button" class="{{ $secondaryButtonClass }}" x-on:click="closeModal()">Close</button>
                    </div>
                </div>
            </div>
        @endforeach

        <section class="{{ $cardClass }} overflow-hidden p-0">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Submitted Tank Delivery Records</h2>
                    <p class="mt-1 {{ $captionClass }}">{{ number_format($recordItems->count()) }} submitted record{{ $recordItems->count() === 1 ? '' : 's' }} on this page, one row per tank line.</p>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="min-w-[1220px] w-full table-fixed">
                    <colgroup>
                        <col class="w-[18%]">
                        <col class="w-[11%]">
                        <col class="w-[10%]">
                        <col class="w-[13%]">
                        <col class="w-[13%]">
                        <col class="w-[11%]">
                        <col class="w-[12%]">
                        <col class="w-[12%]">
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
                                $recordLineLabel = $lineLabel($record);
                                $recordProgressLabel = $batchProgressLabel($record);
                                $recordStatus = $statusMeta($record->delivery_status, $record);
                                $recordBatchInProgress = $batchIsInProgress($record);
                            @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                <td class="{{ $tdPrimaryClass }}">
                                    <span class="inline-flex max-w-full rounded-full bg-brand-50 px-2.5 py-1 text-theme-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ $record->delivery_code }}</span>
                                    <span class="mt-2 block text-theme-xs font-semibold text-gray-600 dark:text-gray-300">{{ $recordLineLabel }}</span>
                                    <span class="mt-1 block text-theme-xs text-gray-500 dark:text-gray-400">Batch Delivery</span>
                                    <span class="mt-1 block text-theme-xs text-gray-500 dark:text-gray-400">{{ $recordProgressLabel }}</span>
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
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $recordStatus['class'] }}">{{ $recordStatus['label'] }}</span>
                                    @if ($recordBatchInProgress)
                                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $statusClass('In Progress') }}">In Progress</span>
                                    @endif
                                </td>
                                <td class="{{ $tdClass }}">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="{{ $viewIconButtonClass }}" x-on:click="openModal('view-{{ $record->id }}')" title="View" aria-label="View {{ $record->delivery_code }}">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M2.75 12s3.25-6.25 9.25-6.25S21.25 12 21.25 12 18 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                                <path d="M14.75 12a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z" stroke="currentColor" stroke-width="1.6" />
                                            </svg>
                                        </button>
                                        @if ($recordStatus['can_edit'])
                                            <button type="button" class="{{ $editIconButtonClass }}" x-on:click="openModal('edit-{{ $record->id }}')" title="Update" aria-label="Update {{ $record->delivery_code }}">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M4.75 19.25h4.1L18.5 9.6a2.33 2.33 0 0 0-3.3-3.3l-9.65 9.65-.8 3.3Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                                    <path d="m13.75 7.75 2.5 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                                </svg>
                                            </button>
                                        @endif
                                        @if ($recordStatus['can_delete'])
                                            <button type="button" class="{{ $deleteIconButtonClass }}" x-on:click="confirmDeleteRecord('delete-record-{{ $record->id }}', @js($record->delivery_code))" title="Delete" aria-label="Delete {{ $record->delivery_code }}">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M5.75 7.25h12.5M9.75 7.25V5.5c0-.414.336-.75.75-.75h3c.414 0 .75.336.75.75v1.75M9 10.75v6M12 10.75v6M15 10.75v6M7.5 7.25l.58 11.03c.05.83.73 1.47 1.56 1.47h4.72c.83 0 1.51-.64 1.56-1.47l.58-11.03" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                        @endif
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
            @php
                $createFormKey = 'create-' . $delivery->id;
                $deliveryBatchLabel = $batchLabel($delivery);
            @endphp
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
                            <p class="{{ $labelClass }}">Submit Delivery Record</p>
                            <h2 class="mt-1 truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $delivery->delivery_code }}</h2>
                            @if ($deliveryBatchLabel)
                                <p class="mt-1 text-theme-xs font-semibold text-gray-500 dark:text-gray-400">{{ $deliveryBatchLabel }} - {{ $delivery->tank_name }} / {{ $delivery->fuel_type }}</p>
                            @endif
                        </div>
                        <button type="button" class="{{ $iconButtonClass }}" x-on:click="closeModal()" aria-label="Close modal">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('staff.tank-delivery-records.store') }}" class="flex min-h-0 flex-1 flex-col js-tdr-form" x-on:submit.prevent="confirmTdrSubmit($event.target, 'Submit Tank Delivery Record', 'Submit this tank delivery record?', 'Submit Record', 'success')">
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
                $recordStatus = $statusMeta($record->delivery_status, $record);
                $recordLineLabel = $lineLabel($record);
                $recordProgressLabel = $batchProgressLabel($record);
                $recordBatchInProgress = $batchIsInProgress($record);
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
                            <p class="mt-1 text-theme-xs font-semibold text-gray-500 dark:text-gray-400">Batch Delivery - {{ $recordLineLabel }} - {{ $recordProgressLabel }}</p>
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
                                <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $recordStatus['class'] }}">{{ $recordStatus['label'] }}</span>
                                @if ($recordBatchInProgress)
                                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $statusClass('In Progress') }}">In Progress</span>
                                @endif
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
                                    @php
                                        $displayLabel = $checklistLabelForFuel($field, $label, $record->fuel_type);
                                        $passed = (bool) $record->{$field};
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
                                        @foreach ($fuelTypesForDelivery($record) as $fuel => $label)
                                            @php
                                                $beforeField = "before_unloading_dipstick_{$fuel}";
                                                $afterField = "after_unloading_dipstick_{$fuel}";
                                                $dispensedField = "total_dispensed_{$fuel}";
                                                $totalField = "after_before_total_{$fuel}";
                                                $summaryValue = $record->{$dispensedField} !== null && $record->{$totalField} !== null
                                                    ? (float) $record->{$dispensedField} + (float) $record->{$totalField}
                                                    : null;
                                            @endphp
                                            <tr>
                                                <td class="{{ $tdPrimaryClass }}">{{ $label }}</td>
                                                <td class="{{ $tdNumberClass }}">{{ $numberLabel($record->{$beforeField}) }}</td>
                                                <td class="{{ $tdNumberClass }}">{{ $numberLabel($record->{$afterField}) }}</td>
                                                <td class="{{ $tdNumberClass }}">{{ $numberLabel($record->{$dispensedField}) }}</td>
                                                <td class="{{ $tdNumberClass }} font-semibold">{{ $numberLabel($record->{$totalField}) }}</td>
                                                <td class="{{ $tdNumberClass }} font-semibold">{{ $numberLabel($summaryValue) }}</td>
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

            @if ($recordStatus['can_edit'])
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
                                <p class="mt-1 text-theme-xs font-semibold text-gray-500 dark:text-gray-400">Batch Delivery - {{ $recordLineLabel }}</p>
                            </div>
                            <button type="button" class="{{ $iconButtonClass }}" x-on:click="closeModal()" aria-label="Close modal">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('staff.tank-delivery-records.update', $record->id) }}" class="flex min-h-0 flex-1 flex-col js-tdr-form" x-on:submit.prevent="confirmTdrSubmit($event.target, 'Update Tank Delivery Record', 'Apply these updates to this tank delivery record?', 'Update Record', 'brand')">
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
            @endif

            @if ($recordStatus['can_delete'])
                <form id="delete-record-{{ $record->id }}" method="POST" action="{{ route('staff.tank-delivery-records.destroy', $record->id) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endforeach

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
    </div>
@endsection

@push('scripts')
    <script>
        function tankDeliveryRecordsModule(config) {
            return {
                modal: null,
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
                feedbackClosing: false,
                init() {
                    this.$nextTick(() => {
                        this.initDatePickers();
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

                        if (config.initialError) {
                            this.showMessage('error', config.initialError);
                        } else if (config.selectedMissingMessage) {
                            this.showMessage('info', config.selectedMissingMessage, 'Delivery Not Available');
                        }
                    });
                },
                initDatePickers() {
                    if (!window.flatpickr) {
                        return;
                    }

                    document.querySelectorAll('[data-tdr-datepicker]').forEach(function(input) {
                        if (input._tdrDatePicker) {
                            return;
                        }

                        input._tdrDatePicker = window.flatpickr(input, {
                            static: true,
                            monthSelectorType: 'static',
                            dateFormat: 'Y-m-d',
                            defaultDate: input.value || null,
                            allowInput: false,
                            disableMobile: true,
                            onChange: function() {
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
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
                    if (this.confirmation.open || this.messageDialog.open || this.feedbackClosing) {
                        return;
                    }

                    this.modal = null;
                },
                submitPendingFilters(event) {
                    const form = event.target.form;

                    if (!form) {
                        return;
                    }

                    const deliverySelect = form.querySelector('[name="delivery_id"]');
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
                        summary = dispensed + total;
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
                    this.showMessage('error', 'Please complete the highlighted Tank Delivery Record fields.');
                    return false;
                },
                async confirmTdrSubmit(form, title, message, confirmText, tone) {
                    if (!form) {
                        this.showMessage('error', 'Unable to find the Tank Delivery Record form.');
                        return;
                    }

                    if (!form.reportValidity()) {
                        this.showMessage('error', 'Please complete all required Tank Delivery Record fields.');
                        return;
                    }

                    if (!this.validateTdrForm(form)) {
                        return;
                    }

                    const confirmed = await this.askConfirmation({
                        title,
                        message,
                        confirmText,
                        tone
                    });

                    if (!confirmed) {
                        return;
                    }

                    this.submitForm(form);
                },
                async confirmDeleteRecord(formId, deliveryCode) {
                    const confirmed = await this.askConfirmation({
                        title: 'Delete Tank Delivery Record',
                        message: 'Delete this record and return the delivery to the pending list?',
                        details: deliveryCode ? deliveryCode : '',
                        confirmText: 'Delete',
                        tone: 'danger'
                    });

                    if (!confirmed) {
                        return;
                    }

                    this.submitForm(document.getElementById(formId));
                },
                submitForm(form) {
                    if (!form) {
                        this.showMessage('error', 'Unable to submit the requested Tank Delivery Record action.');
                        return;
                    }

                    HTMLFormElement.prototype.submit.call(form);
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
