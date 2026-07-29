<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LMS Penugasan Tables — Layer 2: Pelaksanaan Pembelajaran
 *
 * Tabel yang dibuat:
 * 1. lms_penugasan — Penugasan / PR / Proyek dari Guru
 * 2. lms_pengumpulan_tugas — Submission pengumpulan tugas oleh Siswa
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // ─────────────────────────────────────────────────────────────────────
        // 1. Penugasan
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_penugasan', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('mata_pelajaran_id');
            $table->uuid('kelas_id');
            $table->uuid('guru_id');
            $table->uuid('semester_id');
            $table->uuid('tahun_ajaran_id');
            $table->uuid('modul_ajar_id')->nullable()->comment('Opsional: tautkan ke modul ajar');

            $table->string('judul_tugas', 200);
            $table->text('deskripsi')->nullable();
            $table->text('instruksi')->nullable();
            $table->string('tipe_tugas', 20)->default('individu')
                ->comment('individu, kelompok');
            $table->string('jenis_tugas', 20)->default('tugas')
                ->comment('tugas, proyek, quiz, latihan');
            $table->decimal('nilai_maksimal', 5, 2)->default(100);
            $table->decimal('bobot_persen', 5, 2)->default(0)
                ->comment('Bobot kontribusi ke nilai akhir');
            $table->timestamp('tanggal_mulai')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->boolean('izin_kumpul_terlambat')->default(false);
            $table->boolean('is_published')->default(false);
            $table->string('file_lampiran', 500)->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
                $table->softDeletesTz();
            } else {
                $table->timestamps();
                $table->softDeletes();
            }

            $table->index(['mata_pelajaran_id', 'kelas_id', 'semester_id'], 'lms_penugasan_core_idx');
            $table->index(['guru_id', 'tahun_ajaran_id'], 'lms_penugasan_guru_idx');
            $table->index(['deadline'], 'lms_penugasan_deadline_idx');
            $table->index(['is_published'], 'lms_penugasan_published_idx');

            $table->foreign('mata_pelajaran_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->restrictOnDelete();
            $table->foreign('guru_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('modul_ajar_id')->references('id')->on('lms_modul_ajar')->nullOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 2. Pengumpulan Tugas (Submission Siswa)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_pengumpulan_tugas', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('penugasan_id');
            $table->uuid('siswa_id')->comment('FK ke students');

            $table->text('jawaban_teks')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('url_link', 1000)->nullable();
            $table->string('status', 20)->default('belum')
                ->comment('belum, dikumpulkan, terlambat, dinilai, revisi');
            $table->timestamp('waktu_kumpul')->nullable();

            // Penilaian oleh Guru
            $table->decimal('nilai_guru', 5, 2)->nullable();
            $table->text('catatan_guru')->nullable();
            $table->timestamp('waktu_dinilai')->nullable();
            $table->uuid('dinilai_oleh')->nullable()->comment('FK ke employees (guru penilai)');

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
                $table->softDeletesTz();
            } else {
                $table->timestamps();
                $table->softDeletes();
            }

            // Satu siswa hanya bisa mengumpulkan sekali per penugasan
            $table->unique(['penugasan_id', 'siswa_id'], 'lms_pengumpulan_unique');

            $table->index(['penugasan_id', 'status'], 'lms_pengumpulan_penugasan_status_idx');
            $table->index(['siswa_id', 'status'], 'lms_pengumpulan_siswa_status_idx');

            $table->foreign('penugasan_id')->references('id')->on('lms_penugasan')->cascadeOnDelete();
            $table->foreign('siswa_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('dinilai_oleh')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_pengumpulan_tugas');
        Schema::dropIfExists('lms_penugasan');
    }
};
