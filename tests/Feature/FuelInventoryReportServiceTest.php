<?php

use App\Services\FuelInventoryReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach ([
        'fuel_inventory_report_lines',
        'fuel_inventory_reports',
        'liters_movement_period_readings',
        'liters_tracking_periods',
        'liters_movement_adjustments',
        'tank_delivery_records',
        'inventory_logs',
        'suppliers',
        'sales',
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
        $table->decimal('current_volume', 14, 2)->nullable();
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
        $table->unsignedBigInteger('user_id')->nullable();
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

    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('pump_id');
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('locked_by_shift_reading_id')->nullable();
        $table->decimal('discount', 14, 2)->default(0);
        $table->decimal('manual_discount', 14, 2)->default(0);
        $table->string('payment_type')->nullable();
        $table->timestamp('customer_payment_voided_at')->nullable();
        $table->timestamp('created_at')->nullable();
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
        $table->text('opening_inventory')->nullable();
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

    Schema::create('fuel_inventory_reports', function (Blueprint $table) {
        $table->id();
        $table->string('report_code');
        $table->string('status');
        $table->date('period_end');
    });

    Schema::create('fuel_inventory_report_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('fuel_inventory_report_id');
        $table->string('fuel_type');
        $table->decimal('ending_liters', 16, 3);
        $table->decimal('ending_unit_cost', 16, 6);
        $table->decimal('ending_value', 18, 6);
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

test('initial stock saves tank-derived quantities and manually entered prices', function () {
    DB::table('tanks')->insert([
        ['id' => 1, 'name' => 'Premium Tank', 'fuel_type' => 'Premium', 'current_volume' => 2270.23, 'connected_pumps' => 'Pump 1, Pump 3'],
        ['id' => 2, 'name' => 'Regular Tank', 'fuel_type' => 'Unleaded', 'current_volume' => 4700.21, 'connected_pumps' => 'Pump 2, Pump 4'],
        ['id' => 3, 'name' => 'Diesel Tank', 'fuel_type' => 'Diesel', 'current_volume' => 4197.50, 'connected_pumps' => 'Pump 5, Pump 6'],
    ]);
    DB::table('liters_tracking_periods')->insert([
        'id' => 4,
        'period_code' => 'LTM-FIRST',
        'liters_limit' => 4700.21,
        'tank_limits' => json_encode(['tank:1' => 2270.23, 'tank:2' => 4700.21, 'tank:3' => 4197.50]),
        'opening_inventory' => null,
        'status' => 'Active',
        'started_at' => '2026-07-15 08:00:00',
        'ended_at' => null,
    ]);

    $saved = app(FuelInventoryReportService::class)->saveInitialOpening(4, [
        'Premium' => ['unit_cost' => 66.80],
        'Unleaded' => ['unit_cost' => 66.30],
        'Diesel' => ['unit_cost' => 68.10],
    ]);
    $stored = json_decode((string) DB::table('liters_tracking_periods')->where('id', 4)->value('opening_inventory'), true);

    expect($saved['Premium'])->toBe([
        'liters' => 2270.23,
        'unit_cost' => 66.8,
        'value' => 151651.364,
    ])->and($saved['Unleaded']['liters'])->toBe(4700.21)
        ->and($saved['Diesel']['liters'])->toBe(4197.5)
        ->and($stored)->toBe($saved);
});

test('workbook inventory formulas match current stock price guide tank progress and fuel profit', function () {
    DB::table('tanks')->insert([
        ['id' => 1, 'name' => 'Premium Tank', 'fuel_type' => 'Premium', 'current_volume' => 2270.23, 'connected_pumps' => 'Pump 1, Pump 3'],
        ['id' => 2, 'name' => 'Regular Tank', 'fuel_type' => 'Unleaded', 'current_volume' => 4700.21, 'connected_pumps' => 'Pump 2, Pump 4'],
        ['id' => 3, 'name' => 'Diesel Tank', 'fuel_type' => 'Diesel', 'current_volume' => 4197.50, 'connected_pumps' => 'Pump 5, Pump 6'],
    ]);
    DB::table('suppliers')->insert(['id' => 1, 'name' => 'Q & Q']);
    DB::table('fuel_prices')->insert([
        ['fuel_type' => 'Premium', 'price_per_liter' => 76.00, 'bulk_price_per_liter' => 71.50],
        ['fuel_type' => 'Unleaded', 'price_per_liter' => 75.50, 'bulk_price_per_liter' => 71.30],
        ['fuel_type' => 'Diesel', 'price_per_liter' => 77.50, 'bulk_price_per_liter' => 75.00],
    ]);
    DB::table('liters_tracking_periods')->insert([
        'id' => 8,
        'period_code' => 'LTM-WORKBOOK',
        'liters_limit' => 10700.21,
        'tank_limits' => json_encode(['tank:1' => 8270.23, 'tank:2' => 10700.21, 'tank:3' => 10197.50]),
        'opening_inventory' => null,
        'status' => 'Generated',
        'started_at' => '2026-07-15 00:00:00',
        'ended_at' => '2026-07-20 23:59:59',
    ]);

    $deliveries = [
        [1, 1, 4000, 276000],
        [2, 2, 4000, 274000],
        [3, 3, 6000, 459300],
        [4, 1, 2000, 137000],
        [5, 2, 2000, 138000],
    ];

    foreach ($deliveries as [$id, $tankId, $liters, $cost]) {
        DB::table('inventory_logs')->insert([
            'id' => $id,
            'delivery_code' => 'DLV-' . $id,
            'tank_id' => $tankId,
            'supplier_id' => 1,
            'liters_delivered' => $liters,
            'total_cost' => $cost,
            'delivery_date' => '2026-07-16',
            'delivery_status' => 'Verified',
        ]);
        DB::table('tank_delivery_records')->insert([
            'inventory_log_id' => $id,
            'record_date' => '2026-07-16',
            'record_time' => sprintf('%02d:00:00', 8 + $id),
        ]);
    }

    $reading = shiftReadingRow(80, '2026-07-20', 1599.39, 120458.18);
    $reading['status'] = 'Pending';
    $reading['liters_tracking_period_id'] = 8;
    $reading['pump2_liters_end'] = 2931.51;
    $reading['pump2_sales_end'] = 220710.05;
    $reading['pump5_liters_end'] = 2583.27;
    $reading['pump5_sales_end'] = 199060.22;
    DB::table('shift_readings')->insert($reading);
    DB::table('liters_movement_period_readings')->insert([
        'liters_tracking_period_id' => 8,
        'shift_reading_id' => 80,
        'inclusion_status' => 'included',
        'removed_at' => null,
    ]);

    $result = app(FuelInventoryReportService::class)->calculate(
        '2026-07-15',
        '2026-07-20',
        [
            'Premium' => ['liters' => 2270.23, 'unit_cost' => 66.80],
            'Unleaded' => ['liters' => 4700.21, 'unit_cost' => 66.30],
            'Diesel' => ['liters' => 4197.50, 'unit_cost' => 68.10],
        ],
        8
    );
    $lines = collect($result['lines'])->keyBy('fuel_type');

    expect($lines['Premium']['available_liters'])->toBe(8270.23)
        ->and($lines['Premium']['periodic_average_cost'])->toBe(68.275171)
        ->and($lines['Premium']['dispensed_liters'])->toBe(1599.39)
        ->and($lines['Premium']['ending_liters'])->toBe(6670.84)
        ->and($lines['Premium']['average_selling_price'])->toBe(75.315076)
        ->and($lines['Premium']['pump_margin'])->toBe(7.724829)
        ->and($lines['Premium']['bulk_margin'])->toBe(3.224829)
        ->and($lines['Premium']['gross_profit'])->toBe(11259.554923)
        ->and($lines['Unleaded']['available_liters'])->toBe(10700.21)
        ->and($lines['Unleaded']['ending_liters'])->toBe(7768.7)
        ->and($lines['Unleaded']['gross_profit'])->toBe(22460.598212)
        ->and($lines['Diesel']['available_liters'])->toBe(10197.5)
        ->and($lines['Diesel']['ending_liters'])->toBe(7614.23)
        ->and($lines['Diesel']['gross_profit'])->toBe(10296.013608)
        ->and($result['totals']['gross_profit'])->toBe(44016.166743);
});

test('a finalized ending stock becomes the next report opening stock', function () {
    DB::table('fuel_inventory_reports')->insert([
        'id' => 1,
        'report_code' => 'INV-PREVIOUS',
        'status' => 'Finalized',
        'period_end' => '2026-07-14',
    ]);
    DB::table('fuel_inventory_report_lines')->insert([
        'fuel_inventory_report_id' => 1,
        'fuel_type' => 'Premium',
        'ending_liters' => 1000,
        'ending_unit_cost' => 68.25,
        'ending_value' => 68250,
    ]);

    $opening = app(FuelInventoryReportService::class)->openingFromReport(1);

    expect($opening['Premium'])->toBe([
        'liters' => 1000.0,
        'unit_cost' => 68.25,
        'value' => 68250.0,
    ]);
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

test('current stock drives ending stock while liters movement supplies net sales', function () {
    DB::table('tanks')->insert([
        'id' => 1,
        'name' => 'Tank 1',
        'fuel_type' => 'Premium',
        'connected_pumps' => 'Pump 1, Pump 3',
    ]);
    DB::table('liters_tracking_periods')->insert([
        'id' => 6,
        'period_code' => 'LTM-CURRENT-STOCK',
        'liters_limit' => 999,
        'tank_limits' => json_encode(['tank:1' => 999]),
        'status' => 'Generated',
        'started_at' => '2026-07-15 08:00:00',
        'ended_at' => '2026-07-17 08:00:00',
    ]);
    DB::table('suppliers')->insert(['id' => 1, 'name' => 'Q & Q']);
    DB::table('inventory_logs')->insert([
        'id' => 1,
        'delivery_code' => 'DLV-IN-PERIOD',
        'tank_id' => 1,
        'supplier_id' => 1,
        'liters_delivered' => 50,
        'total_cost' => 3500,
        'delivery_date' => '2026-07-14',
        'delivery_status' => 'Verified',
    ]);
    DB::table('tank_delivery_records')->insert([
        'inventory_log_id' => 1,
        'record_date' => '2026-07-16',
        'record_time' => '10:00:00',
    ]);

    $reading = shiftReadingRow(30, '2026-07-16', 20, 1500);
    $reading['status'] = 'Pending';
    $reading['liters_tracking_period_id'] = 6;
    $reading['user_id'] = 10;
    $rejected = shiftReadingRow(31, '2026-07-16', 100, 7500);
    $rejected['status'] = 'Rejected';
    $rejected['liters_tracking_period_id'] = 6;
    $rejected['user_id'] = 11;
    DB::table('shift_readings')->insert([$reading, $rejected]);
    DB::table('liters_movement_period_readings')->insert([
        [
            'liters_tracking_period_id' => 6,
            'shift_reading_id' => 30,
            'inclusion_status' => 'included',
            'removed_at' => null,
        ],
        [
            'liters_tracking_period_id' => 6,
            'shift_reading_id' => 31,
            'inclusion_status' => 'included',
            'removed_at' => null,
        ],
    ]);
    DB::table('sales')->insert([
        'pump_id' => 1,
        'user_id' => 10,
        'locked_by_shift_reading_id' => 30,
        'discount' => 40,
        'manual_discount' => 10,
        'payment_type' => 'Walkin',
        'customer_payment_voided_at' => null,
        'created_at' => '2026-07-16 12:00:00',
    ]);

    $service = app(FuelInventoryReportService::class);
    $suggestedOpening = $service->initialOpeningFromTrackingPeriod(6);
    $result = $service->calculate(
        '2026-07-15',
        '2026-07-17',
        ['Premium' => ['liters' => 100, 'unit_cost' => 60]],
        6
    );
    $premium = collect($result['lines'])->firstWhere('fuel_type', 'Premium');

    expect($suggestedOpening['Premium']['liters'])->toBe(949.0)
        ->and($premium['available_liters'])->toBe(150.0)
        ->and($premium['tank_limit_liters'])->toBe(150.0)
        ->and($premium['dispensed_liters'])->toBe(20.0)
        ->and($premium['ending_liters'])->toBe(130.0)
        ->and($premium['sales_revenue'])->toBe(1450.0)
        ->and($premium['average_selling_price'])->toBe(72.5)
        ->and($premium['source_summary']['configured_liters_movement_limit'])->toBe(999.0)
        ->and($premium['source_summary']['sales_discounts'])->toBe(50.0)
        ->and($result['deliveries'][0]['delivery_date'])->toBe('2026-07-16')
        ->and($result['deliveries'][0]['logged_delivery_date'])->toBe('2026-07-14');
});

function shiftReadingRow(int $id, string $date, float $liters, float $revenue): array
{
    $row = [
        'id' => $id,
        'user_id' => null,
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
