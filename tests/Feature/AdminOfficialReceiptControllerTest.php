<?php

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('sales_invoice_items');
    Schema::dropIfExists('sales_invoice_number_counters');
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

    (require database_path('migrations/2026_08_06_000001_create_sales_invoices_tables.php'))->up();
    (require database_path('migrations/2026_08_06_000002_add_sales_invoice_number_sequence.php'))->up();

    $this->admin = User::create([
        'name' => 'VP Admin',
        'email' => 'vp.admin@example.test',
        'password' => 'password',
        'role' => User::ROLE_VP_ADMIN,
    ]);

    $this->staff = User::create([
        'name' => 'Pump Staff',
        'email' => 'pump.staff@example.test',
        'password' => 'password',
        'role' => User::ROLE_STAFF,
    ]);
});

test('the receipt register filters by date product name and SI number range', function () {
    createOfficialReceiptForTest([
        'si_number' => '1',
        'invoice_date' => '2026-08-01',
        'sold_to' => 'ALPHA CUSTOMER',
        'total_sales_vat_inclusive' => 112,
        'vatable_sales' => 100,
        'vat_amount' => 12,
        'amount_net_of_vat' => 100,
        'total_amount_due' => 112,
    ], [['item_type' => 'Diesel', 'description' => 'Diesel']]);

    createOfficialReceiptForTest([
        'si_number' => '2',
        'invoice_date' => '2026-08-15',
        'sold_to' => 'BETA CUSTOMER',
        'total_sales_vat_inclusive' => 224,
        'vatable_sales' => 200,
        'vat_amount' => 24,
        'amount_net_of_vat' => 200,
        'total_amount_due' => 224,
    ], [['item_type' => 'Premium', 'description' => 'Premium']]);

    createOfficialReceiptForTest([
        'si_number' => '3',
        'invoice_date' => '2026-09-01',
        'sold_to' => 'GAMMA CUSTOMER',
        'total_sales_vat_inclusive' => 336,
        'vatable_sales' => 300,
        'vat_amount' => 36,
        'amount_net_of_vat' => 300,
        'total_amount_due' => 336,
    ], [['item_type' => 'Others', 'description' => 'Engine Oil']]);

    $response = $this->actingAs($this->admin)->get(route('admin.official-receipts.index', [
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'product' => 'Diesel',
        'name' => 'Alpha',
        'si_from' => 1,
        'si_to' => 1,
    ]));

    $response
        ->assertOk()
        ->assertSee('ALPHA CUSTOMER')
        ->assertDontSee('BETA CUSTOMER')
        ->assertDontSee('GAMMA CUSTOMER')
        ->assertSee('Edit Receipt')
        ->assertSee('x-on:input.debounce.450ms', false);

    $totals = $response->viewData('totals');

    expect((int) $totals->active_receipts)->toBe(1)
        ->and((float) $totals->total_amount_due)->toBe(112.0);
});

test('canceled receipts remain listed but are excluded from register totals', function () {
    $active = createOfficialReceiptForTest([
        'si_number' => '1',
        'sold_to' => 'ACTIVE CUSTOMER',
        'total_sales_vat_inclusive' => 112,
        'vatable_sales' => 100,
        'vat_amount' => 12,
        'amount_net_of_vat' => 100,
        'total_amount_due' => 112,
    ]);

    $canceled = createOfficialReceiptForTest([
        'si_number' => '2',
        'sold_to' => 'CANCELED CUSTOMER',
        'total_sales_vat_inclusive' => 224,
        'vatable_sales' => 200,
        'vat_amount' => 24,
        'amount_net_of_vat' => 200,
        'total_amount_due' => 224,
    ]);
    $canceled->markCanceled($this->admin->id, 'Duplicate receipt');

    DB::table('sales_invoice_number_counters')->where('id', 1)->update(['last_number' => 2]);

    $response = $this->actingAs($this->admin)->get(route('admin.official-receipts.index'));

    $response
        ->assertOk()
        ->assertSee($active->sold_to)
        ->assertSee($canceled->sold_to)
        ->assertSee('Duplicate receipt');

    $totals = $response->viewData('totals');

    expect((int) $totals->active_receipts)->toBe(1)
        ->and((float) $totals->total_sales_vat_inclusive)->toBe(112.0)
        ->and((float) $totals->total_amount_due)->toBe(112.0)
        ->and(SalesInvoice::count())->toBe(2);
});

