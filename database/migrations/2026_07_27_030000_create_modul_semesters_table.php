<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi tabel modul_semesters & modul_semester_details untuk PostgreSQL.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        }

        Schema::create('modul_semesters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tahun_ajaran_id')->index();
            $table->uuid('semester_id')->index();
            $table->uuid('unit_pendidikan_id')->nullable()->index();
            $table->uuid('kelas_id')->index();
            $table->uuid('mata_pelajaran_id')->index();
            $table->uuid('guru_id')->index()->comment('FK ke tabel employees (Guru Pengampu)');

            $table->string('kode_modul', 50)->unique();
            $table->string('nama_modul', 150);
            $table->string('jenjang', 50)->nullable();
            $table->string('kurikulum', 100)->default('Kurikulum Merdeka');
            $table->string('status', 20)->default('Aktif')->index()->comment('Aktif, Nonaktif, Arsip');

            // Pembelajaran
            $table->text('atp')->nullable()->comment('Alur Tujuan Pembelajaran');
            $table->text('cp')->nullable()->comment('Capaian Pembelajaran');
            $table->text('tujuan_pembelajaran')->nullable();
            $table->integer('alokasi_jam')->default(36)->comment('Alokasi Jam Pelajaran (JP)');
            $table->integer('jumlah_pertemuan')->default(18);
            $table->text('metode_pembelajaran')->nullable();
            $table->string('model_pembelajaran', 100)->nullable();
            $table->text('media_pembelajaran')->nullable();
            $table->text('sumber_belajar')->nullable();

            // Target
            $table->decimal('target_nilai_minimum', 5, 2)->default(75.00);
            $table->decimal('target_kehadiran', 5, 2)->default(90.00);
            $table->text('target_hafalan')->nullable();
            $table->text('target_proyek')->nullable();

            // Pengaturan
            $table->date('berlaku_mulai')->nullable();
            $table->date('berlaku_sampai')->nullable();
            $table->boolean('ditampilkan_di_portal_ortu')->default(true);
            $table->boolean('ditampilkan_di_aplikasi_siswa')->default(true);
            $table->boolean('arsip_otomatis')->default(false);

            // Bobot Penilaian (%) - Total Harus 100%
            $table->decimal('bobot_tugas', 5, 2)->default(20.00);
            $table->decimal('bobot_quiz', 5, 2)->default(15.00);
            $table->decimal('bobot_projek', 5, 2)->default(25.00);
            $table->decimal('bobot_uts', 5, 2)->default(20.00);
            $table->decimal('bobot_uas', 5, 2)->default(20.00);

            // Audit Trail
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->softDeletesTz();
            $table->timestampsTz();

            // Foreign Keys
            $table->foreign('tahun_ajaran_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->cascadeOnDelete();
            $table->foreign('unit_pendidikan_id')->references('id')->on('education_units')->nullOnDelete();
            $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->cascadeOnDelete();
            $table->foreign('mata_pelajaran_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('guru_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();

            // Unique constraint: Tidak boleh ada Modul Semester yang sama pada kombinasi Tahun Ajaran + Semester + Unit + Kelas + Mata Pelajaran
            $table->unique(
                ['tahun_ajaran_id', 'semester_id', 'unit_pendidikan_id', 'kelas_id', 'mata_pelajaran_id'],
                'uniq_modul_semester_combo'
            );
        });

        Schema::create('modul_semester_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('modul_semester_id')->index();
            $table->integer('minggu')->default(1);
            $table->string('materi', 255);
            $table->text('atp')->nullable();
            $table->text('cp')->nullable();
            $table->integer('jp')->default(2)->comment('Jam Pelajaran minggu ini');
            $table->text('keterangan')->nullable();
            $table->timestampsTz();

            $table->foreign('modul_semester_id')->references('id')->on('modul_semesters')->cascadeOnDelete();
        });
    }

    /**
     * Membalikkan migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('modul_semester_details');
        Schema::dropIfExists('modul_semesters');
    }
};
