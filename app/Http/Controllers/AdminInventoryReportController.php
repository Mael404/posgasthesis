<?php

namespace App\Http\Controllers;

use App\Services\FuelInventoryReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminInventoryReportController extends Controller
{
    public function index(Request $request, FuelInventoryReportService $reports): View
    {
        $validated = $request->validate([
            'report_id' => ['nullable', 'integer'],
            'period_id' => ['nullable', 'integer'],
            'opening' => ['nullable', 'array'],
            'opening.*.liters' => ['nullable', 'numeric', 'min:0'],
            'opening.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $missingTables = collect([
            'fuel_inventory_reports',
            'fuel_inventory_report_lines',
            'inventory_logs',
            'tanks',
            'shift_readings',
            'fuel_prices',
            'liters_tracking_periods',
            'liters_movement_period_readings',
        ])->reject(fn (string $table): bool => Schema::hasTable($table))->values()->all();
        $missingColumns = [];

        if (Schema::hasTable('fuel_inventory_reports')
            && ! Schema::hasColumn('fuel_inventory_reports', 'liters_tracking_period_id')) {
            $missingColumns[] = 'fuel_inventory_reports.liters_tracking_period_id';
        }

        if (Schema::hasTable('fuel_inventory_report_lines')
            && ! Schema::hasColumn('fuel_inventory_report_lines', 'tank_limit_liters')) {
            $missingColumns[] = 'fuel_inventory_report_lines.tank_limit_liters';
        }

        if (Schema::hasTable('liters_tracking_periods')
            && ! Schema::hasColumn('liters_tracking_periods', 'opening_inventory')) {
            $missingColumns[] = 'liters_tracking_periods.opening_inventory';
        }

        $periods = $reports->trackingPeriods();
        $latest = $reports->latestFinalizedReport();
        $viewingSavedReport = isset($validated['report_id']);
        $calculation = $viewingSavedReport
            ? $reports->storedReport((int) $validated['report_id'])
            : null;

        if ($viewingSavedReport && $calculation === null) {
            abort(404);
        }

        $selectedPeriod = null;

        if (! $viewingSavedReport) {
            $requestedPeriodId = $validated['period_id'] ?? $request->old('period_id');
            $selectedPeriod = $requestedPeriodId
                ? $reports->trackingPeriod((int) $requestedPeriodId)
                : $reports->defaultTrackingPeriod();

            if ($requestedPeriodId && ! $selectedPeriod) {
                abort(404);
            }
        }

        $manualOpeningInput = $validated['opening'] ?? $request->old('opening', []);
        $manualOpening = $this->manualOpening($manualOpeningInput);

        if (! $latest
            && $selectedPeriod
            && empty($missingTables)
            && empty($missingColumns)) {
            $suggestedOpening = $reports->initialOpeningFromTrackingPeriod($selectedPeriod);

            foreach (array_keys(FuelInventoryReportService::PRODUCTS) as $fuel) {
                $manualOpening[$fuel]['liters'] = (float) ($suggestedOpening[$fuel]['liters'] ?? 0);

                if (! array_key_exists('unit_cost', $manualOpeningInput[$fuel] ?? [])) {
                    $manualOpening[$fuel]['unit_cost'] = (float) ($suggestedOpening[$fuel]['unit_cost'] ?? 0);
                }
            }
        }

        $opening = $latest ? $reports->openingFromReport($latest) : $manualOpening;
        $linkedReport = $selectedPeriod
            ? $reports->finalizedReportForTrackingPeriod((int) $selectedPeriod->id)
            : null;

        if (! $viewingSavedReport
            && $selectedPeriod
            && empty($missingTables)
            && empty($missingColumns)) {
            $calculation = $reports->calculate(
                $selectedPeriod->started_at,
                $selectedPeriod->ended_at ?? now(),
                $opening,
                (int) $selectedPeriod->id
            );
        }

        return view('pages.admin.inventory.report', [
            'title' => 'Liters Movement & Inventory',
            'calculation' => $calculation,
            'history' => $reports->reportHistory(),
            'latestReport' => $latest,
            'viewingSavedReport' => $viewingSavedReport,
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'linkedReport' => $linkedReport,
            'canFinalize' => $selectedPeriod
                && strtolower(trim((string) $selectedPeriod->status)) === 'generated'
                && ! $linkedReport,
            'manualOpening' => $manualOpening,
            'openingFromLastReport' => $latest ? $opening : null,
            'products' => FuelInventoryReportService::PRODUCTS,
            'missingTables' => $missingTables,
            'missingColumns' => $missingColumns,
        ]);
    }

    public function saveOpening(Request $request, FuelInventoryReportService $reports): RedirectResponse
    {
        $validated = $request->validate([
            'period_id' => ['required', 'integer'],
            'opening' => ['required', 'array'],
            'opening.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $reports->saveInitialOpening(
                (int) $validated['period_id'],
                $this->manualOpening($validated['opening'])
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'opening' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.liters-movement.index', [
                'tab' => 'inventory',
                'period_id' => (int) $validated['period_id'],
            ])
            ->with('success', 'Initial ending stock saved. Quantities came from the tank opening volumes and costs were calculated from the prices entered.');
    }

    public function store(Request $request, FuelInventoryReportService $reports): RedirectResponse
    {
        $validated = $request->validate([
            'period_id' => ['required', 'integer'],
            'opening' => ['nullable', 'array'],
            'opening.*.liters' => ['nullable', 'numeric', 'min:0'],
            'opening.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $manualOpening = $this->manualOpening($validated['opening'] ?? []);

        if (! $reports->latestFinalizedReport()) {
            $period = $reports->trackingPeriod((int) $validated['period_id']);
            $firstOpening = $period ? $reports->initialOpeningFromTrackingPeriod($period) : [];

            foreach (FuelInventoryReportService::PRODUCTS as $fuel => $definition) {
                $line = $firstOpening[$fuel] ?? $manualOpening[$fuel];

                if ($line['liters'] > 0 && $line['unit_cost'] <= 0) {
                    throw ValidationException::withMessages([
                        "opening.{$fuel}.unit_cost" => "Enter and save the opening price per liter for {$definition['product_name']}.",
                    ]);
                }
            }
        }

        try {
            $saved = $reports->finalize(
                (int) $validated['period_id'],
                $manualOpening,
                auth()->id()
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'period_id' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.liters-movement.index', [
                'tab' => 'inventory',
                'report_id' => $saved['id'],
            ])
            ->with('success', "Inventory report {$saved['report_code']} finalized. Its ending stock will be the next report's opening stock.");
    }

    private function manualOpening(array $input): array
    {
        $opening = [];

        foreach (FuelInventoryReportService::PRODUCTS as $fuel => $definition) {
            $line = $input[$fuel] ?? [];
            $opening[$fuel] = [
                'liters' => max(0, (float) ($line['liters'] ?? 0)),
                'unit_cost' => max(0, (float) ($line['unit_cost'] ?? 0)),
            ];
        }

        return $opening;
    }
}
