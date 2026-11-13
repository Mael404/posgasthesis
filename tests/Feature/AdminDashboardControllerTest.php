<?php

use App\Http\Controllers\AdminDashboardController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow('2026-11-13 12:00:00');

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->decimal('current_balance', 14, 2);
    });
    Schema::create('customer_ledgers', function (Blueprint $table) {
        $table->id();
        $table->string('type');
        $table->dateTime('transaction_date');
        $table->decimal('credit', 14, 2)->default(0);
        $table->decimal('debit', 14, 2)->default(0);
        $table->decimal('running_balance', 14, 2)->default(0);
    });
    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('locked_by_shift_reading_id')->nullable();
        $table->string('payment_type')->nullable();
        $table->decimal('amount', 14, 2);
        $table->decimal('discount', 14, 2)->default(0);
        $table->decimal('manual_discount', 14, 2)->default(0);
        $table->timestamps();
    });
    Schema::create('shift_readings', function (Blueprint $table) {
        $table->id();
        $table->date('period_from');
        $table->date('period_to')->nullable();
        $table->string('status')->nullable();
        for ($pump = 1; $pump <= 6; $pump++) {
            foreach (['sales', 'liters'] as $metric) {
                $table->decimal("pump{$pump}_{$metric}_start", 14, 2)->default(0);
                $table->decimal("pump{$pump}_{$metric}_end", 14, 2)->default(0);
            }
        }
        $table->timestamps();
    });
    Schema::create('tanks', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('fuel_type');
        $table->decimal('current_volume', 14, 2);
        $table->decimal('capacity', 14, 2);
    });

    DB::table('customers')->insert([['current_balance' => 500], ['current_balance' => 250], ['current_balance' => -100]]);
    DB::table('tanks')->insert([
        ['name' => 'Premium Main', 'fuel_type' => 'Premium', 'current_volume' => -25.5, 'capacity' => 1000],
        ['name' => 'Diesel Main', 'fuel_type' => 'Diesel', 'current_volume' => 800, 'capacity' => 1000],
    ]);
    foreach ([
        [1, '2026-11-13', 'Verified', 1000, 20],
        [2, '2026-11-13', 'Pending', 200, 5],
        [3, '2026-11-12', 'Approved', 300, 6],
        [4, '2026-10-31', 'Verified', 400, 8],
        [5, '2026-11-13', 'Rejected', 900, 18],
    ] as [$id, $date, $status, $sales, $liters]) {
        DB::table('shift_readings')->insert([
            'id' => $id, 'period_from' => $date, 'period_to' => $date, 'status' => $status,
            'pump1_sales_end' => $sales, 'pump1_liters_end' => $liters,
            'created_at' => "{$date} 18:00:00", 'updated_at' => "{$date} 18:00:00",
        ]);
    }
    foreach ([
        [1, '2026-11-13 00:00:00', 'Cash', 600, 10, 5],
        [1, '2026-11-13 23:59:59', 'Credit', 400, 20, 5],
        [2, '2026-11-13 12:00:00', 'Cash', 200, 0, 0],
        [3, '2026-11-12 12:00:00', 'Credit', 300, 0, 0],
        [4, '2026-10-31 12:00:00', 'Cash', 400, 0, 0],
        [null, '2026-11-13 12:00:00', 'Collection', 999, 0, 0],
    ] as [$reading, $date, $type, $amount, $discount, $manual]) {
        DB::table('sales')->insert([
            'locked_by_shift_reading_id' => $reading, 'payment_type' => $type, 'amount' => $amount,
            'discount' => $discount, 'manual_discount' => $manual, 'created_at' => $date, 'updated_at' => $date,
        ]);
    }
    foreach ([['2026-11-13 00:00:00', 100], ['2026-11-13 23:59:59', 150], ['2026-11-12 12:00:00', 50]] as [$date, $credit]) {
        DB::table('customer_ledgers')->insert(['type' => 'Payment', 'transaction_date' => $date, 'credit' => $credit]);
    }
    DB::table('customer_ledgers')->insert(['type' => 'Sale', 'transaction_date' => '2026-11-13 12:00:00', 'credit' => 999]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('dashboard preserves verified net sales, credit modes, payment boundaries and liters', function () {
    $data = (new AdminDashboardController)->getDashboardData(Request::create('/api/dashboard-data'))->getData(true);
    $stats = $data['stats'];

    expect($stats['shift_reading_verified_net_sales_with_credit'])->toEqual(960)
        ->and($stats['shift_reading_verified_net_sales_without_credit'])->toEqual(560)
        ->and($stats['shift_reading_pending_net_sales_with_credit'])->toEqual(200)
        ->and($stats['shift_reading_all_net_sales_with_credit'])->toEqual(1160)
        ->and($stats['shift_reading_verified_count'])->toBe(1)
        ->and($stats['expected_credit_receivables'])->toEqual(750)
        ->and($stats['expected_credit_sales'])->toEqual(400)
        ->and($stats['credit_payments_received'])->toEqual(250)
        ->and($stats['credit_payment_count'])->toBe(2)
        ->and($stats['shift_reading_verified_liters'])->toEqual(20)
        ->and($stats['shift_reading_unverified_liters'])->toEqual(5)
        ->and($data['chartLabels'])->toBe(['2026-11-13'])
        ->and($data['chartData'])->toEqual([1160]);
});

test('one dashboard range applies to activity and trend while balances stay current', function ($period, $from, $to, $netSales, $payments, $creditSales, $pending) {
    $data = (new AdminDashboardController)->getDashboardData(Request::create('/api/dashboard-data', 'GET', [
        'period' => $period, 'from' => $from, 'to' => $to,
    ]))->getData(true);

    expect($data['filter']['from'])->toBe($from)
        ->and($data['filter']['to'])->toBe($to)
        ->and($data['stats']['shift_reading_verified_net_sales_with_credit'])->toEqual($netSales)
        ->and($data['stats']['credit_payments_received'])->toEqual($payments)
        ->and($data['stats']['expected_credit_sales'])->toEqual($creditSales)
        ->and($data['stats']['expected_credit_receivables'])->toEqual(750)
        ->and($data['inventory'][0]['current_volume'])->toEqual(-25.5)
        ->and($data['inventory'][0]['percentage'])->toEqual(0)
        ->and($data['warnings']['negative_inventory'])->toHaveCount(1)
        ->and($data['warnings']['pending_readings'])->toBe($pending)
        ->and($data['chartLabels'][0])->toBe($from)
        ->and(end($data['chartLabels']))->toBe($to);
})->with([
    'today' => ['today', '2026-11-13', '2026-11-13', 960, 250, 400, 1],
    'week' => ['weekly', '2026-11-09', '2026-11-13', 1260, 300, 700, 1],
    'month' => ['monthly', '2026-11-01', '2026-11-13', 1260, 300, 700, 1],
    'custom' => ['custom', '2026-10-31', '2026-10-31', 400, 0, 0, 0],
    'empty' => ['custom', '2026-01-01', '2026-01-02', 0, 0, 0, 0],
]);

test('custom trend fills missing days and normalizes reversed dates', function () {
    $data = (new AdminDashboardController)->getDashboardData(Request::create('/api/dashboard-data', 'GET', [
        'period' => 'custom', 'from' => '2026-11-13', 'to' => '2026-11-11',
    ]))->getData(true);

    expect($data['filter']['from'])->toBe('2026-11-11')
        ->and($data['filter']['to'])->toBe('2026-11-13')
        ->and($data['chartData'])->toEqual([0, 300, 1160])
        ->and($data['stats']['shift_reading_verified_liters'])->toEqual(26);
});

test('invalid or incomplete custom dates cannot silently show the wrong period', function ($query) {
    expect(fn () => (new AdminDashboardController)->getDashboardData(Request::create('/api/dashboard-data', 'GET', $query)))
        ->toThrow(ValidationException::class);
})->with([
    [['period' => 'custom', 'from' => '2026-02-30', 'to' => '2026-11-13']],
    [['period' => 'custom', 'from' => '2026-11-13']],
    [['period' => 'custom', 'from' => '', 'to' => '']],
    [['period' => 'unknown']],
]);

test('dashboard renders exactly six sections with a single date filter and no detailed tables', function () {
    $view = (new AdminDashboardController)->dashboard(Request::create('/admin/dashboard'));
    $source = file_get_contents(resource_path('views/pages/admin/admindashboard.blade.php'));
    $source = str_replace("@extends('layouts.app')", '', $source) . "\n@yield('content')\n@stack('scripts')";
    $html = Blade::render($source, $view->getData());

    expect(substr_count($html, '<article '))->toBe(6)
        ->and(substr_count($html, '<table'))->toBe(0)
        ->and(substr_count($html, 'name="from"'))->toBe(1)
        ->and(substr_count($html, 'name="to"'))->toBe(1);
});

test('dashboard has safe empty states when optional data tables are unavailable', function () {
    foreach (['sales', 'shift_readings', 'customers', 'customer_ledgers', 'tanks'] as $table) {
        Schema::drop($table);
    }
    $data = (new AdminDashboardController)->getDashboardData(Request::create('/api/dashboard-data'))->getData(true);

    expect($data['inventory'])->toBe([])
        ->and($data['stats']['shift_reading_verified_net_sales_with_credit'])->toEqual(0)
        ->and($data['stats']['credit_payments_received'])->toEqual(0)
        ->and($data['stats']['expected_credit_receivables'])->toEqual(0)
        ->and($data['warnings']['pending_readings'])->toBe(0)
        ->and($data['warnings']['negative_inventory'])->toBe([])
        ->and($data['chartData'])->toEqual([0]);
});
