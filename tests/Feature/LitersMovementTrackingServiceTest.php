<?php

use App\Services\LitersMovementTrackingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('liters_movement_period_readings');
    Schema::dropIfExists('liters_movement_corrections');
    Schema::dropIfExists('liters_movement_adjustments');
    Schema::dropIfExists('sales');
    Schema::dropIfExists('shift_readings');
    Schema::dropIfExists('liters_tracking_periods');
    Schema::dropIfExists('fuel_prices');
    Schema::dropIfExists('tanks');

    Schema::create('tanks', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('fuel_type');
        $table->decimal('current_volume', 14, 2)->nullable();
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

    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('locked_by_shift_reading_id')->nullable();
        $table->unsignedInteger('pump_id')->nullable();
        $table->decimal('amount', 14, 2)->default(0);
        $table->decimal('discount', 14, 2)->default(0);
        $table->decimal('manual_discount', 14, 2)->default(0);
        $table->string('payment_type')->nullable();
        $table->timestamp('customer_payment_voided_at')->nullable();
        $table->timestamps();
    });

    Schema::create('liters_movement_adjustments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('liters_tracking_period_id');
        $table->unsignedBigInteger('shift_reading_id');
        $table->unsignedTinyInteger('pump_number');
        $table->decimal('liters', 12, 2);
        $table->decimal('amount', 14, 2)->default(0);
        $table->string('reason')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('authorized_by')->nullable();
        $table->timestamp('voided_at')->nullable();
        $table->unsignedBigInteger('voided_by')->nullable();
        $table->unsignedBigInteger('void_authorized_by')->nullable();
        $table->string('void_reason')->nullable();
        $table->timestamps();
    });

    Schema::create('liters_movement_corrections', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('liters_tracking_period_id');
        $table->unsignedBigInteger('generated_period_id');
        $table->unsignedBigInteger('original_shift_reading_id');
        $table->unsignedBigInteger('replacement_shift_reading_id')->nullable();
        $table->string('entry_type', 16);
        $table->string('status', 16)->default('pending');
        $table->unsignedTinyInteger('pump_number');
        $table->string('fuel_type', 32)->nullable();
        $table->decimal('liters', 14, 2);
        $table->decimal('amount', 14, 2);
        $table->decimal('total_sales', 14, 2);
        $table->decimal('discounts', 14, 2)->default(0);
        $table->decimal('net_sales', 14, 2);
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();

        $table->unique(['original_shift_reading_id', 'entry_type', 'pump_number']);
        $table->unique(['replacement_shift_reading_id', 'entry_type', 'pump_number']);
    });

    Schema::create('liters_movement_period_readings', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('liters_tracking_period_id');
        $table->unsignedBigInteger('shift_reading_id');
        $table->string('inclusion_status', 24)->default('included');
        $table->string('inclusion_source', 32)->default('period_assignment');
        $table->timestamp('included_at');
        $table->unsignedBigInteger('included_by')->nullable();
        $table->timestamp('removed_at')->nullable();
        $table->unsignedBigInteger('removed_by')->nullable();
        $table->string('removal_status', 32)->nullable();
        $table->text('removal_reason')->nullable();
        $table->timestamps();

        $table->unique(['liters_tracking_period_id', 'shift_reading_id']);
    });

    DB::table('tanks')->insert([
        ['id' => 1, 'name' => 'Premium Tank', 'fuel_type' => 'Premium', 'current_volume' => 2270.23, 'connected_pumps' => 'Pump 1, Pump 3', 'status' => 'Active'],
        ['id' => 2, 'name' => 'Unleaded Tank', 'fuel_type' => 'Unleaded', 'current_volume' => 4700.21, 'connected_pumps' => 'Pump 2, Pump 4', 'status' => 'Active'],
        ['id' => 3, 'name' => 'Diesel Tank', 'fuel_type' => 'Diesel', 'current_volume' => 4197.50, 'connected_pumps' => 'Pump 5, Pump 6', 'status' => 'Active'],
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

    DB::table('sales')->insert([
        [
            'user_id' => 1,
            'locked_by_shift_reading_id' => 10,
            'pump_id' => 5,
            'amount' => 12000,
            'discount' => 100,
            'manual_discount' => 20,
            'payment_type' => 'Walkin',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => 2,
            'locked_by_shift_reading_id' => 11,
            'pump_id' => 5,
            'amount' => 3000,
            'discount' => 30,
            'manual_discount' => 5,
            'payment_type' => 'Credit',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => 3,
            'locked_by_shift_reading_id' => 12,
            'pump_id' => 5,
            'amount' => 6000,
            'discount' => 500,
            'manual_discount' => 500,
            'payment_type' => 'Walkin',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
});

test('the first liters movement period starts from tank current volumes', function () {
    DB::table('liters_movement_period_readings')->delete();
    DB::table('liters_movement_corrections')->delete();
    DB::table('liters_movement_adjustments')->delete();
    DB::table('shift_readings')->delete();
    DB::table('liters_tracking_periods')->delete();

    $period = app(LitersMovementTrackingService::class)->ensureActivePeriod();
    $limits = json_decode((string) $period->tank_limits, true);

    expect($limits)->toEqual([
        'tank:1' => 2270.23,
        'tank:2' => 4700.21,
        'tank:3' => 4197.5,
    ]);
});

test('shared liters movement totals pending and verified shift reading pump deltas by tank', function () {
    $state = app(LitersMovementTrackingService::class)->state();
    $diesel = collect($state['summary']['tank_breakdown'])
        ->firstWhere('fuel_type', 'Diesel');

    expect($state['summary']['total_liters'])->toBe(500.0)
        ->and($state['summary']['submission_count'])->toBe(2)
        ->and($state['summary']['pump_entry_count'])->toBe(3)
        ->and(collect($state['summary']['tank_breakdown'])->pluck('id')->all())->toBe([1, 2, 3])
        ->and(collect($state['summary']['tank_breakdown'])->pluck('fuel_type')->all())->toBe(['Premium', 'Unleaded', 'Diesel'])
        ->and($diesel['liters'])->toBe(500.0)
        ->and($diesel['remaining_liters'])->toBe(500.0)
        ->and($diesel['status']['key'])->toBe('normal')
        ->and($state['transactions']->total())->toBe(2)
        ->and(DB::table('shift_readings')->whereIn('id', [10, 11])->pluck('liters_tracking_period_id', 'id')->all())->toBe([10 => 1, 11 => 1])
        ->and(DB::table('shift_readings')->whereIn('id', [12, 13])->whereNull('liters_tracking_period_id')->count())->toBe(2);
});

test('net sales uses submitted pump totals while discounts stay scoped to eligible readings', function () {
    $state = app(LitersMovementTrackingService::class)->state(null, 20, 'page', true);
    $pumpSales = collect($state['summary']['pump_net_sales'])->keyBy('pump_id');
    $diesel = collect($state['summary']['tank_breakdown'])->firstWhere('fuel_type', 'Diesel');

    expect($pumpSales[5]['total_sales'])->toBe(15000.0)
        ->and($pumpSales[5]['discounts'])->toBe(155.0)
        ->and($pumpSales[5]['net_sales'])->toBe(14845.0)
        ->and($pumpSales[5]['transaction_count'])->toBe(2)
        ->and($pumpSales[6]['total_sales'])->toBe(15000.0)
        ->and($pumpSales[6]['discounts'])->toBe(0.0)
        ->and($pumpSales[6]['net_sales'])->toBe(15000.0)
        ->and($pumpSales[6]['transaction_count'])->toBe(0)
        ->and($state['summary']['total_sales'])->toBe(30000.0)
        ->and($state['summary']['total_discounts'])->toBe(155.0)
        ->and($state['summary']['total_net_sales'])->toBe(29845.0)
        ->and($diesel['average_selling_price'])->toBe(59.69);
});

test('pump deduction subtracts both liters and submitted sales amount', function () {
    $service = app(LitersMovementTrackingService::class);

    $service->deductPumpLiters(10, 5, 10, 600, 1, 99, 'Nozzle calibration');

    $state = $service->state(null, 20, 'page', true);
    $diesel = collect($state['summary']['tank_breakdown'])
        ->firstWhere('fuel_type', 'Diesel');
    $pumpSales = collect($state['summary']['pump_net_sales'])->keyBy('pump_id');
    $reading = $state['transactions']->getCollection()->firstWhere('id', 10);
    $pumpFive = collect($reading->pump_entries)->firstWhere('pump_id', 5);

    expect($state['summary']['total_liters'])->toBe(490.0)
        ->and($state['summary']['total_amount'])->toBe(29400.0)
        ->and($diesel['liters'])->toBe(490.0)
        ->and($pumpFive['liters'])->toBe(190.0)
        ->and($pumpFive['amount'])->toBe(11400.0)
        ->and($pumpFive['deducted_liters'])->toBe(10.0)
        ->and($pumpFive['deducted_amount'])->toBe(600.0)
        ->and($pumpSales[5]['total_sales'])->toBe(14400.0)
        ->and($pumpSales[5]['discounts'])->toBe(155.0)
        ->and($pumpSales[5]['net_sales'])->toBe(14245.0)
        ->and($state['summary']['total_sales'])->toBe(29400.0)
        ->and($state['summary']['total_net_sales'])->toBe(29245.0);
});

test('pump deduction accepts zero liters and zero amount', function () {
    $service = app(LitersMovementTrackingService::class);

    $adjustment = $service->deductPumpLiters(10, 5, 0, 0, 1, 99, 'No movement adjustment');
    $state = $service->state(null, 20, 'page', true);

    expect((float) $adjustment->liters)->toBe(0.0)
        ->and((float) $adjustment->amount)->toBe(0.0)
        ->and($state['summary']['total_liters'])->toBe(500.0)
        ->and($state['summary']['total_amount'])->toBe(30000.0);
});

test('full pump deduction clears net sales and voiding it restores every total', function () {
    DB::table('shift_readings')->where('id', 10)->update([
        'pump5_sales_end' => 62500,
    ]);

    $service = app(LitersMovementTrackingService::class);
    $adjustment = $service->deductPumpLiters(10, 5, 200, 12000, 1, 99, 'Void all Pump 5 movement');
    $deducted = $service->state(null, 20, 'page', true);
    $deductedPumpSales = collect($deducted['summary']['pump_net_sales'])->keyBy('pump_id');

    expect((float) $adjustment->amount)->toBe(12500.0)
        ->and($deductedPumpSales[5]['liters'])->toBe(50.0)
        ->and($deductedPumpSales[5]['total_sales'])->toBe(3000.0)
        ->and($deductedPumpSales[5]['discounts'])->toBe(35.0)
        ->and($deductedPumpSales[5]['net_sales'])->toBe(2965.0)
        ->and($deducted['summary']['total_net_sales'])->toBe(17965.0);

    $service->voidDeduction((int) $adjustment->id, 1, 1, 'Restore deduction', null, 1);
    $restored = $service->state(null, 20, 'page', true);
    $restoredPumpSales = collect($restored['summary']['pump_net_sales'])->keyBy('pump_id');

    expect($restoredPumpSales[5]['liters'])->toBe(250.0)
        ->and($restoredPumpSales[5]['total_sales'])->toBe(15500.0)
        ->and($restoredPumpSales[5]['discounts'])->toBe(155.0)
        ->and($restoredPumpSales[5]['net_sales'])->toBe(15345.0)
        ->and($restored['summary']['total_net_sales'])->toBe(30345.0);
});

test('a reached shared limit remains non blocking for the next shift reading submission', function () {
    $service = app(LitersMovementTrackingService::class);

    $service->updateActiveLimits(['tank:1' => 1000, 'tank:2' => 1000, 'tank:3' => 0]);
    $state = $service->state();
    $diesel = collect($state['summary']['tank_breakdown'])
        ->firstWhere('fuel_type', 'Diesel');

    expect($diesel['status']['key'])->toBe('limit_reached')
        ->and($state['summary']['reached_limits'])->toHaveCount(1)
        ->and($service->activePeriodIdForNewShiftReading())->toBe(1);
});

test('generate record snapshots shift readings and carries ending stock into the new period', function () {
    $service = app(LitersMovementTrackingService::class);
    $result = $service->generateAndReset(99);
    $snapshot = json_decode((string) $result['period']->totals_snapshot, true);
    $newLimits = json_decode((string) $result['new_period']->tank_limits, true);
    $newState = $service->state();

    expect($result['summary']['total_liters'])->toBe(500.0)
        ->and($snapshot['source'])->toBe('shift_readings')
        ->and($snapshot['submission_count'])->toBe(2)
        ->and($snapshot['shift_reading_ids'])->toBe([10, 11])
        ->and($snapshot['ending_tank_limits'])->toEqual([
            'tank:1' => 1000.0,
            'tank:2' => 1000.0,
            'tank:3' => 500.0,
        ])
        ->and($newLimits)->toBe($snapshot['ending_tank_limits'])
        ->and($newState['summary']['total_liters'])->toBe(0.0)
        ->and($newState['summary']['remaining_liters'])->toBe(2500.0)
        ->and($newState['summary']['submission_count'])->toBe(0)
        ->and((int) $newState['period']->id)->not->toBe(1)
        ->and($service->activeLimitsAreGeneratedEndingCarryForward($result['new_period']))->toBeTrue();
});

test('admin display can use carried current stock without changing the active period', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $activePeriodId = (int) $generated['new_period']->id;
    $storedLimits = (string) DB::table('liters_tracking_periods')->where('id', $activePeriodId)->value('tank_limits');
    $state = $service->state(null, 20, 'page', false, [
        'tank:1' => 1100,
        'tank:2' => 1200,
        'tank:3' => 1300,
    ]);

    expect($state['summary']['limit'])->toBe(3600.0)
        ->and($state['summary']['remaining_liters'])->toBe(3600.0)
        ->and((string) DB::table('liters_tracking_periods')->where('id', $activePeriodId)->value('tank_limits'))->toBe($storedLimits);
});

test('a new period excludes stale readings and memberships left by a reused period id', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $activePeriodId = (int) $generated['new_period']->id;
    $staleMembershipCreatedAt = now()->subDays(2);
    $currentMembershipCreatedAt = now();

    $staleReading = (array) DB::table('shift_readings')->where('id', 10)->first();
    $staleReading['id'] = 20;
    $staleReading['liters_tracking_period_id'] = $activePeriodId;
    $staleReading['period_from'] = now()->subMonth()->toDateString();
    $staleReading['period_to'] = now()->subMonth()->toDateString();
    $staleReading['status'] = 'Verified';
    $staleReading['created_at'] = now()->subMonth();
    $staleReading['updated_at'] = now()->subMonth();

    $currentReading = $staleReading;
    $currentReading['id'] = 21;
    $currentReading['period_from'] = now()->toDateString();
    $currentReading['period_to'] = now()->toDateString();
    $currentReading['created_at'] = now();
    $currentReading['updated_at'] = now();
    $currentReading['pump5_liters_end'] = 1025;
    $currentReading['pump5_sales_end'] = 51500;

    DB::table('shift_readings')->insert([$staleReading, $currentReading]);
    DB::table('liters_movement_period_readings')->insert([
        [
            'liters_tracking_period_id' => $activePeriodId,
            'shift_reading_id' => 20,
            'inclusion_status' => 'included',
            'inclusion_source' => 'active_period_assignment',
            'included_at' => $staleReading['created_at'],
            'created_at' => $staleMembershipCreatedAt,
            'updated_at' => $staleMembershipCreatedAt,
        ],
        [
            'liters_tracking_period_id' => $activePeriodId,
            'shift_reading_id' => 21,
            'inclusion_status' => 'included',
            'inclusion_source' => 'shift_reading_submission',
            'included_at' => $currentReading['created_at'],
            'created_at' => $currentMembershipCreatedAt,
            'updated_at' => $currentMembershipCreatedAt,
        ],
    ]);

    $state = $service->state();
    $diesel = collect($state['summary']['tank_breakdown'])->firstWhere('fuel_type', 'Diesel');

    expect($state['transactions']->pluck('id')->all())->toBe([21])
        ->and($state['summary']['submission_count'])->toBe(1)
        ->and($state['summary']['total_liters'])->toBe(25.0)
        ->and(collect($diesel['entries'])->pluck('id')->unique()->values()->all())->toBe([21])
        ->and(collect($state['summary']['staff_breakdown'])->sum('submission_count'))->toBe(1)
        ->and((int) DB::table('shift_readings')->where('id', 20)->value('liters_tracking_period_id'))->toBe($activePeriodId)
        ->and(DB::table('liters_movement_period_readings')
            ->where('liters_tracking_period_id', $activePeriodId)
            ->where('shift_reading_id', 20)
            ->value('inclusion_status'))->toBe('included');
});

test('editing current stock and adding a delivery preserve live dispensing exactly once', function () {
    $service = app(LitersMovementTrackingService::class);

    $service->updateActiveLimits(['tank:1' => 1000, 'tank:2' => 1000, 'tank:3' => 450]);
    $edited = $service->state();
    $editedDiesel = collect($edited['summary']['tank_breakdown'])->firstWhere('fuel_type', 'Diesel');

    $service->addActiveTankDeliveries([3 => 150]);
    $delivered = $service->state();
    $deliveredDiesel = collect($delivered['summary']['tank_breakdown'])->firstWhere('fuel_type', 'Diesel');
    $storedLimits = json_decode((string) $delivered['period']->tank_limits, true);

    expect($editedDiesel['liters'])->toBe(500.0)
        ->and($editedDiesel['remaining_liters'])->toBe(450.0)
        ->and($deliveredDiesel['liters'])->toBe(500.0)
        ->and($deliveredDiesel['remaining_liters'])->toBe(600.0)
        ->and((float) $storedLimits['tank:3'])->toBe(1100.0);
});

test('voiding a generated record restores its included shift readings to the active period', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $generatedPeriodId = (int) $generated['period']->id;
    $activePeriodId = (int) $generated['new_period']->id;

    $result = $service->voidGeneratedPeriod($generatedPeriodId, 88);
    $snapshot = json_decode((string) DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('totals_snapshot'), true);
    $newState = $service->state();
    $diesel = collect($newState['summary']['tank_breakdown'])->firstWhere('fuel_type', 'Diesel');

    expect($result['restored_shift_reading_ids'])->toBe([10, 11])
        ->and($result['restored_count'])->toBe(2)
        ->and($result['restored_liters'])->toBe(500.0)
        ->and(DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('status'))->toBe('voided')
        ->and((int) DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('voided_by'))->toBe(88)
        ->and($snapshot['reverted_to_period_id'])->toBe($activePeriodId)
        ->and($snapshot['reverted_shift_reading_ids'])->toBe([10, 11])
        ->and($snapshot['reverted_tank_liters'])->toEqual(['tank:3' => 500.0])
        ->and(DB::table('shift_readings')->whereIn('id', [10, 11])->pluck('liters_tracking_period_id', 'id')->all())->toBe([10 => $activePeriodId, 11 => $activePeriodId])
        ->and($newState['summary']['total_liters'])->toBe(500.0)
        ->and($diesel['remaining_liters'])->toBe(500.0)
        ->and($newState['summary']['submission_count'])->toBe(2);
});

