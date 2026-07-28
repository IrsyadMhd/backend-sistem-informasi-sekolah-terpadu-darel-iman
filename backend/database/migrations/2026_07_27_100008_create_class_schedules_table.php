<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 08
 *
 * Masalah: Tidak ada tabel Jadwal Pelajaran.
 *
 * Solusi: Buat tabel `class_schedules` baru.
 *
 * Desain:
 * - Dual FK kelas: `kelas_id` (→ tbl_kelas, primer) + `class_id` (→ classes, legacy)
 * - Dual FK guru: `employee_id` (→ employees, primer) + `teacher_id` (→ teachers, legacy)
 * - Ini memungkinkan backward compat dengan kedua sistem kelas/guru.
 *
 * Aturan SAFE REFACTOR:
 * - Tabel baru, tidak mengubah yang lama.
 * - Semua FK asing nullable untuk fleksibilitas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            // Referensi Kelas — dual FK untuk backward compat
            $table->uuid('kelas_id')->nullable()->comment('FK primer ke tbl_kelas');
            $table->uuid('class_id')->nullable()->comment('FK legacy ke classes (read-only)');

            // Referensi Guru — dual FK untuk backward compat
            $table->uuid('employee_id')->nullable()->comment('FK primer ke employees');
            $table->uuid('teacher_id')->nullable()->comment('FK legacy ke teachers');

            // Mata Pelajaran & Ruangan
            $table->uuid('subject_id');
            $table->uuid('classroom_id')->nullable();

            // Periode
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');

            // Jadwal Harian
            $table->smallInteger('day_of_week')->comment('1=Senin, 2=Selasa, ..., 7=Minggu');
            $table->time('time_start');
            $table->time('time_end');
            $table->string('week_type', 20)->default('all')->comment('all / odd / even — untuk jadwal ganjil/genap');

            // Status & Metadata
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('metadata')->nullable();

            // Audit Log
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->softDeletesTz();
            $table->timestampsTz();

            // Index performa
            $table->index(['academic_year_id', 'semester_id', 'day_of_week'], 'schedules_period_day_idx');
            $table->index(['kelas_id', 'day_of_week', 'is_active'], 'schedules_kelas_day_idx');
            $table->index(['employee_id', 'day_of_week', 'is_active'], 'schedules_employee_day_idx');

            // FK
            $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->nullOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->nullOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->nullOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('classroom_id')->references('id')->on('classrooms')->nullOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
        });

        // CHECK constraint ditambahkan via raw SQL setelah tabel dibuat
        // (Blueprint::check() tidak ada di Laravel — harus pakai DB::statement())
        DB::statement('
            ALTER TABLE class_schedules
            ADD CONSTRAINT chk_day_of_week
            CHECK (day_of_week >= 1 AND day_of_week <= 7)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
