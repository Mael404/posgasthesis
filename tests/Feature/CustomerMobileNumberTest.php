<?php

use App\Models\User;
use App\Support\PhilippineMobileNumber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->string('role');
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('business_name');
        $table->string('owner_name')->nullable();
        $table->string('first_name', 60)->nullable();
        $table->string('middle_name', 60)->nullable();
        $table->string('last_name', 60)->nullable();
        $table->string('suffix', 4)->nullable();
        $table->string('contact_number', 50)->nullable();
        $table->decimal('credit_limit', 12, 2)->default(0);
        $table->decimal('current_balance', 12, 2)->default(0);
        $table->string('payment_terms')->nullable();
        $table->decimal('sp_diesel', 10, 2)->nullable();
        $table->decimal('sp_unleaded', 10, 2)->nullable();
        $table->decimal('sp_premium', 10, 2)->nullable();
        $table->string('status')->default('Active');
        $table->timestamps();
    });
    $this->actingAs(User::create([
        'name' => 'Customer Admin',
        'email' => 'mobile.admin@example.test',
        'password' => 'password',
        'role' => User::ROLE_VP_ADMIN,
    ]));
    $this->customerData = [
        'business_name' => 'Customer Business',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'credit_limit' => 5000,
        'contact_number' => '09171234567',
    ];
});

test('valid mobile numbers are trimmed and stored as local-format strings', function ($input, $expected) {
    $this->postJson(route('customers.store'), array_replace($this->customerData, ['contact_number' => $input]))
        ->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseHas('customers', ['contact_number' => $expected]);
    $this->assertSame($expected, DB::table('customers')->value('contact_number'));
})->with([
    ['09214028982', '09214028982'],
    ['09214027871', '09214027871'],
    ['09171234567', '09171234567'],
    ['  09214028982  ', '09214028982'],
    ["\t 09171234567 \r\n", '09171234567'],
    ["\u{00A0}09171234567\u{00A0}", '09171234567'],
    ["\u{FEFF}09171234567\u{FEFF}", '09171234567'],
]);

test('invalid mobile input is rejected with the required clear message on creation and update', function ($input) {
    $this->postJson(route('customers.store'), array_replace($this->customerData, ['contact_number' => $input]))
        ->assertUnprocessable()->assertJsonValidationErrors('contact_number')
        ->assertJsonPath('errors.contact_number.0', PhilippineMobileNumber::VALIDATION_MESSAGE);
    $this->assertDatabaseCount('customers', 0);

    $id = DB::table('customers')->insertGetId(['business_name' => 'Existing Customer', 'contact_number' => '09171234567']);
    $this->postJson(route('customers.update'), array_replace($this->customerData, ['id' => $id, 'contact_number' => $input]))
        ->assertUnprocessable()->assertJsonValidationErrors('contact_number')
        ->assertJsonPath('errors.contact_number.0', PhilippineMobileNumber::VALIDATION_MESSAGE);
    $this->assertDatabaseHas('customers', ['id' => $id, 'contact_number' => '09171234567']);
})->with([
    ['9214028982'], ['0921402898'], ['092140289821'], ['09214ABC982'],
    ['+639214028982'], ['+639171234567'], ['+09214027871'], ['09214027871e0'],
    ['639214028982'], ['+6309214028982'], ['+63921402898'],
    ['+6391712345678'], ['08171234567'], ['++639171234567'],
    ['09 171234567'], ['0917-123-4567'], ['+63 9171234567'], ['0917123456.'],
    [null], [''], ['   '], [9214028982], [639171234567], [['09171234567']],
    ['０９１７１２３４５６７'], ["0917\n1234567"], ["09171234567\0"], ['N/A'],
]);

