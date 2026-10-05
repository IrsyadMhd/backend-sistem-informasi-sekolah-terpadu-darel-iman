<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_mutations')) {
            Schema::create('student_mutations', function (Blueprint $table) {
                if (DB::getDriverName() === 'pgsql') {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }

                $table->uuid('student_id')->index();
                $table->uuid('unit_asal_id')->index();
                $table->uuid('unit_tujuan_id')->nullable()->index();
                $table->uuid('kelas_asal_id')->nullable()->index();
                $table->uuid('kelas_tujuan_id')->nullable()->index();

                $table->string('jenis_mutasi', 50)->default('pindah_unit_internal')->index()
                    ->comment('pindah_unit_internal / pindah_keluar / pindah_masuk / berhenti');

                $table->string('sekolah_tujuan', 180)->nullable();
                $table->string('sekolah_asal', 180)->nullable();
                $table->text('alasan')->nullable();
                $table->text('catatan_penolakan')->nullable();

                $table->enum('status', ['menunggu_persetujuan', 'disetujui', 'ditolak', 'dibatalkan'])
                    ->default('menunggu_persetujuan')->index();

                $table->uuid('diajukan_oleh')->nullable()->index();
                $table->timestampTz('diajukan_pada')->useCurrent();
                $table->uuid('disetujui_oleh')->nullable()->index();
                $table->timestampTz('disetujui_pada')->nullable();

                $table->jsonb('metadata')->nullable();
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
                $table->foreign('unit_asal_id')->references('id')->on('education_units')->cascadeOnDelete();
                $table->foreign('unit_tujuan_id')->references('id')->on('education_units')->nullOnDelete();
                $table->foreign('diajukan_oleh')->references('id')->on('users')->nullOnDelete();
                $table->foreign('disetujui_oleh')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_mutations');
    }
};
