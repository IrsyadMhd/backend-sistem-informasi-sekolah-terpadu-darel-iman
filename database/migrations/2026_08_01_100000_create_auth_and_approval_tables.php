<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: Tidak menambahkan kolom `username` ke tabel `users`.
        // Kolom `username` TIDAK ADA pada skema PostgreSQL yang berjalan;
        // autentikasi memakai `email`/`phone` pada tabel users (lihat AuthService).

        // 1. Add nik to parents if not present
        if (Schema::hasTable('parents') && ! Schema::hasColumn('parents', 'nik')) {
            Schema::table('parents', function (Blueprint $table) {
                $table->string('nik', 32)->nullable()->unique()->index()->after('user_id');
            });
        }

        // 2. Create qr_credentials table for Employee ID Cards & Student Cards
        if (! Schema::hasTable('qr_credentials')) {
            Schema::create('qr_credentials', function (Blueprint $table) {
                if (DB::getDriverName() === 'pgsql') {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->uuid('user_id')->nullable()->index();
                $table->uuid('employee_id')->nullable()->index();
                $table->uuid('student_id')->nullable()->index();
                $table->string('card_type', 30)->comment('employee_card / student_card');
                $table->string('token_hash', 128)->unique()->index();
                $table->string('card_version', 20)->default('v1');
                $table->enum('status', ['active', 'revoked', 'expired'])->default('active')->index();
                $table->timestampTz('issued_at')->useCurrent();
                $table->timestampTz('expires_at')->nullable();
                $table->timestampTz('revoked_at')->nullable();
                $table->timestampTz('last_used_at')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampsTz();

                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            });
        }

        // 3. Create login_events table for login history & security audit
        if (! Schema::hasTable('login_events')) {
            Schema::create('login_events', function (Blueprint $table) {
                if (DB::getDriverName() === 'pgsql') {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->uuid('user_id')->nullable()->index();
                $table->uuid('education_unit_id')->nullable()->index();
                $table->string('portal_type', 30)->comment('admin / employee / parent_student');
                $table->string('identifier_used', 100)->nullable();
                $table->string('login_method', 30)->comment('username_password / niy_password / phone_password / nis_password / nik_password / qr_code');
                $table->enum('status', ['success', 'failed', 'locked'])->default('success')->index();
                $table->string('failure_reason')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('device_id')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampTz('created_at')->useCurrent()->index();

                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // 4. Create user_devices table
        if (! Schema::hasTable('user_devices')) {
            Schema::create('user_devices', function (Blueprint $table) {
                if (DB::getDriverName() === 'pgsql') {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->uuid('user_id');
                $table->string('device_id', 100)->index();
                $table->string('device_name')->nullable();
                $table->string('device_type', 30)->default('web');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->boolean('is_trusted')->default(false);
                $table->timestampTz('last_active_at')->useCurrent();
                $table->timestampsTz();

                $table->unique(['user_id', 'device_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // 5. Create delete_requests table for Admin deletion approval
        if (! Schema::hasTable('delete_requests')) {
            Schema::create('delete_requests', function (Blueprint $table) {
                if (DB::getDriverName() === 'pgsql') {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->string('target_table', 60);
                $table->uuid('target_id');
                $table->string('target_label')->nullable();
                $table->uuid('requested_by')->index();
                $table->uuid('education_unit_id')->nullable()->index();
                $table->text('reason');
                $table->string('attachment_path')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
                $table->uuid('reviewed_by')->nullable()->index();
                $table->timestampTz('reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delete_requests');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('qr_credentials');

        if (Schema::hasTable('parents') && Schema::hasColumn('parents', 'nik')) {
            Schema::table('parents', function (Blueprint $table) {
                $table->dropColumn('nik');
            });
        }
    }
};
