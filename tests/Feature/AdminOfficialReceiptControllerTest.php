<?php

use App\Helpers\MenuHelper;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('sales_invoice_items');
    Schema::dropIfExists('sales_invoice_number_counters');
    Schema::dropIfExists('sales_invoices');
    Schema::dropIfExists('tanks');
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

    Schema::create('tanks', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('fuel_type');
        $table->string('status')->nullable();
    });

    DB::table('tanks')->insert([
        ['id' => 1, 'name' => 'Tank 1 (Premium)', 'fuel_type' => 'Premium', 'status' => 'Active'],
        ['id' => 2, 'name' => 'Tank 2 (Unleaded)', 'fuel_type' => 'Unleaded', 'status' => 'Active'],
        ['id' => 3, 'name' => 'Tank 3 (Diesel)', 'fuel_type' => 'Diesel', 'status' => 'Active'],
    ]);

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

    $this->semiAdmin = User::create([
        'name' => 'Semi Admin',
        'email' => 'semi.admin@example.test',
        'password' => 'password',
        'role' => User::ROLE_SEMI_ADMIN,
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
        'withholding_tax' => 7.25,
        'total_amount_due' => 104.75,
    ], [['item_type' => 'Diesel', 'description' => 'Diesel', 'quantity' => 2, 'amount' => 112]]);

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
        ->assertSee('Official Receipt Register')
        ->assertDontSee('VAT receipt records patterned after the workbook register. Canceled receipts remain visible.')
        ->assertDontSee('Financial totals always exclude canceled receipts.')
        ->assertDontSee('The total follows the latest issued SI sequence, including canceled receipts.')
        ->assertSee('Active Receipts')
        ->assertSee('<select name="product"', false)
        ->assertSee('Unleaded/Regular')
        ->assertSee('All Tanks / Products')
        ->assertSee('Diesel / Tank 3')
        ->assertSee('!border-warning-300 !bg-warning-50 !text-warning-800', false)
        ->assertSee('value="Premium" class="bg-error-50 text-error-700', false)
        ->assertSee('value="Unleaded" class="bg-success-50 text-success-700', false)
        ->assertSee('value="Diesel" class="bg-warning-50 text-warning-800', false)
        ->assertSee('Total Liters')
        ->assertDontSee('Changing any field refreshes the register automatically.')
        ->assertSee('Withholding Tax')
        ->assertDontSee('Net of VAT')
        ->assertSee('&#8369;7.25', false)
        ->assertSee('Total Withholding Tax')
        ->assertSee('Edit Receipt')
        ->assertSee('type="text" name="start_date" value="2026-08-01"', false)
        ->assertSee('type="text" name="end_date" value="2026-08-31"', false)
        ->assertSee('data-official-receipt-date', false)
        ->assertSee("dateFormat: 'Y-m-d'", false)
        ->assertSee('input.form?.requestSubmit()', false)
        ->assertSee('x-on:input.debounce.450ms', false);

    $totals = $response->viewData('totals');

    expect((int) $totals->active_receipts)->toBe(1)
        ->and((float) $totals->total_liters)->toBe(2.0)
        ->and((float) $totals->total_sales_vat_inclusive)->toBe(112.0)
        ->and((float) $totals->vatable_sales)->toBe(100.0)
        ->and((float) $totals->vat_amount)->toBe(12.0)
        ->and((float) $totals->withholding_tax)->toBe(7.25)
        ->and((float) $totals->total_amount_due)->toBe(104.75);
});

