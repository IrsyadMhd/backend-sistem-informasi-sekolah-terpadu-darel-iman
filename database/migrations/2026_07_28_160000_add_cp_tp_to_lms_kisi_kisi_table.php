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
        Schema::table('lms_kisi_kisi', function (Blueprint $table) {
            $table->uuid('cp_id')->nullable()->after('mata_pelajaran_id');
            $table->uuid('tp_id')->nullable()->after('cp_id');

            $table->foreign('cp_id')->references('id')->on('lms_capaian_pembelajaran')->nullOnDelete();
            $table->foreign('tp_id')->references('id')->on('lms_tujuan_pembelajaran')->nullOnDelete();

            $table->index(['cp_id', 'tp_id'], 'lms_kisi_cp_tp_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_kisi_kisi', function (Blueprint $table) {
            $table->dropForeign(['cp_id']);
            $table->dropForeign(['tp_id']);
            $table->dropIndex('lms_kisi_cp_tp_idx');
            $table->dropColumn(['cp_id', 'tp_id']);
        });
    }
};
