<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dormitory_permits')) {
            Schema::table('dormitory_permits', function (Blueprint $table) {
                // 1. Tambahan status terpisah (Keberadaan fisik & tenggat waktu)
                if (! Schema::hasColumn('dormitory_permits', 'presence_status')) {
                    $table->string('presence_status', 30)->default('di_pondok')->index(); // di_pondok, di_luar, berobat, pulang
                }
                if (! Schema::hasColumn('dormitory_permits', 'deadline_status')) {
                    $table->string('deadline_status', 30)->default('on_time')->index(); // on_time, near_deadline, overdue
                }

                // 2. Kategori Medis / Sakit & Darurat
                if (! Schema::hasColumn('dormitory_permits', 'is_emergency')) {
                    $table->boolean('is_emergency')->default(false)->index();
                }
                if (! Schema::hasColumn('dormitory_permits', 'medical_complaint')) {
                    $table->text('medical_complaint')->nullable();
                }
                if (! Schema::hasColumn('dormitory_permits', 'medical_destination')) {
                    $table->string('medical_destination', 255)->nullable();
                }
                if (! Schema::hasColumn('dormitory_permits', 'medical_escort_name')) {
                    $table->string('medical_escort_name', 150)->nullable();
                }

                // 3. Alur Perpanjangan Waktu Izin
                if (! Schema::hasColumn('dormitory_permits', 'original_scheduled_return_at')) {
                    $table->dateTime('original_scheduled_return_at')->nullable();
                }
                if (! Schema::hasColumn('dormitory_permits', 'extension_count')) {
                    $table->unsignedSmallInteger('extension_count')->default(0);
                }
                if (! Schema::hasColumn('dormitory_permits', 'extension_status')) {
                    $table->string('extension_status', 30)->default('none')->index(); // none, requested, approved, rejected
                }
                if (! Schema::hasColumn('dormitory_permits', 'extension_reason')) {
                    $table->text('extension_reason')->nullable();
                }
                if (! Schema::hasColumn('dormitory_permits', 'extension_requested_return_at')) {
                    $table->dateTime('extension_requested_return_at')->nullable();
                }
                if (! Schema::hasColumn('dormitory_permits', 'extension_approved_by')) {
                    $table->uuid('extension_approved_by')->nullable();
                }
                if (! Schema::hasColumn('dormitory_permits', 'extension_approved_at')) {
                    $table->dateTime('extension_approved_at')->nullable();
                }

                // 4. Pengaju izin (wali/santri)
                if (! Schema::hasColumn('dormitory_permits', 'applied_by_user_id')) {
                    $table->uuid('applied_by_user_id')->nullable()->index();
                }
                if (! Schema::hasColumn('dormitory_permits', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dormitory_permits')) {
            Schema::table('dormitory_permits', function (Blueprint $table) {
                $columns = [
                    'presence_status',
                    'deadline_status',
                    'is_emergency',
                    'medical_complaint',
                    'medical_destination',
                    'medical_escort_name',
                    'original_scheduled_return_at',
                    'extension_count',
                    'extension_status',
                    'extension_reason',
                    'extension_requested_return_at',
                    'extension_approved_by',
                    'extension_approved_at',
                    'applied_by_user_id',
                    'rejection_reason',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('dormitory_permits', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
