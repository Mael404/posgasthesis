<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\CustomerController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('customer_ledgers');
    Schema::dropIfExists('customers');

    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('business_name')->nullable();
        $table->string('owner_name')->nullable();
        $table->string('contact_number')->nullable();
        $table->decimal('current_balance', 12, 2)->default(0);
        $table->decimal('credit_limit', 12, 2)->default(0);
        $table->string('payment_terms')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('customer_ledgers', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('customer_id');
        $table->unsignedBigInteger('invoice_id')->nullable();
        $table->date('transaction_date');
        $table->string('type', 50);
        $table->string('reference_no')->nullable();
        $table->string('or_number')->nullable();
        $table->string('description')->nullable();
        $table->decimal('debit', 12, 2)->default(0);
        $table->decimal('credit', 12, 2)->default(0);
        $table->decimal('discount', 12, 2)->default(0);
        $table->decimal('running_balance', 12, 2)->default(0);
        $table->timestamps();
    });

    DB::table('customers')->insert([
        'id' => 1,
        'business_name' => 'Demo Customer',
        'owner_name' => 'Demo Owner',
        'current_balance' => 24888,
        'credit_limit' => 100000,
        'payment_terms' => '15 Days',
        'status' => 'Active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

test('billing invoice rows exclude fuel sale voided after the invoice period', function () {
    insertBillingSoaLedgerRows([
        [
            'id' => 1,
            'invoice_id' => 10,
            'transaction_date' => '2026-07-14',
            'type' => 'Fuel Sale',
            'reference_no' => 'EDIT-1688',
            'description' => 'Credit Charge: Premium - 270L (Pump 1)',
            'debit' => 19764,
            'running_balance' => 19764,
        ],
        [
            'id' => 2,
            'invoice_id' => 10,
            'transaction_date' => '2026-07-14',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-ACTIVE-PREMIUM',
            'description' => 'Credit Charge: Premium - 250L (Pump 1)',
            'debit' => 18300,
            'running_balance' => 38064,
        ],
        [
            'id' => 3,
            'invoice_id' => 10,
            'transaction_date' => '2026-07-14',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-ACTIVE-UNLEADED',
            'description' => 'Credit Charge: Unleaded - 90L (Pump 4)',
            'debit' => 6588,
            'running_balance' => 44652,
        ],
        [
            'id' => 4,
            'invoice_id' => null,
            'transaction_date' => '2026-07-20',
            'type' => 'Void',
            'reference_no' => 'VOID-1688',
            'description' => 'VOID Transaction: Ref EDIT-1688',
            'credit' => 19764,
            'running_balance' => 24888,
        ],
    ]);

    $invoice = (object) [
        'id' => 10,
        'customer_id' => 1,
        'period_start' => '2026-07-14',
        'period_end' => '2026-07-14',
    ];

    $rows = callPrivateBillingSoaMethod(new BillingController(), 'billingRowsForInvoice', [$invoice]);

    expect($rows->pluck('id')->all())->toBe([2, 3])
        ->and((float) $rows->sum('debit'))->toBe(24888.0);
});

test('customer soa is generated from active ledger charges only', function () {
    insertBillingSoaLedgerRows([
        [
            'id' => 1,
            'transaction_date' => '2026-07-14',
            'type' => 'Fuel Sale',
            'reference_no' => 'EDIT-1688',
            'description' => 'Credit Charge: Premium - 270L (Pump 1)',
            'debit' => 19764,
            'running_balance' => 19764,
        ],
        [
            'id' => 2,
            'transaction_date' => '2026-07-14',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-ACTIVE-PREMIUM',
            'description' => 'Credit Charge: Premium - 250L (Pump 1)',
            'debit' => 18300,
            'running_balance' => 38064,
        ],
        [
            'id' => 3,
            'transaction_date' => '2026-07-14',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-ACTIVE-UNLEADED',
            'description' => 'Credit Charge: Unleaded - 90L (Pump 4)',
            'debit' => 6588,
            'running_balance' => 44652,
        ],
        [
            'id' => 4,
            'transaction_date' => '2026-07-20',
            'type' => 'Void',
            'reference_no' => 'VOID-1688',
            'description' => 'VOID Transaction: Ref EDIT-1688',
            'credit' => 19764,
            'running_balance' => 24888,
        ],
    ]);

    $view = (new CustomerController())->generateSOA(Request::create('/customers/soa', 'GET', [
        'customer_id' => 1,
        'start_date' => '2026-07-14',
        'end_date' => '2026-07-14',
    ]));
    $data = $view->getData();

    expect($data['transactions']->pluck('id')->all())->toBe([2, 3])
        ->and((float) $data['previousBalance'])->toBe(0.0)
        ->and((float) $data['transactions']->sum('debit'))->toBe(24888.0);
});

test('older external reversals do not exclude later active charges with reused references', function () {
    insertBillingSoaLedgerRows([
        [
            'id' => 1,
            'invoice_id' => null,
            'transaction_date' => '2026-07-01',
            'type' => 'Void',
            'reference_no' => 'VOID-OLD',
            'description' => 'VOID Transaction: Ref REUSED-OR',
            'credit' => 1000,
        ],
        [
            'id' => 2,
            'invoice_id' => 11,
            'transaction_date' => '2026-07-14',
            'type' => 'Fuel Sale',
            'reference_no' => 'REUSED-OR',
            'description' => 'Credit Charge: Diesel - 10L (Pump 5)',
            'debit' => 1000,
        ],
    ]);

    $invoice = (object) [
        'id' => 11,
        'customer_id' => 1,
        'period_start' => '2026-07-14',
        'period_end' => '2026-07-14',
    ];

    $rows = callPrivateBillingSoaMethod(new BillingController(), 'billingRowsForInvoice', [$invoice]);

    expect($rows->pluck('id')->all())->toBe([2]);
});

function insertBillingSoaLedgerRows(array $rows): void
{
    foreach ($rows as $row) {
        DB::table('customer_ledgers')->insert(array_merge([
            'customer_id' => 1,
            'invoice_id' => null,
            'transaction_date' => '2026-07-14',
            'type' => 'Fuel Sale',
            'reference_no' => null,
            'or_number' => null,
            'description' => null,
            'debit' => 0,
            'credit' => 0,
            'discount' => 0,
            'running_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $row));
    }
}

function callPrivateBillingSoaMethod(object $target, string $method, array $arguments = [])
{
    $reflection = new ReflectionMethod($target, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($target, $arguments);
}
