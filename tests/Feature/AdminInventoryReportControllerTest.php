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

test('the report page waits until the next report period is available', function () {
    Carbon::setTestNow('2026-07-22 12:00:00');

    $latest = (object) [
        'id' => 10,
        'period_end' => '2026-07-22',
    ];
    $reports = Mockery::mock(FuelInventoryReportService::class);
    $reports->shouldReceive('latestFinalizedReport')->once()->andReturn($latest);
    $reports->shouldReceive('openingFromReport')->once()->with($latest)->andReturn([]);
    $reports->shouldReceive('reportHistory')->once()->andReturn(collect());
    $reports->shouldNotReceive('calculate');

    $view = (new AdminInventoryReportController)->index(
        Request::create('/inventory/report', 'GET'),
        $reports
    );
    $data = $view->getData();

    expect($data['startDate'])->toBe('2026-07-23')
        ->and($data['endDate'])->toBe('2026-07-23')
        ->and($data['reportPeriodAvailable'])->toBeFalse()
        ->and($data['calculation'])->toBeNull();
});
