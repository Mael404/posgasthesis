<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_price_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('fuel_type', 20);
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->decimal('new_price', 10, 2)->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('created_at');
            $table->index(['customer_id', 'created_at', 'id'], 'customer_price_histories_customer_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_price_histories');
    }
};
