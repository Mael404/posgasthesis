<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_logs')) {
            return;
        }

        $indexes = DB::select("SHOW INDEX FROM inventory_logs WHERE Key_name = 'inventory_logs_delivery_code_unique'");

        if ($indexes) {
            DB::statement('ALTER TABLE inventory_logs DROP INDEX inventory_logs_delivery_code_unique');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_logs') || ! Schema::hasColumn('inventory_logs', 'delivery_code')) {
            return;
        }

        $indexes = DB::select("SHOW INDEX FROM inventory_logs WHERE Key_name = 'inventory_logs_delivery_code_unique'");

        if ($indexes || $this->hasDuplicateDeliveryCodes()) {
            return;
        }

        DB::statement('ALTER TABLE inventory_logs ADD UNIQUE inventory_logs_delivery_code_unique (delivery_code)');
    }

    private function hasDuplicateDeliveryCodes(): bool
    {
        $duplicates = DB::table('inventory_logs')
            ->select('delivery_code')
            ->whereNotNull('delivery_code')
            ->groupBy('delivery_code')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        return $duplicates;
    }
};
