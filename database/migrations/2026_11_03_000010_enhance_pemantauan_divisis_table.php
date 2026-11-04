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
        Schema::table('pemantauan_divisis', function (Blueprint $table) {
            if (!Schema::hasColumn('pemantauan_divisis', 'unit_id')) {
                $table->uuid('unit_id')->nullable()->index();
                $table->foreign('unit_id')->references('id')->on('education_units')->nullOnDelete();
            }
            if (!Schema::hasColumn('pemantauan_divisis', 'unit_pendidikan')) {
                $table->string('unit_pendidikan', 120)->nullable()->index();
            }
            if (!Schema::hasColumn('pemantauan_divisis', 'kategori_laporan')) {
                $table->string('kategori_laporan', 100)->nullable();
            }
            if (!Schema::hasColumn('pemantauan_divisis', 'petugas_supervisi')) {
                $table->string('petugas_supervisi', 120)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pemantauan_divisis', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn([
                'unit_id',
                'unit_pendidikan',
                'kategori_laporan',
                'petugas_supervisi',
            ]);
        });
    }
};
