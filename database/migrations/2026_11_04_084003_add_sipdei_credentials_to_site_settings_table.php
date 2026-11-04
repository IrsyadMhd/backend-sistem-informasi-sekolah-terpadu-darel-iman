<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('site_settings')) {
            Schema::table('site_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('site_settings', 'sipdei_base_url')) {
                    $table->string('sipdei_base_url', 255)->nullable()->default('https://sipdei.dareliman.tech')->after('mapbox_token');
                }
                if (!Schema::hasColumn('site_settings', 'sipdei_api_key')) {
                    $table->text('sipdei_api_key')->nullable()->after('sipdei_base_url');
                }
                if (!Schema::hasColumn('site_settings', 'sipdei_cache_ttl')) {
                    $table->integer('sipdei_cache_ttl')->nullable()->default(600)->after('sipdei_api_key');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('site_settings')) {
            Schema::table('site_settings', function (Blueprint $table) {
                if (Schema::hasColumn('site_settings', 'sipdei_cache_ttl')) {
                    $table->dropColumn('sipdei_cache_ttl');
                }
                if (Schema::hasColumn('site_settings', 'sipdei_api_key')) {
                    $table->dropColumn('sipdei_api_key');
                }
                if (Schema::hasColumn('site_settings', 'sipdei_base_url')) {
                    $table->dropColumn('sipdei_base_url');
                }
            });
        }
    }
};
