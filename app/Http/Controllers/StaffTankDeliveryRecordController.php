<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use App\Models\TankDeliveryRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffTankDeliveryRecordController extends Controller
{
    private array $checklistItems = [
        'security_seals_passed' => 'Security Seals',
        'product_level_passed' => 'Product Level',
        'ethanol_blend_passed' => 'Ethanol Blend',
        'free_of_water_passed' => 'Free of Water',
        'fully_drained_compartment_passed' => 'Fully Drained Compartment',
    ];

    public function index(Request $request): View
    {
        $this->authorizeStaff();
        $this->ensureTablesReady();

        $pendingDate = $request->input('pending_date');
        $pendingTankType = $request->input('pending_tank_type');
        $hasPendingFilters = $request->filled('pending_date') || $request->filled('pending_tank_type');

        $pendingDeliveries = $this->pendingDeliveriesQuery($pendingDate, $pendingTankType)->get();
        $selectedDelivery = null;

        if ($request->filled('delivery_id')) {
            $selectedDelivery = $this->pendingDeliveriesQuery()
                ->where('inventory_logs.id', $request->integer('delivery_id'))
                ->first();
        } elseif ($request->filled('delivery_code')) {
            $selectedDelivery = $this->pendingDeliveriesQuery()
                ->where('inventory_logs.delivery_code', $request->delivery_code)
                ->first();
        }

        $records = DB::table('tank_delivery_records')
            ->join('inventory_logs', 'tank_delivery_records.inventory_log_id', '=', 'inventory_logs.id')
            ->join('tanks', 'inventory_logs.tank_id', '=', 'tanks.id')
            ->join('suppliers', 'inventory_logs.supplier_id', '=', 'suppliers.id')
            ->leftJoin('users', 'tank_delivery_records.staff_id', '=', 'users.id')
            ->select(
                'tank_delivery_records.*',
                'inventory_logs.delivery_date',
                'inventory_logs.delivery_status',
                'inventory_logs.liters_delivered',
                'inventory_logs.total_cost',
                DB::raw('(select count(*) from inventory_logs as batch_logs where batch_logs.delivery_code = inventory_logs.delivery_code) as delivery_batch_count'),
                DB::raw('(select count(*) from inventory_logs as batch_logs where batch_logs.delivery_code = inventory_logs.delivery_code and batch_logs.id <= inventory_logs.id) as delivery_batch_line'),
                'tanks.name as tank_name',
                'tanks.fuel_type',
                'suppliers.name as supplier_name',
                'users.name as staff_name'
            )
            ->orderByDesc('tank_delivery_records.created_at')
            ->orderByDesc('tank_delivery_records.id')
            ->paginate(15)
            ->withQueryString();

        return view('pages.staff.tankdeliveryrecords', [
            'title' => 'Tank Delivery Record',
            'pendingDeliveries' => $pendingDeliveries,
            'selectedDelivery' => $selectedDelivery,
            'records' => $records,
            'fuelTypes' => TankDeliveryRecord::FUEL_TYPES,
            'checklistItems' => $this->checklistItems,
            'pendingDate' => $pendingDate,
            'pendingTankType' => $pendingTankType,
            'hasPendingFilters' => $hasPendingFilters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStaff();
        $this->ensureTablesReady();

        $validated = $this->validateRecord($request);

        DB::transaction(function () use ($validated) {
            $delivery = DB::table('inventory_logs')
                ->where('id', $validated['inventory_log_id'])
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                throw ValidationException::withMessages([
                    'inventory_log_id' => 'The selected delivery code was not found.',
                ]);
            }

            if (empty($delivery->delivery_code)) {
                throw ValidationException::withMessages([
                    'inventory_log_id' => 'This delivery does not have a generated delivery code.',
                ]);
            }

            if ($delivery->delivery_status !== InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD) {
                throw ValidationException::withMessages([
                    'inventory_log_id' => 'This delivery already has a completed Tank Delivery Record.',
                ]);
            }

            $alreadyRecorded = DB::table('tank_delivery_records')
                ->where('inventory_log_id', $delivery->id)
                ->exists();

            if ($alreadyRecorded) {
                throw ValidationException::withMessages([
                    'inventory_log_id' => 'This delivery already has a Tank Delivery Record.',
                ]);
            }

            $recordData = array_merge([
                'inventory_log_id' => $delivery->id,
                'delivery_code' => $delivery->delivery_code,
                'staff_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ], $this->recordFieldsFromValidated($validated));

            DB::table('tank_delivery_records')->insert($recordData);

            DB::table('inventory_logs')
                ->where('id', $delivery->id)
                ->update([
                    'delivery_status' => InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED,
                ]);
        });

        return redirect()
            ->route('staff.tank-delivery-records.index')
            ->with('success', 'Tank Delivery Record submitted successfully.');
    }

    public function update(Request $request, int $record): RedirectResponse
    {
        $this->authorizeStaff();
        $this->ensureTablesReady();

        $validated = $this->validateRecord($request);

        DB::transaction(function () use ($validated, $record) {
            $existingRecord = DB::table('tank_delivery_records')
                ->where('id', $record)
                ->lockForUpdate()
                ->first();

            if (! $existingRecord) {
                abort(404);
            }

            if ((int) $existingRecord->inventory_log_id !== (int) $validated['inventory_log_id']) {
                throw ValidationException::withMessages([
                    'inventory_log_id' => 'The selected delivery code does not match this Tank Delivery Record.',
                ]);
            }

            $delivery = DB::table('inventory_logs')
                ->where('id', $existingRecord->inventory_log_id)
                ->lockForUpdate()
                ->first();

            if (! $delivery) {
                throw ValidationException::withMessages([
                    'inventory_log_id' => 'The selected delivery code was not found.',
                ]);
            }

            if (empty($delivery->delivery_code)) {
                throw ValidationException::withMessages([
                    'inventory_log_id' => 'This delivery does not have a generated delivery code.',
                ]);
            }

            DB::table('tank_delivery_records')
                ->where('id', $existingRecord->id)
                ->update(array_merge([
                    'delivery_code' => $delivery->delivery_code,
                    'updated_at' => now(),
                ], $this->recordFieldsFromValidated($validated)));

            DB::table('inventory_logs')
                ->where('id', $delivery->id)
                ->update([
                    'delivery_status' => InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED,
                ]);
        });

        return redirect()
            ->route('staff.tank-delivery-records.index')
            ->with('success', 'Tank Delivery Record updated successfully.');
    }

    public function destroy(int $record): RedirectResponse
    {
        $this->authorizeStaff();
        $this->ensureTablesReady();

        DB::transaction(function () use ($record) {
            $existingRecord = DB::table('tank_delivery_records')
                ->where('id', $record)
                ->lockForUpdate()
                ->first();

            if (! $existingRecord) {
                abort(404);
            }

            DB::table('tank_delivery_records')
                ->where('id', $existingRecord->id)
                ->delete();

            $hasRemainingRecord = DB::table('tank_delivery_records')
                ->where('inventory_log_id', $existingRecord->inventory_log_id)
                ->exists();

            if (! $hasRemainingRecord) {
                DB::table('inventory_logs')
                    ->where('id', $existingRecord->inventory_log_id)
                    ->update([
                        'delivery_status' => InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD,
                    ]);
            }
        });

        return redirect()
            ->route('staff.tank-delivery-records.index')
            ->with('success', 'Tank Delivery Record deleted successfully.');
    }

    private function validateRecord(Request $request): array
    {
        $rules = [
            'inventory_log_id' => ['required', 'integer', 'exists:inventory_logs,id'],
            'record_date' => ['required', 'date'],
            'record_time' => ['required', 'date_format:H:i'],
            'plate_number' => ['required', 'string', 'max:60'],
        ];

        foreach (array_keys($this->checklistItems) as $field) {
            $rules[$field] = ['required', 'in:0,1'];
        }

        $deliveryFuel = $this->fuelKeyForType($this->fuelTypeForInventoryLogId((int) $request->input('inventory_log_id')));
        $requiredFuelKeys = $deliveryFuel ? [$deliveryFuel] : array_keys(TankDeliveryRecord::FUEL_TYPES);

        foreach (array_keys(TankDeliveryRecord::FUEL_TYPES) as $fuel) {
            $presenceRule = in_array($fuel, $requiredFuelKeys, true) ? 'required' : 'nullable';

            $rules["before_unloading_dipstick_{$fuel}"] = [$presenceRule, 'numeric', 'min:0'];
            $rules["after_unloading_dipstick_{$fuel}"] = [$presenceRule, 'numeric', 'min:0'];
            $rules["total_dispensed_{$fuel}"] = [$presenceRule, 'numeric', 'min:0'];
        }

        $messages = [
            'inventory_log_id.required' => 'Please choose a pending delivery code first.',
            'inventory_log_id.exists' => 'The selected delivery code is no longer available.',
            'record_date.required' => 'Please enter the tanker delivery date.',
            'record_time.required' => 'Please enter the tanker delivery time.',
            'record_time.date_format' => 'Please enter the tanker delivery time using the HH:MM format.',
            'plate_number.required' => 'Please enter the tanker plate number.',
            '*.in' => 'Please mark each checklist item as Passed or Not Passed.',
            '*.required' => 'Please complete all required fields.',
            '*.numeric' => 'Dipstick and dispensed values must be valid numbers.',
            '*.min' => 'Dipstick and dispensed values cannot be negative.',
        ];

        foreach (TankDeliveryRecord::FUEL_TYPES as $fuel => $label) {
            $messages["before_unloading_dipstick_{$fuel}.required"] = "{$label} before unloading dipstick is required.";
            $messages["after_unloading_dipstick_{$fuel}.required"] = "{$label} after unloading dipstick is required.";
            $messages["total_dispensed_{$fuel}.required"] = "{$label} total dispensed is required.";
        }

        $checklistItems = $this->checklistItemsForFuel($this->fuelTypeForInventoryLogId((int) $request->input('inventory_log_id')));

        foreach ($checklistItems as $field => $label) {
            $messages["{$field}.required"] = "Please mark {$label} as Passed or Not Passed.";
        }

        $validator = Validator::make($request->all(), $rules, $messages);

        $validator->after(function ($validator) use ($request) {
            foreach (TankDeliveryRecord::FUEL_TYPES as $fuel => $label) {
                $before = $request->input("before_unloading_dipstick_{$fuel}");
                $after = $request->input("after_unloading_dipstick_{$fuel}");

                if ($before === null || $before === '' || $after === null || $after === '') {
                    continue;
                }

                if (is_numeric($before) && is_numeric($after) && (float) $after < (float) $before) {
                    $validator->errors()->add(
                        "after_unloading_dipstick_{$fuel}",
                        "{$label} after unloading dipstick cannot be lower than before unloading dipstick."
                    );
                }
            }
        });

        return $validator->validate();
    }

    private function recordFieldsFromValidated(array $validated): array
    {
        $recordData = [
            'inventory_log_id' => $validated['inventory_log_id'],
            'record_date' => $validated['record_date'],
            'record_time' => $validated['record_time'],
            'plate_number' => strtoupper(trim($validated['plate_number'])),
            'security_seals_passed' => (int) $validated['security_seals_passed'],
            'product_level_passed' => (int) $validated['product_level_passed'],
            'ethanol_blend_passed' => (int) $validated['ethanol_blend_passed'],
            'free_of_water_passed' => (int) $validated['free_of_water_passed'],
            'fully_drained_compartment_passed' => (int) $validated['fully_drained_compartment_passed'],
        ];

        foreach (array_keys(TankDeliveryRecord::FUEL_TYPES) as $fuel) {
            $before = $this->nullableDecimal($validated["before_unloading_dipstick_{$fuel}"] ?? null);
            $after = $this->nullableDecimal($validated["after_unloading_dipstick_{$fuel}"] ?? null);
            $dispensed = $this->nullableDecimal($validated["total_dispensed_{$fuel}"] ?? null);
            $afterBeforeTotal = ($before !== null && $after !== null) ? round($after - $before, 2) : null;
            $deliverySummary = ($afterBeforeTotal !== null && $dispensed !== null)
                ? round($dispensed + $afterBeforeTotal, 2)
                : null;

            $recordData["before_unloading_dipstick_{$fuel}"] = $before;
            $recordData["after_unloading_dipstick_{$fuel}"] = $after;
            $recordData["total_dispensed_{$fuel}"] = $dispensed;
            $recordData["after_before_total_{$fuel}"] = $afterBeforeTotal;
            $recordData["delivery_summary_{$fuel}"] = $deliverySummary;
        }

        return $recordData;
    }

    private function pendingDeliveriesQuery(?string $pendingDate = null, ?string $pendingTankType = null)
    {
        $query = DB::table('inventory_logs')
            ->join('tanks', 'inventory_logs.tank_id', '=', 'tanks.id')
            ->join('suppliers', 'inventory_logs.supplier_id', '=', 'suppliers.id')
            ->leftJoin('tank_delivery_records', 'inventory_logs.id', '=', 'tank_delivery_records.inventory_log_id')
            ->whereNotNull('inventory_logs.delivery_code')
            ->where('inventory_logs.delivery_status', InventoryLog::STATUS_PENDING_TANK_DELIVERY_RECORD)
            ->whereNull('tank_delivery_records.id');

        if ($pendingDate !== null && $pendingDate !== '') {
            $query->whereDate('inventory_logs.delivery_date', $pendingDate);
        }

        if ($pendingTankType !== null && $pendingTankType !== '') {
            $query->whereRaw('LOWER(tanks.fuel_type) = ?', [strtolower($pendingTankType)]);
        }

        return $query->select(
            'inventory_logs.id',
            'inventory_logs.delivery_code',
            'inventory_logs.delivery_date',
            'inventory_logs.liters_delivered',
            'inventory_logs.total_cost',
            'inventory_logs.created_at',
            DB::raw('(select count(*) from inventory_logs as batch_logs where batch_logs.delivery_code = inventory_logs.delivery_code) as delivery_batch_count'),
            DB::raw('(select count(*) from inventory_logs as batch_logs where batch_logs.delivery_code = inventory_logs.delivery_code and batch_logs.id <= inventory_logs.id) as delivery_batch_line'),
            'tanks.name as tank_name',
            'tanks.fuel_type',
            'suppliers.name as supplier_name'
        )
            ->orderByDesc('inventory_logs.delivery_date')
            ->orderByDesc('inventory_logs.created_at')
            ->orderByDesc('inventory_logs.id');
    }

    private function nullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function checklistItemsForFuel(?string $fuelType): array
    {
        $items = $this->checklistItems;
        $fuelType = strtolower((string) $fuelType);

        if (str_contains($fuelType, 'diesel')) {
            $items['ethanol_blend_passed'] = 'Visual Appearance';
        }

        return $items;
    }

    private function fuelTypeForInventoryLogId(?int $inventoryLogId): ?string
    {
        if (! $inventoryLogId || ! Schema::hasTable('inventory_logs') || ! Schema::hasTable('tanks')) {
            return null;
        }

        return DB::table('inventory_logs')
            ->join('tanks', 'inventory_logs.tank_id', '=', 'tanks.id')
            ->where('inventory_logs.id', $inventoryLogId)
            ->value('tanks.fuel_type');
    }

    private function fuelKeyForType(?string $fuelType): ?string
    {
        $fuelType = strtolower(trim((string) $fuelType));

        if ($fuelType === '') {
            return null;
        }

        if (str_contains($fuelType, 'regular')) {
            return 'unleaded';
        }

        foreach (TankDeliveryRecord::FUEL_TYPES as $fuel => $label) {
            $fuelLabel = strtolower($label);

            if ($fuelType === $fuel || $fuelType === $fuelLabel || str_contains($fuelType, $fuel) || str_contains($fuelType, $fuelLabel)) {
                return $fuel;
            }
        }

        return null;
    }

    private function authorizeStaff(): void
    {
        if (! auth()->check() || (auth()->user()->role ?? null) !== 'staff') {
            abort(403);
        }
    }

    private function ensureTablesReady(): void
    {
        if (! Schema::hasTable('tank_delivery_records')
            || ! Schema::hasTable('inventory_logs')
            || ! Schema::hasColumn('inventory_logs', 'delivery_code')
            || ! Schema::hasColumn('inventory_logs', 'delivery_status')) {
            abort(500, 'Tank Delivery Record tables are not ready. Please run php artisan migrate.');
        }
    }
}