test('void before generation detaches the reading and records an audit membership without a negative correction', function () {
    $service = app(LitersMovementTrackingService::class);
    $service->state();

    $result = $service->recordShiftReadingVoid(10, 1, 'Voided', 'Incorrect meter image');
    DB::table('shift_readings')->where('id', 10)->update(['status' => 'Voided']);
    $membership = DB::table('liters_movement_period_readings')
        ->where('liters_tracking_period_id', 1)
        ->where('shift_reading_id', 10)
        ->first();

    expect($result['action'])->toBe('detached')
        ->and(DB::table('shift_readings')->where('id', 10)->value('liters_tracking_period_id'))->toBeNull()
        ->and(DB::table('liters_movement_corrections')->count())->toBe(0)
        ->and($membership->inclusion_status)->toBe('removed')
        ->and($membership->removal_status)->toBe('Voided')
        ->and($membership->removal_reason)->toBe('Incorrect meter image')
        ->and($service->state()['summary']['total_liters'])->toBe(50.0);
});

test('rejection after generation recalculates the original record and never posts a negative active transaction', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $generatedPeriodId = (int) $generated['period']->id;

    $removal = $service->recordShiftReadingVoid(10, 88, 'Rejected', 'Meter total needs correction');
    DB::table('shift_readings')->where('id', 10)->update(['status' => 'Rejected']);
    $snapshot = json_decode((string) DB::table('liters_tracking_periods')
        ->where('id', $generatedPeriodId)
        ->value('totals_snapshot'), true);
    $activeState = $service->state(null, 20, 'page', true);
    $activeDiesel = collect($activeState['summary']['tank_breakdown'])->firstWhere('fuel_type', 'Diesel');
    $secondRemoval = $service->recordShiftReadingVoid(10, 88, 'Rejected', 'Meter total needs correction');

    expect($removal['action'])->toBe('generated_period_recalculated')
        ->and($secondRemoval['action'])->toBe('existing_removal')
        ->and($snapshot['total_liters'])->toEqual(50)
        ->and($snapshot['total_amount'])->toEqual(3000)
        ->and($snapshot['total_sales'])->toEqual(3000)
        ->and($snapshot['total_discounts'])->toEqual(35)
        ->and($snapshot['total_net_sales'])->toEqual(2965)
        ->and($snapshot['submission_count'])->toBe(1)
        ->and($snapshot['shift_reading_ids'])->toBe([11])
        ->and($snapshot['ending_tank_limits'])->toEqual([
            'tank:1' => 1000.0,
            'tank:2' => 1000.0,
            'tank:3' => 950.0,
        ])
        ->and($snapshot['removed_shift_readings'])->toHaveCount(1)
        ->and($snapshot['removed_shift_readings'][0]['id'])->toBe(10)
        ->and($snapshot['removed_shift_readings'][0]['removal_status'])->toBe('Rejected')
        ->and($activeState['summary']['total_liters'])->toBe(0.0)
        ->and($activeDiesel['remaining_liters'])->toBe(950.0)
        ->and($activeState['summary']['total_sales'])->toBe(0.0)
        ->and($activeState['transactions']->total())->toBe(0)
        ->and(DB::table('liters_movement_corrections')->count())->toBe(0);
});

