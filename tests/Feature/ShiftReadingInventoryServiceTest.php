<?php

use App\Services\ShiftReadingInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('shift_readings');
    Schema::dropIfExists('tanks');

    Schema::create('tanks', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('fuel_type')->nullable();
        $table->string('connected_pumps')->nullable();
        $table->string('status')->nullable();
        $table->decimal('current_volume', 12, 2)->default(0);
    });

    Schema::create('shift_readings', function (Blueprint $table) {
        $table->id();
        $table->string('status')->default('Pending');
        $table->timestamp('inventory_applied_at')->nullable();
        $table->timestamp('inventory_reversed_at')->nullable();
        $table->text('inventory_deductions')->nullable();
        $table->timestamps();

        for ($pump = 1; $pump <= 6; $pump++) {
            $table->decimal("pump{$pump}_liters_start", 12, 2)->default(0);
            $table->decimal("pump{$pump}_liters_end", 12, 2)->default(0);
        }
    });
});

test('a pending reading deducts once and restores the exact tanks once', function () {
    DB::table('tanks')->insert([
        [
            'id' => 1,
            'name' => 'Premium Main',
            'fuel_type' => 'Premium',
            'connected_pumps' => 'Pump 1, Pump 3',
            'status' => 'Active',
            'current_volume' => 1000,
        ],
        [
            'id' => 2,
            'name' => 'Premium Spare',
            'fuel_type' => 'Premium',
            'connected_pumps' => '',
            'status' => 'Active',
            'current_volume' => 500,
        ],
    ]);

    $reading = array_fill_keys(
        collect(range(1, 6))
            ->flatMap(fn ($pump) => ["pump{$pump}_liters_start", "pump{$pump}_liters_end"])
            ->all(),
        0
    );

    $reading['id'] = 10;
    $reading['status'] = 'Pending';
    $reading['pump1_liters_start'] = 100;
    $reading['pump1_liters_end'] = 125.5;
    $reading['pump3_liters_start'] = 200;
    $reading['pump3_liters_end'] = 210;
    $reading['created_at'] = now();
    $reading['updated_at'] = now();

    DB::table('shift_readings')->insert($reading);

    $service = app(ShiftReadingInventoryService::class);
    $settlement = DB::table('shift_readings')->where('id', 10)->first();

    expect($service->apply($settlement))->toBe(35.5)
        ->and((float) DB::table('tanks')->where('id', 1)->value('current_volume'))->toBe(964.5)
        ->and($service->apply($settlement))->toBe(0.0)
        ->and((float) DB::table('tanks')->where('id', 1)->value('current_volume'))->toBe(964.5);

    $deductions = json_decode(
        (string) DB::table('shift_readings')->where('id', 10)->value('inventory_deductions'),
        true
    );

    expect($deductions)->toHaveCount(1)
        ->and($deductions[0]['tank_id'])->toBe(1)
        ->and((float) $deductions[0]['liters'])->toBe(35.5);

    DB::table('tanks')->where('id', 1)->update(['connected_pumps' => '']);
    DB::table('tanks')->where('id', 2)->update(['connected_pumps' => 'Pump 1, Pump 3']);

    expect($service->restore($settlement))->toBe(35.5)
        ->and((float) DB::table('tanks')->where('id', 1)->value('current_volume'))->toBe(1000.0)
        ->and((float) DB::table('tanks')->where('id', 2)->value('current_volume'))->toBe(500.0)
        ->and($service->restore($settlement))->toBe(0.0)
        ->and((float) DB::table('tanks')->where('id', 1)->value('current_volume'))->toBe(1000.0);
});