test('canceling a receipt retains its SI number and the next save advances the sequence', function () {
    $receipt = createOfficialReceiptForTest([
        'si_number' => '1',
        'sold_to' => 'RECEIPT TO CANCEL',
    ]);

    DB::table('sales_invoice_number_counters')->where('id', 1)->update(['last_number' => 1]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.official-receipts.cancel', $receipt), ['reason' => 'Incorrect customer'])
        ->assertOk()
        ->assertJsonPath('receipt.si_number', '1')
        ->assertJsonPath('receipt.status', 'Canceled');

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $receipt->id,
        'si_number' => '1',
        'status' => 'Canceled',
        'cancellation_reason' => 'Incorrect customer',
    ]);

    $nextReceipt = $this->actingAs($this->staff)
        ->postJson(route('staff.sales-invoices.store'), [])
        ->assertCreated();

    $nextReceipt->assertJsonPath('invoice.si_number', '2');
    expect(SalesInvoice::count())->toBe(2);
});

test('an admin can edit the actual receipt form while preserving its SI number and status', function () {
    $receipt = createOfficialReceiptForTest([
        'si_number' => '1',
        'sold_to' => 'ORIGINAL CUSTOMER',
        'total_sales_vat_inclusive' => 112,
        'vatable_sales' => 100,
        'vat_amount' => 12,
        'amount_net_of_vat' => 100,
        'total_amount_due' => 112,
    ], [['item_type' => 'Diesel', 'description' => 'Diesel', 'amount' => 112]]);

    $this->actingAs($this->admin)
        ->putJson(route('admin.official-receipts.update', $receipt), [
            'si_number' => '999',
            'invoice_date' => '2026-08-07',
            'sold_to' => 'Edited Customer',
            'tin' => '123-456-789',
            'terms' => '30 Days',
            'address' => 'Edited Address',
            'discount_id_no' => 'PWD-10',
            'signature' => 'Edited Signature',
            'authorized_representative' => 'Admin Representative',
            'vat_exempt_sales' => 10,
            'zero_rated_sales' => 5,
            'sc_pwd_discount' => 2,
            'withholding_tax' => 1,
            'items' => [[
                'item_type' => 'Others',
                'other_description' => 'Edited Service',
                'quantity' => 2,
                'price' => 100,
                'amount' => 224,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('receipt.si_number', '1')
        ->assertJsonPath('receipt.status', 'Active')
        ->assertJsonPath('receipt.total_amount_due', 224);

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $receipt->id,
        'si_number' => '1',
        'status' => 'Active',
        'sold_to' => 'EDITED CUSTOMER',
        'total_sales_vat_inclusive' => 224,
        'vatable_sales' => 200,
        'vat_amount' => 24,
        'amount_net_of_vat' => 200,
        'total_amount_due' => 224,
    ]);

    $this->assertDatabaseMissing('sales_invoice_items', [
        'sales_invoice_id' => $receipt->id,
        'description' => 'Diesel',
    ]);
    $this->assertDatabaseHas('sales_invoice_items', [
        'sales_invoice_id' => $receipt->id,
        'item_type' => 'Others',
        'description' => 'Edited Service',
        'amount' => 224,
    ]);
});

function createOfficialReceiptForTest(array $overrides = [], array $items = []): SalesInvoice
{
    $receipt = SalesInvoice::create(array_merge([
        'staff_id' => null,
        'invoice_date' => '2026-08-06',
        'sold_to' => 'TEST CUSTOMER',
        'tin' => 'No TIN Inputted',
        'terms' => 'Cash',
        'address' => 'PILAR, SORSOGON',
        'discount_id_no' => 'No ID Number Inputted',
        'signature' => 'No Signature Inputted',
        'si_number' => '1',
        'authorized_representative' => 'AUTHORIZED REPRESENTATIVE',
        'status' => SalesInvoice::STATUS_ACTIVE,
        'vatable_sales' => 0,
        'vat_exempt_sales' => 0,
        'zero_rated_sales' => 0,
        'vat_amount' => 0,
        'total_sales_vat_inclusive' => 0,
        'less_vat' => 0,
        'amount_net_of_vat' => 0,
        'sc_pwd_discount' => 0,
        'add_vat' => 0,
        'withholding_tax' => 0,
        'total_amount_due' => 0,
    ], $overrides));

    foreach ($items as $index => $item) {
        $receipt->items()->create(array_merge([
            'sort_order' => $index + 1,
            'item_type' => 'Others',
            'description' => 'Test Item',
            'quantity' => 1,
            'price' => 100,
            'amount' => 100,
        ], $item));
    }

    return $receipt;
}
