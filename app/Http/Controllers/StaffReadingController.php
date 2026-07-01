<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesVoidedSettlementDisplay;
use App\Services\ShiftReadingInventoryService;
use App\Support\StaffHeaderNotifications;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffReadingController extends Controller
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

    public function index(): View
    {
        $dataWarning = null;
        $latestReading = null;
        $unreadAdminRemarks = collect();
        $unreadAdminRemarksCount = 0;
        $readings = $this->emptyPaginator();

        if (! $this->hasTable('shift_readings')) {
            $dataWarning = 'Shift reading records are not available yet.';
        } else {
            $readings = $this->readingQuery()
                ->when($this->hasColumn('shift_readings', 'created_at'), fn ($query) => $query->orderByDesc('shift_readings.created_at'))
                ->when(! $this->hasColumn('shift_readings', 'created_at') && $this->hasColumn('shift_readings', 'id'), fn ($query) => $query->orderByDesc('shift_readings.id'))
                ->paginate(10, ['*'], 'readings_page')
                ->withQueryString();

            $readings->setCollection(
                $readings->getCollection()->map(function ($reading) {
                    $this->calculateReadingData($reading);

                    return $reading;
                })
            );

            $latestReading = $this->latestSubmittedReading();
            $unreadAdminRemarks = $this->unreadAdminRemarks();
            $unreadAdminRemarksCount = app(StaffHeaderNotifications::class)
                ->unreadShiftReadingRemarksCount((int) auth()->id());
        }

        return view('pages.staff.shiftreadings', [
            'title' => 'Shift Readings',
            'readings' => $readings,
            'prevReadings' => $this->previousFormReadings($latestReading),
            'latestReading' => $latestReading,
            'liveSales' => $this->liveSales(),
            'dataWarning' => $dataWarning,
            'unreadAdminRemarks' => $unreadAdminRemarks,
            'unreadAdminRemarksCount' => $unreadAdminRemarksCount,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($missing = $this->missingTables(['shift_readings', 'shift_reading_images', 'tanks'])) {
            return $this->jsonError('Missing table(s): ' . implode(', ', $missing) . '.', 422);
        }

        $validated = $request->validate($this->storeRules(), [], $this->storeAttributes());

        try {
            $readingId = null;

            DB::transaction(function () use ($request, $validated, &$readingId) {
                $periodStart = Carbon::parse($validated['period_from'])->startOfDay();
                $periodEnd = Carbon::parse($validated['period_to'])->endOfDay();

                $this->assertNoOverlappingReading((int) auth()->id(), $periodStart, $periodEnd);

                $cashDenominations = $this->normalizeCashDenominations($request->input('cash_denominations', []));
                $gcashEntries = $this->normalizePaymentEntries($request->input('gcash_entries', []), 'reference_no');
                $chequeEntries = $this->normalizePaymentEntries($request->input('cheque_entries', []), 'cheque_no');

                $this->assertPaymentEntriesComplete($gcashEntries, 'reference_no', 'GCash');
                $this->assertPaymentEntriesComplete($chequeEntries, 'cheque_no', 'Cheque');

                $cashOnHand = array_sum(array_column($cashDenominations, 'amount'));
                $gcashAmount = array_sum(array_column($gcashEntries, 'amount'));
                $chequeAmount = array_sum(array_column($chequeEntries, 'amount'));

                $payload = [
                    'user_id' => auth()->id(),
                    'period_from' => $periodStart->toDateString(),
                    'period_to' => $periodEnd->toDateString(),
                    'cash_on_hand' => $cashOnHand,
                    'gcash_amount' => $gcashAmount,
                    'cheque_amount' => $chequeAmount,
                    'cash_denominations' => json_encode($cashDenominations),
                    'gcash_entries' => json_encode($gcashEntries),
                    'cheque_entries' => json_encode($chequeEntries),
                    'status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                for ($pump = 1; $pump <= self::PUMP_COUNT; $pump++) {
                    foreach (['sales_start', 'sales_end', 'liters_start', 'liters_end'] as $suffix) {
                        $payload["pump{$pump}_{$suffix}"] = $validated["pump{$pump}_{$suffix}"];
                    }
                }

                foreach (['premium', 'regular', 'diesel'] as $fuel) {
                    $payload["dipstick_{$fuel}_start"] = $validated["dipstick_{$fuel}_start"];
                    $payload["dipstick_{$fuel}"] = $validated["dipstick_{$fuel}"];
                }

                $readingId = $this->insertForExistingColumnsAndGetId('shift_readings', $payload);
                $reading = DB::table('shift_readings')->where('id', $readingId)->first();

                if ($reading) {
                    $this->lockSalesForReading($reading);
                    $this->shiftReadingInventory->apply($reading);
                }

                foreach ($this->meterPhotoFields() as $field) {
                    $this->storeMeterImages(
                        $request,
                        $readingId,
                        $field['dispenser'],
                        $field['type'],
                        $field['name']
                    );
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Settlement submitted. Its meter-recorded fuel was deducted from the connected tanks and is pending verification.',
                'id' => $readingId,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    public function getDetails(int $id): JsonResponse
    {
        if (! $this->hasTable('shift_readings') || ! $this->hasColumn('shift_readings', 'id')) {
            return $this->jsonError('Shift readings are not available.', 404);
        }

        $reading = $this->readingQuery()
            ->where('shift_readings.id', $id)
            ->first();

        if (! $reading) {
            return $this->jsonError('Shift reading not found.', 404);
        }

        $this->calculateReadingData($reading);
        $this->markAdminRemarksSeen((int) $reading->id, $reading);

        return response()->json([
            'reading' => $reading,
            'meter_images' => $this->meterImages($id),
            'transaction_breakdowns' => $this->settlementDetailBreakdowns($reading),
            'read_admin_remark_id' => (int) $reading->id,
            'unread_admin_remarks_count' => app(StaffHeaderNotifications::class)
                ->unreadShiftReadingRemarksCount((int) auth()->id()),
        ]);
    }

    public function showImage(Request $request, int $reading, int $dispenser, string $type)
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
            ->join('shift_readings', 'shift_reading_images.shift_reading_id', '=', 'shift_readings.id')
            ->select('shift_reading_images.*', 'shift_readings.user_id')
            ->where('shift_reading_images.shift_reading_id', $reading)
            ->where('shift_reading_images.dispenser', $dispenser)
            ->whereRaw('LOWER(shift_reading_images.image_type) = ?', [strtolower($type)])
            ->where('shift_readings.user_id', auth()->id());

        $imageId = (int) $request->query('image', 0);

        if ($imageId > 0 && $this->hasColumn('shift_reading_images', 'id')) {
            $query->where('shift_reading_images.id', $imageId);
        }

        $image = $query
            ->when($this->hasColumn('shift_reading_images', 'id'), fn ($query) => $query->orderBy('shift_reading_images.id'))
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

    public function voidReading(Request $request, int $reading): JsonResponse
    {
        $validated = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->hasTable('shift_readings') || ! $this->hasColumn('shift_readings', 'id')) {
            return $this->jsonError('Shift readings are not available.', 404);
        }

        try {
            DB::transaction(function () use ($reading, $validated) {
                $settlement = DB::table('shift_readings')
                    ->where('id', $reading)
                    ->where('user_id', auth()->id())
                    ->lockForUpdate()
                    ->first();

                if (! $settlement) {
                    throw new \RuntimeException('Settlement not found.');
                }

                $status = strtolower((string) ($settlement->status ?? 'pending'));

                if ($status === 'verified' || $status === 'approved') {
                    throw new \RuntimeException('Approved settlements can only be voided by an admin.');
                }

                if ($status === 'voided') {
                    throw new \RuntimeException('This settlement has already been voided.');
                }

                if (! in_array($status, ['pending', 'rejected'], true)) {
                    throw new \RuntimeException('Only pending or rejected settlements can be voided here.');
                }

                $this->calculateReadingData($settlement);
                $snapshot = $this->settlementSnapshotPayload($settlement);

                $this->shiftReadingInventory->restore($settlement);
                $this->unlockSalesForReading($settlement);
                $this->updateShiftReading((int) $settlement->id, array_merge(
                    $this->voidUpdatePayload($validated['void_reason'] ?? null),
                    $snapshot
                ));
            });

            return response()->json([
                'success' => true,
                'message' => 'Settlement voided. Its tank inventory was restored and locked transactions are available for correction again.',
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
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

        if (
            $this->hasTable('users')
            && $this->hasColumn('shift_readings', 'admin_remarks_by')
            && $this->hasColumn('users', 'id')
            && $this->hasColumn('users', 'name')
        ) {
            $query->leftJoin('users as remark_admins', 'shift_readings.admin_remarks_by', '=', 'remark_admins.id');
            $selects[] = 'remark_admins.name as admin_remarks_author_name';
        } else {
            $selects[] = DB::raw("'Admin' as admin_remarks_author_name");
        }

        if ($this->hasColumn('shift_readings', 'user_id')) {
            $query->where('shift_readings.user_id', auth()->id());
        }

        return $query->select($selects);
    }

    private function unreadAdminRemarks(int $limit = 10)
    {
        if (! $this->canReadAdminRemarks()) {
            return collect();
        }

        $query = DB::table('shift_readings');
        $selects = [
            'shift_readings.id',
            'shift_readings.admin_remarks',
            'shift_readings.admin_remarks_seen_at',
            $this->selectColumn('shift_readings', 'period_from', 'period_from'),
            $this->selectColumn('shift_readings', 'period_to', 'period_to'),
            $this->selectColumn('shift_readings', 'created_at', 'created_at'),
        ];

        $timestampColumn = $this->adminRemarkTimestampColumn();
        $selects[] = $timestampColumn
            ? DB::raw("shift_readings.{$timestampColumn} as admin_remarks_timestamp")
            : DB::raw('NULL as admin_remarks_timestamp');

        if (
            $this->hasTable('users')
            && $this->hasColumn('shift_readings', 'admin_remarks_by')
            && $this->hasColumn('users', 'id')
            && $this->hasColumn('users', 'name')
        ) {
            $query->leftJoin('users as remark_admins', 'shift_readings.admin_remarks_by', '=', 'remark_admins.id');
            $selects[] = 'remark_admins.name as admin_remarks_author_name';
        } else {
            $selects[] = DB::raw("'Admin' as admin_remarks_author_name");
        }

        return $query
            ->select($selects)
            ->where('shift_readings.user_id', auth()->id())
            ->whereNotNull('shift_readings.admin_remarks')
            ->whereRaw("TRIM(COALESCE(shift_readings.admin_remarks, '')) <> ''")
            ->where(function ($query) use ($timestampColumn) {
                $query->whereNull('shift_readings.admin_remarks_seen_at');

                if ($timestampColumn) {
                    $query->orWhereColumn('shift_readings.admin_remarks_seen_at', '<', "shift_readings.{$timestampColumn}");
                }
            })
            ->when($timestampColumn, fn ($query) => $query->orderByDesc("shift_readings.{$timestampColumn}"))
            ->when(! $timestampColumn, fn ($query) => $query->orderByDesc('shift_readings.id'))
            ->limit($limit)
            ->get();
    }

    private function canReadAdminRemarks(): bool
    {
        return $this->hasTable('shift_readings')
            && $this->hasColumn('shift_readings', 'id')
            && $this->hasColumn('shift_readings', 'user_id')
            && $this->hasColumn('shift_readings', 'admin_remarks')
            && $this->hasColumn('shift_readings', 'admin_remarks_seen_at');
    }

    private function adminRemarkTimestampColumn(): ?string
    {
        foreach (['admin_remarks_updated_at', 'updated_at', 'created_at'] as $column) {
            if ($this->hasColumn('shift_readings', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function latestSubmittedReading()
    {
        if (! $this->hasTable('shift_readings')) {
            return null;
        }

        return DB::table('shift_readings')
            ->when($this->hasColumn('shift_readings', 'status'), fn ($query) => $query->where('status', '<>', 'Voided'))
            ->when($this->hasColumn('shift_readings', 'created_at'), fn ($query) => $query->orderByDesc('created_at'))
            ->when($this->hasColumn('shift_readings', 'id'), fn ($query) => $query->orderByDesc('id'))
            ->first();
    }

    private function previousFormReadings($latestReading): array
    {
        $values = [];

        for ($pump = 1; $pump <= self::PUMP_COUNT; $pump++) {
            $values["p{$pump}_sales"] = $this->number($latestReading?->{"pump{$pump}_sales_end"} ?? 0);
            $values["p{$pump}_liters"] = $this->number($latestReading?->{"pump{$pump}_liters_end"} ?? 0);
        }

        foreach (['premium', 'regular', 'diesel'] as $fuel) {
            $values["dipstick_{$fuel}"] = $this->number($latestReading?->{"dipstick_{$fuel}"} ?? 0);
        }

        return $values;
    }

    private function liveSales()
    {
        if (! $this->hasTable('sales')) {
            return collect();
        }

        $query = DB::table('sales');

        if ($this->hasColumn('sales', 'user_id')) {
            $query->where('user_id', auth()->id());
        }

        if ($this->hasColumn('sales', 'locked_by_shift_reading_id')) {
            $query->whereNull('locked_by_shift_reading_id');
        }

        if ($this->hasColumn('sales', 'customer_payment_voided_at')) {
            $query->whereNull('customer_payment_voided_at');
        }

        return $query
            ->select([
                $this->selectColumn('sales', 'payment_type', 'payment_type'),
                $this->selectColumn('sales', 'amount', 'amount', '0'),
                $this->selectColumn('sales', 'discount', 'discount', '0'),
                $this->selectColumn('sales', 'manual_discount', 'manual_discount', '0'),
                $this->selectColumn('sales', 'surcharge', 'surcharge', '0'),
                $this->selectColumn('sales', 'created_at', 'created_at'),
            ])
            ->get()
            ->map(function ($sale) {
                return [
                    'payment_type' => (string) ($sale->payment_type ?? ''),
                    'amount' => $this->money($sale->amount ?? 0),
                    'discount' => $this->money($sale->discount ?? 0),
                    'manual_discount' => $this->money($sale->manual_discount ?? 0),
                    'surcharge' => $this->money($sale->surcharge ?? 0),
                    'created_at' => $sale->created_at ? Carbon::parse($sale->created_at)->format('Y-m-d H:i:s') : null,
                ];
            })
            ->values();
    }

    private function storeRules(): array
    {
        $rules = [
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'cash_denominations' => ['nullable', 'array'],
            'cash_denominations.*' => ['nullable', 'integer', 'min:0'],
            'gcash_entries' => ['nullable', 'array'],
            'gcash_entries.*.reference_no' => ['nullable', 'string', 'max:100'],
            'gcash_entries.*.amount' => ['nullable', 'numeric', 'min:0'],
            'cheque_entries' => ['nullable', 'array'],
            'cheque_entries.*.cheque_no' => ['nullable', 'string', 'max:100'],
            'cheque_entries.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];

        for ($pump = 1; $pump <= self::PUMP_COUNT; $pump++) {
            $rules["pump{$pump}_sales_start"] = ['required', 'numeric'];
            $rules["pump{$pump}_sales_end"] = ['required', 'numeric', "gte:pump{$pump}_sales_start"];
            $rules["pump{$pump}_liters_start"] = ['required', 'numeric'];
            $rules["pump{$pump}_liters_end"] = ['required', 'numeric', "gte:pump{$pump}_liters_start"];
        }

        foreach (['premium', 'regular', 'diesel'] as $fuel) {
            $rules["dipstick_{$fuel}_start"] = ['required', 'numeric', 'min:0'];
            $rules["dipstick_{$fuel}"] = ['required', 'numeric', 'min:0'];
        }

        foreach ($this->meterPhotoFields() as $field) {
            $rules[$field['name']] = ['required', 'array', 'min:1'];
            $rules[$field['name'] . '.*'] = ['image', 'max:10240'];
        }

        return $rules;
    }

    private function storeAttributes(): array
    {
        $attributes = [];

        foreach ($this->meterPhotoFields() as $field) {
            $attributes[$field['name']] = $field['label'];
            $attributes[$field['name'] . '.*'] = $field['label'];
        }

        return $attributes;
    }

    private function meterPhotoFields(): array
    {
        $fields = [];
        $phases = [
            'opening' => 'Opening',
            'closing' => 'Closing',
        ];
        $metrics = [
            'liters' => 'Liters',
            'sales' => 'Sales',
        ];

        for ($dispenser = 1; $dispenser <= 3; $dispenser++) {
            foreach ($phases as $phase => $phaseLabel) {
                foreach ($metrics as $metric => $metricLabel) {
                    $fields[] = [
                        'name' => "dispenser_{$phase}_{$metric}_image_{$dispenser}",
                        'label' => "Dispenser {$dispenser} {$phaseLabel} {$metricLabel} photo",
                        'dispenser' => $dispenser,
                        'type' => "{$phase}_{$metric}",
                    ];
                }
            }
        }

        return $fields;
    }

    private function cashDenominationMap(): array
    {
        return [
            '1000' => ['label' => '1000', 'value' => 1000, 'type' => 'paper'],
            '500' => ['label' => '500', 'value' => 500, 'type' => 'paper'],
            '200' => ['label' => '200', 'value' => 200, 'type' => 'paper'],
            '100' => ['label' => '100', 'value' => 100, 'type' => 'paper'],
            '50' => ['label' => '50', 'value' => 50, 'type' => 'paper'],
            '20_paper' => ['label' => '20', 'value' => 20, 'type' => 'paper'],
            '20_coin' => ['label' => '20', 'value' => 20, 'type' => 'coin'],
            '10' => ['label' => '10', 'value' => 10, 'type' => 'coin'],
            '5' => ['label' => '5', 'value' => 5, 'type' => 'coin'],
            '1' => ['label' => '1', 'value' => 1, 'type' => 'coin'],
            '025' => ['label' => '25 cents', 'value' => 0.25, 'type' => 'coin'],
        ];
    }

    private function normalizeCashDenominations(array $input): array
    {
        $rows = [];

        foreach ($this->cashDenominationMap() as $key => $denomination) {
            $pieces = max(0, (int) ($input[$key] ?? 0));
            $amount = $pieces * (float) $denomination['value'];

            $rows[] = [
                'key' => $key,
                'label' => $denomination['label'],
                'type' => $denomination['type'],
                'value' => (float) $denomination['value'],
                'pieces' => $pieces,
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    private function normalizePaymentEntries(array $entries, string $referenceKey): array
    {
        return collect($entries)
            ->map(function ($entry) use ($referenceKey) {
                $reference = trim((string) ($entry[$referenceKey] ?? ''));
                $amount = max(0, (float) ($entry['amount'] ?? 0));

                return [
                    $referenceKey => $reference,
                    'amount' => $amount,
                ];
            })
            ->filter(fn ($entry) => $entry[$referenceKey] !== '' || $entry['amount'] > 0)
            ->values()
            ->all();
    }

    private function assertPaymentEntriesComplete(array $entries, string $referenceKey, string $label): void
    {
        foreach ($entries as $entry) {
            if ($entry[$referenceKey] === '' || $entry['amount'] <= 0) {
                throw ValidationException::withMessages([
                    $referenceKey => "{$label} reference and amount are required for every entered row.",
                ]);
            }
        }
    }

    private function assertNoOverlappingReading(int $userId, Carbon $periodStart, Carbon $periodEnd): void
    {
        if (
            ! $this->hasTable('shift_readings')
            || ! $this->hasColumn('shift_readings', 'period_from')
            || ! $this->hasColumn('shift_readings', 'user_id')
        ) {
            return;
        }

        $query = DB::table('shift_readings')
            ->where('user_id', $userId)
            ->whereDate('period_from', '<=', $periodEnd->toDateString());

        if ($this->hasColumn('shift_readings', 'status')) {
            $query->whereIn('status', ['Pending', 'Verified']);
        }

        if ($this->hasColumn('shift_readings', 'period_to')) {
            $query->where(function ($query) use ($periodStart) {
                $query->whereDate('period_to', '>=', $periodStart->toDateString())
                    ->orWhereNull('period_to');
            });
        } else {
            $query->whereDate('period_from', '>=', $periodStart->toDateString());
        }

        $selects = [
            'id',
            'period_from',
            $this->hasColumn('shift_readings', 'status') ? 'status' : DB::raw("'Pending' as status"),
            $this->hasColumn('shift_readings', 'period_to') ? 'period_to' : DB::raw('NULL as period_to'),
        ];

        $overlap = $query
            ->when($this->hasColumn('shift_readings', 'period_from'), fn ($query) => $query->orderBy('period_from'))
            ->when($this->hasColumn('shift_readings', 'id'), fn ($query) => $query->orderBy('id'))
            ->first($selects);

        if (! $overlap) {
            return;
        }

        $from = Carbon::parse($overlap->period_from)->format('M d, Y');
        $to = Carbon::parse($overlap->period_to ?: $overlap->period_from)->format('M d, Y');
        $status = ($overlap->status ?? 'Pending') === 'Pending' ? 'Pending Verification' : ($overlap->status ?? 'Pending');

        throw ValidationException::withMessages([
            'period_from' => "This period overlaps your {$status} settlement for {$from} to {$to}. Please choose a non-overlapping period.",
        ]);
    }

    private function calculateReadingData(object $reading): void
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
        $reading->dipstick_premium = $this->liters($this->firstReadingNumber($reading, ['dipstick_premium', 'premium_dipstick', 'premium_dipstick_reading']));
        $reading->dipstick_regular = $this->liters($this->firstReadingNumber($reading, ['dipstick_regular', 'dipstick_unleaded', 'regular_dipstick', 'unleaded_dipstick', 'regular_dipstick_reading', 'unleaded_dipstick_reading']));
        $reading->dipstick_diesel = $this->liters($this->firstReadingNumber($reading, ['dipstick_diesel', 'diesel_dipstick', 'diesel_dipstick_reading']));
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
        if (! $this->hasTable('shift_reading_images') || ! $this->hasColumn('shift_reading_images', 'shift_reading_id')) {
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
                    ? route('staff.readings.image', $imageRouteParameters, false)
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
            'credit_sales' => $transactions->filter(fn ($sale) => strtolower((string) $sale->payment_type) === 'credit')->values()->all(),
            'payments_collected' => $transactions->filter(fn ($sale) => strtolower((string) $sale->payment_type) === 'collection')->values()->all(),
            'excess_markups' => $transactions->filter(fn ($sale) => (float) $sale->surcharge > 0)->values()->all(),
            'system_discounts' => $transactions->filter(fn ($sale) => (float) $sale->discount > 0)->values()->all(),
            'manual_discounts' => $transactions->filter(fn ($sale) => (float) $sale->manual_discount > 0)->values()->all(),
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
            $this->lockedSalesPresence[$readingId] = DB::table('sales')
                ->where('locked_by_shift_reading_id', $readingId)
                ->exists();
        }

        return $this->lockedSalesPresence[$readingId];
    }

    private function lockSalesForReading(object $reading): void
    {
        if (! $this->hasTable('sales') || ! $this->hasColumn('sales', 'locked_by_shift_reading_id') || ! isset($reading->id)) {
            return;
        }

        [, $periodStart, $periodEnd] = $this->getReadingPeriodContext($reading);
        $query = DB::table('sales')->whereNull('locked_by_shift_reading_id');

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

    private function unlockSalesForReading(object $reading): void
    {
        if (! $this->hasTable('sales') || ! $this->hasColumn('sales', 'locked_by_shift_reading_id') || ! isset($reading->id)) {
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

    private function storeMeterImages(Request $request, int $readingId, int $dispenser, string $type, string $input): void
    {
        if (! $request->hasFile($input)) {
            return;
        }

        $files = $request->file($input, []);
        $files = is_array($files) ? $files : [$files];

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('meter_images', 'public');

            $this->insertForExistingColumns('shift_reading_images', [
                'shift_reading_id' => $readingId,
                'dispenser' => $dispenser,
                'image_type' => $type,
                'image_path' => $path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function voidUpdatePayload(?string $reason): array
    {
        return [
            'status' => 'Voided',
            'voided_at' => now(),
            'voided_by' => auth()->id(),
            'void_reason' => trim((string) $reason) !== '' ? trim((string) $reason) : null,
            'updated_at' => now(),
        ];
    }

    private function updateShiftReading(int $id, array $updates): void
    {
        $filtered = collect($updates)
            ->filter(fn ($value, $column) => $this->hasColumn('shift_readings', (string) $column))
            ->all();

        if ($filtered !== []) {
            DB::table('shift_readings')->where('id', $id)->update($filtered);
        }
    }

    private function markAdminRemarksSeen(int $id, object $reading): void
    {
        if (
            ! $this->hasColumn('shift_readings', 'admin_remarks')
            || ! $this->hasColumn('shift_readings', 'admin_remarks_seen_at')
            || trim((string) ($reading->admin_remarks ?? '')) === ''
        ) {
            return;
        }

        $seenAt = now();
        $query = DB::table('shift_readings')->where('id', $id);

        if ($this->hasColumn('shift_readings', 'user_id')) {
            $query->where('user_id', auth()->id());
        }

        $query->update(['admin_remarks_seen_at' => $seenAt]);
        $reading->admin_remarks_seen_at = $seenAt->toDateTimeString();
    }

    private function insertForExistingColumnsAndGetId(string $table, array $payload): int
    {
        $filtered = $this->onlyExistingColumns($table, $payload);

        if ($filtered === []) {
            throw new \RuntimeException("No writable columns were found for {$table}.");
        }

        return (int) DB::table($table)->insertGetId($filtered);
    }

    private function insertForExistingColumns(string $table, array $payload): void
    {
        $filtered = $this->onlyExistingColumns($table, $payload);

        if ($filtered !== []) {
            DB::table($table)->insert($filtered);
        }
    }

    private function onlyExistingColumns(string $table, array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value, $column) => $this->hasColumn($table, (string) $column))
            ->all();
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

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->path($relativePath);
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

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            10,
            LengthAwarePaginator::resolveCurrentPage('readings_page'),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'readings_page',
            ]
        );
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

    private function jsonError(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
