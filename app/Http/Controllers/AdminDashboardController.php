<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\Process\Process;

class AdminDashboardController extends Controller
{
    private const PERIODS = ['today', 'weekly', 'monthly', 'custom'];
    private const METRIC_DATE_FILTERS = [
        'net-sales' => 'net_sales',
        'expected-credit' => 'expected_credit',
        'credit-payments' => 'credit_payments',
        'advance-receivables' => 'advance_receivables',
        'total-excess' => 'total_excess',
    ];

    private $lockedSalesPresence = [];
    private array $tablePresence = [];
    private array $columnPresence = [];

    public function dashboard(Request $request): View
    {
        $payload = $this->dashboardPayload($request);

        return view('pages.admin.admindashboard', array_merge($payload, [
            'title' => 'Admin Dashboard',
        ]));
    }

    public function getDashboardData(Request $request): JsonResponse
    {
        return response()->json($this->dashboardPayload($request));
    }

    private function dashboardPayload(?Request $request = null): array
    {
        $ranges = $this->periodRanges();
        $metricDateRanges = $this->metricDateRanges($request);
        $lastVerifiedShift = null;

        if ($this->hasTable('shift_readings')) {
            $lastVerifiedShift = DB::table('shift_readings')
                ->when($this->hasColumn('shift_readings', 'status'), fn ($query) => $query->where('status', 'Verified'))
                ->when($this->hasColumn('shift_readings', 'created_at'), fn ($query) => $query->orderByDesc('created_at'))
                ->first();
        }

        $lastVerifiedAt = $lastVerifiedShift
            ? Carbon::parse($lastVerifiedShift->created_at)
            : null;

        [$chartLabels, $chartData] = $this->salesTrend();
        $creditSnapshot = $this->creditSnapshot();

        return [
            'stats' => $this->statsForPeriods($ranges, $creditSnapshot),
            'customMetricStats' => $this->customMetricStats($metricDateRanges, $creditSnapshot),
            'tankMovements' => $this->tankMovementsForPeriods($ranges),
            'pumpSales' => $this->pumpSalesForPeriods($ranges),
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'periodLabels' => $this->periodLabels(),
            'cardDefaultPeriods' => $this->cardDefaultPeriods($metricDateRanges),
            'customPeriodLabels' => $this->customPeriodLabels($metricDateRanges),
            'metricDateFilters' => $this->metricDateFilters($metricDateRanges),
            'metricDateFilterNames' => self::METRIC_DATE_FILTERS,
            'lastVerifiedAt' => $lastVerifiedAt?->toDateTimeString(),
            'lastUpdatedAt' => Carbon::now()->toDateTimeString(),
        ];
    }

    private function metricDateRanges(?Request $request): array
    {
        $ranges = [];

        if (! $request) {
            return $ranges;
        }

        foreach (self::METRIC_DATE_FILTERS as $target => $prefix) {
            $range = $this->dateRangeFromValues(
                $request->query($prefix . '_from'),
                $request->query($prefix . '_to')
            );

            if ($range) {
                $ranges[$target] = $range;
            }
        }

        return $ranges;
    }

