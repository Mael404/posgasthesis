<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fuel_inventory_reports')
            && ! Schema::hasColumn('fuel_inventory_reports', 'liters_tracking_period_id')) {
            Schema::table('fuel_inventory_reports', function (Blueprint $table) {
                $table->foreignId('liters_tracking_period_id')
                    ->nullable()
                    ->after('report_code')
                    ->constrained('liters_tracking_periods', 'id', 'fuel_inventory_report_ltm_period_fk')
                    ->restrictOnDelete();
                $table->unique(
                    'liters_tracking_period_id',
                    'fuel_inventory_report_ltm_period_unique'
                );
            });
        }

        if (Schema::hasTable('fuel_inventory_report_lines')) {
            Schema::table('fuel_inventory_report_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('fuel_inventory_report_lines', 'tank_id')) {
                    $table->unsignedInteger('tank_id')
                        ->nullable()
                        ->after('fuel_inventory_report_id');
                }

                if (! Schema::hasColumn('fuel_inventory_report_lines', 'tank_limit_liters')) {
                    $table->decimal('tank_limit_liters', 16, 3)
                        ->default(0)
                        ->after('periodic_average_cost');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fuel_inventory_report_lines')) {
            Schema::table('fuel_inventory_report_lines', function (Blueprint $table) {
                if (Schema::hasColumn('fuel_inventory_report_lines', 'tank_id')) {
                    $table->dropColumn('tank_id');
                }

                if (Schema::hasColumn('fuel_inventory_report_lines', 'tank_limit_liters')) {
                    $table->dropColumn('tank_limit_liters');
                }
            });
        }

        if (Schema::hasTable('fuel_inventory_reports')
            && Schema::hasColumn('fuel_inventory_reports', 'liters_tracking_period_id')) {
            Schema::table('fuel_inventory_reports', function (Blueprint $table) {
                $table->dropUnique('fuel_inventory_report_ltm_period_unique');
                $table->dropForeign('fuel_inventory_report_ltm_period_fk');
                $table->dropColumn('liters_tracking_period_id');
            });
        }
    }
};
