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
        Schema::table('site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('site_settings', 'address')) {
                $table->string('address', 255)->nullable()->after('footer_text');
            }
            if (!Schema::hasColumn('site_settings', 'phone')) {
                $table->string('phone', 50)->nullable()->after('address');
            }
            if (!Schema::hasColumn('site_settings', 'sk_pendirian')) {
                $table->string('sk_pendirian', 100)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('site_settings', 'motto')) {
                $table->string('motto', 255)->nullable()->after('sk_pendirian');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $columns = ['address', 'phone', 'sk_pendirian', 'motto'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
