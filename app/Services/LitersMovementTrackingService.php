<?php

namespace App\Services;

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
    private const DEFAULT_FUEL_TYPES = ['Unleaded', 'Premium', 'Diesel'];
    private const DEFAULT_FUEL_LIMITS = [
        'regular' => 4000.0,
        'premium' => 4347.0,
        'diesel' => null,
    ];

    private array $tablePresence = [];
    private array $columnPresence = [];
    private ?array $fuelPrices = null;

    public function state(?int $staffId = null, int $perPage = 20, string $pageName = 'page'): array
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
        $this->attachUntrackedFuelSalesToPeriod($period);

        return [
            'period' => $period,
            'summary' => $this->summaryForPeriod($period, $staffId),
            'transactions' => $this->transactionsForPeriod($period, $staffId, $perPage, $pageName),
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

    public function activePeriodIdForNewFuelSale(): ?int
    {
        if (! $this->isReady()) {
            return null;
        }

        return (int) $this->ensureActivePeriod()->id;
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

            $this->attachUntrackedFuelSalesToPeriod($period);

            $transactions = $this->summaryRowsForPeriod($period, null, true);
            $endedAt = now();
            $summary = $this->summaryFromRows($period, $transactions);
            $transactionIds = $transactions->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $tankLimits = $this->storedTankLimitsForPeriod($period, $this->preparedTanks());
            $snapshot = [
                'period_code' => $period->period_code,
                'started_at' => $period->started_at,
                'ended_at' => $endedAt->toDateTimeString(),
                'liters_limit' => $summary['limit'],
                'tank_limits' => $tankLimits,
                'total_liters' => $summary['total_liters'],
                'total_amount' => $summary['total_amount'],
                'transaction_count' => $summary['transaction_count'],
                'fuel_breakdown' => $summary['fuel_breakdown'],
                'tank_breakdown' => $summary['tank_breakdown'],
                'staff_breakdown' => $summary['staff_breakdown'],
                'reached_limits' => $summary['reached_limits'],
                'transaction_ids' => $transactionIds,
                'generated_by' => $generatedBy,
                'generated_at' => $endedAt->toDateTimeString(),
            ];

            if ($transactionIds !== []) {
                DB::table('sales')
                    ->whereIn('id', $transactionIds)
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

    public function history(int $perPage = 10, string $pageName = 'history_page'): LengthAwarePaginator
    {
        if (! $this->hasTable('liters_tracking_periods')) {
            return $this->emptyPaginator($perPage, $pageName);
        }

        $query = DB::table('liters_tracking_periods')
            ->where('liters_tracking_periods.status', self::GENERATED_STATUS);

        if ($this->hasTable('users') && $this->hasColumn('users', 'name')) {
            $query->leftJoin('users', 'liters_tracking_periods.generated_by', '=', 'users.id')
                ->select('liters_tracking_periods.*', DB::raw("COALESCE(users.name, 'Admin') as generated_by_name"));
        } else {
            $query->select('liters_tracking_periods.*', DB::raw("'Admin' as generated_by_name"));
        }

        $paginator = $query
            ->orderByDesc('liters_tracking_periods.ended_at')
            ->orderByDesc('liters_tracking_periods.id')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();

        $paginator->getCollection()->transform(function ($period) {
            $period->snapshot = $this->decodeSnapshot($period->totals_snapshot ?? null);

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
                    'body' => number_format($liters, 2) . ' L recorded against ' . number_format($limit, 2) . ' L. Review and generate the record when ready.',
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

    private function attachUntrackedFuelSalesToPeriod(object $period): void
    {
        if (! $this->isReady() || empty($period->id)) {
            return;
        }

        $query = DB::table('sales')
            ->whereNull('liters_tracking_period_id');

        if ($this->hasColumn('sales', 'created_at') && ! empty($period->started_at)) {
            $query->where('created_at', '>=', $period->started_at);
        }

        $this->scopeToFuelSales($query);

        $query->update(['liters_tracking_period_id' => $period->id]);
    }

    private function summaryForPeriod(object $period, ?int $staffId): array
    {
        return $this->summaryFromRows($period, $this->summaryRowsForPeriod($period, $staffId));
    }

    private function summaryRowsForPeriod(object $period, ?int $staffId, bool $lock = false): Collection
    {
        $query = $this->fuelSalesQuery($period, $staffId);

        if ($this->hasTable('users') && $this->hasColumn('users', 'id')) {
            $query->leftJoin('users', 'sales.user_id', '=', 'users.id');
        }

        $staffExpression = $this->hasTable('users') && $this->hasColumn('users', 'name')
            ? "COALESCE(users.name, 'Staff')"
            : "'Staff'";

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->select(
                'sales.id',
                'sales.user_id',
                'sales.pump_id',
                'sales.fuel_type',
                'sales.liters',
                'sales.price_applied',
                'sales.amount',
                'sales.or_number',
                'sales.reference_no',
                'sales.created_at',
                DB::raw("{$staffExpression} as staff_name")
            )
            ->orderBy('sales.created_at')
            ->orderBy('sales.id')
            ->get();
    }

    private function summaryFromRows(object $period, Collection $rows): array
    {
        $tanks = $this->preparedTanks();
        $fuelBreakdown = $this->defaultFuelBreakdown($tanks);
        $tankBreakdown = $this->defaultTankBreakdown($period, $tanks);
        $pumpToTank = $this->pumpToTankMap($tanks);
        $fuelToTank = $this->uniqueFuelToTankMap($tanks);
        $staffBreakdown = [];
        $totalLiters = 0.0;
        $totalAmount = 0.0;

        foreach ($rows as $row) {
            $liters = $this->liters($row->liters ?? 0);
            $amount = $this->money($row->amount ?? 0);

            if ($liters <= 0) {
                continue;
            }

            $fuel = $this->normalizeFuelType($row->fuel_type ?? '');
            $fuelKey = $this->normalizeFuelKey($fuel);

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

            $staffKey = (int) ($row->user_id ?? 0);
            $staffIndex = $staffKey > 0 ? (string) $staffKey : 'unknown';

            if (! isset($staffBreakdown[$staffIndex])) {
                $staffBreakdown[$staffIndex] = [
                    'staff_id' => $staffKey ?: null,
                    'staff_name' => trim((string) ($row->staff_name ?? 'Staff')) ?: 'Staff',
                    'liters' => 0.0,
                    'amount' => 0.0,
                    'transaction_count' => 0,
                ];
            }

            $staffBreakdown[$staffIndex]['liters'] = $this->liters($staffBreakdown[$staffIndex]['liters'] + $liters);
            $staffBreakdown[$staffIndex]['amount'] = $this->money($staffBreakdown[$staffIndex]['amount'] + $amount);
            $staffBreakdown[$staffIndex]['transaction_count']++;

            $tankId = $pumpToTank[(int) ($row->pump_id ?? 0)] ?? ($fuelToTank[$fuelKey] ?? null);

            if (! $tankId || ! isset($tankBreakdown[$tankId])) {
                $tankId = 'unassigned';
                $this->ensureUnassignedTankBreakdown($tankBreakdown, [
                    'fuel_type' => $fuel,
                    'connected_pumps_display' => (int) ($row->pump_id ?? 0) > 0 ? 'P' . (int) $row->pump_id : 'No tank match',
                ]);
            }

            $price = $this->transactionPrice($row, $fuel, $liters, $amount);
            $tankBreakdown[$tankId]['liters'] = $this->liters($tankBreakdown[$tankId]['liters'] + $liters);
            $tankBreakdown[$tankId]['amount'] = $this->money($tankBreakdown[$tankId]['amount'] + $amount);
            $tankBreakdown[$tankId]['transaction_count']++;
            $tankBreakdown[$tankId]['entries'][] = [
                'id' => (int) ($row->id ?? 0),
                'created_at' => $row->created_at ?? null,
                'pump_id' => (int) ($row->pump_id ?? 0),
                'staff_name' => trim((string) ($row->staff_name ?? 'Staff')) ?: 'Staff',
                'receipt' => trim((string) ($row->or_number ?? '')),
                'reference' => trim((string) ($row->reference_no ?? '')),
                'liters' => $liters,
                'price_per_liter' => $price,
                'amount' => $amount,
            ];

            $totalLiters = $this->liters($totalLiters + $liters);
            $totalAmount = $this->money($totalAmount + $amount);
        }

        $tankRows = $this->formatTankBreakdown($tankBreakdown);
        $configuredLimit = $this->liters(collect($tankRows)->sum(fn ($tank) => (float) ($tank['limit'] ?? 0)));
        $percent = $configuredLimit > 0 ? round(($totalLiters / $configuredLimit) * 100, 2) : 0.0;
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

        return [
            'total_liters' => $totalLiters,
            'total_amount' => $totalAmount,
            'limit' => $configuredLimit,
            'configured_limit_count' => collect($tankRows)->filter(fn ($tank) => (bool) ($tank['has_limit'] ?? false))->count(),
            'remaining_liters' => $configuredLimit > 0 ? $this->liters(max(0, $configuredLimit - $totalLiters)) : 0.0,
            'over_liters' => $configuredLimit > 0 ? $this->liters(max(0, $totalLiters - $configuredLimit)) : 0.0,
            'percent' => $percent,
            'progress_width' => min(100, max(0, $percent)),
            'transaction_count' => $rows->count(),
            'fuel_breakdown' => array_values($fuelBreakdown),
            'tank_breakdown' => $tankRows,
            'staff_breakdown' => collect($staffBreakdown)->sortByDesc('liters')->values()->all(),
            'reached_limits' => $reachedLimits,
            'status' => $status,
        ];
    }

    private function transactionsForPeriod(object $period, ?int $staffId, int $perPage, string $pageName): LengthAwarePaginator
    {
        $query = $this->fuelSalesQuery($period, $staffId);
        $this->joinTransactionContext($query);

        return $query
            ->select($this->transactionSelects())
            ->orderByDesc('sales.created_at')
            ->orderByDesc('sales.id')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }

    private function fuelSalesQuery(object $period, ?int $staffId)
    {
        $query = DB::table('sales')
            ->where('sales.liters_tracking_period_id', (int) $period->id);

        $this->scopeToFuelSales($query);

        if ($staffId !== null && $this->hasColumn('sales', 'user_id')) {
            $query->where('sales.user_id', $staffId);
        }

        return $query;
    }

    private function scopeToFuelSales($query): void
    {
        if ($this->hasColumn('sales', 'payment_type')) {
            $query->where(function ($query) {
                $query->whereNull('sales.payment_type')
                    ->orWhereRaw('LOWER(sales.payment_type) != ?', ['collection']);
            });
        }

        if ($this->hasColumn('sales', 'liters')) {
            $query->where('sales.liters', '>', 0);
        }
    }

    private function joinTransactionContext($query): void
    {
        if ($this->hasTable('users') && $this->hasColumn('users', 'id')) {
            $query->leftJoin('users', 'sales.user_id', '=', 'users.id');
        }

        if ($this->hasTable('customers') && $this->hasColumn('sales', 'customer_id')) {
            $query->leftJoin('customers', 'sales.customer_id', '=', 'customers.id');
        }

        if ($this->hasTable('customers') && $this->hasColumn('sales', 'posted_customer_id')) {
            $query->leftJoin('customers as posted_customers', 'sales.posted_customer_id', '=', 'posted_customers.id');
        }
    }

    private function transactionSelects(): array
    {
        $customerParts = [];

        if ($this->hasTable('customers') && $this->hasColumn('sales', 'customer_id')) {
            if ($this->hasColumn('customers', 'business_name')) {
                $customerParts[] = 'customers.business_name';
            }

            if ($this->hasColumn('customers', 'owner_name')) {
                $customerParts[] = 'customers.owner_name';
            }
        }

        if ($this->hasTable('customers') && $this->hasColumn('sales', 'posted_customer_id')) {
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

        $customerExpression = $customerParts
            ? 'COALESCE(' . implode(', ', $customerParts) . ", 'Walk-In Customer')"
            : "'Walk-In Customer'";
        $staffExpression = $this->hasTable('users') && $this->hasColumn('users', 'name')
            ? "COALESCE(users.name, 'Staff')"
            : "'Staff'";

        return [
            'sales.*',
            DB::raw("{$staffExpression} as staff_name"),
            DB::raw("{$customerExpression} as customer_display_name"),
        ];
    }

    private function emptySummary(?object $period): array
    {
        $tanks = $this->preparedTanks();
        $tankBreakdown = $this->formatTankBreakdown($this->defaultTankBreakdown($period, $tanks));
        $limit = $this->liters(collect($tankBreakdown)->sum(fn ($tank) => (float) ($tank['limit'] ?? 0)));

        return [
            'total_liters' => 0.0,
            'total_amount' => 0.0,
            'limit' => $limit,
            'configured_limit_count' => collect($tankBreakdown)->filter(fn ($tank) => (bool) ($tank['has_limit'] ?? false))->count(),
            'remaining_liters' => $limit,
            'over_liters' => 0.0,
            'percent' => 0.0,
            'progress_width' => 0.0,
            'transaction_count' => 0,
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
                $liters = $this->liters($tank['liters'] ?? 0);
                $amount = $this->money($tank['amount'] ?? 0);
                $limitValue = $hasLimit ? $this->liters($limit) : null;
                $percent = $hasLimit ? round(($liters / $limitValue) * 100, 2) : 0.0;

                $tank['liters'] = $liters;
                $tank['amount'] = $amount;
                $tank['price_per_liter'] = $this->money($tank['price_per_liter'] ?? 0);
                $tank['limit'] = $limitValue;
                $tank['has_limit'] = $hasLimit;
                $tank['remaining_liters'] = $hasLimit ? $this->liters(max(0, $limitValue - $liters)) : 0.0;
                $tank['over_liters'] = $hasLimit ? $this->liters(max(0, $liters - $limitValue)) : 0.0;
                $tank['percent'] = $percent;
                $tank['progress_width'] = min(100, max(0, $percent));
                $tank['transaction_count'] = (int) ($tank['transaction_count'] ?? 0);
                $tank['entries'] = collect($tank['entries'] ?? [])->sortBy('created_at')->values()->all();
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

        if (! $this->hasTable('fuel_prices')) {
            return $this->fuelPrices = [];
        }

        return $this->fuelPrices = DB::table('fuel_prices')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $this->normalizeFuelKey($row->fuel_type ?? '') => $this->money($row->price_per_liter ?? 0),
            ])
            ->all();
    }

    private function transactionPrice($row, string $fuelType, float $liters, float $amount): float
    {
        $price = (float) ($row->price_applied ?? 0);

        if ($price <= 0 && $liters > 0 && $amount > 0) {
            $price = $amount / $liters;
        }

        if ($price <= 0) {
            $price = $this->priceForFuel($fuelType);
        }

        return $this->money($price);
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

    private function isReady(): bool
    {
        return $this->hasTable('liters_tracking_periods')
            && $this->hasTable('sales')
            && $this->hasTable('tanks')
            && $this->hasTable('fuel_prices')
            && $this->hasColumn('sales', 'liters_tracking_period_id')
            && $this->hasColumn('sales', 'user_id')
            && $this->hasColumn('sales', 'pump_id')
            && $this->hasColumn('sales', 'liters')
            && $this->hasColumn('sales', 'fuel_type')
            && $this->hasColumn('sales', 'amount')
            && $this->hasColumn('sales', 'created_at')
            && $this->hasColumn('tanks', 'id')
            && $this->hasColumn('tanks', 'fuel_type')
            && $this->hasColumn('tanks', 'connected_pumps')
            && $this->hasColumn('fuel_prices', 'fuel_type')
            && $this->hasColumn('fuel_prices', 'price_per_liter');
    }

    private function missingRequirements(): array
    {
        $missing = [];

        foreach (['liters_tracking_periods', 'sales', 'tanks', 'fuel_prices'] as $table) {
            if (! $this->hasTable($table)) {
                $missing[] = $table;
            }
        }

        if (! $this->hasTable('sales')) {
            return $missing;
        }

        foreach (['liters_tracking_period_id', 'user_id', 'pump_id', 'liters', 'fuel_type', 'amount', 'created_at'] as $column) {
            if (! $this->hasColumn('sales', $column)) {
                $missing[] = "sales.{$column}";
            }
        }

        if ($this->hasTable('tanks')) {
            foreach (['id', 'fuel_type', 'connected_pumps'] as $column) {
                if (! $this->hasColumn('tanks', $column)) {
                    $missing[] = "tanks.{$column}";
                }
            }
        }

        if ($this->hasTable('fuel_prices')) {
            foreach (['fuel_type', 'price_per_liter'] as $column) {
                if (! $this->hasColumn('fuel_prices', $column)) {
                    $missing[] = "fuel_prices.{$column}";
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
