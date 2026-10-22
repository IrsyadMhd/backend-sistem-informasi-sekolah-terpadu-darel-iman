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
        Schema::table('class_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('class_schedules', 'activity_type')) {
                $table->string('activity_type', 30)->default('kbm')->after('classroom_id')->comment('kbm, routine, break');
            }
            if (!Schema::hasColumn('class_schedules', 'activity_name')) {
                $table->string('activity_name', 255)->nullable()->after('activity_type')->comment('Nama kegiatan manual');
            }
        });

        // Buat subject_id nullable di class_schedules jika belum nullable
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE class_schedules ALTER COLUMN subject_id DROP NOT NULL;');
        } catch (\Throwable $e) {
            // PostgreSQL / SQLite fallback
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('class_schedules', 'activity_name')) {
                $table->dropColumn('activity_name');
            }
            if (Schema::hasColumn('class_schedules', 'activity_type')) {
                $table->dropColumn('activity_type');
            }
        });
    }
};
