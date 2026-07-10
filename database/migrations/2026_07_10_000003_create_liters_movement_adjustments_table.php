<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('liters_movement_adjustments')
            || ! Schema::hasTable('shift_readings')
            || ! Schema::hasTable('liters_tracking_periods')
        ) {
            return;
        }

        Schema::create('liters_movement_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liters_tracking_period_id')->constrained('liters_tracking_periods')->cascadeOnDelete();
            $table->foreignId('shift_reading_id')->constrained('shift_readings')->cascadeOnDelete();
            $table->unsignedTinyInteger('pump_number');
            $table->decimal('liters', 12, 2);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('void_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['shift_reading_id', 'pump_number', 'voided_at'], 'ltm_adjustments_reading_pump_void_idx');
            $table->index(['liters_tracking_period_id', 'voided_at'], 'ltm_adjustments_period_void_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liters_movement_adjustments');
    }
};
