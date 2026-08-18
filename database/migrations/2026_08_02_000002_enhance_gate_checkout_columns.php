<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to enhance gate attendance check-out & pickup info.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'check_out_status')) {
                $table->string('check_out_status', 30)->nullable()->after('status')->comment('pulang_normal, pulang_lebih_awal, pulang_terlambat, tidak_ada_absensi_masuk, perlu_verifikasi');
            }
            if (! Schema::hasColumn('attendances', 'check_out_method')) {
                $table->string('check_out_method', 30)->nullable()->after('attendance_method');
            }
            if (! Schema::hasColumn('attendances', 'checkout_device_id')) {
                $table->string('checkout_device_id', 100)->nullable();
            }
            if (! Schema::hasColumn('attendances', 'pickup_person')) {
                $table->string('pickup_person', 150)->nullable();
            }
            if (! Schema::hasColumn('attendances', 'pickup_relation')) {
                $table->string('pickup_relation', 100)->nullable();
            }
            if (! Schema::hasColumn('attendances', 'pickup_verification')) {
                $table->string('pickup_verification', 100)->nullable();
            }
            if (! Schema::hasColumn('attendances', 'photo_snapshot')) {
                $table->string('photo_snapshot', 255)->nullable();
            }
            if (! Schema::hasColumn('attendances', 'approved_by')) {
                $table->uuid('approved_by')->nullable();
            }
            if (! Schema::hasColumn('attendances', 'verified_by')) {
                $table->uuid('verified_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'check_out_status',
                'check_out_method',
                'checkout_device_id',
                'pickup_person',
                'pickup_relation',
                'pickup_verification',
                'photo_snapshot',
                'approved_by',
                'verified_by',
            ]);
        });
    }
};
