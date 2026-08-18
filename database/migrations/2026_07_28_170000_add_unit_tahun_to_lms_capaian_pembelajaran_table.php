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
        Schema::table('lms_capaian_pembelajaran', function (Blueprint $table) {
            if (! Schema::hasColumn('lms_capaian_pembelajaran', 'unit_pendidikan_id')) {
                $table->uuid('unit_pendidikan_id')->nullable()->after('id');
                $table->foreign('unit_pendidikan_id')
                    ->references('id')
                    ->on('education_units')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lms_capaian_pembelajaran', 'tahun_ajaran_id')) {
                $table->uuid('tahun_ajaran_id')->nullable()->after('unit_pendidikan_id');
                $table->foreign('tahun_ajaran_id')
                    ->references('id')
                    ->on('academic_years')
                    ->nullOnDelete();
            }

            $table->index(
                ['unit_pendidikan_id', 'tahun_ajaran_id', 'kurikulum_id', 'mata_pelajaran_id', 'status'],
                'lms_cp_full_filter_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_capaian_pembelajaran', function (Blueprint $table) {
            $table->dropIndex('lms_cp_full_filter_idx');

            if (Schema::hasColumn('lms_capaian_pembelajaran', 'tahun_ajaran_id')) {
                $table->dropForeign(['tahun_ajaran_id']);
                $table->dropColumn('tahun_ajaran_id');
            }

            if (Schema::hasColumn('lms_capaian_pembelajaran', 'unit_pendidikan_id')) {
                $table->dropForeign(['unit_pendidikan_id']);
                $table->dropColumn('unit_pendidikan_id');
            }
        });
    }
};