test('contact number is required when omitted on both creation and update', function () {
    $payload = $this->customerData;
    unset($payload['contact_number']);
    $this->postJson(route('customers.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('contact_number');
    $id = DB::table('customers')->insertGetId(['business_name' => 'Existing Customer', 'contact_number' => '09171234567']);
    $this->postJson(route('customers.update'), array_replace($payload, ['id' => $id]))
        ->assertUnprocessable()->assertJsonValidationErrors('contact_number');
    $this->assertDatabaseHas('customers', ['id' => $id, 'contact_number' => '09171234567']);
});

test('equivalent local and international numbers cannot create duplicate customers', function ($stored, $submitted) {
    $id = DB::table('customers')->insertGetId(['business_name' => 'Existing Customer', 'contact_number' => $stored]);
    $this->postJson(route('customers.store'), array_replace($this->customerData, ['contact_number' => $submitted]))
        ->assertUnprocessable()->assertJsonValidationErrors('contact_number')
        ->assertJsonPath('errors.contact_number.0', PhilippineMobileNumber::DUPLICATE_MESSAGE);
    $this->assertDatabaseCount('customers', 1);
    $this->assertDatabaseHas('customers', ['id' => $id, 'contact_number' => $stored]);
})->with([
    ['09171234567', '09171234567'],
    ['+639171234567', '09171234567'],
    ['  +639171234567  ', ' 09171234567 '],
    ["\t+639171234567\r\n", '09171234567'],
    ["\u{00A0}09171234567\u{00A0}", '09171234567'],
]);

test('inactive customers also reserve their normalized mobile number', function () {
    DB::table('customers')->insert(['business_name' => 'Inactive Customer', 'contact_number' => '+639171234567', 'status' => 'Inactive']);
    $this->postJson(route('customers.store'), $this->customerData)->assertUnprocessable()
        ->assertJsonPath('errors.contact_number.0', PhilippineMobileNumber::DUPLICATE_MESSAGE);
    $this->assertDatabaseCount('customers', 1);
});

test('customers may keep their own equivalent number and it is normalized when saved', function ($stored, $submitted) {
    $id = DB::table('customers')->insertGetId(['business_name' => 'Existing Customer', 'contact_number' => $stored]);
    $this->postJson(route('customers.update'), array_replace($this->customerData, ['id' => $id, 'contact_number' => $submitted]))
        ->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseHas('customers', ['id' => $id, 'contact_number' => '09171234567']);
    $this->assertDatabaseCount('customers', 1);
})->with([
    ['09171234567', '09171234567'],
    ['+639171234567', '09171234567'],
]);

test('updates cannot use another customer number even when its stored format differs', function () {
    $ownerId = DB::table('customers')->insertGetId(['business_name' => 'Number Owner', 'contact_number' => '+639171234567']);
    $editedId = DB::table('customers')->insertGetId(['business_name' => 'Edited Customer', 'contact_number' => '09214028982']);
    $this->postJson(route('customers.update'), array_replace($this->customerData, ['id' => $editedId]))
        ->assertUnprocessable()->assertJsonPath('errors.contact_number.0', PhilippineMobileNumber::DUPLICATE_MESSAGE);
    $this->assertDatabaseHas('customers', ['id' => $editedId, 'contact_number' => '09214028982', 'business_name' => 'Edited Customer']);
    $this->assertDatabaseHas('customers', ['id' => $ownerId, 'contact_number' => '+639171234567']);
});

test('legacy placeholders and shared numbers are not rewritten or removed when viewing or adding a different customer', function () {
    foreach ([null, 'N/A', '09171234567', '+639171234567'] as $number) {
        DB::table('customers')->insert(['business_name' => 'Legacy Customer', 'contact_number' => $number]);
    }
    $before = DB::table('customers')->orderBy('id')->get(['id', 'contact_number'])->toJson();
    $this->get(route('customers.index'))->assertOk()->assertSee('type="tel"', false)
        ->assertSee('customer-contact-number-error')->assertSee('Enter 11 digits starting with 09 (e.g. 09214027871).')
        ->assertSee('inputmode="numeric"', false)->assertSee('maxlength="11"', false)
        ->assertSee('pattern="09[0-9]{9}"', false);
    $this->postJson(route('customers.store'), array_replace($this->customerData, ['contact_number' => '09214028982']))->assertOk();
    $this->assertSame($before, DB::table('customers')->where('id', '<=', 4)->orderBy('id')->get(['id', 'contact_number'])->toJson());
    $this->assertDatabaseCount('customers', 5);
});

test('a legacy placeholder requires correction before saving an edited customer', function () {
    $id = DB::table('customers')->insertGetId(['business_name' => 'Legacy Customer', 'contact_number' => 'N/A']);
    $this->postJson(route('customers.update'), array_replace($this->customerData, ['id' => $id, 'contact_number' => 'N/A']))
        ->assertUnprocessable()->assertJsonValidationErrors('contact_number');
    $this->assertDatabaseHas('customers', ['id' => $id, 'contact_number' => 'N/A']);
    $this->postJson(route('customers.update'), array_replace($this->customerData, ['id' => $id, 'contact_number' => '09171234567']))->assertOk();
    $this->assertDatabaseHas('customers', ['id' => $id, 'contact_number' => '09171234567']);
});
