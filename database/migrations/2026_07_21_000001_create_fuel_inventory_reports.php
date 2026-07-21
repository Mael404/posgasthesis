<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fuel_prices') && ! Schema::hasColumn('fuel_prices', 'bulk_price_per_liter')) {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->decimal('bulk_price_per_liter', 12, 4)->nullable()->after('price_per_liter');
            });
        }

        if (! Schema::hasTable('fuel_inventory_reports')) {
            Schema::create('fuel_inventory_reports', function (Blueprint $table) {
                $table->id();
                $table->string('report_code', 64)->unique();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 24)->default('Finalized');
                $table->string('calculation_method', 64)->default('moving_weighted_average');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'period_end'], 'fuel_inventory_reports_status_end_idx');
            });
        }

        if (! Schema::hasTable('fuel_inventory_report_lines')) {
            Schema::create('fuel_inventory_report_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fuel_inventory_report_id')
                    ->constrained('fuel_inventory_reports')
                    ->cascadeOnDelete();
                $table->string('fuel_type', 32);
                $table->string('product_name', 64);
                $table->decimal('opening_liters', 16, 3)->default(0);
                $table->decimal('opening_unit_cost', 16, 6)->default(0);
                $table->decimal('opening_value', 18, 6)->default(0);
                $table->decimal('purchased_liters', 16, 3)->default(0);
                $table->decimal('purchase_value', 18, 6)->default(0);
                $table->decimal('available_liters', 16, 3)->default(0);
                $table->decimal('available_value', 18, 6)->default(0);
                $table->decimal('periodic_average_cost', 16, 6)->default(0);
                $table->decimal('dispensed_liters', 16, 3)->default(0);
                $table->decimal('sales_revenue', 18, 6)->default(0);
                $table->decimal('cost_of_goods_sold', 18, 6)->default(0);
                $table->decimal('ending_liters', 16, 3)->default(0);
                $table->decimal('ending_unit_cost', 16, 6)->default(0);
                $table->decimal('ending_value', 18, 6)->default(0);
                $table->decimal('pump_price', 16, 6)->default(0);
                $table->decimal('bulk_price', 16, 6)->default(0);
                $table->decimal('pump_margin', 16, 6)->default(0);
                $table->decimal('bulk_margin', 16, 6)->default(0);
                $table->decimal('average_selling_price', 16, 6)->default(0);
                $table->decimal('gross_margin_per_liter', 16, 6)->default(0);
                $table->decimal('gross_profit', 18, 6)->default(0);
                $table->json('source_summary')->nullable();
                $table->timestamps();

                $table->unique(
                    ['fuel_inventory_report_id', 'fuel_type'],
                    'fuel_inventory_report_lines_report_fuel_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_inventory_report_lines');
        Schema::dropIfExists('fuel_inventory_reports');

        if (Schema::hasTable('fuel_prices') && Schema::hasColumn('fuel_prices', 'bulk_price_per_liter')) {
            Schema::table('fuel_prices', function (Blueprint $table) {
                $table->dropColumn('bulk_price_per_liter');
            });
        }
    }
};
