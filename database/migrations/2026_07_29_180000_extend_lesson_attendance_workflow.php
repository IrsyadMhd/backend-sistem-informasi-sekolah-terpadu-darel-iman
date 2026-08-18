<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_attendance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('schedule_id');
            $table->date('attendance_date');
            $table->unsignedInteger('meeting_number')->default(1);
            $table->uuid('learning_module_id')->nullable();
            $table->text('learning_material')->nullable();
            $table->text('learning_activity')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->uuid('finalized_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['schedule_id', 'attendance_date']);
            $table->foreign('schedule_id')->references('id')->on('class_schedules')->restrictOnDelete();
        });

        Schema::table('lms_presensi', function (Blueprint $table) {
            $table->uuid('session_id')->nullable()->after('id');
            $table->time('arrival_time')->nullable()->after('waktu_presensi');
            $table->string('verification_status', 30)->default('unverified')->after('arrival_time');
            $table->foreign('session_id')->references('id')->on('lesson_attendance_sessions')->nullOnDelete();
            $table->index('session_id');
        });

        Schema::create('student_attendance_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type', 10);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft');
            $table->text('review_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->index(['student_id', 'start_date', 'end_date']);
        });

        Schema::create('lesson_attendance_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_id');
            $table->string('previous_status', 30);
            $table->string('proposed_status', 30);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->json('before_data');
            $table->json('after_data');
            $table->uuid('requested_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->foreign('attendance_id')->references('id')->on('lms_presensi')->restrictOnDelete();
            $table->index(['attendance_id', 'status']);
        });

        Schema::create('homeroom_attendance_follow_ups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('class_id');
            $table->uuid('student_id');
            $table->string('case_type', 80);
            $table->date('case_date');
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->string('priority', 20);
            $table->text('action');
            $table->text('parent_communication')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->uuid('created_by');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('class_id')->references('id')->on('tbl_kelas')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->index(['class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_attendance_follow_ups');
        Schema::dropIfExists('lesson_attendance_corrections');
        Schema::dropIfExists('student_attendance_permissions');
        Schema::table('lms_presensi', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
            $table->dropColumn(['session_id', 'arrival_time', 'verification_status']);
        });
        Schema::dropIfExists('lesson_attendance_sessions');
    }
};
