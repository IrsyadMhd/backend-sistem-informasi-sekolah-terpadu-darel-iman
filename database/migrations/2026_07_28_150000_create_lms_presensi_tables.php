<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LMS Presensi Pembelajaran Tables
 *
 * Tabel lms_presensi mencatat presensi harian per jadwal pelajaran.
 * Relasi:
 * - class_schedules (1:N lms_presensi via jadwal_pelajaran_id)
 * - students (1:N lms_presensi via siswa_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        Schema::create('lms_presensi', function (Blueprint $table) use ($isPgsql) {
            $table->uuid('id')->primary();

            $table->uuid('jadwal_pelajaran_id')->comment('FK ke class_schedules');
            $table->uuid('siswa_id')->comment('FK ke students');

            $table->date('tanggal');
            $table->string('status_hadir', 20)->default('hadir')
                ->comment('hadir, izin, sakit, alpa, terlambat');
            $table->text('keterangan')->nullable();
            $table->integer('pertemuan_ke')->default(1);
            $table->timestamp('waktu_presensi')->nullable();

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

            // Unique index per jadwal, siswa, dan tanggal
            $table->unique(['jadwal_pelajaran_id', 'siswa_id', 'tanggal'], 'lms_presensi_schedule_student_date_unique');

            // Indexes for fast lookup
            $table->index(['jadwal_pelajaran_id', 'tanggal'], 'lms_presensi_jadwal_tanggal_idx');
            $table->index(['siswa_id', 'status_hadir'], 'lms_presensi_siswa_status_idx');
            $table->index(['tanggal'], 'lms_presensi_tanggal_idx');

            // Foreign Keys
            $table->foreign('jadwal_pelajaran_id')->references('id')->on('class_schedules')->cascadeOnDelete();
            $table->foreign('siswa_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_presensi');
    }
};
