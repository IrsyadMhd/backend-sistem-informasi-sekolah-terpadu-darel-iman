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
        Schema::create('radar_akreditasis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id')->index();
            $table->string('kode_audit', 50)->nullable()->index();
            $table->string('peringkat_saat_ini', 60)->default('Belum Terakreditasi');
            $table->smallInteger('skor_akreditasi')->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('habis_berlaku')->nullable();
            $table->string('sisa_waktu', 50)->nullable();
            $table->smallInteger('kesiapan_total')->default(0);
            $table->jsonb('standar_evaluasi')->nullable();
            $table->text('rekomendasi_divisi')->nullable();
            $table->uuid('auditor_id')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('unit_id')->references('id')->on('education_units')->cascadeOnDelete();
            $table->foreign('auditor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radar_akreditasis');
    }
};
