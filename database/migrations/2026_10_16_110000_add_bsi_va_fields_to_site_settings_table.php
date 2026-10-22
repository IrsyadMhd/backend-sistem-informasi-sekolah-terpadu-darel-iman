<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_settings')) {
            Schema::table('site_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('site_settings', 'bsi_va_prefix')) {
                    $table->string('bsi_va_prefix', 20)->nullable()->default('9023')->after('bank_account_name');
                }
                if (!Schema::hasColumn('site_settings', 'bsi_va_format')) {
                    $table->string('bsi_va_format', 50)->nullable()->default('{PREFIX}{NIS}')->after('bsi_va_prefix');
                }
            });

            // Set default jika masih null
            DB::table('site_settings')->whereNull('bsi_va_prefix')->update([
                'bsi_va_prefix' => '9023',
                'bsi_va_format' => '{PREFIX}{NIS}',
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_settings')) {
            Schema::table('site_settings', function (Blueprint $table) {
                if (Schema::hasColumn('site_settings', 'bsi_va_prefix')) {
                    $table->dropColumn('bsi_va_prefix');
                }
                if (Schema::hasColumn('site_settings', 'bsi_va_format')) {
                    $table->dropColumn('bsi_va_format');
                }
            });
        }
    }
};
