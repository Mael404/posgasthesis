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
            if (! Schema::hasColumn('shift_readings', 'expected_collection_snapshot')) {
                $table->decimal('expected_collection_snapshot', 15, 2)->nullable()->after('cheque_entries');
            }

            if (! Schema::hasColumn('shift_readings', 'cash_variance_snapshot')) {
                $table->decimal('cash_variance_snapshot', 15, 2)->nullable()->after('expected_collection_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shift_readings')) {
            return;
        }

        $columns = collect([
            'cash_variance_snapshot',
            'expected_collection_snapshot',
        ])->filter(fn ($column) => Schema::hasColumn('shift_readings', $column))->all();

        if ($columns === []) {
            return;
        }

        Schema::table('shift_readings', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
