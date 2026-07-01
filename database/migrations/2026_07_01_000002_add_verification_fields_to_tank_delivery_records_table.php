<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'tank_delivery_records';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLE, 'verified_by')) {
                $table->unsignedBigInteger('verified_by')->nullable()->after('staff_id');
            }

            if (! Schema::hasColumn(self::TABLE, 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified_by');
            }

            if (! Schema::hasColumn(self::TABLE, 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('verified_at');
            }

            if (! Schema::hasColumn(self::TABLE, 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }

            if (! Schema::hasColumn(self::TABLE, 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $columns = collect([
                'rejection_reason',
                'rejected_at',
                'rejected_by',
                'verified_at',
                'verified_by',
            ])->filter(fn ($column) => Schema::hasColumn(self::TABLE, $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
