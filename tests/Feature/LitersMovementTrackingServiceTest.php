<?php

use App\Services\LitersMovementTrackingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('shift_readings');
    Schema::dropIfExists('liters_tracking_periods');
    Schema::dropIfExists('fuel_prices');
    Schema::dropIfExists('tanks');

    Schema::create('tanks', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('fuel_type');
        $table->string('connected_pumps')->nullable();
        $table->string('status')->nullable();
    });

    Schema::create('fuel_prices', function (Blueprint $table) {
        $table->id();
        $table->string('fuel_type');
        $table->decimal('price_per_liter', 12, 2);
    });

    Schema::create('liters_tracking_periods', function (Blueprint $table) {
        $table->id();
        $table->string('period_code')->unique();
        $table->decimal('liters_limit', 12, 2)->default(4000);
        $table->text('tank_limits')->nullable();
        $table->string('status', 24)->default('active');
        $table->timestamp('started_at')->nullable();
        $table->timestamp('ended_at')->nullable();
        $table->unsignedBigInteger('generated_by')->nullable();
        $table->timestamp('voided_at')->nullable();
        $table->unsignedBigInteger('voided_by')->nullable();
        $table->text('totals_snapshot')->nullable();
        $table->timestamps();
    });

    Schema::create('shift_readings', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('liters_tracking_period_id')->nullable();
        $table->date('period_from')->nullable();
        $table->date('period_to')->nullable();
        $table->string('status')->nullable();

        for ($pump = 1; $pump <= 6; $pump++) {
            $table->decimal("pump{$pump}_liters_start", 14, 2)->default(0);
            $table->decimal("pump{$pump}_liters_end", 14, 2)->default(0);
            $table->decimal("pump{$pump}_sales_start", 14, 2)->default(0);
            $table->decimal("pump{$pump}_sales_end", 14, 2)->default(0);
        }

        $table->timestamps();
    });

    DB::table('tanks')->insert([
        ['id' => 1, 'name' => 'Premium Tank', 'fuel_type' => 'Premium', 'connected_pumps' => 'Pump 1, Pump 3', 'status' => 'Active'],
        ['id' => 2, 'name' => 'Unleaded Tank', 'fuel_type' => 'Unleaded', 'connected_pumps' => 'Pump 2, Pump 4', 'status' => 'Active'],
        ['id' => 3, 'name' => 'Diesel Tank', 'fuel_type' => 'Diesel', 'connected_pumps' => 'Pump 5, Pump 6', 'status' => 'Active'],
    ]);

    DB::table('fuel_prices')->insert([
        ['fuel_type' => 'Premium', 'price_per_liter' => 65],
        ['fuel_type' => 'Unleaded', 'price_per_liter' => 63],
        ['fuel_type' => 'Diesel', 'price_per_liter' => 60],
    ]);

    DB::table('liters_tracking_periods')->insert([
        'id' => 1,
        'period_code' => 'LTM-TEST-1',
        'liters_limit' => 1000,
        'tank_limits' => json_encode(['tank:1' => 1000, 'tank:2' => 1000, 'tank:3' => 1000]),
        'status' => 'active',
        'started_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $reading = function (int $id, int $userId, string $status, array $pumpLiters): array {
        $row = [
            'id' => $id,
            'user_id' => $userId,
            'liters_tracking_period_id' => null,
            'period_from' => now()->toDateString(),
            'period_to' => now()->toDateString(),
            'status' => $status,
            'created_at' => now()->subMinutes(20 - $id),
            'updated_at' => now()->subMinutes(20 - $id),
        ];

        for ($pump = 1; $pump <= 6; $pump++) {
            $liters = (float) ($pumpLiters[$pump] ?? 0);
            $row["pump{$pump}_liters_start"] = 1000;
            $row["pump{$pump}_liters_end"] = 1000 + $liters;
            $row["pump{$pump}_sales_start"] = 50000;
            $row["pump{$pump}_sales_end"] = 50000 + ($liters * 60);
        }

        return $row;
    };

    DB::table('shift_readings')->insert([
        $reading(10, 1, 'Pending', [5 => 200, 6 => 250]),
        $reading(11, 2, 'Verified', [5 => 50]),
        $reading(12, 3, 'Rejected', [5 => 100]),
        $reading(13, 4, 'Voided', [6 => 100]),
    ]);
});

test('shared liters movement totals pending and verified shift reading pump deltas by tank', function () {
    $state = app(LitersMovementTrackingService::class)->state();
    $diesel = collect($state['summary']['tank_breakdown'])
        ->firstWhere('fuel_type', 'Diesel');

    expect($state['summary']['total_liters'])->toBe(500.0)
        ->and($state['summary']['submission_count'])->toBe(2)
        ->and($state['summary']['pump_entry_count'])->toBe(3)
        ->and($diesel['liters'])->toBe(500.0)
        ->and($diesel['remaining_liters'])->toBe(500.0)
        ->and($diesel['status']['key'])->toBe('normal')
        ->and($state['transactions']->total())->toBe(2)
        ->and(DB::table('shift_readings')->whereNull('liters_tracking_period_id')->count())->toBe(0);
});

test('a reached shared limit remains non blocking for the next shift reading submission', function () {
    $service = app(LitersMovementTrackingService::class);

    $service->updateActiveLimits(['tank:1' => 1000, 'tank:2' => 1000, 'tank:3' => 500]);
    $state = $service->state();
    $diesel = collect($state['summary']['tank_breakdown'])
        ->firstWhere('fuel_type', 'Diesel');

    expect($diesel['status']['key'])->toBe('limit_reached')
        ->and($state['summary']['reached_limits'])->toHaveCount(1)
        ->and($service->activePeriodIdForNewShiftReading())->toBe(1);
});

test('generate record snapshots shift readings and starts a zeroed shared period', function () {
    $service = app(LitersMovementTrackingService::class);
    $result = $service->generateAndReset(99);
    $snapshot = json_decode((string) $result['period']->totals_snapshot, true);
    $newState = $service->state();

    expect($result['summary']['total_liters'])->toBe(500.0)
        ->and($snapshot['source'])->toBe('shift_readings')
        ->and($snapshot['submission_count'])->toBe(2)
        ->and($snapshot['shift_reading_ids'])->toBe([10, 11])
        ->and($newState['summary']['total_liters'])->toBe(0.0)
        ->and($newState['summary']['submission_count'])->toBe(0)
        ->and((int) $newState['period']->id)->not->toBe(1);
});

test('voiding a generated record restores its included shift readings to the active period', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $generatedPeriodId = (int) $generated['period']->id;
    $activePeriodId = (int) $generated['new_period']->id;

    $result = $service->voidGeneratedPeriod($generatedPeriodId, 88);
    $snapshot = json_decode((string) DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('totals_snapshot'), true);
    $newState = $service->state();

    expect($result['restored_shift_reading_ids'])->toBe([10, 11])
        ->and($result['restored_count'])->toBe(2)
        ->and($result['restored_liters'])->toBe(500.0)
        ->and(DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('status'))->toBe('voided')
        ->and((int) DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('voided_by'))->toBe(88)
        ->and($snapshot['reverted_to_period_id'])->toBe($activePeriodId)
        ->and($snapshot['reverted_shift_reading_ids'])->toBe([10, 11])
        ->and(DB::table('shift_readings')->whereIn('id', [10, 11])->pluck('liters_tracking_period_id', 'id')->all())->toBe([10 => $activePeriodId, 11 => $activePeriodId])
        ->and($newState['summary']['total_liters'])->toBe(500.0)
        ->and($newState['summary']['submission_count'])->toBe(2);
});
