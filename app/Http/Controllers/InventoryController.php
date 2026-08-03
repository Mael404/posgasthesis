<?php

namespace App\Http\Controllers;

use App\Services\LitersMovementTrackingService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryController extends Controller
{
    private const MAX_BULK_DELIVERIES = 3;

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

    public function __construct(
        private readonly LitersMovementTrackingService $litersMovementTracking
    ) {}

    public function index(Request $request)
    {
        $route = match ($request->query('tab')) {
            'stock' => 'inventory.stock.index',
            'pricing' => 'inventory.prices.index',
            default => 'inventory.tanks.index',
        };

        return redirect()->route($route, $request->except('tab'));
    }

    protected function inventoryViewData(
        Request $request,
        string $inventoryPage,
        string $inventoryTitle,
        string $inventoryDescription,
        string $filterRouteName
    ): array
    {
        $request->validate([
            'date_filter' => 'nullable|in:today,weekly,monthly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $dateRange = $this->dateRangeFromRequest($request);
        $tankTransactionPerPageOptions = [10, 20, 50, 100];
        $tankTransactionPerPage = (int) $request->query('tank_per_page', 10);
        $tankTransactionPerPage = in_array($tankTransactionPerPage, $tankTransactionPerPageOptions, true)
            ? $tankTransactionPerPage
            : 10;
        $tanks = $this->prepareTanks($this->tankRows());
        $activeTankId = $this->activeTankId($request, $tanks);
        $suppliers = $this->supplierRows();
        $prices = $this->fuelPriceRows($tanks);
        $deliveryLogs = $this->deliveryLogs($dateRange);
        $tankTransactionStats = $this->tankTransactionStats($tanks, $dateRange);
        $tankTransactions = $this->tankTransactions($request, $tanks, $activeTankId, $dateRange, $tankTransactionPerPage);
        $summary = $this->summary($tanks, $deliveryLogs, $tankTransactionStats);

        return [
            'title' => $inventoryTitle,
            'inventoryPage' => $inventoryPage,
            'inventoryTitle' => $inventoryTitle,
            'inventoryDescription' => $inventoryDescription,
            'filterRoute' => route($filterRouteName),
            'resetRoute' => route($filterRouteName),
            'tanks' => $tanks,
            'suppliers' => $suppliers,
            'prices' => $prices,
            'deliveryLogs' => $deliveryLogs,
            'tankTransactions' => $tankTransactions,
            'tankTransactionStats' => $tankTransactionStats,
            'activeTankId' => $activeTankId,
            'tankTransactionPerPage' => $tankTransactionPerPage,
            'tankTransactionPerPageOptions' => $tankTransactionPerPageOptions,
            'summary' => $summary,
            'dateRange' => $dateRange,
            'canManagePrices' => auth()->check() && auth()->user()->role !== 'semiadmin',
            'missingInventoryTables' => $this->missingTables(['tanks', 'suppliers', 'fuel_prices', 'inventory_logs']),
        ];
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
            'supplier_name' => 'required|string|max:255',
            'delivery_date' => 'required|date',
            'deliveries' => ['nullable', 'array', 'min:1', 'max:' . self::MAX_BULK_DELIVERIES],
            'deliveries.*.tank_id' => ['required_with:deliveries', 'integer', 'exists:tanks,id', 'distinct'],
            'deliveries.*.liters' => ['required_with:deliveries', 'numeric', 'min:1'],
            'deliveries.*.cost' => ['required_with:deliveries', 'numeric', 'min:1'],
            'tank_id' => ['required_without:deliveries', 'integer', 'exists:tanks,id'],
            'liters' => ['required_without:deliveries', 'numeric', 'min:1'],
            'cost' => ['required_without:deliveries', 'numeric', 'min:1'],
        ], [
            'deliveries.max' => 'A bulk delivery can include up to ' . self::MAX_BULK_DELIVERIES . ' tank entries only.',
            'deliveries.*.tank_id.distinct' => 'Each bulk delivery row must use a different tank.',
        ]);

        $supplierName = $this->normalizeSupplierName($validated['supplier_name']);
        $deliveries = $this->deliveryRowsFromValidated($validated);

        if ($supplierName === '') {
            return response()->json([
                'success' => false,
                'message' => 'Supplier is required.',
            ], 422);
        }

        try {
            $result = $this->saveDeliveryWithGeneratedCode($deliveries, $supplierName, $validated['delivery_date']);

            return response()->json([
                'success' => true,
                'message' => $result['delivery_count'] > 1
                    ? $result['delivery_count'] . ' delivery entries saved. Tank volumes updated.'
                    : 'Delivery saved. Tank volume updated.',
                'delivery_code' => $result['delivery_code'],
                'delivery_count' => $result['delivery_count'],
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
            'bulk_prices' => 'nullable|array',
            'bulk_prices.*' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $bulkPrices = collect($validated['bulk_prices'] ?? []);

                foreach ($validated['prices'] as $fuelType => $price) {
                    $fuelType = trim((string) $fuelType);

                    if ($fuelType === '') {
                        continue;
                    }

                    $values = [
                        'price_per_liter' => (float) $price,
                    ];

                    if ($this->hasColumn('fuel_prices', 'bulk_price_per_liter')) {
                        $bulkPrice = $bulkPrices->first(
                            fn ($value, $key) => strtolower(trim((string) $key)) === strtolower($fuelType)
                        );

                        if ($bulkPrice !== null && $bulkPrice !== '') {
                            $values['bulk_price_per_liter'] = (float) $bulkPrice;
                        }
                    }

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
                    'bulk_price_per_liter' => (float) ($price->bulk_price_per_liter ?? 0),
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

    private function activeTankId(Request $request, $tanks): ?string
    {
        if ($tanks->isEmpty()) {
            return null;
        }

        $requestedTankId = trim((string) $request->query('tank_id', ''));

        if ($requestedTankId !== '') {
            $requestedTank = $tanks->first(fn ($tank) => (string) $tank->id === $requestedTankId);

            if ($requestedTank) {
                return (string) $requestedTank->id;
            }
        }

        return (string) $tanks->first()->id;
    }

    private function tankTransactionStats($tanks, ?array $dateRange)
    {
        if (! $this->hasTankTransactionSource()) {
            return $this->emptyTankTransactionStats($tanks);
        }

        $pumpNumbers = $tanks
            ->flatMap(fn ($tank) => $tank->connected_pump_numbers)
            ->map(fn ($pumpNumber) => (int) $pumpNumber)
            ->filter(fn ($pumpNumber) => $pumpNumber > 0)
            ->unique()
            ->values();

        if ($pumpNumbers->isEmpty()) {
            return $this->emptyTankTransactionStats($tanks);
        }

        $rows = $this->tankTransactionBaseQuery($pumpNumbers->all(), $dateRange)
            ->select(
                'sales.pump_id',
                'sales.fuel_type',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('COALESCE(SUM(sales.liters), 0) as total_liters'),
                DB::raw('COALESCE(SUM(sales.amount), 0) as total_amount')
            )
            ->groupBy('sales.pump_id', 'sales.fuel_type')
            ->get();

        return $tanks->mapWithKeys(function ($tank) use ($rows) {
            $tankPumpNumbers = $tank->connected_pump_numbers
                ->map(fn ($pumpNumber) => (int) $pumpNumber)
                ->all();
            $tankFuelType = $this->normalizeFuelType($tank->fuel_type);
            $matches = $rows->filter(function ($row) use ($tankPumpNumbers, $tankFuelType) {
                return in_array((int) $row->pump_id, $tankPumpNumbers, true)
                    && $this->normalizeFuelType($row->fuel_type) === $tankFuelType;
            });

            return [
                (int) $tank->id => [
                    'count' => (int) $matches->sum('transaction_count'),
                    'liters' => (float) $matches->sum('total_liters'),
                    'amount' => (float) $matches->sum('total_amount'),
                ],
            ];
        });
    }

    private function tankTransactions(Request $request, $tanks, ?string $activeTankId, ?array $dateRange, int $perPage): LengthAwarePaginator
    {
        if (! $this->hasTankTransactionSource() || ! $activeTankId) {
            return $this->emptyTankTransactionPaginator($request, $perPage);
        }

        $activeTank = $tanks->first(fn ($tank) => (string) $tank->id === (string) $activeTankId);

        if (! $activeTank) {
            return $this->emptyTankTransactionPaginator($request, $perPage);
        }

        $pumpNumbers = $activeTank->connected_pump_numbers
            ->map(fn ($pumpNumber) => (int) $pumpNumber)
            ->filter(fn ($pumpNumber) => $pumpNumber > 0)
            ->values();

        if ($pumpNumbers->isEmpty()) {
            return $this->emptyTankTransactionPaginator($request, $perPage);
        }

        $query = $this->tankTransactionBaseQuery($pumpNumbers->all(), $dateRange)
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
            );

        $this->applyTankFuelFilter($query, $this->normalizeFuelType($activeTank->fuel_type));

        return $query
            ->orderByDesc('sales.created_at')
            ->orderByDesc('sales.id')
            ->paginate($perPage, ['*'], 'tank_transaction_page')
            ->withQueryString();
    }

    private function hasTankTransactionSource(): bool
    {
        return $this->hasTable('sales')
            && $this->hasColumn('sales', 'pump_id')
            && $this->hasColumn('sales', 'fuel_type')
            && $this->hasColumn('sales', 'liters');
    }

    private function tankTransactionBaseQuery(array $pumpNumbers, ?array $dateRange)
    {
        $query = DB::table('sales')
            ->whereIn('sales.pump_id', $pumpNumbers)
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

        return $query;
    }

    private function applyTankFuelFilter($query, string $fuelType): void
    {
        $fuelType = $this->normalizeFuelType($fuelType);

        if ($fuelType === 'Diesel') {
            $query->whereRaw('LOWER(sales.fuel_type) LIKE ?', ['%diesel%']);
            return;
        }

        if ($fuelType === 'Unleaded') {
            $query->where(function ($query) {
                $query->whereRaw('LOWER(sales.fuel_type) LIKE ?', ['%unleaded%'])
                    ->orWhereRaw('LOWER(sales.fuel_type) LIKE ?', ['%regular%']);
            });
            return;
        }

        if ($fuelType === 'Premium') {
            $query->whereRaw('LOWER(sales.fuel_type) LIKE ?', ['%premium%']);
            return;
        }

        if ($fuelType === 'N/A') {
            $query->where(function ($query) {
                $query->whereNull('sales.fuel_type')
                    ->orWhereRaw("TRIM(sales.fuel_type) = ''")
                    ->orWhereRaw('LOWER(TRIM(sales.fuel_type)) = ?', ['n/a']);
            });
            return;
        }

        $query->whereRaw('LOWER(TRIM(sales.fuel_type)) = ?', [strtolower($fuelType)]);
    }

    private function emptyTankTransactionStats($tanks)
    {
        return $tanks->mapWithKeys(fn ($tank) => [
            (int) $tank->id => [
                'count' => 0,
                'liters' => 0.0,
                'amount' => 0.0,
            ],
        ]);
    }

    private function emptyTankTransactionPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        $paginator = new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            max((int) $request->query('tank_transaction_page', 1), 1),
            [
                'path' => $request->url(),
                'pageName' => 'tank_transaction_page',
            ]
        );

        $paginator->appends($request->except('tank_transaction_page'));

        return $paginator;
    }

    private function summary($tanks, $deliveryLogs, $tankTransactionStats): array
    {
        $capacity = (float) $tanks->sum('capacity');
        $currentVolume = (float) $tanks->sum('current_volume');
        $transactionStats = collect($tankTransactionStats);

        return [
            'tank_count' => $tanks->count(),
            'current_volume' => round($currentVolume, 2),
            'capacity' => round($capacity, 2),
            'fill_percentage' => $capacity > 0 ? round(($currentVolume / $capacity) * 100, 1) : 0,
            'low_tanks' => $tanks->filter(fn ($tank) => (float) $tank->fill_percentage <= 25)->count(),
            'delivered_liters' => round((float) $deliveryLogs->sum('liters_delivered'), 2),
            'sold_liters' => round((float) $transactionStats->sum('liters'), 2),
            'sold_amount' => round((float) $transactionStats->sum('amount'), 2),
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

    private function deliveryRowsFromValidated(array $validated): array
    {
        $rows = $validated['deliveries'] ?? [[
            'tank_id' => $validated['tank_id'] ?? null,
            'liters' => $validated['liters'] ?? null,
            'cost' => $validated['cost'] ?? null,
        ]];

        return collect($rows)
            ->map(fn ($row) => [
                'tank_id' => (int) ($row['tank_id'] ?? 0),
                'liters' => (float) ($row['liters'] ?? 0),
                'cost' => (float) ($row['cost'] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function saveDeliveryWithGeneratedCode(array $deliveries, string $supplierName, string $deliveryDate): array
    {
        $lastDuplicate = null;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return DB::transaction(function () use ($deliveries, $supplierName, $deliveryDate) {
                    $tankIds = collect($deliveries)->pluck('tank_id')->unique()->values();

                    if ($tankIds->isEmpty() || $tankIds->count() !== count($deliveries)) {
                        throw new \RuntimeException('Each delivery entry must use a different tank.');
                    }

                    if ($tankIds->count() > self::MAX_BULK_DELIVERIES) {
                        throw new \RuntimeException('A bulk delivery can include up to ' . self::MAX_BULK_DELIVERIES . ' tank entries only.');
                    }

                    $this->litersMovementTracking->addActiveTankDeliveries(
                        collect($deliveries)
                            ->mapWithKeys(fn (array $delivery): array => [
                                (int) $delivery['tank_id'] => (float) $delivery['liters'],
                            ])
                            ->all()
                    );

                    $tanks = DB::table('tanks')
                        ->whereIn('id', $tankIds->all())
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($tanks->count() !== $tankIds->count()) {
                        throw new \RuntimeException('One or more selected tanks were not found.');
                    }

                    $createdAt = now();
                    $deliveryCode = $this->generateDeliveryCode($createdAt);
                    $supplierId = $this->findOrCreateSupplier($supplierName);
                    $parsedDeliveryDate = Carbon::parse($deliveryDate)->toDateString();

                    foreach ($deliveries as $delivery) {
                        $tank = $tanks->get($delivery['tank_id']);
                        $liters = (float) $delivery['liters'];
                        $capacity = (float) ($tank->capacity ?? 0);
                        $currentVolume = (float) ($tank->current_volume ?? 0);
                        $newTotalVolume = $currentVolume + $liters;

                        if ($capacity > 0 && $newTotalVolume > $capacity) {
                            $excess = $newTotalVolume - $capacity;
                            throw new \RuntimeException('OVERFILL ALERT! Delivery for ' . ($tank->name ?? 'the selected tank') . ' exceeds tank capacity by ' . number_format($excess, 2) . ' liters.');
                        }

                        DB::table('inventory_logs')->insert([
                            'delivery_code' => $deliveryCode,
                            'tank_id' => $delivery['tank_id'],
                            'supplier_id' => $supplierId,
                            'liters_delivered' => $liters,
                            'total_cost' => (float) $delivery['cost'],
                            'delivery_date' => $parsedDeliveryDate,
                            'created_at' => $createdAt,
                        ]);

                        DB::table('tanks')
                            ->where('id', $delivery['tank_id'])
                            ->update(['current_volume' => $newTotalVolume]);
                    }

                    return [
                        'delivery_code' => $deliveryCode,
                        'delivery_count' => count($deliveries),
                    ];
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
