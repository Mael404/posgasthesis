<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_invoices')) {
            return;
        }

        if (! Schema::hasTable('sales_invoice_number_counters')) {
            Schema::create('sales_invoice_number_counters', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('status', 24)->default('Active')->after('authorized_representative');
            $table->timestamp('canceled_at')->nullable()->after('status');
            $table->foreignId('canceled_by')->nullable()->after('canceled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable()->after('canceled_by');
            $table->index(['status', 'invoice_date'], 'sales_invoices_status_date_idx');
        });

        DB::transaction(function () {
            $lastNumber = 0;

            DB::table('sales_invoices')
                ->orderBy('id')
                ->select('id')
                ->get()
                ->each(function (object $invoice) use (&$lastNumber) {
                    $lastNumber++;

                    DB::table('sales_invoices')
                        ->where('id', $invoice->id)
                        ->update(['si_number' => (string) $lastNumber]);
                });

            DB::table('sales_invoice_number_counters')->updateOrInsert(
                ['id' => 1],
                [
                    'last_number' => $lastNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex(['si_number']);
            $table->unique('si_number');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_invoices')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->dropUnique(['si_number']);
                $table->index('si_number');
                $table->dropIndex('sales_invoices_status_date_idx');
                $table->dropConstrainedForeignId('canceled_by');
                $table->dropColumn(['status', 'canceled_at', 'cancellation_reason']);
            });
        }

        Schema::dropIfExists('sales_invoice_number_counters');
    }
};
