<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INCLUDED_READING_STATUSES = ['pending', 'verified', 'approved'];

    public function up(): void
    {
        if (
            Schema::hasTable('liters_movement_corrections')
            || ! Schema::hasTable('liters_tracking_periods')
            || ! Schema::hasTable('shift_readings')
        ) {
            return;
        }

        Schema::create('liters_movement_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liters_tracking_period_id')->constrained('liters_tracking_periods')->restrictOnDelete();
            $table->foreignId('generated_period_id')->constrained('liters_tracking_periods')->restrictOnDelete();
            $table->foreignId('original_shift_reading_id')->constrained('shift_readings')->restrictOnDelete();
            $table->foreignId('replacement_shift_reading_id')->nullable()->constrained('shift_readings')->restrictOnDelete();
            $table->string('entry_type', 16);
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('pump_number');
            $table->string('fuel_type', 32)->nullable();
            $table->decimal('liters', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->decimal('total_sales', 14, 2);
            $table->decimal('discounts', 14, 2)->default(0);
            $table->decimal('net_sales', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['original_shift_reading_id', 'entry_type', 'pump_number'],
                'ltm_corr_original_type_pump_unique'
            );
            $table->unique(
                ['replacement_shift_reading_id', 'entry_type', 'pump_number'],
                'ltm_corr_replacement_type_pump_unique'
            );
            $table->index(
                ['liters_tracking_period_id', 'status'],
                'ltm_corr_period_status_idx'
            );
            $table->index(
                ['generated_period_id', 'status'],
                'ltm_corr_generated_status_idx'
            );
        });

        $this->backfillExistingGeneratedReadingCorrections();
    }

    public function down(): void
    {
        Schema::dropIfExists('liters_movement_corrections');
    }

    private function backfillExistingGeneratedReadingCorrections(): void
    {
        if (
            ! Schema::hasColumn('shift_readings', 'liters_tracking_period_id')
            || ! Schema::hasColumn('shift_readings', 'status')
        ) {
            return;
        }

        $activePeriod = DB::table('liters_tracking_periods')
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        if (! $activePeriod) {
            return;
        }

        $voidedReadings = DB::table('shift_readings')
            ->join('liters_tracking_periods', 'shift_readings.liters_tracking_period_id', '=', 'liters_tracking_periods.id')
            ->whereRaw('LOWER(liters_tracking_periods.status) = ?', ['generated'])
            ->whereRaw('LOWER(TRIM(shift_readings.status)) = ?', ['voided'])
            ->select('shift_readings.*', 'liters_tracking_periods.totals_snapshot', 'liters_tracking_periods.id as generated_period_id')
            ->orderBy('shift_readings.id')
            ->get();

        foreach ($voidedReadings as $original) {
            $breakdown = $this->generatedBreakdown($original);

            if ($breakdown->isEmpty()) {
                throw new RuntimeException(
                    "Cannot backfill Liters Movement correction for Shift Reading #{$original->id}: its generated snapshot has no per-pump entries."
                );
            }

            $replacement = $this->replacementFor($original, (int) $activePeriod->id);
            $now = now();
            $reversalStatus = $replacement ? 'matched' : 'pending';

            foreach ($breakdown as $entry) {
                DB::table('liters_movement_corrections')->insert([
                    'liters_tracking_period_id' => (int) $activePeriod->id,
                    'generated_period_id' => (int) $original->generated_period_id,
                    'original_shift_reading_id' => (int) $original->id,
                    'replacement_shift_reading_id' => null,
                    'entry_type' => 'reversal',
                    'status' => $reversalStatus,
                    'pump_number' => (int) $entry['pump_number'],
                    'fuel_type' => $entry['fuel_type'],
                    'liters' => -abs((float) $entry['liters']),
                    'amount' => -abs((float) $entry['amount']),
                    'total_sales' => -abs((float) $entry['total_sales']),
                    'discounts' => -abs((float) $entry['discounts']),
                    'net_sales' => -abs((float) $entry['net_sales']),
                    'created_by' => $original->voided_by ?? $original->user_id ?? null,
                    'created_at' => $original->voided_at ?? $now,
                    'updated_at' => $now,
                ]);
            }

            if (! $replacement) {
                continue;
            }

            foreach ($this->replacementBreakdown($replacement) as $entry) {
                DB::table('liters_movement_corrections')->insert([
                    'liters_tracking_period_id' => (int) $activePeriod->id,
                    'generated_period_id' => (int) $original->generated_period_id,
                    'original_shift_reading_id' => (int) $original->id,
                    'replacement_shift_reading_id' => (int) $replacement->id,
                    'entry_type' => 'replacement',
                    'status' => 'applied',
                    'pump_number' => (int) $entry['pump_number'],
                    'fuel_type' => $entry['fuel_type'],
                    'liters' => abs((float) $entry['liters']),
                    'amount' => abs((float) $entry['amount']),
                    'total_sales' => abs((float) $entry['total_sales']),
                    'discounts' => abs((float) $entry['discounts']),
                    'net_sales' => abs((float) $entry['net_sales']),
                    'created_by' => $replacement->user_id ?? null,
                    'created_at' => $replacement->created_at ?? $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function generatedBreakdown(object $original): Collection
    {
        $snapshot = json_decode((string) ($original->totals_snapshot ?? ''), true);

        if (! is_array($snapshot)) {
            return collect();
        }

        return collect($snapshot['tank_breakdown'] ?? [])
            ->flatMap(function ($tank) use ($original) {
                $fuelType = (string) ($tank['fuel_type'] ?? '');

                return collect($tank['entries'] ?? [])
                    ->filter(fn ($entry) => (int) ($entry['id'] ?? 0) === (int) $original->id)
                    ->map(function ($entry) use ($fuelType) {
                        $totalSales = (float) ($entry['total_sales'] ?? $entry['amount'] ?? 0);
                        $discounts = (float) ($entry['discounts'] ?? 0);

                        return [
                            'pump_number' => (int) ($entry['pump_id'] ?? 0),
                            'fuel_type' => $fuelType,
                            'liters' => round((float) ($entry['liters'] ?? 0), 2),
                            'amount' => round((float) ($entry['amount'] ?? $totalSales), 2),
                            'total_sales' => round($totalSales, 2),
                            'discounts' => round($discounts, 2),
                            'net_sales' => round((float) ($entry['net_sales'] ?? ($totalSales - $discounts)), 2),
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
                    'liters' => round($entries->sum('liters'), 2),
                    'amount' => round($entries->sum('amount'), 2),
                    'total_sales' => round($entries->sum('total_sales'), 2),
                    'discounts' => round($entries->sum('discounts'), 2),
                    'net_sales' => round($entries->sum('net_sales'), 2),
                ];
            })
            ->values();
    }

    private function replacementFor(object $original, int $activePeriodId): ?object
    {
        $query = DB::table('shift_readings')
            ->where('id', '<>', (int) $original->id)
            ->where('user_id', (int) $original->user_id)
            ->where('liters_tracking_period_id', $activePeriodId)
            ->whereIn(DB::raw('LOWER(TRIM(status))'), self::INCLUDED_READING_STATUSES);

        if (Schema::hasColumn('shift_readings', 'period_from')) {
            $query->whereDate('period_from', $original->period_from);
        }

        if (Schema::hasColumn('shift_readings', 'period_to')) {
            $query->whereDate('period_to', $original->period_to ?: $original->period_from);
        }

        if (Schema::hasColumn('shift_readings', 'created_at') && ! empty($original->voided_at)) {
            $query->where('created_at', '>=', $original->voided_at);
        }

        $replacements = $query->orderBy('id')->limit(2)->get();

        if ($replacements->count() > 1) {
            throw new RuntimeException(
                "Cannot safely choose a replacement for generated Shift Reading #{$original->id}; multiple active submissions match its staff and period."
            );
        }

        return $replacements->first();
    }

    private function replacementBreakdown(object $replacement): Collection
    {
        $discounts = $this->discountsForReading((int) $replacement->id);
        $fuelTypes = [1 => 'Premium', 2 => 'Unleaded', 3 => 'Premium', 4 => 'Unleaded', 5 => 'Diesel', 6 => 'Diesel'];

        return collect($fuelTypes)
            ->map(function (string $fuelType, int $pump) use ($replacement, $discounts) {
                $liters = round(max(
                    0,
                    (float) ($replacement->{"pump{$pump}_liters_end"} ?? 0)
                        - (float) ($replacement->{"pump{$pump}_liters_start"} ?? 0)
                ), 2);
                $amount = round(max(
                    0,
                    (float) ($replacement->{"pump{$pump}_sales_end"} ?? 0)
                        - (float) ($replacement->{"pump{$pump}_sales_start"} ?? 0)
                ), 2);
                $pumpDiscounts = round((float) ($discounts[$pump] ?? 0), 2);

                return [
                    'pump_number' => $pump,
                    'fuel_type' => $fuelType,
                    'liters' => $liters,
                    'amount' => $amount,
                    'total_sales' => $amount,
                    'discounts' => $pumpDiscounts,
                    'net_sales' => round($amount - $pumpDiscounts, 2),
                ];
            })
            ->filter(fn ($entry) => $entry['liters'] > 0 || $entry['amount'] > 0)
            ->values();
    }

    private function discountsForReading(int $readingId): array
    {
        if (
            ! Schema::hasTable('sales')
            || ! Schema::hasColumn('sales', 'locked_by_shift_reading_id')
            || ! Schema::hasColumn('sales', 'pump_id')
        ) {
            return [];
        }

        $discount = Schema::hasColumn('sales', 'discount') ? 'COALESCE(discount, 0)' : '0';
        $manualDiscount = Schema::hasColumn('sales', 'manual_discount') ? 'COALESCE(manual_discount, 0)' : '0';

        return DB::table('sales')
            ->where('locked_by_shift_reading_id', $readingId)
            ->where('pump_id', '>', 0)
            ->selectRaw("pump_id, SUM({$discount} + {$manualDiscount}) as discounts")
            ->groupBy('pump_id')
            ->pluck('discounts', 'pump_id')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }
};
