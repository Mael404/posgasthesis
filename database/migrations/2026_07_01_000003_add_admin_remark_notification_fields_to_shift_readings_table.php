<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'shift_readings';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, 'admin_remarks_by')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $column = $table->unsignedBigInteger('admin_remarks_by')->nullable();

                if (Schema::hasColumn(self::TABLE, 'admin_remarks')) {
                    $column->after('admin_remarks');
                }
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'admin_remarks_updated_at')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $column = $table->timestamp('admin_remarks_updated_at')->nullable();

                if (Schema::hasColumn(self::TABLE, 'admin_remarks_by')) {
                    $column->after('admin_remarks_by');
                } elseif (Schema::hasColumn(self::TABLE, 'admin_remarks')) {
                    $column->after('admin_remarks');
                }
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'admin_remarks_seen_at')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $column = $table->timestamp('admin_remarks_seen_at')->nullable();

                if (Schema::hasColumn(self::TABLE, 'admin_remarks_updated_at')) {
                    $column->after('admin_remarks_updated_at');
                } elseif (Schema::hasColumn(self::TABLE, 'admin_remarks')) {
                    $column->after('admin_remarks');
                }
            });
        }

        $this->backfillExistingRemarks();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $columns = collect([
            'admin_remarks_seen_at',
            'admin_remarks_updated_at',
            'admin_remarks_by',
        ])->filter(fn ($column) => Schema::hasColumn(self::TABLE, $column))->all();

        if ($columns !== []) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function backfillExistingRemarks(): void
    {
        if (
            ! Schema::hasColumn(self::TABLE, 'admin_remarks')
            || ! Schema::hasColumn(self::TABLE, 'admin_remarks_updated_at')
        ) {
            return;
        }

        DB::table(self::TABLE)
            ->whereNotNull('admin_remarks')
            ->whereRaw("TRIM(COALESCE(admin_remarks, '')) <> ''")
            ->whereNull('admin_remarks_updated_at')
            ->update(['admin_remarks_updated_at' => now()]);
    }
};
