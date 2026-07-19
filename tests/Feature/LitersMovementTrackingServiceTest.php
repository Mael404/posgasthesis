<?php

use App\Services\LitersMovementTrackingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
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
        ->and(DB::table('shift_readings')->whereIn('id', [10, 11])->pluck('liters_tracking_period_id', 'id')->all())->toBe([10 => 1, 11 => 1])
        ->and(DB::table('shift_readings')->whereIn('id', [12, 13])->whereNull('liters_tracking_period_id')->count())->toBe(2);
});

test('net sales uses submitted pump totals while discounts stay scoped to eligible readings', function () {
    $state = app(LitersMovementTrackingService::class)->state(null, 20, 'page', true);
    $pumpSales = collect($state['summary']['pump_net_sales'])->keyBy('pump_id');

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
        ->and($state['summary']['total_net_sales'])->toBe(29845.0);
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

test('void before generation detaches the reading without creating a correction', function () {
    $service = app(LitersMovementTrackingService::class);
    $service->state();

    $result = $service->recordShiftReadingVoid(10, 1);
    DB::table('shift_readings')->where('id', 10)->update(['status' => 'Voided']);

    expect($result['action'])->toBe('detached')
        ->and(DB::table('shift_readings')->where('id', 10)->value('liters_tracking_period_id'))->toBeNull()
        ->and(DB::table('liters_movement_corrections')->count())->toBe(0)
        ->and($service->state()['summary']['total_liters'])->toBe(50.0);
});

test('void after generation and resubmission posts only the signed net correction', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $generatedPeriodId = (int) $generated['period']->id;
    $activePeriodId = (int) $generated['new_period']->id;
    $savedSnapshot = (string) DB::table('liters_tracking_periods')
        ->where('id', $generatedPeriodId)
        ->value('totals_snapshot');

    $voidResult = $service->recordShiftReadingVoid(10, 88);
    DB::table('shift_readings')->where('id', 10)->update(['status' => 'Voided']);

    expect($voidResult['action'])->toBe('reversal_created')
        ->and(DB::table('liters_movement_corrections')->where('entry_type', 'reversal')->sum('liters'))->toEqual(-450)
        ->and((string) DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('totals_snapshot'))->toBe($savedSnapshot);

    expect(fn () => $service->generateAndReset(77))
        ->toThrow(RuntimeException::class, 'pending generated-reading reversal');

    $replacement = (array) DB::table('shift_readings')->where('id', 10)->first();
    $replacement['id'] = 14;
    $replacement['liters_tracking_period_id'] = $activePeriodId;
    $replacement['status'] = 'Pending';
    $replacement['pump5_liters_end'] = 1190;
    $replacement['pump5_sales_end'] = 61400;
    $replacement['pump6_liters_end'] = 1250;
    $replacement['pump6_sales_end'] = 65000;
    $replacement['created_at'] = now();
    $replacement['updated_at'] = now();
    DB::table('shift_readings')->insert($replacement);

    $replacementResult = $service->registerShiftReadingReplacement(14, 1);
    $state = $service->state(null, 20, 'page', true);
    $transaction = $state['transactions']->getCollection()->first();

    expect($replacementResult['action'])->toBe('replacement_created')
        ->and(DB::table('liters_movement_corrections')->where('entry_type', 'reversal')->where('status', 'matched')->count())->toBe(2)
        ->and(DB::table('liters_movement_corrections')->where('entry_type', 'replacement')->where('status', 'applied')->sum('liters'))->toEqual(440)
        ->and($state['summary']['total_liters'])->toBe(-10.0)
        ->and($state['summary']['total_amount'])->toBe(-600.0)
        ->and($state['summary']['submission_count'])->toBe(1)
        ->and($state['transactions']->total())->toBe(1)
        ->and($transaction->status)->toBe('Correction Applied')
        ->and($transaction->is_liters_movement_correction)->toBeTrue()
        ->and($transaction->can_adjust_liters)->toBeFalse()
        ->and(collect($transaction->pump_entries)->sum('liters'))->toBe(-10.0)
        ->and(collect($transaction->pump_entries)->where('correction_entry_type', 'reversal'))->toHaveCount(2)
        ->and(collect($transaction->pump_entries)->where('correction_entry_type', 'replacement'))->toHaveCount(2)
        ->and((string) DB::table('liters_tracking_periods')->where('id', $generatedPeriodId)->value('totals_snapshot'))->toBe($savedSnapshot);
});

test('generated reading correction methods are idempotent and reject a second replacement', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $activePeriodId = (int) $generated['new_period']->id;

    $service->recordShiftReadingVoid(10, 88);
    $secondVoid = $service->recordShiftReadingVoid(10, 88);
    DB::table('shift_readings')->where('id', 10)->update(['status' => 'Voided']);

    $replacement = (array) DB::table('shift_readings')->where('id', 10)->first();
    $replacement['id'] = 14;
    $replacement['liters_tracking_period_id'] = $activePeriodId;
    $replacement['status'] = 'Pending';
    $replacement['created_at'] = now();
    $replacement['updated_at'] = now();
    DB::table('shift_readings')->insert($replacement);

    $service->registerShiftReadingReplacement(14, 1);
    $sameReplacement = $service->registerShiftReadingReplacement(14, 1);

    $duplicate = $replacement;
    $duplicate['id'] = 15;
    $duplicate['created_at'] = now()->addSecond();
    $duplicate['updated_at'] = now()->addSecond();
    DB::table('shift_readings')->insert($duplicate);

    expect($secondVoid['action'])->toBe('existing_reversal')
        ->and($sameReplacement['action'])->toBe('existing_replacement')
        ->and(DB::table('liters_movement_corrections')->count())->toBe(4);

    expect(fn () => $service->registerShiftReadingReplacement(15, 1))
        ->toThrow(RuntimeException::class, 'already has a corrected replacement');
});

test('only voidGeneratedPeriod cancels corrections tied to the restored generated batch', function () {
    $service = app(LitersMovementTrackingService::class);
    $generated = $service->generateAndReset(99);
    $activePeriodId = (int) $generated['new_period']->id;

    $service->recordShiftReadingVoid(10, 88);
    DB::table('shift_readings')->where('id', 10)->update(['status' => 'Voided']);

    $replacement = (array) DB::table('shift_readings')->where('id', 10)->first();
    $replacement['id'] = 14;
    $replacement['liters_tracking_period_id'] = $activePeriodId;
    $replacement['status'] = 'Pending';
    $replacement['created_at'] = now();
    $replacement['updated_at'] = now();
    DB::table('shift_readings')->insert($replacement);
    $service->registerShiftReadingReplacement(14, 1);

    $result = $service->voidGeneratedPeriod((int) $generated['period']->id, 77);
    $state = $service->state();

    expect($result['cancelled_correction_ids'])->toHaveCount(4)
        ->and(DB::table('liters_movement_corrections')->where('status', 'cancelled')->count())->toBe(4)
        ->and($state['summary']['total_liters'])->toBe(500.0)
        ->and($state['summary']['submission_count'])->toBe(2);
});
