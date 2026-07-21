<?php

use App\Services\FuelInventoryReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach (['liters_movement_adjustments', 'tank_delivery_records', 'inventory_logs', 'suppliers', 'shift_readings', 'fuel_prices', 'tanks'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('tanks', function (Blueprint $table) {
        $table->id();
        $table->string('fuel_type');
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
        ->and($premium['gross_profit'])->toBe(270.0)
        ->and($premium['source_summary']['shift_readings'][0]['adjustment_ids'])->toBe([1]);
});

test('later deliveries do not change the cost assigned to earlier fuel dispensing', function () {
    DB::table('tanks')->insert(['id' => 1, 'fuel_type' => 'Premium']);
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
        [
            'id' => 2,
            'delivery_code' => 'DLV-PENDING',
            'tank_id' => 1,
            'supplier_id' => 1,
            'liters_delivered' => 500,
            'total_cost' => 50000,
            'delivery_date' => '2026-07-16',
            'delivery_status' => 'Pending Tank Delivery Record',
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
        ->and($premium['cost_of_goods_sold'])->toEqualWithDelta(3166.666667, 0.00001)
        ->and($premium['ending_liters'])->toBe(150.0)
        ->and($premium['ending_unit_cost'])->toEqualWithDelta(65.555556, 0.00001)
        ->and($premium['gross_profit'])->toEqualWithDelta(613.333333, 0.00001)
        ->and($result['deliveries'])->toHaveCount(1)
        ->and($result['deliveries'][0]['delivery_code'])->toBe('DLV-VERIFIED');

    $events = $premium['source_summary']['events'];

    expect($events[0]['type'])->toBe('sale')
        ->and($events[0]['average_cost_before'])->toBe(60.0)
        ->and($events[1]['type'])->toBe('delivery')
        ->and($events[2]['type'])->toBe('sale')
        ->and($events[2]['average_cost_before'])->toEqualWithDelta(65.555556, 0.00001);
});

function shiftReadingRow(int $id, string $date, float $liters, float $revenue): array
{
    $row = [
        'id' => $id,
        'period_from' => $date,
        'period_to' => $date,
        'status' => 'Verified',
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
