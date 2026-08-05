<?php

namespace App\Http\Controllers;

use App\Services\LitersMovementTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LitersMovementController extends Controller
{
    public function staff(Request $request, LitersMovementTrackingService $tracking): View
    {
        $state = $tracking->state((int) $request->user()->id, 20, 'tracking_page');

        return view('pages.staff.literstracking', [
            'title' => 'Liters Tracking',
            'period' => $state['period'],
            'summary' => $state['summary'],
            'transactions' => $state['transactions'],
            'missingRequirements' => $state['missingRequirements'],
            'canManageAdjustments' => $tracking->canAdjustLiters(),
        ]);
    }

    public function admin(LitersMovementTrackingService $tracking): View
    {
        $state = $tracking->state(null, 25, 'tracking_page', true);

        return view('pages.admin.litersmovement', [
            'title' => 'Liters Movement & Inventory',
            'period' => $state['period'],
            'summary' => $state['summary'],
            'transactions' => $state['transactions'],
            'history' => $tracking->history(10, 'history_page'),
            'missingRequirements' => $state['missingRequirements'],
            'canManageAdjustments' => $tracking->canAdjustLiters(),
        ]);
    }

    public function updateLimit(Request $request, LitersMovementTrackingService $tracking): RedirectResponse
    {
        $validated = $request->validate([
            'liters_limit' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'tank_limits' => ['nullable', 'array'],
            'tank_limits.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        try {
            $tankLimits = $validated['tank_limits'] ?? [];

            if ($tankLimits === [] && isset($validated['liters_limit'])) {
                $tankLimits = ['legacy' => (float) $validated['liters_limit']];
            }

            $tracking->updateActiveLimits($tankLimits);

            return back()->with('success', 'Current tank stock updated.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function generate(Request $request, LitersMovementTrackingService $tracking): RedirectResponse
    {
        try {
            $result = $tracking->generateAndReset((int) $request->user()->id);
            $periodCode = $result['period']->period_code ?? 'tracking period';
            $liters = number_format((float) ($result['summary']['total_liters'] ?? 0), 2);

            return back()->with('success', "Generated {$periodCode} with {$liters} liters and started a new active period.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function storeDeduction(Request $request, int $reading, LitersMovementTrackingService $tracking): RedirectResponse
    {
        $validated = $request->validate([
            'pump_number' => ['required', 'integer', 'in:1,2,3,4,5,6'],
            'liters' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reason' => ['nullable', 'string', 'max:255'],
            'semiadmin_password' => ['required', 'string'],
        ]);

        try {
            $authorizingSemiadmin = $this->findSemiadminByPassword($validated['semiadmin_password']);

            if (! $authorizingSemiadmin) {
                return back()
                    ->with('error', 'Invalid semi-admin password.')
                    ->withInput($request->except('semiadmin_password'));
            }

            $staffId = (int) $request->user()->id;
            $adjustment = $tracking->deductPumpLiters(
                $reading,
                (int) $validated['pump_number'],
                (float) $validated['liters'],
                (float) $validated['amount'],
                $staffId,
                (int) $authorizingSemiadmin->id,
                $validated['reason'] ?? null,
                $staffId
            );

            $liters = number_format((float) ($adjustment->liters ?? $validated['liters']), 2);
            $amount = number_format((float) ($adjustment->amount ?? $validated['amount']), 2);
            $pump = (int) ($adjustment->pump_number ?? $validated['pump_number']);

            return back()->with('success', "Deducted {$liters} L and ₱{$amount} from Pump {$pump}.");
        } catch (\Throwable $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput($request->except('semiadmin_password'));
        }
    }

    public function storeAdminDeduction(Request $request, int $reading, LitersMovementTrackingService $tracking): RedirectResponse
    {
        $validated = $request->validate([
            'pump_number' => ['required', 'integer', 'in:1,2,3,4,5,6'],
            'liters' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $adminId = (int) $request->user()->id;
            $adjustment = $tracking->deductPumpLiters(
                $reading,
                (int) $validated['pump_number'],
                (float) $validated['liters'],
                (float) $validated['amount'],
                $adminId,
                $adminId,
                $validated['reason'] ?? null
            );

            $liters = number_format((float) ($adjustment->liters ?? $validated['liters']), 2);
            $amount = number_format((float) ($adjustment->amount ?? $validated['amount']), 2);
            $pump = (int) ($adjustment->pump_number ?? $validated['pump_number']);

            return back()->with('success', "Deducted {$liters} L and ₱{$amount} from Pump {$pump}.");
        } catch (\Throwable $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function voidAdminDeduction(Request $request, int $deduction, LitersMovementTrackingService $tracking): RedirectResponse
    {
        $validated = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $adminId = (int) $request->user()->id;
            $adjustment = $tracking->voidDeduction(
                $deduction,
                $adminId,
                $adminId,
                $validated['void_reason'] ?? null,
                null,
                $adminId
            );

            $liters = number_format((float) ($adjustment->liters ?? 0), 2);
            $amount = number_format((float) ($adjustment->amount ?? 0), 2);
            $pump = (int) ($adjustment->pump_number ?? 0);

            return back()->with('success', "Voided your {$liters} L / ₱{$amount} deduction for Pump {$pump}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function voidDeduction(Request $request, int $deduction, LitersMovementTrackingService $tracking): RedirectResponse
    {
        $validated = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:255'],
            'semiadmin_password' => ['required', 'string'],
        ]);

        try {
            $authorizingSemiadmin = $this->findSemiadminByPassword($validated['semiadmin_password']);

            if (! $authorizingSemiadmin) {
                return back()->with('error', 'Invalid semi-admin password.');
            }

            $staffId = (int) $request->user()->id;
            $adjustment = $tracking->voidDeduction(
                $deduction,
                $staffId,
                (int) $authorizingSemiadmin->id,
                $validated['void_reason'] ?? null,
                $staffId
            );

            $liters = number_format((float) ($adjustment->liters ?? 0), 2);
            $pump = (int) ($adjustment->pump_number ?? 0);

            return back()->with('success', "Voided the {$liters} L deduction for Pump {$pump}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function voidGenerated(Request $request, int $period, LitersMovementTrackingService $tracking): RedirectResponse
    {
        try {
            $result = $tracking->voidGeneratedPeriod($period, (int) $request->user()->id);
            $periodCode = $result['period']->period_code ?? 'generated record';
            $liters = number_format((float) ($result['restored_liters'] ?? 0), 2);
            $submissions = number_format((int) ($result['restored_count'] ?? 0));

            return back()->with('success', "Voided {$periodCode}. Restored {$submissions} submissions with {$liters} liters to the active period.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function findSemiadminByPassword(string $password)
    {
        return DB::table('users')
            ->where('role', 'semiadmin')
            ->get()
            ->first(function ($user) use ($password) {
                return Hash::check($password, $user->password);
            });
    }
}
