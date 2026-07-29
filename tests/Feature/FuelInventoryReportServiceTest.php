<?php

use App\Services\FuelInventoryReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach ([
        'liters_movement_period_readings',
        'liters_tracking_periods',
        'liters_movement_adjustments',
        'tank_delivery_records',
        'inventory_logs',
        'suppliers',
        'shift_readings',
        'fuel_prices',
        'tanks',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('tanks', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('fuel_type');
        $table->string('connected_pumps')->nullable();
    });

    Schema::create('suppliers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('inventory_logs', function (Blueprint $table) {
        $table->id();
        $table->string('delivery_code')->nullable();
        $table->foreignId('tank_id');
        $table->foreignId('supplier_id')->nullable();
        $table->decimal('liters_delivered', 12, 2);
        $table->decimal('total_cost', 14, 2);
        $table->date('delivery_date');
        $table->string('delivery_status');
    });

    Schema::create('tank_delivery_records', function (Blueprint $table) {
        $table->id();
        $table->foreignId('inventory_log_id');
        $table->date('record_date')->nullable();
        $table->time('record_time')->nullable();
    });

    Schema::create('shift_readings', function (Blueprint $table) {
        $table->id();
        $table->date('period_from');
        $table->date('period_to');
        $table->string('status');
        $table->foreignId('liters_tracking_period_id')->nullable();
        $table->timestamp('created_at')->nullable();

        for ($pump = 1; $pump <= 6; $pump++) {
            $table->decimal("pump{$pump}_liters_start", 14, 2)->default(0);
            $table->decimal("pump{$pump}_liters_end", 14, 2)->default(0);
            $table->decimal("pump{$pump}_sales_start", 14, 2)->default(0);
            $table->decimal("pump{$pump}_sales_end", 14, 2)->default(0);
        }
    });

    Schema::create('fuel_prices', function (Blueprint $table) {
        $table->id();
        $table->string('fuel_type');
        $table->decimal('price_per_liter', 12, 4);
        $table->decimal('bulk_price_per_liter', 12, 4)->nullable();
    });

    Schema::create('liters_movement_adjustments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('shift_reading_id');
        $table->unsignedTinyInteger('pump_number');
        $table->decimal('liters', 12, 2);
        $table->decimal('amount', 14, 2);
        $table->timestamp('voided_at')->nullable();
    });

    Schema::create('liters_tracking_periods', function (Blueprint $table) {
        $table->id();
        $table->string('period_code');
        $table->decimal('liters_limit', 16, 2)->default(0);
        $table->text('tank_limits')->nullable();
        $table->string('status');
        $table->timestamp('started_at');
        $table->timestamp('ended_at')->nullable();
    });

    Schema::create('liters_movement_period_readings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('liters_tracking_period_id');
        $table->foreignId('shift_reading_id');
        $table->string('inclusion_status')->default('included');
        $table->timestamp('removed_at')->nullable();
    });
});

test('active liters movement deductions reduce report liters and revenue', function () {
    DB::table('shift_readings')->insert(shiftReadingRow(10, '2026-07-15', 20, 1500));
    DB::table('liters_movement_adjustments')->insert([
        'shift_reading_id' => 10,
        'pump_number' => 1,
        'liters' => 2,
        'amount' => 150,
        'voided_at' => null,
    ]);

    $result = app(FuelInventoryReportService::class)->calculate(
        '2026-07-15',
        '2026-07-15',
        ['Premium' => ['liters' => 100, 'unit_cost' => 60]]
    );
    $premium = collect($result['lines'])->firstWhere('fuel_type', 'Premium');

    expect($premium['dispensed_liters'])->toBe(18.0)
        ->and($premium['sales_revenue'])->toBe(1350.0)
        ->and($premium['cost_of_goods_sold'])->toBe(1080.0)
        ->and($premium['tank_limit_liters'])->toBe(100.0)
        ->and($premium['ending_liters'])->toBe(82.0)
        ->and($premium['gross_profit'])->toBe(270.0)
        ->and($premium['source_summary']['shift_readings'][0]['adjustment_ids'])->toBe([1]);
});

