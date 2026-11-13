<?php

use App\Http\Controllers\AdminReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('sales');
    Schema::dropIfExists('shift_readings');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('shift_readings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->date('period_from');
        $table->date('period_to')->nullable();
        $table->string('status')->nullable();

        for ($pump = 1; $pump <= 6; $pump++) {
            $table->decimal("pump{$pump}_sales_start", 14, 2)->nullable();
            $table->decimal("pump{$pump}_sales_end", 14, 2)->nullable();
            $table->decimal("pump{$pump}_liters_start", 14, 2)->nullable();
            $table->decimal("pump{$pump}_liters_end", 14, 2)->nullable();
        }

        $table->timestamps();
    });

    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->foreignId('locked_by_shift_reading_id')->nullable();
        $table->unsignedInteger('pump_id')->nullable();
        $table->string('fuel_type')->nullable();
        $table->decimal('amount', 14, 2)->default(0);
        $table->decimal('discount', 14, 2)->default(0);
        $table->decimal('manual_discount', 14, 2)->default(0);
        $table->timestamps();
    });

    DB::table('users')->insert([
        'id' => 1,
        'name' => 'Test Staff',
    ]);

    $reading = function (int $id, string $date, string $status, float $salesStart, float $salesEnd): array {
        $row = [
            'id' => $id,
            'user_id' => 1,
            'period_from' => $date,
            'period_to' => $date,
            'status' => $status,
            'created_at' => "{$date} 18:00:00",
            'updated_at' => "{$date} 18:00:00",
        ];

        for ($pump = 1; $pump <= 6; $pump++) {
            $row["pump{$pump}_sales_start"] = $pump === 1 ? $salesStart : 0;
            $row["pump{$pump}_sales_end"] = $pump === 1 ? $salesEnd : 0;
            $row["pump{$pump}_liters_start"] = 0;
            $row["pump{$pump}_liters_end"] = $pump === 1 ? 10 : 0;
        }

        return $row;
    };

    DB::table('shift_readings')->insert([
        $reading(1, '2026-08-01', 'Pending', 100, 1100),
        $reading(2, '2026-08-02', 'Verified', 1100, 2100),
        $reading(3, '2026-08-03', 'Rejected', 2100, 7100),
        $reading(4, '2026-08-04', 'Voided', 7100, 12100),
    ]);

    DB::table('sales')->insert([
        [
            'user_id' => 1,
            'locked_by_shift_reading_id' => 1,
            'pump_id' => 1,
            'fuel_type' => 'Premium',
            'amount' => 1000,
            'discount' => 50,
            'manual_discount' => 25,
            'created_at' => '2026-08-01 12:00:00',
            'updated_at' => '2026-08-01 12:00:00',
        ],
        [
            'user_id' => 1,
            'locked_by_shift_reading_id' => 2,
            'pump_id' => 1,
            'fuel_type' => 'Premium',
            'amount' => 1000,
            'discount' => 100,
            'manual_discount' => 0,
            'created_at' => '2026-08-02 12:00:00',
            'updated_at' => '2026-08-02 12:00:00',
        ],
        [
            'user_id' => 1,
            'locked_by_shift_reading_id' => 1,
            'pump_id' => 1,
            'fuel_type' => 'Premium',
            'amount' => 500,
            'discount' => 0,
            'manual_discount' => 0,
            'created_at' => '2026-08-01 13:00:00',
            'updated_at' => '2026-08-01 13:00:00',
        ],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('sales');
    Schema::dropIfExists('shift_readings');
    Schema::dropIfExists('users');
});

test('both status filter uses pending and approved shift readings for dashboard-style net sales', function () {
    $view = (new AdminReportController)->index(Request::create('/admin/sales-monitoring', 'GET', [
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'reading_status' => 'all',
    ]));
    $data = $view->getData();

    expect($data['shiftReadings'])->toHaveCount(2)
        ->and($data['shiftReadings']->pluck('id')->all())->toBe([2, 1])
        ->and($data['transactions'])->toHaveCount(3)
        ->and($data['transactions']->pluck('locked_by_shift_reading_id')->all())->toBe([2, 1, 1])
        ->and($data['transactionTotals'])->toBe([
            'records' => 3,
            'liters' => 0.0,
            'discounts' => 175.0,
            'amount' => 2500.0,
        ])
        ->and($data['totalGrossSales'])->toBe(2000.0)
        ->and($data['totalDiscounts'])->toBe(175.0)
        ->and($data['totalNetSales'])->toBe(1825.0)
        ->and($data['reportData']['Premium']['net_sales'])->toBe(1825.0);
});