test('tank product filtering scopes receipt counts liters and financial totals to matching active items', function () {
    createOfficialReceiptForTest([
        'si_number' => '1',
        'sold_to' => 'PREMIUM CUSTOMER ONE',
        'total_sales_vat_inclusive' => 240,
        'vatable_sales' => 214.29,
        'vat_amount' => 25.71,
        'amount_net_of_vat' => 214.29,
        'total_amount_due' => 240,
    ], [['item_type' => 'Premium', 'description' => 'Premium', 'quantity' => 4, 'amount' => 240]]);

    createOfficialReceiptForTest([
        'si_number' => '2',
        'sold_to' => 'PREMIUM CUSTOMER TWO',
        'total_sales_vat_inclusive' => 360,
        'vatable_sales' => 321.43,
        'vat_amount' => 38.57,
        'amount_net_of_vat' => 321.43,
        'withholding_tax' => 12,
        'total_amount_due' => 348,
    ], [['item_type' => 'Premium', 'description' => 'Premium', 'quantity' => 6, 'amount' => 360]]);

    createOfficialReceiptForTest([
        'si_number' => '3',
        'sold_to' => 'DIESEL CUSTOMER',
        'total_sales_vat_inclusive' => 500,
        'vatable_sales' => 446.43,
        'vat_amount' => 53.57,
        'amount_net_of_vat' => 446.43,
        'total_amount_due' => 500,
    ], [['item_type' => 'Diesel', 'description' => 'Diesel', 'quantity' => 8, 'amount' => 500]]);

    $canceledPremium = createOfficialReceiptForTest([
        'si_number' => '4',
        'sold_to' => 'CANCELED PREMIUM CUSTOMER',
        'total_sales_vat_inclusive' => 100,
        'vatable_sales' => 89.29,
        'vat_amount' => 10.71,
        'amount_net_of_vat' => 89.29,
        'total_amount_due' => 100,
    ], [['item_type' => 'Premium', 'description' => 'Premium', 'quantity' => 2, 'amount' => 100]]);
    $canceledPremium->markCanceled($this->admin->id, 'Canceled premium receipt');

    $response = $this->actingAs($this->admin)->get(route('admin.official-receipts.index', [
        'product' => 'Premium',
    ]));

    $response
        ->assertOk()
        ->assertSee('Premium / Tank 1')
        ->assertSee('PREMIUM CUSTOMER ONE')
        ->assertSee('PREMIUM CUSTOMER TWO')
        ->assertSee('CANCELED PREMIUM CUSTOMER')
        ->assertDontSee('DIESEL CUSTOMER');

    $receiptSummary = $response->viewData('receiptSummary');
    $totals = $response->viewData('totals');

    expect((int) $receiptSummary->total_receipts)->toBe(4)
        ->and((int) $receiptSummary->active_receipts)->toBe(2)
        ->and((int) $receiptSummary->canceled_receipts)->toBe(1)
        ->and((int) $totals->active_receipts)->toBe(2)
        ->and((float) $totals->total_liters)->toBe(10.0)
        ->and((float) $totals->total_sales_vat_inclusive)->toBe(600.0)
        ->and((float) $totals->vatable_sales)->toBe(535.71)
        ->and((float) $totals->vat_amount)->toBe(64.29)
        ->and((float) $totals->withholding_tax)->toBe(12.0)
        ->and((float) $totals->total_amount_due)->toBe(588.0);
});

