<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Santri Worship Attendance System.
     */
    public function up(): void
    {
        // 1. Worship Attendance Templates
        if (! Schema::hasTable('worship_attendance_templates')) {
            Schema::create('worship_attendance_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('nama', 150);
                $table->string('code', 50)->unique();
                $table->string('category', 40)->default('shalat_wajib')->comment('shalat_wajib, shalat_sunnah, ibadah_lain, program_asrama');
                $table->string('obligation_type', 20)->default('wajib')->comment('wajib, sunnah, opsional');
                $table->uuid('education_unit_id')->nullable()->index();
                $table->uuid('dormitory_id')->nullable()->index();
                $table->string('gender_scope', 20)->default('all')->comment('all, male, female');
                $table->string('participant_scope', 50)->default('all_students')->comment('all_students, santri_asrama, tahfizh_only');
                $table->string('time_source', 30)->default('fixed')->comment('fixed, prayer_schedule');
                $table->string('prayer_name', 30)->nullable()->comment('subuh, zuhur, asar, magrib, isya');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->integer('open_offset_minutes')->default(15);
                $table->integer('iqamah_offset_minutes')->nullable()->default(10);
                $table->integer('late_tolerance_minutes')->default(10);
                $table->integer('close_offset_minutes')->default(30);
                $table->json('active_days')->nullable();
                $table->string('location_name', 150)->nullable();
                $table->json('attendance_methods')->nullable()->comment('["qr", "rfid", "face", "checklist"]');
                $table->boolean('verification_required')->default(true);
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('education_unit_id')->references('id')->on('education_units')->nullOnDelete();
            });
        }

        // 2. Worship Attendance Sessions
        if (! Schema::hasTable('worship_attendance_sessions')) {
            Schema::create('worship_attendance_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('template_id')->index();
                $table->date('session_date')->index();
                $table->timestamp('scheduled_start_at')->nullable();
                $table->timestamp('scheduled_end_at')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('location_name', 150)->nullable();
                $table->uuid('supervisor_id')->nullable()->comment('Musyrif / Musyrifah ID');
                $table->string('status', 30)->default('opened')->comment('opened, closed, cancelled, verified');
                $table->boolean('generated_automatically')->default(true);
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->foreign('template_id')->references('id')->on('worship_attendance_templates')->cascadeOnDelete();
                $table->foreign('supervisor_id')->references('id')->on('employees')->nullOnDelete();
                $table->unique(['template_id', 'session_date']);
            });
        }

        // 3. Worship Attendance Details
        if (! Schema::hasTable('worship_attendance_details')) {
            Schema::create('worship_attendance_details', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('session_id')->index();
                $table->uuid('student_id')->index();
                $table->string('attendance_status', 40)->default('belum_diverifikasi')
                    ->comment('hadir_berjamaah, hadir_sendiri, terlambat, tidak_hadir, izin, sakit, uzur_syarii, haid, tugas, piket, safar, dispensasi, perangkat_bermasalah, belum_diverifikasi');
                $table->dateTime('attended_at')->nullable();
                $table->string('method', 30)->default('checklist')->comment('qr, rfid, face, checklist, manual');
                $table->string('device_identifier', 100)->nullable();
                $table->uuid('verified_by')->nullable()->comment('Musyrif / Musyrifah validator ID');
                $table->text('notes')->nullable();
                $table->boolean('is_private')->default(false)->comment('True for female privacy status like haid / uzur_syarii');
                $table->timestamps();

                $table->foreign('session_id')->references('id')->on('worship_attendance_sessions')->cascadeOnDelete();
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
                $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
                $table->unique(['session_id', 'student_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worship_attendance_details');
        Schema::dropIfExists('worship_attendance_sessions');
        Schema::dropIfExists('worship_attendance_templates');
    }
};
