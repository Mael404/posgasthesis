<?php

use App\Http\Controllers\InventoryController;
use App\Services\LitersMovementTrackingService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    foreach (['inventory_logs', 'suppliers', 'tanks'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('tanks', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('fuel_type');
        $table->decimal('capacity', 14, 2)->default(0);
        $table->decimal('current_volume', 14, 2)->default(0);
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
        $table->decimal('liters_delivered', 14, 2);
        $table->decimal('total_cost', 18, 2);
        $table->date('delivery_date');
        $table->timestamp('created_at')->nullable();
    });

    DB::table('tanks')->insert([
        ['id' => 1, 'name' => 'Tank 1 (Premium)', 'fuel_type' => 'Premium', 'capacity' => 11000, 'current_volume' => 4000],
        ['id' => 2, 'name' => 'Tank 2 (Unleaded)', 'fuel_type' => 'Unleaded', 'capacity' => 11000, 'current_volume' => 5000],
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('stock in calculates each stored total cost from liters and price per liter', function () {
    Carbon::setTestNow('2026-08-05 10:30:00');

    $tracking = Mockery::mock(LitersMovementTrackingService::class);
    $tracking->shouldReceive('addActiveTankDeliveries')
        ->once()
        ->with([1 => 1000.0, 2 => 800.0]);

    $request = Request::create('/inventory/stock-in', 'POST', [
        'supplier_name' => 'test supplier',
        'delivery_date' => '2026-08-05',
        'deliveries' => [
            [
                'tank_id' => 1,
                'liters' => 1000,
                'price_per_liter' => 72.50,
                'cost' => 1,
            ],
            [
                'tank_id' => 2,
                'liters' => 800,
                'price_per_liter' => 69.80,
                'cost' => 1,
            ],
        ],
    ]);

    $response = (new InventoryController($tracking))->stockIn($request);
    $payload = $response->getData(true);
    $logs = DB::table('inventory_logs')->orderBy('tank_id')->get();

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['success'])->toBeTrue()
        ->and($logs)->toHaveCount(2)
        ->and((float) $logs[0]->liters_delivered)->toBe(1000.0)
        ->and((float) $logs[0]->total_cost)->toBe(72500.0)
        ->and((float) $logs[1]->liters_delivered)->toBe(800.0)
        ->and((float) $logs[1]->total_cost)->toBe(55840.0)
        ->and((float) DB::table('tanks')->where('id', 1)->value('current_volume'))->toBe(5000.0)
        ->and((float) DB::table('tanks')->where('id', 2)->value('current_volume'))->toBe(5800.0);
});
