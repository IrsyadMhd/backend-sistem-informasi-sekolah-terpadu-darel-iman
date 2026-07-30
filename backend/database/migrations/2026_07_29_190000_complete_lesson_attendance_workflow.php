<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_attendance_sessions', function (Blueprint $table) {
            $table->string('topic')->nullable()->after('learning_activity');
            $table->uuid('learning_material_id')->nullable()->after('learning_module_id');
            $table->uuid('learning_activity_id')->nullable()->after('learning_material_id');
        });

        Schema::table('student_attendance_permissions', function (Blueprint $table) {
            $table->uuid('academic_year_id')->nullable()->after('student_id');
            $table->uuid('semester_id')->nullable()->after('academic_year_id');
            $table->uuid('class_id')->nullable()->after('semester_id');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->uuid('updated_by')->nullable()->after('created_by');
        });

        Schema::table('lesson_attendance_corrections', function (Blueprint $table) {
            $table->uuid('reviewed_by')->nullable()->after('approved_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });

        Schema::table('homeroom_attendance_follow_ups', function (Blueprint $table) {
            $table->uuid('homeroom_teacher_id')->nullable()->after('student_id');
            $table->uuid('updated_by')->nullable()->after('created_by');
        });

        Schema::create('attendance_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('action', 80);
            $table->string('module', 80)->default('attendance');
            $table->string('reference_type');
            $table->uuid('reference_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
            $table->index(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE lesson_attendance_sessions ADD CONSTRAINT lesson_attendance_sessions_status_check CHECK (status IN ('draft','final','locked','revised','cancelled'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE lesson_attendance_sessions DROP CONSTRAINT IF EXISTS lesson_attendance_sessions_status_check');
        }
        Schema::dropIfExists('attendance_audit_logs');
        Schema::table('homeroom_attendance_follow_ups', fn (Blueprint $table) => $table->dropColumn(['homeroom_teacher_id', 'updated_by']));
        Schema::table('lesson_attendance_corrections', fn (Blueprint $table) => $table->dropColumn(['reviewed_by', 'reviewed_at', 'review_notes']));
        Schema::table('student_attendance_permissions', fn (Blueprint $table) => $table->dropColumn(['academic_year_id', 'semester_id', 'class_id', 'submitted_at', 'updated_by']));
        Schema::table('lesson_attendance_sessions', fn (Blueprint $table) => $table->dropColumn(['topic', 'learning_material_id', 'learning_activity_id']));
    }
};
