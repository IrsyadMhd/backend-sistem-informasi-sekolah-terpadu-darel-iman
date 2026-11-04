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
        Schema::table('laporan_bulanans', function (Blueprint $table) {
            if (!Schema::hasColumn('laporan_bulanans', 'unit_id')) {
                $table->uuid('unit_id')->nullable()->after('id_semester')->index();
                $table->foreign('unit_id')->references('id')->on('education_units')->nullOnDelete();
            }
            if (!Schema::hasColumn('laporan_bulanans', 'kode_laporan')) {
                $table->string('kode_laporan', 50)->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('laporan_bulanans', 'periode')) {
                $table->string('periode', 50)->nullable()->after('tahun');
            }
            if (!Schema::hasColumn('laporan_bulanans', 'kategori_laporan')) {
                $table->string('kategori_laporan', 100)->nullable()->default('Laporan Kinerja Manajerial');
            }
            if (!Schema::hasColumn('laporan_bulanans', 'catatan_divisi')) {
                $table->text('catatan_divisi')->nullable();
            }
            if (!Schema::hasColumn('laporan_bulanans', 'tanggal_kirim')) {
                $table->date('tanggal_kirim')->nullable();
            }
            if (!Schema::hasColumn('laporan_bulanans', 'persentase_kelengkapan')) {
                $table->smallInteger('persentase_kelengkapan')->nullable()->default(100);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporan_bulanans', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn([
                'unit_id',
                'kode_laporan',
                'periode',
                'kategori_laporan',
                'catatan_divisi',
                'tanggal_kirim',
                'persentase_kelengkapan',
            ]);
        });
    }
};
