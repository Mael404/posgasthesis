<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('liters_tracking_periods')
            && ! Schema::hasColumn('liters_tracking_periods', 'opening_inventory')) {
            Schema::table('liters_tracking_periods', function (Blueprint $table): void {
                $table->json('opening_inventory')->nullable()->after('tank_limits');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('liters_tracking_periods')
            && Schema::hasColumn('liters_tracking_periods', 'opening_inventory')) {
            Schema::table('liters_tracking_periods', function (Blueprint $table): void {
                $table->dropColumn('opening_inventory');
            });
        }
    }
};
