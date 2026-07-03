<?php

namespace App\Http\Controllers;

use App\Services\LitersMovementTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LitersMovementController extends Controller
{
    public function staff(LitersMovementTrackingService $tracking): View
    {
        $state = $tracking->state((int) auth()->id(), 20, 'tracking_page');

        return view('pages.staff.literstracking', [
            'title' => 'Liters Tracking',
            'period' => $state['period'],
            'summary' => $state['summary'],
            'transactions' => $state['transactions'],
            'missingRequirements' => $state['missingRequirements'],
        ]);
    }

    public function admin(LitersMovementTrackingService $tracking): View
    {
        $state = $tracking->state(null, 25, 'tracking_page');

        return view('pages.admin.litersmovement', [
            'title' => 'Liters Movement',
            'period' => $state['period'],
            'summary' => $state['summary'],
            'transactions' => $state['transactions'],
            'history' => $tracking->history(10, 'history_page'),
            'missingRequirements' => $state['missingRequirements'],
        ]);
    }

    public function updateLimit(Request $request, LitersMovementTrackingService $tracking): RedirectResponse
    {
        $validated = $request->validate([
            'liters_limit' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
            'tank_limits' => ['nullable', 'array'],
            'tank_limits.*' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
        ]);

        try {
            $tankLimits = $validated['tank_limits'] ?? [];

            if ($tankLimits === [] && isset($validated['liters_limit'])) {
                $tankLimits = ['legacy' => (float) $validated['liters_limit']];
            }

            $tracking->updateActiveLimits($tankLimits);

            return back()->with('success', 'Tank liters limits updated.');
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
}
