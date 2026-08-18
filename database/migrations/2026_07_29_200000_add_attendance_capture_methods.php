<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_attendance_sessions', function (Blueprint $table) {
            $table->string('attendance_method', 30)->default('manual');
            $table->string('session_token_hash', 64)->nullable()->unique();
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamp('session_closed_at')->nullable();
            $table->uuid('device_id')->nullable();
            $table->string('scan_location')->nullable();
            $table->json('metadata')->nullable();
        });

        Schema::table('lms_presensi', function (Blueprint $table) {
            $table->string('recorded_method', 30)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->uuid('scan_log_id')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('device_identifier')->nullable();
            $table->json('capture_metadata')->nullable();
        });

        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_code')->unique();
            $table->string('device_name');
            $table->string('device_type', 30);
            $table->string('vendor')->nullable();
            $table->uuid('unit_id')->nullable();
            $table->string('location')->nullable();
            $table->string('api_key_hash', 64)->nullable();
            $table->string('status', 20)->default('inactive');
            $table->timestamp('last_seen_at')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_scan_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lesson_attendance_id');
            $table->uuid('student_id')->nullable();
            $table->uuid('class_schedule_id');
            $table->string('scan_method', 30);
            $table->string('raw_identifier')->nullable();
            $table->string('hashed_identifier', 64)->nullable();
            $table->uuid('device_id')->nullable();
            $table->timestamp('scanned_at');
            $table->string('result_status', 40);
            $table->text('failure_reason')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->ipAddress('request_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('lesson_attendance_id')->references('id')->on('lesson_attendance_sessions')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            $table->foreign('class_schedule_id')->references('id')->on('class_schedules')->restrictOnDelete();
            $table->foreign('device_id')->references('id')->on('attendance_devices')->nullOnDelete();
            $table->index(['lesson_attendance_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_scan_logs');
        Schema::dropIfExists('attendance_devices');
        Schema::table('lms_presensi', fn (Blueprint $table) => $table->dropColumn([
            'recorded_method', 'recorded_at', 'recorded_by', 'scan_log_id', 'confidence_score', 'device_identifier', 'capture_metadata',
        ]));
        Schema::table('lesson_attendance_sessions', fn (Blueprint $table) => $table->dropColumn([
            'attendance_method', 'session_token_hash', 'session_started_at', 'session_expires_at', 'session_closed_at', 'device_id', 'scan_location', 'metadata',
        ]));
    }
};