    private function dateRangeFromValues($from, $to): ?array
    {
        if (! $from && ! $to) {
            return null;
        }

        try {
            $start = Carbon::parse($from ?: $to)->startOfDay();
            $end = Carbon::parse($to ?: $from)->endOfDay();
        } catch (\Exception $e) {
            return null;
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function periodRanges(): array
    {
        return [
            'today' => [
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
            ],
            'weekly' => [
                Carbon::now()->startOfWeek()->startOfDay(),
                Carbon::today()->endOfDay(),
            ],
            'monthly' => [
                Carbon::now()->startOfMonth()->startOfDay(),
                Carbon::today()->endOfDay(),
            ],
            'custom' => [
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
            ],
        ];
    }

    private function periodLabels(): array
    {
        return [
            'today' => 'Today',
            'weekly' => 'This Week',
            'monthly' => 'This Month',
            'custom' => 'Custom',
        ];
    }

    private function customPeriodLabels(array $metricDateRanges): array
    {
        return collect($metricDateRanges)
            ->map(fn ($range) => $range[0]->format('M d, Y') . ' - ' . $range[1]->format('M d, Y'))
            ->all();
    }

    private function cardDefaultPeriods(array $metricDateRanges): array
    {
        return collect(self::METRIC_DATE_FILTERS)
            ->mapWithKeys(fn ($prefix, $target) => [$target => isset($metricDateRanges[$target]) ? 'custom' : 'today'])
            ->all();
    }

    private function metricDateFilters(array $metricDateRanges): array
    {
        $today = Carbon::today()->toDateString();

        return collect(self::METRIC_DATE_FILTERS)
            ->mapWithKeys(function ($prefix, $target) use ($metricDateRanges, $today) {
                $range = $metricDateRanges[$target] ?? null;

                return [
                    $target => [
                        'prefix' => $prefix,
                        'from' => $range ? $range[0]->toDateString() : $today,
                        'to' => $range ? $range[1]->toDateString() : $today,
                    ],
                ];
            })
            ->all();
    }

    private function statsForPeriods(array $ranges, array $creditSnapshot): array
    {
        $stats = [];

        foreach (self::PERIODS as $period) {
            [$start, $end] = $ranges[$period];
            $stats[$period] = $this->statsForRange($start, $end, $creditSnapshot);
        }

        return $stats;
    }

    private function customMetricStats(array $metricDateRanges, array $creditSnapshot): array
    {
        return collect($metricDateRanges)
            ->map(fn ($range) => $this->statsForRange($range[0], $range[1], $creditSnapshot))
            ->all();
    }

    private function statsForRange(Carbon $start, Carbon $end, array $creditSnapshot): array
    {
        $hasSales = $this->hasTable('sales');
        $fuelSales = $hasSales ? $this->fuelSalesQuery($start, $end) : null;
        $shiftReadingNetSales = $this->shiftReadingNetSales($start, $end);

        $paymentRows = $this->creditPaymentRows($start, $end);

        return [
            'sales_table_net_sales_with_credit' => $hasSales ? $this->sumSalesTableNetSales(clone $fuelSales) : 0,
            'sales_table_net_sales_without_credit' => $hasSales ? $this->sumSalesTableNetSales(
                $this->walkinFuelSales(clone $fuelSales)
            ) : 0,
            'shift_reading_net_sales_with_credit' => $shiftReadingNetSales['with_credit'],
            'shift_reading_net_sales_without_credit' => $shiftReadingNetSales['without_credit'],
            'shift_reading_count' => $shiftReadingNetSales['reading_count'],
            'expected_credit_sales' => $this->expectedCreditSales($start, $end),
            'expected_credit_receivables' => $creditSnapshot['receivables'],
            'credit_payments_received' => $this->money($paymentRows->sum('credit')),
            'credit_payment_count' => $paymentRows->count(),
            'advance_receivables_received' => $this->advanceReceivedFromPayments($paymentRows),
            'overall_advance_balance' => $creditSnapshot['advance_balance'],
            'total_excess' => $hasSales && $this->hasColumn('sales', 'surcharge')
                ? $this->money((clone $fuelSales)->sum('surcharge'))
                : 0,
        ];
    }

    private function creditSnapshot(): array
    {
        if (! $this->hasTable('customers') || ! $this->hasColumn('customers', 'current_balance')) {
            return [
                'receivables' => 0,
                'advance_balance' => 0,
            ];
        }

        $snapshot = DB::table('customers')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN current_balance > 0 THEN current_balance ELSE 0 END), 0) as receivables,
                COALESCE(SUM(CASE WHEN current_balance < 0 THEN ABS(current_balance) ELSE 0 END), 0) as advance_balance
            ')
            ->first();

        return [
            'receivables' => $this->money($snapshot->receivables ?? 0),
            'advance_balance' => $this->money($snapshot->advance_balance ?? 0),
        ];
    }

