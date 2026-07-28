<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 09
 *
 * Masalah: Tidak ada tabel nilai akhir/raport. Nilai tersebar di
 * assignment_submissions, exam_results, memorization_reports.
 *
 * Solusi: Buat tabel `student_grades` untuk nilai akhir per semester.
 *
 * Desain:
 * - Dual FK kelas: `kelas_id` (→ tbl_kelas, primer) + `class_id` (→ classes, legacy)
 * - Final score dihitung dari komponen sesuai bobot di modul_semesters.
 * - Unique constraint: satu siswa, satu mapel, satu semester.
 *
 * Aturan SAFE REFACTOR:
 * - Tabel baru, tidak mengubah yang lama.
 * - Semua score column nullable (input bertahap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_grades', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            // Relasi utama
            $table->uuid('student_id');
            $table->uuid('subject_id');
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');

            // Dual FK kelas untuk backward compat
            $table->uuid('kelas_id')->nullable()->comment('FK primer ke tbl_kelas');
            $table->uuid('class_id')->nullable()->comment('FK legacy ke classes');

            // Komponen Nilai (nullable — diisi bertahap)
            $table->decimal('score_assignment', 5, 2)->nullable()->comment('Nilai Tugas/PR');
            $table->decimal('score_quiz', 5, 2)->nullable()->comment('Nilai Quiz/Ulangan Harian');
            $table->decimal('score_project', 5, 2)->nullable()->comment('Nilai Proyek');
            $table->decimal('score_midterm', 5, 2)->nullable()->comment('Nilai UTS/PTS');
            $table->decimal('score_final', 5, 2)->nullable()->comment('Nilai UAS/PAT');

            // Nilai Akhir (dihitung dari komponen × bobot di modul_semesters)
            $table->decimal('final_score', 5, 2)->nullable()->comment('Nilai Akhir Semester');
            $table->string('grade_letter', 5)->nullable()->comment('A, B, C, D, E');
            $table->boolean('is_passed')->default(true)->comment('Lulus KKM atau tidak');

            // Catatan Guru
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->softDeletesTz();
            $table->timestampsTz();

            // Unique: satu nilai per siswa per mapel per semester
            $table->unique(
                ['student_id', 'subject_id', 'academic_year_id', 'semester_id'],
                'uniq_student_grade_period'
            );

            // Index performa
            $table->index(['academic_year_id', 'semester_id', 'kelas_id'], 'grades_period_kelas_idx');
            $table->index(['student_id', 'academic_year_id', 'semester_id'], 'grades_student_period_idx');

            // FK
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->nullOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->nullOnDelete();
        });

        // Tambah check constraints
        DB::statement('ALTER TABLE student_grades ADD CONSTRAINT chk_score_assignment CHECK (score_assignment IS NULL OR (score_assignment >= 0 AND score_assignment <= 100))');
        DB::statement('ALTER TABLE student_grades ADD CONSTRAINT chk_score_quiz CHECK (score_quiz IS NULL OR (score_quiz >= 0 AND score_quiz <= 100))');
        DB::statement('ALTER TABLE student_grades ADD CONSTRAINT chk_score_project CHECK (score_project IS NULL OR (score_project >= 0 AND score_project <= 100))');
        DB::statement('ALTER TABLE student_grades ADD CONSTRAINT chk_score_midterm CHECK (score_midterm IS NULL OR (score_midterm >= 0 AND score_midterm <= 100))');
        DB::statement('ALTER TABLE student_grades ADD CONSTRAINT chk_score_final CHECK (score_final IS NULL OR (score_final >= 0 AND score_final <= 100))');
        DB::statement('ALTER TABLE student_grades ADD CONSTRAINT chk_final_score CHECK (final_score IS NULL OR (final_score >= 0 AND final_score <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
