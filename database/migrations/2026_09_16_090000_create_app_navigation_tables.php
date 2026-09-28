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
        Schema::create('app_modules', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. 'dashboard', 'master-data', 'akademik', etc.
            $table->string('name');
            $table->string('icon');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('platform', 20)->default('both'); // 'web', 'mobile', 'both'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('platform');
        });

        Schema::create('app_menus', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. 'dash-super-admin', 'master-siswa', etc.
            $table->string('module_id');
            $table->string('name');
            $table->string('path');
            $table->string('icon');
            $table->json('required_permissions')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('platform', 20)->default('both'); // 'web', 'mobile', 'both'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('module_id')
                ->references('id')
                ->on('app_modules')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index(['module_id', 'is_active', 'sort_order']);
            $table->index('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_menus');
        Schema::dropIfExists('app_modules');
    }
};
