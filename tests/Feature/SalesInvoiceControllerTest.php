<?php

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('sales_invoice_items');
    Schema::dropIfExists('sales_invoices');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('role');
        $table->rememberToken();
        $table->timestamps();
    });

    $migration = require database_path('migrations/2026_08_06_000001_create_sales_invoices_tables.php');
    $migration->up();

    $sequenceMigration = require database_path('migrations/2026_08_06_000002_add_sales_invoice_number_sequence.php');
    $sequenceMigration->up();

    $this->staff = User::create([
        'name' => 'Pump Staff',
        'email' => 'pump.staff@example.test',
        'password' => 'password',
        'role' => User::ROLE_STAFF,
    ]);
});

test('a staff user can store a sales invoice with workbook calculations', function () {
    $response = $this->actingAs($this->staff)->postJson(route('staff.sales-invoices.store'), [
        'invoice_date' => '2026-08-06',
        'sold_to' => 'Alfie Andres Miranbueno',
        'tin' => '123-456-789',
        'terms' => 'Cash',
        'address' => 'Pilar, Sorsogon',
        'discount_id_no' => 'PWD-100',
        'signature' => 'Alfie A. Miranbueno',
        'si_number' => '9999',
        'authorized_representative' => 'Juan Dela Cruz',
        'vat_exempt_sales' => 20,
        'zero_rated_sales' => 10,
        'sc_pwd_discount' => 5,
        'withholding_tax' => 3,
        'items' => [
            [
                'item_type' => 'Diesel',
                'quantity' => 10,
                'price' => 60,
                'amount' => 112,
            ],
            [
                'item_type' => 'Others',
                'other_description' => 'Delivery service',
                'quantity' => 2,
                'price' => 150,
                'amount' => 224,
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('invoice.total_amount_due', 333);

    $invoiceId = $response->json('invoice.id');

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $invoiceId,
        'staff_id' => $this->staff->id,
        'sold_to' => 'ALFIE ANDRES MIRANBUENO',
        'address' => 'PILAR, SORSOGON',
        'authorized_representative' => 'Pump Staff',
        'si_number' => '1',
        'status' => 'Active',
        'total_sales_vat_inclusive' => 336,
        'less_vat' => 36,
        'amount_net_of_vat' => 300,
        'vatable_sales' => 300,
        'vat_amount' => 36,
        'add_vat' => 36,
        'total_amount_due' => 333,
        'vat_exempt_sales' => 20,
        'zero_rated_sales' => 10,
        'sc_pwd_discount' => 5,
        'withholding_tax' => 3,
    ]);

    $this->assertDatabaseHas('sales_invoice_items', [
        'sales_invoice_id' => $invoiceId,
        'sort_order' => 1,
        'item_type' => 'Diesel',
        'description' => 'Diesel',
        'quantity' => 10,
        'price' => 60,
        'amount' => 112,
    ]);

    $this->assertDatabaseHas('sales_invoice_items', [
        'sales_invoice_id' => $invoiceId,
        'sort_order' => 2,
        'item_type' => 'Others',
        'description' => 'Delivery service',
        'amount' => 224,
    ]);
});

test('blank optional sales invoice fields receive readable defaults', function () {
    $response = $this->actingAs($this->staff)->postJson(route('staff.sales-invoices.store'), []);

    $response->assertCreated()->assertJsonPath('invoice.total_amount_due', 0);

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $response->json('invoice.id'),
        'sold_to' => 'NO NAME INPUTTED',
        'tin' => 'No TIN Inputted',
        'terms' => 'No Terms Inputted',
        'address' => 'NO ADDRESS INPUTTED',
        'discount_id_no' => 'No ID Number Inputted',
        'signature' => 'No Signature Inputted',
        'si_number' => '1',
        'authorized_representative' => 'Pump Staff',
        'total_amount_due' => 0,
    ]);

    expect(DB::table('sales_invoice_items')->count())->toBe(0);
});

