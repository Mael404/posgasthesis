<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShiftReadingInventoryService
{
    private const PUMP_FUEL_TYPES = [
        1 => 'Premium',
        2 => 'Unleaded',
        3 => 'Premium',
        4 => 'Unleaded',
        5 => 'Diesel',
        6 => 'Diesel',
    ];

    public function apply(object $reading): float
    {
        $this->assertTrackingColumnsExist();

        $reading = $this->freshReading($reading);

        if (! $reading || $this->isApplied($reading)) {
            return 0;
        }

        $deductions = $this->deductionsForReading($reading);

        foreach ($deductions as $deduction) {
            DB::table('tanks')
                ->where('id', $deduction['tank_id'])
                ->decrement('current_volume', $deduction['liters']);
        }

        $updates = [
            'inventory_applied_at' => now(),
            'inventory_reversed_at' => null,
            'inventory_deductions' => json_encode($deductions),
        ];

        if (Schema::hasColumn('shift_readings', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('shift_readings')->where('id', $reading->id)->update($updates);

        return $this->totalLiters($deductions);
    }

    public function restore(object $reading): float
    {
        $this->assertTrackingColumnsExist();

        $reading = $this->freshReading($reading);

        if (! $reading || ! $this->isApplied($reading)) {
            return 0;
        }

        $deductions = $this->storedDeductions($reading);

        if ($deductions === []) {
            $deductions = $this->deductionsForReading($reading);
        }

        foreach ($deductions as $deduction) {
            DB::table('tanks')
                ->where('id', $deduction['tank_id'])
                ->increment('current_volume', $deduction['liters']);
        }

        $updates = [
            'inventory_reversed_at' => now(),
        ];

        if (Schema::hasColumn('shift_readings', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('shift_readings')->where('id', $reading->id)->update($updates);

        return $this->totalLiters($deductions);
    }

    private function freshReading(object $reading): ?object
    {
        if (
            ! isset($reading->id)
            || ! Schema::hasTable('shift_readings')
            || ! Schema::hasTable('tanks')
            || ! Schema::hasColumn('tanks', 'current_volume')
        ) {
            return null;
        }

        return DB::table('shift_readings')
            ->where('id', (int) $reading->id)
            ->lockForUpdate()
            ->first();
    }

    private function isApplied(object $reading): bool
    {
        return ! empty($reading->inventory_applied_at)
            && empty($reading->inventory_reversed_at);
    }

    private function deductionsForReading(object $reading): array
    {
        $deductions = [];

        foreach (self::PUMP_FUEL_TYPES as $pumpId => $fuelType) {
            $start = $this->number($reading->{"pump{$pumpId}_liters_start"} ?? 0);
            $end = $this->number($reading->{"pump{$pumpId}_liters_end"} ?? $start);
            $liters = round(max(0, $end - $start), 2);

            if ($liters <= 0) {
                continue;
            }

            $tank = $this->tankForPump($pumpId, $fuelType);

            if (! $tank) {
                continue;
            }

            $tankId = (int) $tank->id;

            if (! isset($deductions[$tankId])) {
                $deductions[$tankId] = [
                    'tank_id' => $tankId,
                    'fuel_type' => (string) ($tank->fuel_type ?? $fuelType),
                    'pump_ids' => [],
                    'liters' => 0,
                ];
            }

            $deductions[$tankId]['pump_ids'][] = $pumpId;
            $deductions[$tankId]['liters'] = round($deductions[$tankId]['liters'] + $liters, 2);
        }

        return array_values($deductions);
    }

    private function tankForPump(int $pumpId, string $fuelType): ?object
    {
        $query = DB::table('tanks');

        if (Schema::hasColumn('tanks', 'fuel_type')) {
            $query->whereRaw('LOWER(fuel_type) = ?', [strtolower($fuelType)]);
        }

        if (Schema::hasColumn('tanks', 'status')) {
            $query->where(function ($query) {
                $query->whereRaw('LOWER(status) = ?', ['active'])
                    ->orWhereNull('status')
                    ->orWhere('status', '');
            });
        }

        $tanks = $query
            ->when(Schema::hasColumn('tanks', 'id'), fn ($query) => $query->orderBy('id'))
            ->lockForUpdate()
            ->get();

        if (Schema::hasColumn('tanks', 'connected_pumps')) {
            $connectedTank = $tanks->first(function ($tank) use ($pumpId) {
                preg_match_all('/\d+/', (string) ($tank->connected_pumps ?? ''), $matches);

                return in_array($pumpId, array_map('intval', $matches[0] ?? []), true);
            });

            if ($connectedTank) {
                return $connectedTank;
            }
        }

        return $tanks->first();
    }

    private function storedDeductions(object $reading): array
    {
        $decoded = json_decode((string) ($reading->inventory_deductions ?? ''), true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($row) => is_array($row) && (int) ($row['tank_id'] ?? 0) > 0 && (float) ($row['liters'] ?? 0) > 0)
            ->map(function ($row) {
                return [
                    'tank_id' => (int) $row['tank_id'],
                    'fuel_type' => (string) ($row['fuel_type'] ?? ''),
                    'pump_ids' => array_values(array_map('intval', (array) ($row['pump_ids'] ?? []))),
                    'liters' => round((float) $row['liters'], 2),
                ];
            })
            ->values()
            ->all();
    }

    private function totalLiters(array $deductions): float
    {
        return round(array_sum(array_column($deductions, 'liters')), 2);
    }

    private function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }

    private function assertTrackingColumnsExist(): void
    {
        foreach (['inventory_applied_at', 'inventory_reversed_at', 'inventory_deductions'] as $column) {
            if (! Schema::hasColumn('shift_readings', $column)) {
                throw new \RuntimeException('Shift-reading inventory tracking is not migrated yet. Run php artisan migrate.');
            }
        }
    }
}
