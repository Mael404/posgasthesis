<?php

use App\Http\Controllers\AdminInventoryReportController;
use App\Services\FuelInventoryReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach ([
        'fuel_inventory_reports',
        'fuel_inventory_report_lines',
        'inventory_logs',
        'tank_delivery_records',
        'tanks',
        'shift_readings',
        'fuel_prices',
    ] as $table) {
        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
            });
        }
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the report page defaults to the active liters movement period', function () {
    Carbon::setTestNow('2026-07-22 12:00:00');

    $period = (object) [
        'id' => 10,
        'period_code' => 'LTM-ACTIVE',
        'status' => 'Active',
        'started_at' => '2026-07-20 08:00:00',
        'ended_at' => null,
    ];
    $reports = Mockery::mock(FuelInventoryReportService::class);
    $reports->shouldReceive('trackingPeriods')->once()->andReturn(collect([$period]));
    $reports->shouldReceive('latestFinalizedReport')->once()->andReturnNull();
    $reports->shouldReceive('defaultTrackingPeriod')->once()->andReturn($period);
    $reports->shouldReceive('finalizedReportForTrackingPeriod')->once()->with(10)->andReturnNull();
    $reports->shouldReceive('reportHistory')->once()->andReturn(collect());
    $reports->shouldNotReceive('calculate');

    $view = (new AdminInventoryReportController)->index(
        Request::create('/inventory/report', 'GET'),
        $reports
    );
    $data = $view->getData();

    expect($data['selectedPeriod']->id)->toBe(10)
        ->and($data['canFinalize'])->toBeFalse()
        ->and($data['calculation'])->toBeNull();
});
