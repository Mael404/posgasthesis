<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LitersMovementTrackingService
{
    private const DEFAULT_LIMIT = 4000.0;
    private const ACTIVE_STATUS = 'active';
    private const GENERATED_STATUS = 'generated';
    private const VOIDED_STATUS = 'voided';
    private const CORRECTION_REVERSAL = 'reversal';
    private const CORRECTION_REPLACEMENT = 'replacement';
    private const CORRECTION_PENDING = 'pending';
    private const CORRECTION_MATCHED = 'matched';
    private const CORRECTION_APPLIED = 'applied';
    private const CORRECTION_CANCELLED = 'cancelled';
    private const DEFAULT_FUEL_TYPES = ['Unleaded', 'Premium', 'Diesel'];
    private const DEFAULT_FUEL_LIMITS = [
        'regular' => 4000.0,
        'premium' => 4347.0,
        'diesel' => null,
    ];
    private const PUMP_FUEL_TYPES = [
        1 => 'Premium',
        2 => 'Unleaded',
        3 => 'Premium',
        4 => 'Unleaded',
        5 => 'Diesel',
        6 => 'Diesel',
    ];

    private array $tablePresence = [];
    private array $columnPresence = [];
    private ?array $fuelPrices = null;
    private array $readingNetSalesCache = [];
    private array $lockedSalesPresence = [];

    public function state(?int $staffId = null, int $perPage = 20, string $pageName = 'page', bool $includeNetSales = false): array
    {
        if (! $this->isReady()) {
            return [
                'period' => null,
                'summary' => $this->emptySummary(null),
                'transactions' => $this->emptyPaginator($perPage, $pageName),
                'missingRequirements' => $this->missingRequirements(),
            ];
        }

        $period = $this->ensureActivePeriod();
        $this->attachUntrackedShiftReadingsToPeriod($period);

        return [
            'period' => $period,
            'summary' => $this->summaryForPeriod($period, $staffId, $includeNetSales),
            'transactions' => $this->transactionsForPeriod($period, $staffId, $perPage, $pageName, $includeNetSales),
            'missingRequirements' => [],
        ];
    }

    public function ensureActivePeriod(?float $limit = null, ?array $tankLimits = null): object
    {
        $period = $this->activePeriod();

        if ($period) {
            return $period;
        }

        $periodId = $this->createPeriod($limit ?? self::DEFAULT_LIMIT, null, $tankLimits);

        return DB::table('liters_tracking_periods')->where('id', $periodId)->first();
    }

    public function activePeriod(): ?object
    {
        if (! $this->hasTable('liters_tracking_periods')) {
            return null;
        }

        return DB::table('liters_tracking_periods')
            ->where('status', self::ACTIVE_STATUS)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();
    }

    public function activePeriodIdForNewShiftReading(): ?int
    {
        if (! $this->isReady()) {
            return null;
        }

        return (int) $this->ensureActivePeriod()->id;
    }

    public function canAdjustLiters(): bool
    {
        return $this->isReady()
            && $this->hasTable('liters_movement_adjustments')
            && $this->hasColumn('liters_movement_adjustments', 'amount');
    }

    public function recordShiftReadingVoid(int $shiftReadingId, ?int $createdBy = null): array
    {
        if (! $this->isReady()) {
            throw new \RuntimeException('Liters tracking is not migrated yet. Run php artisan migrate.');
        }

        return DB::transaction(function () use ($shiftReadingId, $createdBy) {
            $preliminaryReading = DB::table('shift_readings')
                ->where('id', $shiftReadingId)
                ->first();

            if (! $preliminaryReading) {
                throw new \RuntimeException('Shift-reading submission was not found.');
            }

            $periodId = (int) ($preliminaryReading->liters_tracking_period_id ?? 0);

            if ($periodId <= 0) {
                return ['action' => 'none', 'correction_ids' => []];
            }

            $period = DB::table('liters_tracking_periods')
                ->where('id', $periodId)
                ->lockForUpdate()
                ->first();

            if (! $period) {
                return ['action' => 'none', 'correction_ids' => []];
            }

            $periodStatus = strtolower(trim((string) ($period->status ?? '')));
            $activePeriod = $periodStatus === self::GENERATED_STATUS
                ? $this->lockedActivePeriodForCorrection($period)
                : null;
            $reading = DB::table('shift_readings')
                ->where('id', $shiftReadingId)
                ->lockForUpdate()
                ->first();

            if (! $reading || (int) ($reading->liters_tracking_period_id ?? 0) !== (int) $period->id) {
                throw new \RuntimeException('The Shift Reading changed Liters Movement periods while it was being voided. Please retry.');
            }

            if ($periodStatus === self::ACTIVE_STATUS) {
                DB::table('shift_readings')
                    ->where('id', $reading->id)
                    ->update(['liters_tracking_period_id' => null]);

                DB::table('liters_tracking_periods')
                    ->where('id', $period->id)
                    ->update(['updated_at' => now()]);

                return ['action' => 'detached', 'correction_ids' => []];
            }

            if ($periodStatus !== self::GENERATED_STATUS) {
                return ['action' => 'none', 'correction_ids' => []];
            }

            $this->assertCorrectionReady();

            $existingIds = DB::table('liters_movement_corrections')
                ->where('original_shift_reading_id', (int) $reading->id)
                ->where('entry_type', self::CORRECTION_REVERSAL)
                ->where('status', '<>', self::CORRECTION_CANCELLED)
                ->lockForUpdate()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($existingIds !== []) {
                return ['action' => 'existing_reversal', 'correction_ids' => $existingIds];
            }

            $breakdown = $this->generatedCorrectionBreakdown($period, (int) $reading->id);

            if ($breakdown === []) {
                throw new \RuntimeException(
                    "Generated history for Shift Reading #{$reading->id} has no per-pump snapshot entries. The reading was not voided to avoid an inaccurate reversal."
                );
            }

            $now = now();
            $correctionIds = [];

            foreach ($breakdown as $entry) {
                $correctionIds[] = (int) DB::table('liters_movement_corrections')->insertGetId([
                    'liters_tracking_period_id' => (int) $activePeriod->id,
                    'generated_period_id' => (int) $period->id,
                    'original_shift_reading_id' => (int) $reading->id,
                    'replacement_shift_reading_id' => null,
                    'entry_type' => self::CORRECTION_REVERSAL,
                    'status' => self::CORRECTION_PENDING,
                    'pump_number' => (int) $entry['pump_number'],
                    'fuel_type' => $entry['fuel_type'],
                    'liters' => -abs((float) $entry['liters']),
                    'amount' => -abs((float) $entry['amount']),
                    'total_sales' => -abs((float) $entry['total_sales']),
                    'discounts' => -abs((float) $entry['discounts']),
                    'net_sales' => -abs((float) $entry['net_sales']),
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('liters_tracking_periods')
                ->where('id', $activePeriod->id)
                ->update(['updated_at' => $now]);

            return ['action' => 'reversal_created', 'correction_ids' => $correctionIds];
        });
    }

    public function registerShiftReadingReplacement(int $shiftReadingId, ?int $createdBy = null): array
    {
        if (! $this->hasTable('liters_movement_corrections')) {
            return ['action' => 'normal_submission', 'correction_ids' => []];
        }

        return DB::transaction(function () use ($shiftReadingId, $createdBy) {
            $preliminaryReading = DB::table('shift_readings')
                ->where('id', $shiftReadingId)
                ->first();

            if (! $preliminaryReading || ! $this->isIncludedReadingStatus($preliminaryReading->status ?? null)) {
                return ['action' => 'normal_submission', 'correction_ids' => []];
            }

            $activePeriodId = (int) ($preliminaryReading->liters_tracking_period_id ?? 0);

            if ($activePeriodId <= 0) {
                return ['action' => 'normal_submission', 'correction_ids' => []];
            }

            $activePeriod = DB::table('liters_tracking_periods')
                ->where('id', $activePeriodId)
                ->where('status', self::ACTIVE_STATUS)
                ->lockForUpdate()
                ->first();

            if (! $activePeriod) {
                throw new \RuntimeException('The replacement Shift Reading is not attached to the active Liters Movement period.');
            }

            $reading = DB::table('shift_readings')
                ->where('id', $shiftReadingId)
                ->lockForUpdate()
                ->first();

            if (
                ! $reading
                || ! $this->isIncludedReadingStatus($reading->status ?? null)
                || (int) ($reading->liters_tracking_period_id ?? 0) !== $activePeriodId
            ) {
                throw new \RuntimeException('The replacement Shift Reading changed while its Liters Movement correction was being recorded. Please retry.');
            }

            $existingReplacementMatches = DB::table('liters_movement_corrections')
                ->join('shift_readings as originals', 'liters_movement_corrections.original_shift_reading_id', '=', 'originals.id')
                ->where('liters_movement_corrections.liters_tracking_period_id', $activePeriodId)
                ->where('liters_movement_corrections.entry_type', self::CORRECTION_REPLACEMENT)
                ->where('liters_movement_corrections.status', self::CORRECTION_APPLIED)
                ->where('originals.user_id', (int) ($reading->user_id ?? 0))
                ->when($this->hasColumn('shift_readings', 'period_from'), fn ($query) => $query->whereDate('originals.period_from', $reading->period_from))
                ->when($this->hasColumn('shift_readings', 'period_to'), fn ($query) => $query->whereDate('originals.period_to', $reading->period_to ?: $reading->period_from))
                ->lockForUpdate()
                ->distinct()
                ->pluck('liters_movement_corrections.replacement_shift_reading_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values();

            if ($existingReplacementMatches->contains((int) $reading->id)) {
                return ['action' => 'existing_replacement', 'correction_ids' => []];
            }

            if ($existingReplacementMatches->isNotEmpty()) {
                throw new \RuntimeException('This generated Shift Reading already has a corrected replacement for the same staff member and settlement period.');
            }

            $pendingOriginalIds = DB::table('liters_movement_corrections')
                ->where('liters_tracking_period_id', $activePeriodId)
                ->where('entry_type', self::CORRECTION_REVERSAL)
                ->where('status', self::CORRECTION_PENDING)
                ->distinct()
                ->pluck('original_shift_reading_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($pendingOriginalIds === []) {
                return ['action' => 'normal_submission', 'correction_ids' => []];
            }

            $matches = DB::table('shift_readings')
                ->whereIn('id', $pendingOriginalIds)
                ->where('user_id', (int) ($reading->user_id ?? 0))
                ->when($this->hasColumn('shift_readings', 'period_from'), fn ($query) => $query->whereDate('period_from', $reading->period_from))
                ->when($this->hasColumn('shift_readings', 'period_to'), fn ($query) => $query->whereDate('period_to', $reading->period_to ?: $reading->period_from))
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($matches->isEmpty()) {
                return ['action' => 'normal_submission', 'correction_ids' => []];
            }

            if ($matches->count() > 1) {
                throw new \RuntimeException('More than one pending generated-reading reversal matches this staff member and settlement period. The replacement was not attached automatically.');
            }

            $original = $matches->first();
            $reversals = DB::table('liters_movement_corrections')
                ->where('original_shift_reading_id', (int) $original->id)
                ->where('entry_type', self::CORRECTION_REVERSAL)
                ->where('status', self::CORRECTION_PENDING)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($reversals->isEmpty()) {
                return ['action' => 'normal_submission', 'correction_ids' => []];
            }

            $existingReplacement = DB::table('liters_movement_corrections')
                ->where('original_shift_reading_id', (int) $original->id)
                ->where('entry_type', self::CORRECTION_REPLACEMENT)
                ->where('status', '<>', self::CORRECTION_CANCELLED)
                ->lockForUpdate()
                ->exists();

            if ($existingReplacement) {
                throw new \RuntimeException("Shift Reading #{$original->id} already has a Liters Movement replacement.");
            }

            $breakdown = $this->replacementCorrectionBreakdown($reading);

            if ($breakdown === []) {
                throw new \RuntimeException('The corrected Shift Reading has no per-pump liters or amount to post as a replacement.');
            }

            $now = now();
            $correctionIds = [];

            foreach ($breakdown as $entry) {
                $correctionIds[] = (int) DB::table('liters_movement_corrections')->insertGetId([
                    'liters_tracking_period_id' => $activePeriodId,
                    'generated_period_id' => (int) $reversals->first()->generated_period_id,
                    'original_shift_reading_id' => (int) $original->id,
                    'replacement_shift_reading_id' => (int) $reading->id,
                    'entry_type' => self::CORRECTION_REPLACEMENT,
                    'status' => self::CORRECTION_APPLIED,
                    'pump_number' => (int) $entry['pump_number'],
                    'fuel_type' => $entry['fuel_type'],
                    'liters' => abs((float) $entry['liters']),
                    'amount' => abs((float) $entry['amount']),
                    'total_sales' => abs((float) $entry['total_sales']),
                    'discounts' => abs((float) $entry['discounts']),
                    'net_sales' => abs((float) $entry['net_sales']),
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('liters_movement_corrections')
                ->whereIn('id', $reversals->pluck('id')->all())
                ->update([
                    'status' => self::CORRECTION_MATCHED,
                    'updated_at' => $now,
                ]);

            $this->touchPeriodAndReading($activePeriodId, (int) $reading->id, $now);

            return [
                'action' => 'replacement_created',
                'original_shift_reading_id' => (int) $original->id,
                'correction_ids' => $correctionIds,
            ];
        });
    }

    public function deductPumpLiters(
        int $shiftReadingId,
        int $pumpNumber,
        float $liters,
        float $amount,
        int $createdBy,
        int $authorizedBy,
        ?string $reason = null,
        ?int $allowedStaffId = null
    ): object {
        $this->assertAdjustmentReady();

        $pumpNumber = (int) $pumpNumber;
        $liters = $this->liters($liters);
        $amount = $this->money($amount);

        if (! array_key_exists($pumpNumber, self::PUMP_FUEL_TYPES)) {
            throw new \RuntimeException('Choose a valid pump.');
        }

        if ($liters <= 0) {
            throw new \RuntimeException('Enter liters greater than zero.');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Enter amount greater than zero.');
        }

        return DB::transaction(function () use ($shiftReadingId, $pumpNumber, $liters, $amount, $createdBy, $authorizedBy, $reason, $allowedStaffId) {
            $period = $this->ensureActivePeriod();
            $period = DB::table('liters_tracking_periods')
                ->where('id', (int) $period->id)
                ->where('status', self::ACTIVE_STATUS)
                ->lockForUpdate()
                ->first();

            if (! $period) {
                throw new \RuntimeException('Active liters movement period was not found.');
            }

            $this->attachUntrackedShiftReadingsToPeriod($period);

            $reading = DB::table('shift_readings')
                ->where('id', $shiftReadingId)
                ->lockForUpdate()
                ->first();

            if (! $reading) {
                throw new \RuntimeException('Shift-reading submission was not found.');
            }

            $this->assertReadingBelongsToStaff(
                $reading,
                $allowedStaffId,
                'Only your own shift-reading submissions can be adjusted from staff liters tracking.'
            );

            if ((int) ($reading->liters_tracking_period_id ?? 0) !== (int) $period->id) {
                throw new \RuntimeException('Only submissions in the active liters movement period can be adjusted.');
            }

            if (! $this->isIncludedReadingStatus($reading->status ?? null)) {
                throw new \RuntimeException('Only pending or verified submissions can be adjusted.');
            }

            if ($this->isReplacementReading((int) $reading->id)) {
                throw new \RuntimeException('Generated-reading replacements are calculated from their signed reversal and replacement entries and cannot receive manual liters deductions.');
            }

            $rawLiters = $this->rawPumpLiters($reading, $pumpNumber);
            $rawAmount = $this->rawPumpAmount($reading, $pumpNumber);

            if ($rawLiters <= 0) {
                throw new \RuntimeException("Pump {$pumpNumber} has no liters to deduct from this submission.");
            }

            if ($rawAmount <= 0) {
                throw new \RuntimeException("Pump {$pumpNumber} has no sales amount to deduct from this submission.");
            }

            $activeAdjustments = $this->adjustmentsForReadings([$reading->id], true)[(int) $reading->id] ?? [];
            $alreadyDeducted = $this->activeAdjustmentLitersForPump($activeAdjustments, $pumpNumber);
            $availableLiters = $this->liters(max(0, $rawLiters - $alreadyDeducted));
            $alreadyDeductedAmount = $this->activeAdjustmentAmountForPump($activeAdjustments, $pumpNumber);
            $availableAmount = $this->money(max(0, $rawAmount - $alreadyDeductedAmount));

            if ($liters > $availableLiters) {
                throw new \RuntimeException('Deduction cannot exceed the remaining pump liters (' . number_format($availableLiters, 2) . ' L).');
            }

            if ($amount > $availableAmount) {
                throw new \RuntimeException('Deduction amount cannot exceed the remaining pump sales (₱' . number_format($availableAmount, 2) . ').');
            }

            $now = now();
            $adjustmentId = DB::table('liters_movement_adjustments')->insertGetId([
                'liters_tracking_period_id' => (int) $period->id,
                'shift_reading_id' => (int) $reading->id,
                'pump_number' => $pumpNumber,
                'liters' => $liters,
                'amount' => $amount,
                'reason' => $this->cleanText($reason),
                'created_by' => $createdBy,
                'authorized_by' => $authorizedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->touchPeriodAndReading((int) $period->id, (int) $reading->id, $now);

            return DB::table('liters_movement_adjustments')->where('id', $adjustmentId)->first();
        });
    }

    public function voidDeduction(
        int $adjustmentId,
        int $voidedBy,
        int $authorizedBy,
        ?string $reason = null,
        ?int $allowedStaffId = null,
        ?int $allowedCreatedById = null
    ): object
    {
        $this->assertAdjustmentReady();

        return DB::transaction(function () use ($adjustmentId, $voidedBy, $authorizedBy, $reason, $allowedStaffId, $allowedCreatedById) {
            $adjustment = DB::table('liters_movement_adjustments')
                ->where('id', $adjustmentId)
                ->lockForUpdate()
                ->first();

            if (! $adjustment) {
                throw new \RuntimeException('Liters deduction was not found.');
            }

            if (! empty($adjustment->voided_at)) {
                throw new \RuntimeException('This liters deduction is already voided.');
            }

            if ($allowedCreatedById !== null && (int) ($adjustment->created_by ?? 0) !== $allowedCreatedById) {
                throw new \RuntimeException('Only deductions created by your admin account can be voided.');
            }

            $reading = DB::table('shift_readings')
                ->where('id', (int) $adjustment->shift_reading_id)
                ->lockForUpdate()
                ->first();

            if (! $reading) {
                throw new \RuntimeException('The shift-reading submission for this deduction was not found.');
            }

            $this->assertReadingBelongsToStaff(
                $reading,
                $allowedStaffId,
                'Only deductions for your own shift-reading submissions can be voided from staff liters tracking.'
            );

            $period = DB::table('liters_tracking_periods')
                ->where('id', (int) ($reading->liters_tracking_period_id ?? 0))
                ->where('status', self::ACTIVE_STATUS)
                ->lockForUpdate()
                ->first();

            if (! $period) {
                throw new \RuntimeException('Only deductions attached to the active liters movement period can be voided from staff liters tracking.');
            }

            $now = now();
            DB::table('liters_movement_adjustments')->where('id', $adjustment->id)->update([
                'voided_at' => $now,
                'voided_by' => $voidedBy,
                'void_authorized_by' => $authorizedBy,
                'void_reason' => $this->cleanText($reason),
                'updated_at' => $now,
            ]);

            $this->touchPeriodAndReading((int) $period->id, (int) $reading->id, $now);

            return DB::table('liters_movement_adjustments')->where('id', $adjustment->id)->first();
        });
    }

    public function updateActiveLimit(float $limit): object
    {
        return $this->updateActiveLimits(['legacy' => $limit]);
    }

    public function updateActiveLimits(array $limits): object
    {
        if (! $this->isReady()) {
            throw new \RuntimeException('Liters tracking is not migrated yet. Run php artisan migrate.');
        }

        $limits = $this->sanitizeTankLimits($limits);

        return DB::transaction(function () use ($limits) {
            $period = DB::table('liters_tracking_periods')
                ->where('status', self::ACTIVE_STATUS)
                ->lockForUpdate()
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first();

            $tanks = $this->preparedTanks();
            $legacyLimit = $this->legacyLimitFromLimits($limits, $tanks);

            if (! $period) {
                $period = DB::table('liters_tracking_periods')
                    ->where('id', $this->createPeriod($legacyLimit, null, $limits))
                    ->first();
            }

            $values = [
                'liters_limit' => round($legacyLimit, 2),
                'updated_at' => now(),
            ];

            if ($this->hasColumn('liters_tracking_periods', 'tank_limits')) {
                $values['tank_limits'] = json_encode($limits);
            }

            DB::table('liters_tracking_periods')->where('id', $period->id)->update($values);

            return DB::table('liters_tracking_periods')->where('id', $period->id)->first();
        });
    }

    public function generateAndReset(int $generatedBy): array
    {
        if (! $this->isReady()) {
            throw new \RuntimeException('Liters tracking is not migrated yet. Run php artisan migrate.');
        }

        return DB::transaction(function () use ($generatedBy) {
            $period = DB::table('liters_tracking_periods')
                ->where('status', self::ACTIVE_STATUS)
                ->lockForUpdate()
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first();

            if (! $period) {
                $period = DB::table('liters_tracking_periods')
                    ->where('id', $this->createPeriod(self::DEFAULT_LIMIT))
                    ->first();
            }

            $this->attachUntrackedShiftReadingsToPeriod($period);

            $this->assertNoPendingCorrections((int) $period->id);

            $readings = $this->summaryRowsForPeriod($period, null, true);
            $endedAt = now();
            $summary = $this->summaryFromRows($period, $readings, true);
            $readingIds = $readings->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $tankLimits = $this->storedTankLimitsForPeriod($period, $this->preparedTanks());
            $snapshot = [
                'source' => 'shift_readings',
                'period_code' => $period->period_code,
                'started_at' => $period->started_at,
                'ended_at' => $endedAt->toDateTimeString(),
                'liters_limit' => $summary['limit'],
                'tank_limits' => $tankLimits,
                'total_liters' => $summary['total_liters'],
                'total_amount' => $summary['total_amount'],
                'total_sales' => $summary['total_sales'],
                'total_discounts' => $summary['total_discounts'],
                'total_net_sales' => $summary['total_net_sales'],
                'transaction_count' => $summary['transaction_count'],
                'submission_count' => $summary['submission_count'],
                'pump_entry_count' => $summary['pump_entry_count'],
                'pump_net_sales' => $summary['pump_net_sales'],
                'fuel_breakdown' => $summary['fuel_breakdown'],
                'tank_breakdown' => $summary['tank_breakdown'],
                'staff_breakdown' => $summary['staff_breakdown'],
                'reached_limits' => $summary['reached_limits'],
                'shift_reading_ids' => $readingIds,
                'liters_movement_correction_ids' => $this->correctionIdsForPeriod((int) $period->id),
                'generated_by' => $generatedBy,
                'generated_at' => $endedAt->toDateTimeString(),
            ];

            if ($readingIds !== []) {
                DB::table('shift_readings')
                    ->whereIn('id', $readingIds)
                    ->update(['liters_tracking_period_id' => $period->id]);
            }

            DB::table('liters_tracking_periods')->where('id', $period->id)->update([
                'status' => self::GENERATED_STATUS,
                'ended_at' => $endedAt,
                'generated_by' => $generatedBy,
                'totals_snapshot' => json_encode($snapshot),
                'updated_at' => $endedAt,
            ]);

            $newPeriodId = $this->createPeriod(
                (float) $period->liters_limit,
                $endedAt,
                $tankLimits
            );

            return [
                'period' => DB::table('liters_tracking_periods')->where('id', $period->id)->first(),
                'new_period' => DB::table('liters_tracking_periods')->where('id', $newPeriodId)->first(),
                'summary' => $summary,
            ];
        });
    }

    public function voidGeneratedPeriod(int $periodId, int $voidedBy): array
    {
        if (! $this->isReady()) {
            throw new \RuntimeException('Liters tracking is not migrated yet. Run php artisan migrate.');
        }

        return DB::transaction(function () use ($periodId, $voidedBy) {
            $period = DB::table('liters_tracking_periods')
                ->where('id', $periodId)
                ->lockForUpdate()
                ->first();

            if (! $period) {
                throw new \RuntimeException('Generated history record was not found.');
            }

            if (($period->status ?? null) === self::VOIDED_STATUS) {
                throw new \RuntimeException('This generated history record is already voided.');
            }

            if (($period->status ?? null) !== self::GENERATED_STATUS) {
                throw new \RuntimeException('Only generated history records can be voided.');
            }

            $snapshot = $this->decodeSnapshot($period->totals_snapshot ?? null);
            $readingIds = collect($snapshot['shift_reading_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($readingIds === []) {
                $readingIds = DB::table('shift_readings')
                    ->where('liters_tracking_period_id', $period->id)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            if ($readingIds === []) {
                throw new \RuntimeException('This generated record has no shift-reading submissions to restore.');
            }

            $activePeriod = DB::table('liters_tracking_periods')
                ->where('status', self::ACTIVE_STATUS)
                ->lockForUpdate()
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first();

            if (! $activePeriod) {
                $activePeriod = DB::table('liters_tracking_periods')
                    ->where('id', $this->createPeriod(
                        (float) ($period->liters_limit ?? self::DEFAULT_LIMIT),
                        now(),
                        $this->storedTankLimitsForPeriod($period, $this->preparedTanks())
                    ))
                    ->first();
            }

            $cancelledCorrectionIds = [];
            $restoredCorrectionIds = [];

            if ($this->hasTable('liters_movement_corrections')) {
                $cancelledCorrectionIds = DB::table('liters_movement_corrections')
                    ->where('generated_period_id', (int) $period->id)
                    ->where('status', '<>', self::CORRECTION_CANCELLED)
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($cancelledCorrectionIds !== []) {
                    DB::table('liters_movement_corrections')
                        ->whereIn('id', $cancelledCorrectionIds)
                        ->update([
                            'status' => self::CORRECTION_CANCELLED,
                            'updated_at' => now(),
                        ]);
                }

                $restoredCorrectionIds = DB::table('liters_movement_corrections')
                    ->where('liters_tracking_period_id', (int) $period->id)
                    ->where('status', '<>', self::CORRECTION_CANCELLED)
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($restoredCorrectionIds !== []) {
                    DB::table('liters_movement_corrections')
                        ->whereIn('id', $restoredCorrectionIds)
                        ->update([
                            'liters_tracking_period_id' => (int) $activePeriod->id,
                            'updated_at' => now(),
                        ]);
                }
            }

            $restoredIds = DB::table('shift_readings')
                ->whereIn('id', $readingIds)
                ->where('liters_tracking_period_id', $period->id)
                ->lockForUpdate()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($restoredIds === []) {
                throw new \RuntimeException('The included shift-reading submissions are no longer attached to this generated record.');
            }

            DB::table('shift_readings')
                ->whereIn('id', $restoredIds)
                ->update(['liters_tracking_period_id' => $activePeriod->id]);

            $voidedAt = now();
            $restoredLiters = $this->liters(collect($snapshot['tank_breakdown'] ?? [])
                ->flatMap(fn ($tank) => $tank['entries'] ?? [])
                ->filter(fn ($entry) => in_array((int) ($entry['id'] ?? 0), $restoredIds, true))
                ->sum(fn ($entry) => (float) ($entry['liters'] ?? 0)));

            if ($restoredLiters <= 0) {
                $restoredLiters = $this->liters((float) ($snapshot['total_liters'] ?? 0));
            }

            $snapshot['voided_at'] = $voidedAt->toDateTimeString();
            $snapshot['voided_by'] = $voidedBy;
            $snapshot['reverted_to_period_id'] = (int) $activePeriod->id;
            $snapshot['reverted_shift_reading_ids'] = $restoredIds;
            $snapshot['cancelled_correction_ids'] = $cancelledCorrectionIds;
            $snapshot['reverted_correction_ids'] = $restoredCorrectionIds;
            $snapshot['reverted_liters'] = $restoredLiters;

            $updates = [
                'status' => self::VOIDED_STATUS,
                'totals_snapshot' => json_encode($snapshot),
                'updated_at' => $voidedAt,
            ];

            if ($this->hasColumn('liters_tracking_periods', 'voided_at')) {
                $updates['voided_at'] = $voidedAt;
            }

            if ($this->hasColumn('liters_tracking_periods', 'voided_by')) {
                $updates['voided_by'] = $voidedBy;
            }

            DB::table('liters_tracking_periods')->where('id', $period->id)->update($updates);

            return [
                'period' => DB::table('liters_tracking_periods')->where('id', $period->id)->first(),
                'active_period' => DB::table('liters_tracking_periods')->where('id', $activePeriod->id)->first(),
                'restored_shift_reading_ids' => $restoredIds,
                'restored_count' => count($restoredIds),
                'restored_liters' => $restoredLiters,
                'cancelled_correction_ids' => $cancelledCorrectionIds,
                'restored_correction_ids' => $restoredCorrectionIds,
            ];
        });
    }

    public function history(int $perPage = 10, string $pageName = 'history_page'): LengthAwarePaginator
    {
        if (! $this->hasTable('liters_tracking_periods')) {
            return $this->emptyPaginator($perPage, $pageName);
        }

        $query = DB::table('liters_tracking_periods')
            ->whereIn('liters_tracking_periods.status', [self::GENERATED_STATUS, self::VOIDED_STATUS]);

        if ($this->hasTable('users') && $this->hasColumn('users', 'name')) {
            $query->leftJoin('users as generators', 'liters_tracking_periods.generated_by', '=', 'generators.id');

            if ($this->hasColumn('liters_tracking_periods', 'voided_by')) {
                $query->leftJoin('users as voiders', 'liters_tracking_periods.voided_by', '=', 'voiders.id')
                    ->select(
                        'liters_tracking_periods.*',
                        DB::raw("COALESCE(generators.name, 'Admin') as generated_by_name"),
                        DB::raw('voiders.name as voided_by_name')
                    );
            } else {
                $query->select(
                    'liters_tracking_periods.*',
                    DB::raw("COALESCE(generators.name, 'Admin') as generated_by_name"),
                    DB::raw('NULL as voided_by_name')
                );
            }
        } else {
            $query->select(
                'liters_tracking_periods.*',
                DB::raw("'Admin' as generated_by_name"),
                DB::raw('NULL as voided_by_name')
            );
        }

        $paginator = $query
            ->orderByDesc('liters_tracking_periods.ended_at')
            ->orderByDesc('liters_tracking_periods.id')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();

        $paginator->getCollection()->transform(function ($period) {
            $period->snapshot = $this->snapshotWithSalesFallback($period, $this->decodeSnapshot($period->totals_snapshot ?? null));

            return $period;
        });

        return $paginator;
    }

    public function activeLimitReachedWithoutCreatingPeriod(): int
    {
        if (! $this->isReady()) {
            return 0;
        }

        $period = $this->activePeriod();

        if (! $period) {
            return 0;
        }

        return count($this->summaryForPeriod($period, null)['reached_limits'] ?? []);
    }

    public function activeLimitNotifications(int $limit = 10): array
    {
        if (! $this->isReady()) {
            return [];
        }

        $period = $this->activePeriod();

        if (! $period) {
            return [];
        }

        $summary = $this->summaryForPeriod($period, null);
        $url = Route::has('admin.liters-movement.index')
            ? route('admin.liters-movement.index', [], false)
            : '/admin/liters-movement';
        $sortTimestamp = $period->updated_at ? strtotime((string) $period->updated_at) : time();

        return collect($summary['reached_limits'] ?? [])
            ->take($limit)
            ->map(function (array $tank) use ($period, $url, $sortTimestamp) {
                $limit = (float) ($tank['limit'] ?? 0);
                $liters = (float) ($tank['liters'] ?? 0);

                return [
                    'id' => 'liters-movement-' . (int) $period->id . '-' . Str::slug((string) ($tank['limit_key'] ?? $tank['name'] ?? 'tank')),
                    'type' => 'liters_movement_limit',
                    'actorName' => 'Liters Movement',
                    'summary' => 'reached the limit for',
                    'context' => $tank['name'] ?? $tank['fuel_type'] ?? 'Tank',
                    'body' => number_format($liters, 2) . ' L from submitted shift readings against ' . number_format($limit, 2) . ' L. This alert does not block new submissions.',
                    'badge' => $tank['fuel_type'] ?? 'Fuel',
                    'time' => 'Active now',
                    'url' => $url,
                    'sort_timestamp' => $sortTimestamp,
                ];
            })
            ->values()
            ->all();
    }

    private function createPeriod(float $limit, $startedAt = null, ?array $tankLimits = null): int
    {
        $startedAt ??= now();
        $values = [
            'period_code' => $this->generatePeriodCode(),
            'liters_limit' => round($limit, 2),
            'status' => self::ACTIVE_STATUS,
            'started_at' => $startedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($this->hasColumn('liters_tracking_periods', 'tank_limits')) {
            $values['tank_limits'] = json_encode($tankLimits ?? $this->defaultStoredTankLimits($limit));
        }

        return (int) DB::table('liters_tracking_periods')->insertGetId($values);
    }

    private function generatePeriodCode(): string
    {
        do {
            $code = 'LTM-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
        } while (DB::table('liters_tracking_periods')->where('period_code', $code)->exists());

        return $code;
    }

    private function attachUntrackedShiftReadingsToPeriod(object $period): void
    {
        if (! $this->isReady() || empty($period->id)) {
            return;
        }

        $excludedFromActivePeriod = DB::table('shift_readings')
            ->where('liters_tracking_period_id', (int) $period->id);

        $this->scopeToExcludedShiftReadings($excludedFromActivePeriod);
        $excludedFromActivePeriod->update(['liters_tracking_period_id' => null]);

        $query = DB::table('shift_readings')
            ->whereNull('liters_tracking_period_id');

        if ($this->hasColumn('shift_readings', 'created_at') && ! empty($period->started_at)) {
            $query->where('created_at', '>=', $period->started_at);
        }

        $this->scopeToIncludedShiftReadings($query);
        $query->update(['liters_tracking_period_id' => $period->id]);
    }

    private function summaryForPeriod(object $period, ?int $staffId, bool $includeNetSales = false): array
    {
        return $this->summaryFromRows($period, $this->summaryRowsForPeriod($period, $staffId), $includeNetSales);
    }

    private function summaryRowsForPeriod(object $period, ?int $staffId, bool $lock = false): Collection
    {
        $query = $this->shiftReadingsQuery($period, $staffId);

        if ($this->hasTable('users') && $this->hasColumn('users', 'id')) {
            $query->leftJoin('users', 'shift_readings.user_id', '=', 'users.id');
        }

        $staffExpression = $this->hasTable('users') && $this->hasColumn('users', 'name')
            ? "COALESCE(users.name, 'Staff')"
            : "'Staff'";

        if ($lock) {
            $query->lockForUpdate();
        }

        $rows = $query
            ->select(
                'shift_readings.*',
                DB::raw("{$staffExpression} as staff_name")
            )
            ->orderBy('shift_readings.created_at')
            ->orderBy('shift_readings.id')
            ->get();

        $correctionRows = $this->correctionGroupsForPeriod($period, $staffId, $lock);
        $replacementIds = $correctionRows
            ->pluck('replacement_shift_reading_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique();

        return $rows
            ->reject(fn ($reading) => $replacementIds->contains((int) ($reading->id ?? 0)))
            ->concat($correctionRows)
            ->sortBy(fn ($reading) => sprintf('%s-%020d', (string) ($reading->created_at ?? ''), (int) ($reading->id ?? 0)))
            ->values();
    }

    private function summaryFromRows(object $period, Collection $rows, bool $includeNetSales = false): array
    {
        $tanks = $this->preparedTanks();
        $fuelBreakdown = $this->defaultFuelBreakdown($tanks);
        $tankBreakdown = $this->defaultTankBreakdown($period, $tanks);
        $pumpToTank = $this->pumpToTankMap($tanks);
        $fuelToTank = $this->uniqueFuelToTankMap($tanks);
        $staffBreakdown = [];
        $totalLiters = 0.0;
        $totalAmount = 0.0;
        $pumpEntryCount = 0;
        $pumpNetSales = $this->defaultPumpNetSalesBreakdown();
        $adjustmentsByReading = $this->adjustmentsForReadings(
            $rows
                ->reject(fn ($reading) => (bool) ($reading->is_liters_movement_correction ?? false))
                ->pluck('id')
                ->all(),
            true
        );

        foreach ($rows as $reading) {
            $staffKey = (int) ($reading->user_id ?? 0);
            $staffIndex = $staffKey > 0 ? (string) $staffKey : 'unknown';

            if (! isset($staffBreakdown[$staffIndex])) {
                $staffBreakdown[$staffIndex] = [
                    'staff_id' => $staffKey ?: null,
                    'staff_name' => trim((string) ($reading->staff_name ?? 'Staff')) ?: 'Staff',
                    'liters' => 0.0,
                    'amount' => 0.0,
                    'transaction_count' => 0,
                    'submission_count' => 0,
                ];
            }

            $readingLiters = 0.0;
            $readingAmount = 0.0;
            $readingAdjustments = $adjustmentsByReading[(int) ($reading->id ?? 0)] ?? [];
            $readingNetSales = $includeNetSales
                ? $this->netSalesByPumpForReading($reading, $readingAdjustments)
                : $this->defaultPumpNetSalesBreakdown();

            foreach ($readingNetSales as $pumpId => $sales) {
                if (! isset($pumpNetSales[$pumpId])) {
                    $pumpNetSales[$pumpId] = $sales;
                    continue;
                }

                $pumpNetSales[$pumpId]['total_sales'] = $this->money($pumpNetSales[$pumpId]['total_sales'] + $sales['total_sales']);
                $pumpNetSales[$pumpId]['discounts'] = $this->money($pumpNetSales[$pumpId]['discounts'] + $sales['discounts']);
                $pumpNetSales[$pumpId]['net_sales'] = $this->money($pumpNetSales[$pumpId]['net_sales'] + $sales['net_sales']);
                $pumpNetSales[$pumpId]['transaction_count'] += (int) $sales['transaction_count'];
            }

            foreach ($this->pumpEntriesForReading($reading, $readingAdjustments, $readingNetSales) as $entry) {
                $liters = $entry['liters'];
                $amount = $entry['amount'];
                $totalSales = $this->money($includeNetSales ? ($entry['total_sales'] ?? $amount) : $amount);
                $discounts = $this->money($includeNetSales ? ($entry['discounts'] ?? 0) : 0);
                $netSales = $this->money($includeNetSales ? ($entry['net_sales'] ?? ($totalSales - $discounts)) : $amount);
                $fuel = $entry['fuel_type'];
                $fuelKey = $this->normalizeFuelKey($fuel);

                if (! isset($pumpNetSales[$entry['pump_id']])) {
                    $pumpNetSales[$entry['pump_id']] = $this->emptyPumpNetSalesRow($entry['pump_id'], $fuel);
                }

                $pumpNetSales[$entry['pump_id']]['liters'] = $this->liters(
                    ($pumpNetSales[$entry['pump_id']]['liters'] ?? 0) + $liters
                );

                if (! isset($fuelBreakdown[$fuel])) {
                    $fuelBreakdown[$fuel] = [
                        'fuel_type' => $fuel,
                        'liters' => 0.0,
                        'amount' => 0.0,
                        'transaction_count' => 0,
                    ];
                }

                $fuelBreakdown[$fuel]['liters'] = $this->liters($fuelBreakdown[$fuel]['liters'] + $liters);
                $fuelBreakdown[$fuel]['amount'] = $this->money($fuelBreakdown[$fuel]['amount'] + $amount);
                $fuelBreakdown[$fuel]['transaction_count']++;

                $tankId = $pumpToTank[$entry['pump_id']] ?? ($fuelToTank[$fuelKey] ?? null);

                if (! $tankId || ! isset($tankBreakdown[$tankId])) {
                    $tankId = 'unassigned';
                    $this->ensureUnassignedTankBreakdown($tankBreakdown, [
                        'fuel_type' => $fuel,
                        'connected_pumps_display' => 'P' . $entry['pump_id'],
                    ]);
                }

                $tankBreakdown[$tankId]['liters'] = $this->liters($tankBreakdown[$tankId]['liters'] + $liters);
                $tankBreakdown[$tankId]['amount'] = $this->money($tankBreakdown[$tankId]['amount'] + $amount);
                $tankBreakdown[$tankId]['total_sales'] = $this->money(($tankBreakdown[$tankId]['total_sales'] ?? 0) + $totalSales);
                $tankBreakdown[$tankId]['discounts'] = $this->money(($tankBreakdown[$tankId]['discounts'] ?? 0) + $discounts);
                $tankBreakdown[$tankId]['net_sales'] = $this->money(($tankBreakdown[$tankId]['net_sales'] ?? 0) + $netSales);
                $tankBreakdown[$tankId]['transaction_count']++;
                $tankBreakdown[$tankId]['entries'][] = [
                    'id' => (int) ($reading->id ?? 0),
                    'created_at' => $reading->created_at ?? null,
                    'period_from' => $reading->period_from ?? null,
                    'period_to' => $reading->period_to ?? null,
                    'pump_id' => $entry['pump_id'],
                    'staff_name' => trim((string) ($reading->staff_name ?? 'Staff')) ?: 'Staff',
                    'status' => trim((string) ($reading->status ?? 'Pending')) ?: 'Pending',
                    'liters' => $liters,
                    'raw_liters' => $entry['raw_liters'] ?? $liters,
                    'deducted_liters' => $entry['deducted_liters'] ?? 0,
                    'price_per_liter' => $entry['price_per_liter'],
                    'amount' => $amount,
                    'raw_amount' => $entry['raw_amount'] ?? $amount,
                    'deducted_amount' => $entry['deducted_amount'] ?? 0,
                    'total_sales' => $totalSales,
                    'discounts' => $discounts,
                    'net_sales' => $netSales,
                    'sales_transaction_count' => $entry['sales_transaction_count'] ?? 0,
                    'adjustments' => $entry['adjustments'] ?? [],
                    'is_liters_movement_correction' => (bool) ($reading->is_liters_movement_correction ?? false),
                    'correction_entry_type' => $entry['correction_entry_type'] ?? null,
                    'correction_status' => $entry['correction_status'] ?? null,
                    'original_shift_reading_id' => $entry['original_shift_reading_id'] ?? null,
                    'replacement_shift_reading_id' => $entry['replacement_shift_reading_id'] ?? null,
                ];

                $readingLiters = $this->liters($readingLiters + $liters);
                $readingAmount = $this->money($readingAmount + $amount);
                $pumpEntryCount++;
            }

            $staffBreakdown[$staffIndex]['liters'] = $this->liters($staffBreakdown[$staffIndex]['liters'] + $readingLiters);
            $staffBreakdown[$staffIndex]['amount'] = $this->money($staffBreakdown[$staffIndex]['amount'] + $readingAmount);
            $staffBreakdown[$staffIndex]['transaction_count']++;
            $staffBreakdown[$staffIndex]['submission_count']++;
            $totalLiters = $this->liters($totalLiters + $readingLiters);
            $totalAmount = $this->money($totalAmount + $readingAmount);
        }

        $tankRows = $this->formatTankBreakdown($tankBreakdown);
        $configuredLimit = $this->liters(collect($tankRows)->sum(fn ($tank) => (float) ($tank['limit'] ?? 0)));
        $limitedLiters = $this->liters(collect($tankRows)->sum(
            fn ($tank) => ($tank['has_limit'] ?? false) ? (float) ($tank['liters'] ?? 0) : 0
        ));
        $remainingLiters = $this->liters(collect($tankRows)->sum(fn ($tank) => (float) ($tank['remaining_liters'] ?? 0)));
        $overLiters = $this->liters(collect($tankRows)->sum(fn ($tank) => (float) ($tank['over_liters'] ?? 0)));
        $percent = $configuredLimit > 0 ? round(($limitedLiters / $configuredLimit) * 100, 2) : 0.0;
        $reachedLimits = collect($tankRows)
            ->filter(fn ($tank) => ($tank['status']['key'] ?? null) === 'limit_reached')
            ->values()
            ->all();
        $nearLimits = collect($tankRows)
            ->filter(fn ($tank) => ($tank['status']['key'] ?? null) === 'near_limit')
            ->count();
        $status = $reachedLimits !== []
            ? $this->statusFor(1, 1)
            : ($nearLimits > 0 ? $this->statusFor(0.8, 1) : $this->statusFor(0, 1));
        $pumpNetSalesRows = collect($pumpNetSales)->sortKeys()->values()->all();
        $totalSales = $this->money(collect($pumpNetSalesRows)->sum('total_sales'));
        $totalDiscounts = $this->money(collect($pumpNetSalesRows)->sum('discounts'));
        $totalNetSales = $this->money(collect($pumpNetSalesRows)->sum('net_sales'));

        return [
            'total_liters' => $totalLiters,
            'total_amount' => $totalAmount,
            'total_sales' => $totalSales,
            'total_discounts' => $totalDiscounts,
            'total_net_sales' => $totalNetSales,
            'limit' => $configuredLimit,
            'configured_limit_count' => collect($tankRows)->filter(fn ($tank) => (bool) ($tank['has_limit'] ?? false))->count(),
            'remaining_liters' => $remainingLiters,
            'over_liters' => $overLiters,
            'percent' => $percent,
            'progress_width' => min(100, max(0, $percent)),
            'transaction_count' => $rows->count(),
            'submission_count' => $rows->count(),
            'pump_entry_count' => $pumpEntryCount,
            'pump_net_sales' => $pumpNetSalesRows,
            'fuel_breakdown' => array_values($fuelBreakdown),
            'tank_breakdown' => $tankRows,
            'staff_breakdown' => collect($staffBreakdown)->sortByDesc('liters')->values()->all(),
            'reached_limits' => $reachedLimits,
            'status' => $status,
        ];
    }

    private function transactionsForPeriod(object $period, ?int $staffId, int $perPage, string $pageName, bool $includeNetSales = false): LengthAwarePaginator
    {
        $rows = $this->summaryRowsForPeriod($period, $staffId)
            ->sortByDesc(fn ($reading) => sprintf('%s-%020d', (string) ($reading->created_at ?? ''), (int) ($reading->id ?? 0)))
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $pageRows = $rows->forPage($page, $perPage)->values();
        $readingIds = $pageRows
            ->reject(fn ($reading) => (bool) ($reading->is_liters_movement_correction ?? false))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $activeAdjustmentsByReading = $this->adjustmentsForReadings($readingIds, true);
        $allAdjustmentsByReading = $this->adjustmentsForReadings($readingIds, false);

        $pageRows->transform(function ($reading) use ($activeAdjustmentsByReading, $allAdjustmentsByReading, $includeNetSales) {
            $readingId = (int) ($reading->id ?? 0);
            $isCorrection = (bool) ($reading->is_liters_movement_correction ?? false);
            $activeAdjustments = $isCorrection ? [] : ($activeAdjustmentsByReading[$readingId] ?? []);
            $netSalesByPump = $includeNetSales
                ? $this->netSalesByPumpForReading($reading, $activeAdjustments)
                : $this->defaultPumpNetSalesBreakdown();
            $entries = $this->pumpEntriesForReading($reading, $activeAdjustments, $netSalesByPump);
            $reading->pump_entries = $entries;
            $reading->liter_adjustments = $isCorrection ? [] : ($allAdjustmentsByReading[$readingId] ?? []);
            $reading->can_adjust_liters = ! $isCorrection;
            $reading->total_liters = $this->liters(collect($entries)->sum('liters'));
            $reading->total_amount = $this->money(collect($entries)->sum('amount'));
            $reading->total_sales = $this->money(collect($netSalesByPump)->sum('total_sales'));
            $reading->total_discounts = $this->money(collect($netSalesByPump)->sum('discounts'));
            $reading->total_net_sales = $this->money(collect($netSalesByPump)->sum('net_sales'));
            $reading->status = trim((string) ($reading->status ?? 'Pending')) ?: 'Pending';

            return $reading;
        });

        $paginator = new LengthAwarePaginator(
            $pageRows,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );

        $paginator->withQueryString();

        return $paginator;
    }

    private function shiftReadingsQuery(object $period, ?int $staffId)
    {
        $query = DB::table('shift_readings')
            ->where('shift_readings.liters_tracking_period_id', (int) $period->id);

        $this->scopeToIncludedShiftReadings($query);

        if ($staffId !== null && $this->hasColumn('shift_readings', 'user_id')) {
            $query->where('shift_readings.user_id', $staffId);
        }

        return $query;
    }

    private function correctionGroupsForPeriod(object $period, ?int $staffId, bool $lock = false): Collection
    {
        if (! $this->hasTable('liters_movement_corrections')) {
            return collect();
        }

        $query = DB::table('liters_movement_corrections')
            ->where('liters_tracking_period_id', (int) $period->id)
            ->whereIn('status', [
                self::CORRECTION_PENDING,
                self::CORRECTION_MATCHED,
                self::CORRECTION_APPLIED,
            ])
            ->orderBy('original_shift_reading_id')
            ->orderBy('entry_type')
            ->orderBy('pump_number')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $corrections = $query->get();

        if ($corrections->isEmpty()) {
            return collect();
        }

        $readingIds = $corrections
            ->flatMap(fn ($entry) => [
                (int) ($entry->original_shift_reading_id ?? 0),
                (int) ($entry->replacement_shift_reading_id ?? 0),
            ])
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $readings = DB::table('shift_readings')
            ->whereIn('id', $readingIds->all())
            ->get()
            ->keyBy(fn ($reading) => (int) $reading->id);
        $userIds = $readings
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $userNames = $this->hasTable('users') && $this->hasColumn('users', 'name')
            ? DB::table('users')->whereIn('id', $userIds->all())->pluck('name', 'id')
            : collect();

        return $corrections
            ->groupBy(fn ($entry) => (int) $entry->original_shift_reading_id)
            ->map(function (Collection $entries) use ($readings, $userNames, $staffId) {
                $first = $entries->first();
                $originalId = (int) ($first->original_shift_reading_id ?? 0);
                $replacementId = (int) ($entries->pluck('replacement_shift_reading_id')->filter()->first() ?? 0);
                $original = $readings->get($originalId);
                $replacement = $replacementId > 0 ? $readings->get($replacementId) : null;
                $reading = $replacement ?: $original;
                $userId = (int) ($reading->user_id ?? 0);

                if ($staffId !== null && $userId !== $staffId) {
                    return null;
                }

                $pending = $entries->contains(fn ($entry) => ($entry->status ?? null) === self::CORRECTION_PENDING);
                $pumpEntries = $entries
                    ->map(fn ($entry) => $this->formatCorrectionPumpEntry($entry))
                    ->values()
                    ->all();
                $createdAt = $entries->max('created_at') ?: ($reading->created_at ?? null);

                return (object) [
                    'id' => $replacementId > 0 ? $replacementId : $originalId,
                    'user_id' => $userId,
                    'staff_name' => trim((string) ($userNames[$userId] ?? 'Staff')) ?: 'Staff',
                    'period_from' => $reading->period_from ?? null,
                    'period_to' => $reading->period_to ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'status' => $pending ? 'Correction Pending' : 'Correction Applied',
                    'is_liters_movement_correction' => true,
                    'can_adjust_liters' => false,
                    'original_shift_reading_id' => $originalId,
                    'replacement_shift_reading_id' => $replacementId ?: null,
                    'correction_ids' => $entries->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'correction_pump_entries' => $pumpEntries,
                ];
            })
            ->filter()
            ->values();
    }

    private function formatCorrectionPumpEntry(object $entry): array
    {
        $liters = $this->liters($entry->liters ?? 0);
        $amount = $this->money($entry->amount ?? 0);
        $fuelType = $this->normalizeFuelType(
            $entry->fuel_type ?? (self::PUMP_FUEL_TYPES[(int) ($entry->pump_number ?? 0)] ?? 'N/A')
        );
        $price = abs($liters) > 0
            ? abs($amount / $liters)
            : $this->priceForFuel($fuelType);

        return [
            'pump_id' => (int) ($entry->pump_number ?? 0),
            'fuel_type' => $fuelType,
            'liters' => $liters,
            'raw_liters' => $liters,
            'deducted_liters' => 0.0,
            'price_per_liter' => $this->money($price),
            'amount' => $amount,
            'raw_amount' => $amount,
            'deducted_amount' => 0.0,
            'total_sales' => $this->money($entry->total_sales ?? $amount),
            'discounts' => $this->money($entry->discounts ?? 0),
            'net_sales' => $this->money($entry->net_sales ?? $amount),
            'sales_transaction_count' => 0,
            'adjustments' => [],
            'correction_id' => (int) ($entry->id ?? 0),
            'correction_entry_type' => (string) ($entry->entry_type ?? ''),
            'correction_status' => (string) ($entry->status ?? ''),
            'original_shift_reading_id' => (int) ($entry->original_shift_reading_id ?? 0),
            'replacement_shift_reading_id' => ! empty($entry->replacement_shift_reading_id)
                ? (int) $entry->replacement_shift_reading_id
                : null,
        ];
    }

    private function scopeToIncludedShiftReadings($query): void
    {
        if ($this->hasColumn('shift_readings', 'status')) {
            $query->whereIn(DB::raw('LOWER(TRIM(shift_readings.status))'), $this->includedReadingStatuses());
        }
    }

    private function scopeToExcludedShiftReadings($query): void
    {
        if (! $this->hasColumn('shift_readings', 'status')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($query) {
            $query->whereNull('shift_readings.status')
                ->orWhereNotIn(DB::raw('LOWER(TRIM(shift_readings.status))'), $this->includedReadingStatuses());
        });
    }

    private function pumpEntriesForReading(object $reading, array $activeAdjustments = [], array $netSalesByPump = []): array
    {
        if ((bool) ($reading->is_liters_movement_correction ?? false)) {
            return array_values((array) ($reading->correction_pump_entries ?? []));
        }

        $entries = [];

        foreach (self::PUMP_FUEL_TYPES as $pumpId => $fuelType) {
            $rawLiters = $this->rawPumpLiters($reading, $pumpId);
            $deductedLiters = $this->liters(min($rawLiters, $this->activeAdjustmentLitersForPump($activeAdjustments, $pumpId)));
            $liters = $this->liters(max(0, $rawLiters - $deductedLiters));

            if ($rawLiters <= 0 && $deductedLiters <= 0) {
                continue;
            }

            $rawAmount = $this->rawPumpAmount($reading, $pumpId);
            $deductedAmount = $this->money(min($rawAmount, $this->activeAdjustmentAmountForPump($activeAdjustments, $pumpId)));
            $amount = $this->money(max(0, $rawAmount - $deductedAmount));
            $configuredPrice = $this->priceForFuel($fuelType);
            $price = $configuredPrice > 0
                ? $configuredPrice
                : ($rawAmount > 0 && $rawLiters > 0 ? $rawAmount / $rawLiters : 0);
            $netSales = $netSalesByPump[$pumpId] ?? $this->emptyPumpNetSalesRow($pumpId, $fuelType);

            $entries[] = [
                'pump_id' => $pumpId,
                'fuel_type' => $fuelType,
                'liters' => $liters,
                'raw_liters' => $rawLiters,
                'deducted_liters' => $deductedLiters,
                'price_per_liter' => $this->money($price),
                'amount' => $amount,
                'raw_amount' => $rawAmount,
                'deducted_amount' => $deductedAmount,
                'total_sales' => $this->money($netSales['total_sales'] ?? 0),
                'discounts' => $this->money($netSales['discounts'] ?? 0),
                'net_sales' => $this->money($netSales['net_sales'] ?? 0),
                'sales_transaction_count' => (int) ($netSales['transaction_count'] ?? 0),
                'adjustments' => collect($activeAdjustments)
                    ->filter(fn ($adjustment) => (int) ($adjustment['pump_number'] ?? 0) === $pumpId)
                    ->values()
                    ->all(),
            ];
        }

        return $entries;
    }

    private function netSalesByPumpForReading(object $reading, array $activeAdjustments = []): array
    {
        if ((bool) ($reading->is_liters_movement_correction ?? false)) {
            $rows = $this->defaultPumpNetSalesBreakdown();

            foreach ((array) ($reading->correction_pump_entries ?? []) as $entry) {
                $pumpId = (int) ($entry['pump_id'] ?? 0);

                if ($pumpId <= 0) {
                    continue;
                }

                $row = $rows[$pumpId] ?? $this->emptyPumpNetSalesRow(
                    $pumpId,
                    (string) ($entry['fuel_type'] ?? 'N/A')
                );
                $row['total_sales'] = $this->money($row['total_sales'] + (float) ($entry['total_sales'] ?? 0));
                $row['discounts'] = $this->money($row['discounts'] + (float) ($entry['discounts'] ?? 0));
                $row['net_sales'] = $this->money($row['net_sales'] + (float) ($entry['net_sales'] ?? 0));
                $rows[$pumpId] = $row;
            }

            return $rows;
        }

        $readingId = (int) ($reading->id ?? 0);
        $cacheKey = $readingId . ':' . md5(json_encode($activeAdjustments));

        if ($readingId > 0 && array_key_exists($cacheKey, $this->readingNetSalesCache)) {
            return $this->readingNetSalesCache[$cacheKey];
        }

        $pumpRows = collect($this->defaultPumpNetSalesBreakdown())
            ->mapWithKeys(function (array $row, int $pumpId) use ($reading) {
                $salesStart = (float) ($reading->{"pump{$pumpId}_sales_start"} ?? 0);
                $salesEnd = (float) ($reading->{"pump{$pumpId}_sales_end"} ?? $salesStart);
                $totalSales = $this->money($salesEnd - $salesStart);

                $row['total_sales'] = $totalSales;
                $row['net_sales'] = $totalSales;

                return [$pumpId => $row];
            })
            ->all();

        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'pump_id')
        ) {
            return $this->cacheReadingNetSales(
                $cacheKey,
                $this->applyAdjustmentAmountsToPumpRows($pumpRows, $activeAdjustments)
            );
        }

        $query = DB::table('sales');

        if ($this->readingHasLockedSales($reading)) {
            $query->where('locked_by_shift_reading_id', $readingId);
        } else {
            if ($this->hasColumn('sales', 'user_id') && isset($reading->user_id)) {
                $query->where('user_id', (int) $reading->user_id);
            }

            if ($this->hasColumn('sales', 'created_at')) {
                try {
                    $from = $reading->period_from ?? ($reading->created_at ?? now());
                    $to = $reading->period_to ?? $from;
                    $query->whereBetween('created_at', [
                        Carbon::parse($from)->startOfDay(),
                        Carbon::parse($to)->endOfDay(),
                    ]);
                } catch (\Throwable) {
                    return $this->cacheReadingNetSales(
                        $cacheKey,
                        $this->applyAdjustmentAmountsToPumpRows($pumpRows, $activeAdjustments)
                    );
                }
            }
        }

        if ($this->hasColumn('sales', 'payment_type')) {
            $query->where(function ($query) {
                $query->whereNull('payment_type')
                    ->orWhereRaw('LOWER(payment_type) <> ?', ['collection']);
            });
        }

        if ($this->hasColumn('sales', 'customer_payment_voided_at')) {
            $query->whereNull('customer_payment_voided_at');
        }

        $discount = $this->hasColumn('sales', 'discount') ? 'COALESCE(discount, 0)' : '0';
        $manualDiscount = $this->hasColumn('sales', 'manual_discount') ? 'COALESCE(manual_discount, 0)' : '0';
        $salesRows = $query
            ->where('pump_id', '>', 0)
            ->selectRaw("
                pump_id,
                COALESCE(SUM({$discount} + {$manualDiscount}), 0) as discounts,
                COUNT(*) as transaction_count
            ")
            ->groupBy('pump_id')
            ->get();

        foreach ($salesRows as $sales) {
            $pumpId = (int) ($sales->pump_id ?? 0);

            if ($pumpId <= 0) {
                continue;
            }

            $row = $pumpRows[$pumpId] ?? $this->emptyPumpNetSalesRow(
                $pumpId,
                self::PUMP_FUEL_TYPES[$pumpId] ?? 'N/A'
            );
            $discounts = $this->money($sales->discounts ?? 0);

            $row['discounts'] = $discounts;
            $row['net_sales'] = $this->money(($row['total_sales'] ?? 0) - $discounts);
            $row['transaction_count'] = (int) ($sales->transaction_count ?? 0);
            $pumpRows[$pumpId] = $row;
        }

        $pumpRows = $this->applyAdjustmentAmountsToPumpRows($pumpRows, $activeAdjustments);
        ksort($pumpRows);

        return $this->cacheReadingNetSales($cacheKey, $pumpRows);
    }

    private function applyAdjustmentAmountsToPumpRows(array $pumpRows, array $activeAdjustments): array
    {
        foreach ($pumpRows as $pumpId => $row) {
            $deductedAmount = $this->activeAdjustmentAmountForPump($activeAdjustments, (int) $pumpId);

            if ($deductedAmount <= 0) {
                continue;
            }

            $row['total_sales'] = $this->money(max(0, (float) ($row['total_sales'] ?? 0) - $deductedAmount));
            $row['net_sales'] = $this->money($row['total_sales'] - (float) ($row['discounts'] ?? 0));
            $pumpRows[$pumpId] = $row;
        }

        return $pumpRows;
    }

    private function readingHasLockedSales(object $reading): bool
    {
        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'locked_by_shift_reading_id')
            || ! isset($reading->id)
        ) {
            return false;
        }

        $readingId = (int) $reading->id;

        if (! array_key_exists($readingId, $this->lockedSalesPresence)) {
            $this->lockedSalesPresence[$readingId] = DB::table('sales')
                ->where('locked_by_shift_reading_id', $readingId)
                ->exists();
        }

        return $this->lockedSalesPresence[$readingId];
    }

    private function defaultPumpNetSalesBreakdown(): array
    {
        return collect(self::PUMP_FUEL_TYPES)
            ->mapWithKeys(fn ($fuelType, $pumpId) => [
                (int) $pumpId => $this->emptyPumpNetSalesRow((int) $pumpId, $fuelType),
            ])
            ->all();
    }

    private function emptyPumpNetSalesRow(int $pumpId, string $fuelType): array
    {
        return [
            'pump_id' => $pumpId,
            'pump_label' => 'Pump ' . $pumpId,
            'fuel_type' => $fuelType,
            'liters' => 0.0,
            'total_sales' => 0.0,
            'discounts' => 0.0,
            'net_sales' => 0.0,
            'transaction_count' => 0,
        ];
    }

    private function cacheReadingNetSales(string $cacheKey, array $pumpRows): array
    {
        if ($cacheKey !== '') {
            $this->readingNetSalesCache[$cacheKey] = $pumpRows;
        }

        return $pumpRows;
    }

    private function rawPumpLiters(object $reading, int $pumpId): float
    {
        $litersStart = (float) ($reading->{"pump{$pumpId}_liters_start"} ?? 0);
        $litersEnd = (float) ($reading->{"pump{$pumpId}_liters_end"} ?? $litersStart);

        return $this->liters(max(0, $litersEnd - $litersStart));
    }

    private function rawPumpAmount(object $reading, int $pumpId): float
    {
        $salesStart = (float) ($reading->{"pump{$pumpId}_sales_start"} ?? 0);
        $salesEnd = (float) ($reading->{"pump{$pumpId}_sales_end"} ?? $salesStart);

        return $this->money(max(0, $salesEnd - $salesStart));
    }

    private function adjustmentsForReadings(array $readingIds, bool $activeOnly = false): array
    {
        $readingIds = collect($readingIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($readingIds === [] || ! $this->hasTable('liters_movement_adjustments')) {
            return [];
        }

        $query = DB::table('liters_movement_adjustments')
            ->whereIn('liters_movement_adjustments.shift_reading_id', $readingIds);

        if ($activeOnly) {
            $query->whereNull('liters_movement_adjustments.voided_at');
        }

        $selects = ['liters_movement_adjustments.*'];

        if ($this->hasTable('users') && $this->hasColumn('users', 'id') && $this->hasColumn('users', 'name')) {
            $query
                ->leftJoin('users as creators', 'liters_movement_adjustments.created_by', '=', 'creators.id')
                ->leftJoin('users as authorizers', 'liters_movement_adjustments.authorized_by', '=', 'authorizers.id')
                ->leftJoin('users as voiders', 'liters_movement_adjustments.voided_by', '=', 'voiders.id')
                ->leftJoin('users as void_authorizers', 'liters_movement_adjustments.void_authorized_by', '=', 'void_authorizers.id');

            $selects[] = DB::raw("COALESCE(creators.name, 'Staff') as created_by_name");
            $selects[] = DB::raw("COALESCE(authorizers.name, 'Semi Admin') as authorized_by_name");
            $selects[] = DB::raw('voiders.name as voided_by_name');
            $selects[] = DB::raw('void_authorizers.name as void_authorized_by_name');
        } else {
            $selects[] = DB::raw("'Staff' as created_by_name");
            $selects[] = DB::raw("'Semi Admin' as authorized_by_name");
            $selects[] = DB::raw('NULL as voided_by_name');
            $selects[] = DB::raw('NULL as void_authorized_by_name');
        }

        return $query
            ->select($selects)
            ->orderBy('liters_movement_adjustments.created_at')
            ->orderBy('liters_movement_adjustments.id')
            ->get()
            ->map(fn ($adjustment) => $this->formatAdjustment($adjustment))
            ->groupBy('shift_reading_id')
            ->map(fn ($adjustments) => $adjustments->values()->all())
            ->all();
    }

    private function formatAdjustment(object $adjustment): array
    {
        return [
            'id' => (int) ($adjustment->id ?? 0),
            'liters_tracking_period_id' => (int) ($adjustment->liters_tracking_period_id ?? 0),
            'shift_reading_id' => (int) ($adjustment->shift_reading_id ?? 0),
            'pump_number' => (int) ($adjustment->pump_number ?? 0),
            'liters' => $this->liters($adjustment->liters ?? 0),
            'amount' => $this->adjustmentAmount($adjustment),
            'reason' => (string) ($adjustment->reason ?? ''),
            'created_at' => $adjustment->created_at ?? null,
            'created_by_id' => (int) ($adjustment->created_by ?? 0),
            'created_by' => trim((string) ($adjustment->created_by_name ?? 'Staff')) ?: 'Staff',
            'authorized_by' => trim((string) ($adjustment->authorized_by_name ?? 'Semi Admin')) ?: 'Semi Admin',
            'voided_at' => $adjustment->voided_at ?? null,
            'voided_by' => trim((string) ($adjustment->voided_by_name ?? '')) ?: null,
            'void_authorized_by' => trim((string) ($adjustment->void_authorized_by_name ?? '')) ?: null,
            'void_reason' => (string) ($adjustment->void_reason ?? ''),
            'status' => empty($adjustment->voided_at) ? 'active' : 'voided',
        ];
    }

    private function activeAdjustmentLitersForPump(array $adjustments, int $pumpId): float
    {
        return $this->liters(collect($adjustments)
            ->filter(fn ($adjustment) => (int) ($adjustment['pump_number'] ?? 0) === $pumpId)
            ->filter(fn ($adjustment) => ($adjustment['status'] ?? 'active') === 'active')
            ->sum(fn ($adjustment) => (float) ($adjustment['liters'] ?? 0)));
    }

    private function activeAdjustmentAmountForPump(array $adjustments, int $pumpId): float
    {
        return $this->money(collect($adjustments)
            ->filter(fn ($adjustment) => (int) ($adjustment['pump_number'] ?? 0) === $pumpId)
            ->filter(fn ($adjustment) => ($adjustment['status'] ?? 'active') === 'active')
            ->sum(fn ($adjustment) => (float) ($adjustment['amount'] ?? 0)));
    }

    private function adjustmentAmount(object $adjustment): float
    {
        $amount = $this->money($adjustment->amount ?? 0);

        if ($amount > 0) {
            return $amount;
        }

        $pumpNumber = (int) ($adjustment->pump_number ?? 0);
        $price = $this->priceForFuel(self::PUMP_FUEL_TYPES[$pumpNumber] ?? '');

        return $this->money(((float) ($adjustment->liters ?? 0)) * $price);
    }

    private function emptySummary(?object $period): array
    {
        $tanks = $this->preparedTanks();
        $tankBreakdown = $this->formatTankBreakdown($this->defaultTankBreakdown($period, $tanks));
        $limit = $this->liters(collect($tankBreakdown)->sum(fn ($tank) => (float) ($tank['limit'] ?? 0)));

        return [
            'total_liters' => 0.0,
            'total_amount' => 0.0,
            'total_sales' => 0.0,
            'total_discounts' => 0.0,
            'total_net_sales' => 0.0,
            'limit' => $limit,
            'configured_limit_count' => collect($tankBreakdown)->filter(fn ($tank) => (bool) ($tank['has_limit'] ?? false))->count(),
            'remaining_liters' => $limit,
            'over_liters' => 0.0,
            'percent' => 0.0,
            'progress_width' => 0.0,
            'transaction_count' => 0,
            'submission_count' => 0,
            'pump_entry_count' => 0,
            'pump_net_sales' => array_values($this->defaultPumpNetSalesBreakdown()),
            'fuel_breakdown' => array_values($this->defaultFuelBreakdown($tanks)),
            'tank_breakdown' => $tankBreakdown,
            'staff_breakdown' => [],
            'reached_limits' => [],
            'status' => $this->statusFor(0, 1),
        ];
    }

    private function defaultFuelBreakdown(?Collection $tanks = null): array
    {
        $fuelTypes = collect(self::DEFAULT_FUEL_TYPES);

        if ($tanks) {
            $fuelTypes = $tanks
                ->pluck('fuel_type')
                ->merge($fuelTypes);
        }

        return $fuelTypes
            ->map(fn ($fuel) => $this->normalizeFuelType($fuel))
            ->filter()
            ->unique()
            ->sortBy(fn ($fuel) => $this->fuelSortKey($fuel))
            ->mapWithKeys(fn ($fuel) => [$fuel => [
                'fuel_type' => $fuel,
                'liters' => 0.0,
                'amount' => 0.0,
                'transaction_count' => 0,
            ]])
            ->all();
    }

    private function defaultTankBreakdown(?object $period, Collection $tanks): array
    {
        $limits = $this->storedTankLimitsForPeriod($period, $tanks);

        return $tanks
            ->mapWithKeys(function (array $tank) use ($limits) {
                $limitKey = $this->tankLimitKey($tank);
                $fuelType = $this->normalizeFuelType($tank['fuel_type'] ?? '');
                $limit = array_key_exists($limitKey, $limits)
                    ? $limits[$limitKey]
                    : $this->defaultLimitForFuel($fuelType);

                return [
                    (int) $tank['id'] => [
                        'id' => (int) $tank['id'],
                        'limit_key' => $limitKey,
                        'name' => $tank['name'],
                        'fuel_type' => $fuelType,
                        'connected_pumps_display' => $tank['connected_pumps_display'],
                        'price_per_liter' => $this->priceForFuel($fuelType),
                        'limit' => $limit,
                        'has_limit' => $limit !== null && (float) $limit > 0,
                        'liters' => 0.0,
                        'amount' => 0.0,
                        'total_sales' => 0.0,
                        'discounts' => 0.0,
                        'net_sales' => 0.0,
                        'remaining_liters' => $limit !== null ? (float) $limit : 0.0,
                        'over_liters' => 0.0,
                        'percent' => 0.0,
                        'progress_width' => 0.0,
                        'transaction_count' => 0,
                        'entries' => [],
                        'status' => $this->statusFor(0, (float) ($limit ?? 0)),
                    ],
                ];
            })
            ->all();
    }

    private function formatTankBreakdown(array $tankRows): array
    {
        return collect($tankRows)
            ->map(function (array $tank) {
                $limit = $tank['limit'];
                $hasLimit = $limit !== null && (float) $limit > 0;
                $entries = collect($tank['entries'] ?? []);
                $liters = $this->liters($tank['liters'] ?? 0);
                $amount = $this->money($tank['amount'] ?? 0);
                $entryTotalSales = $this->money($entries->sum(fn ($entry) => (float) ($entry['total_sales'] ?? $entry['amount'] ?? 0)));
                $entryDiscounts = $this->money($entries->sum(fn ($entry) => (float) ($entry['discounts'] ?? 0)));
                $entryNetSales = $this->money($entries->sum(function ($entry) {
                    if (array_key_exists('net_sales', $entry)) {
                        return (float) $entry['net_sales'];
                    }

                    return (float) ($entry['total_sales'] ?? $entry['amount'] ?? 0) - (float) ($entry['discounts'] ?? 0);
                }));
                $totalSales = $this->money(array_key_exists('total_sales', $tank) ? $tank['total_sales'] : ($entryTotalSales ?: $amount));
                $discounts = $this->money(array_key_exists('discounts', $tank) ? $tank['discounts'] : $entryDiscounts);
                $netSales = $this->money(array_key_exists('net_sales', $tank) ? $tank['net_sales'] : ($entryNetSales ?: ($totalSales - $discounts)));
                $limitValue = $hasLimit ? $this->liters($limit) : null;
                $percent = $hasLimit ? round(($liters / $limitValue) * 100, 2) : 0.0;

                $tank['liters'] = $liters;
                $tank['amount'] = $amount;
                $tank['total_sales'] = $totalSales;
                $tank['discounts'] = $discounts;
                $tank['net_sales'] = $netSales;
                $tank['price_per_liter'] = $this->money($tank['price_per_liter'] ?? 0);
                $tank['limit'] = $limitValue;
                $tank['has_limit'] = $hasLimit;
                $tank['remaining_liters'] = $hasLimit ? $this->liters(max(0, $limitValue - $liters)) : 0.0;
                $tank['over_liters'] = $hasLimit ? $this->liters(max(0, $liters - $limitValue)) : 0.0;
                $tank['percent'] = $percent;
                $tank['progress_width'] = min(100, max(0, $percent));
                $tank['transaction_count'] = (int) ($tank['transaction_count'] ?? 0);
                $tank['entries'] = $entries->sortBy('created_at')->values()->all();
                $tank['status'] = $this->statusFor($liters, (float) ($limitValue ?? 0));

                return $tank;
            })
            ->sortBy(fn ($tank) => $this->fuelSortKey($tank['fuel_type']) . '-' . str_pad((string) ($tank['id'] ?? 0), 6, '0', STR_PAD_LEFT))
            ->values()
            ->all();
    }

    private function ensureUnassignedTankBreakdown(array &$tankRows, array $context = []): void
    {
        if (isset($tankRows['unassigned'])) {
            return;
        }

        $fuelType = $this->normalizeFuelType($context['fuel_type'] ?? 'N/A');

        $tankRows['unassigned'] = [
            'id' => 'unassigned',
            'limit_key' => 'unassigned',
            'name' => 'Unassigned',
            'fuel_type' => $fuelType,
            'connected_pumps_display' => $context['connected_pumps_display'] ?? 'No tank match',
            'price_per_liter' => $this->priceForFuel($fuelType),
            'limit' => null,
            'has_limit' => false,
            'liters' => 0.0,
            'amount' => 0.0,
            'total_sales' => 0.0,
            'discounts' => 0.0,
            'net_sales' => 0.0,
            'remaining_liters' => 0.0,
            'over_liters' => 0.0,
            'percent' => 0.0,
            'progress_width' => 0.0,
            'transaction_count' => 0,
            'entries' => [],
            'status' => $this->statusFor(0, 0),
        ];
    }

    private function preparedTanks(): Collection
    {
        if (! $this->hasTable('tanks')) {
            return collect();
        }

        return DB::table('tanks')
            ->when($this->hasColumn('tanks', 'id'), fn ($query) => $query->orderBy('id'))
            ->get()
            ->filter(function ($tank) {
                $status = strtolower(trim((string) ($tank->status ?? '')));

                return $status === '' || $status === 'active';
            })
            ->map(function ($tank) {
                $fuelType = $this->normalizeFuelType($tank->fuel_type ?? 'N/A');
                $pumpNumbers = $this->tankPumpNumbers($tank);

                return [
                    'id' => (int) ($tank->id ?? 0),
                    'name' => ($tank->name ?? null) ?: $fuelType,
                    'fuel_type' => $fuelType,
                    'connected_pump_numbers' => $pumpNumbers,
                    'connected_pumps_display' => $pumpNumbers->isEmpty()
                        ? 'N/A'
                        : $pumpNumbers->map(fn ($pumpNumber) => 'P' . $pumpNumber)->implode(', '),
                ];
            })
            ->sortBy(fn ($tank) => $this->fuelSortKey($tank['fuel_type']) . '-' . str_pad((string) $tank['id'], 6, '0', STR_PAD_LEFT))
            ->values();
    }

    private function tankPumpNumbers($tank): Collection
    {
        $connectedPumps = trim((string) ($tank->connected_pumps ?? ''));
        $fuelKey = $this->normalizeFuelKey($tank->fuel_type ?? '');

        $pumpNumbers = collect(preg_split('/\s*,\s*/', $connectedPumps, -1, PREG_SPLIT_NO_EMPTY))
            ->map(function ($pumpLabel) {
                preg_match('/\d+/', (string) $pumpLabel, $matches);

                return isset($matches[0]) ? (int) $matches[0] : null;
            })
            ->filter(fn ($pumpNumber) => $pumpNumber !== null && $pumpNumber > 0);

        if ($pumpNumbers->isEmpty()) {
            $pumpNumbers = match ($fuelKey) {
                'premium' => collect([1, 3]),
                'regular' => collect([2, 4]),
                'diesel' => collect([5, 6]),
                default => collect(),
            };
        }

        return $pumpNumbers->unique()->values();
    }

    private function pumpToTankMap(Collection $tanks): array
    {
        $map = [];

        foreach ($tanks as $tank) {
            foreach ($tank['connected_pump_numbers'] as $pumpNumber) {
                $pumpNumber = (int) $pumpNumber;

                if ($pumpNumber > 0 && ! isset($map[$pumpNumber])) {
                    $map[$pumpNumber] = (int) $tank['id'];
                }
            }
        }

        return $map;
    }

    private function uniqueFuelToTankMap(Collection $tanks): array
    {
        $counts = [];
        $map = [];

        foreach ($tanks as $tank) {
            $fuel = $this->normalizeFuelKey($tank['fuel_type']);

            if ($fuel === '') {
                continue;
            }

            $counts[$fuel] = ($counts[$fuel] ?? 0) + 1;
            $map[$fuel] = (int) $tank['id'];
        }

        foreach ($counts as $fuel => $count) {
            if ($count > 1) {
                unset($map[$fuel]);
            }
        }

        return $map;
    }

    private function storedTankLimitsForPeriod(?object $period, Collection $tanks): array
    {
        $stored = [];

        if ($period && $this->hasColumn('liters_tracking_periods', 'tank_limits')) {
            $decoded = json_decode((string) ($period->tank_limits ?? ''), true);
            $stored = is_array($decoded) ? $this->sanitizeTankLimits($decoded) : [];
        }

        if ($stored !== []) {
            return $stored;
        }

        return $tanks
            ->mapWithKeys(function (array $tank) use ($period) {
                $fuelType = $this->normalizeFuelType($tank['fuel_type'] ?? '');
                $legacyLimit = (float) ($period->liters_limit ?? self::DEFAULT_LIMIT);

                return [$this->tankLimitKey($tank) => $this->defaultLimitForFuel($fuelType, $legacyLimit)];
            })
            ->all();
    }

    private function defaultStoredTankLimits(float $legacyLimit): array
    {
        return $this->preparedTanks()
            ->mapWithKeys(fn (array $tank) => [
                $this->tankLimitKey($tank) => $this->defaultLimitForFuel($tank['fuel_type'], $legacyLimit),
            ])
            ->all();
    }

    private function sanitizeTankLimits(array $limits): array
    {
        $sanitized = [];

        foreach ($limits as $key => $value) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            if ($value === null || $value === '') {
                $sanitized[$key] = null;
                continue;
            }

            $numeric = round((float) $value, 2);
            $sanitized[$key] = $numeric > 0 ? $numeric : null;
        }

        return $sanitized;
    }

    private function legacyLimitFromLimits(array $limits, Collection $tanks): float
    {
        foreach ($tanks as $tank) {
            if ($this->normalizeFuelKey($tank['fuel_type']) !== 'regular') {
                continue;
            }

            $key = $this->tankLimitKey($tank);

            if (isset($limits[$key]) && (float) $limits[$key] > 0) {
                return (float) $limits[$key];
            }
        }

        foreach ($limits as $value) {
            if ($value !== null && (float) $value > 0) {
                return (float) $value;
            }
        }

        return self::DEFAULT_LIMIT;
    }

    private function tankLimitKey(array $tank): string
    {
        return 'tank:' . (int) ($tank['id'] ?? 0);
    }

    private function defaultLimitForFuel($fuelType, ?float $legacyLimit = null): ?float
    {
        $fuelKey = $this->normalizeFuelKey($fuelType);

        if ($fuelKey === 'regular' && $legacyLimit !== null && $legacyLimit > 0) {
            return round($legacyLimit, 2);
        }

        return self::DEFAULT_FUEL_LIMITS[$fuelKey] ?? null;
    }

    private function priceForFuel($fuelType): float
    {
        return $this->currentFuelPrices()[$this->normalizeFuelKey($fuelType)] ?? 0.0;
    }

    private function currentFuelPrices(): array
    {
        if ($this->fuelPrices !== null) {
            return $this->fuelPrices;
        }

        if (
            ! $this->hasTable('fuel_prices')
            || ! $this->hasColumn('fuel_prices', 'fuel_type')
            || ! $this->hasColumn('fuel_prices', 'price_per_liter')
        ) {
            return $this->fuelPrices = [];
        }

        return $this->fuelPrices = DB::table('fuel_prices')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $this->normalizeFuelKey($row->fuel_type ?? '') => $this->money($row->price_per_liter ?? 0),
            ])
            ->all();
    }

    private function statusFor(float $totalLiters, float $limit): array
    {
        if ($limit > 0 && $totalLiters >= $limit) {
            return [
                'key' => 'limit_reached',
                'label' => 'Limit Reached',
                'badge_class' => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                'progress_class' => 'bg-error-500',
                'border_class' => 'border-error-200 bg-error-50/60 dark:border-error-500/25 dark:bg-error-500/10',
            ];
        }

        if ($limit > 0 && $totalLiters >= ($limit * 0.8)) {
            return [
                'key' => 'near_limit',
                'label' => 'Near Limit',
                'badge_class' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                'progress_class' => 'bg-warning-500',
                'border_class' => 'border-warning-200 bg-warning-50/60 dark:border-warning-500/25 dark:bg-warning-500/10',
            ];
        }

        return [
            'key' => 'normal',
            'label' => $limit > 0 ? 'Normal' : 'No Limit',
            'badge_class' => $limit > 0
                ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'
                : 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300',
            'progress_class' => $limit > 0 ? 'bg-success-500' : 'bg-gray-400',
            'border_class' => $limit > 0
                ? 'border-success-200 bg-success-50/60 dark:border-success-500/25 dark:bg-success-500/10'
                : 'border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]',
        ];
    }

    private function normalizeFuelType($fuelType): string
    {
        $value = strtolower(trim((string) $fuelType));

        if (str_contains($value, 'diesel')) {
            return 'Diesel';
        }

        if (str_contains($value, 'premium')) {
            return 'Premium';
        }

        if (str_contains($value, 'regular') || str_contains($value, 'unleaded')) {
            return 'Unleaded';
        }

        return trim((string) $fuelType) !== '' ? ucfirst((string) $fuelType) : 'N/A';
    }

    private function normalizeFuelKey($fuelType): string
    {
        $value = strtolower(trim((string) $fuelType));

        if (str_contains($value, 'diesel')) {
            return 'diesel';
        }

        if (str_contains($value, 'premium')) {
            return 'premium';
        }

        if (str_contains($value, 'regular') || str_contains($value, 'unleaded')) {
            return 'regular';
        }

        return $value;
    }

    private function fuelSortKey($fuelType): string
    {
        return match ($this->normalizeFuelKey($fuelType)) {
            'regular' => '10',
            'premium' => '20',
            'diesel' => '30',
            default => '90-' . strtolower((string) $fuelType),
        };
    }

    private function decodeSnapshot($snapshot): array
    {
        $decoded = json_decode((string) $snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function snapshotWithSalesFallback(object $period, array $snapshot): array
    {
        if (! $this->snapshotNeedsSalesFallback($snapshot)) {
            return $snapshot;
        }

        $rows = $this->snapshotSalesRows($period, $snapshot);

        if ($rows->isEmpty()) {
            return $snapshot;
        }

        $summary = $this->summaryFromRows($period, $rows, true);
        $snapshot['total_sales'] = $snapshot['total_sales'] ?? ($summary['total_sales'] ?? 0);
        $snapshot['total_discounts'] = $snapshot['total_discounts'] ?? ($summary['total_discounts'] ?? 0);
        $snapshot['total_net_sales'] = $snapshot['total_net_sales'] ?? ($summary['total_net_sales'] ?? 0);

        if (empty($snapshot['pump_net_sales']) && ! empty($summary['pump_net_sales'])) {
            $snapshot['pump_net_sales'] = $summary['pump_net_sales'];
        }

        if (empty($snapshot['tank_breakdown']) || ! is_array($snapshot['tank_breakdown'])) {
            return $snapshot;
        }

        $salesByReadingPump = [];

        foreach (($summary['tank_breakdown'] ?? []) as $tank) {
            foreach (($tank['entries'] ?? []) as $entry) {
                $readingId = (int) ($entry['id'] ?? 0);
                $pumpId = (int) ($entry['pump_id'] ?? 0);

                if ($readingId <= 0 || $pumpId <= 0) {
                    continue;
                }

                $salesByReadingPump["{$readingId}:{$pumpId}"] = [
                    'total_sales' => $entry['total_sales'] ?? 0,
                    'discounts' => $entry['discounts'] ?? 0,
                    'net_sales' => $entry['net_sales'] ?? 0,
                    'sales_transaction_count' => $entry['sales_transaction_count'] ?? 0,
                ];
            }
        }

        $snapshot['tank_breakdown'] = collect($snapshot['tank_breakdown'])
            ->map(function ($tank) use ($salesByReadingPump) {
                if (empty($tank['entries']) || ! is_array($tank['entries'])) {
                    return $tank;
                }

                $tank['entries'] = collect($tank['entries'])
                    ->map(function ($entry) use ($salesByReadingPump) {
                        $key = ((int) ($entry['id'] ?? 0)) . ':' . ((int) ($entry['pump_id'] ?? 0));
                        $sales = $salesByReadingPump[$key] ?? null;

                        if (! $sales) {
                            return $entry;
                        }

                        foreach ($sales as $field => $value) {
                            if (! array_key_exists($field, $entry)) {
                                $entry[$field] = $value;
                            }
                        }

                        return $entry;
                    })
                    ->values()
                    ->all();

                return $tank;
            })
            ->values()
            ->all();

        return $snapshot;
    }

    private function snapshotNeedsSalesFallback(array $snapshot): bool
    {
        if (
            ! array_key_exists('total_sales', $snapshot)
            || ! array_key_exists('total_discounts', $snapshot)
            || ! array_key_exists('total_net_sales', $snapshot)
            || empty($snapshot['pump_net_sales'])
        ) {
            return true;
        }

        foreach (($snapshot['tank_breakdown'] ?? []) as $tank) {
            foreach (($tank['entries'] ?? []) as $entry) {
                if (
                    ! array_key_exists('total_sales', $entry)
                    || ! array_key_exists('discounts', $entry)
                    || ! array_key_exists('net_sales', $entry)
                    || ! array_key_exists('sales_transaction_count', $entry)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function snapshotSalesRows(object $period, array $snapshot): Collection
    {
        if (! $this->hasTable('shift_readings')) {
            return collect();
        }

        $readingIds = collect($snapshot['shift_reading_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $query = DB::table('shift_readings');

        if ($readingIds !== []) {
            $query->whereIn('shift_readings.id', $readingIds);
        } elseif ($this->hasColumn('shift_readings', 'liters_tracking_period_id') && (int) ($period->id ?? 0) > 0) {
            $query->where('shift_readings.liters_tracking_period_id', (int) $period->id);
        } else {
            return collect();
        }

        $this->scopeToIncludedShiftReadings($query);

        if ($this->hasTable('users') && $this->hasColumn('users', 'id')) {
            $query->leftJoin('users', 'shift_readings.user_id', '=', 'users.id');
        }

        $staffExpression = $this->hasTable('users') && $this->hasColumn('users', 'name')
            ? "COALESCE(users.name, 'Staff')"
            : "'Staff'";

        return $query
            ->select('shift_readings.*', DB::raw("{$staffExpression} as staff_name"))
            ->orderBy('shift_readings.created_at')
            ->orderBy('shift_readings.id')
            ->get();
    }

    private function lockedActivePeriodForCorrection(object $sourcePeriod): object
    {
        $activePeriod = DB::table('liters_tracking_periods')
            ->where('status', self::ACTIVE_STATUS)
            ->lockForUpdate()
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if ($activePeriod) {
            return $activePeriod;
        }

        $activePeriodId = $this->createPeriod(
            (float) ($sourcePeriod->liters_limit ?? self::DEFAULT_LIMIT),
            now(),
            $this->storedTankLimitsForPeriod($sourcePeriod, $this->preparedTanks())
        );

        $activePeriod = DB::table('liters_tracking_periods')
            ->where('id', $activePeriodId)
            ->lockForUpdate()
            ->first();

        if (! $activePeriod) {
            throw new \RuntimeException('Unable to create an active Liters Movement period for the generated-reading reversal.');
        }

        return $activePeriod;
    }

    private function generatedCorrectionBreakdown(object $period, int $readingId): array
    {
        $snapshot = $this->decodeSnapshot($period->totals_snapshot ?? null);

        return collect($snapshot['tank_breakdown'] ?? [])
            ->flatMap(function ($tank) use ($readingId) {
                $fuelType = $this->normalizeFuelType($tank['fuel_type'] ?? 'N/A');

                return collect($tank['entries'] ?? [])
                    ->filter(fn ($entry) => (int) ($entry['id'] ?? 0) === $readingId)
                    ->map(function ($entry) use ($fuelType) {
                        $totalSales = $this->money($entry['total_sales'] ?? $entry['amount'] ?? 0);
                        $discounts = $this->money($entry['discounts'] ?? 0);

                        return [
                            'pump_number' => (int) ($entry['pump_id'] ?? 0),
                            'fuel_type' => $fuelType,
                            'liters' => $this->liters($entry['liters'] ?? 0),
                            'amount' => $this->money($entry['amount'] ?? $totalSales),
                            'total_sales' => $totalSales,
                            'discounts' => $discounts,
                            'net_sales' => $this->money($entry['net_sales'] ?? ($totalSales - $discounts)),
                        ];
                    });
            })
            ->filter(fn ($entry) => $entry['pump_number'] > 0 && ($entry['liters'] > 0 || $entry['amount'] > 0))
            ->groupBy('pump_number')
            ->map(function (Collection $entries) {
                $first = $entries->first();

                return [
                    'pump_number' => (int) $first['pump_number'],
                    'fuel_type' => (string) $first['fuel_type'],
                    'liters' => $this->liters($entries->sum('liters')),
                    'amount' => $this->money($entries->sum('amount')),
                    'total_sales' => $this->money($entries->sum('total_sales')),
                    'discounts' => $this->money($entries->sum('discounts')),
                    'net_sales' => $this->money($entries->sum('net_sales')),
                ];
            })
            ->values()
            ->all();
    }

    private function replacementCorrectionBreakdown(object $reading): array
    {
        $salesByPump = $this->netSalesByPumpForReading($reading);

        return collect(self::PUMP_FUEL_TYPES)
            ->map(function (string $fuelType, int $pumpId) use ($reading, $salesByPump) {
                $liters = $this->rawPumpLiters($reading, $pumpId);
                $amount = $this->rawPumpAmount($reading, $pumpId);
                $sales = $salesByPump[$pumpId] ?? $this->emptyPumpNetSalesRow($pumpId, $fuelType);

                return [
                    'pump_number' => $pumpId,
                    'fuel_type' => $fuelType,
                    'liters' => $liters,
                    'amount' => $amount,
                    'total_sales' => $this->money($sales['total_sales'] ?? $amount),
                    'discounts' => $this->money($sales['discounts'] ?? 0),
                    'net_sales' => $this->money($sales['net_sales'] ?? $amount),
                ];
            })
            ->filter(fn ($entry) => $entry['liters'] > 0 || $entry['amount'] > 0)
            ->values()
            ->all();
    }

    private function isReplacementReading(int $readingId): bool
    {
        return $readingId > 0
            && $this->hasTable('liters_movement_corrections')
            && DB::table('liters_movement_corrections')
                ->where('replacement_shift_reading_id', $readingId)
                ->where('entry_type', self::CORRECTION_REPLACEMENT)
                ->where('status', '<>', self::CORRECTION_CANCELLED)
                ->exists();
    }

    private function assertNoPendingCorrections(int $periodId): void
    {
        if (! $this->hasTable('liters_movement_corrections')) {
            return;
        }

        $pendingOriginalIds = DB::table('liters_movement_corrections')
            ->where('liters_tracking_period_id', $periodId)
            ->where('entry_type', self::CORRECTION_REVERSAL)
            ->where('status', self::CORRECTION_PENDING)
            ->lockForUpdate()
            ->distinct()
            ->pluck('original_shift_reading_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pendingOriginalIds !== []) {
            throw new \RuntimeException(
                'This Liters Movement period has ' . count($pendingOriginalIds)
                . ' pending generated-reading reversal(s). Submit the corrected Shift Reading before generating the period.'
            );
        }
    }

    private function correctionIdsForPeriod(int $periodId): array
    {
        if (! $this->hasTable('liters_movement_corrections')) {
            return [];
        }

        return DB::table('liters_movement_corrections')
            ->where('liters_tracking_period_id', $periodId)
            ->where('status', '<>', self::CORRECTION_CANCELLED)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function assertCorrectionReady(): void
    {
        if (! $this->hasTable('liters_movement_corrections')) {
            throw new \RuntimeException('Liters Movement generated-reading corrections are not migrated yet. Run php artisan migrate.');
        }
    }

    private function assertAdjustmentReady(): void
    {
        if (! $this->isReady()) {
            throw new \RuntimeException('Liters tracking is not migrated yet. Run php artisan migrate.');
        }

        if (! $this->hasTable('liters_movement_adjustments')) {
            throw new \RuntimeException('Liters movement adjustments are not migrated yet. Run php artisan migrate.');
        }

        if (! $this->hasColumn('liters_movement_adjustments', 'amount')) {
            throw new \RuntimeException('Liters movement adjustment amounts are not migrated yet. Run php artisan migrate.');
        }
    }

    private function isIncludedReadingStatus($status): bool
    {
        $status = strtolower(trim((string) $status));

        return in_array($status, $this->includedReadingStatuses(), true);
    }

    private function assertReadingBelongsToStaff(object $reading, ?int $staffId, string $message): void
    {
        if ($staffId === null) {
            return;
        }

        if ((int) $staffId <= 0 || (int) ($reading->user_id ?? 0) !== (int) $staffId) {
            throw new \RuntimeException($message);
        }
    }

    private function includedReadingStatuses(): array
    {
        return ['pending', 'verified', 'approved'];
    }

    private function touchPeriodAndReading(int $periodId, int $readingId, $timestamp): void
    {
        DB::table('liters_tracking_periods')->where('id', $periodId)->update(['updated_at' => $timestamp]);

        if ($this->hasColumn('shift_readings', 'updated_at')) {
            DB::table('shift_readings')->where('id', $readingId)->update(['updated_at' => $timestamp]);
        }
    }

    private function cleanText($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? Str::limit($value, 255, '') : null;
    }

    private function isReady(): bool
    {
        if (
            ! $this->hasTable('liters_tracking_periods')
            || ! $this->hasTable('shift_readings')
            || ! $this->hasTable('tanks')
            || ! $this->hasColumn('shift_readings', 'liters_tracking_period_id')
            || ! $this->hasColumn('shift_readings', 'id')
            || ! $this->hasColumn('shift_readings', 'user_id')
            || ! $this->hasColumn('shift_readings', 'created_at')
            || ! $this->hasColumn('tanks', 'id')
            || ! $this->hasColumn('tanks', 'fuel_type')
            || ! $this->hasColumn('tanks', 'connected_pumps')
        ) {
            return false;
        }

        foreach (array_keys(self::PUMP_FUEL_TYPES) as $pumpId) {
            if (
                ! $this->hasColumn('shift_readings', "pump{$pumpId}_liters_start")
                || ! $this->hasColumn('shift_readings', "pump{$pumpId}_liters_end")
            ) {
                return false;
            }
        }

        return true;
    }

    private function missingRequirements(): array
    {
        $missing = [];

        foreach (['liters_tracking_periods', 'shift_readings', 'tanks'] as $table) {
            if (! $this->hasTable($table)) {
                $missing[] = $table;
            }
        }

        if (! $this->hasTable('shift_readings')) {
            return $missing;
        }

        foreach (['liters_tracking_period_id', 'id', 'user_id', 'created_at'] as $column) {
            if (! $this->hasColumn('shift_readings', $column)) {
                $missing[] = "shift_readings.{$column}";
            }
        }

        foreach (array_keys(self::PUMP_FUEL_TYPES) as $pumpId) {
            foreach (['liters_start', 'liters_end'] as $suffix) {
                $column = "pump{$pumpId}_{$suffix}";

                if (! $this->hasColumn('shift_readings', $column)) {
                    $missing[] = "shift_readings.{$column}";
                }
            }
        }

        if ($this->hasTable('tanks')) {
            foreach (['id', 'fuel_type', 'connected_pumps'] as $column) {
                if (! $this->hasColumn('tanks', $column)) {
                    $missing[] = "tanks.{$column}";
                }
            }
        }

        return $missing;
    }

    private function emptyPaginator(int $perPage, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            $perPage,
            LengthAwarePaginator::resolveCurrentPage($pageName),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    private function hasTable(string $table): bool
    {
        if (! array_key_exists($table, $this->tablePresence)) {
            $this->tablePresence[$table] = Schema::hasTable($table);
        }

        return $this->tablePresence[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";

        if (! array_key_exists($key, $this->columnPresence)) {
            $this->columnPresence[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }

        return $this->columnPresence[$key];
    }

    private function money($value): float
    {
        return round((float) $value, 2);
    }

    private function liters($value): float
    {
        return round((float) $value, 2);
    }
}
