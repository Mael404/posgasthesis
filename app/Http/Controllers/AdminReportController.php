<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    private const DEFAULT_FUEL_BY_PUMP = [
        1 => 'Premium',
        2 => 'Unleaded',
        3 => 'Premium',
        4 => 'Unleaded',
        5 => 'Diesel',
        6 => 'Diesel',
    ];

    private const DEFAULT_FUEL_TYPES = ['Diesel', 'Unleaded', 'Premium'];

    private array $lockedSalesPresence = [];

    private array $tablePresence = [];

    private array $columnPresence = [];

    public function index(Request $request): View
    {
        return $this->reportView($request, 'Transaction Logs');
    }

    public function print(Request $request): View
    {
        return $this->reportView($request, 'Print Transaction Logs');
    }

    private function reportView(Request $request, string $title): View
    {
        [$rangeStart, $rangeEnd] = $this->dateRange($request);
        $start = $rangeStart->toDateString();
        $end = $rangeEnd->toDateString();
        $readingStatus = $this->readingStatus($request);
        $staffId = $this->staffId($request);
        $staffOptions = $this->staffOptions();
        $shiftReadings = $this->shiftReadingTransactions($rangeStart, $rangeEnd, $readingStatus, $staffId);
        $transactions = $this->individualSalesTransactions($shiftReadings, $rangeStart, $rangeEnd, $staffId);
        $transactionTotals = $this->individualTransactionTotals($transactions);
        $perPage = 10;
        $page = max(1, min(
            $request->integer('transaction_page', 1),
            (int) ceil($transactions->count() / $perPage)
        ));
        $transactions = (new LengthAwarePaginator(
            $transactions->forPage($page, $perPage)->values(),
            $transactions->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'transaction_page']
        ))->appends($request->query())->fragment('transaction-log');
        $reportData = $this->salesOverview($shiftReadings);
        $tanks = $this->tanksByFuelType();
        $salesOut = $this->salesOutByFuelType($reportData);
        $totalGrossSales = $this->money($shiftReadings->sum('gross_sales'));
        $totalDiscounts = $this->money($shiftReadings->sum('discounts'));
        $totalNetSales = $this->money($shiftReadings->sum('net_sales'));
        $totalLiters = $this->liters($shiftReadings->sum('liters'));

        return view('pages.admin.transactionlogs', compact(
            'start',
            'end',
            'readingStatus',
            'staffId',
            'staffOptions',
            'shiftReadings',
            'transactions',
            'transactionTotals',
            'reportData',
            'tanks',
            'salesOut',
            'totalGrossSales',
            'totalDiscounts',
            'totalNetSales',
            'totalLiters',
            'title'
        ));
    }

    private function dateRange(Request $request): array
    {
        $defaultStart = Carbon::now()->startOfMonth();
        $defaultEnd = Carbon::now()->endOfMonth();

        try {
            $start = Carbon::parse($request->input('start_date', $defaultStart->toDateString()))->startOfDay();
            $end = Carbon::parse($request->input('end_date', $defaultEnd->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $start = $defaultStart->copy()->startOfDay();
            $end = $defaultEnd->copy()->endOfDay();
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    private function readingStatus(Request $request): string
    {
        $status = strtolower((string) $request->input('reading_status', 'all'));

        return in_array($status, ['pending', 'approved', 'all'], true) ? $status : 'all';
    }

    private function staffId(Request $request): ?int
    {
        $staffId = filter_var($request->input('staff_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $staffId === false ? null : $staffId;
    }

    private function shiftReadingTransactions(
        Carbon $start,
        Carbon $end,
        string $readingStatus,
        ?int $staffId = null
    ): Collection {
        $query = $this->shiftReadingsForRange($start, $end);

        if (! $query) {
            return collect();
        }

        $this->applyShiftReadingStatusFilter($query, $readingStatus);

        if ($staffId && $this->hasColumn('shift_readings', 'user_id')) {
            $query->where('shift_readings.user_id', $staffId);
        }

        $canJoinUsers = $this->hasTable('users')
            && $this->hasColumn('shift_readings', 'user_id')
            && $this->hasColumn('users', 'id')
            && $this->hasColumn('users', 'name');

        if ($canJoinUsers) {
            $query->leftJoin('users', 'shift_readings.user_id', '=', 'users.id');
        }

        $readings = $query
            ->select('shift_readings.*')
            ->selectRaw($canJoinUsers ? 'users.name as staff_name' : "'Staff' as staff_name")
            ->when($this->hasColumn('shift_readings', 'period_from'), fn ($query) => $query->orderByDesc('shift_readings.period_from'))
            ->when($this->hasColumn('shift_readings', 'created_at'), fn ($query) => $query->orderByDesc('shift_readings.created_at'))
            ->when($this->hasColumn('shift_readings', 'id'), fn ($query) => $query->orderByDesc('shift_readings.id'))
            ->get();

        return $readings->map(function ($reading) {
            $previousVerified = $this->previousVerifiedReading($reading);
            $fuelTotals = $this->readingFuelTotals($reading, $previousVerified);
            $discountsByFuel = $this->readingDiscountsByFuel($reading);

            foreach ($discountsByFuel as $fuelType => $discounts) {
                if (! isset($fuelTotals[$fuelType])) {
                    $fuelTotals[$fuelType] = $this->emptyFuelTotals();
                }

                $fuelTotals[$fuelType]['discounts'] += (float) $discounts;
            }

            $fuelTotals = collect($fuelTotals)
                ->map(function ($totals) {
                    $totals['gross_sales'] = $this->money($totals['gross_sales'] ?? 0);
                    $totals['discounts'] = $this->money($totals['discounts'] ?? 0);
                    $totals['net_sales'] = $this->money($totals['gross_sales'] - $totals['discounts']);
                    $totals['liters'] = $this->liters($totals['liters'] ?? 0);

                    return $totals;
                })
                ->all();

            $grossSales = $this->money(collect($fuelTotals)->sum('gross_sales'));
            $discounts = $this->money(collect($fuelTotals)->sum('discounts'));

            return (object) [
                'id' => (int) ($reading->id ?? 0),
                'user_id' => (int) ($reading->user_id ?? 0),
                'staff_name' => $reading->staff_name ?? 'Staff',
                'period_from' => $reading->period_from ?? ($reading->created_at ?? null),
                'period_to' => $reading->period_to ?? ($reading->period_from ?? ($reading->created_at ?? null)),
                'status' => $this->displayReadingStatus($reading->status ?? null),
                'gross_sales' => $grossSales,
                'discounts' => $discounts,
                'net_sales' => $this->money($grossSales - $discounts),
                'liters' => $this->liters(collect($fuelTotals)->sum('liters')),
                'fuel_totals' => $fuelTotals,
            ];
        });
    }

    private function staffOptions(): Collection
    {
        if (
            ! $this->hasTable('users')
            || ! $this->hasTable('shift_readings')
            || ! $this->hasColumn('users', 'id')
            || ! $this->hasColumn('users', 'name')
            || ! $this->hasColumn('shift_readings', 'user_id')
        ) {
            return collect();
        }

        $query = DB::table('users')
            ->join('shift_readings', 'users.id', '=', 'shift_readings.user_id')
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name');

        if ($this->hasColumn('shift_readings', 'status')) {
            $query->where(function ($statuses) {
                $statuses->whereNull('shift_readings.status')
                    ->orWhereRaw(
                        'LOWER(TRIM(shift_readings.status)) IN (?, ?, ?)',
                        ['pending', 'verified', 'approved']
                    );
            });
        }

        return $query->get()->map(function ($staff) {
            $staff->id = (int) $staff->id;

            return $staff;
        });
    }

    private function individualSalesTransactions(
        Collection $shiftReadings,
        Carbon $start,
        Carbon $end,
        ?int $staffId = null
    ): Collection {
        if (! $this->hasTable('sales') || $shiftReadings->isEmpty()) {
            return collect();
        }

        $query = DB::table('sales');
        $canJoinStaff = $this->hasTable('users')
            && $this->hasColumn('sales', 'user_id')
            && $this->hasColumn('users', 'id')
            && $this->hasColumn('users', 'name');
        $customerParts = [];

        if ($canJoinStaff) {
            $query->leftJoin('users', 'sales.user_id', '=', 'users.id');
        }

        if (
            $this->hasTable('customers')
            && $this->hasColumn('customers', 'id')
            && $this->hasColumn('sales', 'customer_id')
        ) {
            $query->leftJoin('customers', 'sales.customer_id', '=', 'customers.id');

            if ($this->hasColumn('customers', 'business_name')) {
                $customerParts[] = 'customers.business_name';
            }

            if ($this->hasColumn('customers', 'owner_name')) {
                $customerParts[] = 'customers.owner_name';
            }
        }

        if (
            $this->hasTable('customers')
            && $this->hasColumn('customers', 'id')
            && $this->hasColumn('sales', 'posted_customer_id')
        ) {
            $query->leftJoin('customers as posted_customers', 'sales.posted_customer_id', '=', 'posted_customers.id');

            if ($this->hasColumn('customers', 'business_name')) {
                $customerParts[] = 'posted_customers.business_name';
            }

            if ($this->hasColumn('customers', 'owner_name')) {
                $customerParts[] = 'posted_customers.owner_name';
            }
        }

        if ($this->hasColumn('sales', 'customer_name')) {
            $customerParts[] = 'sales.customer_name';
        }

        $query
            ->select('sales.*')
            ->selectRaw($canJoinStaff ? 'users.name as staff_name' : 'NULL as staff_name')
            ->selectRaw($this->coalesceExpression($customerParts, "'Walk-In Customer'").' as customer_display');

        $query->where(function ($eligibleSales) use ($shiftReadings) {
            foreach ($shiftReadings as $reading) {
                $eligibleSales->orWhere(function ($readingSales) use ($reading) {
                    if ($this->readingHasLockedSales($reading)) {
                        $readingSales->where('sales.locked_by_shift_reading_id', $reading->id);

                        return;
                    }

                    if ($this->hasColumn('sales', 'locked_by_shift_reading_id')) {
                        $readingSales->whereNull('sales.locked_by_shift_reading_id');
                    }

                    if ($this->hasColumn('sales', 'user_id') && $reading->user_id > 0) {
                        $readingSales->where('sales.user_id', $reading->user_id);
                    }

                    if ($this->hasColumn('sales', 'created_at') && $reading->period_from) {
                        $periodStart = Carbon::parse($reading->period_from)->startOfDay();
                        $periodEnd = Carbon::parse($reading->period_to ?: $reading->period_from)->endOfDay();
                        $readingSales->whereBetween('sales.created_at', [$periodStart, $periodEnd]);
                    }
                });
            }
        });

        if ($this->hasColumn('sales', 'created_at')) {
            $query->whereBetween('sales.created_at', [$start, $end])
                ->orderByDesc('sales.created_at');
        }

        if ($staffId && $this->hasColumn('sales', 'user_id')) {
            $query->where('sales.user_id', $staffId);
        }

        if ($this->hasColumn('sales', 'customer_payment_voided_at')) {
            $query->whereNull('sales.customer_payment_voided_at');
        }

        if ($this->hasColumn('sales', 'id')) {
            $query->orderByDesc('sales.id');
        }

        return $query->get()->map(function ($sale) use ($shiftReadings) {
            $reading = $this->matchingShiftReading($shiftReadings, $sale);
            $customerDisplay = trim((string) ($sale->customer_display ?? ''));
            $paymentType = strtolower(trim((string) ($sale->payment_type ?? '')));
            $discounts = (float) ($sale->discount ?? 0) + (float) ($sale->manual_discount ?? 0);

            $sale->shift_reading_id = (int) ($sale->locked_by_shift_reading_id ?? ($reading->id ?? 0));
            $sale->staff_name = trim((string) ($sale->staff_name ?? '')) ?: ($reading->staff_name ?? 'Staff');
            $sale->customer_display = $customerDisplay !== '' ? $customerDisplay : 'Walk-In Customer';
            $sale->reference_no = trim((string) ($sale->reference_no ?? '')) ?: 'TXN-'.($sale->id ?? '0');
            $sale->or_number = trim((string) ($sale->or_number ?? ''));
            $sale->pump_label = (int) ($sale->pump_id ?? 0) > 0 ? 'Pump '.(int) $sale->pump_id : 'N/A';
            $sale->product_label = $paymentType === 'collection'
                ? 'Account Payment'
                : (trim((string) ($sale->fuel_type ?? '')) ?: 'N/A');
            $sale->payment_label = match ($paymentType) {
                'credit' => 'Credit Sale',
                'collection' => 'Collection',
                default => 'Cash / Walk-In',
            };
            $sale->liters = $this->liters($sale->liters ?? 0);
            $sale->discounts = $this->money($discounts);
            $sale->amount = $this->money($sale->amount ?? 0);

            return $sale;
        });
    }

    private function matchingShiftReading(Collection $shiftReadings, object $sale): ?object
    {
        $lockedReadingId = (int) ($sale->locked_by_shift_reading_id ?? 0);

        if ($lockedReadingId > 0) {
            return $shiftReadings->first(fn ($reading) => $reading->id === $lockedReadingId);
        }

        if (empty($sale->created_at)) {
            return null;
        }

        $saleDate = Carbon::parse($sale->created_at);

        return $shiftReadings->first(function ($reading) use ($sale, $saleDate) {
            if ($reading->user_id > 0 && isset($sale->user_id) && $reading->user_id !== (int) $sale->user_id) {
                return false;
            }

            if (! $reading->period_from) {
                return false;
            }

            $periodStart = Carbon::parse($reading->period_from)->startOfDay();
            $periodEnd = Carbon::parse($reading->period_to ?: $reading->period_from)->endOfDay();

            return $saleDate->betweenIncluded($periodStart, $periodEnd);
        });
    }

    private function individualTransactionTotals(Collection $transactions): array
    {
        return [
            'records' => $transactions->count(),
            'liters' => $this->liters($transactions->sum('liters')),
            'discounts' => $this->money($transactions->sum('discounts')),
            'amount' => $this->money($transactions->sum('amount')),
        ];
    }

    private function shiftReadingsForRange(Carbon $start, Carbon $end)
    {
        if (! $this->hasTable('shift_readings')) {
            return null;
        }

        $query = DB::table('shift_readings');

        if ($this->hasColumn('shift_readings', 'period_from')) {
            $query->whereDate('shift_readings.period_from', '<=', $end->toDateString());

            if ($this->hasColumn('shift_readings', 'period_to')) {
                return $query->where(function ($query) use ($start) {
                    $query->whereDate('shift_readings.period_to', '>=', $start->toDateString())
                        ->orWhere(function ($nullPeriodTo) use ($start) {
                            $nullPeriodTo->whereNull('shift_readings.period_to')
                                ->whereDate('shift_readings.period_from', '>=', $start->toDateString());
                        });
                });
            }

            return $query->whereDate('shift_readings.period_from', '>=', $start->toDateString());
        }

        if ($this->hasColumn('shift_readings', 'created_at')) {
            $query->whereBetween('shift_readings.created_at', [$start, $end]);
        }

        return $query;
    }

    private function applyShiftReadingStatusFilter($query, string $readingStatus): void
    {
        if (! $this->hasColumn('shift_readings', 'status')) {
            if ($readingStatus === 'approved') {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $normalizedStatus = 'LOWER(TRIM(shift_readings.status))';

        if ($readingStatus === 'pending') {
            $query->where(function ($query) use ($normalizedStatus) {
                $query->whereNull('shift_readings.status')
                    ->orWhereRaw("{$normalizedStatus} = ?", ['pending']);
            });

            return;
        }

        if ($readingStatus === 'approved') {
            $query->whereRaw("{$normalizedStatus} IN (?, ?)", ['verified', 'approved']);

            return;
        }

        $query->where(function ($query) use ($normalizedStatus) {
            $query->whereNull('shift_readings.status')
                ->orWhereRaw("{$normalizedStatus} IN (?, ?, ?)", ['pending', 'verified', 'approved']);
        });
    }

    private function readingFuelTotals(object $reading, ?object $previousVerified): array
    {
        $totals = collect(self::DEFAULT_FUEL_TYPES)
            ->mapWithKeys(fn ($fuelType) => [$fuelType => $this->emptyFuelTotals()])
            ->all();

        foreach (self::DEFAULT_FUEL_BY_PUMP as $pump => $fuelType) {
            $salesStartField = "pump{$pump}_sales_start";
            $salesEndField = "pump{$pump}_sales_end";
            $litersStartField = "pump{$pump}_liters_start";
            $litersEndField = "pump{$pump}_liters_end";
            $previousSalesEnd = $previousVerified ? (float) ($previousVerified->$salesEndField ?? 0) : 0;
            $previousLitersEnd = $previousVerified ? (float) ($previousVerified->$litersEndField ?? 0) : 0;
            $salesStart = $reading->$salesStartField ?? $previousSalesEnd;
            $litersStart = $reading->$litersStartField ?? $previousLitersEnd;

            $totals[$fuelType]['gross_sales'] += (float) ($reading->$salesEndField ?? 0) - (float) $salesStart;
            $totals[$fuelType]['liters'] += max(0, (float) ($reading->$litersEndField ?? 0) - (float) $litersStart);
        }

        return $totals;
    }

    private function readingDiscountsByFuel(object $reading): array
    {
        if (! $this->hasTable('sales')) {
            return [];
        }

        $discountExpression = $this->hasColumn('sales', 'discount')
            ? 'COALESCE(SUM(discount), 0)'
            : '0';
        $manualDiscountExpression = $this->hasColumn('sales', 'manual_discount')
            ? 'COALESCE(SUM(manual_discount), 0)'
            : '0';

        if ($discountExpression === '0' && $manualDiscountExpression === '0') {
            return [];
        }

        $periodStart = Carbon::parse($reading->period_from ?? $reading->created_at)->startOfDay();
        $periodEnd = Carbon::parse($reading->period_to ?? $reading->period_from ?? $reading->created_at)->endOfDay();
        $pumpExpression = $this->hasColumn('sales', 'pump_id') ? 'COALESCE(pump_id, 0)' : '0';
        $fuelExpression = $this->hasColumn('sales', 'fuel_type') ? 'MAX(fuel_type)' : "'N/A'";
        $query = $this->scopeSalesToReading(DB::table('sales'), $reading, $periodStart, $periodEnd)
            ->selectRaw("\n                {$pumpExpression} as pump_id,\n                {$fuelExpression} as fuel_type,\n                {$discountExpression} + {$manualDiscountExpression} as discounts\n            ");

        if ($this->hasColumn('sales', 'pump_id')) {
            $query->groupByRaw($pumpExpression);
        }

        return $query
            ->get()
            ->reduce(function (array $discounts, $row) {
                $pumpId = (int) ($row->pump_id ?? 0);
                $fuelType = self::DEFAULT_FUEL_BY_PUMP[$pumpId]
                    ?? $this->normalizeFuelType($row->fuel_type ?? 'N/A');
                $discounts[$fuelType] = ($discounts[$fuelType] ?? 0) + (float) ($row->discounts ?? 0);

                return $discounts;
            }, []);
    }

    private function scopeSalesToReading($query, object $reading, Carbon $periodStart, Carbon $periodEnd)
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

    private function readingHasLockedSales(object $reading): bool
    {
        if (
            ! isset($reading->id)
            || ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'locked_by_shift_reading_id')
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

    private function previousVerifiedReading(object $reading): ?object
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
                    ->orWhere(function ($query) use ($reading) {
                        $query->whereDate('period_to', '=', $reading->period_from)
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

    private function salesOverview(Collection $transactions): array
    {
        $reportData = collect(self::DEFAULT_FUEL_TYPES)
            ->mapWithKeys(fn ($fuelType) => [$fuelType => $this->emptyFuelTotals()])
            ->all();

        foreach ($transactions as $transaction) {
            foreach ($transaction->fuel_totals as $fuelType => $totals) {
                if (! isset($reportData[$fuelType])) {
                    $reportData[$fuelType] = $this->emptyFuelTotals();
                }

                foreach (['gross_sales', 'discounts', 'net_sales', 'liters'] as $metric) {
                    $reportData[$fuelType][$metric] += (float) ($totals[$metric] ?? 0);
                }
            }
        }

        return collect($reportData)
            ->map(function ($totals) {
                $totals['gross_sales'] = $this->money($totals['gross_sales']);
                $totals['discounts'] = $this->money($totals['discounts']);
                $totals['net_sales'] = $this->money($totals['net_sales']);
                $totals['liters'] = $this->liters($totals['liters']);

                return $totals;
            })
            ->all();
    }

    private function emptyFuelTotals(): array
    {
        return [
            'gross_sales' => 0.0,
            'discounts' => 0.0,
            'net_sales' => 0.0,
            'liters' => 0.0,
        ];
    }

    private function tanksByFuelType(): Collection
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
            ->mapWithKeys(function ($tank) {
                $fuelType = $this->normalizeFuelType($tank->fuel_type ?? ($tank->name ?? 'Tank'));

                return [
                    $fuelType => (object) [
                        'fuel_type' => $fuelType,
                        'name' => $tank->name ?? $fuelType,
                        'current_volume' => $this->liters($tank->current_volume ?? 0),
                        'capacity' => $this->liters($tank->capacity ?? 0),
                    ],
                ];
            });
    }

    private function salesOutByFuelType(array $reportData): Collection
    {
        return collect($reportData)
            ->map(fn ($totals, $fuelType) => (object) [
                'fuel_type' => $fuelType,
                'total_liters' => $this->liters($totals['liters'] ?? 0),
            ])
            ->filter(fn ($row) => in_array($row->fuel_type, self::DEFAULT_FUEL_TYPES, true));
    }

    private function displayReadingStatus($status): string
    {
        $status = strtolower(trim((string) $status));

        if (in_array($status, ['verified', 'approved'], true)) {
            return 'Approved';
        }

        return 'Pending';
    }

    private function normalizeFuelType($fuelType): string
    {
        $value = trim((string) $fuelType);
        $lower = strtolower($value);

        if (str_contains($lower, 'diesel')) {
            return 'Diesel';
        }

        if (str_contains($lower, 'unleaded') || str_contains($lower, 'regular')) {
            return 'Unleaded';
        }

        if (str_contains($lower, 'premium')) {
            return 'Premium';
        }

        return $value !== '' ? ucfirst($value) : 'N/A';
    }

    private function coalesceExpression(array $expressions, string $fallback = "''"): string
    {
        $parts = array_values(array_filter($expressions));

        if ($parts === []) {
            return $fallback;
        }

        $parts[] = $fallback;

        return 'COALESCE('.implode(', ', $parts).')';
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
