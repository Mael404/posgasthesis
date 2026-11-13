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
        $table->unsignedBigInteger('source_sale_id')->nullable();
        $table->unsignedBigInteger('reversal_of_ledger_id')->nullable();
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

test('billing excludes an edited charge that was voided before the replacement sale', function () {
    insertBillingSoaLedgerRows([
        [
            'id' => 1,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-167146R2026',
            'or_number' => 'CBA 1831',
            'description' => 'Credit Charge: Diesel - 480L (Pump 6)',
            'debit' => 41280,
        ],
        [
            'id' => 2,
            'transaction_date' => '2026-08-21',
            'type' => 'Edit Reversal',
            'reference_no' => 'EDIT-2044',
            'or_number' => 'CBA 1831',
            'description' => 'Edit Reversal: Original transaction amount from TXN-167146R2026',
            'credit' => 41280,
        ],
        [
            'id' => 3,
            'invoice_id' => 12,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'EDIT-2044',
            'or_number' => 'CBA 1831',
            'description' => 'Credit Charge: Diesel - 464.67L (Pump 6) from TXN-167146R2026',
            'debit' => 39961.62,
        ],
        [
            'id' => 4,
            'transaction_date' => '2026-08-21',
            'type' => 'Void',
            'reference_no' => 'VOID-2044',
            'or_number' => 'CBA 1831',
            'description' => 'VOID Transaction: Ref TXN-167146R2026',
            'credit' => 39961.62,
        ],
        [
            'id' => 5,
            'invoice_id' => 12,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-257898M2026',
            'or_number' => 'CBA 1831',
            'description' => 'Credit Charge: Diesel - 464.67L (Pump 6)',
            'debit' => 39961.62,
        ],
    ]);

    $invoice = (object) [
        'id' => 12,
        'customer_id' => 1,
        'period_start' => '2026-08-21',
        'period_end' => '2026-08-21',
    ];

    $rows = callPrivateBillingSoaMethod(new BillingController(), 'billingRowsForInvoice', [$invoice]);

    expect($rows->pluck('id')->all())->toBe([5])
        ->and((float) $rows->sum('debit'))->toBe(39961.62);
});

test('customer soa excludes an edited charge that was subsequently voided', function () {
    insertBillingSoaLedgerRows([
        [
            'id' => 1,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-167146R2026',
            'or_number' => 'CBA 1831',
            'description' => 'Credit Charge: Diesel - 480L (Pump 6)',
            'debit' => 41280,
        ],
        [
            'id' => 2,
            'transaction_date' => '2026-08-21',
            'type' => 'Edit Reversal',
            'reference_no' => 'EDIT-2044',
            'or_number' => 'CBA 1831',
            'description' => 'Edit Reversal: Original transaction amount from TXN-167146R2026',
            'credit' => 41280,
        ],
        [
            'id' => 3,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'EDIT-2044',
            'or_number' => 'CBA 1831',
            'description' => 'Credit Charge: Diesel - 464.67L (Pump 6) from TXN-167146R2026',
            'debit' => 39961.62,
        ],
        [
            'id' => 4,
            'transaction_date' => '2026-08-21',
            'type' => 'Void',
            'reference_no' => 'VOID-2044',
            'or_number' => 'CBA 1831',
            'description' => 'VOID Transaction: Ref TXN-167146R2026',
            'credit' => 39961.62,
        ],
        [
            'id' => 5,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-257898M2026',
            'or_number' => 'CBA 1831',
            'description' => 'Credit Charge: Diesel - 464.67L (Pump 6)',
            'debit' => 39961.62,
        ],
    ]);

    $view = (new CustomerController())->generateSOA(Request::create('/customers/soa', 'GET', [
        'customer_id' => 1,
        'start_date' => '2026-08-21',
        'end_date' => '2026-08-21',
    ]));
    $data = $view->getData();

    expect($data['transactions']->pluck('id')->all())->toBe([5])
        ->and((float) $data['transactions']->sum('debit'))->toBe(39961.62);
});

test('billing uses an exact reversal link when multiple charges have the same amount', function () {
    insertBillingSoaLedgerRows([
        [
            'id' => 1,
            'invoice_id' => 13,
            'source_sale_id' => 3001,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-VOIDED',
            'debit' => 5000,
        ],
        [
            'id' => 2,
            'invoice_id' => 13,
            'source_sale_id' => 3002,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-ACTIVE',
            'debit' => 5000,
        ],
        [
            'id' => 3,
            'source_sale_id' => 3001,
            'reversal_of_ledger_id' => 1,
            'transaction_date' => '2026-08-21',
            'type' => 'Void',
            'reference_no' => 'VOID-3001',
            'description' => 'Legacy description without a usable transaction reference',
            'credit' => 5000,
        ],
    ]);

    $invoice = (object) [
        'id' => 13,
        'customer_id' => 1,
        'period_start' => '2026-08-21',
        'period_end' => '2026-08-21',
    ];

    $rows = callPrivateBillingSoaMethod(new BillingController(), 'billingRowsForInvoice', [$invoice]);

    expect($rows->pluck('id')->all())->toBe([2])
        ->and((float) $rows->sum('debit'))->toBe(5000.0);
});

test('billing follows exact links through repeated edits and a final void', function () {
    insertBillingSoaLedgerRows([
        [
            'id' => 1,
            'invoice_id' => 14,
            'source_sale_id' => 4001,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-ORIGINAL',
            'debit' => 10000,
        ],
        [
            'id' => 2,
            'source_sale_id' => 4001,
            'reversal_of_ledger_id' => 1,
            'transaction_date' => '2026-08-21',
            'type' => 'Edit Reversal',
            'reference_no' => 'EDIT-4001',
            'credit' => 10000,
        ],
        [
            'id' => 3,
            'invoice_id' => 14,
            'source_sale_id' => 4001,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'EDIT-4001',
            'debit' => 9000,
        ],
        [
            'id' => 4,
            'source_sale_id' => 4001,
            'reversal_of_ledger_id' => 3,
            'transaction_date' => '2026-08-21',
            'type' => 'Edit Reversal',
            'reference_no' => 'EDIT-4001',
            'credit' => 9000,
        ],
        [
            'id' => 5,
            'invoice_id' => 14,
            'source_sale_id' => 4001,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'EDIT-4001',
            'debit' => 8000,
        ],
        [
            'id' => 6,
            'source_sale_id' => 4001,
            'reversal_of_ledger_id' => 5,
            'transaction_date' => '2026-08-21',
            'type' => 'Void',
            'reference_no' => 'VOID-4001',
            'credit' => 8000,
        ],
        [
            'id' => 7,
            'invoice_id' => 14,
            'source_sale_id' => 4002,
            'transaction_date' => '2026-08-21',
            'type' => 'Fuel Sale',
            'reference_no' => 'TXN-REPROCESSED',
            'debit' => 8000,
        ],
    ]);

    $invoice = (object) [
        'id' => 14,
        'customer_id' => 1,
        'period_start' => '2026-08-21',
        'period_end' => '2026-08-21',
    ];

    $rows = callPrivateBillingSoaMethod(new BillingController(), 'billingRowsForInvoice', [$invoice]);

    expect($rows->pluck('id')->all())->toBe([7])
        ->and((float) $rows->sum('debit'))->toBe(8000.0);
});

function insertBillingSoaLedgerRows(array $rows): void
{
    foreach ($rows as $row) {
        DB::table('customer_ledgers')->insert(array_merge([
            'customer_id' => 1,
            'invoice_id' => null,
            'source_sale_id' => null,
            'reversal_of_ledger_id' => null,
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
