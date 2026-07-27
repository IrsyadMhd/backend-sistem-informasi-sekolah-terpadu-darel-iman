<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi tabel tbl_kelas untuk PostgreSQL.
     */
    public function up(): void
    {
        // Pastikan ekstensi pgcrypto aktif untuk UUID
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        Schema::create('tbl_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('yayasan_id')->nullable()->index();
            $table->uuid('unit_pendidikan_id')->index();
            $table->uuid('tahun_ajaran_id')->index();
            $table->uuid('semester_id')->index();
            $table->string('jenjang', 30)->comment('Contoh: TK, SD, SMP, SMA, MA');
            $table->string('tingkat', 20)->comment('Contoh: 1, 2, 7, 10, A, B');
            $table->string('kode_kelas', 50)->unique()->comment('Kode unik rombel/kelas');
            $table->string('nama_kelas', 100)->comment('Nama rombel/kelas, contoh: 7-A, 10-IPA-1');
            $table->uuid('wali_kelas_id')->nullable()->index()->comment('FK ke tabel employees (Guru)');
            $table->integer('kapasitas')->default(30)->comment('Kapasitas maksimal siswa (min 1)');
            $table->string('ruangan', 50)->nullable()->comment('Lokasi/nama ruangan kelas');
            $table->string('status', 20)->default('Aktif')->index()->comment('Status kelas: Aktif / Nonaktif');

            // Audit Log Fields
            $table->uuid('created_by')->nullable()->comment('ID Pengguna yang membuat data');
            $table->uuid('updated_by')->nullable()->comment('ID Pengguna yang mengubah data');
            $table->uuid('deleted_by')->nullable()->comment('ID Pengguna yang menghapus data');

            // Standard Timestamps & Soft Deletes
            $table->softDeletesTz();
            $table->timestampsTz();

            // Foreign Key Constraints
            $table->foreign('unit_pendidikan_id')->references('id')->on('education_units')->cascadeOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->cascadeOnDelete();
            $table->foreign('wali_kelas_id')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();

            // Index gabungan untuk performa query filter
            $table->index(['unit_pendidikan_id', 'tahun_ajaran_id', 'semester_id']);
        });
    }

    /**
     * Membalikkan migrasi (drop tabel tbl_kelas).
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_kelas');
    }
};
