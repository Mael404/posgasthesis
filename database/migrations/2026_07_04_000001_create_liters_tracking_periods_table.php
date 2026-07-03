<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('liters_tracking_periods')) {
            return;
        }

        Schema::create('liters_tracking_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_code')->unique();
            $table->decimal('liters_limit', 12, 2)->default(4000);
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('totals_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liters_tracking_periods');
    }
};
