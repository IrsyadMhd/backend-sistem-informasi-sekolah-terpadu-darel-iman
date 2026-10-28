<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_menus', function (Blueprint $table) {
            $table->string('name_mobile')->nullable()->after('name');
            $table->string('icon_mobile')->nullable()->after('icon');
            $table->json('target_roles')->nullable()->after('required_permissions');
            $table->json('metadata')->nullable()->after('platform');
        });

        Schema::table('app_modules', function (Blueprint $table) {
            $table->string('name_mobile')->nullable()->after('name');
            $table->string('icon_mobile')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('app_menus', function (Blueprint $table) {
            $table->dropColumn(['name_mobile', 'icon_mobile', 'target_roles', 'metadata']);
        });

        Schema::table('app_modules', function (Blueprint $table) {
            $table->dropColumn(['name_mobile', 'icon_mobile']);
        });
    }
};