test('current stock and fuel profit use one periodic weighted average cost', function () {
    DB::table('tanks')->insert([
        'id' => 1,
        'name' => 'Tank 1',
        'fuel_type' => 'Premium',
        'connected_pumps' => 'Pump 1, Pump 3',
    ]);
    DB::table('suppliers')->insert(['id' => 1, 'name' => 'Q & Q']);
    DB::table('fuel_prices')->insert([
        'fuel_type' => 'Premium',
        'price_per_liter' => 76,
        'bulk_price_per_liter' => 71.50,
    ]);

    DB::table('inventory_logs')->insert([
        [
            'id' => 1,
            'delivery_code' => 'DLV-VERIFIED',
            'tank_id' => 1,
            'supplier_id' => 1,
            'liters_delivered' => 100,
            'total_cost' => 7000,
            'delivery_date' => '2026-07-16',
            'delivery_status' => 'Verified',
        ],
    ]);
    DB::table('tank_delivery_records')->insert([
        'inventory_log_id' => 1,
        'record_date' => '2026-07-16',
        'record_time' => '10:00:00',
    ]);

    $firstSale = shiftReadingRow(1, '2026-07-15', 20, 1500);
    $secondSale = shiftReadingRow(2, '2026-07-17', 30, 2280);
    DB::table('shift_readings')->insert([$firstSale, $secondSale]);

    $result = app(FuelInventoryReportService::class)->calculate(
        '2026-07-15',
        '2026-07-17',
        ['Premium' => ['liters' => 100, 'unit_cost' => 60]]
    );
    $premium = collect($result['lines'])->firstWhere('fuel_type', 'Premium');

    expect($premium['purchased_liters'])->toBe(100.0)
        ->and($premium['purchase_value'])->toBe(7000.0)
        ->and($premium['available_liters'])->toBe(200.0)
        ->and($premium['periodic_average_cost'])->toBe(65.0)
        ->and($premium['dispensed_liters'])->toBe(50.0)
        ->and($premium['cost_of_goods_sold'])->toBe(3250.0)
        ->and($premium['ending_liters'])->toBe(150.0)
        ->and($premium['ending_unit_cost'])->toBe(65.0)
        ->and($premium['gross_profit'])->toBe(530.0)
        ->and($result['deliveries'])->toHaveCount(1)
        ->and($result['deliveries'][0]['delivery_code'])->toBe('DLV-VERIFIED');
});

test('a movement period includes pending readings and uses its stored tank limit', function () {
    DB::table('tanks')->insert([
        'id' => 1,
        'name' => 'Tank 1',
        'fuel_type' => 'Premium',
        'connected_pumps' => 'Pump 1, Pump 3',
    ]);
    DB::table('liters_tracking_periods')->insert([
        'id' => 5,
        'period_code' => 'LTM-TEST',
        'liters_limit' => 8270.21,
        'tank_limits' => json_encode(['tank:1' => 8270.21]),
        'status' => 'Generated',
        'started_at' => '2026-07-15 08:00:00',
        'ended_at' => '2026-07-20 08:00:00',
    ]);

    $pending = shiftReadingRow(20, '2026-07-16', 1599.39, 120458.18);
    $pending['status'] = 'Pending';
    $pending['liters_tracking_period_id'] = 5;
    $removed = shiftReadingRow(21, '2026-07-17', 500, 38000);
    $removed['liters_tracking_period_id'] = 5;
    DB::table('shift_readings')->insert([$pending, $removed]);
    DB::table('liters_movement_period_readings')->insert([
        [
            'liters_tracking_period_id' => 5,
            'shift_reading_id' => 20,
            'inclusion_status' => 'included',
            'removed_at' => null,
        ],
        [
            'liters_tracking_period_id' => 5,
            'shift_reading_id' => 21,
            'inclusion_status' => 'removed',
            'removed_at' => '2026-07-18 12:00:00',
        ],
    ]);

    $result = app(FuelInventoryReportService::class)->calculate(
        '2026-07-15',
        '2026-07-20',
        ['Premium' => ['liters' => 8270.21, 'unit_cost' => 68.27517058]],
        5
    );
    $premium = collect($result['lines'])->firstWhere('fuel_type', 'Premium');

    expect($premium['dispensed_liters'])->toBe(1599.39)
        ->and($premium['tank_limit_liters'])->toBe(8270.21)
        ->and($premium['ending_liters'])->toBe(6670.82)
        ->and($premium['source_summary']['shift_reading_ids'])->toBe([20]);
});

function shiftReadingRow(int $id, string $date, float $liters, float $revenue): array
{
    $row = [
        'id' => $id,
        'period_from' => $date,
        'period_to' => $date,
        'status' => 'Verified',
        'liters_tracking_period_id' => null,
        'created_at' => "{$date} 23:00:00",
    ];

    for ($pump = 1; $pump <= 6; $pump++) {
        $row["pump{$pump}_liters_start"] = 0;
        $row["pump{$pump}_liters_end"] = $pump === 1 ? $liters : 0;
        $row["pump{$pump}_sales_start"] = 0;
        $row["pump{$pump}_sales_end"] = $pump === 1 ? $revenue : 0;
    }

    return $row;
}
