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
        if (! Schema::hasTable('supervisi_kbms')) {
            Schema::create('supervisi_kbms', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('guru_id')->index();
                $table->uuid('unit_id')->index();
                $table->string('mapel', 150)->index();
                $table->string('kelas', 100)->nullable();
                $table->date('tanggal')->index();
                $table->string('supervisor', 150)->default('Divisi Pendidikan');
                $table->string('status', 30)->default('selesai')->index(); // 'selesai', 'terjadwal', 'pendampingan'
                $table->integer('skor_total')->default(0);
                $table->jsonb('rubrik')->nullable();
                $table->text('apresiasi')->nullable();
                $table->text('rekomendasi')->nullable();
                $table->uuid('id_penginput')->nullable()->index();
                $table->jsonb('data_tambahan')->nullable();
                $table->softDeletesTz();
                $table->timestampsTz();

                $table->foreign('guru_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('unit_id')->references('id')->on('education_units')->cascadeOnDelete();
                $table->foreign('id_penginput')->references('id')->on('users')->nullOnDelete();
                $table->index(['unit_id', 'status', 'tanggal'], 'supervisi_kbms_lookup_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisi_kbms');
    }
};
