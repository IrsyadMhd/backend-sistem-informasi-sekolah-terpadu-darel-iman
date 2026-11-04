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
        if (! Schema::hasTable('kurasi_soals')) {
            Schema::create('kurasi_soals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('kode_kurasi', 50)->unique()->index();
                $table->string('judul', 255)->index();
                $table->uuid('education_unit_id')->index();
                $table->uuid('subject_id')->nullable()->index();
                $table->uuid('penyusun_employee_id')->nullable()->index();
                $table->string('nama_penyusun', 150)->nullable();
                $table->string('nama_mapel', 150)->nullable();
                $table->string('jenjang', 50)->index();
                $table->string('klasifikasi', 30)->default('HOTS')->index(); // HOTS, MOTS, LOTS
                $table->string('tingkat_kesukaran', 50)->default('Sedang');
                $table->string('status', 30)->default('menunggu_reviu')->index(); // 'disetujui', 'perlu_revisi', 'menunggu_reviu'
                $table->date('tanggal_reviu')->nullable()->index();
                $table->uuid('kurator_user_id')->nullable()->index();
                $table->string('nama_kurator', 150)->nullable()->default('Divisi Pendidikan');
                $table->text('catatan_kurator')->nullable();
                $table->jsonb('rubrik_kurasi')->nullable();
                $table->string('lampiran_dokumen_url', 255)->nullable();
                $table->jsonb('metadata')->nullable();
                $table->softDeletesTz();
                $table->timestampsTz();

                $table->foreign('education_unit_id')->references('id')->on('education_units')->cascadeOnDelete();
                $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
                $table->foreign('penyusun_employee_id')->references('id')->on('employees')->nullOnDelete();
                $table->foreign('kurator_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['education_unit_id', 'status', 'klasifikasi'], 'kurasi_soals_unit_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kurasi_soals');
    }
};
