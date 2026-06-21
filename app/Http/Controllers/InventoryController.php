<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class InventoryController extends Controller
{
    private const DEFAULT_FUEL_BY_PUMP = [
        1 => 'Premium',
        2 => 'Unleaded',
        3 => 'Premium',
        4 => 'Unleaded',
        5 => 'Diesel',
        6 => 'Diesel',
    ];

    private array $tablePresence = [];
    private array $columnPresence = [];

    public function index(Request $request): View
    {
        $request->validate([
            'date_filter' => 'nullable|in:today,weekly,monthly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'tab' => 'nullable|in:tanks,stock,pricing',
        ]);

        $dateRange = $this->dateRangeFromRequest($request);
        $tanks = $this->prepareTanks($this->tankRows());
        $suppliers = $this->supplierRows();
        $prices = $this->fuelPriceRows($tanks);
        $deliveryLogs = $this->deliveryLogs($dateRange);
        $tankTransactions = $this->tankTransactions($tanks, $dateRange);
        $summary = $this->summary($tanks, $deliveryLogs, $tankTransactions);

        return view('pages.admin.inventoryandtanks', [
            'title' => 'Inventory & Tanks',
            'tanks' => $tanks,
            'suppliers' => $suppliers,
            'prices' => $prices,
            'deliveryLogs' => $deliveryLogs,
            'tankTransactions' => $tankTransactions,
            'summary' => $summary,
            'dateRange' => $dateRange,
            'canManagePrices' => auth()->check() && auth()->user()->role !== 'semiadmin',
            'missingInventoryTables' => $this->missingTables(['tanks', 'suppliers', 'fuel_prices', 'inventory_logs']),
        ]);
    }

    public function stockIn(Request $request): JsonResponse
    {
        if ($missing = $this->missingTables(['tanks', 'suppliers', 'inventory_logs'])) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory setup is incomplete. Missing table(s): ' . implode(', ', $missing) . '.',
            ], 422);
        }

        $validated = $request->validate([
            'tank_id' => 'required|exists:tanks,id',
            'supplier_name' => 'required|string|max:255',
            'liters' => 'required|numeric|min:1',
            'cost' => 'required|numeric|min:1',
            'delivery_date' => 'required|date',
        ]);

        $supplierName = $this->normalizeSupplierName($validated['supplier_name']);

        if ($supplierName === '') {
            return response()->json([
                'success' => false,
                'message' => 'Supplier is required.',
            ], 422);
        }

        try {
            $deliveryCode = $this->saveDeliveryWithGeneratedCode($validated, $supplierName);

            return response()->json([
                'success' => true,
                'message' => 'Delivery saved. Tank volume updated.',
                'delivery_code' => $deliveryCode,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function updatePrice(Request $request): JsonResponse
    {
        if (! $this->hasTable('fuel_prices')) {
            return response()->json([
                'success' => false,
                'message' => 'Fuel price table is not available.',
            ], 422);
        }

        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.*' => 'required|numeric|min:1',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                foreach ($validated['prices'] as $fuelType => $price) {
                    $fuelType = trim((string) $fuelType);

                    if ($fuelType === '') {
                        continue;
                    }

                    $values = [
                        'price_per_liter' => (float) $price,
                    ];

                    if ($this->hasColumn('fuel_prices', 'updated_at')) {
                        $values['updated_at'] = now();
                    }

                    $existing = DB::table('fuel_prices')
                        ->whereRaw('LOWER(fuel_type) = ?', [strtolower($fuelType)])
                        ->first();

                    if ($existing) {
                        DB::table('fuel_prices')->where('id', $existing->id)->update($values);
                        continue;
                    }

                    DB::table('fuel_prices')->insert(array_merge([
                        'fuel_type' => $this->normalizeFuelType($fuelType),
                    ], $values));
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Fuel prices updated.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function tankRows()
    {
        if (! $this->hasTable('tanks')) {
            return collect();
        }

        return DB::table('tanks')
            ->when($this->hasColumn('tanks', 'id'), fn ($query) => $query->orderBy('id'))
            ->get();
    }

    private function supplierRows()
    {
        if (! $this->hasTable('suppliers')) {
            return collect();
        }

        return DB::table('suppliers')
            ->when($this->hasColumn('suppliers', 'name'), fn ($query) => $query->orderBy('name'))
            ->get();
    }

    private function fuelPriceRows($tanks)
    {
        $prices = collect();

        if ($this->hasTable('fuel_prices')) {
            $prices = DB::table('fuel_prices')
                ->when($this->hasColumn('fuel_prices', 'id'), fn ($query) => $query->orderBy('id'))
                ->get();
        }

        $knownFuelTypes = $tanks
            ->pluck('fuel_type')
            ->merge($prices->pluck('fuel_type'))
            ->merge(array_values(self::DEFAULT_FUEL_BY_PUMP))
            ->map(fn ($fuelType) => $this->normalizeFuelType($fuelType))
            ->filter()
            ->unique()
            ->values();

        return $knownFuelTypes
            ->map(function ($fuelType) use ($prices) {
                $price = $prices->first(fn ($row) => strtolower((string) $row->fuel_type) === strtolower($fuelType));

                return (object) [
                    'fuel_type' => $fuelType,
                    'price_per_liter' => (float) ($price->price_per_liter ?? 0),
                    'updated_at' => $price->updated_at ?? null,
                ];
            })
            ->sortBy(function ($price) {
                $index = array_search($price->fuel_type, ['Diesel', 'Unleaded', 'Premium'], true);

                return $index === false ? 99 : $index;
            })
            ->values();
    }

    private function deliveryLogs(?array $dateRange)
    {
        if (
            ! $this->hasTable('inventory_logs')
            || ! $this->hasTable('tanks')
            || ! $this->hasTable('suppliers')
        ) {
            return collect();
        }

        $query = DB::table('inventory_logs')
            ->join('tanks', 'inventory_logs.tank_id', '=', 'tanks.id')
            ->join('suppliers', 'inventory_logs.supplier_id', '=', 'suppliers.id')
            ->select(
                'inventory_logs.*',
                'tanks.name as tank_name',
                'tanks.fuel_type',
                'suppliers.name as supplier_name'
            )
            ->orderByDesc('inventory_logs.delivery_date')
            ->orderByDesc('inventory_logs.created_at');

        $this->applyDateRange($query, 'inventory_logs.delivery_date', $dateRange, true);

        if (! $dateRange) {
            $query->limit(50);
        }

        return $query->get();
    }

    private function tankTransactions($tanks, ?array $dateRange)
    {
        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'pump_id')
            || ! $this->hasColumn('sales', 'fuel_type')
            || ! $this->hasColumn('sales', 'liters')
        ) {
            return collect();
        }

        $pumpNumbers = $tanks
            ->flatMap(fn ($tank) => $tank->connected_pump_numbers)
            ->unique()
            ->values();

        if ($pumpNumbers->isEmpty()) {
            return collect();
        }

        $query = DB::table('sales')
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->leftJoin('customers as posted_customers', 'sales.posted_customer_id', '=', 'posted_customers.id')
            ->select(
                'sales.id',
                'sales.or_number',
                'sales.pump_id',
                'sales.payment_type',
                'sales.fuel_type',
                'sales.liters',
                'sales.price_applied',
                'sales.amount',
                'sales.created_at',
                DB::raw("COALESCE(users.name, CONCAT(users.first_name, ' ', users.last_name), 'Staff') as staff_name"),
                DB::raw("COALESCE(customers.business_name, posted_customers.business_name, customers.owner_name, posted_customers.owner_name, sales.customer_name, 'Walk-In Customer') as customer_display")
            )
            ->whereIn('sales.pump_id', $pumpNumbers->all())
            ->where('sales.liters', '>', 0)
            ->where(function ($query) {
                $query->whereNull('sales.payment_type')
                    ->orWhereRaw('LOWER(sales.payment_type) <> ?', ['collection']);
            });

        $this->applyDateRange($query, 'sales.created_at', $dateRange);

        if ($this->hasColumn('sales', 'is_void')) {
            $query->where(function ($query) {
                $query->where('sales.is_void', false)
                    ->orWhereNull('sales.is_void');
            });
        }

        if ($this->hasColumn('sales', 'voided_at')) {
            $query->whereNull('sales.voided_at');
        }

        if ($this->hasColumn('sales', 'status')) {
            $query->where(function ($query) {
                $query->whereNull('sales.status')
                    ->orWhereRaw('LOWER(sales.status) NOT IN (?, ?, ?, ?)', ['void', 'voided', 'cancelled', 'canceled']);
            });
        }

        $transactions = $query
            ->orderByDesc('sales.created_at')
            ->orderByDesc('sales.id')
            ->get();

        return $tanks->mapWithKeys(function ($tank) use ($transactions) {
            $tankPumpNumbers = $tank->connected_pump_numbers
                ->map(fn ($pumpNumber) => (int) $pumpNumber)
                ->all();
            $tankFuelType = $this->normalizeFuelType($tank->fuel_type);

            return [
                $tank->id => $transactions
                    ->filter(function ($transaction) use ($tankPumpNumbers, $tankFuelType) {
                        return in_array((int) $transaction->pump_id, $tankPumpNumbers, true)
                            && $this->normalizeFuelType($transaction->fuel_type) === $tankFuelType;
                    })
                    ->values(),
            ];
        });
    }

    private function summary($tanks, $deliveryLogs, $tankTransactions): array
    {
        $capacity = (float) $tanks->sum('capacity');
        $currentVolume = (float) $tanks->sum('current_volume');
        $transactions = collect($tankTransactions)->flatten(1);

        return [
            'tank_count' => $tanks->count(),
            'current_volume' => round($currentVolume, 2),
            'capacity' => round($capacity, 2),
            'fill_percentage' => $capacity > 0 ? round(($currentVolume / $capacity) * 100, 1) : 0,
            'low_tanks' => $tanks->filter(fn ($tank) => (float) $tank->fill_percentage <= 25)->count(),
            'delivered_liters' => round((float) $deliveryLogs->sum('liters_delivered'), 2),
            'sold_liters' => round((float) $transactions->sum('liters'), 2),
            'sold_amount' => round((float) $transactions->sum('amount'), 2),
        ];
    }

    private function prepareTanks($tanks)
    {
        return $tanks->map(function ($tank) {
            $fuelType = $this->normalizeFuelType($tank->fuel_type ?? $tank->name ?? 'N/A');
            $capacity = (float) ($tank->capacity ?? 0);
            $currentVolume = (float) ($tank->current_volume ?? 0);
            $pumpNumbers = $this->tankPumpNumbers($tank, $fuelType);

            $tank->fuel_type = $fuelType;
            $tank->name = $tank->name ?? $fuelType;
            $tank->current_volume = round($currentVolume, 2);
            $tank->capacity = round($capacity, 2);
            $tank->status = $tank->status ?? 'Active';
            $tank->connected_pump_numbers = $pumpNumbers;
            $tank->connected_pumps_display = $pumpNumbers->isEmpty()
                ? 'N/A'
                : $pumpNumbers->map(fn ($pumpNumber) => 'P' . $pumpNumber)->implode(', ');
            $tank->fill_percentage = $capacity > 0
                ? round(max(0, min(($currentVolume / $capacity) * 100, 100)), 1)
                : 0;

            return $tank;
        });
    }

    private function tankPumpNumbers($tank, ?string $fuelType = null)
    {
        $connectedPumps = trim((string) ($tank->connected_pumps ?? ''));

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

        $fuelType = $fuelType ?: $this->normalizeFuelType($tank->fuel_type ?? null);

        return collect(self::DEFAULT_FUEL_BY_PUMP)
            ->filter(fn ($defaultFuelType) => $this->normalizeFuelType($defaultFuelType) === $fuelType)
            ->keys()
            ->values();
    }

    private function normalizeSupplierName($supplierName): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $supplierName)));
    }

    private function findOrCreateSupplier(string $supplierName): int
    {
        $supplier = DB::table('suppliers')
            ->whereRaw('UPPER(name) = ?', [$supplierName])
            ->first();

        if ($supplier) {
            if ($supplier->name !== $supplierName) {
                DB::table('suppliers')->where('id', $supplier->id)->update(['name' => $supplierName]);
            }

            return (int) $supplier->id;
        }

        return (int) DB::table('suppliers')->insertGetId(['name' => $supplierName]);
    }

    private function saveDeliveryWithGeneratedCode(array $validated, string $supplierName): string
    {
        $lastDuplicate = null;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return DB::transaction(function () use ($validated, $supplierName) {
                    $tank = DB::table('tanks')
                        ->where('id', $validated['tank_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $tank) {
                        throw new \RuntimeException('The selected tank was not found.');
                    }

                    $liters = (float) $validated['liters'];
                    $capacity = (float) ($tank->capacity ?? 0);
                    $currentVolume = (float) ($tank->current_volume ?? 0);
                    $newTotalVolume = $currentVolume + $liters;

                    if ($capacity > 0 && $newTotalVolume > $capacity) {
                        $excess = $newTotalVolume - $capacity;
                        throw new \RuntimeException('OVERFILL ALERT! This delivery exceeds the tank capacity by ' . number_format($excess, 2) . ' liters.');
                    }

                    $createdAt = now();
                    $deliveryCode = $this->generateDeliveryCode($createdAt);
                    $supplierId = $this->findOrCreateSupplier($supplierName);

                    DB::table('inventory_logs')->insert([
                        'delivery_code' => $deliveryCode,
                        'delivery_status' => InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD,
                        'tank_id' => $validated['tank_id'],
                        'supplier_id' => $supplierId,
                        'liters_delivered' => $liters,
                        'total_cost' => (float) $validated['cost'],
                        'delivery_date' => Carbon::parse($validated['delivery_date'])->toDateString(),
                        'created_at' => $createdAt,
                    ]);

                    DB::table('tanks')
                        ->where('id', $validated['tank_id'])
                        ->update(['current_volume' => $newTotalVolume]);

                    return $deliveryCode;
                });
            } catch (QueryException $e) {
                if (! $this->isDuplicateDeliveryCodeError($e)) {
                    throw $e;
                }

                $lastDuplicate = $e;
                usleep(100000);
            }
        }

        throw $lastDuplicate ?: new \RuntimeException('Unable to generate a unique delivery code.');
    }

    private function generateDeliveryCode(Carbon $timestamp): string
    {
        $datePrefix = 'DLV-' . $timestamp->format('Ymd') . '-';

        $maxSequence = DB::table('inventory_logs')
            ->whereNotNull('delivery_code')
            ->where('delivery_code', 'like', $datePrefix . '%')
            ->lockForUpdate()
            ->pluck('delivery_code')
            ->map(function ($code) {
                preg_match('/-(\d{3,})$/', (string) $code, $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max();

        $sequence = ((int) $maxSequence) + 1;

        return sprintf('%s%s-%03d', $datePrefix, $timestamp->format('His'), $sequence);
    }

    private function isDuplicateDeliveryCodeError(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1062;
    }

    private function dateRangeFromRequest(Request $request): ?array
    {
        if ($request->filled('date_filter')) {
            return match ($request->date_filter) {
                'weekly' => [
                    Carbon::now()->startOfWeek()->startOfDay(),
                    Carbon::now()->endOfWeek()->endOfDay(),
                ],
                'monthly' => [
                    Carbon::now()->startOfMonth()->startOfDay(),
                    Carbon::now()->endOfMonth()->endOfDay(),
                ],
                default => [
                    Carbon::today()->startOfDay(),
                    Carbon::today()->endOfDay(),
                ],
            };
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            return [
                $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : null,
                $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : null,
            ];
        }

        return null;
    }

    private function applyDateRange($query, string $column, ?array $dateRange, bool $dateOnly = false): void
    {
        if (! $dateRange) {
            return;
        }

        [$start, $end] = $dateRange;
        $startValue = $start ? ($dateOnly ? $start->toDateString() : $start) : null;
        $endValue = $end ? ($dateOnly ? $end->toDateString() : $end) : null;

        if ($startValue && $endValue) {
            $query->whereBetween($column, [$startValue, $endValue]);
            return;
        }

        if ($startValue) {
            $query->where($column, '>=', $startValue);
        }

        if ($endValue) {
            $query->where($column, '<=', $endValue);
        }
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

    private function missingTables(array $tables): array
    {
        return collect($tables)
            ->reject(fn ($table) => $this->hasTable($table))
            ->values()
            ->all();
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
}
