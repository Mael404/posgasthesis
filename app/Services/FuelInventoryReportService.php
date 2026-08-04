<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class FuelInventoryReportService
{
    private const INCLUDED_READING_STATUSES = ['pending', 'verified', 'approved'];

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

    public function __construct(
        private readonly LitersMovementTrackingService $litersMovement
    ) {}

    public function trackingPeriods(int $limit = 24): Collection
    {
        if (! Schema::hasTable('liters_tracking_periods')) {
            return collect();
        }

        $periods = DB::table('liters_tracking_periods')
            ->whereIn(DB::raw('LOWER(TRIM(status))'), ['active', 'generated'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $periods
            ->filter(fn (object $period): bool => strtolower(trim((string) $period->status)) === 'active')
            ->concat($periods->filter(
                fn (object $period): bool => strtolower(trim((string) $period->status)) !== 'active'
            ))
            ->values();
    }

    public function defaultTrackingPeriod(): ?object
    {
        return $this->trackingPeriods()->first();
    }

    public function trackingPeriod(int $periodId): ?object
    {
        if ($periodId <= 0 || ! Schema::hasTable('liters_tracking_periods')) {
            return null;
        }

        return DB::table('liters_tracking_periods')
            ->where('id', $periodId)
            ->whereIn(DB::raw('LOWER(TRIM(status))'), ['active', 'generated'])
            ->first();
    }

    public function initialOpeningFromTrackingPeriod(int|object $period): array
    {
        if (! is_object($period)) {
            $period = $this->trackingPeriod($period);
        }

        if (! $period) {
            return [];
        }

        $storedOpening = $this->storedInitialOpening($period);

        if ($storedOpening !== []) {
            return $storedOpening;
        }

        [$start, $end] = $this->reportWindow(
            $period,
            $period->started_at,
            $period->ended_at ?? now()
        );
        $definitions = $this->productDefinitions();
        $limits = $this->decodedTankLimits($period);
        $previousCurrentStockCosts = $this->previousGeneratedCurrentStockCosts($period);
        $deliveredByFuel = $this->deliveryLogs($start, $end)
            ->groupBy(fn (object $delivery): string => $this->canonicalFuel($delivery->fuel_type ?? null) ?? '')
            ->map(fn (Collection $deliveries): float => (float) $deliveries->sum('liters_delivered'));

        return collect($definitions)
            ->map(function (array $definition, string $fuel) use ($limits, $deliveredByFuel, $previousCurrentStockCosts): array {
                [$currentLimit, $hasCurrentLimit] = $this->tankLimitForDefinition(
                    $definition,
                    $limits,
                    0.0
                );
                $deliveredLiters = max(0, (float) $deliveredByFuel->get($fuel, 0));

                return [
                    'liters' => $hasCurrentLimit
                        ? $this->rounded(max(0, $currentLimit - $deliveredLiters))
                        : 0.0,
                    'unit_cost' => $this->rounded(max(0, (float) ($previousCurrentStockCosts[$fuel] ?? 0))),
                ];
            })
            ->all();
    }

    private function previousGeneratedCurrentStockCosts(object $period): array
    {
        if (! Schema::hasTable('liters_tracking_periods') || empty($period->started_at)) {
            return [];
        }

        $previousPeriod = DB::table('liters_tracking_periods')
            ->whereRaw('LOWER(TRIM(status)) = ?', ['generated'])
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', Carbon::parse($period->started_at))
            ->where('id', '!=', (int) $period->id)
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->first();

        if (! $previousPeriod) {
            return [];
        }

        $opening = $this->storedInitialOpening($previousPeriod);

        if ($opening === []) {
            $opening = $this->initialOpeningFromTrackingPeriod($previousPeriod);
        }

        $calculation = $this->calculate(
            $previousPeriod->started_at,
            $previousPeriod->ended_at,
            $opening,
            (int) $previousPeriod->id
        );

        return collect($calculation['lines'] ?? [])
            ->mapWithKeys(fn (array $line): array => [
                (string) $line['fuel_type'] => max(0, (float) ($line['periodic_average_cost'] ?? 0)),
            ])
            ->all();
    }

    public function saveInitialOpening(int $trackingPeriodId, array $openingCosts): array
    {
        if (! Schema::hasColumn('liters_tracking_periods', 'opening_inventory')) {
            throw new RuntimeException('Run the pending inventory migration before saving initial stock.');
        }

        return DB::transaction(function () use ($trackingPeriodId, $openingCosts): array {
            $period = DB::table('liters_tracking_periods')
                ->where('id', $trackingPeriodId)
                ->whereIn(DB::raw('LOWER(TRIM(status))'), ['active', 'generated'])
                ->lockForUpdate()
                ->first();

            if (! $period) {
                throw new RuntimeException('The selected Liters Movement period was not found.');
            }

            if ($this->latestFinalizedReport(true)) {
                throw new RuntimeException('Initial stock can only be entered before the first inventory report is finalized.');
            }

            $opening = $this->initialOpeningFromTrackingPeriod($period);

            foreach (self::PRODUCTS as $fuel => $definition) {
                $submitted = $this->openingBalance($openingCosts, $fuel);
                $unitCost = max(0, (float) ($submitted['unit_cost'] ?? 0));
                $liters = max(0, (float) ($opening[$fuel]['liters'] ?? 0));

                if ($liters > 0 && $unitCost <= 0) {
                    throw new RuntimeException("Enter the opening price per liter for {$definition['product_name']}.");
                }

                $opening[$fuel] = [
                    'liters' => $this->rounded($liters),
                    'unit_cost' => $this->rounded($unitCost),
                    'value' => $this->rounded($liters * $unitCost),
                ];
            }

            $values = ['opening_inventory' => json_encode($opening, JSON_UNESCAPED_SLASHES)];

            if (Schema::hasColumn('liters_tracking_periods', 'updated_at')) {
                $values['updated_at'] = now();
            }

            DB::table('liters_tracking_periods')->where('id', $trackingPeriodId)->update($values);

            return $opening;
        });
    }

    public function calculate(
        string|Carbon $periodStart,
        string|Carbon $periodEnd,
        array $openingBalances,
        ?int $trackingPeriodId = null
    ): array {
        $period = $trackingPeriodId ? $this->trackingPeriod($trackingPeriodId) : null;

        if ($trackingPeriodId && ! $period) {
            throw new RuntimeException('The selected Liters Movement period is no longer available.');
        }

        [$start, $end] = $this->reportWindow($period, $periodStart, $periodEnd);
        $definitions = $this->productDefinitions();
        $deliveries = $this->deliveryLogs($start, $end);
        $readings = $this->includedShiftReadings($start, $end, $trackingPeriodId);
        $adjustmentsByReading = $this->activeAdjustmentsByReading(
            $readings->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
        $movementTotals = $period
            ? $this->movementTotalsByFuel($this->litersMovement->reportSummary($period))
            : [];
        $prices = $this->pricesByFuel();
        $tankLimits = $this->decodedTankLimits($period);
        $deliveryRows = [];
        $readingRows = [];

        foreach ($deliveries as $delivery) {
            $fuel = $this->canonicalFuel($delivery->fuel_type ?? null);
            $liters = max(0, (float) ($delivery->liters_delivered ?? 0));
            $totalCost = max(0, (float) ($delivery->total_cost ?? 0));

            if ($fuel === null || $liters <= 0 || ! isset($definitions[$fuel])) {
                continue;
            }

            $occurredAt = $this->deliveryOccurredAt($delivery);
            $deliveryRows[] = [
                'id' => (int) $delivery->id,
                'delivery_code' => (string) ($delivery->delivery_code ?? ''),
                'delivery_date' => $occurredAt->toDateString(),
                'logged_delivery_date' => Carbon::parse($delivery->delivery_date)->toDateString(),
                'occurred_at' => $occurredAt->toDateTimeString(),
                'supplier' => (string) ($delivery->supplier_name ?? 'N/A'),
                'delivery_status' => (string) ($delivery->delivery_status ?? ''),
                'tank_id' => (int) ($delivery->tank_id ?? 0),
                'fuel_type' => $fuel,
                'product_name' => $definitions[$fuel]['product_name'],
                'liters' => $liters,
                'unit_cost' => $this->divide($totalCost, $liters),
                'total_cost' => $totalCost,
            ];
        }

        foreach ($readings as $reading) {
            $occurredAt = Carbon::parse(
                $reading->created_at
                    ?? $reading->period_to
                    ?? $end
            );

            foreach ($definitions as $fuel => $definition) {
                $liters = 0.0;
                $revenue = 0.0;
                $adjustmentIds = [];

                foreach ($definition['pumps'] as $pump) {
                    $adjustments = collect($adjustmentsByReading[(int) $reading->id][$pump] ?? []);
                    $adjustedLiters = (float) $adjustments->sum('liters');
                    $adjustedAmount = (float) $adjustments->sum('amount');
                    $adjustmentIds = array_merge($adjustmentIds, $adjustments->pluck('id')->all());
                    $liters += max(
                        0,
                        (float) ($reading->{"pump{$pump}_liters_end"} ?? 0)
                            - (float) ($reading->{"pump{$pump}_liters_start"} ?? 0)
                            - $adjustedLiters
                    );
                    $revenue += max(
                        0,
                        (float) ($reading->{"pump{$pump}_sales_end"} ?? 0)
                            - (float) ($reading->{"pump{$pump}_sales_start"} ?? 0)
                            - $adjustedAmount
                    );
                }

                if ($liters <= 0 && $revenue <= 0) {
                    continue;
                }

                $readingRows[] = [
                    'id' => (int) $reading->id,
                    'status' => (string) ($reading->status ?? ''),
                    'period_from' => Carbon::parse($reading->period_from ?? $occurredAt)->toDateString(),
                    'period_to' => Carbon::parse($reading->period_to ?? $occurredAt)->toDateString(),
                    'occurred_at' => $occurredAt->toDateTimeString(),
                    'fuel_type' => $fuel,
                    'product_name' => $definition['product_name'],
                    'pumps' => $definition['pumps'],
                    'liters' => $liters,
                    'revenue' => $revenue,
                    'adjustment_ids' => array_values(array_unique($adjustmentIds)),
                ];
            }
        }

        $lines = [];
        $warnings = [];

        foreach ($definitions as $fuel => $definition) {
            $lineWarnings = [];
            $opening = $this->openingBalance($openingBalances, $fuel);
            $openingLiters = max(0, (float) ($opening['liters'] ?? 0));
            $openingUnitCost = max(0, (float) ($opening['unit_cost'] ?? 0));
            $openingValue = array_key_exists('value', $opening)
                ? max(0, (float) $opening['value'])
                : $openingLiters * $openingUnitCost;
            $fuelDeliveries = collect($deliveryRows)->where('fuel_type', $fuel)->values();
            $fuelReadings = collect($readingRows)->where('fuel_type', $fuel)->values();
            $purchasedLiters = (float) $fuelDeliveries->sum('liters');
            $purchaseValue = (float) $fuelDeliveries->sum('total_cost');
            $availableLiters = $openingLiters + $purchasedLiters;
            $availableValue = $openingValue + $purchaseValue;
            $periodicAverageCost = $this->divide($availableValue, $availableLiters);
            $movement = $movementTotals[$fuel] ?? null;
            $dispensedLiters = $movement !== null
                ? (float) $movement['liters']
                : (float) $fuelReadings->sum('liters');
            $grossSalesRevenue = $movement !== null
                ? (float) $movement['total_sales']
                : (float) $fuelReadings->sum('revenue');
            $salesDiscounts = $movement !== null
                ? (float) $movement['discounts']
                : 0.0;
            $salesRevenue = $movement !== null
                ? (float) $movement['net_sales']
                : $grossSalesRevenue;
            [$configuredTankLimit, $hasTankLimit] = $this->tankLimitForDefinition(
                $definition,
                $tankLimits,
                $availableLiters
            );
            $endingLiters = $availableLiters - $dispensedLiters;
            $costOfGoodsSold = $dispensedLiters * $periodicAverageCost;
            $endingValue = $endingLiters * $periodicAverageCost;
            $averageSellingPrice = $this->divide($salesRevenue, $dispensedLiters);
            $grossMarginPerLiter = $dispensedLiters > 0
                ? $averageSellingPrice - $periodicAverageCost
                : 0.0;
            $pumpPrice = (float) ($prices[$fuel]['pump_price'] ?? 0);
            $bulkPrice = (float) ($prices[$fuel]['bulk_price'] ?? 0);

            if ($openingLiters > 0 && $openingValue <= 0) {
                $lineWarnings[] = "{$definition['product_name']} has opening liters but no opening cost.";
            }

            if ($period && ! $hasTankLimit) {
                $lineWarnings[] = "{$definition['product_name']} has no tank limit in the selected Liters Movement period. Current stock is being used as a preview fallback.";
            }

            if ($period && $hasTankLimit && abs($configuredTankLimit - $availableLiters) > 0.01) {
                $difference = abs($configuredTankLimit - $availableLiters);
                $direction = $configuredTankLimit > $availableLiters ? 'above' : 'below';
                $lineWarnings[] = "{$definition['product_name']} tank limit is "
                    . number_format($difference, 2)
                    . " L {$direction} calculated current stock. Current stock is used for this inventory report; update the editable Liters Movement limit if needed.";
            }

            if ($endingLiters < -0.0005) {
                $lineWarnings[] = "{$definition['product_name']} dispensed liters exceed calculated current stock by "
                    . number_format(abs($endingLiters), 2)
                    . ' L.';
            }

            if ($availableLiters > 0 && $periodicAverageCost <= 0) {
                $lineWarnings[] = "{$definition['product_name']} has current stock but no usable inventory cost.";
            }

            if ($bulkPrice <= 0) {
                $lineWarnings[] = "{$definition['product_name']} does not yet have a bulk selling price.";
            }

            $warnings = array_merge($warnings, $lineWarnings);
            $lines[] = [
                'tank_id' => $definition['tank_ids'][0] ?? null,
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
                'tank_limit_liters' => $this->rounded($availableLiters),
                'dispensed_liters' => $this->rounded($dispensedLiters),
                'sales_revenue' => $this->rounded($salesRevenue),
                'cost_of_goods_sold' => $this->rounded($costOfGoodsSold),
                'ending_liters' => $this->rounded($endingLiters),
                'ending_unit_cost' => $this->rounded($periodicAverageCost),
                'ending_value' => $this->rounded($endingValue),
                'pump_price' => $this->rounded($pumpPrice),
                'bulk_price' => $this->rounded($bulkPrice),
                'pump_margin' => $this->rounded($pumpPrice - $periodicAverageCost),
                'bulk_margin' => $this->rounded($bulkPrice - $periodicAverageCost),
                'average_selling_price' => $this->rounded($averageSellingPrice),
                'gross_margin_per_liter' => $this->rounded($grossMarginPerLiter),
                'gross_profit' => $this->rounded($salesRevenue - $costOfGoodsSold),
                'source_summary' => [
                    'liters_tracking_period' => $this->periodMetadata($period),
                    'tank_ids' => $definition['tank_ids'],
                    'pump_numbers' => $definition['pumps'],
                    'tank_limit_source' => 'calculated_current_stock',
                    'configured_liters_movement_limit' => $this->rounded($configuredTankLimit),
                    'has_configured_liters_movement_limit' => $hasTankLimit,
                    'sales_basis' => $movement !== null ? 'liters_movement_net_sales' : 'meter_sales',
                    'gross_sales_revenue' => $this->rounded($grossSalesRevenue),
                    'sales_discounts' => $this->rounded($salesDiscounts),
                    'net_sales_revenue' => $this->rounded($salesRevenue),
                    'delivery_ids' => $fuelDeliveries->pluck('id')->all(),
                    'shift_reading_ids' => $fuelReadings->pluck('id')->unique()->values()->all(),
                    'deliveries' => $fuelDeliveries->all(),
                    'shift_readings' => $fuelReadings->all(),
                    'warnings' => $lineWarnings,
                ],
            ];
        }

        return [
            'liters_tracking_period_id' => $trackingPeriodId,
            'tracking_period' => $this->periodMetadata($period),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'calculation_method' => 'periodic_weighted_average',
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

    public function reportHistory(int $limit = 12): Collection
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

    public function finalizedReportForTrackingPeriod(int $trackingPeriodId): ?object
    {
        if ($trackingPeriodId <= 0
            || ! Schema::hasTable('fuel_inventory_reports')
            || ! Schema::hasColumn('fuel_inventory_reports', 'liters_tracking_period_id')) {
            return null;
        }

        return DB::table('fuel_inventory_reports')
            ->where('liters_tracking_period_id', $trackingPeriodId)
            ->whereRaw('LOWER(status) = ?', ['finalized'])
            ->first();
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
        $warnings = collect($lines)
            ->flatMap(fn (array $line): array => $line['source_summary']['warnings'] ?? [])
            ->unique()
            ->values()
            ->all();
        $trackingPeriodId = Schema::hasColumn('fuel_inventory_reports', 'liters_tracking_period_id')
            ? (int) ($report->liters_tracking_period_id ?? 0)
            : 0;
        $trackingPeriod = $trackingPeriodId > 0 && Schema::hasTable('liters_tracking_periods')
            ? DB::table('liters_tracking_periods')->where('id', $trackingPeriodId)->first()
            : null;

        return [
            'id' => (int) $report->id,
            'report_code' => (string) $report->report_code,
            'status' => (string) $report->status,
            'liters_tracking_period_id' => $trackingPeriodId ?: null,
            'tracking_period' => $this->periodMetadata($trackingPeriod),
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

    public function finalize(int $trackingPeriodId, array $manualOpening, ?int $createdBy): array
    {
        return DB::transaction(function () use ($trackingPeriodId, $manualOpening, $createdBy): array {
            $period = DB::table('liters_tracking_periods')
                ->where('id', $trackingPeriodId)
                ->lockForUpdate()
                ->first();

            if (! $period) {
                throw new RuntimeException('The selected Liters Movement period was not found.');
            }

            if (strtolower(trim((string) $period->status)) !== 'generated') {
                throw new RuntimeException('Generate the Liters Movement period before finalizing its inventory report.');
            }

            if (Schema::hasColumn('fuel_inventory_reports', 'liters_tracking_period_id')
                && DB::table('fuel_inventory_reports')
                    ->where('liters_tracking_period_id', $trackingPeriodId)
                    ->exists()) {
                throw new RuntimeException('This Liters Movement period already has a finalized inventory report.');
            }

            $latest = $this->latestFinalizedReport(true);
            $this->assertPeriodFollowsLatestReport($period, $latest);
            $opening = $latest
                ? $this->openingFromReport($latest)
                : $this->firstOpeningForFinalization($period, $manualOpening);
            [$start, $end] = $this->reportWindow($period, $period->started_at, $period->ended_at);
            $calculation = $this->calculate($start, $end, $opening, $trackingPeriodId);
            $sequence = DB::table('fuel_inventory_reports')
                ->whereDate('period_end', $end->toDateString())
                ->count() + 1;
            $reportCode = 'INV-' . $end->format('Ymd') . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $now = now();
            $reportValues = [
                'report_code' => $reportCode,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => 'Finalized',
                'calculation_method' => 'periodic_weighted_average',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('fuel_inventory_reports', 'liters_tracking_period_id')) {
                $reportValues['liters_tracking_period_id'] = $trackingPeriodId;
            }

            $reportId = DB::table('fuel_inventory_reports')->insertGetId($reportValues);

            foreach ($calculation['lines'] as $line) {
                $lineValues = collect($line)->except('source_summary')->all();

                if (! Schema::hasColumn('fuel_inventory_report_lines', 'tank_id')) {
                    unset($lineValues['tank_id']);
                }

                if (! Schema::hasColumn('fuel_inventory_report_lines', 'tank_limit_liters')) {
                    unset($lineValues['tank_limit_liters']);
                }

                DB::table('fuel_inventory_report_lines')->insert(array_merge(
                    $lineValues,
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

    private function reportWindow(
        ?object $period,
        string|Carbon $periodStart,
        string|Carbon|null $periodEnd
    ): array {
        $start = $period
            ? Carbon::parse($period->started_at)
            : Carbon::parse($periodStart)->startOfDay();
        $end = $period
            ? ($period->ended_at ? Carbon::parse($period->ended_at) : now())
            : Carbon::parse($periodEnd)->endOfDay();

        if ($end->lt($start)) {
            throw new RuntimeException('The report end date must be on or after its start date.');
        }

        return [$start, $end];
    }

    private function assertPeriodFollowsLatestReport(object $period, ?object $latest): void
    {
        if (! $latest) {
            return;
        }

        if (Schema::hasColumn('fuel_inventory_reports', 'liters_tracking_period_id')
            && ! empty($latest->liters_tracking_period_id)) {
            $latestPeriod = DB::table('liters_tracking_periods')
                ->where('id', (int) $latest->liters_tracking_period_id)
                ->first();

            if ($latestPeriod && Carbon::parse($period->started_at)->lt(Carbon::parse($latestPeriod->ended_at))) {
                throw new RuntimeException('Select a Liters Movement period that follows the latest finalized inventory report.');
            }

            return;
        }

        if (Carbon::parse($period->started_at)->startOfDay()->lte(Carbon::parse($latest->period_end)->endOfDay())) {
            throw new RuntimeException('Select a Liters Movement period that follows the latest finalized inventory report.');
        }
    }

    private function movementTotalsByFuel(array $summary): array
    {
        $totals = collect(self::PRODUCTS)
            ->map(fn (): array => [
                'liters' => 0.0,
                'total_sales' => 0.0,
                'discounts' => 0.0,
                'net_sales' => 0.0,
            ])
            ->all();

        foreach (($summary['pump_net_sales'] ?? []) as $pump) {
            $fuel = $this->canonicalFuel($pump['fuel_type'] ?? null);

            if ($fuel === null || ! isset($totals[$fuel])) {
                continue;
            }

            $totals[$fuel]['liters'] += max(0, (float) ($pump['liters'] ?? 0));
            $totals[$fuel]['total_sales'] += (float) ($pump['total_sales'] ?? 0);
            $totals[$fuel]['discounts'] += (float) ($pump['discounts'] ?? 0);
            $totals[$fuel]['net_sales'] += (float) ($pump['net_sales'] ?? 0);
        }

        return collect($totals)
            ->map(fn (array $row): array => [
                'liters' => $this->rounded($row['liters']),
                'total_sales' => $this->rounded($row['total_sales']),
                'discounts' => $this->rounded($row['discounts']),
                'net_sales' => $this->rounded($row['net_sales']),
            ])
            ->all();
    }

    private function deliveryLogs(Carbon $start, Carbon $end): Collection
    {
        if (! Schema::hasTable('inventory_logs') || ! Schema::hasTable('tanks')) {
            return collect();
        }

        $query = DB::table('inventory_logs as logs')
            ->join('tanks', 'logs.tank_id', '=', 'tanks.id');
        $hasDeliveryRecords = Schema::hasTable('tank_delivery_records')
            && Schema::hasColumn('tank_delivery_records', 'inventory_log_id')
            && Schema::hasColumn('tank_delivery_records', 'record_date')
            && Schema::hasColumn('tank_delivery_records', 'record_time');

        if ($hasDeliveryRecords) {
            $query->leftJoin(
                'tank_delivery_records as delivery_records',
                'delivery_records.inventory_log_id',
                '=',
                'logs.id'
            );
        }

        if (Schema::hasTable('suppliers')) {
            $query->leftJoin('suppliers', 'logs.supplier_id', '=', 'suppliers.id');
        }

        $select = [
            'logs.id',
            'logs.delivery_code',
            'logs.tank_id',
            'logs.delivery_date',
            Schema::hasColumn('inventory_logs', 'delivery_status')
                ? 'logs.delivery_status'
                : DB::raw("'' as delivery_status"),
            Schema::hasColumn('inventory_logs', 'created_at')
                ? 'logs.created_at'
                : DB::raw('null as created_at'),
            'logs.liters_delivered',
            'logs.total_cost',
            'tanks.fuel_type',
        ];
        $select[] = $hasDeliveryRecords
            ? 'delivery_records.record_date'
            : DB::raw('null as record_date');
        $select[] = $hasDeliveryRecords
            ? 'delivery_records.record_time'
            : DB::raw('null as record_time');
        $select[] = $hasDeliveryRecords && Schema::hasColumn('tank_delivery_records', 'rejected_at')
            ? 'delivery_records.rejected_at'
            : DB::raw('null as rejected_at');
        $select[] = Schema::hasTable('suppliers')
            ? 'suppliers.name as supplier_name'
            : DB::raw("'N/A' as supplier_name");

        $query
            ->where(function ($query) use ($start, $end, $hasDeliveryRecords): void {
                $query->whereBetween('logs.delivery_date', [
                    $start->toDateString(),
                    $end->toDateString(),
                ]);

                if ($hasDeliveryRecords) {
                    $query->orWhereBetween('delivery_records.record_date', [
                        $start->toDateString(),
                        $end->toDateString(),
                    ]);
                }
            })
            ->select($select)
            ->orderBy('logs.delivery_date');

        if (Schema::hasColumn('inventory_logs', 'created_at')) {
            $query->orderBy('logs.created_at');
        }

        return $query
            ->orderBy('logs.id')
            ->get()
            ->filter(function (object $delivery) use ($start, $end): bool {
                if (! $this->deliveryIsIncluded($delivery)) {
                    return false;
                }

                $occurredAt = $this->deliveryOccurredAt($delivery);

                return $occurredAt->betweenIncluded($start, $end);
            })
            ->values();
    }

    private function includedShiftReadings(Carbon $start, Carbon $end, ?int $trackingPeriodId): Collection
    {
        if (! Schema::hasTable('shift_readings')) {
            return collect();
        }

        $query = DB::table('shift_readings');

        if ($trackingPeriodId && Schema::hasTable('liters_movement_period_readings')) {
            $query
                ->join(
                    'liters_movement_period_readings as period_memberships',
                    'period_memberships.shift_reading_id',
                    '=',
                    'shift_readings.id'
                )
                ->where('period_memberships.liters_tracking_period_id', $trackingPeriodId)
                ->whereRaw('LOWER(TRIM(period_memberships.inclusion_status)) = ?', ['included']);

            if (Schema::hasColumn('liters_movement_period_readings', 'removed_at')) {
                $query->whereNull('period_memberships.removed_at');
            }
        } elseif ($trackingPeriodId && Schema::hasColumn('shift_readings', 'liters_tracking_period_id')) {
            $query->where('shift_readings.liters_tracking_period_id', $trackingPeriodId);
        } else {
            $query
                ->whereDate('shift_readings.period_to', '>=', $start->toDateString())
                ->whereDate('shift_readings.period_to', '<=', $end->toDateString());
        }

        return $query
            ->whereIn(DB::raw('LOWER(TRIM(shift_readings.status))'), self::INCLUDED_READING_STATUSES)
            ->select('shift_readings.*')
            ->orderBy('shift_readings.period_to')
            ->orderBy('shift_readings.id')
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

    private function productDefinitions(): array
    {
        $definitions = collect(self::PRODUCTS)
            ->map(fn (array $definition): array => array_merge($definition, [
                'tank_ids' => [],
                'configured_pumps' => [],
            ]))
            ->all();

        if (! Schema::hasTable('tanks')) {
            return $definitions;
        }

        $columns = ['id', 'fuel_type'];

        if (Schema::hasColumn('tanks', 'connected_pumps')) {
            $columns[] = 'connected_pumps';
        }

        DB::table('tanks')
            ->select($columns)
            ->orderBy('id')
            ->get()
            ->each(function (object $tank) use (&$definitions): void {
                $fuel = $this->canonicalFuel($tank->fuel_type ?? null);

                if ($fuel === null || ! isset($definitions[$fuel])) {
                    return;
                }

                $definitions[$fuel]['tank_ids'][] = (int) $tank->id;
                $definitions[$fuel]['configured_pumps'] = array_merge(
                    $definitions[$fuel]['configured_pumps'],
                    $this->parsePumpNumbers($tank->connected_pumps ?? null)
                );
            });

        foreach ($definitions as &$definition) {
            $definition['tank_ids'] = array_values(array_unique($definition['tank_ids']));
            $configuredPumps = array_values(array_unique($definition['configured_pumps']));

            if ($configuredPumps !== []) {
                sort($configuredPumps);
                $definition['pumps'] = $configuredPumps;
            }

            unset($definition['configured_pumps']);
        }
        unset($definition);

        return $definitions;
    }

    private function parsePumpNumbers(mixed $connectedPumps): array
    {
        preg_match_all('/\d+/', (string) $connectedPumps, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($pump) => (int) $pump)
            ->filter(fn (int $pump): bool => $pump >= 1 && $pump <= 6)
            ->unique()
            ->values()
            ->all();
    }

    private function decodedTankLimits(?object $period): array
    {
        if (! $period || ! isset($period->tank_limits)) {
            return [];
        }

        $limits = json_decode((string) $period->tank_limits, true);

        return is_array($limits) ? $limits : [];
    }

    private function tankLimitForDefinition(array $definition, array $tankLimits, float $fallback): array
    {
        $limit = 0.0;
        $found = false;

        foreach ($definition['tank_ids'] as $tankId) {
            $key = "tank:{$tankId}";

            if (! array_key_exists($key, $tankLimits) || ! is_numeric($tankLimits[$key])) {
                continue;
            }

            $limit += max(0, (float) $tankLimits[$key]);
            $found = true;
        }

        return [$found ? $limit : $fallback, $found];
    }

    private function pricesByFuel(): array
    {
        if (! Schema::hasTable('fuel_prices')) {
            return [];
        }

        $hasBulkPrice = Schema::hasColumn('fuel_prices', 'bulk_price_per_liter');

        return DB::table('fuel_prices')
            ->orderBy('id')
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
        if (! empty($delivery->record_date)) {
            $date = Carbon::parse($delivery->record_date)->toDateString();
            $time = ! empty($delivery->record_time)
                ? Carbon::parse($delivery->record_time)->format('H:i:s')
                : '00:00:00';

            return Carbon::parse("{$date} {$time}");
        }

        $date = Carbon::parse($delivery->delivery_date)->toDateString();
        $time = ! empty($delivery->created_at)
            ? Carbon::parse($delivery->created_at)->format('H:i:s')
            : '00:00:00';

        return Carbon::parse("{$date} {$time}");
    }

    private function deliveryIsIncluded(object $delivery): bool
    {
        if (! empty($delivery->rejected_at)) {
            return false;
        }

        $status = strtolower(trim((string) ($delivery->delivery_status ?? '')));

        return ! str_contains($status, 'reject')
            && ! str_contains($status, 'cancel')
            && ! str_contains($status, 'void');
    }

    private function periodMetadata(?object $period): ?array
    {
        if (! $period) {
            return null;
        }

        return [
            'id' => (int) $period->id,
            'period_code' => (string) ($period->period_code ?? ''),
            'status' => (string) ($period->status ?? ''),
            'started_at' => Carbon::parse($period->started_at)->toDateTimeString(),
            'ended_at' => $period->ended_at
                ? Carbon::parse($period->ended_at)->toDateTimeString()
                : null,
        ];
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

    private function storedInitialOpening(object $period): array
    {
        if (! isset($period->opening_inventory) || trim((string) $period->opening_inventory) === '') {
            return [];
        }

        $decoded = json_decode((string) $period->opening_inventory, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect(self::PRODUCTS)
            ->mapWithKeys(function (array $definition, string $fuel) use ($decoded): array {
                $line = $this->openingBalance($decoded, $fuel);
                $liters = max(0, (float) ($line['liters'] ?? 0));
                $unitCost = max(0, (float) ($line['unit_cost'] ?? 0));

                return [$fuel => [
                    'liters' => $this->rounded($liters),
                    'unit_cost' => $this->rounded($unitCost),
                    'value' => $this->rounded(
                        array_key_exists('value', $line)
                            ? max(0, (float) $line['value'])
                            : $liters * $unitCost
                    ),
                ]];
            })
            ->all();
    }

    private function firstOpeningForFinalization(object $period, array $manualOpening): array
    {
        $opening = $this->initialOpeningFromTrackingPeriod($period);

        foreach (self::PRODUCTS as $fuel => $definition) {
            $line = $opening[$fuel] ?? ['liters' => 0.0, 'unit_cost' => 0.0];
            $submitted = $this->openingBalance($manualOpening, $fuel);
            $liters = max(0, (float) ($line['liters'] ?? 0));
            $unitCost = max(0, (float) ($line['unit_cost'] ?? 0));

            if ($unitCost <= 0) {
                $unitCost = max(0, (float) ($submitted['unit_cost'] ?? 0));
            }

            if ($liters > 0 && $unitCost <= 0) {
                throw new RuntimeException("Enter and save the opening price per liter for {$definition['product_name']} before finalizing.");
            }

            $opening[$fuel] = [
                'liters' => $this->rounded($liters),
                'unit_cost' => $this->rounded($unitCost),
                'value' => $this->rounded($liters * $unitCost),
            ];
        }

        return $opening;
    }

    private function totals(array $lines): array
    {
        $collection = collect($lines);

        return [
            'opening_value' => $this->rounded((float) $collection->sum('opening_value')),
            'purchase_value' => $this->rounded((float) $collection->sum('purchase_value')),
            'available_value' => $this->rounded((float) $collection->sum('available_value')),
            'tank_limit_liters' => $this->rounded((float) $collection->sum('tank_limit_liters')),
            'ending_value' => $this->rounded((float) $collection->sum('ending_value')),
            'ending_liters' => $this->rounded((float) $collection->sum('ending_liters')),
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