    private function creditPaymentRows(Carbon $start, Carbon $end)
    {
        if (! $this->hasTable('customer_ledgers')) {
            return collect();
        }

        foreach (['type', 'transaction_date', 'credit', 'debit', 'running_balance'] as $column) {
            if (! $this->hasColumn('customer_ledgers', $column)) {
                return collect();
            }
        }

        return DB::table('customer_ledgers')
            ->where('type', 'Payment')
            ->whereBetween('transaction_date', [$start, $end])
            ->select('credit', 'debit', 'running_balance')
            ->get();
    }

    private function advanceReceivedFromPayments($paymentRows): float
    {
        return $this->money($paymentRows->sum(function ($row) {
            $credit = (float) ($row->credit ?? 0);
            $runningBalance = (float) ($row->running_balance ?? 0);

            if ($credit <= 0 || $runningBalance >= 0) {
                return 0;
            }

            return min($credit, abs($runningBalance));
        }));
    }

    private function tankMovementsForPeriods(array $ranges): array
    {
        $movements = [];

        foreach (self::PERIODS as $period) {
            [$start, $end] = $ranges[$period];
            $movements[$period] = $this->tankMovements($start, $end);
        }

        return $movements;
    }

    private function tankMovements(Carbon $start, Carbon $end): array
    {
        $tanks = $this->preparedTanks();
        $salesByPumpFuel = collect();

        if (
            $this->hasTable('sales')
            && $this->hasColumn('sales', 'pump_id')
            && $this->hasColumn('sales', 'fuel_type')
            && $this->hasColumn('sales', 'liters')
        ) {
            $netExpression = $this->salesNetExpression();
            $salesByPumpFuel = $this->fuelSalesQuery($start, $end)
                ->selectRaw("
                    pump_id,
                    fuel_type,
                    COALESCE(SUM(liters), 0) as liters_sold,
                    COALESCE(SUM({$netExpression}), 0) as net_sales
                ")
                ->where('pump_id', '>', 0)
                ->groupBy('pump_id', 'fuel_type')
                ->get()
                ->keyBy(fn ($sale) => (int) $sale->pump_id . '|' . (string) $sale->fuel_type);
        }

        $deliveriesByTank = collect();

        if (
            $this->hasTable('inventory_logs')
            && $this->hasColumn('inventory_logs', 'tank_id')
            && $this->hasColumn('inventory_logs', 'delivery_date')
            && $this->hasColumn('inventory_logs', 'liters_delivered')
        ) {
            $deliveriesByTank = DB::table('inventory_logs')
                ->whereBetween('delivery_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('tank_id, COALESCE(SUM(liters_delivered), 0) as liters_delivered')
                ->groupBy('tank_id')
                ->pluck('liters_delivered', 'tank_id');
        }

        return $tanks->map(function ($tank) use ($salesByPumpFuel, $deliveriesByTank) {
            $soldLiters = $tank['connected_pump_numbers']->sum(function ($pumpNumber) use ($tank, $salesByPumpFuel) {
                $sale = $salesByPumpFuel->get((int) $pumpNumber . '|' . $tank['fuel_type']);

                return (float) ($sale->liters_sold ?? 0);
            });

            $deliveredLiters = (float) ($deliveriesByTank[$tank['id']] ?? 0);

            return [
                'id' => $tank['id'],
                'name' => $tank['name'],
                'fuel_type' => $tank['fuel_type'],
                'connected_pumps_display' => $tank['connected_pumps_display'],
                'current_volume' => $tank['current_volume'],
                'capacity' => $tank['capacity'],
                'percentage' => $tank['percentage'],
                'delivered_liters' => $this->liters($deliveredLiters),
                'sold_liters' => $this->liters($soldLiters),
                'net_liters' => $this->liters($deliveredLiters - $soldLiters),
            ];
        })->values()->all();
    }

    private function pumpSalesForPeriods(array $ranges): array
    {
        $pumpSales = [];

        foreach (self::PERIODS as $period) {
            [$start, $end] = $ranges[$period];
            $pumpSales[$period] = $this->pumpSales($start, $end);
        }

        return $pumpSales;
    }

    private function pumpSales(Carbon $start, Carbon $end): array
    {
        $defaultFuelByPump = $this->defaultFuelByPump();
        $rows = collect();

        if (
            $this->hasTable('sales')
            && $this->hasColumn('sales', 'pump_id')
            && $this->hasColumn('sales', 'fuel_type')
            && $this->hasColumn('sales', 'liters')
        ) {
            $netExpression = $this->salesNetExpression();
            $rows = $this->fuelSalesQuery($start, $end)
                ->selectRaw("
                    pump_id,
                    MAX(fuel_type) as fuel_type,
                    COALESCE(SUM(liters), 0) as liters,
                    COALESCE(SUM({$netExpression}), 0) as net_sales,
                    COUNT(*) as transactions
                ")
                ->where('pump_id', '>', 0)
                ->groupBy('pump_id')
                ->get()
                ->keyBy(fn ($row) => (int) $row->pump_id);
        }

        $pumpIds = collect(array_keys($defaultFuelByPump))
            ->merge($rows->keys())
            ->map(fn ($pumpId) => (int) $pumpId)
            ->unique()
            ->sort()
            ->values();

        return $pumpIds->map(function ($pumpId) use ($defaultFuelByPump, $rows) {
            $row = $rows->get($pumpId);

            return [
                'pump_id' => $pumpId,
                'pump_label' => 'Pump ' . $pumpId,
                'fuel_type' => $row->fuel_type ?? ($defaultFuelByPump[$pumpId] ?? 'N/A'),
                'liters' => $this->liters($row->liters ?? 0),
                'net_sales' => $this->money($row->net_sales ?? 0),
                'transactions' => (int) ($row->transactions ?? 0),
            ];
        })->all();
    }

    private function salesTrend(?array $customDateRange = null): array
    {
        $labels = [];
        $data = [];

        if ($customDateRange) {
            [$start, $end] = $customDateRange;
            $date = $start->copy()->startOfDay();

            while ($date->lte($end)) {
                $labels[] = $date->format('M d');
                $data[] = $this->hasTable('sales')
                    ? $this->money(
                        $this->sumSalesTableNetSales(
                            $this->fuelSalesQuery($date->copy()->startOfDay(), $date->copy()->endOfDay())
                        )
                    )
                    : 0;
                $date->addDay();
            }

            return [$labels, $data];
        }

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('D, M d');
            $data[] = $this->hasTable('sales')
                ? $this->money(
                    $this->sumSalesTableNetSales(
                        $this->fuelSalesQuery($date->copy()->startOfDay(), $date->copy()->endOfDay())
                    )
                )
                : 0;
        }

        return [$labels, $data];
    }

    private function fuelSalesQuery(Carbon $start, Carbon $end)
    {
        $query = DB::table('sales');

        if ($this->hasColumn('sales', 'created_at')) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($this->hasColumn('sales', 'payment_type')) {
            $query->where(function ($query) {
                $query->whereNull('payment_type')
                    ->orWhereRaw('LOWER(payment_type) <> ?', ['collection']);
            });
        }

        return $query;
    }

    private function walkinFuelSales($query)
    {
        if (! $this->hasColumn('sales', 'payment_type')) {
            return $query;
        }

        return $query->where(function ($query) {
            $query->whereNull('payment_type')
                ->orWhereRaw('LOWER(payment_type) IN (?, ?)', ['walkin', 'cash']);
        });
    }

    private function sumSalesTableNetSales($query): float
    {
        $netExpression = $this->salesNetExpression();
        $total = $query
            ->selectRaw("COALESCE(SUM({$netExpression}), 0) as total")
            ->value('total');

        return $this->money($total);
    }

    private function shiftReadingNetSales(Carbon $start, Carbon $end): array
    {
        if (
            ! $this->hasTable('shift_readings')
            || ! $this->hasColumn('shift_readings', 'period_from')
            || ! $this->hasColumn('shift_readings', 'period_to')
        ) {
            return [
                'with_credit' => 0,
                'without_credit' => 0,
                'reading_count' => 0,
            ];
        }

        $readings = DB::table('shift_readings')
            ->whereDate('period_from', '<=', $end->toDateString())
            ->whereDate('period_to', '>=', $start->toDateString())
            ->when($this->hasColumn('shift_readings', 'status'), fn ($query) => $query->where('status', 'Verified'))
            ->when($this->hasColumn('shift_readings', 'created_at'), fn ($query) => $query->orderBy('created_at'))
            ->get();

        $withCredit = 0;
        $withoutCredit = 0;

        foreach ($readings as $reading) {
            $lastVerified = $this->previousVerifiedReading($reading);
            $pumpSales = $this->readingPumpSales($reading, $lastVerified);
            $posStats = $this->readingSalesAdjustments($reading, $lastVerified);
            $discounts = (float) ($posStats->total_auto_discount ?? 0)
                + (float) ($posStats->total_manual_discount ?? 0);
            $creditSales = (float) ($posStats->credit_sales ?? 0);

            $withCredit += $pumpSales - $discounts;
            $withoutCredit += $pumpSales - $creditSales - $discounts;
        }

        return [
            'with_credit' => $this->money($withCredit),
            'without_credit' => $this->money($withoutCredit),
            'reading_count' => $readings->count(),
        ];
    }

    private function readingPumpSales($reading, $lastVerified = null): float
    {
        $total = 0;

        for ($pump = 1; $pump <= 6; $pump++) {
            $startField = "pump{$pump}_sales_start";
            $endField = "pump{$pump}_sales_end";
            $previousEnd = $lastVerified ? (float) ($lastVerified->$endField ?? 0) : 0;
            $startValue = $reading->$startField ?? $previousEnd;

            $total += (float) ($reading->$endField ?? 0) - (float) $startValue;
        }

        return $this->money($total);
    }

    private function readingSalesAdjustments($reading, $lastVerified = null)
    {
        if (! $this->hasTable('sales')) {
            return $this->emptyReadingAdjustments();
        }

        $periodStart = Carbon::parse($reading->period_from)->startOfDay();
        $periodEnd = Carbon::parse($reading->period_to ?: $reading->period_from)->endOfDay();
        $creditSalesExpression = $this->hasColumn('sales', 'payment_type') && $this->hasColumn('sales', 'amount')
            ? 'COALESCE(SUM(CASE WHEN LOWER(payment_type) = "credit" THEN amount ELSE 0 END), 0)'
            : '0';
        $autoDiscountExpression = $this->hasColumn('sales', 'discount')
            ? 'COALESCE(SUM(discount), 0)'
            : '0';
        $manualDiscountExpression = $this->hasColumn('sales', 'manual_discount')
            ? 'COALESCE(SUM(manual_discount), 0)'
            : '0';

        $salesQuery = DB::table('sales');

        return $this->scopeSalesToReading($salesQuery, $reading, $periodStart, $periodEnd)
            ->selectRaw("
                {$creditSalesExpression} as credit_sales,
                {$autoDiscountExpression} as total_auto_discount,
                {$manualDiscountExpression} as total_manual_discount
            ")
            ->first();
    }

    private function readingHasLockedSales($reading): bool
    {
        if (! $this->hasTable('sales') || ! $this->hasColumn('sales', 'locked_by_shift_reading_id')) {
            return false;
        }

        $readingId = (int) $reading->id;

        if (!array_key_exists($readingId, $this->lockedSalesPresence)) {
            $this->lockedSalesPresence[$readingId] = DB::table('sales')
                ->where('locked_by_shift_reading_id', $readingId)
                ->exists();
        }

        return $this->lockedSalesPresence[$readingId];
    }

    private function scopeSalesToReading($query, $reading, Carbon $periodStart, Carbon $periodEnd)
    {
        if ($this->readingHasLockedSales($reading)) {
            return $query->where('locked_by_shift_reading_id', $reading->id);
        }

        if ($this->hasColumn('sales', 'user_id') && isset($reading->user_id)) {
            $query->where('user_id', $reading->user_id);
        }

        if ($this->hasColumn('sales', 'created_at')) {
            $query->whereBetween('created_at', [$periodStart, $periodEnd]);
        }

        return $query;
    }

    private function previousVerifiedReading($reading)
    {
        if (
            ! $this->hasTable('shift_readings')
            || ! $this->hasColumn('shift_readings', 'id')
            || ! $this->hasColumn('shift_readings', 'period_to')
            || ! isset($reading->id, $reading->period_from)
        ) {
            return null;
        }

        $query = DB::table('shift_readings')
            ->when($this->hasColumn('shift_readings', 'status'), fn ($query) => $query->where('status', 'Verified'))
            ->where('id', '<>', $reading->id);

        if (! $this->hasColumn('shift_readings', 'created_at') || ! isset($reading->created_at)) {
            return $query
                ->whereDate('period_to', '<', $reading->period_from)
                ->orderByDesc('period_to')
                ->orderByDesc('id')
                ->first();
        }

        return $query
            ->where(function ($query) use ($reading) {
                $query->whereDate('period_to', '<', $reading->period_from)
                    ->orWhere(function ($q) use ($reading) {
                        $q->whereDate('period_to', '=', $reading->period_from)
                            ->where(function ($samePeriod) use ($reading) {
                                $samePeriod->where('created_at', '<', $reading->created_at)
                                    ->orWhere(function ($sameTimestamp) use ($reading) {
                                        $sameTimestamp->where('created_at', '=', $reading->created_at)
                                            ->where('id', '<', $reading->id);
                                    });
                            });
                    });
            })
            ->orderByDesc('period_to')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function preparedTanks()
    {
        if (! $this->hasTable('tanks')) {
            return collect();
        }

        $query = DB::table('tanks');

        if ($this->hasColumn('tanks', 'id')) {
            $query->orderBy('id');
        }

        return $query
            ->get()
            ->map(function ($tank) {
                $fuelType = $tank->fuel_type ?? 'N/A';
                $capacity = (float) (($tank->capacity ?? 0) > 0 ? $tank->capacity : 11000);
                $currentVolume = (float) ($tank->current_volume ?? 0);
                $percentage = $capacity > 0 ? ($currentVolume / $capacity) * 100 : 0;
                $pumpNumbers = $this->tankPumpNumbers($tank);

                return [
                    'id' => (int) ($tank->id ?? 0),
                    'name' => ($tank->name ?? null) ?: $fuelType,
                    'fuel_type' => $fuelType,
                    'current_volume' => $this->liters($currentVolume),
                    'capacity' => $this->liters($capacity),
                    'percentage' => round(max(0, min($percentage, 100)), 1),
                    'connected_pump_numbers' => $pumpNumbers,
                    'connected_pumps_display' => $pumpNumbers->isEmpty()
                        ? 'N/A'
                        : $pumpNumbers->map(fn ($pumpNumber) => 'P' . $pumpNumber)->implode(', '),
                ];
            });
    }

    private function tankPumpNumbers($tank)
    {
        $connectedPumps = trim((string) ($tank->connected_pumps ?? ''));
        $fuelType = $tank->fuel_type ?? null;

        $pumpNumbers = collect(preg_split('/\s*,\s*/', $connectedPumps, -1, PREG_SPLIT_NO_EMPTY))
            ->map(function ($pumpLabel) {
                preg_match('/\d+/', $pumpLabel, $matches);

                return isset($matches[0]) ? (int) $matches[0] : null;
            })
            ->filter(fn ($pumpNumber) => $pumpNumber > 0)
            ->unique()
            ->values();

        if ($pumpNumbers->isNotEmpty()) {
            return $pumpNumbers;
        }

        return collect($this->defaultFuelByPump())
            ->filter(fn ($defaultFuelType) => $defaultFuelType === $fuelType)
            ->keys()
            ->values();
    }

    private function defaultFuelByPump(): array
    {
        return [
            1 => 'Premium',
            2 => 'Unleaded',
            3 => 'Premium',
            4 => 'Unleaded',
            5 => 'Diesel',
            6 => 'Diesel',
        ];
    }

    private function expectedCreditSales(Carbon $start, Carbon $end): float
    {
        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'amount')
            || ! $this->hasColumn('sales', 'payment_type')
        ) {
            return 0;
        }

        $query = DB::table('sales')
            ->whereRaw('LOWER(payment_type) = ?', ['credit']);

        if ($this->hasColumn('sales', 'created_at')) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        return $this->money($query->sum('amount'));
    }

    private function salesNetExpression(): string
    {
        $amount = $this->hasColumn('sales', 'amount') ? 'COALESCE(amount, 0)' : '0';
        $discount = $this->hasColumn('sales', 'discount') ? 'COALESCE(discount, 0)' : '0';
        $manualDiscount = $this->hasColumn('sales', 'manual_discount') ? 'COALESCE(manual_discount, 0)' : '0';

        return "{$amount} - {$discount} - {$manualDiscount}";
    }

    private function emptyReadingAdjustments(): object
    {
        return (object) [
            'credit_sales' => 0,
            'total_auto_discount' => 0,
            'total_manual_discount' => 0,
        ];
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

    public function backupDatabase()
    {
        try {
            $connectionName = config('database.default');
            $connection = config("database.connections.{$connectionName}");

            if (($connection['driver'] ?? null) !== 'mysql') {
                return back()->with('error', 'Backup is only available for MySQL connections.');
            }

            $dbName = $connection['database'] ?? null;
            $dbUser = $connection['username'] ?? null;
            $dbPass = $connection['password'] ?? null;
            $dbHost = $connection['host'] ?? '127.0.0.1';
            $dbPort = $connection['port'] ?? null;

            if (! $dbName || ! $dbUser) {
                return back()->with('error', 'Backup failed: Database credentials are incomplete.');
            }

            $phTime = Carbon::now('Asia/Manila');
            $formattedDate = $phTime->format('F j, Y h-ia');
            $safeDbName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $dbName);
            $filename = "backup-{$safeDbName}-{$formattedDate}.sql";
            $storagePath = storage_path('app/' . $filename);

            $command = [
                $this->mysqldumpBinary(),
                '--host=' . $dbHost,
                '--user=' . $dbUser,
            ];

            if ($dbPort) {
                $command[] = '--port=' . $dbPort;
            }

            if ($dbPass !== null && $dbPass !== '') {
                $command[] = '--password=' . $dbPass;
            }

            $command[] = '--result-file=' . $storagePath;
            $command[] = $dbName;

            $process = new Process($command);
            $process->setTimeout(180);
            $process->run();

            if (! $process->isSuccessful()) {
                $message = trim($process->getErrorOutput() ?: $process->getOutput());

                return back()->with('error', 'Backup Error: ' . ($message ?: 'mysqldump could not complete.'));
            }

            if (file_exists($storagePath)) {
                return response()->download($storagePath)->deleteFileAfterSend(true);
            }

            return back()->with('error', 'Backup failed: File could not be generated.');
        } catch (\Exception $e) {
            return back()->with('error', 'Backup Error: ' . $e->getMessage());
        }
    }

    private function mysqldumpBinary(): string
    {
        $candidates = array_filter([
            env('MYSQLDUMP_PATH'),
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'mysqldump',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump' || is_file($candidate)) {
                return $candidate;
            }
        }

        return 'mysqldump';
    }
}
