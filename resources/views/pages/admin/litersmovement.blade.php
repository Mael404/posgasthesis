@extends('layouts.app')

@section('content')
    @php
        $summary = array_merge([
            'total_liters' => 0,
            'total_amount' => 0,
            'total_sales' => 0,
            'total_discounts' => 0,
            'total_net_sales' => 0,
            'limit' => 4000,
            'remaining_liters' => 4000,
            'over_liters' => 0,
            'percent' => 0,
            'progress_width' => 0,
            'transaction_count' => 0,
            'submission_count' => 0,
            'pump_entry_count' => 0,
            'pump_net_sales' => [],
            'fuel_breakdown' => [],
            'tank_breakdown' => [],
            'staff_breakdown' => [],
            'reached_limits' => [],
            'configured_limit_count' => 0,
            'status' => [
                'key' => 'normal',
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
        $history = $history ?? collect();
        $historyItems = $history instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($history->items())
            : collect($history);
        $tankBreakdown = collect($summary['tank_breakdown'] ?? []);
        $pumpNetSales = collect($summary['pump_net_sales'] ?? []);
        $reachedLimits = collect($summary['reached_limits'] ?? []);
        $missingRequirements = $missingRequirements ?? [];
        $canManageAdjustments = (bool) ($canManageAdjustments ?? false);
        $currentAdminId = (int) auth()->id();

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
        $smallSecondaryButtonClass = 'inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-theme-xs font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200';
        $smallDangerButtonClass = 'inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-error-500 px-3 text-theme-xs font-medium text-white shadow-theme-xs transition hover:bg-error-600';
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
                return [
                    'badge' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                    'dot' => 'bg-warning-500',
                    'bar' => 'bg-warning-500',
                    'border' => 'border-warning-300 dark:border-warning-500/40',
                    'soft' => 'bg-warning-50/70 dark:bg-warning-500/10',
                    'header' => 'bg-warning-500 text-gray-950',
                ];
            }

            if (str_contains($fuel, 'regular') || str_contains($fuel, 'unleaded')) {
                return [
                    'badge' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
                    'dot' => 'bg-success-500',
                    'bar' => 'bg-success-500',
                    'border' => 'border-success-300 dark:border-success-500/40',
                    'soft' => 'bg-success-50/70 dark:bg-success-500/10',
                    'header' => 'bg-success-500 text-white',
                ];
            }

            if (str_contains($fuel, 'premium')) {
                return [
                    'badge' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                    'dot' => 'bg-error-500',
                    'bar' => 'bg-error-500',
                    'border' => 'border-error-300 dark:border-error-500/40',
                    'soft' => 'bg-error-50/70 dark:bg-error-500/10',
                    'header' => 'bg-error-500 text-white',
                ];
            }

            return [
                'badge' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                'dot' => 'bg-brand-500',
                'bar' => 'bg-brand-500',
                'border' => 'border-brand-300 dark:border-brand-500/40',
                'soft' => 'bg-brand-50/70 dark:bg-brand-500/10',
                'header' => 'bg-brand-500 text-white',
            ];
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

        $historyStatusStyle = function ($status) {
            $status = strtolower(trim((string) $status));

            if ($status === 'voided') {
                return ['label' => 'Voided', 'class' => 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300'];
            }

            return ['label' => 'Generated', 'class' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'];
        };

        $historyDetailsPayload = $historyItems->mapWithKeys(function ($record) use ($dateLabel) {
            $snapshot = is_array($record->snapshot ?? null) ? $record->snapshot : [];
            $recordId = (int) ($record->id ?? 0);
            $recordStatus = strtolower(trim((string) ($record->status ?? 'generated'))) ?: 'generated';
            $recordLimit = (float) ($snapshot['liters_limit'] ?? $record->liters_limit ?? 0);
            $recordLiters = (float) ($snapshot['total_liters'] ?? 0);
            $recordSubmissions = (int) ($snapshot['submission_count'] ?? $snapshot['transaction_count'] ?? 0);
            $voidedAt = $record->voided_at ?? ($snapshot['voided_at'] ?? null);
            $recordPumpLiters = collect($snapshot['tank_breakdown'] ?? [])
                ->flatMap(fn ($tank) => collect($tank['entries'] ?? []))
                ->groupBy(fn ($entry) => (int) ($entry['pump_id'] ?? 0))
                ->map(fn ($entries) => (float) $entries->sum(fn ($entry) => (float) ($entry['liters'] ?? 0)));
            $recordPumpNetSales = collect($snapshot['pump_net_sales'] ?? [])->map(function ($pump, $key) use ($recordPumpLiters) {
                $pumpId = (int) ($pump['pump_id'] ?? (is_numeric($key) ? $key : 0));

                return [
                    'pump_id' => $pumpId,
                    'fuel_type' => (string) ($pump['fuel_type'] ?? 'Fuel'),
                    'liters' => (float) ($pump['liters'] ?? $recordPumpLiters->get($pumpId, 0)),
                    'transaction_count' => (int) ($pump['transaction_count'] ?? 0),
                    'total_sales' => (float) ($pump['total_sales'] ?? 0),
                    'discounts' => (float) ($pump['discounts'] ?? 0),
                    'net_sales' => (float) ($pump['net_sales'] ?? 0),
                ];
            })->filter(fn ($pump) => (int) ($pump['pump_id'] ?? 0) > 0)->sortBy('pump_id')->values()->all();
            $recordTotalSales = (float) ($snapshot['total_sales'] ?? collect($recordPumpNetSales)->sum('total_sales'));
            $recordTotalDiscounts = (float) ($snapshot['total_discounts'] ?? collect($recordPumpNetSales)->sum('discounts'));
            $recordTotalNetSales = (float) ($snapshot['total_net_sales'] ?? collect($recordPumpNetSales)->sum('net_sales'));
            $removedSubmissions = collect($snapshot['removed_shift_readings'] ?? [])->map(function ($reading) use ($dateLabel) {
                $periodFrom = $reading['period_from'] ?? null;
                $periodTo = $reading['period_to'] ?? $periodFrom;

                return [
                    'id' => (int) ($reading['id'] ?? 0),
                    'staff_name' => (string) ($reading['staff_name'] ?? 'Staff'),
                    'current_status' => (string) ($reading['current_status'] ?? $reading['removal_status'] ?? 'Removed'),
                    'period_label' => $periodFrom || $periodTo
                        ? $dateLabel($periodFrom, 'M d, Y') . ' to ' . $dateLabel($periodTo, 'M d, Y')
                        : 'N/A',
                    'included_at_label' => $dateLabel($reading['included_at'] ?? null),
                    'included_by' => (string) ($reading['included_by'] ?? ''),
                    'removed_at_label' => $dateLabel($reading['removed_at'] ?? null),
                    'removed_by' => (string) ($reading['removed_by'] ?? ''),
                    'removal_status' => (string) ($reading['removal_status'] ?? 'Removed'),
                    'removal_reason' => (string) ($reading['removal_reason'] ?? ''),
                ];
            })->values()->all();

            $tanks = collect($snapshot['tank_breakdown'] ?? [])->map(function ($tank) use ($dateLabel) {
                $tankName = (string) ($tank['name'] ?? $tank['fuel_type'] ?? 'Tank');
                $fuelType = (string) ($tank['fuel_type'] ?? 'Fuel');

                $entries = collect($tank['entries'] ?? [])->map(function ($entry) use ($dateLabel, $tankName, $fuelType) {
                    $periodFrom = $entry['period_from'] ?? null;
                    $periodTo = $entry['period_to'] ?? null;
                    $periodLabel = $periodFrom || $periodTo
                        ? $dateLabel($periodFrom, 'M d, Y') . ' to ' . $dateLabel($periodTo, 'M d, Y')
                        : 'N/A';
                    $adjustments = collect($entry['adjustments'] ?? [])->map(function ($adjustment) use ($dateLabel) {
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

                    return [
                        'id' => (int) ($entry['id'] ?? 0),
                        'staff_name' => (string) ($entry['staff_name'] ?? 'Staff'),
                        'status' => (string) ($entry['status'] ?? 'Pending'),
                        'created_at_label' => $dateLabel($entry['created_at'] ?? null),
                        'period_label' => $periodLabel,
                        'pump_id' => (int) ($entry['pump_id'] ?? 0),
                        'tank_name' => $tankName,
                        'fuel_type' => $fuelType,
                        'liters' => (float) ($entry['liters'] ?? 0),
                        'raw_liters' => (float) ($entry['raw_liters'] ?? $entry['liters'] ?? 0),
                        'deducted_liters' => (float) ($entry['deducted_liters'] ?? 0),
                        'raw_amount' => (float) ($entry['raw_amount'] ?? $entry['amount'] ?? $entry['total_sales'] ?? 0),
                        'deducted_amount' => (float) ($entry['deducted_amount'] ?? 0),
                        'total_sales' => (float) ($entry['total_sales'] ?? 0),
                        'discounts' => (float) ($entry['discounts'] ?? 0),
                        'net_sales' => (float) ($entry['net_sales'] ?? 0),
                        'sales_transaction_count' => (int) ($entry['sales_transaction_count'] ?? 0),
                        'adjustments' => $adjustments,
                    ];
                })->values()->all();
                $entryRows = collect($entries);

                return [
                    'id' => (string) ($tank['id'] ?? ''),
                    'name' => $tankName,
                    'fuel_type' => $fuelType,
                    'connected_pumps' => (string) ($tank['connected_pumps_display'] ?? 'N/A'),
                    'liters' => (float) ($tank['liters'] ?? 0),
                    'total_sales' => (float) ($tank['total_sales'] ?? $entryRows->sum('total_sales')),
                    'discounts' => (float) ($tank['discounts'] ?? $entryRows->sum('discounts')),
                    'net_sales' => (float) ($tank['net_sales'] ?? $entryRows->sum('net_sales')),
                    'limit' => isset($tank['limit']) ? (float) $tank['limit'] : null,
                    'has_limit' => (bool) ($tank['has_limit'] ?? false),
                    'status_label' => (string) ($tank['status']['label'] ?? 'Normal'),
                    'entries' => $entries,
                ];
            })->values()->all();

            return [$recordId => [
                'id' => $recordId,
                'period_code' => (string) ($record->period_code ?? 'Generated Record'),
                'status' => $recordStatus,
                'status_label' => ucfirst($recordStatus),
                'started_at_label' => $dateLabel($record->started_at ?? null),
                'ended_at_label' => $dateLabel($record->ended_at ?? null),
                'generated_at_label' => $dateLabel($snapshot['generated_at'] ?? $record->ended_at ?? null),
                'generated_by' => (string) ($record->generated_by_name ?? 'Admin'),
                'voided_at_label' => ! empty($voidedAt) ? $dateLabel($voidedAt) : null,
                'voided_by' => (string) ($record->voided_by_name ?? ''),
                'limit' => $recordLimit,
                'total_liters' => $recordLiters,
                'total_sales' => $recordTotalSales,
                'total_discounts' => $recordTotalDiscounts,
                'total_net_sales' => $recordTotalNetSales,
                'submission_count' => $recordSubmissions,
                'pump_entry_count' => (int) ($snapshot['pump_entry_count'] ?? 0),
                'pump_net_sales' => $recordPumpNetSales,
                'tanks' => $tanks,
                'removed_submissions' => $removedSubmissions,
            ]];
        })->all();

        $activeSubmissionsPayload = $transactionItems->mapWithKeys(function ($transaction) use ($dateLabel, $readingStatusStyle, $currentAdminId) {
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
                    'total_sales' => (float) ($entry['total_sales'] ?? 0),
                    'discounts' => (float) ($entry['discounts'] ?? 0),
                    'net_sales' => (float) ($entry['net_sales'] ?? 0),
                    'sales_transaction_count' => (int) ($entry['sales_transaction_count'] ?? 0),
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
                            'net_discounts' => (float) $entries->sum('discounts'),
                            'net_sales' => (float) $entries->sum('net_sales'),
                        ];
                    })
                    ->sortBy('pump_id')
                    ->values()
                    ->all()
                : [];
            $pumpEntries = $pumpEntries->all();
            $adjustments = collect($transaction->liter_adjustments ?? [])->map(function ($adjustment) use ($dateLabel, $currentAdminId) {
                $createdById = (int) ($adjustment['created_by_id'] ?? 0);
                $status = strtolower((string) ($adjustment['status'] ?? 'active'));

                return [
                    'id' => (int) ($adjustment['id'] ?? 0),
                    'pump_number' => (int) ($adjustment['pump_number'] ?? 0),
                    'liters' => (float) ($adjustment['liters'] ?? 0),
                    'amount' => (float) ($adjustment['amount'] ?? 0),
                    'reason' => (string) ($adjustment['reason'] ?? ''),
                    'status' => $status,
                    'created_at_label' => $dateLabel($adjustment['created_at'] ?? null),
                    'created_by_id' => $createdById,
                    'created_by' => (string) ($adjustment['created_by'] ?? 'Staff'),
                    'authorized_by' => (string) ($adjustment['authorized_by'] ?? 'Semi Admin'),
                    'voided_at_label' => ! empty($adjustment['voided_at']) ? $dateLabel($adjustment['voided_at']) : null,
                    'voided_by' => (string) ($adjustment['voided_by'] ?? ''),
                    'void_authorized_by' => (string) ($adjustment['void_authorized_by'] ?? ''),
                    'void_reason' => (string) ($adjustment['void_reason'] ?? ''),
                    'can_void' => $status === 'active' && $createdById > 0 && $createdById === $currentAdminId,
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
                'total_sales' => (float) ($transaction->total_sales ?? 0),
                'total_discounts' => (float) ($transaction->total_discounts ?? 0),
                'total_net_sales' => (float) ($transaction->total_net_sales ?? 0),
                'pump_entries' => $pumpEntries,
                'correction_pumps' => $correctionPumps,
                'adjustments' => $adjustments,
                'active_adjustments_count' => collect($adjustments)->where('status', 'active')->count(),
            ]];
        })->all();

        $printGeneratedAt = now();
        $printGeneratedBy = auth()->user()->name ?? 'Administrator';
        $printPeriodCode = $period ? (string) ($period->period_code ?? 'Active Period') : 'Pending Migration';
        $printReportingPeriod = $period
            ? $dateLabel($period->started_at ?? null, 'M d, Y h:i A') . ' to ' . (! empty($period->ended_at) ? $dateLabel($period->ended_at, 'M d, Y h:i A') : $printGeneratedAt->format('M d, Y h:i A'))
            : 'N/A';
        $printStatus = $period ? ucfirst((string) ($period->status ?? 'active')) : 'Pending Migration';
        $fuelSortOrder = function ($fuel) {
            $fuel = strtolower((string) $fuel);

            if (str_contains($fuel, 'premium')) {
                return 1;
            }

            if (str_contains($fuel, 'unleaded') || str_contains($fuel, 'regular')) {
                return 2;
            }

            if (str_contains($fuel, 'diesel')) {
                return 3;
            }

            return 99;
        };
        $printFuelRows = collect($summary['fuel_breakdown'] ?? [])
            ->sortBy(fn ($fuel) => $fuelSortOrder($fuel['fuel_type'] ?? ''))
            ->values();
        $printTankEntries = $tankBreakdown
            ->flatMap(function ($tank) {
                return collect($tank['entries'] ?? [])->map(function ($entry) use ($tank) {
                    return array_merge($entry, [
                        'tank_name' => (string) ($tank['name'] ?? 'Tank'),
                        'tank_fuel_type' => (string) ($tank['fuel_type'] ?? 'Fuel'),
                    ]);
                });
            })
            ->values();
        $printPumpLiters = $printTankEntries
            ->groupBy(fn ($entry) => (int) ($entry['pump_id'] ?? 0))
            ->map(function ($entries) {
                $first = $entries->first() ?? [];

                return [
                    'fuel_type' => (string) ($first['fuel_type'] ?? 'Fuel'),
                    'liters' => (float) $entries->sum(fn ($entry) => (float) ($entry['liters'] ?? 0)),
                    'reading_count' => $entries->pluck('id')->filter()->unique()->count(),
                ];
            });
        $printPumpRows = $pumpNetSales
            ->map(function ($pump) use ($printPumpLiters) {
                $pumpId = (int) ($pump['pump_id'] ?? 0);
                $movement = $printPumpLiters->get($pumpId, []);

                return [
                    'pump_id' => $pumpId,
                    'fuel_type' => (string) ($pump['fuel_type'] ?? ($movement['fuel_type'] ?? 'Fuel')),
                    'liters' => (float) ($movement['liters'] ?? 0),
                    'records' => (int) ($pump['transaction_count'] ?? 0),
                    'gross' => (float) ($pump['total_sales'] ?? 0),
                    'discount' => (float) ($pump['discounts'] ?? 0),
                    'net' => (float) ($pump['net_sales'] ?? 0),
                ];
            })
            ->sortBy('pump_id')
            ->values();
        $printAdjustmentRows = $printTankEntries
            ->flatMap(function ($entry) use ($dateLabel) {
                return collect($entry['adjustments'] ?? [])->map(function ($adjustment) use ($entry, $dateLabel) {
                    $adjustmentId = (int) ($adjustment['id'] ?? 0);
                    $sortDate = (string) ($adjustment['created_at'] ?? $entry['created_at'] ?? '');

                    return [
                        'dedupe_key' => $adjustmentId > 0
                            ? 'adjustment-' . $adjustmentId
                            : implode('|', [
                                $sortDate,
                                (int) ($adjustment['pump_number'] ?? $entry['pump_id'] ?? 0),
                                (float) ($adjustment['liters'] ?? 0),
                                (float) ($adjustment['amount'] ?? 0),
                                (string) ($adjustment['reason'] ?? ''),
                            ]),
                        'sort_date' => $sortDate,
                        'date_label' => $dateLabel($adjustment['created_at'] ?? $entry['created_at'] ?? null, 'M d, Y'),
                        'pump_number' => (int) ($adjustment['pump_number'] ?? $entry['pump_id'] ?? 0),
                        'liters' => (float) ($adjustment['liters'] ?? 0),
                        'amount' => (float) ($adjustment['amount'] ?? 0),
                        'reason' => (string) ($adjustment['reason'] ?? 'Adjustment'),
                        'authorized_by' => (string) ($adjustment['authorized_by'] ?? 'Administrator'),
                    ];
                });
            })
            ->unique('dedupe_key')
            ->sortBy('sort_date')
            ->values();
        $printShiftReadingRows = $printTankEntries
            ->groupBy(fn ($entry) => (int) ($entry['id'] ?? 0))
            ->map(function ($entries, $id) use ($dateLabel) {
                $first = $entries->first() ?? [];
                $periodFrom = $first['period_from'] ?? null;
                $periodTo = $first['period_to'] ?? null;
                $periodLabel = $periodFrom || $periodTo
                    ? $dateLabel($periodFrom, 'M d, Y') . ' to ' . $dateLabel($periodTo, 'M d, Y')
                    : 'N/A';

                return [
                    'id' => (int) $id,
                    'sort_date' => (string) ($first['created_at'] ?? ''),
                    'date_label' => $dateLabel($first['created_at'] ?? null, 'M d, Y'),
                    'staff_name' => (string) ($first['staff_name'] ?? 'Staff'),
                    'period_label' => $periodLabel,
                    'liters' => (float) $entries->sum(fn ($entry) => (float) ($entry['liters'] ?? 0)),
                    'meter_sales' => (float) $entries->sum(fn ($entry) => (float) ($entry['amount'] ?? 0)),
                    'net_sales' => (float) $entries->sum(fn ($entry) => (float) ($entry['net_sales'] ?? 0)),
                    'status' => (string) ($first['status'] ?? 'Pending'),
                ];
            })
            ->sortBy('sort_date')
            ->values();
    @endphp

    <x-common.page-breadcrumb pageTitle="Liters Movement" />

    <div
        x-data="litersMovementAdmin({
            sessionMessage: @js(session('success') ?: session('status')),
            sessionError: @js(session('error') ?: $errors->first()),
            historyRecords: @js($historyDetailsPayload),
            activeSubmissions: @js($activeSubmissionsPayload),
            canManageAdjustments: @js($canManageAdjustments),
            deductionUrlTemplate: @js(route('admin.liters-movement.deductions.store', ['reading' => '__READING__'])),
            deductionVoidUrlTemplate: @js(route('admin.liters-movement.deductions.void', ['deduction' => '__DEDUCTION__'])),
        })"
        x-init="init()"
        x-effect="document.body.classList.toggle('overflow-hidden', confirmation.open || messageDialog.open || activeDetails.open || deduction.open || deductionVoid.open || historyDetails.open || voidConfirmation.open)"
        x-on:keydown.escape.window="closeTopLayer()"
        class="liters-movement-admin space-y-6">
        <style>
            [x-cloak] { display: none !important; }
            .liters-print-report { display: none; }

            @media print {
                @page { margin: 1cm; size: A4 portrait; }
                html, body {
                    background: #fff !important;
                    margin: 0 !important;
                    min-height: 0 !important;
                    padding: 0 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                body > :not(.min-h-screen) {
                    display: none !important;
                }
                body > .min-h-screen {
                    min-height: 0 !important;
                    overflow: visible !important;
                }
                body > .min-h-screen > :not(.min-w-0),
                body > .min-h-screen > .min-w-0 > header,
                body > .min-h-screen > .min-w-0 > .min-w-0 > :not(.liters-movement-admin),
                .liters-movement-admin > :not(.liters-print-report) {
                    display: none !important;
                }
                body > .min-h-screen > .min-w-0,
                body > .min-h-screen > .min-w-0 > .min-w-0,
                .liters-movement-admin {
                    display: block !important;
                    margin: 0 !important;
                    max-width: none !important;
                    min-height: 0 !important;
                    padding: 0 !important;
                    transition: none !important;
                    width: 100% !important;
                }
                .liters-print-report {
                    box-sizing: border-box;
                    color: #222 !important;
                    display: flex !important;
                    flex-direction: column;
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 10px;
                    line-height: 1.32;
                    min-height: 277mm;
                    position: static;
                    width: 100%;
                }
                .liters-print-main { flex: 1; }
                .liters-report-header {
                    border-bottom: 3px solid #465fff;
                    margin-bottom: 12px;
                    padding-bottom: 10px;
                }
                .liters-header-table, .liters-meta-table, .liters-data-table, .liters-summary-table {
                    border-collapse: collapse;
                    width: 100%;
                }
                .liters-header-table td {
                    border: 0;
                    padding: 0;
                    vertical-align: middle;
                }
                .liters-print-logo {
                    height: auto;
                    max-width: 130px;
                }
                .liters-company-name {
                    color: #465fff;
                    font-size: 22px;
                    font-weight: 900;
                    letter-spacing: 0;
                    margin: 0;
                }
                .liters-company-details { color: #475467; margin: 2px 0 0; }
                .liters-report-title {
                    color: #98a2b3;
                    font-size: 18px;
                    font-weight: 900;
                    letter-spacing: 0;
                    text-align: right;
                    text-transform: uppercase;
                }
                .liters-report-number {
                    color: #d92d20;
                    font-size: 14px;
                    font-weight: 900;
                    margin-top: 5px;
                    text-align: right;
                }
                .liters-meta-box {
                    background: #f8f9fc;
                    border: 1px solid #e4e7ec;
                    border-radius: 8px;
                    margin-bottom: 12px;
                    padding: 10px 12px;
                }
                .liters-meta-table td {
                    border: 0;
                    font-size: 10px;
                    padding: 2px 8px;
                    vertical-align: top;
                }
                .liters-meta-table td:first-child {
                    color: #667085;
                    font-weight: 800;
                    text-transform: uppercase;
                    width: 24%;
                }
                .liters-meta-table td:last-child {
                    color: #101828;
                    font-weight: 700;
                }
                .liters-divider {
                    border-top: 1px solid #101828;
                    margin: 12px 0;
                }
                .liters-section-title {
                    color: #465fff;
                    font-size: 11px;
                    font-weight: 900;
                    margin: 0 0 6px;
                    text-transform: uppercase;
                }
                .liters-summary-grid {
                    display: grid;
                    gap: 12px;
                    grid-template-columns: 1fr 1fr;
                    margin-bottom: 8px;
                    text-align: center;
                }
                .liters-summary-label {
                    color: #667085;
                    font-size: 10px;
                    font-weight: 800;
                    text-transform: uppercase;
                }
                .liters-summary-value {
                    color: #101828;
                    font-size: 18px;
                    font-weight: 900;
                    margin-top: 2px;
                }
                .liters-summary-table {
                    margin-top: 6px;
                    width: 45%;
                }
                .liters-summary-table td {
                    border: 0;
                    border-bottom: 1px solid #e4e7ec;
                    padding: 3px 6px;
                }
                .liters-summary-table td:first-child {
                    color: #475467;
                    font-weight: 700;
                }
                .liters-data-table {
                    margin-bottom: 8px;
                    page-break-inside: auto;
                }
                .liters-data-table th, .liters-data-table td {
                    border: 1px solid #d0d5dd;
                    padding: 4px 5px;
                    vertical-align: top;
                }
                .liters-data-table th {
                    background: #f2f4f7;
                    color: #344054;
                    font-size: 8.5px;
                    font-weight: 900;
                    text-align: left;
                    text-transform: uppercase;
                }
                .liters-data-table tfoot td {
                    background: #f8f9fc;
                    font-weight: 900;
                }
                .liters-amount-col {
                    font-family: "Courier New", monospace;
                    font-weight: 700;
                    text-align: right !important;
                    white-space: nowrap;
                }
                .liters-text-center { text-align: center !important; }
                .liters-bottom-section {
                    margin-top: auto;
                    page-break-inside: avoid;
                    padding-top: 22px;
                }
                .liters-signatures {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 24px;
                }
                .liters-signature-box {
                    text-align: center;
                    width: 40%;
                }
                .liters-sig-heading {
                    color: #101828;
                    font-size: 10px;
                    font-weight: 800;
                    margin: 0 0 18px;
                    text-align: left;
                }
                .liters-sig-line {
                    border-bottom: 1px solid #101828;
                    height: 24px;
                    margin-bottom: 5px;
                }
                .liters-sig-label {
                    color: #344054;
                    font-size: 10px;
                    font-weight: 800;
                    margin: 0;
                }
            }
        </style>

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
                <span class="font-semibold">Tank limit reached:</span>
                {{ $reachedLimits->map(fn ($tank) => ($tank['name'] ?? $tank['fuel_type'] ?? 'Tank') . ' (' . number_format((float) ($tank['liters'] ?? 0), 2) . ' L / ' . number_format((float) ($tank['limit'] ?? 0), 2) . ' L)')->implode(', ') }}.
                Staff can continue submitting shift readings; the limit is an alert and does not block submission.
            </div>
        @endif

        <section class="{{ $cardClass }}">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(360px,520px)] xl:items-start">
                <div class="min-w-0">
                    <p class="{{ $labelClass }}">Admin Module</p>
                    <h1 class="mt-2 text-title-sm font-semibold text-gray-800 dark:text-white/90">Liters Movement</h1>
                    <p class="mt-2 {{ $captionClass }}">
                        Shared period {{ $period->period_code ?? 'Pending Migration' }} started {{ $period ? $dateLabel($period->started_at, 'M d, Y h:i A') : 'after migration' }}. Totals come from submitted staff shift readings.
                    </p>
                </div>

                @if (! $missingRequirements)
                    <div class="grid grid-cols-1 gap-3">
                        <form method="POST" action="{{ route('admin.liters-movement.limit') }}" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                @foreach ($tankBreakdown as $tank)
                                    @php
                                        $style = $fuelStyle($tank['fuel_type'] ?? '');
                                        $limitKey = $tank['limit_key'] ?? '';
                                        $oldLimit = old("tank_limits.{$limitKey}", ($tank['has_limit'] ?? false) ? ($tank['limit'] ?? '') : '');
                                    @endphp
                                    <label class="min-w-0 text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                                        <span class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full {{ $style['dot'] }}"></span>
                                            {{ $tank['fuel_type'] ?? 'Fuel' }}
                                        </span>
                                        <input type="number" name="tank_limits[{{ $limitKey }}]" min="1" step="0.01" value="{{ $oldLimit }}" placeholder="No limit" class="mt-1.5 {{ $inputClass }}">
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="{{ $captionClass }}">Leave a tank blank to skip limit alerts for that tank.</p>
                                <button type="submit" class="{{ $primaryButtonClass }}">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 9.2 16.7 19 6.9" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Update Limits
                                </button>
                            </div>
                        </form>

                        <form x-ref="generateForm" method="POST" action="{{ route('admin.liters-movement.generate') }}" class="hidden">
                            @csrf
                        </form>
                        <a href="{{ route('inventory.reports.index', ['period_id' => $period->id ?? null]) }}" class="{{ $secondaryButtonClass }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4.75h14v14.5H5V4.75Zm3 4h8M8 12h8M8 15.25h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Inventory Report
                        </a>
                        <button type="button" x-on:click="confirmGenerate()" class="{{ $dangerButtonClass }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7.5h14M8 7.5V5.75C8 4.784 8.784 4 9.75 4h4.5C15.216 4 16 4.784 16 5.75V7.5M9 10.75v6M15 10.75v6M6.5 7.5l.75 12A1.75 1.75 0 0 0 9 21.25h6a1.75 1.75 0 0 0 1.75-1.75l.75-12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Generate Record & Reset
                        </button>
                        <button type="button" x-on:click="printReport()" class="{{ $secondaryButtonClass }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 8.25V4.75h10v3.5M7.25 17.25H5.75A1.75 1.75 0 0 1 4 15.5v-4A1.75 1.75 0 0 1 5.75 9.75h12.5A1.75 1.75 0 0 1 20 11.5v4a1.75 1.75 0 0 1-1.75 1.75h-1.5M7.25 14.25h9.5v5h-9.5v-5ZM16.75 12.25h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Print Report
                        </button>
                    </div>
                @endif
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Total Liters</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['total_liters'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">&#8369;{{ number_format((float) $summary['total_amount'], 2) }} meter sales</p>
            </article>
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Configured Limits</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['limit'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">{{ number_format((int) ($summary['configured_limit_count'] ?? 0)) }} active tank limit{{ (int) ($summary['configured_limit_count'] ?? 0) === 1 ? '' : 's' }}</p>
            </article>
            <article class="{{ $cardClass }}">
                <p class="{{ $labelClass }}">Remaining Before Limits</p>
                <p class="{{ $valueClass }}">{{ number_format((float) $summary['remaining_liters'], 2) }} L</p>
                <p class="mt-1 {{ $captionClass }}">{{ (float) $summary['over_liters'] > 0 ? number_format((float) $summary['over_liters'], 2) . ' L over configured limits' : 'Across configured tanks' }}</p>
            </article>
            <article class="{{ $cardClass }} {{ $summary['status']['border_class'] }}">
                <p class="{{ $labelClass }}">Reached Tanks</p>
                <p class="{{ $valueClass }}">{{ number_format($reachedLimits->count()) }}</p>
                <p class="mt-1 {{ $captionClass }}">{{ number_format((int) $summary['submission_count']) }} included shift submission{{ (int) $summary['submission_count'] === 1 ? '' : 's' }}</p>
            </article>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Net Sales Per Pump</h2>
                    <p class="mt-1 {{ $captionClass }}">Submitted pump sales from Pending and Verified shift readings, less their automatic and manual discounts. Rejected and Voided readings are excluded.</p>
                </div>
                <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-left dark:border-brand-500/25 dark:bg-brand-500/10 sm:text-right">
                    <p class="{{ $labelClass }} text-brand-600 dark:text-brand-400">Overall Net Sales</p>
                    <p class="mt-1 text-title-sm font-bold tabular-nums text-brand-700 dark:text-brand-300">&#8369;{{ number_format((float) $summary['total_net_sales'], 2) }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[900px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[15%]">
                            <col class="w-[16%]">
                            <col class="w-[14%]">
                            <col class="w-[13%]">
                            <col class="w-[14%]">
                            <col class="w-[14%]">
                            <col class="w-[14%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Pump</th>
                                <th class="{{ $thClass }}">Fuel</th>
                                <th class="{{ $thNumberClass }}">Total Liters</th>
                                <th class="{{ $thNumberClass }}">Sales Records</th>
                                <th class="{{ $thNumberClass }}">Total Sales</th>
                                <th class="{{ $thNumberClass }}">Discounts</th>
                                <th class="{{ $thNumberClass }}">Net Sales</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($pumpNetSales as $pump)
                                @php
                                    $style = $fuelStyle($pump['fuel_type'] ?? '');
                                @endphp
                                <tr>
                                    <td class="{{ $tdPrimaryClass }}">{{ $pump['pump_label'] ?? 'Pump ' . (int) ($pump['pump_id'] ?? 0) }}</td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $style['badge'] }}">{{ $pump['fuel_type'] ?? 'N/A' }}</span>
                                    </td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format((float) ($pump['liters'] ?? 0), 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format((int) ($pump['transaction_count'] ?? 0)) }}</td>
                                    <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) ($pump['total_sales'] ?? 0), 2) }}</td>
                                    <td class="{{ $tdNumberClass }} text-error-600 dark:text-error-400">-&#8369;{{ number_format((float) ($pump['discounts'] ?? 0), 2) }}</td>
                                    <td class="{{ $tdNumberClass }} font-semibold text-gray-800 dark:text-white/90">&#8369;{{ number_format((float) ($pump['net_sales'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-200 bg-gray-50 font-semibold dark:border-gray-800 dark:bg-white/[0.03]">
                            <tr>
                                <td colspan="2" class="{{ $tdPrimaryClass }}">Overall</td>
                                <td class="{{ $tdNumberClass }}">{{ number_format($pumpNetSales->sum(fn ($pump) => (float) ($pump['liters'] ?? 0)), 2) }} L</td>
                                <td class="{{ $tdNumberClass }}">{{ number_format($pumpNetSales->sum(fn ($pump) => (int) ($pump['transaction_count'] ?? 0))) }}</td>
                                <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $summary['total_sales'], 2) }}</td>
                                <td class="{{ $tdNumberClass }} text-error-600 dark:text-error-400">-&#8369;{{ number_format((float) $summary['total_discounts'], 2) }}</td>
                                <td class="{{ $tdNumberClass }} font-bold text-brand-600 dark:text-brand-400">&#8369;{{ number_format((float) $summary['total_net_sales'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </section>

        <section class="{{ $cardClass }}">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tank Progress</h2>
                    <p class="mt-1 {{ $captionClass }}">Active tracking period by tank limit.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @foreach ($tankBreakdown as $tank)
                    @php
                        $style = $fuelStyle($tank['fuel_type'] ?? '');
                    @endphp
                    <div class="rounded-xl border p-4 {{ $style['border'] }} {{ $style['soft'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ $tank['name'] ?? 'Tank' }}</p>
                                <p class="mt-1 {{ $captionClass }}">{{ $tank['connected_pumps_display'] ?? 'N/A' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $tank['status']['badge_class'] ?? '' }}">{{ $tank['status']['label'] ?? 'Normal' }}</span>
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-4">
                            <p class="text-xl font-bold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($tank['liters'] ?? 0), 2) }} L</p>
                            <p class="{{ $captionClass }}">{{ ($tank['has_limit'] ?? false) ? number_format((float) ($tank['limit'] ?? 0), 2) . ' L limit' : 'No limit' }}</p>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/70 dark:bg-white/[0.08]">
                            <div class="h-full rounded-full {{ $style['bar'] }}" style="width: {{ $tank['progress_width'] ?? 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Movement Tables</h2>
                    <p class="mt-1 {{ $captionClass }}">Review active tank ledgers, staff totals, included submissions, and generated records.</p>
                </div>

                <div class="max-w-full overflow-x-auto">
                    <div class="inline-flex min-w-full gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] sm:min-w-0" role="tablist" aria-label="Liters movement tables">
                        <button
                            type="button"
                            role="tab"
                            x-on:click="setTableTab('tank-ledgers')"
                            :aria-selected="tableTab === 'tank-ledgers'"
                            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg px-3 text-theme-sm font-medium transition"
                            :class="tableTab === 'tank-ledgers' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200'">
                            Tank Ledgers
                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-theme-xs tabular-nums text-gray-600 dark:bg-white/[0.08] dark:text-gray-300">{{ number_format($tankBreakdown->count()) }}</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            x-on:click="setTableTab('staff-breakdown')"
                            :aria-selected="tableTab === 'staff-breakdown'"
                            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg px-3 text-theme-sm font-medium transition"
                            :class="tableTab === 'staff-breakdown' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200'">
                            Staff Breakdown
                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-theme-xs tabular-nums text-gray-600 dark:bg-white/[0.08] dark:text-gray-300">{{ number_format(count($summary['staff_breakdown'] ?? [])) }}</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            x-on:click="setTableTab('shift-submissions')"
                            :aria-selected="tableTab === 'shift-submissions'"
                            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg px-3 text-theme-sm font-medium transition"
                            :class="tableTab === 'shift-submissions' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200'">
                            Shift Submissions
                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-theme-xs tabular-nums text-gray-600 dark:bg-white/[0.08] dark:text-gray-300">{{ number_format((int) $summary['submission_count']) }}</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            x-on:click="setTableTab('generated-history')"
                            :aria-selected="tableTab === 'generated-history'"
                            class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg px-3 text-theme-sm font-medium transition"
                            :class="tableTab === 'generated-history' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200'">
                            Generated History
                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-theme-xs tabular-nums text-gray-600 dark:bg-white/[0.08] dark:text-gray-300">{{ number_format($historyItems->count()) }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="tableTab === 'tank-ledgers'" x-cloak role="tabpanel" class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tank Ledgers</h2>
                    <p class="mt-1 {{ $captionClass }}">Each submitted pump meter delta is assigned to its designated tank and uses the same discount-adjusted net sales as the Net Sales card.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($tankBreakdown as $tank)
                        @php
                            $style = $fuelStyle($tank['fuel_type'] ?? '');
                            $entries = collect($tank['entries'] ?? []);
                            $tankGrossSales = (float) ($tank['total_sales'] ?? $entries->sum(fn ($entry) => (float) ($entry['total_sales'] ?? $entry['amount'] ?? 0)));
                            $tankDiscounts = (float) ($tank['discounts'] ?? $entries->sum(fn ($entry) => (float) ($entry['discounts'] ?? 0)));
                            $tankNetSales = (float) ($tank['net_sales'] ?? $entries->sum(fn ($entry) => (float) ($entry['net_sales'] ?? 0)));
                        @endphp
                        <article class="{{ $cardClass }} flex h-[520px] flex-col overflow-hidden p-0">
                            <div class="px-4 py-3 {{ $style['header'] }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-semibold">{{ $tank['fuel_type'] ?? 'Fuel' }}</p>
                                        <p class="mt-1 line-clamp-2 text-theme-xs opacity-90">{{ $tank['name'] ?? 'Tank' }} | {{ $tank['connected_pumps_display'] ?? 'N/A' }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-white/20 px-2.5 py-1 text-theme-xs font-semibold">
                                        {{ ($tank['has_limit'] ?? false) ? number_format((float) ($tank['limit'] ?? 0), 2) . ' L' : 'No limit' }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                                <div>
                                    <p class="{{ $labelClass }}">Liters</p>
                                    <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($tank['liters'] ?? 0), 2) }} L</p>
                                </div>
                                <div>
                                    <p class="{{ $labelClass }} text-brand-600 dark:text-brand-400">Net Sales</p>
                                    <p class="mt-1 text-theme-sm font-bold tabular-nums text-brand-700 dark:text-brand-300">&#8369;{{ number_format($tankNetSales, 2) }}</p>
                                </div>
                                <div>
                                    <p class="{{ $labelClass }}">Sales</p>
                                    <p class="mt-1 text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;{{ number_format($tankGrossSales, 2) }}</p>
                                </div>
                                <div>
                                    <p class="{{ $labelClass }}">Discount</p>
                                    <p class="mt-1 text-theme-sm font-semibold tabular-nums text-error-600 dark:text-error-400">-&#8369;{{ number_format($tankDiscounts, 2) }}</p>
                                </div>
                            </div>

                            <div class="min-h-0 flex-1 overflow-x-auto">
                                <div class="flex h-full min-w-[430px] flex-col">
                                    <div class="shrink-0 grid grid-cols-[0.55fr_0.9fr_1.15fr_1fr] border-b border-gray-100 bg-gray-50 px-4 py-2 text-theme-xs font-semibold uppercase text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                                        <div>Pump</div>
                                        <div class="text-right">Qty</div>
                                        <div class="text-right">Sales / Disc</div>
                                        <div class="text-right">Net</div>
                                    </div>

                                    <div class="min-h-0 flex-1 overflow-y-auto">
                                        @forelse ($entries as $entry)
                                            @php
                                                $entryGrossSales = (float) ($entry['total_sales'] ?? $entry['amount'] ?? 0);
                                                $entryDiscounts = (float) ($entry['discounts'] ?? 0);
                                                $entryNetSales = (float) ($entry['net_sales'] ?? ($entryGrossSales - $entryDiscounts));
                                            @endphp
                                            <div class="grid grid-cols-[0.55fr_0.9fr_1.15fr_1fr] items-center border-b border-gray-100 px-4 py-2.5 text-theme-xs last:border-b-0 dark:border-gray-800">
                                                <div class="font-semibold text-gray-800 dark:text-white/90">P{{ (int) ($entry['pump_id'] ?? 0) }}</div>
                                                <div class="text-right tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) ($entry['liters'] ?? 0), 2) }} L</div>
                                                <div class="text-right tabular-nums">
                                                    <p class="text-gray-700 dark:text-gray-300">&#8369;{{ number_format($entryGrossSales, 2) }}</p>
                                                    <p class="text-error-600 dark:text-error-400">-&#8369;{{ number_format($entryDiscounts, 2) }}</p>
                                                </div>
                                                <div class="text-right font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;{{ number_format($entryNetSales, 2) }}</div>
                                            </div>
                                        @empty
                                            <div class="px-4 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                                No active-period records.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div x-show="tableTab === 'staff-breakdown'" x-cloak role="tabpanel" class="{{ $cardClass }}">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Staff Breakdown</h2>
                    <p class="mt-1 {{ $captionClass }}">Active tracking totals grouped by staff.</p>
                </div>
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="max-w-full overflow-x-auto">
                        <table class="min-w-[640px] w-full table-fixed">
                            <colgroup>
                                <col class="w-[40%]">
                                <col class="w-[20%]">
                                <col class="w-[20%]">
                                <col class="w-[20%]">
                            </colgroup>
                            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                <tr>
                                    <th class="{{ $thClass }}">Staff</th>
                                    <th class="{{ $thNumberClass }}">Liters</th>
                                    <th class="{{ $thNumberClass }}">Amount</th>
                                    <th class="{{ $thNumberClass }}">Submissions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($summary['staff_breakdown'] as $staff)
                                    <tr>
                                        <td class="{{ $tdPrimaryClass }}">{{ $staff['staff_name'] }}</td>
                                        <td class="{{ $tdNumberClass }}">{{ number_format((float) $staff['liters'], 2) }} L</td>
                                        <td class="{{ $tdNumberClass }}">&#8369;{{ number_format((float) $staff['amount'], 2) }}</td>
                                        <td class="{{ $tdNumberClass }}">{{ number_format((int) ($staff['submission_count'] ?? $staff['transaction_count'] ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                            No pending or verified shift submissions included yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div x-show="tableTab === 'shift-submissions'" x-cloak role="tabpanel" class="{{ $cardClass }}">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Included Shift Reading Submissions</h2>
                    <p class="mt-1 {{ $captionClass }}">Pending and verified submissions share the same global tank limits.</p>
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
                                            <button type="button" x-on:click="openActiveDetails({{ $transactionId }})" class="{{ $smallSecondaryButtonClass }}" title="View shift-reading movement and deductions">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.75 12s3.5-6.25 9.25-6.25S21.25 12 21.25 12 17.75 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.75 12a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                                                View
                                            </button>
                                            <button type="button" x-on:click="openDeduction({{ $transactionId }})" class="{{ $smallPrimaryButtonClass }}" title="Deduct pump liters and amount" @disabled(! $canManageAdjustments || ! ($transaction->can_adjust_liters ?? true))>
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
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
            </div>

            <div x-show="tableTab === 'generated-history'" x-cloak role="tabpanel" class="{{ $cardClass }}">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Generated History</h2>
                <p class="mt-1 {{ $captionClass }}">Saved records from previous tracking periods.</p>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <div class="max-w-full overflow-x-auto">
                    <table class="min-w-[1120px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[17%]">
                            <col class="w-[13%]">
                            <col class="w-[13%]">
                            <col class="w-[10%]">
                            <col class="w-[10%]">
                            <col class="w-[15%]">
                            <col class="w-[10%]">
                            <col class="w-[12%]">
                        </colgroup>
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr>
                                <th class="{{ $thClass }}">Period</th>
                                <th class="{{ $thClass }}">Started</th>
                                <th class="{{ $thClass }}">Ended</th>
                                <th class="{{ $thNumberClass }}">Limit</th>
                                <th class="{{ $thNumberClass }}">Liters</th>
                                <th class="{{ $thClass }}">Generated By</th>
                                <th class="{{ $thClass }}">Status</th>
                                <th class="{{ $thNumberClass }}">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($historyItems as $record)
                                @php
                                    $snapshot = $record->snapshot ?? [];
                                    $recordId = (int) ($record->id ?? 0);
                                    $recordStatus = strtolower(trim((string) ($record->status ?? 'generated'))) ?: 'generated';
                                    $historyStatus = $historyStatusStyle($recordStatus);
                                    $recordLiters = (float) ($snapshot['total_liters'] ?? 0);
                                    $recordLimit = (float) ($snapshot['liters_limit'] ?? $record->liters_limit ?? 0);
                                    $recordSubmissions = (int) ($snapshot['submission_count'] ?? $snapshot['transaction_count'] ?? 0);
                                    $voidedAt = $record->voided_at ?? ($snapshot['voided_at'] ?? null);
                                @endphp
                                <tr>
                                    <td class="{{ $tdPrimaryClass }}">
                                        <div class="truncate" title="{{ $record->period_code }}">{{ $record->period_code }}</div>
                                        <div class="mt-1 text-theme-xs font-normal text-gray-500 dark:text-gray-400">{{ number_format($recordSubmissions) }} submissions</div>
                                    </td>
                                    <td class="{{ $tdClass }}">{{ $dateLabel($record->started_at ?? null) }}</td>
                                    <td class="{{ $tdClass }}">{{ $dateLabel($record->ended_at ?? null) }}</td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format($recordLimit, 2) }} L</td>
                                    <td class="{{ $tdNumberClass }}">{{ number_format($recordLiters, 2) }} L</td>
                                    <td class="{{ $tdClass }}">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $record->generated_by_name ?? 'Admin' }}</div>
                                        @if ($recordStatus === 'voided')
                                            <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                                Voided{{ ! empty($record->voided_by_name) ? ' by ' . $record->voided_by_name : '' }}{{ ! empty($voidedAt) ? ' on ' . $dateLabel($voidedAt) : '' }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium {{ $historyStatus['class'] }}">{{ $historyStatus['label'] }}</span>
                                    </td>
                                    <td class="{{ $tdClass }}">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" x-on:click="openHistoryDetails({{ $recordId }})" class="{{ $smallSecondaryButtonClass }}" title="View included shift-reading submissions">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.75 12s3.5-6.25 9.25-6.25S21.25 12 21.25 12 17.75 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.75 12a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0Z" stroke="currentColor" stroke-width="1.7"/></svg>
                                                Details
                                            </button>

                                            @if ($recordStatus === 'generated')
                                                <form id="void-liters-period-{{ $recordId }}" method="POST" action="{{ route('admin.liters-movement.history.void', $recordId) }}" class="hidden">
                                                    @csrf
                                                </form>
                                                <button type="button" x-on:click="confirmVoid(@js($record->period_code), 'void-liters-period-{{ $recordId }}', {{ $recordLiters }}, {{ $recordSubmissions }})" class="{{ $smallDangerButtonClass }}" title="Void this generated record and restore its included readings">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.75 7.75h8.5a5.5 5.5 0 1 1-3.9 9.39" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.5 4.5 4.25 7.75 7.5 11" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    Void
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                                        No generated records yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($history instanceof \Illuminate\Contracts\Pagination\Paginator && $history->hasPages())
                <div class="mt-5">
                    {{ $history->links() }}
                </div>
            @endif
            </div>
        </section>

        <section class="liters-print-report" aria-hidden="true">
            <div class="liters-print-main">
                <header class="liters-report-header">
                    <table class="liters-header-table">
                        <tr>
                            <td style="width: 20%;">
                                <img src="{{ asset('images/logojrs.png') }}" alt="JR-M Fuel Station Logo" class="liters-print-logo">
                            </td>
                            <td style="width: 45%;">
                                <h1 class="liters-company-name">JR-M FUEL STATION</h1>
                                <p class="liters-company-details">Sitio Apad, Calongay, Pilar Sorsogon</p>
                            </td>
                            <td style="width: 35%;">
                                <div class="liters-report-title">Liters Movement and Net Sales Report</div>
                                <div class="liters-report-number">{{ $printPeriodCode }}</div>
                            </td>
                        </tr>
                    </table>
                </header>

                <section class="liters-meta-box">
                    <table class="liters-meta-table">
                        <tr>
                            <td>Period Code:</td>
                            <td>{{ $printPeriodCode }}</td>
                        </tr>
                        <tr>
                            <td>Reporting Period:</td>
                            <td>{{ $printReportingPeriod }}</td>
                        </tr>
                        <tr>
                            <td>Generated Date:</td>
                            <td>{{ $printGeneratedAt->format('M d, Y h:i A') }}</td>
                        </tr>
                        <tr>
                            <td>Generated By:</td>
                            <td>{{ $printGeneratedBy }}</td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td>{{ $printStatus }}</td>
                        </tr>
                    </table>
                </section>

                <div class="liters-divider"></div>

                <section>
                    <h2 class="liters-section-title">Period Summary</h2>
                    <div class="liters-summary-grid">
                        <div>
                            <div class="liters-summary-label">Total Liters Movement</div>
                            <div class="liters-summary-value">{{ number_format((float) $summary['total_liters'], 2) }} L</div>
                        </div>
                        <div>
                            <div class="liters-summary-label">Net Sales</div>
                            <div class="liters-summary-value">&#8369;{{ number_format((float) $summary['total_net_sales'], 2) }}</div>
                        </div>
                    </div>
                    <table class="liters-summary-table">
                        <tr>
                            <td>Gross Sales</td>
                            <td class="liters-amount-col">&#8369;{{ number_format((float) $summary['total_sales'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Discounts</td>
                            <td class="liters-amount-col">&#8369;{{ number_format((float) $summary['total_discounts'], 2) }}</td>
                        </tr>
                        <tr>
                            <td>Submissions</td>
                            <td class="liters-amount-col">{{ number_format((int) $summary['submission_count']) }}</td>
                        </tr>
                    </table>
                </section>

                <div class="liters-divider"></div>

                <section>
                    <h2 class="liters-section-title">Liters Movement by Fuel / Tank</h2>
                    <table class="liters-data-table">
                        <thead>
                            <tr>
                                <th style="width: 34%;">Fuel / Tank</th>
                                <th style="width: 22%;" class="liters-amount-col">Liters</th>
                                <th style="width: 24%;" class="liters-amount-col">Meter Sales</th>
                                <th style="width: 20%;" class="liters-amount-col">Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($printFuelRows as $fuel)
                                <tr>
                                    <td>{{ $fuel['fuel_type'] ?? 'Fuel' }}</td>
                                    <td class="liters-amount-col">{{ number_format((float) ($fuel['liters'] ?? 0), 2) }} L</td>
                                    <td class="liters-amount-col">&#8369;{{ number_format((float) ($fuel['amount'] ?? 0), 2) }}</td>
                                    <td class="liters-amount-col">{{ number_format((int) ($fuel['transaction_count'] ?? 0)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="liters-text-center">No fuel movement recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td class="liters-amount-col">{{ number_format((float) $summary['total_liters'], 2) }} L</td>
                                <td class="liters-amount-col">&#8369;{{ number_format((float) $summary['total_amount'], 2) }}</td>
                                <td class="liters-amount-col">{{ number_format((int) $summary['pump_entry_count']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <div class="liters-divider"></div>

                <section>
                    <h2 class="liters-section-title">Pump Sales and Liters Movement</h2>
                    <table class="liters-data-table">
                        <thead>
                            <tr>
                                <th style="width: 9%;">Pump</th>
                                <th style="width: 17%;">Fuel</th>
                                <th style="width: 14%;" class="liters-amount-col">Liters</th>
                                <th style="width: 11%;" class="liters-amount-col">Records</th>
                                <th style="width: 17%;" class="liters-amount-col">Gross</th>
                                <th style="width: 15%;" class="liters-amount-col">Discount</th>
                                <th style="width: 17%;" class="liters-amount-col">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($printPumpRows as $pump)
                                <tr>
                                    <td>P{{ (int) ($pump['pump_id'] ?? 0) }}</td>
                                    <td>{{ $pump['fuel_type'] ?? 'Fuel' }}</td>
                                    <td class="liters-amount-col">{{ number_format((float) ($pump['liters'] ?? 0), 2) }} L</td>
                                    <td class="liters-amount-col">{{ number_format((int) ($pump['records'] ?? 0)) }}</td>
                                    <td class="liters-amount-col">&#8369;{{ number_format((float) ($pump['gross'] ?? 0), 2) }}</td>
                                    <td class="liters-amount-col">&#8369;{{ number_format((float) ($pump['discount'] ?? 0), 2) }}</td>
                                    <td class="liters-amount-col">&#8369;{{ number_format((float) ($pump['net'] ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="liters-text-center">No pump movement recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">Total</td>
                                <td class="liters-amount-col">{{ number_format((float) $summary['total_liters'], 2) }} L</td>
                                <td class="liters-amount-col">{{ number_format($pumpNetSales->sum(fn ($pump) => (int) ($pump['transaction_count'] ?? 0))) }}</td>
                                <td class="liters-amount-col">&#8369;{{ number_format((float) $summary['total_sales'], 2) }}</td>
                                <td class="liters-amount-col">&#8369;{{ number_format((float) $summary['total_discounts'], 2) }}</td>
                                <td class="liters-amount-col">&#8369;{{ number_format((float) $summary['total_net_sales'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </section>

                <div class="liters-divider"></div>

                <section>
                    <h2 class="liters-section-title">Liters and Sales Adjustments</h2>
                    <table class="liters-data-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 9%;">Pump</th>
                                <th style="width: 17%;" class="liters-amount-col">Liters Deducted</th>
                                <th style="width: 16%;" class="liters-amount-col">Amount</th>
                                <th style="width: 27%;">Reason</th>
                                <th style="width: 16%;">Authorized By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($printAdjustmentRows as $adjustment)
                                <tr>
                                    <td>{{ $adjustment['date_label'] }}</td>
                                    <td>P{{ (int) ($adjustment['pump_number'] ?? 0) }}</td>
                                    <td class="liters-amount-col">{{ number_format((float) ($adjustment['liters'] ?? 0), 2) }} L</td>
                                    <td class="liters-amount-col">&#8369;{{ number_format((float) ($adjustment['amount'] ?? 0), 2) }}</td>
                                    <td>{{ $adjustment['reason'] }}</td>
                                    <td>{{ $adjustment['authorized_by'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="liters-text-center">No active liters or sales adjustments.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <div class="liters-divider"></div>

                <section>
                    <h2 class="liters-section-title">Included Shift Readings</h2>
                    <table class="liters-data-table">
                        <thead>
                            <tr>
                                <th style="width: 12%;">Date</th>
                                <th style="width: 17%;">Staff</th>
                                <th style="width: 20%;">Period</th>
                                <th style="width: 13%;" class="liters-amount-col">Liters</th>
                                <th style="width: 15%;" class="liters-amount-col">Meter Sales</th>
                                <th style="width: 15%;" class="liters-amount-col">Net Sales</th>
                                <th style="width: 8%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($printShiftReadingRows as $reading)
                                <tr>
                                    <td>{{ $reading['date_label'] }}</td>
                                    <td>{{ $reading['staff_name'] }}</td>
                                    <td>{{ $reading['period_label'] }}</td>
                                    <td class="liters-amount-col">{{ number_format((float) ($reading['liters'] ?? 0), 2) }} L</td>
                                    <td class="liters-amount-col">&#8369;{{ number_format((float) ($reading['meter_sales'] ?? 0), 2) }}</td>
                                    <td class="liters-amount-col">&#8369;{{ number_format((float) ($reading['net_sales'] ?? 0), 2) }}</td>
                                    <td>{{ $reading['status'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="liters-text-center">No included shift readings.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <div class="liters-divider"></div>

                <section>
                    <h2 class="liters-section-title">Final Report Totals</h2>
                    <table class="liters-summary-table">
                        <tr>
                            <td>Net Liters Movement</td>
                            <td class="liters-amount-col">{{ number_format((float) $summary['total_liters'], 2) }} L</td>
                        </tr>
                        <tr>
                            <td>Net Sales</td>
                            <td class="liters-amount-col">&#8369;{{ number_format((float) $summary['total_net_sales'], 2) }}</td>
                        </tr>
                    </table>
                </section>
            </div>

        </section>

        <div x-show="activeDetails.open" x-cloak class="fixed inset-0 z-[100035] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="activeDetails.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="activeDetails.open"
                x-transition
                x-on:click.outside="closeActiveDetails()"
                class="relative z-10 flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Shift Reading #<span x-text="activeDetails.row?.id"></span></h3>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium" :class="submissionStatusClass(activeDetails.row?.status)" x-text="activeDetails.row?.status_label || 'Pending'"></span>
                        </div>
                        <p class="mt-1 {{ $captionClass }}"><span x-text="activeDetails.row?.staff_name || 'Staff'"></span> | <span x-text="activeDetails.row?.period_label || 'N/A'"></span></p>
                    </div>
                    <button type="button" x-on:click="closeActiveDetails()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200" aria-label="Close shift reading details">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Submitted</p>
                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="activeDetails.row?.submitted_at_label || 'N/A'"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}" x-text="activeDetails.row?.is_correction ? 'Net Liters' : 'Current Liters'"></p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatHistoryNumber(activeDetails.row?.total_liters)"></span> L</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}" x-text="activeDetails.row?.is_correction ? 'Net Meter Sales' : 'Meter Sales'"></p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(activeDetails.row?.total_amount)"></span></p>
                        </div>
                        <div class="rounded-xl border border-brand-200 bg-brand-50/60 p-4 dark:border-brand-500/25 dark:bg-brand-500/10">
                            <p class="{{ $labelClass }} text-brand-600 dark:text-brand-400">Net Sales</p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-brand-700 dark:text-brand-300">&#8369;<span x-text="formatHistoryNumber(activeDetails.row?.total_net_sales)"></span></p>
                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">&#8369;<span x-text="formatHistoryNumber(activeDetails.row?.total_discounts)"></span><span x-text="activeDetails.row?.is_correction ? ' net discounts' : ' discounts'"></span></p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90" x-text="activeDetails.row?.is_correction ? 'Correction Breakdown' : 'Pump Movement'"></h4>
                        <p x-show="activeDetails.row?.is_correction" class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">
                            Original Shift Reading #<span x-text="activeDetails.row?.original_shift_reading_id || 'N/A'"></span> was reversed
                            <span x-show="activeDetails.row?.replacement_shift_reading_id">and replaced by Shift Reading #<span x-text="activeDetails.row?.replacement_shift_reading_id"></span>.</span>
                            <span x-show="!activeDetails.row?.replacement_shift_reading_id">and is awaiting a corrected replacement.</span>
                            Original and replacement cells show liters with meter sales underneath.
                        </p>
                        <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="max-w-full overflow-x-auto">
                                <table class="min-w-[1040px] w-full table-fixed">
                                    <colgroup>
                                        <col class="w-[9%]">
                                        <col class="w-[13%]">
                                        <col class="w-[13%]">
                                        <col class="w-[13%]">
                                        <col class="w-[13%]">
                                        <col class="w-[13%]">
                                        <col class="w-[13%]">
                                        <col class="w-[13%]">
                                    </colgroup>
                                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                        <tr x-show="!activeDetails.row?.is_correction">
                                            <th class="{{ $thClass }}">Pump</th>
                                            <th class="{{ $thClass }}">Fuel</th>
                                            <th class="{{ $thNumberClass }}">Original</th>
                                            <th class="{{ $thNumberClass }}">Deducted</th>
                                            <th class="{{ $thNumberClass }}">Current</th>
                                            <th class="{{ $thNumberClass }}">Total Sales</th>
                                            <th class="{{ $thNumberClass }}">Discounts</th>
                                            <th class="{{ $thNumberClass }}">Net Sales</th>
                                        </tr>
                                        <tr x-show="activeDetails.row?.is_correction">
                                            <th class="{{ $thClass }}">Pump</th>
                                            <th class="{{ $thClass }}">Fuel</th>
                                            <th class="{{ $thNumberClass }}">Original</th>
                                            <th class="{{ $thNumberClass }}">Replacement</th>
                                            <th class="{{ $thNumberClass }}">Net Liters</th>
                                            <th class="{{ $thNumberClass }}">Net Meter Sales</th>
                                            <th class="{{ $thNumberClass }}">Net Discounts</th>
                                            <th class="{{ $thNumberClass }}">Net Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody x-show="!activeDetails.row?.is_correction" class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <template x-for="pump in activePumps()" :key="pump.pump_id">
                                            <tr>
                                                <td class="{{ $tdPrimaryClass }}">P<span x-text="pump.pump_id"></span></td>
                                                <td class="{{ $tdClass }}" x-text="pump.fuel_type"></td>
                                                <td class="{{ $tdNumberClass }}"><span x-text="formatHistoryNumber(pump.raw_liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }} text-error-600 dark:text-error-400"><span x-text="formatHistoryNumber(pump.deducted_liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }}"><span x-text="formatHistoryNumber(pump.liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }}">&#8369;<span x-text="formatHistoryNumber(pump.total_sales)"></span></td>
                                                <td class="{{ $tdNumberClass }} text-error-600 dark:text-error-400">-&#8369;<span x-text="formatHistoryNumber(pump.discounts)"></span></td>
                                                <td class="{{ $tdNumberClass }} font-semibold text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(pump.net_sales)"></span></td>
                                            </tr>
                                        </template>
                                        <tr x-show="activePumps().length === 0">
                                            <td colspan="8" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No pump movement found for this submission.</td>
                                        </tr>
                                    </tbody>
                                    <tbody x-show="activeDetails.row?.is_correction" class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <template x-for="pump in activeCorrectionPumps()" :key="`correction-${pump.pump_id}`">
                                            <tr>
                                                <td class="{{ $tdPrimaryClass }}">P<span x-text="pump.pump_id"></span></td>
                                                <td class="{{ $tdClass }}" x-text="pump.fuel_type"></td>
                                                <td class="{{ $tdNumberClass }}">
                                                    <span><span x-text="formatHistoryNumber(pump.original_liters)"></span> L</span>
                                                    <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">&#8369;<span x-text="formatHistoryNumber(pump.original_amount)"></span></span>
                                                </td>
                                                <td class="{{ $tdNumberClass }}">
                                                    <span><span x-text="formatHistoryNumber(pump.replacement_liters)"></span> L</span>
                                                    <span class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">&#8369;<span x-text="formatHistoryNumber(pump.replacement_amount)"></span></span>
                                                </td>
                                                <td class="{{ $tdNumberClass }} font-semibold" :class="Number(pump.net_liters || 0) < 0 ? 'text-error-600 dark:text-error-400' : (Number(pump.net_liters || 0) > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-800 dark:text-white/90')"><span x-text="formatHistoryNumber(pump.net_liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }}">&#8369;<span x-text="formatHistoryNumber(pump.net_amount)"></span></td>
                                                <td class="{{ $tdNumberClass }}">&#8369;<span x-text="formatHistoryNumber(pump.net_discounts)"></span></td>
                                                <td class="{{ $tdNumberClass }} font-semibold text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(pump.net_sales)"></span></td>
                                            </tr>
                                        </template>
                                        <tr x-show="activeCorrectionPumps().length === 0">
                                            <td colspan="8" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No correction entries were found for this submission.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div x-show="!activeDetails.row?.is_correction" class="mt-6">
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Deduction History</h4>
                        <div class="mt-3 space-y-3">
                            <template x-for="adjustment in activeAdjustments()" :key="adjustment.id">
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-gray-800 dark:text-white/90">P<span x-text="adjustment.pump_number"></span> - <span x-text="formatHistoryNumber(adjustment.liters)"></span> L / &#8369;<span x-text="formatHistoryNumber(adjustment.amount)"></span></p>
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium" :class="adjustment.status === 'voided' ? 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300' : 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400'" x-text="adjustment.status === 'voided' ? 'Voided' : 'Active'"></span>
                                            </div>
                                            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Added <span x-text="adjustment.created_at_label"></span> by <span x-text="adjustment.created_by"></span>; authorized by <span x-text="adjustment.authorized_by"></span></p>
                                            <p x-show="adjustment.reason" class="mt-1 text-theme-sm text-gray-600 dark:text-gray-300" x-text="adjustment.reason"></p>
                                            <p x-show="adjustment.status === 'voided'" class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                                                Voided <span x-text="adjustment.voided_at_label || 'N/A'"></span><span x-show="adjustment.voided_by"> by <span x-text="adjustment.voided_by"></span></span><span x-show="adjustment.void_authorized_by">; authorized by <span x-text="adjustment.void_authorized_by"></span></span>
                                            </p>
                                            <p x-show="adjustment.void_reason" class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-text="adjustment.void_reason"></p>
                                        </div>
                                        <button
                                            x-show="adjustment.can_void"
                                            type="button"
                                            x-on:click="openDeductionVoid(adjustment)"
                                            class="{{ $smallDangerButtonClass }} shrink-0"
                                            title="Void this deduction">
                                            Void
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <div x-show="activeAdjustments().length === 0" class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-theme-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                No liters deductions have been added for this submission.
                            </div>
                        </div>
                    </div>
                    <div x-show="activeDetails.row?.is_correction" class="mt-6">
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Correction Accounting</h4>
                        <div class="mt-3 rounded-xl border border-dashed border-brand-300 bg-brand-50/50 p-4 text-theme-sm text-gray-600 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-gray-300">
                            This row applies only the difference between the generated reading and its corrected replacement. Manual liters deductions do not apply to Net Correction entries.
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    <button type="button" x-on:click="closeActiveDetails()" class="{{ $secondaryButtonClass }}">Close</button>
                </div>
            </div>
        </div>

        <div x-show="deduction.open" x-cloak class="fixed inset-0 z-[100050] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="deduction.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <form
                x-show="deduction.open"
                x-transition
                x-on:click.outside="closeDeduction()"
                x-on:submit="if (!canSubmitDeduction()) $event.preventDefault()"
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
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatHistoryNumber(deduction.row?.total_liters)"></span> L</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Selected pump available</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatHistoryNumber(selectedPumpAvailable())"></span> L</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Available sales amount</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(selectedPumpAvailableAmount())"></span></span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Pump price</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(selectedPumpPrice())"></span> / L</span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="admin_deduction_pump_number">Pump</label>
                        <select id="admin_deduction_pump_number" name="pump_number" x-model="deduction.pump" x-on:change="deduction.amountManual = false; syncDeductionAmount(true)" class="{{ $inputClass }}" required>
                            <option value="">Choose pump</option>
                            <template x-for="pump in deductionPumps()" :key="pump.pump_id">
                                <option :value="pump.pump_id" :disabled="Number(pump.liters || 0) <= 0" x-text="`P${pump.pump_id} - ${formatHistoryNumber(pump.liters)} L available`"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="admin_deduction_liters">Liters to Deduct <span class="text-error-500">*</span></label>
                        <input id="admin_deduction_liters" name="liters" type="number" min="0.01" step="0.01" x-bind:max="selectedPumpAvailable()" x-model="deduction.liters" x-on:input="syncDeductionAmount()" class="{{ $inputClass }}" required>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="admin_deduction_amount">Amount to Deduct <span class="text-error-500">*</span></label>
                        <input id="admin_deduction_amount" name="amount" type="number" min="0.01" step="0.01" x-bind:max="selectedPumpAvailableAmount()" x-model="deduction.amount" x-on:input="deduction.amountManual = true" class="{{ $inputClass }}" required>
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">Auto-calculated from liters x current pump price. You can still edit it.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="admin_deduction_reason">Reason</label>
                        <textarea id="admin_deduction_reason" name="reason" x-model="deduction.reason" class="{{ $textareaClass }}" maxlength="255" placeholder="Optional note"></textarea>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="closeDeduction()" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button type="submit" class="{{ $primaryButtonClass }}" :disabled="!canSubmitDeduction()">Save Deduction</button>
                </div>
            </form>
        </div>

        <div x-show="deductionVoid.open" x-cloak class="fixed inset-0 z-[100060] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="deductionVoid.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <form
                x-show="deductionVoid.open"
                x-transition
                x-on:click.outside="closeDeductionVoid()"
                x-on:submit="if (!deductionVoid.action) $event.preventDefault()"
                x-bind:action="deductionVoid.action"
                method="POST"
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                @csrf
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Void Your Deduction</h3>
                    <p class="mt-1 {{ $captionClass }}">This restores the deducted liters and amount to the staff submission.</p>
                </div>

                <div class="space-y-4 p-5">
                    <div class="rounded-xl border border-gray-200 p-4 text-theme-sm dark:border-gray-800">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Pump</span>
                            <span class="font-semibold text-gray-800 dark:text-white/90">P<span x-text="deductionVoid.adjustment?.pump_number || 'N/A'"></span></span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Deducted liters</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatHistoryNumber(deductionVoid.adjustment?.liters)"></span> L</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Deducted amount</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(deductionVoid.adjustment?.amount)"></span></span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300" for="admin_deduction_void_reason">Void reason</label>
                        <textarea id="admin_deduction_void_reason" name="void_reason" x-model="deductionVoid.reason" class="{{ $textareaClass }}" maxlength="255" placeholder="Optional note"></textarea>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="closeDeductionVoid()" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button type="submit" class="{{ $dangerButtonClass }}" :disabled="!deductionVoid.action">Void Deduction</button>
                </div>
            </form>
        </div>

        <div x-show="historyDetails.open" x-cloak class="fixed inset-0 z-[100040] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="historyDetails.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="historyDetails.open"
                x-transition
                x-on:click.outside="closeHistoryDetails()"
                class="relative z-10 flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-lg font-semibold text-gray-800 dark:text-white/90" x-text="historyDetails.record?.period_code || 'Generated Record'"></h3>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium"
                                :class="historyDetails.record?.status === 'voided' ? 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300' : 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400'"
                                x-text="historyDetails.record?.status_label || 'Generated'"></span>
                        </div>
                        <p class="mt-1 {{ $captionClass }}">Included shift-reading submissions and pump movement saved in this generated record.</p>
                    </div>
                    <button type="button" x-on:click="closeHistoryDetails()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200" aria-label="Close history details">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Liters</p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatHistoryNumber(historyDetails.record?.total_liters)"></span> L</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Submissions</p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90" x-text="formatHistoryNumber(historyDetails.record?.submission_count, 0)"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Total Sales</p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(historyDetails.record?.total_sales)"></span></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Discounts</p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-error-600 dark:text-error-400">&#8369;<span x-text="formatHistoryNumber(historyDetails.record?.total_discounts)"></span></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }} text-brand-600 dark:text-brand-400">Net Sales</p>
                            <p class="mt-2 text-title-sm font-bold tabular-nums text-brand-700 dark:text-brand-300">&#8369;<span x-text="formatHistoryNumber(historyDetails.record?.total_net_sales)"></span></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Generated</p>
                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="historyDetails.record?.generated_at_label || 'N/A'"></p>
                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-text="historyDetails.record?.generated_by || 'Admin'"></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="{{ $labelClass }}">Tracking Period</p>
                            <p class="mt-2 text-theme-sm font-semibold text-gray-800 dark:text-white/90" x-text="historyDetails.record?.started_at_label || 'N/A'"></p>
                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">to <span x-text="historyDetails.record?.ended_at_label || 'N/A'"></span></p>
                        </div>
                    </div>

                    <div x-show="historyDetails.record?.status === 'voided'" class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-theme-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                        Voided<span x-show="historyDetails.record?.voided_by"> by <span x-text="historyDetails.record?.voided_by"></span></span><span x-show="historyDetails.record?.voided_at_label"> on <span x-text="historyDetails.record?.voided_at_label"></span></span>. The included submissions were restored to the active liters movement period.
                    </div>

                    <div class="mt-6">
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Saved Sales Per Pump</h4>
                        <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="max-w-full overflow-x-auto">
                                <table class="min-w-[940px] w-full table-fixed">
                                    <colgroup>
                                        <col class="w-[10%]">
                                        <col class="w-[16%]">
                                        <col class="w-[14%]">
                                        <col class="w-[14%]">
                                        <col class="w-[16%]">
                                        <col class="w-[14%]">
                                        <col class="w-[16%]">
                                    </colgroup>
                                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                        <tr>
                                            <th class="{{ $thClass }}">Pump</th>
                                            <th class="{{ $thClass }}">Fuel</th>
                                            <th class="{{ $thNumberClass }}">Total Liters</th>
                                            <th class="{{ $thNumberClass }}">Transactions</th>
                                            <th class="{{ $thNumberClass }}">Total Sales</th>
                                            <th class="{{ $thNumberClass }}">Discounts</th>
                                            <th class="{{ $thNumberClass }}">Net Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <template x-for="pump in historyPumpSales()" :key="`history-sales-${pump.pump_id}`">
                                            <tr>
                                                <td class="{{ $tdPrimaryClass }}">P<span x-text="pump.pump_id"></span></td>
                                                <td class="{{ $tdClass }}" x-text="pump.fuel_type"></td>
                                                <td class="{{ $tdNumberClass }}"><span x-text="formatHistoryNumber(pump.liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }}" x-text="formatHistoryNumber(pump.transaction_count, 0)"></td>
                                                <td class="{{ $tdNumberClass }}">&#8369;<span x-text="formatHistoryNumber(pump.total_sales)"></span></td>
                                                <td class="{{ $tdNumberClass }} text-error-600 dark:text-error-400">&#8369;<span x-text="formatHistoryNumber(pump.discounts)"></span></td>
                                                <td class="{{ $tdNumberClass }} font-semibold text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(pump.net_sales)"></span></td>
                                            </tr>
                                        </template>
                                        <tr x-show="historyPumpSales().length === 0">
                                            <td colspan="7" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No saved sales breakdown was found in this record.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Per Tank Summary</h4>
                        <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                            <div class="max-w-full overflow-x-auto">
                                <table class="min-w-[760px] w-full table-fixed">
                                    <colgroup>
                                        <col class="w-[28%]">
                                        <col class="w-[18%]">
                                        <col class="w-[22%]">
                                        <col class="w-[16%]">
                                        <col class="w-[16%]">
                                    </colgroup>
                                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                        <tr>
                                            <th class="{{ $thClass }}">Tank</th>
                                            <th class="{{ $thClass }}">Fuel</th>
                                            <th class="{{ $thClass }}">Pumps</th>
                                            <th class="{{ $thNumberClass }}">Liters</th>
                                            <th class="{{ $thNumberClass }}">Limit</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        <template x-for="tank in historyTanks()" :key="tank.id || tank.name">
                                            <tr>
                                                <td class="{{ $tdPrimaryClass }}" x-text="tank.name"></td>
                                                <td class="{{ $tdClass }}" x-text="tank.fuel_type"></td>
                                                <td class="{{ $tdClass }}" x-text="tank.connected_pumps"></td>
                                                <td class="{{ $tdNumberClass }}"><span x-text="formatHistoryNumber(tank.liters)"></span> L</td>
                                                <td class="{{ $tdNumberClass }}" x-text="formatTankLimit(tank)"></td>
                                            </tr>
                                        </template>
                                        <tr x-show="historyTanks().length === 0">
                                            <td colspan="5" class="px-5 py-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">No tank movement was saved in this record.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Included Shift Reading Submissions</h4>
                        <div class="mt-3 space-y-3">
                            <template x-for="submission in historySubmissions()" :key="submission.key">
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-gray-800 dark:text-white/90">Shift Reading #<span x-text="submission.id"></span></p>
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-theme-xs font-medium" :class="submissionStatusClass(submission.status)" x-text="submission.status"></span>
                                            </div>
                                            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Submitted <span x-text="submission.created_at_label"></span> by <span class="font-medium text-gray-700 dark:text-gray-300" x-text="submission.staff_name"></span></p>
                                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-text="submission.period_label"></p>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3 text-left sm:grid-cols-4 sm:text-right">
                                            <div>
                                                <p class="{{ $labelClass }}">Total Liters</p>
                                                <p class="mt-1 font-semibold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatHistoryNumber(submission.total_liters)"></span> L</p>
                                            </div>
                                            <div>
                                                <p class="{{ $labelClass }}">Total Sales</p>
                                                <p class="mt-1 font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(submission.total_sales)"></span></p>
                                            </div>
                                            <div>
                                                <p class="{{ $labelClass }}">Discounts</p>
                                                <p class="mt-1 font-semibold tabular-nums text-error-600 dark:text-error-400">&#8369;<span x-text="formatHistoryNumber(submission.total_discounts)"></span></p>
                                            </div>
                                            <div>
                                                <p class="{{ $labelClass }} text-brand-600 dark:text-brand-400">Net Sales</p>
                                                <p class="mt-1 font-semibold tabular-nums text-brand-700 dark:text-brand-300">&#8369;<span x-text="formatHistoryNumber(submission.total_net_sales)"></span></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                                        <table class="min-w-[980px] w-full table-fixed">
                                            <colgroup>
                                                <col class="w-[9%]">
                                                <col class="w-[17%]">
                                                <col class="w-[12%]">
                                                <col class="w-[11%]">
                                                <col class="w-[13%]">
                                                <col class="w-[11%]">
                                                <col class="w-[13%]">
                                                <col class="w-[7%]">
                                                <col class="w-[7%]">
                                            </colgroup>
                                            <thead class="bg-gray-50 dark:bg-white/[0.02]">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Pump</th>
                                                    <th class="px-3 py-2 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Tank</th>
                                                    <th class="px-3 py-2 text-left text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Fuel</th>
                                                    <th class="px-3 py-2 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Txns</th>
                                                    <th class="px-3 py-2 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total Sales</th>
                                                    <th class="px-3 py-2 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Discounts</th>
                                                    <th class="px-3 py-2 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Net Sales</th>
                                                    <th class="px-3 py-2 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Deducted</th>
                                                    <th class="px-3 py-2 text-right text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Current</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                                <template x-for="pump in submission.pumps" :key="`${submission.key}-${pump.pump_id}-${pump.tank_name}`">
                                                    <tr>
                                                        <td class="px-3 py-2 text-theme-sm text-gray-700 dark:text-gray-300">P<span x-text="pump.pump_id"></span></td>
                                                        <td class="px-3 py-2 text-theme-sm text-gray-700 dark:text-gray-300" x-text="pump.tank_name"></td>
                                                        <td class="px-3 py-2 text-theme-sm text-gray-700 dark:text-gray-300" x-text="pump.fuel_type"></td>
                                                        <td class="px-3 py-2 text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300" x-text="formatHistoryNumber(pump.sales_transaction_count, 0)"></td>
                                                        <td class="px-3 py-2 text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300">&#8369;<span x-text="formatHistoryNumber(pump.total_sales)"></span></td>
                                                        <td class="px-3 py-2 text-right text-theme-sm tabular-nums text-error-600 dark:text-error-400">&#8369;<span x-text="formatHistoryNumber(pump.discounts)"></span></td>
                                                        <td class="px-3 py-2 text-right text-theme-sm font-semibold tabular-nums text-gray-800 dark:text-white/90">&#8369;<span x-text="formatHistoryNumber(pump.net_sales)"></span></td>
                                                        <td class="px-3 py-2 text-right text-theme-sm tabular-nums text-error-600 dark:text-error-400"><span x-text="formatHistoryNumber(pump.deducted_liters)"></span> L</td>
                                                        <td class="px-3 py-2 text-right text-theme-sm tabular-nums text-gray-700 dark:text-gray-300"><span x-text="formatHistoryNumber(pump.liters)"></span> L</td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div x-show="submission.adjustments.length > 0" class="mt-4 rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.02]">
                                        <p class="{{ $labelClass }}">Deduction History</p>
                                        <div class="mt-2 space-y-2">
                                            <template x-for="adjustment in submission.adjustments" :key="`${submission.key}-adjustment-${adjustment.id}`">
                                                <div class="flex flex-col gap-1 text-theme-sm text-gray-700 dark:text-gray-300 sm:flex-row sm:items-center sm:justify-between">
                                                    <div class="min-w-0">
                                                        <span class="font-semibold text-gray-800 dark:text-white/90">P<span x-text="adjustment.pump_number"></span> - <span x-text="formatHistoryNumber(adjustment.liters)"></span> L / &#8369;<span x-text="formatHistoryNumber(adjustment.amount)"></span></span>
                                                        <span class="ml-2 inline-flex rounded-full px-2 py-0.5 text-theme-xs font-medium" :class="adjustment.status === 'voided' ? 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300' : 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400'" x-text="adjustment.status === 'voided' ? 'Voided' : 'Active'"></span>
                                                        <p x-show="adjustment.reason" class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400" x-text="adjustment.reason"></p>
                                                    </div>
                                                    <p class="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400">Authorized by <span x-text="adjustment.authorized_by"></span></p>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div x-show="historySubmissions().length === 0" class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-theme-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                No shift-reading submissions were saved in this record.
                            </div>
                        </div>
                    </div>

                    <div x-show="historyRemovedSubmissions().length > 0" class="mt-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Removal Audit Trail</h4>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-700 dark:bg-white/[0.06] dark:text-gray-300"><span x-text="historyRemovedSubmissions().length"></span> removed</span>
                        </div>
                        <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">These submissions were once associated with this generated record but are excluded from every displayed total.</p>
                        <div class="mt-3 space-y-3">
                            <template x-for="submission in historyRemovedSubmissions()" :key="`removed-${submission.id}`">
                                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.02]">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-gray-800 dark:text-white/90">Shift Reading #<span x-text="submission.id"></span></p>
                                                <span class="inline-flex rounded-full bg-error-50 px-2.5 py-1 text-theme-xs font-medium text-error-700 dark:bg-error-500/15 dark:text-error-400" x-text="submission.removal_status || submission.current_status"></span>
                                            </div>
                                            <p class="mt-1 text-theme-sm text-gray-600 dark:text-gray-400"><span x-text="submission.staff_name"></span> &middot; <span x-text="submission.period_label"></span></p>
                                            <p x-show="submission.removal_reason" class="mt-2 text-theme-sm text-gray-700 dark:text-gray-300" x-text="submission.removal_reason"></p>
                                        </div>
                                        <div class="shrink-0 text-theme-xs text-gray-500 dark:text-gray-400 sm:text-right">
                                            <p>Included <span x-text="submission.included_at_label"></span><span x-show="submission.included_by"> by <span x-text="submission.included_by"></span></span></p>
                                            <p class="mt-1">Removed <span x-text="submission.removed_at_label"></span><span x-show="submission.removed_by"> by <span x-text="submission.removed_by"></span></span></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    <button type="button" x-on:click="closeHistoryDetails()" class="{{ $secondaryButtonClass }}">Close</button>
                </div>
            </div>
        </div>

        <div x-show="voidConfirmation.open" x-cloak class="fixed inset-0 z-[100050] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="voidConfirmation.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="voidConfirmation.open"
                x-transition
                x-on:click.outside="resolveVoid(false)"
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Void Generated Record</h3>
                    <p class="mt-1 {{ $captionClass }}">Return the included shift-reading submissions to the active liters movement period?</p>
                </div>
                <div class="space-y-3 p-5">
                    <div class="rounded-xl border border-gray-200 p-4 text-theme-sm dark:border-gray-800">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Period</span>
                            <span class="max-w-[12rem] truncate font-semibold text-gray-800 dark:text-white/90" x-text="voidConfirmation.periodCode"></span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Submissions to restore</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90" x-text="formatHistoryNumber(voidConfirmation.submissions, 0)"></span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Liters to restore</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90"><span x-text="formatHistoryNumber(voidConfirmation.liters)"></span> L</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="resolveVoid(false)" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button x-ref="voidPrimary" type="button" x-on:click="resolveVoid(true)" class="{{ $dangerButtonClass }}">Void & Restore</button>
                </div>
            </div>
        </div>

        <div x-show="confirmation.open" x-cloak class="fixed inset-0 z-[100020] flex items-center justify-center overflow-y-auto p-4">
            <div x-show="confirmation.open" x-transition.opacity class="absolute inset-0 bg-gray-900/55 backdrop-blur-sm"></div>
            <div
                x-show="confirmation.open"
                x-transition
                x-on:click.outside="resolveConfirmation(false)"
                class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Generate Record & Reset</h3>
                    <p class="mt-1 {{ $captionClass }}">Save the active tracking summary and start a new period at 0 liters?</p>
                </div>
                <div class="space-y-3 p-5">
                    <div class="rounded-xl border border-gray-200 p-4 text-theme-sm dark:border-gray-800">
                        <div class="flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Current Liters</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) $summary['total_liters'], 2) }} L</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Shift submissions</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((int) $summary['submission_count']) }}</span>
                        </div>
                        <div class="mt-2 flex justify-between gap-4">
                            <span class="text-gray-500 dark:text-gray-400">Limit</span>
                            <span class="font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ number_format((float) $summary['limit'], 2) }} L</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="resolveConfirmation(false)" class="{{ $secondaryButtonClass }}">Cancel</button>
                    <button x-ref="confirmPrimary" type="button" x-on:click="resolveConfirmation(true)" class="{{ $dangerButtonClass }}">Generate & Reset</button>
                </div>
            </div>
        </div>

        <div x-show="messageDialog.open" x-cloak class="fixed inset-0 z-[100030] flex items-center justify-center overflow-y-auto p-4">
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
        function litersMovementAdmin(config) {
            return {
                toast: { open: false, message: '', timer: null },
                confirmation: { open: false },
                messageDialog: { open: false, title: '', message: '' },
                tableTab: 'tank-ledgers',
                tableTabs: ['tank-ledgers', 'staff-breakdown', 'shift-submissions', 'generated-history'],
                activeSubmissions: config.activeSubmissions || {},
                activeDetails: { open: false, row: null },
                canManageAdjustments: Boolean(config.canManageAdjustments),
                deduction: {
                    open: false,
                    row: null,
                    action: '',
                    pump: '',
                    liters: '',
                    amount: '',
                    amountManual: false,
                    reason: '',
                },
                deductionVoid: {
                    open: false,
                    adjustment: null,
                    action: '',
                    reason: '',
                },
                historyRecords: config.historyRecords || {},
                historyDetails: { open: false, record: null },
                voidConfirmation: { open: false, periodCode: '', formId: '', liters: 0, submissions: 0 },
                init() {
                    const hashTab = String(window.location.hash || '').replace('#', '');

                    if (this.tableTabs.includes(hashTab)) {
                        this.tableTab = hashTab;
                    }

                    if (config.sessionMessage) {
                        this.showToast(config.sessionMessage);
                    }

                    if (config.sessionError) {
                        this.showMessage('Unable to Continue', config.sessionError);
                    }
                },
                setTableTab(tab) {
                    if (! this.tableTabs.includes(tab)) {
                        return;
                    }

                    this.tableTab = tab;

                    if (window.history && typeof window.history.replaceState === 'function') {
                        window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${tab}`);
                    }
                },
                async confirmGenerate() {
                    if (window.Swal && typeof window.Swal.fire === 'function') {
                        const result = await window.Swal.fire({
                            title: 'Generate Record & Reset?',
                            text: 'This saves the active liters movement and starts a new tracking period.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Generate & Reset',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#d92d20'
                        });

                        if (result.isConfirmed) {
                            this.$refs.generateForm.submit();
                        }

                        return;
                    }

                    this.confirmation.open = true;
                    this.$nextTick(() => {
                        if (this.$refs.confirmPrimary) {
                            this.$refs.confirmPrimary.focus();
                        }
                    });
                },
                resolveConfirmation(confirmed) {
                    this.confirmation.open = false;

                    if (confirmed && this.$refs.generateForm) {
                        this.$refs.generateForm.submit();
                    }
                },
                printReport() {
                    this.$nextTick(() => window.print());
                },
                openActiveDetails(recordId) {
                    const row = this.activeSubmissions[String(recordId)] || this.activeSubmissions[recordId] || null;

                    if (! row) {
                        this.showMessage('Details Unavailable', 'This shift-reading submission is not available on the current page.');
                        return;
                    }

                    this.activeDetails = {
                        open: true,
                        row: JSON.parse(JSON.stringify(row)),
                    };
                },
                closeActiveDetails() {
                    this.activeDetails = { open: false, row: null };
                },
                activePumps() {
                    return this.activeDetails.row?.pump_entries || [];
                },
                activeCorrectionPumps() {
                    return this.activeDetails.row?.correction_pumps || [];
                },
                activeAdjustments() {
                    return this.activeDetails.row?.adjustments || [];
                },
                openDeduction(recordId) {
                    if (! this.canManageAdjustments) {
                        this.showMessage('Migration Required', 'Run the liters movement adjustment migrations before adding deductions.');
                        return;
                    }

                    const row = this.activeSubmissions[String(recordId)] || this.activeSubmissions[recordId] || null;

                    if (! row) {
                        this.showMessage('Deduction Unavailable', 'This shift-reading submission is not available on the current page.');
                        return;
                    }

                    if (row.is_correction || ! row.can_adjust_liters) {
                        this.showMessage('Deduction Unavailable', 'Net correction rows already combine the generated reversal and corrected replacement and cannot receive another liters deduction.');
                        return;
                    }

                    const firstPump = (row.pump_entries || []).find((pump) => Number(pump.liters || 0) > 0 && Number(pump.amount || 0) > 0);

                    if (! firstPump) {
                        this.showMessage('Deduction Unavailable', 'This submission has no remaining pump liters and sales amount to deduct.');
                        return;
                    }

                    this.deduction = {
                        open: true,
                        row: JSON.parse(JSON.stringify(row)),
                        action: String(config.deductionUrlTemplate || '').replace('__READING__', row.id),
                        pump: String(firstPump.pump_id),
                        liters: '',
                        amount: '',
                        amountManual: false,
                        reason: '',
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
                    };
                },
                openDeductionVoid(adjustment) {
                    if (! adjustment || ! adjustment.id || ! adjustment.can_void || adjustment.status !== 'active') {
                        this.showMessage('Void Unavailable', 'Only an active deduction created by your admin account can be voided.');
                        return;
                    }

                    this.deductionVoid = {
                        open: true,
                        adjustment: JSON.parse(JSON.stringify(adjustment)),
                        action: String(config.deductionVoidUrlTemplate || '').replace('__DEDUCTION__', adjustment.id),
                        reason: '',
                    };
                },
                closeDeductionVoid() {
                    this.deductionVoid = {
                        open: false,
                        adjustment: null,
                        action: '',
                        reason: '',
                    };
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
                        && amount <= this.selectedPumpAvailableAmount();
                },
                openHistoryDetails(recordId) {
                    const record = this.historyRecords[String(recordId)] || this.historyRecords[recordId] || null;

                    if (! record) {
                        this.showMessage('Details Unavailable', 'This generated record has no saved details to display.');
                        return;
                    }

                    this.historyDetails = {
                        open: true,
                        record: JSON.parse(JSON.stringify(record)),
                    };
                },
                closeHistoryDetails() {
                    this.historyDetails = { open: false, record: null };
                },
                historyTanks() {
                    return this.historyDetails.record?.tanks || [];
                },
                historyPumpSales() {
                    return this.historyDetails.record?.pump_net_sales || [];
                },
                historyRemovedSubmissions() {
                    return this.historyDetails.record?.removed_submissions || [];
                },
                historySubmissions() {
                    const grouped = new Map();

                    this.historyTanks().forEach((tank) => {
                        (tank.entries || []).forEach((entry, index) => {
                            const id = Number(entry.id || 0);
                            const key = id > 0 ? String(id) : `${tank.id || tank.name}-${entry.pump_id || index}`;

                            if (! grouped.has(key)) {
                                grouped.set(key, {
                                    key,
                                    id: id > 0 ? id : 'N/A',
                                    staff_name: entry.staff_name || 'Staff',
                                    status: entry.status || 'Pending',
                                    created_at_label: entry.created_at_label || 'N/A',
                                    period_label: entry.period_label || 'N/A',
                                    total_liters: 0,
                                    total_sales: 0,
                                    total_discounts: 0,
                                    total_net_sales: 0,
                                    sales_transaction_count: 0,
                                    pumps: [],
                                    adjustments: [],
                                });
                            }

                            const submission = grouped.get(key);
                            const liters = Number(entry.liters || 0);
                            const totalSales = Number(entry.total_sales || 0);
                            const discounts = Number(entry.discounts || 0);
                            const netSales = Number(entry.net_sales || 0);
                            const salesTransactionCount = Number(entry.sales_transaction_count || 0);

                            submission.total_liters = Math.round((submission.total_liters + liters) * 100) / 100;
                            submission.total_sales = Math.round((submission.total_sales + totalSales) * 100) / 100;
                            submission.total_discounts = Math.round((submission.total_discounts + discounts) * 100) / 100;
                            submission.total_net_sales = Math.round((submission.total_net_sales + netSales) * 100) / 100;
                            submission.sales_transaction_count += salesTransactionCount;
                            submission.pumps.push({
                                pump_id: entry.pump_id || 0,
                                tank_name: entry.tank_name || tank.name || 'Tank',
                                fuel_type: entry.fuel_type || tank.fuel_type || 'Fuel',
                                liters,
                                raw_liters: Number(entry.raw_liters || liters),
                                deducted_liters: Number(entry.deducted_liters || 0),
                                total_sales: totalSales,
                                discounts,
                                net_sales: netSales,
                                sales_transaction_count: salesTransactionCount,
                            });

                            (entry.adjustments || []).forEach((adjustment) => {
                                const adjustmentId = Number(adjustment.id || 0);
                                const exists = submission.adjustments.some((item) => Number(item.id || 0) === adjustmentId && adjustmentId > 0);

                                if (! exists) {
                                    submission.adjustments.push(adjustment);
                                }
                            });
                        });
                    });

                    return Array.from(grouped.values());
                },
                formatHistoryNumber(value, decimals = 2) {
                    const number = Number(value || 0);

                    return number.toLocaleString(undefined, {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals,
                    });
                },
                formatTankLimit(tank) {
                    if (! tank || ! tank.has_limit) {
                        return 'No limit';
                    }

                    return `${this.formatHistoryNumber(tank.limit)} L`;
                },
                submissionStatusClass(status) {
                    const value = String(status || '').toLowerCase();

                    if (value.includes('verified') || value.includes('approved')) {
                        return 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500';
                    }

                    return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
                },
                confirmVoid(periodCode, formId, liters, submissions) {
                    this.voidConfirmation = {
                        open: true,
                        periodCode: periodCode || 'generated record',
                        formId,
                        liters: Number(liters || 0),
                        submissions: Number(submissions || 0),
                    };

                    this.$nextTick(() => {
                        if (this.$refs.voidPrimary) {
                            this.$refs.voidPrimary.focus();
                        }
                    });
                },
                resolveVoid(confirmed) {
                    const formId = this.voidConfirmation.formId;
                    this.voidConfirmation = { open: false, periodCode: '', formId: '', liters: 0, submissions: 0 };

                    if (! confirmed || ! formId) {
                        return;
                    }

                    const form = document.getElementById(formId);

                    if (form) {
                        form.submit();
                    }
                },
                closeTopLayer() {
                    if (this.messageDialog.open) {
                        this.closeMessage();
                        return;
                    }

                    if (this.deductionVoid.open) {
                        this.closeDeductionVoid();
                        return;
                    }

                    if (this.voidConfirmation.open) {
                        this.resolveVoid(false);
                        return;
                    }

                    if (this.deduction.open) {
                        this.closeDeduction();
                        return;
                    }

                    if (this.activeDetails.open) {
                        this.closeActiveDetails();
                        return;
                    }

                    if (this.historyDetails.open) {
                        this.closeHistoryDetails();
                        return;
                    }

                    if (this.confirmation.open) {
                        this.resolveConfirmation(false);
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
