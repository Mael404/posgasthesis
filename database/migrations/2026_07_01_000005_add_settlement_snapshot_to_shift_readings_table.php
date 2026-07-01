<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shift_readings')) {
            return;
        }

        Schema::table('shift_readings', function (Blueprint $table) {
            if (! Schema::hasColumn('shift_readings', 'settlement_snapshot')) {
                $column = $table->json('settlement_snapshot')->nullable();

                if (Schema::hasColumn('shift_readings', 'cash_variance_snapshot')) {
                    $column->after('cash_variance_snapshot');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shift_readings') || ! Schema::hasColumn('shift_readings', 'settlement_snapshot')) {
            return;
        }

        Schema::table('shift_readings', function (Blueprint $table) {
            $table->dropColumn('settlement_snapshot');
        });
    }
};
