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
        if (! Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tipe_presensi', 20)->default('Siswa')->comment('Siswa / Pegawai');
                $table->uuid('student_id')->nullable()->index();
                $table->uuid('employee_id')->nullable()->index();
                $table->uuid('class_id')->nullable()->index();
                $table->uuid('unit_pendidikan_id')->nullable()->index();
                $table->uuid('academic_year_id')->nullable();
                $table->uuid('semester_id')->nullable();
                $table->integer('month')->nullable();
                $table->date('attendance_date')->index();
                $table->dateTime('check_in_time')->nullable();
                $table->dateTime('check_out_time')->nullable();
                $table->enum('status', ['HADIR', 'TERLAMBAT', 'SAKIT', 'IZIN', 'ALPHA', 'DINAS_LUAR'])->default('HADIR')->index();
                $table->string('attendance_method', 30)->default('MANUAL')->comment('QRCODE, GEOLOCATION, MANUAL, FACE');
                $table->string('location')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('attachment_path')->nullable();
                $table->text('keterangan')->nullable();
                $table->json('metadata')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