test('withholding tax reduces the saved total amount due', function () {
    $response = $this->actingAs($this->staff)->postJson(route('staff.sales-invoices.store'), [
        'withholding_tax' => 20,
        'items' => [[
            'item_type' => 'Diesel',
            'amount' => 1000,
        ]],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('invoice.total_amount_due', 980);

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $response->json('invoice.id'),
        'total_sales_vat_inclusive' => 1000,
        'withholding_tax' => 20,
        'total_amount_due' => 980,
    ]);
});

test('the withholding tax migration corrects totals on existing sales invoices', function () {
    $response = $this->actingAs($this->staff)->postJson(route('staff.sales-invoices.store'), [
        'withholding_tax' => 20,
        'items' => [[
            'item_type' => 'Diesel',
            'amount' => 1000,
        ]],
    ])->assertCreated();

    DB::table('sales_invoices')
        ->where('id', $response->json('invoice.id'))
        ->update(['total_amount_due' => 1000]);

    (require database_path('migrations/2026_08_08_000001_apply_withholding_tax_to_sales_invoice_totals.php'))->up();

    expect((float) SalesInvoice::findOrFail($response->json('invoice.id'))->total_amount_due)->toBe(980.0);
});

test('SI numbers increment on every committed invoice and cannot be supplied by the client', function () {
    $first = $this->actingAs($this->staff)->postJson(route('staff.sales-invoices.store'), [
        'si_number' => '500',
        'sold_to' => 'First Customer',
    ]);

    SalesInvoice::findOrFail($first->json('invoice.id'))->markCanceled($this->staff->id, 'Test cancellation');

    $second = $this->actingAs($this->staff)->postJson(route('staff.sales-invoices.store'), [
        'si_number' => '1',
        'sold_to' => 'Second Customer',
    ]);

    $first->assertCreated()->assertJsonPath('invoice.si_number', '1');
    $second->assertCreated()->assertJsonPath('invoice.si_number', '2');

    $this->assertDatabaseHas('sales_invoices', [
        'sold_to' => 'FIRST CUSTOMER',
        'si_number' => '1',
        'status' => 'Canceled',
        'canceled_by' => $this->staff->id,
        'cancellation_reason' => 'Test cancellation',
    ]);
    $this->assertDatabaseHas('sales_invoices', ['sold_to' => 'SECOND CUSTOMER', 'si_number' => '2']);
    $this->assertDatabaseHas('sales_invoice_number_counters', ['id' => 1, 'last_number' => 2]);
});

test('an assigned SI number is immutable', function () {
    $response = $this->actingAs($this->staff)->postJson(route('staff.sales-invoices.store'), [
        'sold_to' => 'Immutable Number Customer',
    ]);

    $invoice = SalesInvoice::findOrFail($response->json('invoice.id'));

    expect(fn () => $invoice->update(['si_number' => '50']))
        ->toThrow(LogicException::class, 'A sales invoice SI number cannot be changed.');

    expect($invoice->fresh()->si_number)->toBe('1');
});

test('sales invoice item types and nonnegative amounts are validated', function () {
    $this->actingAs($this->staff)
        ->postJson(route('staff.sales-invoices.store'), [
            'items' => [[
                'item_type' => 'Kerosene',
                'amount' => -1,
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.item_type', 'items.0.amount']);

    expect(DB::table('sales_invoices')->count())->toBe(0);
});

test('the staff sales invoice asks for confirmation before saving', function () {
    $this->actingAs($this->staff)
        ->get(route('staff.pump.index'))
        ->assertOk()
        ->assertSee('Are you sure you want to submit this sales invoice?', false)
        ->assertSee('authorizedRepresentative:', false)
        ->assertSee('Authorized representative from the logged-in user', false)
        ->assertDontSee('x-model="salesInvoice.authorized_representative"', false)
        ->assertSee('amountBeforeWithholdingTax - withholdingTax', false)
        ->assertSee("cancelText: 'No'", false);
});
