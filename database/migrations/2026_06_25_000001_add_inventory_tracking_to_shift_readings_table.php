<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PUMP_FUEL_TYPES = [
        1 => 'Premium',
        2 => 'Unleaded',
        3 => 'Premium',
        4 => 'Unleaded',
        5 => 'Diesel',
        6 => 'Diesel',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('shift_readings')) {
            return;
        }

        Schema::table('shift_readings', function (Blueprint $table) {
            if (! Schema::hasColumn('shift_readings', 'inventory_applied_at')) {
                $table->timestamp('inventory_applied_at')->nullable();
            }

            if (! Schema::hasColumn('shift_readings', 'inventory_reversed_at')) {
                $table->timestamp('inventory_reversed_at')->nullable();
            }

            if (! Schema::hasColumn('shift_readings', 'inventory_deductions')) {
                $table->text('inventory_deductions')->nullable();
            }
        });

        $this->backfillActiveReadings();
    }

    public function down(): void
    {
        if (! Schema::hasTable('shift_readings')) {
            return;
        }

        $columns = collect([
            'inventory_applied_at',
            'inventory_reversed_at',
            'inventory_deductions',
        ])->filter(fn ($column) => Schema::hasColumn('shift_readings', $column))->all();

        if ($columns !== []) {
            Schema::table('shift_readings', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function backfillActiveReadings(): void
    {
        if (
            ! Schema::hasTable('tanks')
            || ! Schema::hasColumn('tanks', 'id')
            || ! Schema::hasColumn('tanks', 'current_volume')
            || ! Schema::hasColumn('shift_readings', 'status')
        ) {
            return;
        }

        DB::transaction(function () {
            $readings = DB::table('shift_readings')
                ->whereIn(DB::raw('LOWER(status)'), ['pending', 'verified', 'approved'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($readings as $reading) {
                $deductions = $this->deductionsForReading($reading);
                $status = strtolower((string) ($reading->status ?? ''));
                $appliedAt = $reading->created_at ?? now();

                if ($status === 'pending') {
                    foreach ($deductions as $deduction) {
                        DB::table('tanks')
                            ->where('id', $deduction['tank_id'])
                            ->decrement('current_volume', $deduction['liters']);
                    }

                    $appliedAt = now();
                }

                DB::table('shift_readings')->where('id', $reading->id)->update([
                    'inventory_applied_at' => $appliedAt,
                    'inventory_reversed_at' => null,
                    'inventory_deductions' => json_encode($deductions),
                ]);
            }
        });
    }

    private function deductionsForReading(object $reading): array
    {
        $deductions = [];

        foreach (self::PUMP_FUEL_TYPES as $pumpId => $fuelType) {
            $start = is_numeric($reading->{"pump{$pumpId}_liters_start"} ?? null)
                ? (float) $reading->{"pump{$pumpId}_liters_start"}
                : 0;
            $end = is_numeric($reading->{"pump{$pumpId}_liters_end"} ?? null)
                ? (float) $reading->{"pump{$pumpId}_liters_end"}
                : $start;
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

        $tanks = $query->orderBy('id')->lockForUpdate()->get();

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
};
