<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah kolom atasan_pegawai_id (FK ke employees) di tabel positions.
     * Kolom ini menyimpan referensi pegawai sebagai atasan langsung/pelaporan,
     * berbeda dengan atasan_langsung_id yang merujuk ke jabatan (Position).
     */
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            // Tambah kolom baru atasan_pegawai_id (nullable FK ke employees)
            $table->uuid('atasan_pegawai_id')->nullable()->after('atasan_langsung_id');

            $table->foreign('atasan_pegawai_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    /**
     * Rollback — hapus kolom atasan_pegawai_id.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['atasan_pegawai_id']);
            $table->dropColumn('atasan_pegawai_id');
        });
    }
};