test('legacy unleaded receipt items display the current product name', function () {
    createOfficialReceiptForTest([
        'si_number' => '1',
        'sold_to' => 'LEGACY UNLEADED CUSTOMER',
    ], [['item_type' => 'Unleaded', 'description' => 'Unleaded']]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.official-receipts.index'))
        ->assertOk();

    expect(substr_count($response->getContent(), 'Unleaded/Regular'))->toBe(3);
});

test('canceled receipts remain listed but are excluded from register totals', function () {
    $active = createOfficialReceiptForTest([
        'si_number' => '1',
        'sold_to' => 'ACTIVE CUSTOMER',
        'total_sales_vat_inclusive' => 112,
        'vatable_sales' => 100,
        'vat_amount' => 12,
        'amount_net_of_vat' => 100,
        'withholding_tax' => 5,
        'total_amount_due' => 107,
    ]);

    $canceled = createOfficialReceiptForTest([
        'si_number' => '2',
        'sold_to' => 'CANCELED CUSTOMER',
        'total_sales_vat_inclusive' => 224,
        'vatable_sales' => 200,
        'vat_amount' => 24,
        'amount_net_of_vat' => 200,
        'withholding_tax' => 20,
        'total_amount_due' => 204,
    ]);
    $canceled->markCanceled($this->admin->id, 'Duplicate receipt');

    $latestActive = createOfficialReceiptForTest([
        'si_number' => '3',
        'sold_to' => 'LATEST ACTIVE CUSTOMER',
        'total_sales_vat_inclusive' => 336,
        'vatable_sales' => 300,
        'vat_amount' => 36,
        'amount_net_of_vat' => 300,
        'withholding_tax' => 7,
        'total_amount_due' => 329,
    ]);

    DB::table('sales_invoice_number_counters')->where('id', 1)->update(['last_number' => 3]);

    $response = $this->actingAs($this->admin)->get(route('admin.official-receipts.index'));

    $response
        ->assertOk()
        ->assertSee($active->sold_to)
        ->assertSee($canceled->sold_to)
        ->assertSee($latestActive->sold_to)
        ->assertSee('Duplicate receipt')
        ->assertSee('Receipt Summary')
        ->assertSee('Total Receipts')
        ->assertSee('Active Receipts')
        ->assertSee('Canceled Receipts');

    $totals = $response->viewData('totals');
    $receiptSummary = $response->viewData('receiptSummary');

    expect((int) $receiptSummary->total_receipts)->toBe(3)
        ->and((int) $receiptSummary->active_receipts)->toBe(2)
        ->and((int) $receiptSummary->canceled_receipts)->toBe(1)
        ->and((int) $totals->active_receipts)->toBe(2)
        ->and((float) $totals->withholding_tax)->toBe(12.0)
        ->and((float) $totals->total_sales_vat_inclusive)->toBe(448.0)
        ->and((float) $totals->total_amount_due)->toBe(436.0)
        ->and(SalesInvoice::count())->toBe(3);
});

test('SI numbers display with at least three digits without changing stored values', function () {
    $one = createOfficialReceiptForTest(['si_number' => '1', 'sold_to' => 'ONE DIGIT']);
    $ten = createOfficialReceiptForTest(['si_number' => '10', 'sold_to' => 'TWO DIGITS']);
    $hundred = createOfficialReceiptForTest(['si_number' => '100', 'sold_to' => 'THREE DIGITS']);
    $thousand = createOfficialReceiptForTest(['si_number' => '1000', 'sold_to' => 'FOUR DIGITS']);

    $response = $this->actingAs($this->admin)->get(route('admin.official-receipts.index'));

    $response
        ->assertOk()
        ->assertSee('001')
        ->assertSee('010')
        ->assertSee('100')
        ->assertSee('1000')
        ->assertDontSee('00100')
        ->assertDontSee('01000')
        ->assertSee('x-text="formatSiNumber(salesInvoice.si_number)"', false)
        ->assertSee('x-text="formatSiNumber(cancellation.siNumber)"', false);

    expect($one->formattedSiNumber())->toBe('001')
        ->and($ten->formattedSiNumber())->toBe('010')
        ->and($hundred->formattedSiNumber())->toBe('100')
        ->and($thousand->formattedSiNumber())->toBe('1000')
        ->and($one->fresh()->si_number)->toBe('1')
        ->and($ten->fresh()->si_number)->toBe('10')
        ->and($hundred->fresh()->si_number)->toBe('100')
        ->and($thousand->fresh()->si_number)->toBe('1000');
});

test('canceling a receipt retains its SI number and the next save advances past the latest issued sequence', function () {
    createOfficialReceiptForTest(['si_number' => '1', 'sold_to' => 'FIRST ACTIVE RECEIPT']);
    $receipt = createOfficialReceiptForTest(['si_number' => '2', 'sold_to' => 'RECEIPT TO CANCEL']);
    createOfficialReceiptForTest(['si_number' => '3', 'sold_to' => 'LATEST ACTIVE RECEIPT']);

    // Simulate an out-of-sync counter: receipt history must remain authoritative.
    DB::table('sales_invoice_number_counters')->where('id', 1)->update(['last_number' => 1]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.official-receipts.cancel', $receipt), ['reason' => 'Incorrect customer'])
        ->assertOk()
        ->assertJsonPath('message', 'Official receipt SI No. 002 canceled. Its number and record were retained.')
        ->assertJsonPath('receipt.si_number', '2')
        ->assertJsonPath('receipt.status', 'Canceled');

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $receipt->id,
        'si_number' => '2',
        'status' => 'Canceled',
        'cancellation_reason' => 'Incorrect customer',
    ]);

    $nextReceipt = $this->actingAs($this->staff)
        ->postJson(route('staff.sales-invoices.store'), [
            'items' => [['item_type' => 'Diesel']],
        ])
        ->assertCreated();

    $nextReceipt->assertJsonPath('invoice.si_number', '4');
    expect(SalesInvoice::count())->toBe(4);
    $this->assertDatabaseHas('sales_invoice_number_counters', ['id' => 1, 'last_number' => 4]);
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
        ->assertJsonPath('receipt.total_amount_due', 223);

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $receipt->id,
        'si_number' => '1',
        'status' => 'Active',
        'sold_to' => 'EDITED CUSTOMER',
        'total_sales_vat_inclusive' => 224,
        'vatable_sales' => 200,
        'vat_amount' => 24,
        'amount_net_of_vat' => 200,
        'authorized_representative' => 'VP Admin',
        'withholding_tax' => 1,
        'total_amount_due' => 223,
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

test('staff can access the receipt register from their navigation', function () {
    $receipt = createOfficialReceiptForTest([
        'sold_to' => 'STAFF ACCESS CUSTOMER',
    ]);

    $response = $this->actingAs($this->staff)
        ->get(route('admin.official-receipts.index'));

    $response
        ->assertOk()
        ->assertSee($receipt->sold_to)
        ->assertSee('001')
        ->assertSee('Automatic Filters')
        ->assertSee('VAT Receipt Data')
        ->assertSee('Edit Receipt')
        ->assertSee('Cancel Receipt')
        ->assertSee('name="start_date" value="" placeholder="Select date"', false)
        ->assertSee('name="end_date" value="" placeholder="Select date"', false)
        ->assertSee('data-official-receipt-date', false)
        ->assertSee('authorizedRepresentative:', false)
        ->assertSee('Authorized representative from the logged-in user', false)
        ->assertDontSee('x-model="salesInvoice.authorized_representative"', false)
        ->assertSee('amountBeforeWithholdingTax - withholdingTax', false)
        ->assertDontSee('Official Receipt Register')
        ->assertDontSee('VAT receipt records patterned after the workbook register.')
        ->assertDontSee('Financial totals always exclude canceled receipts.')
        ->assertDontSee('Active Receipts');

    $menuItem = collect(MenuHelper::getMainNavItems())->firstWhere('name', 'Official Receipts');

    expect($menuItem)->not->toBeNull()
        ->and($menuItem['path'])->toBe('/admin/official-receipts');
});

test('staff can edit and cancel official receipts', function () {
    $receipt = createOfficialReceiptForTest([
        'sold_to' => 'ORIGINAL STAFF CUSTOMER',
    ], [['item_type' => 'Diesel', 'description' => 'Diesel', 'amount' => 112]]);

    $this->actingAs($this->staff)
        ->putJson(route('admin.official-receipts.update', $receipt), [
            'invoice_date' => '2026-08-07',
            'sold_to' => 'Edited By Staff',
            'items' => [[
                'item_type' => 'Premium',
                'quantity' => 1,
                'price' => 224,
                'amount' => 224,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('receipt.si_number', '1')
        ->assertJsonPath('receipt.status', SalesInvoice::STATUS_ACTIVE)
        ->assertJsonPath('receipt.total_amount_due', 224);

    $this->actingAs($this->staff)
        ->postJson(route('admin.official-receipts.cancel', $receipt), [])
        ->assertOk()
        ->assertJsonPath('receipt.status', SalesInvoice::STATUS_CANCELED);

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $receipt->id,
        'si_number' => '1',
        'sold_to' => 'EDITED BY STAFF',
        'authorized_representative' => 'Pump Staff',
        'status' => SalesInvoice::STATUS_CANCELED,
        'canceled_by' => $this->staff->id,
        'cancellation_reason' => 'Canceled by authorized user.',
    ]);
});

test('receipt validation still applies to staff users', function () {
    $receipt = createOfficialReceiptForTest([
        'sold_to' => 'UNCHANGED CUSTOMER',
    ]);

    $this->actingAs($this->staff)
        ->putJson(route('admin.official-receipts.update', $receipt), [
            'items' => array_fill(0, 8, ['item_type' => 'Diesel']),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    $this->actingAs($this->staff)
        ->postJson(route('admin.official-receipts.cancel', $receipt), [
            'reason' => str_repeat('x', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $this->assertDatabaseHas('sales_invoices', [
        'id' => $receipt->id,
        'sold_to' => 'UNCHANGED CUSTOMER',
        'status' => SalesInvoice::STATUS_ACTIVE,
        'canceled_at' => null,
    ]);
});

test('shared receipt access does not grant staff other admin permissions', function () {
    $this->actingAs($this->staff)
        ->get(route('admin.announcements.index'))
        ->assertRedirect(route('staff.pump.index'));

    $this->actingAs($this->semiAdmin)
        ->get(route('admin.official-receipts.index'))
        ->assertRedirect(route('dashboard.semi-admin'));
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
