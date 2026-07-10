<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('liters_tracking_periods')) {
            return;
        }

        Schema::table('liters_tracking_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('liters_tracking_periods', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('generated_by')->index();
            }

            if (! Schema::hasColumn('liters_tracking_periods', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('liters_tracking_periods')) {
            return;
        }

        Schema::table('liters_tracking_periods', function (Blueprint $table) {
            if (Schema::hasColumn('liters_tracking_periods', 'voided_by')) {
                $table->dropConstrainedForeignId('voided_by');
            }

            if (Schema::hasColumn('liters_tracking_periods', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
        });
    }
};
