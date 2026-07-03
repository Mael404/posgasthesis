<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales') || Schema::hasColumn('sales', 'liters_tracking_period_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $column = $table->foreignId('liters_tracking_period_id')->nullable();

            if (Schema::hasColumn('sales', 'locked_at')) {
                $column->after('locked_at');
            }

            $column->constrained('liters_tracking_periods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'liters_tracking_period_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('liters_tracking_period_id');
        });
    }
};
