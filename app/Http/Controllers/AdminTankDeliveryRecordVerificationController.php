<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminTankDeliveryRecordVerificationController extends Controller
{
    public function verify(int $record): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureTablesReady();

        try {
            DB::transaction(function () use ($record) {
                $tankDeliveryRecord = DB::table('tank_delivery_records')
                    ->where('id', $record)
                    ->lockForUpdate()
                    ->first();

                if (! $tankDeliveryRecord) {
                    abort(404);
                }

                $inventoryLog = DB::table('inventory_logs')
                    ->where('id', $tankDeliveryRecord->inventory_log_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventoryLog) {
                    throw ValidationException::withMessages([
                        'record' => 'The related delivery log was not found.',
                    ]);
                }

                if (! $this->canVerifyStatus((string) $inventoryLog->delivery_status)) {
                    throw ValidationException::withMessages([
                        'record' => 'Only records pending admin verification can be verified.',
                    ]);
                }

                DB::table('tank_delivery_records')
                    ->where('id', $tankDeliveryRecord->id)
                    ->update($this->withUpdatedAt([
                        'verified_by' => auth()->id(),
                        'verified_at' => now(),
                        'rejected_by' => null,
                        'rejected_at' => null,
                        'rejection_reason' => null,
                    ]));

                DB::table('inventory_logs')
                    ->where('id', $inventoryLog->id)
                    ->update([
                        'delivery_status' => InventoryLog::STATUS_TANK_DELIVERY_RECORD_VERIFIED,
                    ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Tank Delivery Record verified successfully.');
    }

    public function reject(Request $request, int $record): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->ensureTablesReady();

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ], [
            'rejection_reason.required' => 'Please enter a rejection reason.',
        ]);

        try {
            DB::transaction(function () use ($record, $validated) {
                $tankDeliveryRecord = DB::table('tank_delivery_records')
                    ->where('id', $record)
                    ->lockForUpdate()
                    ->first();

                if (! $tankDeliveryRecord) {
                    abort(404);
                }

                $inventoryLog = DB::table('inventory_logs')
                    ->where('id', $tankDeliveryRecord->inventory_log_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventoryLog) {
                    throw ValidationException::withMessages([
                        'record' => 'The related delivery log was not found.',
                    ]);
                }

                if (! $this->canRejectStatus((string) $inventoryLog->delivery_status)) {
                    throw ValidationException::withMessages([
                        'record' => 'Only records pending admin verification can be rejected.',
                    ]);
                }

                DB::table('tank_delivery_records')
                    ->where('id', $tankDeliveryRecord->id)
                    ->update($this->withUpdatedAt([
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now(),
                        'rejection_reason' => trim($validated['rejection_reason']),
                        'verified_by' => null,
                        'verified_at' => null,
                    ]));

                DB::table('inventory_logs')
                    ->where('id', $inventoryLog->id)
                    ->update([
                        'delivery_status' => InventoryLog::STATUS_TANK_DELIVERY_RECORD_REJECTED,
                    ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Tank Delivery Record rejected. Staff can correct and resubmit.');
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->check() || (auth()->user()->role ?? null) !== User::ROLE_VP_ADMIN) {
            abort(403);
        }
    }

    private function ensureTablesReady(): void
    {
        if (! Schema::hasTable('tank_delivery_records')
            || ! Schema::hasTable('inventory_logs')
            || ! Schema::hasColumn('inventory_logs', 'delivery_status')) {
            abort(500, 'Tank Delivery Record tables are not ready. Please run php artisan migrate.');
        }

        foreach (['verified_by', 'verified_at', 'rejected_by', 'rejected_at', 'rejection_reason'] as $column) {
            if (! Schema::hasColumn('tank_delivery_records', $column)) {
                abort(500, 'Tank Delivery Record verification columns are missing. Please run php artisan migrate.');
            }
        }
    }

    private function canVerifyStatus(string $status): bool
    {
        return in_array($status, [
            InventoryLog::STATUS_PENDING_ADMIN_VERIFICATION,
            InventoryLog::STATUS_TANK_DELIVERY_RECORD_COMPLETED,
        ], true);
    }

    private function canRejectStatus(string $status): bool
    {
        return $this->canVerifyStatus($status);
    }

    private function withUpdatedAt(array $values): array
    {
        if (Schema::hasColumn('tank_delivery_records', 'updated_at')) {
            $values['updated_at'] = now();
        }

        return $values;
    }
}