test('approved corrected reading can be inserted idempotently into a selected overlapping generated period', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $generatedPeriodId = (int) $generated['period']->id;
    $activePeriodId = (int) $generated['new_period']->id;

    $service->recordShiftReadingVoid(10, 88, 'Rejected', 'Correct and resubmit');
    DB::table('shift_readings')->where('id', 10)->update(['status' => 'Rejected']);

    $replacement = (array) DB::table('shift_readings')->where('id', 10)->first();
    $replacement['id'] = 14;
    $replacement['liters_tracking_period_id'] = $activePeriodId;
    $replacement['status'] = 'Verified';
    $replacement['pump5_liters_end'] = 1190;
    $replacement['pump5_sales_end'] = 61400;
    $replacement['pump6_liters_end'] = 1250;
    $replacement['pump6_sales_end'] = 65000;
    $replacement['created_at'] = now();
    $replacement['updated_at'] = now();
    DB::table('shift_readings')->insert($replacement);

    DB::table('liters_tracking_periods')->insert([
        'id' => 3,
        'period_code' => 'LTM-OVERLAP-2',
        'liters_limit' => 1000,
        'tank_limits' => json_encode(['tank:1' => 1000, 'tank:2' => 1000, 'tank:3' => 1000]),
        'status' => 'generated',
        'started_at' => now()->startOfDay(),
        'ended_at' => now()->endOfDay(),
        'generated_by' => 99,
        'totals_snapshot' => json_encode(['shift_reading_ids' => []]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service->registerShiftReadingSubmission(14, 1);
    $matches = $service->matchingGeneratedPeriodsForReading(14);
    $firstInsert = $service->insertApprovedReadingIntoGeneratedPeriod(14, $generatedPeriodId, 1);
    $snapshotAfterFirstInsert = (string) DB::table('liters_tracking_periods')
        ->where('id', $generatedPeriodId)
        ->value('totals_snapshot');
    $secondInsert = $service->insertApprovedReadingIntoGeneratedPeriod(14, $generatedPeriodId, 1);
    $snapshot = json_decode((string) DB::table('liters_tracking_periods')
        ->where('id', $generatedPeriodId)
        ->value('totals_snapshot'), true);
    $activeState = $service->state(null, 20, 'page', true);

    expect(collect($matches)->pluck('id')->all())->toBe([$generatedPeriodId, 3])
        ->and($firstInsert['action'])->toBe('inserted')
        ->and($secondInsert['action'])->toBe('already_included')
        ->and((string) DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('totals_snapshot'))->toBe($snapshotAfterFirstInsert)
        ->and($snapshot['total_liters'])->toEqual(490)
        ->and($snapshot['total_amount'])->toEqual(29400)
        ->and($snapshot['submission_count'])->toBe(2)
        ->and($snapshot['shift_reading_ids'])->toBe([11, 14])
        ->and(collect($snapshot['removed_shift_readings'])->pluck('id')->all())->toBe([10])
        ->and(DB::table('liters_movement_period_readings')
            ->where('liters_tracking_period_id', $generatedPeriodId)
            ->where('shift_reading_id', 14)
            ->count())->toBe(1)
        ->and((int) DB::table('shift_readings')->where('id', 14)->value('liters_tracking_period_id'))->toBe($generatedPeriodId)
        ->and($activeState['summary']['total_liters'])->toBe(0.0)
        ->and(DB::table('liters_movement_corrections')->count())->toBe(0);
});

test('moving a reading between matching generated periods recalculates both source and destination', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $sourcePeriodId = (int) $generated['period']->id;

    DB::table('liters_tracking_periods')->insert([
        'id' => 3,
        'period_code' => 'LTM-OVERLAP-DESTINATION',
        'liters_limit' => 1000,
        'tank_limits' => json_encode(['tank:1' => 1000, 'tank:2' => 1000, 'tank:3' => 1000]),
        'status' => 'generated',
        'started_at' => now()->startOfDay(),
        'ended_at' => now()->endOfDay(),
        'generated_by' => 99,
        'totals_snapshot' => json_encode(['shift_reading_ids' => [], 'total_liters' => 0, 'total_sales' => 0]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = $service->insertApprovedReadingIntoGeneratedPeriod(10, 3, 1);
    $sourceSnapshot = json_decode((string) DB::table('liters_tracking_periods')
        ->where('id', $sourcePeriodId)
        ->value('totals_snapshot'), true);
    $destinationSnapshot = json_decode((string) DB::table('liters_tracking_periods')
        ->where('id', 3)
        ->value('totals_snapshot'), true);

    expect($result['recalculated_period_ids'])->toBe([$sourcePeriodId, 3])
        ->and($sourceSnapshot['total_liters'])->toEqual(50)
        ->and($sourceSnapshot['shift_reading_ids'])->toBe([11])
        ->and(collect($sourceSnapshot['removed_shift_readings'])->pluck('id')->all())->toBe([10])
        ->and($destinationSnapshot['total_liters'])->toEqual(450)
        ->and($destinationSnapshot['shift_reading_ids'])->toBe([10])
        ->and((int) DB::table('shift_readings')->where('id', 10)->value('liters_tracking_period_id'))->toBe(3);
});

test('generated-period matching uses business dates instead of submission or approval timestamps', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $generatedPeriodId = (int) $generated['period']->id;
    $activePeriodId = (int) $generated['new_period']->id;
    $outsideDate = now()->subYears(3)->toDateString();

    $reading = (array) DB::table('shift_readings')->where('id', 10)->first();
    $reading['id'] = 14;
    $reading['liters_tracking_period_id'] = $activePeriodId;
    $reading['period_from'] = $outsideDate;
    $reading['period_to'] = $outsideDate;
    $reading['status'] = 'Verified';
    $reading['created_at'] = now();
    $reading['updated_at'] = now();
    DB::table('shift_readings')->insert($reading);
    $service->registerShiftReadingSubmission(14, 1);

    expect($service->matchingGeneratedPeriodsForReading(14))->toBe([]);

    expect(fn () => $service->insertApprovedReadingIntoGeneratedPeriod(14, $generatedPeriodId, 1))
        ->toThrow(RuntimeException::class, 'does not overlap');
});
