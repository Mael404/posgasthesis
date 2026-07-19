@extends('layouts.app')

@section('content')
    @php
        $summary = array_merge([
            'total_liters' => 0,
            'total_amount' => 0,
            'limit' => 4000,
            'remaining_liters' => 4000,
            'over_liters' => 0,
            'percent' => 0,
            'progress_width' => 0,
            'transaction_count' => 0,
            'submission_count' => 0,
            'pump_entry_count' => 0,
            'fuel_breakdown' => [],
            'tank_breakdown' => [],
            'reached_limits' => [],
            'configured_limit_count' => 0,
            'status' => [
                'label' => 'Normal',
                'badge_class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                'progress_class' => 'bg-success-500',
                'border_class' => 'border-success-200 bg-success-50/60 dark:border-success-500/25 dark:bg-success-500/10',
            ],
        ], $summary ?? []);
        $transactions = $transactions ?? collect();
        $transactionItems = $transactions instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($transactions->items())
            : collect($transactions);
        $tankBreakdown = collect($summary['tank_breakdown'] ?? []);
        $reachedLimits = collect($summary['reached_limits'] ?? []);
        $missingRequirements = $missingRequirements ?? [];
        $canManageAdjustments = (bool) ($canManageAdjustments ?? false);

        $cardClass = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] md:p-6';
        $labelClass = 'text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $captionClass = 'text-theme-sm text-gray-500 dark:text-gray-400';
        $valueClass = 'mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90';
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $textareaClass = 'min-h-20 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800';
        $primaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $secondaryButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $dangerButtonClass = 'inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-error-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60';
        $smallPrimaryButtonClass = 'inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-brand-500 px-3 text-theme-xs font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60';
        $smallSecondaryButtonClass = 'inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-theme-xs font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $smallDangerButtonClass = 'inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-error-500 px-3 text-theme-xs font-medium text-white shadow-theme-xs transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60';
        $thClass = 'px-5 py-3 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $thNumberClass = 'px-5 py-3 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400';
        $tdClass = 'px-5 py-4 align-middle text-theme-sm text-gray-700 dark:text-gray-300';
        $tdPrimaryClass = 'px-5 py-4 align-middle text-theme-sm font-medium text-gray-800 dark:text-white/90';
        $tdNumberClass = 'px-5 py-4 align-middle text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300';

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
                return ['badge' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400', 'dot' => 'bg-warning-500', 'bar' => 'bg-warning-500'];
            }

            if (str_contains($fuel, 'regular') || str_contains($fuel, 'unleaded')) {
                return ['badge' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500', 'dot' => 'bg-success-500', 'bar' => 'bg-success-500'];
            }

            if (str_contains($fuel, 'premium')) {
                return ['badge' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400', 'dot' => 'bg-error-500', 'bar' => 'bg-error-500'];
            }

            return ['badge' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400', 'dot' => 'bg-brand-500', 'bar' => 'bg-brand-500'];
        };

        $readingStatusStyle = function ($status) {
            $status = strtolower(trim((string) $status));

            if (str_contains($status, 'correction')) {
                return ['label' => 'Net Correction', 'class' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'];
            }

            if (str_contains($status, 'verified') || str_contains($status, 'approved')) {
                return ['label' => 'Verified', 'class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'];
            }

            return ['label' => 'Pending', 'class' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400'];
        };

        $trackingRowsPayload = $transactionItems->mapWithKeys(function ($transaction) use ($dateLabel, $readingStatusStyle) {
            $transactionId = (int) ($transaction->id ?? 0);
            $isCorrection = (bool) ($transaction->is_liters_movement_correction ?? false);
            $readingStatus = $readingStatusStyle($transaction->status ?? 'Pending');
            $pumpEntries = collect($transaction->pump_entries ?? [])->map(function ($entry) {
                return [
                    'pump_id' => (int) ($entry['pump_id'] ?? 0),
                    'fuel_type' => (string) ($entry['fuel_type'] ?? 'Fuel'),
                    'liters' => (float) ($entry['liters'] ?? 0),
                    'raw_liters' => (float) ($entry['raw_liters'] ?? $entry['liters'] ?? 0),
                    'deducted_liters' => (float) ($entry['deducted_liters'] ?? 0),
                    'price_per_liter' => (float) ($entry['price_per_liter'] ?? 0),
                    'amount' => (float) ($entry['amount'] ?? 0),
                    'raw_amount' => (float) ($entry['raw_amount'] ?? $entry['amount'] ?? 0),
                    'deducted_amount' => (float) ($entry['deducted_amount'] ?? 0),
                    'correction_id' => (int) ($entry['correction_id'] ?? 0),
                    'correction_entry_type' => (string) ($entry['correction_entry_type'] ?? ''),
                    'correction_status' => (string) ($entry['correction_status'] ?? ''),
                ];
            })->values();
            $correctionPumps = $isCorrection
                ? $pumpEntries
                    ->groupBy('pump_id')
                    ->map(function ($entries) {
                        $reversals = $entries->where('correction_entry_type', 'reversal');
                        $replacements = $entries->where('correction_entry_type', 'replacement');
                        $first = $entries->first() ?? [];

                        return [
                            'pump_id' => (int) ($first['pump_id'] ?? 0),
                            'fuel_type' => (string) ($first['fuel_type'] ?? 'Fuel'),
                            'original_liters' => abs((float) $reversals->sum('liters')),
                            'replacement_liters' => (float) $replacements->sum('liters'),
                            'net_liters' => (float) $entries->sum('liters'),
                            'original_amount' => abs((float) $reversals->sum('amount')),
                            'replacement_amount' => (float) $replacements->sum('amount'),
                            'net_amount' => (float) $entries->sum('amount'),
                        ];
                    })
                    ->sortBy('pump_id')
                    ->values()
                    ->all()
                : [];
            $pumpEntries = $pumpEntries->all();
            $adjustments = collect($transaction->liter_adjustments ?? [])->map(function ($adjustment) use ($dateLabel) {
                return [
                    'id' => (int) ($adjustment['id'] ?? 0),
                    'pump_number' => (int) ($adjustment['pump_number'] ?? 0),
                    'liters' => (float) ($adjustment['liters'] ?? 0),
                    'amount' => (float) ($adjustment['amount'] ?? 0),
                    'reason' => (string) ($adjustment['reason'] ?? ''),
                    'status' => (string) ($adjustment['status'] ?? 'active'),
                    'created_at_label' => $dateLabel($adjustment['created_at'] ?? null),
                    'created_by' => (string) ($adjustment['created_by'] ?? 'Staff'),
                    'authorized_by' => (string) ($adjustment['authorized_by'] ?? 'Semi Admin'),
                    'voided_at_label' => ! empty($adjustment['voided_at']) ? $dateLabel($adjustment['voided_at']) : null,
                    'voided_by' => (string) ($adjustment['voided_by'] ?? ''),
                    'void_authorized_by' => (string) ($adjustment['void_authorized_by'] ?? ''),
                    'void_reason' => (string) ($adjustment['void_reason'] ?? ''),
                ];
            })->values()->all();

            return [$transactionId => [
                'id' => $transactionId,
                'is_correction' => $isCorrection,
                'can_adjust_liters' => (bool) ($transaction->can_adjust_liters ?? true),
                'original_shift_reading_id' => (int) ($transaction->original_shift_reading_id ?? 0),
                'replacement_shift_reading_id' => (int) ($transaction->replacement_shift_reading_id ?? 0),
                'staff_name' => (string) ($transaction->staff_name ?? 'Staff'),
                'status' => (string) ($transaction->status ?? 'Pending'),
                'status_label' => $readingStatus['label'],
                'submitted_at_label' => $dateLabel($transaction->created_at ?? null),
                'period_label' => $dateLabel($transaction->period_from ?? null, 'M d, Y') . ' to ' . $dateLabel($transaction->period_to ?? null, 'M d, Y'),
                'total_liters' => (float) ($transaction->total_liters ?? 0),
                'total_amount' => (float) ($transaction->total_amount ?? 0),
                'pump_entries' => $pumpEntries,
                'correction_pumps' => $correctionPumps,
                'adjustments' => $adjustments,
                'active_adjustments_count' => collect($adjustments)->where('status', 'active')->count(),
            ]];
        })->all();
    @endphp

    <x-common.page-breadcrumb pageTitle="Liters Tracking" />

    <div
        x-data="litersTrackingStaff({
            rows: @js($trackingRowsPayload),
            canManageAdjustments: @js($canManageAdjustments),
            deductionUrlTemplate: @js(route('staff.liters-tracking.deductions.store', ['reading' => '__READING__'])),
            voidUrlTemplate: @js(route('staff.liters-tracking.deductions.void', ['deduction' => '__DEDUCTION__'])),
            sessionMessage: @js(session('success') ?: session('status')),
            sessionError: @js(session('error') ?: $errors->first()),
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', details.open || deduction.open || voidDialog.open || messageDialog.open)"
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
        @if ($missingRequirements)
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Missing tracking requirement(s): {{ implode(', ', $missingRequirements) }}.
            </div>
        @endif

        @if ($reachedLimits->isNotEmpty())
            <div class="rounded-xl border border-error-200 bg-error-50 p-4 text-theme-sm text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300">
                <span class="font-semibold">Liters limit reached:</span>
                {{ $reachedLimits->map(fn ($tank) => ($tank['name'] ?? $tank['fuel_type'] ?? 'Tank') . ' (' . number_format((float) ($tank['liters'] ?? 0), 2) . ' L / ' . number_format((float) ($tank['limit'] ?? 0), 2) . ' L)')->implode(', ') }}.
                Shift readings can still be submitted; this is an alert only.
            </div>
        @endif

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Staff Module</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Liters Tracking</h1>
                    <p class="mt-2 {{ $captionClass }}">
                        Shared period {{ $period->period_code ?? 'Pending Migration' }} started {{ $period ? $dateLabel($period->started_at, 'M d, Y h:i A') : 'after migration' }}. All staff see the same submitted shift-reading totals and limits.
                    </p>
                </div>

                <span class="inline-flex w-fit rounded-full px-3 py-1 text-theme-xs font-medium {{ $summary['status']['badge_class'] }}">
                    {{ $summary['status']['label'] }}
                </span>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Total Liters</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['total_liters'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">{{ number_format((int) $summary['submission_count']) }} shift submission{{ (int) $summary['submission_count'] === 1 ? '' : 's' }}</p>
            </article>
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Configured Limits</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['limit'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">Shared across {{ number_format((int) ($summary['configured_limit_count'] ?? 0)) }} tank{{ (int) ($summary['configured_limit_count'] ?? 0) === 1 ? '' : 's' }}</p>
            </article>
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Remaining Liters</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['remaining_liters'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">{{ (float) $summary['over_liters'] > 0 ? number_format((float) $summary['over_liters'], 2) . ' L over limit' : 'Before limit' }}</p>
            </article>
            <article class="{{ $cardClass }} {{ $summary['status']['border_class'] }}">
                <p class="{{ $labelClass }}">Status</p>
                <p class="{{ $valueClass }}">{{ $summary['status']['label'] }}</p>
                <p class="mt-1 {{ $captionClass }}">{{ number_format((float) $summary['percent'], 2) }}% used</p>
            </article>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Shared Tank Limits</h2>
                <p class="mt-1 {{ $captionClass }}">P1/P3 Premium, P2/P4 Unleaded, and P5/P6 Diesel are accumulated from every pending or verified shift submission.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @foreach ($tankBreakdown as $tank)
                    @php
                        $style = $fuelStyle($tank['fuel_type'] ?? '');
                    @endphp
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $tank['name'] ?? $tank['fuel_type'] ?? 'Tank' }}</p>
                                <p class="mt-1 {{ $captionClass }}">{{ $tank['connected_pumps_display'] ?? 'N/A' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $tank['status']['badge_class'] ?? '' }}">{{ $tank['status']['label'] ?? 'Normal' }}</span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <p class="text-xl font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($tank['liters'] ?? 0), 2) }} L</p>
                            <p class="{{ $captionClass }}">{{ ($tank['has_limit'] ?? false) ? number_format((float) ($tank['limit'] ?? 0), 2) . ' L limit' : 'No limit' }}</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white dark:bg-white/[0.08]">
                            <div class="h-full rounded-full {{ $style['bar'] }}" style="width: {{ $tank['progress_width'] ?? 0 }}%"></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Fuel Breakdown</h2>
                <p class="mt-1 {{ $captionClass }}">Current active tracking period.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ($summary['fuel_breakdown'] as $fuel)
                    @php
                        $style = $fuelStyle($fuel['fuel_type'] ?? '');
                    @endphp
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                        <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $style['badge'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $style['dot'] }}"></span>
                            {{ $fuel['fuel_type'] }}
                        </span>
                        <p class="mt-4 text-xl font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) $fuel['liters'], 2) }} L</p>
                        <p class="mt-1 {{ $captionClass }}">&#8369;{{ number_format((float) $fuel['amount'], 2) }} meter sales</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Included Shift Reading Submissions</h2>
                    <p class="mt-1 {{ $captionClass }}">This view includes only your Pending and Verified submissions.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">
                    {{ number_format((int) $summary['submission_count']) }} submissions
                </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1260px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[13%]">
                            <col class="w-[14%]">
                            <col class="w-[15%]">
                            <col class="w-[23%]">
                            <col class="w-[9%]">
                            <col class="w-[10%]">
                            <col class="w-[7%]">
                            <col class="w-[9%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Submitted</th>
                                <th class="{{ $thClass }}">Staff</th>
                                <th class="{{ $thClass }}">Reading Period</th>
                                <th class="{{ $thClass }}">Pump Meter Deltas</th>
                                <th class="{{ $thNumberClass }}">Liters</th>
                                <th class="{{ $thNumberClass }}">Meter Sales</th>
                                <th class="{{ $thClass }}">Status</th>
                                <th class="{{ $thClass }}">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($transactionItems as $transaction)
                                @php
                                    $transactionId = (int) ($transaction->id ?? 0);
                                    $pumpEntries = collect($transaction->pump_entries ?? []);
                                    $activeAdjustmentCount = collect($transaction->liter_adjustments ?? [])->where('status', 'active')->count();
                                    $readingStatus = $readingStatusStyle($transaction->status ?? 'Pending');
                                @endphp
                                <tr>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $dateLabel($transaction->created_at ?? null, 'M d, Y') }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $dateLabel($transaction->created_at ?? null, 'h:i A') }}</div>
                                    </td>
                                    <td class="{{ $tdPrimaryClass }}">{{ $transaction->staff_name ?? 'Staff' }}</td>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $dateLabel($transaction->period_from ?? null, 'M d, Y') }}</div>
                                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">to {{ $dateLabel($transaction->period_to ?? null, 'M d, Y') }}</div>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex flex-wrap gap-1.5">
                                            @forelse ($pumpEntries as $entry)
                                                @php
                                                    $fuel = $fuelStyle($entry['fuel_type'] ?? '');
                                                @endphp
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $fuel['badge'] }}" title="{{ (float) ($entry['deducted_liters'] ?? 0) > 0 ? 'Original: ' . number_format((float) ($entry['raw_liters'] ?? 0), 2) . ' L; deducted: ' . number_format((float) ($entry['deducted_liters'] ?? 0), 2) . ' L' : '' }}">
                                                    P{{ (int) ($entry['pump_id'] ?? 0) }}: {{ number_format((float) ($entry['liters'] ?? 0), 2) }} L
                                                    @if ((float) ($entry['deducted_liters'] ?? 0) > 0)
                                                        <span class="font-semibold opacity-80">-{{ number_format((float) ($entry['deducted_liters'] ?? 0), 2) }}</span>
                                                    @endif
                                                </span>
                                            @empty
                                                <span class="text-theme-xs text-gray-500 dark:text-gray-400">No positive meter movement</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format((float) ($transaction->total_liters ?? 0), 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) ($transaction->total_amount ?? 0), 2) }}</td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $readingStatus['class'] }}">{{ $readingStatus['label'] }}</span>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button" x-on:click="openDetails({{ $transactionId }})" class="{{ $smallSecondaryButtonClass }}" title="View shift-reading movement">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.75 12s3.5-6.25 9.25-6.25S21.25 12 21.25 12 17.75 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.75 12a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                                                View
                                            </button>
                                            <button type="button" x-on:click="openDeduction({{ $transactionId }})" class="{{ $smallPrimaryButtonClass }}" title="Deduct pump liters" @disabled(! $canManageAdjustments || ! ($transaction->can_adjust_liters ?? true))>
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                            </button>
                                            @if ($activeAdjustmentCount > 0)
                                                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-error-50 px-2 text-theme-xs font-semibold text-error-700 dark:bg-error-500/15 dark:text-error-400" title="Active deductions">{{ $activeAdjustmentCount }}</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                        No pending or verified shift-reading submissions in this period yet.
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

        <div x-show="details.open" x-cloak class="fixed inset-0 z-[100040] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="details.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="details.open"
                x-transition
                x-on:click.outside="closeDetails()"
                class="relative z-10 flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Shift Reading #<span x-text="details.row?.id"></span></h3>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium" :class="statusClass(details.row?.status)" x-text="details.row?.status_label || 'Pending'"></span>
                        </div>
                        <p class="mt-1 {{ $captionClass }}"><span x-text="details.row?.staff_name || 'Staff'"></span> | <span x-text="details.row?.period_label || 'N/A'"></span></p>
                    </div>
                    <button type="button" x-on:click="closeDetails()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200" aria-label="Close shift reading details">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Submitted</p>
                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="details.row?.submitted_at_label || 'N/A'"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}" x-text="details.row?.is_correction ? 'Net Liters' : 'Current Liters'"></p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatNumber(details.row?.total_liters)"></span> L</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}" x-text="details.row?.is_correction ? 'Net Meter Sales' : 'Meter Sales'"></p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatNumber(details.row?.total_amount)"></span></p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90" x-text="details.row?.is_correction ? 'Correction Breakdown' : 'Pump Movement'"></h4>
                        <p x-show="details.row?.is_correction" class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Original Shift Reading #<span x-text="details.row?.original_shift_reading_id || 'N/A'"></span> was reversed
                            <span x-show="details.row?.replacement_shift_reading_id">and replaced by Shift Reading #<span x-text="details.row?.replacement_shift_reading_id"></span>.</span>
                            <span x-show="!details.row?.replacement_shift_reading_id">and is awaiting a corrected replacement.</span>
                            Original and replacement cells show liters with meter sales underneath.
                        </p>
                        <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="max-w-full overflow-x-auto">
                                <table class="min-w-[760px] w-full table-fixed">
                                    <colgroup>
                                        <col class="w-[12%]">
                                        <col class="w-[18%]">
                                        <col class="w-[18%]">
                                        <col class="w-[18%]">
                                        <col class="w-[18%]">
                                        <col class="w-[16%]">
                                    </colgroup>
                                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                        <tr x-show="!details.row?.is_correction">
                                            <th class="{{ $thClass }}">Pump</th>
                                            <th class="{{ $thClass }}">Fuel</th>
                                            <th class="{{ $thNumberClass }}">Original</th>
                                            <th class="{{ $thNumberClass }}">Deducted</th>
                                            <th class="{{ $thNumberClass }}">Current</th>
                                            <th class="{{ $thNumberClass }}">Meter Sales</th>
                                        </tr>
                                        <tr x-show="details.row?.is_correction">
                                            <th class="{{ $thClass }}">Pump</th>
                                            <th class="{{ $thClass }}">Fuel</th>
                                            <th class="{{ $thNumberClass }}">Original</th>
                                            <th class="{{ $thNumberClass }}">Replacement</th>
                                            <th class="{{ $thNumberClass }}">Net Liters</th>
                                            <th class="{{ $thNumberClass }}">Net Meter Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody x-show="!details.row?.is_correction" class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <template x-for="pump in detailPumps()" :key="pump.pump_id">
                                            <tr>
                                                <td class="{{ $tdPrimaryClass }}">P<span x-text="pump.pump_id"></span></td>
                                                <td class="{{ $tdClass }}" x-text="pump.fuel_type"></td>
                                                <td class="{{ $tdNumberClass }}"><span x-text="formatNumber(pump.raw_liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }} text-error-600 dark:text-error-400"><span x-text="formatNumber(pump.deducted_liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }}"><span x-text="formatNumber(pump.liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }}">&#8369;<span x-text="formatNumber(pump.amount)"></span></td>
                                            </tr>
                                        </template>
                                        <tr x-show="detailPumps().length === 0">
                                            <td colspan="6" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No pump movement found for this submission.</td>
                                        </tr>
                                    </tbody>
                                    <tbody x-show="details.row?.is_correction" class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <template x-for="pump in detailCorrectionPumps()" :key="`correction-${pump.pump_id}`">
                                            <tr>
                                                <td class="{{ $tdPrimaryClass }}">P<span x-text="pump.pump_id"></span></td>
                                                <td class="{{ $tdClass }}" x-text="pump.fuel_type"></td>
                                                <td class="{{ $tdNumberClass }}">
                                                    <span><span x-text="formatNumber(pump.original_liters)"></span> L</span>
                                                    <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">&#8369;<span x-text="formatNumber(pump.original_amount)"></span></span>
                                                </td>
                                                <td class="{{ $tdNumberClass }}">
                                                    <span><span x-text="formatNumber(pump.replacement_liters)"></span> L</span>
                                                    <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">&#8369;<span x-text="formatNumber(pump.replacement_amount)"></span></span>
                                                </td>
                                                <td class="{{ $tdNumberClass }} font-semibold" :class="Number(pump.net_liters || 0) < 0 ? 'text-error-600 dark:text-error-400' : (Number(pump.net_liters || 0) > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-800 dark:text-white/90')"><span x-text="formatNumber(pump.net_liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }}">&#8369;<span x-text="formatNumber(pump.net_amount)"></span></td>
                                            </tr>
                                        </template>
                                        <tr x-show="detailCorrectionPumps().length === 0">
                                            <td colspan="6" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No correction entries were found for this submission.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div x-show="!details.row?.is_correction" class="mt-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Deduction History</h4>
                            <button type="button" x-on:click="openDeduction(details.row?.id)" class="{{ $smallPrimaryButtonClass }}" :disabled="!canManageAdjustments || !details.row || !details.row.can_adjust_liters">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                Add Deduction
                            </button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <template x-for="adjustment in detailAdjustments()" :key="adjustment.id">
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-gray-800 dark:text-white/90">P<span x-text="adjustment.pump_number"></span> - <span x-text="formatNumber(adjustment.liters)"></span> L / &#8369;<span x-text="formatNumber(adjustment.amount)"></span></p>
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium" :class="adjustment.status === 'voided' ? 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300' : 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400'" x-text="adjustment.status === 'voided' ? 'Voided' : 'Active'"></span>
                                            </div>
                                            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Added <span x-text="adjustment.created_at_label"></span> by <span x-text="adjustment.created_by"></span>; authorized by <span x-text="adjustment.authorized_by"></span></p>
                                            <p x-show="adjustment.reason" class="mt-1 text-theme-sm text-gray-600 dark:text-gray-300" x-text="adjustment.reason"></p>
                                            <p x-show="adjustment.status === 'voided'" class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                                Voided <span x-text="adjustment.voided_at_label || 'N/A'"></span><span x-show="adjustment.voided_by"> by <span x-text="adjustment.voided_by"></span></span><span x-show="adjustment.void_authorized_by">; authorized by <span x-text="adjustment.void_authorized_by"></span></span>
                                            </p>
                                            <p x-show="adjustment.void_reason" class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-text="adjustment.void_reason"></p>
                                        </div>
                                        <button type="button" x-show="adjustment.status !== 'voided'" x-on:click="openVoid(adjustment)" class="{{ $smallDangerButtonClass }}">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.75 7.75h8.5a5.5 5.5 0 1 1-3.9 9.39" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.5 4.5 4.25 7.75 7.5 11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            Void
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <div x-show="detailAdjustments().length === 0" class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-theme-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                No liters deductions have been added for this submission.
                            </div>
                        </div>
                    </div>
                    <div x-show="details.row?.is_correction" class="mt-6">
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Correction Accounting</h4>
                        <div class="mt-3 rounded-xl border border-dashed border-brand-300 bg-brand-50/50 p-4 text-theme-sm text-gray-600 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-gray-300">
                            This row applies only the difference between the generated reading and its corrected replacement. Manual liters deductions do not apply to Net Correction entries.
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    <button type="button" x-on:click="closeDetails()" class="{{ $secondaryButtonClass }}">Close</button>
                </div>
            </div>
        </div>

        <div x-show="deduction.open" x-cloak class="fixed inset-0 z-[100050] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="deduction.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <form
                x-show="deduction.open"
                x-transition
                x-on:click.outside="closeDeduction()"
                x-bind:action="deduction.action"
                method="POST"
                class="relative z-10 flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                @csrf
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Deduct Pump Liters</h3>
                        <p class="mt-1 {{ $captionClass }}">Shift Reading #<span x-text="deduction.row?.id || 'N/A'"></span> | <span x-text="deduction.row?.staff_name || 'Staff'"></span></p>
                    </div>
                    <button type="button" x-on:click="closeDeduction()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200" aria-label="Close deduction modal">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Current submission liters</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatNumber(deduction.row?.total_liters)"></span> L</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Selected pump available</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatNumber(selectedPumpAvailable())"></span> L</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Available sales amount</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatNumber(selectedPumpAvailableAmount())"></span></span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Pump price</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatNumber(selectedPumpPrice())"></span> / L</span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="deduction_pump_number">Pump</label>
                        <select id="deduction_pump_number" name="pump_number" x-model="deduction.pump" x-on:change="deduction.amountManual = false; syncDeductionAmount(true)" class="{{ $inputClass }}" required>
                            <option value="">Choose pump</option>
                            <template x-for="pump in deductionPumps()" :key="pump.pump_id">
                                <option :value="pump.pump_id" :disabled="Number(pump.liters || 0) <= 0" x-text="`P${pump.pump_id} - ${formatNumber(pump.liters)} L available`"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="deduction_liters">Liters to deduct</label>
                        <input id="deduction_liters" name="liters" type="number" min="0.01" step="0.01" x-bind:max="selectedPumpAvailable()" x-model="deduction.liters" x-on:input="syncDeductionAmount()" class="{{ $inputClass }}" required>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="deduction_amount">Amount to deduct</label>
                        <input id="deduction_amount" name="amount" type="number" min="0.01" step="0.01" x-bind:max="selectedPumpAvailableAmount()" x-model="deduction.amount" x-on:input="deduction.amountManual = true" class="{{ $inputClass }}" required>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Auto-calculated from liters x current pump price. You can still edit it.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="deduction_reason">Reason</label>
                        <textarea id="deduction_reason" name="reason" x-model="deduction.reason" class="{{ $textareaClass }}" maxlength="255" placeholder="Optional note"></textarea>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="deduction_password">Semi-admin password</label>
                        <div class="relative">
                            <input id="deduction_password" name="semiadmin_password" x-bind:type="deduction.showPassword ? 'text' : 'password'" x-model="deduction.password" class="{{ $inputClass }} pr-12" autocomplete="current-password" required>
                            <button type="button" x-on:click="deduction.showPassword = !deduction.showPassword" class="absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center justify-center text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" :aria-label="deduction.showPassword ? 'Hide semi-admin password' : 'Show semi-admin password'" :title="deduction.showPassword ? 'Hide password' : 'Show password'">
                                <svg x-show="!deduction.showPassword" class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.75 12s3.5-6.25 9.25-6.25S21.25 12 21.25 12 17.75 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.75 12a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                                <svg x-show="deduction.showPassword" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.75 4.75 20.25 19.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M9.88 5.98A8.71 8.71 0 0 1 12 5.75c5.75 0 9.25 6.25 9.25 6.25a17.2 17.2 0 0 1-2.1 2.83M6.57 7.42C4.17 9.07 2.75 12 2.75 12s3.5 6.25 9.25 6.25c1.37 0 2.61-.35 3.72-.9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.75 10.17a2.75 2.75 0 0 0 3.69 3.65" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="closeDeduction()" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button type="submit" class="{{ $primaryButtonClass }}" :disabled="!canSubmitDeduction()">Save Deduction</button>
                </div>
            </form>
        </div>

        <div x-show="voidDialog.open" x-cloak class="fixed inset-0 z-[100060] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="voidDialog.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <form
                x-show="voidDialog.open"
                x-transition
                x-on:click.outside="closeVoid()"
                x-bind:action="voidDialog.action"
                method="POST"
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                @csrf
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Void Liters Deduction</h3>
                    <p class="mt-1 {{ $captionClass }}">Restore this deduction back into the pump liters and sales.</p>
                </div>
                <div class="space-y-4 p-5">
                    <div class="rounded-xl border border-gray-200 p-4 text-theme-sm dark:border-gray-800">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Pump</span>
                            <span class="font-semibold text-gray-800 dark:text-white/90">P<span x-text="voidDialog.adjustment?.pump_number || 'N/A'"></span></span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Deducted liters</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatNumber(voidDialog.adjustment?.liters)"></span> L</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Deducted amount</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatNumber(voidDialog.adjustment?.amount)"></span></span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="void_reason">Reason</label>
                        <textarea id="void_reason" name="void_reason" x-model="voidDialog.reason" class="{{ $textareaClass }}" maxlength="255" placeholder="Optional note"></textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="void_password">Semi-admin password</label>
                        <div class="relative">
                            <input id="void_password" name="semiadmin_password" x-bind:type="voidDialog.showPassword ? 'text' : 'password'" x-model="voidDialog.password" class="{{ $inputClass }} pr-12" autocomplete="current-password" required>
                            <button type="button" x-on:click="voidDialog.showPassword = !voidDialog.showPassword" class="absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center justify-center text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" :aria-label="voidDialog.showPassword ? 'Hide semi-admin password' : 'Show semi-admin password'" :title="voidDialog.showPassword ? 'Hide password' : 'Show password'">
                                <svg x-show="!voidDialog.showPassword" class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.75 12s3.5-6.25 9.25-6.25S21.25 12 21.25 12 17.75 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.75 12a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                                <svg x-show="voidDialog.showPassword" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.75 4.75 20.25 19.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M9.88 5.98A8.71 8.71 0 0 1 12 5.75c5.75 0 9.25 6.25 9.25 6.25a17.2 17.2 0 0 1-2.1 2.83M6.57 7.42C4.17 9.07 2.75 12 2.75 12s3.5 6.25 9.25 6.25c1.37 0 2.61-.35 3.72-.9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.75 10.17a2.75 2.75 0 0 0 3.69 3.65" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="closeVoid()" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button type="submit" class="{{ $dangerButtonClass }}" :disabled="!voidDialog.password">Void Deduction</button>
                </div>
            </form>
        </div>

        <div x-show="messageDialog.open" x-cloak class="fixed inset-0 z-[100070] flex items-center justify-center overflow-y-auto p-4">
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
        function litersTrackingStaff(config) {
            return {
                rows: config.rows || {},
                canManageAdjustments: Boolean(config.canManageAdjustments),
                toast: { open: false, message: '', timer: null },
                messageDialog: { open: false, title: '', message: '' },
                details: { open: false, row: null },
                deduction: {
                    open: false,
                    row: null,
                    action: '',
                    pump: '',
                    liters: '',
                    amount: '',
                    amountManual: false,
                    reason: '',
                    password: '',
                    showPassword: false,
                },
                voidDialog: {
                    open: false,
                    adjustment: null,
                    action: '',
                    reason: '',
                    password: '',
                    showPassword: false,
                },
                init() {
                    if (config.sessionMessage) {
                        this.showToast(config.sessionMessage);
                    }

                    if (config.sessionError) {
                        this.showMessage('Unable to Continue', config.sessionError);
                    }
                },
                rowFor(id) {
                    return this.rows[String(id)] || this.rows[id] || null;
                },
                openDetails(id) {
                    const row = this.rowFor(id);

                    if (! row) {
                        this.showMessage('Details Unavailable', 'This shift-reading submission is not available on the current page.');
                        return;
                    }

                    this.details = {
                        open: true,
                        row: JSON.parse(JSON.stringify(row)),
                    };
                },
                closeDetails() {
                    this.details = { open: false, row: null };
                },
                openDeduction(id) {
                    if (! this.canManageAdjustments) {
                        this.showMessage('Migration Required', 'Run the liters movement adjustment migration before adding deductions.');
                        return;
                    }

                    const row = this.rowFor(id);

                    if (! row) {
                        this.showMessage('Deduction Unavailable', 'This shift-reading submission is not available on the current page.');
                        return;
                    }

                    if (row.is_correction || ! row.can_adjust_liters) {
                        this.showMessage('Deduction Unavailable', 'Net correction rows already combine the generated reversal and corrected replacement and cannot receive another liters deduction.');
                        return;
                    }

                    const firstPump = (row.pump_entries || []).find((pump) => Number(pump.liters || 0) > 0);

                    this.deduction = {
                        open: true,
                        row: JSON.parse(JSON.stringify(row)),
                        action: String(config.deductionUrlTemplate || '').replace('__READING__', row.id),
                        pump: firstPump ? String(firstPump.pump_id) : '',
                        liters: '',
                        amount: '',
                        amountManual: false,
                        reason: '',
                        password: '',
                        showPassword: false,
                    };
                },
                closeDeduction() {
                    this.deduction = {
                        open: false,
                        row: null,
                        action: '',
                        pump: '',
                        liters: '',
                        amount: '',
                        amountManual: false,
                        reason: '',
                        password: '',
                        showPassword: false,
                    };
                },
                openVoid(adjustment) {
                    if (! adjustment || ! adjustment.id) {
                        this.showMessage('Void Unavailable', 'This deduction is missing its record id.');
                        return;
                    }

                    this.voidDialog = {
                        open: true,
                        adjustment: JSON.parse(JSON.stringify(adjustment)),
                        action: String(config.voidUrlTemplate || '').replace('__DEDUCTION__', adjustment.id),
                        reason: '',
                        password: '',
                        showPassword: false,
                    };
                },
                closeVoid() {
                    this.voidDialog = {
                        open: false,
                        adjustment: null,
                        action: '',
                        reason: '',
                        password: '',
                        showPassword: false,
                    };
                },
                detailPumps() {
                    return this.details.row?.pump_entries || [];
                },
                detailCorrectionPumps() {
                    return this.details.row?.correction_pumps || [];
                },
                detailAdjustments() {
                    return this.details.row?.adjustments || [];
                },
                deductionPumps() {
                    return this.deduction.row?.pump_entries || [];
                },
                selectedPump() {
                    const selected = Number(this.deduction.pump || 0);

                    return this.deductionPumps().find((pump) => Number(pump.pump_id || 0) === selected) || null;
                },
                selectedPumpAvailable() {
                    return Number(this.selectedPump()?.liters || 0);
                },
                selectedPumpAvailableAmount() {
                    return Number(this.selectedPump()?.amount || 0);
                },
                selectedPumpPrice() {
                    return Number(this.selectedPump()?.price_per_liter || 0);
                },
                syncDeductionAmount(force = false) {
                    if (this.deduction.amountManual && ! force) {
                        return;
                    }

                    const liters = Number(this.deduction.liters || 0);
                    const price = this.selectedPumpPrice();

                    this.deduction.amount = liters > 0 && price > 0
                        ? this.roundCurrency(Math.min(liters * price, this.selectedPumpAvailableAmount()))
                        : '';
                },
                roundCurrency(value) {
                    const number = Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;

                    return number > 0 ? number.toFixed(2) : '';
                },
                canSubmitDeduction() {
                    const liters = Number(this.deduction.liters || 0);
                    const amount = Number(this.deduction.amount || 0);

                    return this.canManageAdjustments
                        && Boolean(this.deduction.action)
                        && Boolean(this.deduction.pump)
                        && liters > 0
                        && liters <= this.selectedPumpAvailable()
                        && amount > 0
                        && amount <= this.selectedPumpAvailableAmount()
                        && Boolean(this.deduction.password);
                },
                statusClass(status) {
                    const value = String(status || '').toLowerCase();

                    if (value.includes('verified') || value.includes('approved')) {
                        return 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500';
                    }

                    return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
                },
                formatNumber(value, decimals = 2) {
                    const number = Number(value || 0);

                    return number.toLocaleString(undefined, {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals,
                    });
                },
                closeTopLayer() {
                    if (this.messageDialog.open) {
                        this.closeMessage();
                        return;
                    }

                    if (this.voidDialog.open) {
                        this.closeVoid();
                        return;
                    }

                    if (this.deduction.open) {
                        this.closeDeduction();
                        return;
                    }

                    if (this.details.open) {
                        this.closeDetails();
                    }
                },
                showToast(message) {
                    this.closeToast();
                    this.toast.message = message || 'Action completed.';
                    this.toast.open = true;
                    this.toast.timer = window.setTimeout(() => this.closeToast(), 3200);
                },
                closeToast() {
                    if (this.toast.timer) {
                        window.clearTimeout(this.toast.timer);
                    }

                    this.toast = { open: false, message: '', timer: null };
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
                }
            };
        }
    </script>
@endpush
