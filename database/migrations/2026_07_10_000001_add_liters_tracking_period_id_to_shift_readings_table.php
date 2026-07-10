<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('shift_readings')
            || ! Schema::hasTable('liters_tracking_periods')
            || Schema::hasColumn('shift_readings', 'liters_tracking_period_id')
        ) {
            return;
        }

        Schema::table('shift_readings', function (Blueprint $table) {
            $column = $table->foreignId('liters_tracking_period_id')->nullable();

            if (Schema::hasColumn('shift_readings', 'inventory_deductions')) {
                $column->after('inventory_deductions');
            }

            $column->constrained('liters_tracking_periods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shift_readings') || ! Schema::hasColumn('shift_readings', 'liters_tracking_period_id')) {
            return;
        }

        Schema::table('shift_readings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('liters_tracking_period_id');
        });
    }
};