test('pending and approved filters return only their matching readings', function (string $status, array $ids, float $netSales) {
    $view = (new AdminReportController)->index(Request::create('/admin/sales-monitoring', 'GET', [
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'reading_status' => $status,
    ]));
    $data = $view->getData();

    expect($data['shiftReadings']->pluck('id')->all())->toBe($ids)
        ->and($data['transactions']->pluck('locked_by_shift_reading_id')->unique()->values()->all())->toBe($ids)
        ->and($data['totalNetSales'])->toBe($netSales);
})->with([
    'pending' => ['pending', [1], 925.0],
    'approved' => ['approved', [2], 900.0],
]);

test('staff filter limits both shift summaries and individual transactions', function () {
    DB::table('users')->insert([
        'id' => 2,
        'name' => 'Second Staff',
    ]);
    $secondStaffReading = (array) DB::table('shift_readings')->where('id', 2)->first();
    $secondStaffReading['id'] = 5;
    $secondStaffReading['user_id'] = 2;
    $secondStaffReading['period_from'] = '2026-08-05';
    $secondStaffReading['period_to'] = '2026-08-05';
    $secondStaffReading['pump1_sales_start'] = 0;
    $secondStaffReading['pump1_sales_end'] = 500;
    $secondStaffReading['created_at'] = '2026-08-05 18:00:00';
    $secondStaffReading['updated_at'] = '2026-08-05 18:00:00';
    DB::table('shift_readings')->insert($secondStaffReading);
    DB::table('sales')->insert([
        'user_id' => 2,
        'locked_by_shift_reading_id' => 5,
        'pump_id' => 1,
        'fuel_type' => 'Premium',
        'amount' => 500,
        'discount' => 0,
        'manual_discount' => 0,
        'created_at' => '2026-08-05 12:00:00',
        'updated_at' => '2026-08-05 12:00:00',
    ]);

    $view = (new AdminReportController)->index(Request::create('/admin/sales-monitoring', 'GET', [
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'reading_status' => 'all',
        'staff_id' => 2,
    ]));
    $data = $view->getData();

    expect($data['staffOptions']->pluck('name')->all())->toBe(['Second Staff', 'Test Staff'])
        ->and($data['shiftReadings']->pluck('id')->all())->toBe([5])
        ->and($data['transactions'])->toHaveCount(1)
        ->and($data['transactions']->first()->staff_name)->toBe('Second Staff')
        ->and($data['transactionTotals']['amount'])->toBe(500.0);
});

test('date filter returns each individual sale from matching eligible shifts', function () {
    $view = (new AdminReportController)->index(Request::create('/admin/sales-monitoring', 'GET', [
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-01',
        'reading_status' => 'all',
        'staff_id' => 1,
    ]));
    $data = $view->getData();

    expect($data['shiftReadings']->pluck('id')->all())->toBe([1])
        ->and($data['transactions'])->toHaveCount(2)
        ->and($data['transactions']->pluck('locked_by_shift_reading_id')->all())->toBe([1, 1])
        ->and($data['transactionTotals']['amount'])->toBe(1500.0);
});

test('transaction log pages preserve filters and totals across all matching transactions', function (int $requestedPage, int $currentPage, array $saleIds) {
    DB::table('sales')->insert(array_fill(0, 13, [
        'user_id' => 1,
        'locked_by_shift_reading_id' => 2,
        'pump_id' => 1,
        'fuel_type' => 'Premium',
        'amount' => 100,
        'discount' => 2,
        'manual_discount' => 3,
        'created_at' => '2026-08-02 12:00:00',
        'updated_at' => '2026-08-02 12:00:00',
    ]));
    $filters = [
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'reading_status' => 'approved',
        'staff_id' => 1,
        'tab' => 'sales',
    ];
    $view = (new AdminReportController)->index(Request::create('/admin/sales-monitoring', 'GET', [
        ...$filters,
        'transaction_page' => $requestedPage,
    ]));
    $data = $view->getData();
    $transactions = $data['transactions'];
    parse_str(parse_url($transactions->url(1), PHP_URL_QUERY), $pageQuery);

    expect($transactions->currentPage())->toBe($currentPage)
        ->and($transactions->perPage())->toBe(10)
        ->and($transactions->lastPage())->toBe(2)
        ->and($transactions->total())->toBe(14)
        ->and($transactions->pluck('id')->all())->toBe($saleIds)
        ->and($pageQuery)->toMatchArray(array_map('strval', $filters))
        ->and($pageQuery['transaction_page'])->toBe('1')
        ->and(parse_url($transactions->url(1), PHP_URL_FRAGMENT))->toBe('transaction-log')
        ->and($data['transactionTotals'])->toBe([
            'records' => 14,
            'liters' => 0.0,
            'discounts' => 165.0,
            'amount' => 2300.0,
        ]);
})->with([
    'first page' => [1, 1, range(16, 7)],
    'last page' => [2, 2, [6, 5, 4, 2]],
    'page beyond last' => [99, 2, [6, 5, 4, 2]],
    'negative page' => [-1, 1, range(16, 7)],
]);
