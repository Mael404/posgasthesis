<?php

use App\Services\LitersMovementTrackingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INCLUDED_STATUSES = ['pending', 'verified', 'approved'];

    public function up(): void
    {
        if (
            ! Schema::hasTable('liters_movement_period_readings')
            && Schema::hasTable('liters_tracking_periods')
            && Schema::hasTable('shift_readings')
        ) {
            Schema::create('liters_movement_period_readings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('liters_tracking_period_id')
                    ->constrained('liters_tracking_periods', 'id', 'ltm_period_reading_period_fk')
                    ->restrictOnDelete();
                $table->foreignId('shift_reading_id')
                    ->constrained('shift_readings', 'id', 'ltm_period_reading_shift_fk')
                    ->restrictOnDelete();
                $table->string('inclusion_status', 24)->default('included');
                $table->string('inclusion_source', 32)->default('period_assignment');
                $table->timestamp('included_at');
                $table->foreignId('included_by')->nullable()
                    ->constrained('users', 'id', 'ltm_period_reading_included_by_fk')
                    ->nullOnDelete();
                $table->timestamp('removed_at')->nullable();
                $table->foreignId('removed_by')->nullable()
                    ->constrained('users', 'id', 'ltm_period_reading_removed_by_fk')
                    ->nullOnDelete();
                $table->string('removal_status', 32)->nullable();
                $table->text('removal_reason')->nullable();
                $table->timestamps();

                $table->unique(
                    ['liters_tracking_period_id', 'shift_reading_id'],
                    'ltm_period_reading_unique'
                );
                $table->index(
                    ['liters_tracking_period_id', 'inclusion_status'],
                    'ltm_period_reading_status_idx'
                );
                $table->index(
                    ['shift_reading_id', 'inclusion_status'],
                    'ltm_reading_status_idx'
                );
            });
        }

        if (! Schema::hasTable('liters_movement_period_readings')) {
            return;
        }

        $this->backfillPeriodMemberships();

        // Legacy signed corrections are retained for audit, but must never be
        // included in current or generated totals after source recalculation.
        if (Schema::hasTable('liters_movement_corrections')) {
            DB::table('liters_movement_corrections')
                ->where('status', '<>', 'cancelled')
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);
        }

        app(LitersMovementTrackingService::class)
            ->recalculateAllGeneratedPeriods(null, 'membership_backfill');
    }

    public function down(): void
    {
        Schema::dropIfExists('liters_movement_period_readings');
    }

    private function backfillPeriodMemberships(): void
    {
        if (! Schema::hasColumn('shift_readings', 'liters_tracking_period_id')) {
            return;
        }

        DB::table('shift_readings')
            ->whereNotNull('liters_tracking_period_id')
            ->orderBy('id')
            ->chunkById(200, function ($readings) {
                foreach ($readings as $reading) {
                    $period = DB::table('liters_tracking_periods')
                        ->where('id', (int) $reading->liters_tracking_period_id)
                        ->first();

                    if (! $period) {
                        continue;
                    }

                    $readingStatus = strtolower(trim((string) ($reading->status ?? '')));
                    $isIncluded = in_array($readingStatus, self::INCLUDED_STATUSES, true);
                    $includedAt = $reading->created_at ?? $period->started_at ?? now();
                    $removedAt = $isIncluded
                        ? null
                        : ($reading->voided_at ?? $reading->updated_at ?? now());
                    $removalReason = $isIncluded
                        ? null
                        : ($reading->rejection_reason ?? $reading->void_reason ?? null);
                    $includedBy = $this->existingUserId($period->generated_by ?? $reading->user_id ?? null);
                    $removedBy = $isIncluded
                        ? null
                        : $this->existingUserId($reading->voided_by ?? null);

                    DB::table('liters_movement_period_readings')->updateOrInsert(
                        [
                            'liters_tracking_period_id' => (int) $period->id,
                            'shift_reading_id' => (int) $reading->id,
                        ],
                        [
                            'inclusion_status' => $isIncluded ? 'included' : 'removed',
                            'inclusion_source' => 'legacy_period_assignment',
                            'included_at' => $includedAt,
                            'included_by' => $includedBy,
                            'removed_at' => $removedAt,
                            'removed_by' => $removedBy,
                            'removal_status' => $isIncluded ? null : ($reading->status ?? 'Removed'),
                            'removal_reason' => $removalReason,
                            'created_at' => $includedAt,
                            'updated_at' => $removedAt ?? now(),
                        ]
                    );

                    if (
                        ! $isIncluded
                        && in_array(strtolower((string) ($period->status ?? '')), ['active', 'generated'], true)
                    ) {
                        DB::table('shift_readings')
                            ->where('id', (int) $reading->id)
                            ->where('liters_tracking_period_id', (int) $period->id)
                            ->update(['liters_tracking_period_id' => null]);
                    }
                }
            }, 'id');
    }

    private function existingUserId($userId): ?int
    {
        $userId = (int) $userId;

        if ($userId <= 0 || ! Schema::hasTable('users')) {
            return null;
        }

        return DB::table('users')->where('id', $userId)->exists() ? $userId : null;
    }
};
