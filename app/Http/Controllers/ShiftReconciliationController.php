<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesVoidedSettlementDisplay;
use App\Services\ShiftReadingInventoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ShiftReconciliationController extends Controller
{
    use HandlesVoidedSettlementDisplay;

    private const PUMP_COUNT = 6;

    private array $lockedSalesPresence = [];

    private array $tablePresence = [];

    private array $columnPresence = [];

    public function __construct(
        private readonly ShiftReadingInventoryService $shiftReadingInventory
    ) {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'staff_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:pending,verified,rejected,voided'],
            'variance' => ['nullable', 'string', 'in:balanced,shortage,overage'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
        ]);

        $perPageOptions = [10, 20, 50, 100];
        $perPage = (int) ($validated['per_page'] ?? 10);
        $perPage = in_array($perPage, $perPageOptions, true) ? $perPage : 10;
        $filters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'staff_id' => isset($validated['staff_id']) ? (int) $validated['staff_id'] : null,
            'status' => $validated['status'] ?? null,
            'variance' => $validated['variance'] ?? null,
        ];
        $dataWarning = null;
        $staffOptions = collect();

        if (! $this->hasTable('shift_readings')) {
            $readings = $this->emptyPaginator($perPage);
            $summary = $this->emptySummary();
            $dataWarning = 'Shift reading records are not available yet.';
        } else {
            $staffOptions = $this->staffFilterOptions();
            $query = $this->applyReadingFilters($this->readingQuery(), $filters)
                ->when($this->hasColumn('shift_readings', 'created_at'), fn ($query) => $query->orderByDesc('shift_readings.created_at'))
                ->when(! $this->hasColumn('shift_readings', 'created_at') && $this->hasColumn('shift_readings', 'id'), fn ($query) => $query->orderByDesc('shift_readings.id'))
                ->when($this->hasColumn('shift_readings', 'id'), fn ($query) => $query->orderByDesc('shift_readings.id'));

            if ($this->hasVarianceFilter($filters)) {
                $readings = $this->paginateComputedVarianceReadings($query, $perPage, 'reconciliation_page', $filters)
                    ->withQueryString();
            } else {
                $readings = $query
                    ->paginate($perPage, ['*'], 'reconciliation_page')
                    ->withQueryString();

                $readings->setCollection(
                    $readings->getCollection()->map(function ($reading) {
                        $this->calculateReadingData($reading);

                        return $reading;
                    })
                );
            }

            $summary = $this->summaryStats($filters);
        }

        return view('pages.admin.shiftreconciliation', [
            'title' => 'Shift Reconciliation',
            'readings' => $readings,
            'summary' => $summary,
            'dataWarning' => $dataWarning,
            'filters' => $filters,
            'staffOptions' => $staffOptions,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function getDetails(int $id): JsonResponse
    {
        if (! $this->hasTable('shift_readings') || ! $this->hasColumn('shift_readings', 'id')) {
            return response()->json(['message' => 'Shift readings are not available.'], 404);
        }

        $reading = $this->readingQuery()
            ->where('shift_readings.id', $id)
            ->first();

        if (! $reading) {
            return response()->json(['message' => 'Shift reading not found.'], 404);
        }

        $this->calculateReadingData($reading, true);

        return response()->json([
            'reading' => $reading,
            'meter_images' => $this->meterImages($id),
            'transaction_breakdowns' => $this->settlementDetailBreakdowns($reading),
        ]);
    }

    public function image(Request $request, int $reading, int $dispenser, string $type)
    {
        if (
            ! $this->hasTable('shift_reading_images')
            || ! $this->hasColumn('shift_reading_images', 'shift_reading_id')
            || ! $this->hasColumn('shift_reading_images', 'dispenser')
            || ! $this->hasColumn('shift_reading_images', 'image_type')
        ) {
            abort(404);
        }

        $query = DB::table('shift_reading_images')
            ->where('shift_reading_id', $reading)
            ->where('dispenser', $dispenser)
            ->whereRaw('LOWER(image_type) = ?', [strtolower($type)]);

        $imageId = (int) $request->query('image', 0);

        if ($imageId > 0 && $this->hasColumn('shift_reading_images', 'id')) {
            $query->where('id', $imageId);
        }

        $image = $query
            ->when($this->hasColumn('shift_reading_images', 'id'), fn ($query) => $query->orderBy('id'))
            ->first();

        if (! $image) {
            abort(404);
        }

        $path = $this->storedImagePath($image);

        if (! $path) {
            abort(404);
        }

        return response()->file($path);
    }

    public function approve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $reading = $this->findReadingForAction((int) $validated['id'], true);

                if (! $reading) {
                    throw new \RuntimeException('Shift reading not found.');
                }

                if ($this->hasColumn('shift_readings', 'status') && strtolower((string) $reading->status) !== 'pending') {
                    throw new \RuntimeException('Only pending settlements can be approved.');
                }

                [, $periodStart, $periodEnd] = $this->getReadingPeriodContext($reading);
                $this->shiftReadingInventory->apply($reading);
                $this->applyPendingCollections($reading, $periodStart, $periodEnd);
                $this->lockSalesForReading($reading);
                $this->updateReadingStatus((int) $validated['id'], 'Verified');
            });

            return response()->json([
                'success' => true,
                'message' => 'Shift verified. The inventory deducted on submission remains applied, and pending payments were posted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $reading = $this->findReadingForAction((int) $validated['id'], true);

                if (! $reading) {
                    throw new \RuntimeException('Shift reading not found.');
                }

                if ($this->hasColumn('shift_readings', 'status') && strtolower((string) $reading->status) !== 'pending') {
                    throw new \RuntimeException('Only pending settlements can be rejected.');
                }

                $this->shiftReadingInventory->restore($reading);
                $this->unlockSalesForReading($reading);

                $updates = [];

                if ($this->hasColumn('shift_readings', 'status')) {
                    $updates['status'] = 'Rejected';
                }

                if ($this->hasColumn('shift_readings', 'rejection_reason')) {
                    $updates['rejection_reason'] = trim($validated['rejection_reason']);
                }

                $this->updateShiftReading((int) $validated['id'], $updates);
            });

            return response()->json([
                'success' => true,
                'message' => 'Shift rejected. Its deducted tank inventory was restored and its transactions were unlocked.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function voidApproval(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'void_reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $reversedCollections = 0;

            DB::transaction(function () use ($validated, &$reversedCollections) {
                $reading = $this->findReadingForAction((int) $validated['id'], true);

                if (! $reading) {
                    throw new \RuntimeException('Shift reading not found.');
                }

                if ($this->hasColumn('shift_readings', 'status') && strtolower((string) $reading->status) !== 'verified') {
                    throw new \RuntimeException('Only verified settlements can have their approval undone.');
                }

                $this->calculateReadingData($reading, true);
                $snapshot = $this->settlementSnapshotPayload($reading);

                $this->shiftReadingInventory->restore($reading);
                $reversedCollections = $this->reverseShiftApprovalCollections($reading);
                $this->unlockSalesForReading($reading);
                $this->updateShiftReading((int) $reading->id, array_merge(
                    $this->voidUpdatePayload($validated['void_reason']),
                    $snapshot
                ));
            });

            return response()->json([
                'success' => true,
                'message' => "Approval undone. Inventory was restored, {$reversedCollections} collection payment(s) were reset, and the settlement transactions were unlocked.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveRemarks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'admin_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->hasTable('shift_readings') || ! $this->hasColumn('shift_readings', 'admin_remarks')) {
            return response()->json([
                'success' => false,
                'message' => 'Admin remarks are not available for this shift reading table.',
            ], 422);
        }

        $remarks = trim((string) ($validated['admin_remarks'] ?? ''));
        $remarkUpdates = [
            'admin_remarks' => $remarks !== '' ? $remarks : null,
            'admin_remarks_by' => $remarks !== '' ? auth()->id() : null,
            'admin_remarks_updated_at' => $remarks !== '' ? now() : null,
            'admin_remarks_seen_at' => null,
        ];

        $this->updateShiftReading((int) $validated['id'], $remarkUpdates);

        return response()->json([
            'success' => true,
            'message' => $remarks !== '' ? 'Remarks saved.' : 'Remarks cleared.',
        ]);
    }

    private function readingQuery()
    {
        $query = DB::table('shift_readings');
        $selects = ['shift_readings.*'];

        if (
            $this->hasTable('users')
            && $this->hasColumn('shift_readings', 'user_id')
            && $this->hasColumn('users', 'id')
            && $this->hasColumn('users', 'name')
        ) {
            $query->leftJoin('users', 'shift_readings.user_id', '=', 'users.id');
            $selects[] = 'users.name as staff_name';
        } else {
            $selects[] = DB::raw("'Staff' as staff_name");
        }

        return $query->select($selects);
    }

    private function applyReadingFilters($query, array $filters)
    {
        $staffId = (int) ($filters['staff_id'] ?? 0);

        if ($staffId > 0 && $this->hasColumn('shift_readings', 'user_id')) {
            $query->where('shift_readings.user_id', $staffId);
        }

        $this->applyStatusFilter($query, $filters['status'] ?? null);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($dateFrom || $dateTo) {
            $from = $dateFrom ? Carbon::parse($dateFrom)->toDateString() : null;
            $to = $dateTo ? Carbon::parse($dateTo)->toDateString() : null;
            $hasPeriodFrom = $this->hasColumn('shift_readings', 'period_from');
            $hasPeriodTo = $this->hasColumn('shift_readings', 'period_to');

            if ($hasPeriodFrom && $hasPeriodTo) {
                if ($from && $to) {
                    $query->whereDate('shift_readings.period_from', '<=', $to)
                        ->whereDate('shift_readings.period_to', '>=', $from);
                } elseif ($from) {
                    $query->whereDate('shift_readings.period_to', '>=', $from);
                } elseif ($to) {
                    $query->whereDate('shift_readings.period_from', '<=', $to);
                }
            } elseif ($hasPeriodFrom) {
                $query->when($from, fn ($query) => $query->whereDate('shift_readings.period_from', '>=', $from))
                    ->when($to, fn ($query) => $query->whereDate('shift_readings.period_from', '<=', $to));
            } elseif ($hasPeriodTo) {
                $query->when($from, fn ($query) => $query->whereDate('shift_readings.period_to', '>=', $from))
                    ->when($to, fn ($query) => $query->whereDate('shift_readings.period_to', '<=', $to));
            } elseif ($this->hasColumn('shift_readings', 'created_at')) {
                $query->when($from, fn ($query) => $query->whereDate('shift_readings.created_at', '>=', $from))
                    ->when($to, fn ($query) => $query->whereDate('shift_readings.created_at', '<=', $to));
            }
        }

        return $query;
    }

    private function applyStatusFilter($query, ?string $status): void
    {
        if ($status === null || $status === '' || ! $this->hasColumn('shift_readings', 'status')) {
            return;
        }

        $query->where(function ($query) use ($status) {
            if ($status === 'verified') {
                $query->whereRaw('LOWER(shift_readings.status) LIKE ?', ['%verified%'])
                    ->orWhereRaw('LOWER(shift_readings.status) LIKE ?', ['%approved%']);

                return;
            }

            if ($status === 'rejected') {
                $query->whereRaw('LOWER(shift_readings.status) LIKE ?', ['%reject%']);

                return;
            }

            if ($status === 'voided') {
                $query->whereRaw('LOWER(shift_readings.status) LIKE ?', ['%void%']);

                return;
            }

            $query->whereNull('shift_readings.status')
                ->orWhereRaw('LOWER(shift_readings.status) LIKE ?', ['%pending%']);
        });
    }

    private function hasVarianceFilter(array $filters): bool
    {
        return in_array((string) ($filters['variance'] ?? ''), ['balanced', 'shortage', 'overage'], true);
    }

    private function paginateComputedVarianceReadings($query, int $perPage, string $pageName, array $filters): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $readings = $query->get()
            ->map(function ($reading) {
                $this->calculateReadingData($reading);

                return $reading;
            })
            ->filter(fn ($reading) => $this->readingMatchesVarianceFilter($reading, $filters['variance'] ?? null))
            ->values();

        return new LengthAwarePaginator(
            $readings->forPage($page, $perPage)->values(),
            $readings->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );
    }

    private function readingMatchesVarianceFilter(object $reading, ?string $variance): bool
    {
        $amount = $this->filterableCashVariance($reading);

        if ($amount === null) {
            return false;
        }

        if ($variance === 'balanced') {
            return abs($amount) < 0.01;
        }

        if ($variance === 'shortage') {
            return $amount < -0.009;
        }

        if ($variance === 'overage') {
            return $amount > 0.009;
        }

        return true;
    }

    private function filterableCashVariance(object $reading): ?float
    {
        if ((bool) ($reading->is_voided_settlement ?? false)) {
            return $this->storedSettlementNumber($reading, 'cash_variance_snapshot');
        }

        return $this->number($reading->cash_variance ?? 0);
    }

    private function staffFilterOptions()
    {
        if (! $this->hasTable('shift_readings') || ! $this->hasColumn('shift_readings', 'user_id')) {
            return collect();
        }

        $staffIds = DB::table('shift_readings')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($staffIds->isEmpty()) {
            return collect();
        }

        $names = collect();

        if ($this->hasTable('users') && $this->hasColumn('users', 'id') && $this->hasColumn('users', 'name')) {
            $names = DB::table('users')
                ->whereIn('id', $staffIds->all())
                ->pluck('name', 'id');
        }

        return $staffIds
            ->map(function (int $id) use ($names) {
                $name = trim((string) ($names[$id] ?? ''));

                return (object) [
                    'id' => $id,
                    'name' => $name !== '' ? $name : "Staff #{$id}",
                ];
            })
            ->sortBy(fn ($staff) => strtolower($staff->name))
            ->values();
    }

    private function findReadingForAction(int $id, bool $lockForUpdate = false)
    {
        if (! $this->hasTable('shift_readings') || ! $this->hasColumn('shift_readings', 'id')) {
            return null;
        }

        $query = DB::table('shift_readings')->where('id', $id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function calculateReadingData(object $reading, bool $withPumpNetSales = false): void
    {
        $storedExpectedCollection = $this->storedSettlementNumber($reading, 'expected_collection_snapshot')
            ?? $this->storedSettlementNumber($reading, 'expected_collection');
        $storedCashVariance = $this->storedSettlementNumber($reading, 'cash_variance_snapshot')
            ?? $this->storedSettlementNumber($reading, 'cash_variance');
        [$lastVerified, $periodStart, $periodEnd] = $this->getReadingPeriodContext($reading);

        $totalPumpSales = 0.0;
        $totalPumpLiters = 0.0;

        for ($pump = 1; $pump <= self::PUMP_COUNT; $pump++) {
            $startSalesField = "pump{$pump}_sales_start";
            $endSalesField = "pump{$pump}_sales_end";
            $startLitersField = "pump{$pump}_liters_start";
            $endLitersField = "pump{$pump}_liters_end";
            $dispensedSalesField = "pump{$pump}_dispensed_sales";
            $dispensedLitersField = "pump{$pump}_dispensed_liters";

            $startSales = $this->number($reading->$startSalesField ?? ($lastVerified->$endSalesField ?? 0));
            $endSales = $this->number($reading->$endSalesField ?? $startSales);
            $startLiters = $this->number($reading->$startLitersField ?? ($lastVerified->$endLitersField ?? 0));
            $endLiters = $this->number($reading->$endLitersField ?? $startLiters);

            $reading->$startSalesField = $startSales;
            $reading->$endSalesField = $endSales;
            $reading->$startLitersField = $startLiters;
            $reading->$endLitersField = $endLiters;
            $reading->$dispensedSalesField = $this->money($endSales - $startSales);
            $reading->$dispensedLitersField = $this->liters($endLiters - $startLiters);

            $totalPumpSales += $reading->$dispensedSalesField;
            $totalPumpLiters += $reading->$dispensedLitersField;
        }

        $posStats = $this->readingSalesAdjustments($reading, $periodStart, $periodEnd);
        $grossCreditSales = $this->money($posStats->credit_sales ?? 0);
        $paymentsCollected = $this->money($posStats->total_collections ?? 0);
        $autoDiscount = $this->money($posStats->total_auto_discount ?? 0);
        $manualDiscount = $this->money($posStats->total_manual_discount ?? 0);
        $surcharge = $this->money($posStats->total_surcharge ?? 0);
        $cashOnHand = $this->money($reading->cash_on_hand ?? ($reading->cash_remitted ?? 0));
        $gcashAmount = $this->money($reading->gcash_amount ?? 0);
        $chequeAmount = $this->money($reading->cheque_amount ?? 0);
        $expectedCollection = $this->money(($totalPumpSales + $surcharge + $paymentsCollected) - $grossCreditSales - $autoDiscount - $manualDiscount);
        $totalRemitted = $this->money($cashOnHand + $gcashAmount + $chequeAmount);

        $reading->total_pump_sales = $this->money($totalPumpSales);
        $reading->total_pump_liters = $this->liters($totalPumpLiters);
        $reading->gross_credit_sales = $grossCreditSales;
        $reading->payments_collected = $paymentsCollected;
        $reading->auto_discount = $autoDiscount;
        $reading->manual_discount = $manualDiscount;
        $reading->surcharge = $surcharge;
        $reading->cash_on_hand = $cashOnHand;
        $reading->gcash_amount = $gcashAmount;
        $reading->cheque_amount = $chequeAmount;
        $reading->expected_collection = $expectedCollection;
        $reading->total_remitted = $totalRemitted;
        $reading->cash_variance = $this->money($totalRemitted - $expectedCollection);
        $reading->cash_denominations = $this->decodeJsonDetails($reading->cash_denominations ?? null);
        $reading->gcash_entries = $this->decodeJsonDetails($reading->gcash_entries ?? null);
        $reading->cheque_entries = $this->decodeJsonDetails($reading->cheque_entries ?? null);
        $this->applyVoidedSettlementDisplayState($reading, $storedExpectedCollection, $storedCashVariance);
        if ($withPumpNetSales) {
            $this->applyPumpNetSales($reading, $periodStart, $periodEnd);
        }
        $reading->dipstick_premium = $this->liters($this->firstReadingNumber($reading, [
            'dipstick_premium',
            'premium_dipstick',
            'premium_dipstick_reading',
        ]));
        $reading->dipstick_regular = $this->liters($this->firstReadingNumber($reading, [
            'dipstick_regular',
            'dipstick_unleaded',
            'regular_dipstick',
            'unleaded_dipstick',
            'regular_dipstick_reading',
            'unleaded_dipstick_reading',
        ]));
        $reading->dipstick_diesel = $this->liters($this->firstReadingNumber($reading, [
            'dipstick_diesel',
            'diesel_dipstick',
            'diesel_dipstick_reading',
        ]));
    }

    private function applyPumpNetSales(object $reading, Carbon $periodStart, Carbon $periodEnd): void
    {
        $snapshotRows = $this->normalizedPumpNetSalesSnapshot($reading);

        if ($this->settlementIsVoided($reading) && $snapshotRows !== null) {
            $this->assignPumpNetSalesRows($reading, $snapshotRows);

            return;
        }

        $includeVoidedHistorical = $this->settlementIsVoided($reading);
        $discountsByPump = $this->readingDiscountsByPump($reading, $periodStart, $periodEnd, $includeVoidedHistorical);

        if ($includeVoidedHistorical && ! $this->hasPumpDiscounts($discountsByPump)) {
            $snapshotBreakdowns = $this->settlementSnapshotBreakdowns($reading);

            if ($snapshotBreakdowns !== null) {
                $discountsByPump = $this->breakdownDiscountsByPump($snapshotBreakdowns);
            }
        }

        $rows = [];

        for ($pump = 1; $pump <= self::PUMP_COUNT; $pump++) {
            $grossSales = $this->money($reading->{"pump{$pump}_dispensed_sales"} ?? 0);
            $discounts = $this->money($discountsByPump[$pump] ?? 0);
            $netSales = $this->money($grossSales - $discounts);

            $rows[] = [
                'pump_id' => $pump,
                'pump_label' => "Pump {$pump}",
                'fuel_type' => $this->pumpFuelLabel($pump),
                'gross_sales' => $grossSales,
                'discounts' => $discounts,
                'net_sales' => $netSales,
            ];
        }

        $this->assignPumpNetSalesRows($reading, $rows);
    }

    private function assignPumpNetSalesRows(object $reading, array $rows): void
    {
        $normalizedRows = [];
        $total = 0.0;

        foreach ($rows as $row) {
            $pump = (int) ($row['pump_id'] ?? 0);

            if ($pump < 1 || $pump > self::PUMP_COUNT) {
                continue;
            }

            $grossSales = $this->money($row['gross_sales'] ?? ($reading->{"pump{$pump}_dispensed_sales"} ?? 0));
            $discounts = $this->money($row['discounts'] ?? 0);
            $netSales = $this->money($row['net_sales'] ?? ($grossSales - $discounts));

            $reading->{"pump{$pump}_sales_discount"} = $discounts;
            $reading->{"pump{$pump}_net_sales"} = $netSales;

            $normalizedRows[] = [
                'pump_id' => $pump,
                'pump_label' => $row['pump_label'] ?? "Pump {$pump}",
                'fuel_type' => $row['fuel_type'] ?? $this->pumpFuelLabel($pump),
                'gross_sales' => $grossSales,
                'discounts' => $discounts,
                'net_sales' => $netSales,
            ];
            $total += $netSales;
        }

        $reading->pump_net_sales = $normalizedRows;
        $reading->total_net_sales = $this->money($total);
    }

    private function readingDiscountsByPump(object $reading, Carbon $periodStart, Carbon $periodEnd, bool $includeVoidedHistorical = false): array
    {
        $discounts = array_fill(1, self::PUMP_COUNT, 0.0);

        if (! $this->hasTable('sales')) {
            return $discounts;
        }

        $autoDiscountExpression = $this->hasColumn('sales', 'discount')
            ? 'COALESCE(SUM(sales.discount), 0)'
            : '0';
        $manualDiscountExpression = $this->hasColumn('sales', 'manual_discount')
            ? 'COALESCE(SUM(sales.manual_discount), 0)'
            : '0';
        $pumpExpression = $this->hasColumn('sales', 'pump_id')
            ? 'COALESCE(sales.pump_id, 0)'
            : '0';

        $query = $this->scopeSalesToReading(DB::table('sales'), $reading, $periodStart, $periodEnd, $includeVoidedHistorical)
            ->selectRaw("
                {$pumpExpression} as pump_id,
                {$autoDiscountExpression} + {$manualDiscountExpression} as discounts
            ");

        if ($this->hasColumn('sales', 'pump_id')) {
            $query->groupByRaw($pumpExpression);
        }

        foreach ($query->get() as $row) {
            $pump = (int) ($row->pump_id ?? 0);

            if ($pump >= 1 && $pump <= self::PUMP_COUNT) {
                $discounts[$pump] = $this->money($row->discounts ?? 0);
            }
        }

        return $discounts;
    }

    private function breakdownDiscountsByPump(array $breakdowns): array
    {
        $discounts = array_fill(1, self::PUMP_COUNT, 0.0);

        foreach ([['system_discounts', 'discount'], ['manual_discounts', 'manual_discount']] as [$key, $field]) {
            foreach (($breakdowns[$key] ?? []) as $row) {
                $pump = $this->pumpIdFromBreakdownRow($row);

                if ($pump >= 1 && $pump <= self::PUMP_COUNT) {
                    $discounts[$pump] += $this->number($this->breakdownRowValue($row, $field));
                }
            }
        }

        return array_map(fn ($value) => $this->money($value), $discounts);
    }

    private function normalizedPumpNetSalesSnapshot(object $reading): ?array
    {
        if (! property_exists($reading, 'pump_net_sales')) {
            return null;
        }

        $rows = $reading->pump_net_sales;

        if (is_string($rows)) {
            $rows = json_decode($rows, true);
        } elseif (is_object($rows)) {
            $rows = (array) $rows;
        }

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $normalized = [];

        foreach ($rows as $key => $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }

            if (! is_array($row)) {
                continue;
            }

            $pump = (int) ($row['pump_id'] ?? (is_numeric($key) ? $key : 0));

            if ($pump >= 1 && $pump <= self::PUMP_COUNT) {
                $row['pump_id'] = $pump;
                $normalized[] = $row;
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    private function hasPumpDiscounts(array $discountsByPump): bool
    {
        foreach ($discountsByPump as $discount) {
            if (abs((float) $discount) >= 0.01) {
                return true;
            }
        }

        return false;
    }

    private function pumpIdFromBreakdownRow($row): int
    {
        $pump = (int) ($this->breakdownRowValue($row, 'pump_id') ?? 0);

        if ($pump > 0) {
            return $pump;
        }

        $pumpLabel = (string) ($this->breakdownRowValue($row, 'pump_label') ?? '');

        return preg_match('/pump\s*(\d+)/i', $pumpLabel, $matches) ? (int) $matches[1] : 0;
    }

    private function breakdownRowValue($row, string $field)
    {
        if (is_array($row)) {
            return $row[$field] ?? null;
        }

        if (is_object($row)) {
            return $row->$field ?? null;
        }

        return null;
    }

    private function pumpFuelLabel(int $pump): string
    {
        return [
            1 => 'Premium',
            2 => 'Unleaded',
            3 => 'Premium',
            4 => 'Unleaded',
            5 => 'Diesel',
            6 => 'Diesel',
        ][$pump] ?? 'Fuel';
    }

    private function getReadingPeriodContext(object $reading): array
    {
        $from = $reading->period_from ?? ($reading->created_at ?? now());
        $to = $reading->period_to ?? $from;

        try {
            $periodStart = Carbon::parse($from)->startOfDay();
            $periodEnd = Carbon::parse($to)->endOfDay();
        } catch (\Throwable) {
            $periodStart = Carbon::today()->startOfDay();
            $periodEnd = Carbon::today()->endOfDay();
        }

        return [$this->previousVerifiedReading($reading), $periodStart, $periodEnd];
    }

    private function previousVerifiedReading(object $reading)
    {
        if (! $this->hasTable('shift_readings') || ! $this->hasColumn('shift_readings', 'id') || ! isset($reading->id)) {
            return null;
        }

        $query = DB::table('shift_readings')
            ->where('id', '<>', $reading->id);

        if ($this->hasColumn('shift_readings', 'status')) {
            $query->where('status', 'Verified');
        }

        if ($this->hasColumn('shift_readings', 'user_id') && isset($reading->user_id)) {
            $query->where('user_id', $reading->user_id);
        }

        if ($this->hasColumn('shift_readings', 'period_to') && isset($reading->period_from)) {
            if ($this->hasColumn('shift_readings', 'created_at') && isset($reading->created_at)) {
                $query->where(function ($query) use ($reading) {
                    $query->whereDate('period_to', '<', $reading->period_from)
                        ->orWhere(function ($sameDate) use ($reading) {
                            $sameDate->whereDate('period_to', '=', $reading->period_from)
                                ->where(function ($samePeriod) use ($reading) {
                                    $samePeriod->where('created_at', '<', $reading->created_at)
                                        ->orWhere(function ($sameTimestamp) use ($reading) {
                                            $sameTimestamp->where('created_at', '=', $reading->created_at)
                                                ->where('id', '<', $reading->id);
                                        });
                                });
                        });
                });
            } else {
                $query->whereDate('period_to', '<', $reading->period_from);
            }
        }

        return $query
            ->when($this->hasColumn('shift_readings', 'period_to'), fn ($query) => $query->orderByDesc('period_to'))
            ->when($this->hasColumn('shift_readings', 'created_at'), fn ($query) => $query->orderByDesc('created_at'))
            ->orderByDesc('id')
            ->first();
    }

    private function readingSalesAdjustments(object $reading, Carbon $periodStart, Carbon $periodEnd): object
    {
        if (! $this->hasTable('sales')) {
            return $this->emptyReadingAdjustments();
        }

        $creditSalesExpression = $this->hasColumn('sales', 'payment_type') && $this->hasColumn('sales', 'amount')
            ? "COALESCE(SUM(CASE WHEN LOWER(payment_type) = 'credit' THEN amount ELSE 0 END), 0)"
            : '0';
        $collectionsExpression = $this->hasColumn('sales', 'payment_type') && $this->hasColumn('sales', 'amount')
            ? "COALESCE(SUM(CASE WHEN LOWER(payment_type) = 'collection' THEN amount ELSE 0 END), 0)"
            : '0';
        $autoDiscountExpression = $this->hasColumn('sales', 'discount')
            ? 'COALESCE(SUM(discount), 0)'
            : '0';
        $manualDiscountExpression = $this->hasColumn('sales', 'manual_discount')
            ? 'COALESCE(SUM(manual_discount), 0)'
            : '0';
        $surchargeExpression = $this->hasColumn('sales', 'surcharge')
            ? 'COALESCE(SUM(surcharge), 0)'
            : '0';

        return $this->scopeSalesToReading(DB::table('sales'), $reading, $periodStart, $periodEnd)
            ->selectRaw("
                {$creditSalesExpression} as credit_sales,
                {$collectionsExpression} as total_collections,
                {$autoDiscountExpression} as total_auto_discount,
                {$manualDiscountExpression} as total_manual_discount,
                {$surchargeExpression} as total_surcharge
            ")
            ->first() ?: $this->emptyReadingAdjustments();
    }

    private function meterImages(int $readingId)
    {
        if (
            ! $this->hasTable('shift_reading_images')
            || ! $this->hasColumn('shift_reading_images', 'shift_reading_id')
        ) {
            return collect();
        }

        return DB::table('shift_reading_images')
            ->where('shift_reading_id', $readingId)
            ->when($this->hasColumn('shift_reading_images', 'image_type'), fn ($query) => $query->orderBy('image_type'))
            ->when($this->hasColumn('shift_reading_images', 'dispenser'), fn ($query) => $query->orderBy('dispenser'))
            ->get()
            ->map(function ($image) {
                $image->dispenser = (int) ($image->dispenser ?? 0);
                $image->image_type = strtolower(trim((string) ($image->image_type ?? '')));
                $imageRouteParameters = [
                    'reading' => $image->shift_reading_id,
                    'dispenser' => $image->dispenser,
                    'type' => $image->image_type,
                ];

                if (isset($image->id)) {
                    $imageRouteParameters['image'] = $image->id;
                }

                $image->image_url = $image->dispenser > 0 && $image->image_type !== ''
                    ? route('admin.reconcile.image', $imageRouteParameters)
                    : null;

                return $image;
            })
            ->values();
    }

    private function getTransactionBreakdowns(object $reading, bool $includeVoidedHistorical = false): array
    {
        $empty = [
            'credit_sales' => [],
            'payments_collected' => [],
            'excess_markups' => [],
            'system_discounts' => [],
            'manual_discounts' => [],
        ];

        if (! $this->hasTable('sales')) {
            return $empty;
        }

        [, $periodStart, $periodEnd] = $this->getReadingPeriodContext($reading);
        $canJoinCustomers = $this->hasTable('customers')
            && $this->hasColumn('sales', 'customer_id')
            && $this->hasColumn('customers', 'id');

        $query = DB::table('sales');

        if ($canJoinCustomers) {
            $query->leftJoin('customers', 'sales.customer_id', '=', 'customers.id');
        }

        $query->select([
            $this->selectColumn('sales', 'id', 'id', '0'),
            $this->selectColumn('sales', 'or_number', 'or_number'),
            $this->selectColumn('sales', 'reference_no', 'reference_no'),
            $this->selectColumn('sales', 'pump_id', 'pump_id', '0'),
            $this->selectColumn('sales', 'payment_type', 'payment_type'),
            $this->selectColumn('sales', 'fuel_type', 'fuel_type'),
            $this->selectColumn('sales', 'liters', 'liters', '0'),
            $this->selectColumn('sales', 'price_applied', 'price_applied', '0'),
            $this->selectColumn('sales', 'amount', 'amount', '0'),
            $this->selectColumn('sales', 'discount', 'discount', '0'),
            $this->selectColumn('sales', 'manual_discount', 'manual_discount', '0'),
            $this->selectColumn('sales', 'surcharge', 'surcharge', '0'),
            $this->selectColumn('sales', 'customer_name', 'walkin_customer_name'),
            $this->selectColumn('sales', 'plate_no', 'plate_no'),
            $this->selectColumn('sales', 'invoice_id', 'invoice_id'),
            $this->selectColumn('sales', 'created_at', 'created_at'),
            $canJoinCustomers ? $this->selectColumn('customers', 'business_name', 'business_name') : DB::raw('NULL as business_name'),
            $canJoinCustomers ? $this->selectColumn('customers', 'owner_name', 'owner_name') : DB::raw('NULL as owner_name'),
        ]);

        $this->scopeSalesToReading($query, $reading, $periodStart, $periodEnd, $includeVoidedHistorical)
            ->where(function ($query) {
                $query->whereRaw('1 = 0');

                if ($this->hasColumn('sales', 'payment_type')) {
                    $query->orWhereRaw("LOWER(sales.payment_type) IN ('credit', 'collection')");
                }

                if ($this->hasColumn('sales', 'discount')) {
                    $query->orWhere('sales.discount', '>', 0);
                }

                if ($this->hasColumn('sales', 'manual_discount')) {
                    $query->orWhere('sales.manual_discount', '>', 0);
                }

                if ($this->hasColumn('sales', 'surcharge')) {
                    $query->orWhere('sales.surcharge', '>', 0);
                }
            });

        if ($this->hasColumn('sales', 'created_at')) {
            $query->orderBy('sales.created_at');
        }

        $transactions = $query->get()
            ->map(function ($sale) {
                $businessName = trim((string) ($sale->business_name ?? ''));
                $ownerName = trim((string) ($sale->owner_name ?? ''));
                $walkinName = trim((string) ($sale->walkin_customer_name ?? ''));
                $hasBusinessName = $businessName !== '' && strtoupper($businessName) !== 'N/A';
                $hasOwnerName = $ownerName !== '' && strtoupper($ownerName) !== 'N/A';
                $hasWalkinName = $walkinName !== '' && strtoupper($walkinName) !== 'N/A';

                if ($hasBusinessName) {
                    $customerDisplay = $businessName;

                    if ($hasOwnerName && strcasecmp($businessName, $ownerName) !== 0) {
                        $customerDisplay .= ' - ' . $ownerName;
                    }
                } elseif ($hasOwnerName) {
                    $customerDisplay = $ownerName;
                } elseif ($hasWalkinName) {
                    $customerDisplay = $walkinName;
                } else {
                    $customerDisplay = 'Walk-In Customer';
                }

                $sale->reference_no = $sale->reference_no ?: 'TXN-' . ($sale->id ?? '0');
                $sale->recorded_at = $sale->created_at ? Carbon::parse($sale->created_at)->format('M d, Y h:i A') : 'No date';
                $sale->company_name = $hasBusinessName ? $businessName : 'No company';
                $sale->customer_name = $hasOwnerName ? $ownerName : ($hasWalkinName ? $walkinName : 'Walk-In Customer');
                $sale->customer_display = $customerDisplay;
                $sale->pump_label = (int) ($sale->pump_id ?? 0) > 0 ? 'Pump ' . (int) $sale->pump_id : 'N/A';
                $sale->product_label = strtolower((string) $sale->payment_type) === 'collection'
                    ? ($sale->fuel_type ?: 'Payment')
                    : ($sale->fuel_type ?: 'N/A');
                $sale->liters = $this->liters($sale->liters ?? 0);
                $sale->price_applied = $this->money($sale->price_applied ?? 0);
                $sale->amount = $this->money($sale->amount ?? 0);
                $sale->discount = $this->money($sale->discount ?? 0);
                $sale->manual_discount = $this->money($sale->manual_discount ?? 0);
                $sale->surcharge = $this->money($sale->surcharge ?? 0);

                return $sale;
            });

        return [
            'credit_sales' => $transactions
                ->filter(fn ($sale) => strtolower((string) $sale->payment_type) === 'credit')
                ->values()
                ->all(),
            'payments_collected' => $transactions
                ->filter(fn ($sale) => strtolower((string) $sale->payment_type) === 'collection')
                ->values()
                ->all(),
            'excess_markups' => $transactions
                ->filter(fn ($sale) => (float) $sale->surcharge > 0)
                ->values()
                ->all(),
            'system_discounts' => $transactions
                ->filter(fn ($sale) => (float) $sale->discount > 0)
                ->values()
                ->all(),
            'manual_discounts' => $transactions
                ->filter(fn ($sale) => (float) $sale->manual_discount > 0)
                ->values()
                ->all(),
        ];
    }

    private function scopeSalesToReading($query, object $reading, Carbon $periodStart, Carbon $periodEnd, bool $includeVoidedHistorical = false)
    {
        if ($this->settlementIsVoided($reading)) {
            if ($includeVoidedHistorical) {
                return $this->scopeVoidedSettlementHistoricalSales($query, $reading);
            }

            return $query->whereRaw('1 = 0');
        }

        $this->excludeVoidedCustomerPayments($query);

        if ($this->readingHasLockedSales($reading)) {
            return $query->where('sales.locked_by_shift_reading_id', $reading->id);
        }

        if ($this->hasColumn('sales', 'user_id') && isset($reading->user_id)) {
            $query->where('sales.user_id', $reading->user_id);
        }

        if ($this->hasColumn('sales', 'created_at')) {
            $query->whereBetween('sales.created_at', [$periodStart, $periodEnd]);
        }

        return $query;
    }

    private function readingHasLockedSales(object $reading): bool
    {
        if (! $this->hasTable('sales') || ! $this->hasColumn('sales', 'locked_by_shift_reading_id') || ! isset($reading->id)) {
            return false;
        }

        $readingId = (int) $reading->id;

        if (! array_key_exists($readingId, $this->lockedSalesPresence)) {
            $query = DB::table('sales')
                ->where('locked_by_shift_reading_id', $readingId);

            $this->excludeVoidedCustomerPayments($query);

            $this->lockedSalesPresence[$readingId] = $query->exists();
        }

        return $this->lockedSalesPresence[$readingId];
    }

    private function lockSalesForReading(object $reading): void
    {
        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'locked_by_shift_reading_id')
            || ! isset($reading->id)
        ) {
            return;
        }

        [, $periodStart, $periodEnd] = $this->getReadingPeriodContext($reading);
        $query = DB::table('sales')
            ->whereNull('locked_by_shift_reading_id');

        $this->excludeVoidedCustomerPayments($query);

        if ($this->hasColumn('sales', 'user_id') && isset($reading->user_id)) {
            $query->where('user_id', $reading->user_id);
        }

        if ($this->hasColumn('sales', 'created_at')) {
            $query->whereBetween('created_at', [$periodStart, $periodEnd]);
        }

        $updates = ['locked_by_shift_reading_id' => $reading->id];

        if ($this->hasColumn('sales', 'locked_at')) {
            $updates['locked_at'] = $periodEnd;
        }

        $query->update($updates);
    }

    private function excludeVoidedCustomerPayments($query)
    {
        if ($this->hasColumn('sales', 'customer_payment_voided_at')) {
            $query->whereNull('sales.customer_payment_voided_at');
        }

        return $query;
    }

    private function unlockSalesForReading(object $reading): void
    {
        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'locked_by_shift_reading_id')
            || ! isset($reading->id)
        ) {
            return;
        }

        $updates = ['locked_by_shift_reading_id' => null];

        if ($this->hasColumn('sales', 'locked_at')) {
            $updates['locked_at'] = null;
        }

        DB::table('sales')
            ->where('locked_by_shift_reading_id', (int) $reading->id)
            ->update($updates);

        unset($this->lockedSalesPresence[(int) $reading->id]);
    }

    private function applyPendingCollections(object $reading, Carbon $periodStart, Carbon $periodEnd): void
    {
        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'payment_type')
            || ! $this->hasColumn('sales', 'amount')
        ) {
            return;
        }

        $query = $this->scopeSalesToReading(DB::table('sales'), $reading, $periodStart, $periodEnd)
            ->whereRaw("LOWER(payment_type) = 'collection'");

        if ($this->hasColumn('sales', 'customer_payment_posted_at')) {
            $query->whereNull('customer_payment_posted_at');
        }

        if ($this->hasColumn('sales', 'customer_payment_voided_at')) {
            $query->whereNull('customer_payment_voided_at');
        }

        $query->get()->each(function ($collection) use ($reading) {
            $this->applyCollectionToCustomer($collection, $reading);
        });
    }

    private function applyCollectionToCustomer(object $collection, object $reading): void
    {
        if (
            ! $this->hasTable('customers')
            || ! $this->hasColumn('sales', 'customer_id')
            || ! $this->hasColumn('customers', 'id')
            || ! $this->hasColumn('customers', 'current_balance')
            || empty($collection->customer_id)
        ) {
            return;
        }

        $customer = DB::table('customers')->where('id', $collection->customer_id)->first();

        if (! $customer) {
            return;
        }

        $newBalance = $this->money(($customer->current_balance ?? 0) - ($collection->amount ?? 0));
        $this->updateCustomerBalance((int) $customer->id, $newBalance);
        $referenceNo = trim((string) ($collection->reference_no ?? '')) ?: $this->generateTransactionReference();

        if ($this->hasTable('customer_ledgers')) {
            $this->insertForExistingColumns('customer_ledgers', [
                'customer_id' => $customer->id,
                'transaction_date' => $collection->created_at ?? now(),
                'type' => 'Payment',
                'reference_no' => $referenceNo,
                'or_number' => trim((string) ($collection->or_number ?? '')) ?: null,
                'description' => 'Payment via ' . (($collection->fuel_type ?? null) ?: 'Cash') . " (Verified from Shift #{$reading->id})",
                'debit' => 0,
                'credit' => $collection->amount ?? 0,
                'invoice_id' => $collection->invoice_id ?? null,
                'running_balance' => $newBalance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $saleUpdates = [
            'posted_customer_id' => $customer->id,
            'customer_payment_posted_at' => now(),
            'customer_payment_posted_by' => auth()->id(),
            'updated_at' => now(),
        ];

        if (empty($collection->reference_no)) {
            $saleUpdates['reference_no'] = $referenceNo;
        }

        $this->updateSale((int) $collection->id, $saleUpdates);
        $this->applyCollectionToInvoice($collection);
    }

    private function updateCustomerBalance(int $customerId, float $newBalance): void
    {
        $updates = ['current_balance' => $newBalance];

        if ($this->hasColumn('customers', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('customers')->where('id', $customerId)->update($updates);
    }

    private function updateSale(int $saleId, array $updates): void
    {
        if (! $this->hasTable('sales') || ! $this->hasColumn('sales', 'id')) {
            return;
        }

        $filtered = collect($updates)
            ->filter(fn ($value, $column) => $this->hasColumn('sales', (string) $column))
            ->all();

        if ($filtered !== []) {
            DB::table('sales')->where('id', $saleId)->update($filtered);
        }
    }

    private function applyCollectionToInvoice(object $collection): void
    {
        if (
            empty($collection->invoice_id)
            || ! $this->hasTable('invoices')
            || ! $this->hasColumn('invoices', 'id')
            || ! $this->hasColumn('invoices', 'payments_received')
        ) {
            return;
        }

        $invoice = DB::table('invoices')->where('id', $collection->invoice_id)->first();

        if (! $invoice) {
            return;
        }

        $paidSoFar = $this->money(($invoice->payments_received ?? 0) + ($collection->amount ?? 0));
        $updates = ['payments_received' => $paidSoFar];

        if ($this->hasColumn('invoices', 'status') && $this->hasColumn('invoices', 'total_amount_due')) {
            $updates['status'] = $paidSoFar >= (float) ($invoice->total_amount_due ?? 0) ? 'Paid' : 'Partial';
        }

        if ($this->hasColumn('invoices', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('invoices')->where('id', $collection->invoice_id)->update($updates);
    }

    private function reverseShiftApprovalCollections(object $reading): int
    {
        if (
            ! $this->hasTable('sales')
            || ! $this->hasColumn('sales', 'locked_by_shift_reading_id')
            || ! $this->hasColumn('sales', 'payment_type')
        ) {
            return 0;
        }

        $query = DB::table('sales')
            ->where('locked_by_shift_reading_id', (int) $reading->id)
            ->whereRaw("LOWER(payment_type) = 'collection'");

        if ($this->hasColumn('sales', 'customer_payment_posted_at')) {
            $query->whereNotNull('customer_payment_posted_at');
        }

        $collections = $query->lockForUpdate()->get();
        $reversed = 0;

        foreach ($collections as $collection) {
            $ledger = $this->findShiftApprovalPaymentLedger($reading, $collection);

            if (! $ledger) {
                continue;
            }

            $voidLedgers = $this->findShiftApprovalVoidPaymentLedgers($collection);
            $wasAlreadyVoided = ! empty($collection->customer_payment_voided_at);

            if (! $wasAlreadyVoided) {
                $this->reverseLedgerPaymentImpact($ledger);
            }

            $this->deleteCustomerLedger($ledger);

            foreach ($voidLedgers as $voidLedger) {
                $this->deleteCustomerLedger($voidLedger);
            }

            $this->updateSale((int) $collection->id, [
                'posted_customer_id' => null,
                'customer_name' => null,
                'customer_payment_posted_at' => null,
                'customer_payment_posted_by' => null,
                'customer_payment_authorized_by' => null,
                'customer_payment_voided_at' => null,
                'customer_payment_voided_by' => null,
                'customer_payment_void_authorized_by' => null,
                'updated_at' => now(),
            ]);

            $reversed++;
        }

        return $reversed;
    }

    private function findShiftApprovalPaymentLedger(object $reading, object $collection)
    {
        if (! $this->hasTable('customer_ledgers')) {
            return null;
        }

        $query = DB::table('customer_ledgers');

        if ($this->hasColumn('customer_ledgers', 'type')) {
            $query->where('type', 'Payment');
        }

        if ($this->hasColumn('customer_ledgers', 'description')) {
            $query->where('description', 'LIKE', '%Verified from Shift #' . (int) $reading->id . '%');
        }

        if ($this->hasColumn('customer_ledgers', 'credit')) {
            $query->where('credit', '>', 0);
        }

        $customerId = $collection->posted_customer_id ?? ($collection->customer_id ?? null);

        if (! empty($customerId) && $this->hasColumn('customer_ledgers', 'customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $referenceNo = trim((string) ($collection->reference_no ?? ''));

        if ($referenceNo !== '' && $this->hasColumn('customer_ledgers', 'reference_no')) {
            $query->where('reference_no', $referenceNo);
        } elseif (
            $this->hasColumn('customer_ledgers', 'transaction_date')
            && $this->hasColumn('customer_ledgers', 'credit')
            && isset($collection->created_at)
        ) {
            $query->where('transaction_date', $collection->created_at)
                ->where('credit', (float) ($collection->amount ?? 0));
        }

        $orNumber = trim((string) ($collection->or_number ?? ''));

        if ($orNumber !== '' && $this->hasColumn('customer_ledgers', 'or_number')) {
            $query->where(function ($orQuery) use ($orNumber) {
                $orQuery->where('or_number', $orNumber)
                    ->orWhereNull('or_number');
            });
        }

        return $query
            ->when($this->hasColumn('customer_ledgers', 'id'), fn ($query) => $query->orderByDesc('id'))
            ->lockForUpdate()
            ->first();
    }

    private function findShiftApprovalVoidPaymentLedgers(object $collection)
    {
        if (
            ! $this->hasTable('customer_ledgers')
            || ! $this->hasColumn('customer_ledgers', 'type')
            || ! $this->hasColumn('customer_ledgers', 'reference_no')
        ) {
            return collect();
        }

        return DB::table('customer_ledgers')
            ->where('type', 'Void Payment')
            ->where('reference_no', 'VOID-' . (int) ($collection->id ?? 0))
            ->lockForUpdate()
            ->get();
    }

    private function reverseLedgerPaymentImpact(object $ledger): void
    {
        $amount = $this->money($ledger->credit ?? 0);

        if ($amount <= 0) {
            return;
        }

        if (
            $this->hasTable('customers')
            && $this->hasColumn('customers', 'id')
            && $this->hasColumn('customers', 'current_balance')
            && ! empty($ledger->customer_id)
        ) {
            $customer = DB::table('customers')
                ->where('id', $ledger->customer_id)
                ->lockForUpdate()
                ->first();

            if ($customer) {
                $this->updateCustomerBalance((int) $customer->id, $this->money(($customer->current_balance ?? 0) + $amount));
            }
        }

        if (
            empty($ledger->invoice_id)
            || ! $this->hasTable('invoices')
            || ! $this->hasColumn('invoices', 'id')
            || ! $this->hasColumn('invoices', 'payments_received')
        ) {
            return;
        }

        $invoice = DB::table('invoices')
            ->where('id', $ledger->invoice_id)
            ->lockForUpdate()
            ->first();

        if (! $invoice) {
            return;
        }

        $newPaymentsReceived = max(0, $this->money(($invoice->payments_received ?? 0) - $amount));
        $updates = ['payments_received' => $newPaymentsReceived];

        if ($this->hasColumn('invoices', 'status')) {
            $updates['status'] = $this->invoiceStatusForPaymentsReceived($invoice, $newPaymentsReceived);
        }

        if ($this->hasColumn('invoices', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('invoices')->where('id', $invoice->id)->update($updates);
    }

    private function deleteCustomerLedger(object $ledger): void
    {
        if (! $this->hasTable('customer_ledgers') || ! $this->hasColumn('customer_ledgers', 'id') || empty($ledger->id)) {
            return;
        }

        DB::table('customer_ledgers')->where('id', $ledger->id)->delete();
    }

    private function voidUpdatePayload(string $reason): array
    {
        return [
            'status' => 'Voided',
            'voided_at' => now(),
            'voided_by' => auth()->id(),
            'void_reason' => trim($reason),
            'updated_at' => now(),
        ];
    }

    private function invoiceStatusForPaymentsReceived(object $invoice, float $paymentsReceived): string
    {
        if (($invoice->status ?? null) === 'Rolled Over') {
            return (string) $invoice->status;
        }

        if ($paymentsReceived <= 0) {
            return 'Unpaid';
        }

        if (! $this->hasColumn('invoices', 'total_amount_due')) {
            return (string) ($invoice->status ?? 'Partial');
        }

        return $paymentsReceived < (float) ($invoice->total_amount_due ?? 0)
            ? 'Partial'
            : 'Paid';
    }

    private function updateReadingStatus(int $id, string $status): void
    {
        if (! $this->hasColumn('shift_readings', 'status')) {
            return;
        }

        $this->updateShiftReading($id, ['status' => $status]);
    }

    private function updateShiftReading(int $id, array $updates): void
    {
        if (! $this->hasTable('shift_readings') || ! $this->hasColumn('shift_readings', 'id')) {
            return;
        }

        if ($this->hasColumn('shift_readings', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        $filtered = collect($updates)
            ->filter(fn ($value, $column) => $this->hasColumn('shift_readings', (string) $column))
            ->all();

        if ($filtered !== []) {
            DB::table('shift_readings')->where('id', $id)->update($filtered);
        }
    }

    private function insertForExistingColumns(string $table, array $payload): void
    {
        $filtered = collect($payload)
            ->filter(fn ($value, $column) => $this->hasColumn($table, (string) $column))
            ->all();

        if ($filtered !== []) {
            DB::table($table)->insert($filtered);
        }
    }

    private function generateTransactionReference(): string
    {
        do {
            $referenceNo = 'TXN-'
                . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                . chr(random_int(65, 90))
                . now()->format('Y');
        } while (
            ($this->hasTable('sales') && $this->hasColumn('sales', 'reference_no') && DB::table('sales')->where('reference_no', $referenceNo)->exists())
            || ($this->hasTable('customer_ledgers') && $this->hasColumn('customer_ledgers', 'reference_no') && DB::table('customer_ledgers')->where('reference_no', $referenceNo)->exists())
        );

        return $referenceNo;
    }

    private function storedImagePath(object $image): ?string
    {
        $rawPath = collect(['image_path', 'path', 'file_path', 'filename'])
            ->map(fn ($column) => $image->$column ?? null)
            ->first(fn ($value) => filled($value));

        if (! $rawPath) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', (string) $rawPath), '/');
        $candidates = [
            storage_path('app/public/' . $relativePath),
            storage_path('app/' . $relativePath),
            public_path('storage/' . $relativePath),
            public_path($relativePath),
            storage_path('app/public/meter_images/' . basename($relativePath)),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function selectColumn(string $table, string $column, string $alias, string $default = 'NULL')
    {
        if ($this->hasColumn($table, $column)) {
            return "{$table}.{$column} as {$alias}";
        }

        return DB::raw("{$default} as {$alias}");
    }

    private function summaryStats(array $filters = []): array
    {
        $summary = $this->emptySummary();

        if (! $this->hasTable('shift_readings')) {
            return $summary;
        }

        if ($this->hasVarianceFilter($filters)) {
            return $this->summaryStatsForComputedVariance($filters);
        }

        if (! $this->hasColumn('shift_readings', 'status')) {
            $summary['total'] = $this->applyReadingFilters(DB::table('shift_readings'), $filters)->count();

            return $summary;
        }

        $this->applyReadingFilters(DB::table('shift_readings'), $filters)
            ->selectRaw("LOWER(COALESCE(status, 'pending')) as status_key, COUNT(*) as total")
            ->groupBy('status_key')
            ->get()
            ->each(function ($row) use (&$summary) {
                $status = (string) $row->status_key;
                $count = (int) $row->total;
                $summary['total'] += $count;

                if (str_contains($status, 'verified') || str_contains($status, 'approved')) {
                    $summary['verified'] += $count;
                } elseif (str_contains($status, 'reject')) {
                    $summary['rejected'] += $count;
                } elseif (str_contains($status, 'void')) {
                    $summary['voided'] += $count;
                } else {
                    $summary['pending'] += $count;
                }
            });

        return $summary;
    }

    private function summaryStatsForComputedVariance(array $filters): array
    {
        $summary = $this->emptySummary();

        $this->applyReadingFilters($this->readingQuery(), $filters)
            ->get()
            ->map(function ($reading) {
                $this->calculateReadingData($reading);

                return $reading;
            })
            ->filter(fn ($reading) => $this->readingMatchesVarianceFilter($reading, $filters['variance'] ?? null))
            ->each(function ($reading) use (&$summary) {
                $status = strtolower((string) ($reading->status ?? 'pending'));

                $summary['total']++;

                if (str_contains($status, 'verified') || str_contains($status, 'approved')) {
                    $summary['verified']++;
                } elseif (str_contains($status, 'reject')) {
                    $summary['rejected']++;
                } elseif (str_contains($status, 'void')) {
                    $summary['voided']++;
                } else {
                    $summary['pending']++;
                }
            });

        return $summary;
    }

    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'verified' => 0,
            'rejected' => 0,
            'voided' => 0,
        ];
    }

    private function emptyPaginator(int $perPage = 10): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            LengthAwarePaginator::resolveCurrentPage('reconciliation_page'),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'reconciliation_page',
            ]
        );
    }

    private function emptyReadingAdjustments(): object
    {
        return (object) [
            'credit_sales' => 0,
            'total_collections' => 0,
            'total_auto_discount' => 0,
            'total_manual_discount' => 0,
            'total_surcharge' => 0,
        ];
    }

    private function decodeJsonDetails($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function firstReadingNumber(object $reading, array $fields): float
    {
        foreach ($fields as $field) {
            if (property_exists($reading, $field) && $reading->$field !== null && $reading->$field !== '') {
                return $this->number($reading->$field);
            }
        }

        return 0.0;
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

    private function number($value): float
    {
        return (float) ($value ?? 0);
    }

    private function money($value): float
    {
        return round($this->number($value), 2);
    }

    private function liters($value): float
    {
        return round($this->number($value), 2);
    }
}
