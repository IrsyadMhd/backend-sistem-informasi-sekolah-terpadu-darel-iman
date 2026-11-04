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
        if (! Schema::hasTable('pelatihan_gurus')) {
            Schema::create('pelatihan_gurus', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('kode_pelatihan', 50)->unique()->index();
                $table->string('nama', 255)->index();
                $table->string('kategori', 100)->index(); // 'Pedagogik & Asesmen', 'Kepribadian & Konseling BK', 'Bahasa & Keislaman', 'Teknologi & Media Belajar', 'Kepemimpinan & Manajemen Sekolah'
                $table->string('narasumber', 150)->nullable();
                $table->integer('jam_pelatihan')->default(32); // Target JP
                $table->date('tanggal_mulai')->index();
                $table->date('tanggal_selesai')->nullable();
                $table->string('lokasi', 150)->default('Aula Utama Yayasan & Zoom');
                $table->integer('kuota')->default(100);
                $table->integer('jumlah_terdaftar')->default(0);
                $table->string('status', 30)->default('akan_datang')->index(); // 'akan_datang', 'berjalan', 'selesai', 'dibatalkan'
                $table->text('deskripsi')->nullable();
                $table->string('penyelenggara', 150)->default('Divisi Pendidikan Yayasan');
                $table->uuid('target_unit_id')->nullable()->index();
                $table->uuid('penanggung_jawab_user_id')->nullable()->index();
                $table->jsonb('metadata')->nullable();
                $table->softDeletesTz();
                $table->timestampsTz();

                $table->foreign('target_unit_id')->references('id')->on('education_units')->nullOnDelete();
                $table->foreign('penanggung_jawab_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['kategori', 'status', 'tanggal_mulai'], 'pelatihan_kategori_status_idx');
            });
        }

        if (! Schema::hasTable('pelatihan_guru_pesertas')) {
            Schema::create('pelatihan_guru_pesertas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('pelatihan_id')->index();
                $table->uuid('employee_id')->index();
                $table->uuid('education_unit_id')->nullable()->index();
                $table->string('status_kehadiran', 30)->default('terdaftar')->index(); // 'terdaftar', 'hadir', 'lulus', 'tidak_hadir'
                $table->integer('nilai_evaluasi')->nullable();
                $table->string('nomor_sertifikat', 100)->nullable()->unique();
                $table->text('catatan')->nullable();
                $table->timestampsTz();

                $table->foreign('pelatihan_id')->references('id')->on('pelatihan_gurus')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('education_unit_id')->references('id')->on('education_units')->nullOnDelete();
                $table->unique(['pelatihan_id', 'employee_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelatihan_guru_pesertas');
        Schema::dropIfExists('pelatihan_gurus');
    }
};
