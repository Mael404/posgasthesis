<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_inventory_report_lines')
            || ! Schema::hasColumn('fuel_inventory_report_lines', 'fuel_type')
            || ! Schema::hasColumn('fuel_inventory_report_lines', 'product_name')) {
            return;
        }

        DB::table('fuel_inventory_report_lines')
            ->whereRaw('LOWER(TRIM(fuel_type)) = ?', ['premium'])
            ->update(['product_name' => 'Premium']);

        DB::table('fuel_inventory_report_lines')
            ->whereIn(DB::raw('LOWER(TRIM(fuel_type))'), ['regular', 'unleaded'])
            ->update(['product_name' => 'Regular']);
    }

    public function down(): void
    {
        // These are display-label corrections; financial report snapshots remain unchanged.
    }
};
