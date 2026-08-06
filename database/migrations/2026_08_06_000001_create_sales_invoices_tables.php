<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_invoices')) {
            Schema::create('sales_invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('invoice_date');
                $table->string('sold_to');
                $table->string('tin');
                $table->string('terms');
                $table->text('address');
                $table->string('discount_id_no');
                $table->string('signature');
                $table->string('si_number')->default('0XXX');
                $table->string('authorized_representative');
                $table->decimal('vatable_sales', 16, 2)->default(0);
                $table->decimal('vat_exempt_sales', 16, 2)->default(0);
                $table->decimal('zero_rated_sales', 16, 2)->default(0);
                $table->decimal('vat_amount', 16, 2)->default(0);
                $table->decimal('total_sales_vat_inclusive', 16, 2)->default(0);
                $table->decimal('less_vat', 16, 2)->default(0);
                $table->decimal('amount_net_of_vat', 16, 2)->default(0);
                $table->decimal('sc_pwd_discount', 16, 2)->default(0);
                $table->decimal('add_vat', 16, 2)->default(0);
                $table->decimal('withholding_tax', 16, 2)->default(0);
                $table->decimal('total_amount_due', 16, 2)->default(0);
                $table->timestamps();

                $table->index(['staff_id', 'invoice_date'], 'sales_invoices_staff_date_idx');
                $table->index('si_number');
            });
        }

        if (! Schema::hasTable('sales_invoice_items')) {
            Schema::create('sales_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
                $table->unsignedTinyInteger('sort_order');
                $table->string('item_type', 32)->default('Others');
                $table->string('description');
                $table->decimal('quantity', 16, 3)->default(0);
                $table->decimal('price', 16, 2)->default(0);
                $table->decimal('amount', 16, 2)->default(0);
                $table->timestamps();

                $table->unique(['sales_invoice_id', 'sort_order'], 'sales_invoice_items_invoice_order_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
    }
};
