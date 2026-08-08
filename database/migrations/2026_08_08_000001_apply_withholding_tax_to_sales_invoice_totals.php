<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_invoices') || ! Schema::hasColumns('sales_invoices', [
            'amount_net_of_vat',
            'add_vat',
            'withholding_tax',
            'total_amount_due',
        ])) {
            return;
        }

        DB::table('sales_invoices')->update([
            'total_amount_due' => DB::raw('ROUND((amount_net_of_vat + add_vat) - withholding_tax, 2)'),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_invoices') || ! Schema::hasColumns('sales_invoices', [
            'amount_net_of_vat',
            'add_vat',
            'total_amount_due',
        ])) {
            return;
        }

        DB::table('sales_invoices')->update([
            'total_amount_due' => DB::raw('ROUND(amount_net_of_vat + add_vat, 2)'),
        ]);
    }
};
