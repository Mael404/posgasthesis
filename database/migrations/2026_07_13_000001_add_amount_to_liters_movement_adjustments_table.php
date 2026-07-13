<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('liters_movement_adjustments')
            || Schema::hasColumn('liters_movement_adjustments', 'amount')
        ) {
            return;
        }

        Schema::table('liters_movement_adjustments', function (Blueprint $table) {
            $table->decimal('amount', 14, 2)->default(0)->after('liters');
        });
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('liters_movement_adjustments')
            || ! Schema::hasColumn('liters_movement_adjustments', 'amount')
        ) {
            return;
        }

        Schema::table('liters_movement_adjustments', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
