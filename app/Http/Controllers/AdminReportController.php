<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    private const DEFAULT_FUEL_TYPES = ['Diesel', 'Unleaded', 'Premium'];

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

        $reportData = $this->salesOverview($rangeStart, $rangeEnd);
        $tanks = $this->tanksByFuelType();
        $salesOut = $this->salesOutByFuelType($rangeStart, $rangeEnd);
        $financials = $this->financialInflow($rangeStart, $rangeEnd);

        $totalCash = $this->money(
            $financials
                ->where('category', 'Cash Sales')
                ->sum('amount')
        );
        $totalCredit = $this->money(
            $financials
                ->where('category', 'Credit Payment')
                ->sum('amount')
        );
        $totalInflow = $this->money($totalCash + $totalCredit);

        return view('pages.admin.transactionlogs', compact(
            'start',
            'end',
            'reportData',
            'tanks',
            'salesOut',
            'financials',
            'totalCash',
            'totalCredit',
            'totalInflow',
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

    private function salesOverview(Carbon $start, Carbon $end): array
    {
        $reportData = collect(self::DEFAULT_FUEL_TYPES)
            ->mapWithKeys(fn ($fuelType) => [$fuelType => ['revenue' => 0.0, 'liters' => 0.0]])
            ->all();

        if (! $this->hasTable('sales') || ! $this->hasColumn('sales', 'fuel_type')) {
            return $reportData;
        }

        $revenueExpression = $this->hasColumn('sales', 'amount')
            ? 'COALESCE(SUM(amount), 0)'
            : '0';
        $litersExpression = $this->hasColumn('sales', 'liters')
            ? 'COALESCE(SUM(liters), 0)'
            : '0';

        $fuelStats = DB::table('sales')
            ->when($this->hasColumn('sales', 'created_at'), fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->select('fuel_type')
            ->selectRaw("{$revenueExpression} as revenue")
            ->selectRaw("{$litersExpression} as liters")
            ->groupBy('fuel_type')
            ->get();

        foreach ($fuelStats as $stat) {
            $fuelType = $this->normalizeFuelType($stat->fuel_type);

            if (! isset($reportData[$fuelType])) {
                continue;
            }

            $reportData[$fuelType]['revenue'] = $this->money($reportData[$fuelType]['revenue'] + (float) $stat->revenue);
            $reportData[$fuelType]['liters'] = $this->liters($reportData[$fuelType]['liters'] + (float) $stat->liters);
        }

        return $reportData;
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

    private function salesOutByFuelType(Carbon $start, Carbon $end): Collection
    {
        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'fuel_type')
            || ! $this->hasColumn('sales', 'liters')
        ) {
            return collect();
        }

        $rows = DB::table('sales')
            ->when($this->hasColumn('sales', 'created_at'), fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->select('fuel_type')
            ->selectRaw('COALESCE(SUM(liters), 0) as total_liters')
            ->groupBy('fuel_type')
            ->get();

        return $rows->reduce(function (Collection $salesOut, $row) {
            $fuelType = $this->normalizeFuelType($row->fuel_type);

            if (! in_array($fuelType, self::DEFAULT_FUEL_TYPES, true)) {
                return $salesOut;
            }

            $current = $salesOut->get($fuelType) ?? (object) [
                'fuel_type' => $fuelType,
                'total_liters' => 0.0,
            ];

            $current->total_liters = $this->liters($current->total_liters + (float) $row->total_liters);
            $salesOut->put($fuelType, $current);

            return $salesOut;
        }, collect());
    }

    private function financialInflow(Carbon $start, Carbon $end): Collection
    {
        $cashRemittances = $this->cashRemittances($start, $end);
        $creditPayments = collect([]);

        return $cashRemittances
            ->merge($creditPayments)
            ->sortByDesc('date')
            ->values();
    }

    private function cashRemittances(Carbon $start, Carbon $end): Collection
    {
        if (
            ! $this->hasTable('shift_readings')
            || ! $this->hasColumn('shift_readings', 'created_at')
            || ! $this->hasColumn('shift_readings', 'status')
        ) {
            return collect();
        }

        $amountColumn = $this->hasColumn('shift_readings', 'cash_on_hand')
            ? 'shift_readings.cash_on_hand'
            : ($this->hasColumn('shift_readings', 'cash_remitted') ? 'shift_readings.cash_remitted' : '0');
        $canJoinUsers = $this->hasTable('users')
            && $this->hasColumn('shift_readings', 'user_id')
            && $this->hasColumn('users', 'id')
            && $this->hasColumn('users', 'name');

        $query = DB::table('shift_readings')
            ->whereBetween('shift_readings.created_at', [$start, $end])
            ->whereIn(DB::raw('LOWER(shift_readings.status)'), $this->approvedShiftReadingStatuses());

        if ($canJoinUsers) {
            $query->join('users', 'shift_readings.user_id', '=', 'users.id');
        }

        return $query
            ->select(
                'shift_readings.created_at as date',
                DB::raw($canJoinUsers ? 'users.name as description' : "'Staff' as description"),
                DB::raw("COALESCE({$amountColumn}, 0) as amount"),
                DB::raw("'Cash Sales' as category")
            )
            ->get()
            ->map(function ($row) {
                $row->amount = $this->money($row->amount ?? 0);

                return $row;
            });
    }

    private function approvedShiftReadingStatuses(): array
    {
        return ['verified', 'approved'];
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
