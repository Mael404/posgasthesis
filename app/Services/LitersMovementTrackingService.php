<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LitersMovementTrackingService
{
    private const DEFAULT_LIMIT = 4000.0;
    private const ACTIVE_STATUS = 'active';
    private const GENERATED_STATUS = 'generated';
    private const DEFAULT_FUEL_TYPES = ['Regular', 'Premium', 'Diesel'];

    private array $tablePresence = [];
    private array $columnPresence = [];

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

    public function ensureActivePeriod(?float $limit = null): object
    {
        $period = $this->activePeriod();

        if ($period) {
            return $period;
        }

        $periodId = $this->createPeriod($limit ?? self::DEFAULT_LIMIT);

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
        if (! $this->isReady()) {
            throw new \RuntimeException('Liters tracking is not migrated yet. Run php artisan migrate.');
        }

        return DB::transaction(function () use ($limit) {
            $period = DB::table('liters_tracking_periods')
                ->where('status', self::ACTIVE_STATUS)
                ->lockForUpdate()
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first();

            if (! $period) {
                $period = DB::table('liters_tracking_periods')
                    ->where('id', $this->createPeriod($limit))
                    ->first();
            }

            DB::table('liters_tracking_periods')->where('id', $period->id)->update([
                'liters_limit' => round($limit, 2),
                'updated_at' => now(),
            ]);

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
            $snapshot = [
                'period_code' => $period->period_code,
                'started_at' => $period->started_at,
                'ended_at' => $endedAt->toDateTimeString(),
                'liters_limit' => $summary['limit'],
                'total_liters' => $summary['total_liters'],
                'total_amount' => $summary['total_amount'],
                'transaction_count' => $summary['transaction_count'],
                'fuel_breakdown' => $summary['fuel_breakdown'],
                'staff_breakdown' => $summary['staff_breakdown'],
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

            $newPeriodId = $this->createPeriod((float) $period->liters_limit, $endedAt);

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

        if (! $period || (float) ($period->liters_limit ?? 0) <= 0) {
            return 0;
        }

        $query = $this->fuelSalesQuery($period, null);
        $totalLiters = (float) $query->sum('sales.liters');

        return $totalLiters >= (float) $period->liters_limit ? 1 : 0;
    }

    private function createPeriod(float $limit, $startedAt = null): int
    {
        $startedAt ??= now();

        return (int) DB::table('liters_tracking_periods')->insertGetId([
            'period_code' => $this->generatePeriodCode(),
            'liters_limit' => round($limit, 2),
            'status' => self::ACTIVE_STATUS,
            'started_at' => $startedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
                'sales.fuel_type',
                'sales.liters',
                'sales.amount',
                DB::raw("{$staffExpression} as staff_name")
            )
            ->orderBy('sales.created_at')
            ->orderBy('sales.id')
            ->get();
    }

    private function summaryFromRows(object $period, Collection $rows): array
    {
        $fuelBreakdown = $this->defaultFuelBreakdown();
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

            $totalLiters = $this->liters($totalLiters + $liters);
            $totalAmount = $this->money($totalAmount + $amount);
        }

        $limit = $this->liters($period->liters_limit ?? self::DEFAULT_LIMIT);
        $percent = $limit > 0 ? round(($totalLiters / $limit) * 100, 2) : 0.0;
        $status = $this->statusFor($totalLiters, $limit);

        return [
            'total_liters' => $totalLiters,
            'total_amount' => $totalAmount,
            'limit' => $limit,
            'remaining_liters' => $this->liters(max(0, $limit - $totalLiters)),
            'over_liters' => $this->liters(max(0, $totalLiters - $limit)),
            'percent' => $percent,
            'progress_width' => min(100, max(0, $percent)),
            'transaction_count' => $rows->count(),
            'fuel_breakdown' => array_values($fuelBreakdown),
            'staff_breakdown' => collect($staffBreakdown)->sortByDesc('liters')->values()->all(),
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
        $limit = $this->liters($period->liters_limit ?? self::DEFAULT_LIMIT);

        return [
            'total_liters' => 0.0,
            'total_amount' => 0.0,
            'limit' => $limit,
            'remaining_liters' => $limit,
            'over_liters' => 0.0,
            'percent' => 0.0,
            'progress_width' => 0.0,
            'transaction_count' => 0,
            'fuel_breakdown' => array_values($this->defaultFuelBreakdown()),
            'staff_breakdown' => [],
            'status' => $this->statusFor(0, $limit),
        ];
    }

    private function defaultFuelBreakdown(): array
    {
        return collect(self::DEFAULT_FUEL_TYPES)
            ->mapWithKeys(fn ($fuel) => [$fuel => [
                'fuel_type' => $fuel,
                'liters' => 0.0,
                'amount' => 0.0,
                'transaction_count' => 0,
            ]])
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
            'label' => 'Normal',
            'badge_class' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
            'progress_class' => 'bg-success-500',
            'border_class' => 'border-success-200 bg-success-50/60 dark:border-success-500/25 dark:bg-success-500/10',
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
            return 'Regular';
        }

        return trim((string) $fuelType) !== '' ? ucfirst((string) $fuelType) : 'N/A';
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
            && $this->hasColumn('sales', 'liters_tracking_period_id')
            && $this->hasColumn('sales', 'user_id')
            && $this->hasColumn('sales', 'liters')
            && $this->hasColumn('sales', 'fuel_type')
            && $this->hasColumn('sales', 'amount')
            && $this->hasColumn('sales', 'created_at');
    }

    private function missingRequirements(): array
    {
        $missing = [];

        if (! $this->hasTable('liters_tracking_periods')) {
            $missing[] = 'liters_tracking_periods';
        }

        if (! $this->hasTable('sales')) {
            $missing[] = 'sales';

            return $missing;
        }

        foreach (['liters_tracking_period_id', 'user_id', 'liters', 'fuel_type', 'amount', 'created_at'] as $column) {
            if (! $this->hasColumn('sales', $column)) {
                $missing[] = "sales.{$column}";
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
