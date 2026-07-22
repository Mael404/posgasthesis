<?php

namespace App\Services;

use App\Models\InventoryLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class FuelInventoryReportService
{
    public const PRODUCTS = [
        'Premium' => [
            'product_name' => 'Premium',
            'pumps' => [1, 3],
        ],
        'Unleaded' => [
            'product_name' => 'Regular',
            'pumps' => [2, 4],
        ],
        'Diesel' => [
            'product_name' => 'Diesel',
            'pumps' => [5, 6],
        ],
    ];

    public function calculate(string|Carbon $periodStart, string|Carbon $periodEnd, array $openingBalances): array
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        if ($end->lt($start)) {
            throw new RuntimeException('The report end date must be on or after its start date.');
        }

        $deliveries = $this->verifiedDeliveries($start, $end);
        $readings = $this->verifiedShiftReadings($start, $end);
        $adjustmentsByReading = $this->activeAdjustmentsByReading($readings->pluck('id')->map(fn ($id) => (int) $id)->all());
        $prices = $this->pricesByFuel();
        $events = collect(array_keys(self::PRODUCTS))->mapWithKeys(fn (string $fuel) => [$fuel => []])->all();
        $deliveryRows = [];
        $readingRows = [];

        foreach ($deliveries as $delivery) {
            $fuel = $this->canonicalFuel($delivery->fuel_type ?? null);
            $liters = max(0, (float) ($delivery->liters_delivered ?? 0));
            $totalCost = max(0, (float) ($delivery->total_cost ?? 0));

            if ($fuel === null || $liters <= 0) {
                continue;
            }

            $occurredAt = $this->deliveryOccurredAt($delivery);
            $row = [
                'id' => (int) $delivery->id,
                'delivery_code' => (string) ($delivery->delivery_code ?? ''),
                'delivery_date' => Carbon::parse($delivery->delivery_date)->toDateString(),
                'occurred_at' => $occurredAt->toDateTimeString(),
                'supplier' => (string) ($delivery->supplier_name ?? 'N/A'),
                'fuel_type' => $fuel,
                'product_name' => self::PRODUCTS[$fuel]['product_name'],
                'liters' => $liters,
                'unit_cost' => $this->divide($totalCost, $liters),
                'total_cost' => $totalCost,
            ];

            $deliveryRows[] = $row;
            $events[$fuel][] = [
                'type' => 'delivery',
                'occurred_at' => $row['occurred_at'],
                'liters' => $liters,
                'amount' => $totalCost,
                'source_id' => (int) $delivery->id,
            ];
        }

        foreach ($readings as $reading) {
            $occurredAt = Carbon::parse($reading->period_to ?? $reading->created_at ?? $end)->endOfDay();

            foreach (self::PRODUCTS as $fuel => $definition) {
                $liters = 0.0;
                $revenue = 0.0;
                $adjustmentIds = [];

                foreach ($definition['pumps'] as $pump) {
                    $adjustments = collect($adjustmentsByReading[(int) $reading->id][$pump] ?? []);
                    $adjustedLiters = (float) $adjustments->sum('liters');
                    $adjustedAmount = (float) $adjustments->sum('amount');
                    $adjustmentIds = array_merge($adjustmentIds, $adjustments->pluck('id')->all());
                    $liters += max(0, (float) ($reading->{"pump{$pump}_liters_end"} ?? 0)
                        - (float) ($reading->{"pump{$pump}_liters_start"} ?? 0)
                        - $adjustedLiters);
                    $revenue += max(0, (float) ($reading->{"pump{$pump}_sales_end"} ?? 0)
                        - (float) ($reading->{"pump{$pump}_sales_start"} ?? 0)
                        - $adjustedAmount);
                }

                if ($liters <= 0 && $revenue <= 0) {
                    continue;
                }

                $row = [
                    'id' => (int) $reading->id,
                    'period_from' => Carbon::parse($reading->period_from ?? $occurredAt)->toDateString(),
                    'period_to' => Carbon::parse($reading->period_to ?? $occurredAt)->toDateString(),
                    'occurred_at' => $occurredAt->toDateTimeString(),
                    'fuel_type' => $fuel,
                    'product_name' => $definition['product_name'],
                    'liters' => $liters,
                    'revenue' => $revenue,
                    'adjustment_ids' => array_values(array_unique($adjustmentIds)),
                ];

                $readingRows[] = $row;
                $events[$fuel][] = [
                    'type' => 'sale',
                    'occurred_at' => $row['occurred_at'],
                    'liters' => $liters,
                    'amount' => $revenue,
                    'source_id' => (int) $reading->id,
                ];
            }
        }

        $lines = [];
        $warnings = [];

        foreach (self::PRODUCTS as $fuel => $definition) {
            $opening = $this->openingBalance($openingBalances, $fuel);
            $openingLiters = max(0, (float) ($opening['liters'] ?? 0));
            $openingUnitCost = max(0, (float) ($opening['unit_cost'] ?? 0));
            $openingValue = array_key_exists('value', $opening)
                ? max(0, (float) $opening['value'])
                : $openingLiters * $openingUnitCost;

            if ($openingLiters > 0 && $openingUnitCost <= 0 && $openingValue <= 0) {
                $warnings[] = "{$definition['product_name']} has opening liters but no opening cost.";
            }

            $fuelDeliveries = collect($deliveryRows)->where('fuel_type', $fuel)->values();
            $fuelReadings = collect($readingRows)->where('fuel_type', $fuel)->values();
            $purchasedLiters = (float) $fuelDeliveries->sum('liters');
            $purchaseValue = (float) $fuelDeliveries->sum('total_cost');
            $availableLiters = $openingLiters + $purchasedLiters;
            $availableValue = $openingValue + $purchaseValue;
            $periodicAverageCost = $this->divide($availableValue, $availableLiters);
            $balanceLiters = $openingLiters;
            $balanceValue = $openingValue;
            $dispensedLiters = 0.0;
            $salesRevenue = 0.0;
            $costOfGoodsSold = 0.0;
            $eventAudit = [];

            usort($events[$fuel], static function (array $left, array $right): int {
                $dateComparison = strcmp($left['occurred_at'], $right['occurred_at']);

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return ($left['type'] === 'delivery' ? 0 : 1) <=> ($right['type'] === 'delivery' ? 0 : 1);
            });

            foreach ($events[$fuel] as $event) {
                $averageBefore = $this->divide($balanceValue, $balanceLiters);

                if ($event['type'] === 'delivery') {
                    $balanceLiters += $event['liters'];
                    $balanceValue += $event['amount'];
                    $eventCost = $event['amount'];
                } else {
                    $eventCost = $event['liters'] * $averageBefore;
                    $dispensedLiters += $event['liters'];
                    $salesRevenue += $event['amount'];
                    $costOfGoodsSold += $eventCost;
                    $balanceLiters -= $event['liters'];
                    $balanceValue -= $eventCost;
                }

                $eventAudit[] = [
                    'type' => $event['type'],
                    'source_id' => $event['source_id'],
                    'occurred_at' => $event['occurred_at'],
                    'liters' => $this->rounded($event['liters']),
                    'amount' => $this->rounded($event['amount']),
                    'average_cost_before' => $this->rounded($averageBefore),
                    'cost_applied' => $this->rounded($eventCost),
                    'balance_liters_after' => $this->rounded($balanceLiters),
                    'average_cost_after' => $this->rounded($this->divide($balanceValue, $balanceLiters)),
                ];
            }

            if ($balanceLiters < -0.0005) {
                $warnings[] = "{$definition['product_name']} ends with negative calculated inventory; opening stock or source movements need reconciliation.";
            }

            $endingUnitCost = $this->divide($balanceValue, $balanceLiters);
            $averageSellingPrice = $this->divide($salesRevenue, $dispensedLiters);
            $costPerSoldLiter = $this->divide($costOfGoodsSold, $dispensedLiters);
            $pumpPrice = (float) ($prices[$fuel]['pump_price'] ?? 0);
            $bulkPrice = (float) ($prices[$fuel]['bulk_price'] ?? 0);

            if ($bulkPrice <= 0) {
                $warnings[] = "{$definition['product_name']} does not yet have a bulk selling price.";
            }

            $lines[] = [
                'fuel_type' => $fuel,
                'product_name' => $definition['product_name'],
                'opening_liters' => $this->rounded($openingLiters),
                'opening_unit_cost' => $this->rounded($openingUnitCost),
                'opening_value' => $this->rounded($openingValue),
                'purchased_liters' => $this->rounded($purchasedLiters),
                'purchase_value' => $this->rounded($purchaseValue),
                'available_liters' => $this->rounded($availableLiters),
                'available_value' => $this->rounded($availableValue),
                'periodic_average_cost' => $this->rounded($periodicAverageCost),
                'dispensed_liters' => $this->rounded($dispensedLiters),
                'sales_revenue' => $this->rounded($salesRevenue),
                'cost_of_goods_sold' => $this->rounded($costOfGoodsSold),
                'ending_liters' => $this->rounded($balanceLiters),
                'ending_unit_cost' => $this->rounded($endingUnitCost),
                'ending_value' => $this->rounded($balanceValue),
                'pump_price' => $this->rounded($pumpPrice),
                'bulk_price' => $this->rounded($bulkPrice),
                'pump_margin' => $this->rounded($pumpPrice - $endingUnitCost),
                'bulk_margin' => $this->rounded($bulkPrice - $endingUnitCost),
                'average_selling_price' => $this->rounded($averageSellingPrice),
                'gross_margin_per_liter' => $this->rounded($averageSellingPrice - $costPerSoldLiter),
                'gross_profit' => $this->rounded($salesRevenue - $costOfGoodsSold),
                'source_summary' => [
                    'delivery_ids' => $fuelDeliveries->pluck('id')->all(),
                    'shift_reading_ids' => $fuelReadings->pluck('id')->unique()->values()->all(),
                    'deliveries' => $fuelDeliveries->all(),
                    'shift_readings' => $fuelReadings->all(),
                    'events' => $eventAudit,
                ],
            ];
        }

        return [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'calculation_method' => 'moving_weighted_average',
            'lines' => $lines,
            'deliveries' => collect($deliveryRows)
                ->sortBy(fn (array $row): string => $row['occurred_at'] . '-' . str_pad((string) $row['id'], 12, '0', STR_PAD_LEFT))
                ->values()
                ->all(),
            'shift_readings' => collect($readingRows)
                ->sortBy(fn (array $row): string => $row['occurred_at'] . '-' . str_pad((string) $row['id'], 12, '0', STR_PAD_LEFT))
                ->values()
                ->all(),
            'totals' => $this->totals($lines),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function latestFinalizedReport(bool $lockForUpdate = false): ?object
    {
        if (! Schema::hasTable('fuel_inventory_reports')) {
            return null;
        }

        $query = DB::table('fuel_inventory_reports')
            ->whereRaw('LOWER(status) = ?', ['finalized'])
            ->orderByDesc('period_end')
            ->orderByDesc('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function openingFromReport(int|object $report): array
    {
        $reportId = is_object($report) ? (int) $report->id : $report;

        if (! Schema::hasTable('fuel_inventory_report_lines')) {
            return [];
        }

        return DB::table('fuel_inventory_report_lines')
            ->where('fuel_inventory_report_id', $reportId)
            ->get()
            ->mapWithKeys(fn ($line) => [(string) $line->fuel_type => [
                'liters' => (float) $line->ending_liters,
                'unit_cost' => (float) $line->ending_unit_cost,
                'value' => (float) $line->ending_value,
            ]])
            ->all();
    }

    public function reportHistory(int $limit = 12)
    {
        if (! Schema::hasTable('fuel_inventory_reports')) {
            return collect();
        }

        return DB::table('fuel_inventory_reports')
            ->whereRaw('LOWER(status) = ?', ['finalized'])
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function storedReport(int $reportId): ?array
    {
        if (! Schema::hasTable('fuel_inventory_reports') || ! Schema::hasTable('fuel_inventory_report_lines')) {
            return null;
        }

        $report = DB::table('fuel_inventory_reports')->where('id', $reportId)->first();
        if (! $report) {
            return null;
        }

        $lines = DB::table('fuel_inventory_report_lines')
            ->where('fuel_inventory_report_id', $reportId)
            ->orderBy('id')
            ->get()
            ->map(function ($line): array {
                $values = (array) $line;
                $values['source_summary'] = json_decode((string) ($values['source_summary'] ?? ''), true) ?: [];

                return $values;
            })
            ->all();
        $deliveries = collect($lines)
            ->flatMap(fn (array $line): array => $line['source_summary']['deliveries'] ?? [])
            ->unique(fn (array $row): string => (string) ($row['id'] ?? '') . '-' . (string) ($row['fuel_type'] ?? ''))
            ->sortBy('occurred_at')
            ->values()
            ->all();
        $readings = collect($lines)
            ->flatMap(fn (array $line): array => $line['source_summary']['shift_readings'] ?? [])
            ->unique(fn (array $row): string => (string) ($row['id'] ?? '') . '-' . (string) ($row['fuel_type'] ?? ''))
            ->sortBy('occurred_at')
            ->values()
            ->all();
        $warnings = collect($lines)->flatMap(function (array $line): array {
            $lineWarnings = [];

            if ((float) ($line['ending_liters'] ?? 0) < -0.0005) {
                $lineWarnings[] = "{$line['product_name']} ends with negative calculated inventory; opening stock or source movements need reconciliation.";
            }

            if ((float) ($line['bulk_price'] ?? 0) <= 0) {
                $lineWarnings[] = "{$line['product_name']} did not have a bulk selling price when this report was finalized.";
            }

            return $lineWarnings;
        })->values()->all();

        return [
            'id' => (int) $report->id,
            'report_code' => (string) $report->report_code,
            'status' => (string) $report->status,
            'period_start' => Carbon::parse($report->period_start)->toDateString(),
            'period_end' => Carbon::parse($report->period_end)->toDateString(),
            'calculation_method' => (string) $report->calculation_method,
            'lines' => $lines,
            'deliveries' => $deliveries,
            'shift_readings' => $readings,
            'totals' => $this->totals($lines),
            'warnings' => $warnings,
        ];
    }

    public function finalize(string $periodStart, string $periodEnd, array $manualOpening, ?int $createdBy): array
    {
        return DB::transaction(function () use ($periodStart, $periodEnd, $manualOpening, $createdBy): array {
            $latest = $this->latestFinalizedReport(true);
            $start = Carbon::parse($periodStart)->startOfDay();
            $end = Carbon::parse($periodEnd)->endOfDay();

            if ($end->lt($start)) {
                throw new RuntimeException('The report end date must be on or after its start date.');
            }

            if ($latest) {
                $expectedStart = Carbon::parse($latest->period_end)->addDay()->toDateString();
                if ($start->toDateString() !== $expectedStart) {
                    throw new RuntimeException("The next report must begin on {$expectedStart} so no inventory activity is skipped or duplicated.");
                }

                $opening = $this->openingFromReport($latest);
            } else {
                $opening = $manualOpening;
            }

            $calculation = $this->calculate($start, $end, $opening);
            $sequence = DB::table('fuel_inventory_reports')
                ->whereDate('period_end', $end->toDateString())
                ->count() + 1;
            $reportCode = 'INV-' . $end->format('Ymd') . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $now = now();
            $reportId = DB::table('fuel_inventory_reports')->insertGetId([
                'report_code' => $reportCode,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => 'Finalized',
                'calculation_method' => 'moving_weighted_average',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($calculation['lines'] as $line) {
                DB::table('fuel_inventory_report_lines')->insert(array_merge(
                    collect($line)->except('source_summary')->all(),
                    [
                        'fuel_inventory_report_id' => $reportId,
                        'source_summary' => json_encode($line['source_summary'], JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                ));
            }

            return [
                'id' => $reportId,
                'report_code' => $reportCode,
                'calculation' => $calculation,
            ];
        });
    }

    private function verifiedDeliveries(Carbon $start, Carbon $end)
    {
        if (! Schema::hasTable('inventory_logs')
            || ! Schema::hasTable('tanks')
            || ! Schema::hasTable('tank_delivery_records')) {
            return collect();
        }

        $query = DB::table('inventory_logs as logs')
            ->join('tanks', 'logs.tank_id', '=', 'tanks.id')
            ->leftJoin('tank_delivery_records as records', 'logs.id', '=', 'records.inventory_log_id');

        if (Schema::hasTable('suppliers')) {
            $query->leftJoin('suppliers', 'logs.supplier_id', '=', 'suppliers.id');
        }

        $select = [
            'logs.id',
            'logs.delivery_code',
            'logs.delivery_date',
            'logs.liters_delivered',
            'logs.total_cost',
            'tanks.fuel_type',
            'records.record_date',
            'records.record_time',
        ];
        $select[] = Schema::hasTable('suppliers')
            ? 'suppliers.name as supplier_name'
            : DB::raw("'N/A' as supplier_name");

        return $query
            ->whereDate('logs.delivery_date', '>=', $start->toDateString())
            ->whereDate('logs.delivery_date', '<=', $end->toDateString())
            ->whereRaw('LOWER(TRIM(logs.delivery_status)) = ?', [strtolower(InventoryLog::STATUS_TANK_DELIVERY_RECORD_VERIFIED)])
            ->select($select)
            ->orderBy('logs.delivery_date')
            ->orderBy('logs.id')
            ->get();
    }

    private function verifiedShiftReadings(Carbon $start, Carbon $end)
    {
        if (! Schema::hasTable('shift_readings')) {
            return collect();
        }

        return DB::table('shift_readings')
            ->whereDate('period_to', '>=', $start->toDateString())
            ->whereDate('period_to', '<=', $end->toDateString())
            ->whereIn(DB::raw('LOWER(TRIM(status))'), ['verified', 'approved'])
            ->orderBy('period_to')
            ->orderBy('id')
            ->get();
    }

    private function activeAdjustmentsByReading(array $readingIds): array
    {
        if ($readingIds === [] || ! Schema::hasTable('liters_movement_adjustments')) {
            return [];
        }

        return DB::table('liters_movement_adjustments')
            ->whereIn('shift_reading_id', $readingIds)
            ->whereNull('voided_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($adjustment): int => (int) $adjustment->shift_reading_id)
            ->map(fn ($adjustments) => $adjustments
                ->groupBy(fn ($adjustment): int => (int) $adjustment->pump_number)
                ->map(fn ($pumpAdjustments) => $pumpAdjustments->map(fn ($adjustment): array => [
                    'id' => (int) $adjustment->id,
                    'liters' => max(0, (float) ($adjustment->liters ?? 0)),
                    'amount' => max(0, (float) ($adjustment->amount ?? 0)),
                ])->values()->all())
                ->all())
            ->all();
    }

    private function pricesByFuel(): array
    {
        if (! Schema::hasTable('fuel_prices')) {
            return [];
        }

        $hasBulkPrice = Schema::hasColumn('fuel_prices', 'bulk_price_per_liter');

        return DB::table('fuel_prices')
            ->get()
            ->mapWithKeys(function ($price) use ($hasBulkPrice): array {
                $fuel = $this->canonicalFuel($price->fuel_type ?? null);

                return $fuel === null ? [] : [$fuel => [
                    'pump_price' => (float) ($price->price_per_liter ?? 0),
                    'bulk_price' => $hasBulkPrice ? (float) ($price->bulk_price_per_liter ?? 0) : 0.0,
                ]];
            })
            ->all();
    }

    private function deliveryOccurredAt(object $delivery): Carbon
    {
        $date = $delivery->record_date ?: $delivery->delivery_date;
        $time = $delivery->record_time ?: '00:00:00';

        return Carbon::parse(trim("{$date} {$time}"));
    }

    private function canonicalFuel(mixed $fuelType): ?string
    {
        $fuel = strtolower(trim((string) $fuelType));

        return match (true) {
            str_contains($fuel, 'premium') => 'Premium',
            str_contains($fuel, 'regular'), str_contains($fuel, 'unleaded') => 'Unleaded',
            str_contains($fuel, 'diesel') => 'Diesel',
            default => null,
        };
    }

    private function openingBalance(array $balances, string $fuel): array
    {
        foreach ($balances as $key => $balance) {
            if ($this->canonicalFuel($key) === $fuel) {
                return is_array($balance) ? $balance : [];
            }
        }

        return [];
    }

    private function totals(array $lines): array
    {
        $collection = collect($lines);

        return [
            'opening_value' => $this->rounded((float) $collection->sum('opening_value')),
            'purchase_value' => $this->rounded((float) $collection->sum('purchase_value')),
            'available_value' => $this->rounded((float) $collection->sum('available_value')),
            'ending_value' => $this->rounded((float) $collection->sum('ending_value')),
            'dispensed_liters' => $this->rounded((float) $collection->sum('dispensed_liters')),
            'sales_revenue' => $this->rounded((float) $collection->sum('sales_revenue')),
            'cost_of_goods_sold' => $this->rounded((float) $collection->sum('cost_of_goods_sold')),
            'gross_profit' => $this->rounded((float) $collection->sum('gross_profit')),
        ];
    }

    private function divide(float $numerator, float $denominator): float
    {
        return abs($denominator) < 0.0000001 ? 0.0 : $numerator / $denominator;
    }

    private function rounded(float $value): float
    {
        return round($value, 6);
    }
}
