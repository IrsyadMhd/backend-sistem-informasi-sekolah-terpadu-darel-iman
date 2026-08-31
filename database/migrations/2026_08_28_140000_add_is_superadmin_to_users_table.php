<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_superadmin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_superadmin')->default(false)->after('is_active');
            });

            // Set is_superadmin = true for users with Super Admin role or superadmin email
            try {
                \DB::table('users')
                    ->where('email', 'like', '%superadmin%')
                    ->orWhere('name', 'like', '%Super Admin%')
                    ->update(['is_superadmin' => true]);
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_superadmin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_superadmin');
            });
        }
    }
};
