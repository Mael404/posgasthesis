<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('last_auto_backup_downloaded_on')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_auto_backup_downloaded_on']);
            $table->dropColumn('last_auto_backup_downloaded_on');
        });
    }
};
