<?php

use App\Models\User;
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
        $table->string('contact_number')->nullable();
        $table->decimal('credit_limit', 12, 2)->default(0);
        $table->decimal('current_balance', 12, 2)->default(0);
        $table->string('payment_terms')->nullable();
        $table->decimal('sp_diesel', 10, 2)->nullable();
        $table->decimal('sp_unleaded', 10, 2)->nullable();
        $table->decimal('sp_premium', 10, 2)->nullable();
        $table->string('status')->default('Active');
        $table->timestamps();
    });

    (require database_path('migrations/2026_11_13_000002_add_name_components_to_customers_table.php'))->up();

    $this->actingAs(User::create([
        'name' => 'Customer Admin',
        'email' => 'customer.admin@example.test',
        'password' => 'password',
        'role' => User::ROLE_VP_ADMIN,
    ]));
    $this->customerData = [
        'business_name' => 'Customer Business',
        'credit_limit' => 5000,
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => 'Jr.',
    ];
});

test('customer creation normalizes name parts and constructs the display name on the server', function () {
    $this->postJson(route('customers.store'), array_replace($this->customerData, [
        'first_name' => '  Juan  ',
        'middle_name' => "\u{00A0}Santos\u{00A0}",
        'last_name' => ' Dela   Cruz ',
        'suffix' => ' Jr. ',
        'owner_name' => 'An untrusted display name',
    ]))->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('customers', [
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => 'Jr.',
        'owner_name' => 'Juan Santos Dela Cruz Jr.',
    ]);
});

test('empty optional parts are excluded without extra spaces', function ($middle, $suffix, $expected) {
    $this->postJson(route('customers.store'), array_replace($this->customerData, [
        'middle_name' => $middle,
        'suffix' => $suffix,
    ]))->assertOk();
    $this->assertDatabaseHas('customers', ['owner_name' => $expected]);
})->with([
    [null, null, 'Juan Dela Cruz'],
    ['', '', 'Juan Dela Cruz'],
    ['   ', '   ', 'Juan Dela Cruz'],
    ['Santos', null, 'Juan Santos Dela Cruz'],
    [null, 'III', 'Juan Dela Cruz III'],
]);

test('personal names support accents combining marks international scripts and name punctuation', function ($first, $middle, $last) {
    $this->postJson(route('customers.store'), array_replace($this->customerData, [
        'first_name' => $first,
        'middle_name' => $middle,
        'last_name' => $last,
        'suffix' => null,
    ]))->assertOk();
    $this->assertDatabaseHas('customers', ['owner_name' => implode(' ', array_filter([$first, $middle, $last]))]);
})->with([
    ['María-José', 'C.', "O'Neill"],
    ['Zoë', null, 'D’Arcy'],
    ["Jose\u{0301}", null, 'Muñoz'],
    ['小明', null, '欧阳'],
    ['Мария', null, 'Иванова'],
    ['محمد', null, "نیم\u{200C}ور"],
    ['Jean–Luc', null, 'Picard'],
]);

