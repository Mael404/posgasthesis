<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('liters_tracking_periods') || Schema::hasColumn('liters_tracking_periods', 'tank_limits')) {
            return;
        }

        Schema::table('liters_tracking_periods', function (Blueprint $table) {
            $table->longText('tank_limits')->nullable()->after('liters_limit');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('liters_tracking_periods') || ! Schema::hasColumn('liters_tracking_periods', 'tank_limits')) {
            return;
        }

        Schema::table('liters_tracking_periods', function (Blueprint $table) {
            $table->dropColumn('tank_limits');
        });
    }
};
