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
        if (! Schema::hasTable('munaqasyah_tahfizhs')) {
            Schema::create('munaqasyah_tahfizhs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('nomor_munaqasyah', 50)->unique()->index();
                $table->string('nomor_syahadah', 100)->nullable()->unique()->index();
                $table->uuid('student_id')->nullable()->index();
                $table->string('nama_santri', 150)->index();
                $table->string('nis', 50)->nullable()->index();
                $table->uuid('education_unit_id')->index();
                $table->uuid('kelas_id')->nullable()->index();
                $table->string('nama_kelas', 100)->nullable();
                $table->string('juz_diuji', 100)->index();
                $table->date('tanggal_ujian')->index();
                $table->uuid('penguji_employee_id')->nullable()->index();
                $table->string('nama_penguji', 150)->nullable();
                $table->integer('nilai_tajwid')->default(0);
                $table->integer('nilai_fashahah')->default(0);
                $table->integer('nilai_kelancaran')->default(0);
                $table->integer('nilai_akhir')->default(0);
                $table->string('predikat', 50)->default('Menunggu Ujian')->index();
                $table->string('status', 30)->default('terjadwal')->index(); // 'lulus', 'terjadwal', 'mengulang'
                $table->text('catatan_dewan_penguji')->nullable();
                $table->uuid('kurator_user_id')->nullable()->index();
                $table->jsonb('metadata')->nullable();
                $table->softDeletesTz();
                $table->timestampsTz();

                $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
                $table->foreign('education_unit_id')->references('id')->on('education_units')->cascadeOnDelete();
                $table->foreign('penguji_employee_id')->references('id')->on('employees')->nullOnDelete();
                $table->foreign('kurator_user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['education_unit_id', 'status', 'juz_diuji'], 'munaqasyah_unit_status_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('munaqasyah_tahfizhs');
    }
};