test('customer names reject missing short long or invalid values', function ($field, $value) {
    $this->postJson(route('customers.store'), array_replace($this->customerData, [$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('customers', 0);
})->with([
    ['first_name', null], ['last_name', null], ['first_name', '   '],
    ['first_name', 'J'], ['last_name', 'D'], ['first_name', ' A '],
    ['first_name', str_repeat('a', 61)], ['last_name', str_repeat('b', 61)], ['middle_name', str_repeat('c', 61)],
    ['first_name', 'Juan123'], ['last_name', '<script>'], ['middle_name', 'Name@example'],
    ['first_name', '😀😀'], ['first_name', '--'], ['last_name', ['Dela Cruz']],
    ['suffix', 'VI'], ['suffix', 'None'],
]);

test('first and last names accept exactly two through sixty unicode characters and middle initials', function () {
    $this->postJson(route('customers.store'), array_replace($this->customerData, [
        'first_name' => str_repeat('é', 60),
        'middle_name' => 'Q',
        'last_name' => 'Li',
    ]))->assertOk();
    $this->assertDatabaseHas('customers', ['first_name' => str_repeat('é', 60), 'middle_name' => 'Q', 'last_name' => 'Li']);
});

test('a single owner name cannot bypass required name components for a new customer', function () {
    $this->postJson(route('customers.store'), [
        'business_name' => 'Legacy Request',
        'credit_limit' => 1000,
        'owner_name' => 'Juan Dela Cruz',
    ])->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name']);
    $this->assertDatabaseCount('customers', 0);
});

test('editing other legacy customer details preserves the exact original name and does not split it', function ($legacyName) {
    $id = DB::table('customers')->insertGetId(['business_name' => 'Legacy Customer', 'owner_name' => $legacyName]);
    $this->postJson(route('customers.update'), [
        'id' => $id,
        'business_name' => 'Updated Business',
        'credit_limit' => 6000,
        'owner_name' => 'This single field must not overwrite the saved name',
    ])->assertOk();
    $this->assertDatabaseHas('customers', [
        'id' => $id,
        'owner_name' => $legacyName,
        'first_name' => null,
        'middle_name' => null,
        'last_name' => null,
        'suffix' => null,
        'business_name' => 'Updated Business',
    ]);
})->with([['DELA CRUZ,  JUAN / Accounts'], ['PILAR WATER DISTRICT'], [null]]);

test('an admin can explicitly replace a legacy name using validated components', function () {
    $id = DB::table('customers')->insertGetId(['business_name' => 'Legacy Customer', 'owner_name' => 'CRUZ, JUAN']);
    $this->postJson(route('customers.update'), array_replace($this->customerData, ['id' => $id, 'suffix' => 'III']))->assertOk();
    $this->assertDatabaseHas('customers', [
        'id' => $id,
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'suffix' => 'III',
        'owner_name' => 'Juan Santos Dela Cruz III',
    ]);
    $this->get(route('customers.index'))->assertOk()->assertSeeText('Juan Santos Dela Cruz III')
        ->assertSee('First Name')->assertSee('Middle Name')->assertSee('Last Name')->assertSee('Suffix');
});

test('an incomplete name replacement cannot damage a legacy name', function () {
    $id = DB::table('customers')->insertGetId(['business_name' => 'Legacy Customer', 'owner_name' => 'Original, Complete Name']);
    $this->postJson(route('customers.update'), [
        'id' => $id, 'business_name' => 'Customer', 'credit_limit' => 1000, 'first_name' => 'Juan',
    ])->assertUnprocessable()->assertJsonValidationErrors('last_name');
    $this->assertDatabaseHas('customers', ['id' => $id, 'owner_name' => 'Original, Complete Name', 'first_name' => null]);
});

test('editing a structured name regenerates the full name and omitted name fields preserve it', function () {
    $this->postJson(route('customers.store'), $this->customerData)->assertOk();
    $id = DB::table('customers')->value('id');
    $this->postJson(route('customers.update'), array_replace($this->customerData, [
        'id' => $id, 'middle_name' => '', 'suffix' => '', 'last_name' => 'O’Neill',
    ]))->assertOk();
    $this->postJson(route('customers.update'), ['id' => $id, 'business_name' => 'Renamed Business', 'credit_limit' => 5000])->assertOk();
    $this->assertDatabaseHas('customers', ['id' => $id, 'owner_name' => 'Juan O’Neill', 'middle_name' => null, 'last_name' => 'O’Neill', 'suffix' => null]);
});

test('the migration preserves legacy names through upgrade and rollback', function () {
    $migration = require database_path('migrations/2026_11_13_000002_add_name_components_to_customers_table.php');
    $migration->down();
    $id = DB::table('customers')->insertGetId(['business_name' => 'Legacy', 'owner_name' => 'DELA CRUZ,  JUAN / Contact']);
    $migration->up();
    $this->assertDatabaseHas('customers', ['id' => $id, 'owner_name' => 'DELA CRUZ,  JUAN / Contact', 'first_name' => null, 'last_name' => null]);
    $migration->down();
    $this->assertDatabaseHas('customers', ['id' => $id, 'owner_name' => 'DELA CRUZ,  JUAN / Contact']);
});
