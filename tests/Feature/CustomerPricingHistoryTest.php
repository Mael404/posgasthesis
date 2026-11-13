<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
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

    (require database_path('migrations/2026_11_13_000001_create_customer_price_histories_table.php'))->up();
    (require database_path('migrations/2026_11_13_000002_add_name_components_to_customers_table.php'))->up();

    $this->admin = User::create([
        'name' => 'Pricing Admin',
        'email' => 'pricing.admin@example.test',
        'password' => 'password',
        'role' => User::ROLE_VP_ADMIN,
    ]);

    $this->customerData = [
        'business_name' => 'Wholesale Customer',
        'contact_number' => '09171234567',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'credit_limit' => 5000,
        'sp_diesel' => '75.00',
        'sp_unleaded' => null,
        'sp_premium' => '72.80',
    ];
});

test('creating a customer records each initial special price with its admin and timestamp', function () {
    $this->freezeTime();

    $this->actingAs($this->admin)->postJson(route('customers.store'), $this->customerData)
        ->assertOk()->assertJsonPath('success', true);

    $customerId = DB::table('customers')->value('id');
    $this->assertDatabaseCount('customer_price_histories', 2);
    $this->assertDatabaseHas('customer_price_histories', [
        'customer_id' => $customerId,
        'fuel_type' => 'Diesel',
        'previous_price' => null,
        'new_price' => 75,
        'changed_by' => $this->admin->id,
        'changed_by_name' => 'Pricing Admin',
        'created_at' => now()->toDateTimeString(),
    ]);
    $this->assertDatabaseHas('customer_price_histories', [
        'customer_id' => $customerId,
        'fuel_type' => 'Premium',
        'new_price' => 72.80,
    ]);
});

test('updating prices logs additions changes and clearing a price without losing previous history', function () {
    $this->actingAs($this->admin)->postJson(route('customers.store'), $this->customerData)->assertOk();
    $customerId = DB::table('customers')->value('id');

    $this->postJson(route('customers.update'), array_replace($this->customerData, [
        'id' => $customerId,
        'sp_diesel' => '76.50',
        'sp_unleaded' => '71.50',
        'sp_premium' => null,
    ]))->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseCount('customer_price_histories', 5);
    foreach ([['Diesel', 75, 76.50], ['Unleaded', null, 71.50], ['Premium', 72.80, null]] as [$fuel, $previous, $new]) {
        $this->assertDatabaseHas('customer_price_histories', [
            'customer_id' => $customerId,
            'fuel_type' => $fuel,
            'previous_price' => $previous,
            'new_price' => $new,
        ]);
    }
    $this->assertDatabaseHas('customers', ['id' => $customerId, 'sp_diesel' => 76.50, 'sp_unleaded' => 71.50, 'sp_premium' => null]);
});

test('saving unchanged prices or editing profile details does not duplicate price history', function () {
    $this->actingAs($this->admin)->postJson(route('customers.store'), $this->customerData)->assertOk();

    $this->postJson(route('customers.update'), array_replace($this->customerData, [
        'id' => DB::table('customers')->value('id'),
        'business_name' => 'Renamed Customer',
        'sp_diesel' => 75,
        'sp_premium' => '72.800',
    ]))->assertOk();

    $this->assertDatabaseCount('customer_price_histories', 2);
    $this->assertDatabaseHas('customers', ['business_name' => 'Renamed Customer']);
});

test('standard prices alone do not create special price history', function () {
    $this->actingAs($this->admin)->postJson(route('customers.store'), array_replace($this->customerData, [
        'sp_diesel' => null,
        'sp_premium' => null,
    ]))->assertOk();

    $this->assertDatabaseCount('customers', 1);
    $this->assertDatabaseCount('customer_price_histories', 0);
});

test('history returns only the selected customer newest first and preserves the admin name', function () {
    $this->freezeTime();
    $this->actingAs($this->admin)->postJson(route('customers.store'), $this->customerData)->assertOk();
    $customerId = DB::table('customers')->value('id');
    $this->postJson(route('customers.store'), array_replace($this->customerData, ['business_name' => 'Other Customer', 'contact_number' => '09214028982']))->assertOk();

    $this->travel(1)->minute();
    $this->postJson(route('customers.update'), array_replace($this->customerData, [
        'id' => $customerId,
        'sp_diesel' => '76.50',
    ]))->assertOk();
    $this->admin->update(['name' => 'Renamed Admin']);

    $this->getJson(route('customers.pricingHistory', $customerId))
        ->assertOk()->assertJsonCount(3)
        ->assertJsonPath('0.fuel_type', 'Diesel')
        ->assertJsonPath('0.previous_price', 75)
        ->assertJsonPath('0.new_price', 76.5)
        ->assertJsonPath('0.changed_by_name', 'Pricing Admin')
        ->assertJsonPath('0.date_display', now()->format('M d, Y - h:i:s A'))
        ->assertJsonPath('1.fuel_type', 'Premium')
        ->assertJsonPath('2.fuel_type', 'Diesel');
});

test('the customer details view contains the wholesale pricing history table', function () {
    $this->actingAs($this->admin)->postJson(route('customers.store'), $this->customerData)->assertOk();

    $this->get(route('customers.index'))->assertOk()
        ->assertSee('Pricing History')->assertSee('Previous Price')->assertSee('Changed By');
});

test('a failed history write rolls back both new customers and price updates', function () {
    $this->actingAs($this->admin)->postJson(route('customers.store'), $this->customerData)->assertOk();
    $customerId = DB::table('customers')->value('id');
    Schema::drop('customer_price_histories');

    $this->postJson(route('customers.update'), array_replace($this->customerData, [
        'id' => $customerId,
        'sp_diesel' => 99,
    ]))->assertStatus(422)->assertJsonPath('success', false);
    $this->assertDatabaseHas('customers', ['id' => $customerId, 'sp_diesel' => 75]);

    $this->postJson(route('customers.store'), array_replace($this->customerData, ['contact_number' => '09214028982']))
        ->assertStatus(422)->assertJsonPath('success', false);
    $this->assertDatabaseCount('customers', 1);
    $this->getJson(route('customers.pricingHistory', $customerId))->assertStatus(503);
});

test('invalid prices cannot update a customer or create history', function () {
    $this->actingAs($this->admin)->postJson(route('customers.store'), $this->customerData)->assertOk();
    $customerId = DB::table('customers')->value('id');

    $this->postJson(route('customers.update'), array_replace($this->customerData, [
        'id' => $customerId,
        'sp_diesel' => -1,
    ]))->assertUnprocessable()->assertJsonValidationErrors('sp_diesel');

    $this->assertDatabaseCount('customer_price_histories', 2);
    $this->assertDatabaseHas('customers', ['id' => $customerId, 'sp_diesel' => 75]);
});

test('price history requires admin access and returns not found for a missing customer', function () {
    $this->getJson(route('customers.pricingHistory', 999))->assertUnauthorized();

    $this->admin->update(['role' => User::ROLE_STAFF]);
    $this->actingAs($this->admin)->getJson(route('customers.pricingHistory', 999))
        ->assertRedirect(route('staff.pump.index'));

    $this->admin->update(['role' => User::ROLE_VP_ADMIN]);
    $this->getJson(route('customers.pricingHistory', 999))->assertNotFound();
});
