<?php

namespace App\Http\Controllers;

use App\Services\FuelInventoryReportService;
use Carbon\Carbon;
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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'opening' => ['nullable', 'array'],
            'opening.*.liters' => ['nullable', 'numeric', 'min:0'],
            'opening.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $missingTables = collect([
            'fuel_inventory_reports',
            'fuel_inventory_report_lines',
            'inventory_logs',
            'tank_delivery_records',
            'tanks',
            'shift_readings',
            'fuel_prices',
        ])->reject(fn (string $table): bool => Schema::hasTable($table))->values()->all();

        $latest = $reports->latestFinalizedReport();
        $viewingSavedReport = isset($validated['report_id']);
        $calculation = $viewingSavedReport
            ? $reports->storedReport((int) $validated['report_id'])
            : null;

        if ($viewingSavedReport && $calculation === null) {
            abort(404);
        }

        $expectedStart = $latest
            ? Carbon::parse($latest->period_end)->addDay()->toDateString()
            : now()->startOfMonth()->toDateString();
        $startDate = (string) ($validated['start_date'] ?? $expectedStart);
        $endDate = (string) ($validated['end_date'] ?? now()->toDateString());
        $manualOpening = $this->manualOpening($validated['opening'] ?? []);
        $opening = $latest ? $reports->openingFromReport($latest) : $manualOpening;

        if (! $viewingSavedReport && empty($missingTables)) {
            $calculation = $reports->calculate($startDate, $endDate, $opening);

            if ($latest && $startDate !== $expectedStart) {
                $calculation['warnings'][] = "For a continuous roll-forward, this report should start on {$expectedStart}.";
            }
        }

        return view('pages.admin.inventory.report', [
            'title' => 'Fuel Inventory Report',
            'calculation' => $calculation,
            'history' => $reports->reportHistory(),
            'latestReport' => $latest,
            'viewingSavedReport' => $viewingSavedReport,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'expectedStart' => $expectedStart,
            'manualOpening' => $manualOpening,
            'openingFromLastReport' => $latest ? $opening : null,
            'products' => FuelInventoryReportService::PRODUCTS,
            'missingTables' => $missingTables,
        ]);
    }

    public function store(Request $request, FuelInventoryReportService $reports): RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
            'opening' => ['nullable', 'array'],
            'opening.*.liters' => ['nullable', 'numeric', 'min:0'],
            'opening.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $manualOpening = $this->manualOpening($validated['opening'] ?? []);

        if (! $reports->latestFinalizedReport()) {
            foreach (FuelInventoryReportService::PRODUCTS as $fuel => $definition) {
                $line = $manualOpening[$fuel];

                if ($line['liters'] > 0 && $line['unit_cost'] <= 0) {
                    throw ValidationException::withMessages([
                        "opening.{$fuel}.unit_cost" => "Enter the opening unit cost for {$definition['product_name']}.",
                    ]);
                }
            }
        }

        try {
            $saved = $reports->finalize(
                $validated['start_date'],
                $validated['end_date'],
                $manualOpening,
                auth()->id()
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'start_date' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('inventory.reports.index', ['report_id' => $saved['id']])
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
