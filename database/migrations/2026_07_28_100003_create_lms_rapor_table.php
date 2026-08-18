<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LMS Rapor Table — Layer 4: Rapor & Rekap Nilai
 *
 * Tabel yang dibuat:
 * 1. lms_rapor — Rapor digital per siswa per semester (ringkasan dari student_grades)
 *
 * Note: Rekap nilai per kelas dihasilkan secara agregasi dari student_grades
 * yang sudah ada — tidak perlu tabel baru untuk rekap_nilai.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        Schema::create('lms_rapor', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            // Relasi Utama
            $table->uuid('siswa_id');
            $table->uuid('kelas_id');
            $table->uuid('semester_id');
            $table->uuid('tahun_ajaran_id');
            $table->uuid('guru_wali_id')->nullable()->comment('FK ke employees (wali kelas)');

            // Ringkasan Nilai Akademik
            $table->decimal('total_nilai', 7, 2)->nullable();
            $table->decimal('rata_rata', 5, 2)->nullable();
            $table->unsignedSmallInteger('peringkat_kelas')->nullable();
            $table->unsignedSmallInteger('total_siswa_kelas')->nullable();
            $table->unsignedSmallInteger('total_mapel')->default(0);
            $table->unsignedSmallInteger('mapel_lulus')->default(0);
            $table->unsignedSmallInteger('mapel_tidak_lulus')->default(0);

            // Ringkasan Kehadiran
            $table->unsignedSmallInteger('total_hari_efektif')->default(0);
            $table->unsignedSmallInteger('total_hadir')->default(0);
            $table->unsignedSmallInteger('total_izin')->default(0);
            $table->unsignedSmallInteger('total_sakit')->default(0);
            $table->unsignedSmallInteger('total_alpha')->default(0);

            // Catatan & Status
            $table->text('catatan_wali_kelas')->nullable();
            $table->text('catatan_kepala_sekolah')->nullable();
            $table->string('status_rapor', 20)->default('draft')
                ->comment('draft, final, diterbitkan, direvisi');
            $table->date('tanggal_terbit')->nullable();
            $table->boolean('sudah_dilihat_ortu')->default(false);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
                $table->softDeletesTz();
            } else {
                $table->timestamps();
                $table->softDeletes();
            }

            // Satu siswa hanya punya satu rapor per semester
            $table->unique(
                ['siswa_id', 'semester_id', 'tahun_ajaran_id'],
                'lms_rapor_siswa_period_unique'
            );

            $table->index(['kelas_id', 'semester_id', 'tahun_ajaran_id'], 'lms_rapor_kelas_idx');
            $table->index(['status_rapor'], 'lms_rapor_status_idx');

            $table->foreign('siswa_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('guru_wali_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_rapor');
    }
};
