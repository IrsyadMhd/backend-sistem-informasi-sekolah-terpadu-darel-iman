<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teaching_attendances')) {
            Schema::create('teaching_attendances', function (Blueprint $table): void {
                if (DB::getDriverName() === 'pgsql') {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }

                $table->uuid('schedule_id');
                $table->uuid('employee_id');
                $table->uuid('education_unit_id');
                $table->uuid('academic_year_id');
                $table->uuid('semester_id');
                $table->date('attendance_date');
                $table->timestampTz('check_in_at');
                $table->string('status', 20)->default('hadir');
                $table->string('attendance_method', 30)->default('qr_card');
                $table->uuid('qr_credential_id')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampsTz();

                $table->unique(['schedule_id', 'attendance_date'], 'teaching_attendance_schedule_date_unique');
                $table->index(['employee_id', 'attendance_date'], 'teaching_attendance_employee_date_idx');
                $table->index(['education_unit_id', 'attendance_date'], 'teaching_attendance_unit_date_idx');

                $table->foreign('schedule_id')->references('id')->on('class_schedules')->restrictOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
                $table->foreign('education_unit_id')->references('id')->on('education_units')->restrictOnDelete();
                $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
                $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
                $table->foreign('qr_credential_id')->references('id')->on('qr_credentials')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE teaching_attendances ADD CONSTRAINT teaching_attendances_status_check CHECK (status IN ('hadir', 'terlambat'))");
            }
        }

        if (Schema::hasTable('lesson_attendance_sessions')) {
            Schema::table('lesson_attendance_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('lesson_attendance_sessions', 'teaching_attendance_id')) {
                    $table->uuid('teaching_attendance_id')->nullable();
                    $table->foreign('teaching_attendance_id')
                        ->references('id')
                        ->on('teaching_attendances')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('lesson_attendance_sessions', 'teaching_session_status')) {
                    $table->string('teaching_session_status', 20)->nullable();
                }
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'lesson_sessions_teaching_status_check') THEN ALTER TABLE lesson_attendance_sessions ADD CONSTRAINT lesson_sessions_teaching_status_check CHECK (teaching_session_status IS NULL OR teaching_session_status IN ('scheduled', 'ready', 'active', 'completed', 'cancelled')); END IF; END $$");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('lesson_attendance_sessions')) {
            DB::statement('ALTER TABLE lesson_attendance_sessions DROP CONSTRAINT IF EXISTS lesson_sessions_teaching_status_check');
        }

        if (Schema::hasTable('lesson_attendance_sessions')) {
            Schema::table('lesson_attendance_sessions', function (Blueprint $table): void {
                if (Schema::hasColumn('lesson_attendance_sessions', 'teaching_attendance_id')) {
                    $table->dropForeign(['teaching_attendance_id']);
                    $table->dropColumn('teaching_attendance_id');
                }
                if (Schema::hasColumn('lesson_attendance_sessions', 'teaching_session_status')) {
                    $table->dropColumn('teaching_session_status');
                }
            });
        }

        Schema::dropIfExists('teaching_attendances');
    }
};
