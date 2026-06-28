<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcement_comments') || Schema::hasColumn('announcement_comments', 'parent_id')) {
            return;
        }

        Schema::table('announcement_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('announcement_comments') || ! Schema::hasColumn('announcement_comments', 'parent_id')) {
            return;
        }

        Schema::table('announcement_comments', function (Blueprint $table) {
            $table->dropColumn('parent_id');
        });
    }
};
