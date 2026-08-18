<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 01
 *
 * Masalah: Kolom `jenis_unit_id` tidak ada di tabel `education_units`,
 * menyebabkan relasi JenisUnitPendidikan::unitPendidikan() fatal error.
 *
 * Solusi: Tambah kolom nullable `jenis_unit_id` (uuid) dengan FK ke
 * `master_jenis_unit_pendidikan` menggunakan kolom `uuid` (bukan `id` bigint).
 *
 * Aturan SAFE REFACTOR:
 * - Tidak menghapus/mengubah migration lama.
 * - Kolom baru nullable → data lama tidak rusak.
 * - Tidak ada controller/model yang diubah — relasi model langsung berfungsi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom jenis_unit_id ke education_units (nullable, backward compat)
        Schema::table('education_units', function (Blueprint $table) {
            if (! Schema::hasColumn('education_units', 'jenis_unit_id')) {
                $table->uuid('jenis_unit_id')->nullable()->after('id');
            }
        });

        // Tambah FK setelah kolom dibuat
        // FK ke master_jenis_unit_pendidikan.uuid (bukan .id bigint)
        // karena model JenisUnitPendidikan menggunakan uuid sebagai identifier publik.
        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('master_jenis_unit_pendidikan') && Schema::hasColumn('education_units', 'jenis_unit_id')) {
            DB::statement('
                ALTER TABLE education_units
                ADD CONSTRAINT fk_edu_units_jenis_unit
                FOREIGN KEY (jenis_unit_id)
                REFERENCES master_jenis_unit_pendidikan(uuid)
                ON DELETE SET NULL
                ON UPDATE CASCADE
            ');
        }

        // Fix: education_units menggunakan timestamps() tanpa Tz — tambahkan index is_active
        // (tidak bisa rename kolom per aturan SAFE REFACTOR, hanya tambah index)
        Schema::table('education_units', function (Blueprint $table) {
            if (! Schema::hasColumn('education_units', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('education_units', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        // Hapus FK dulu sebelum hapus kolom
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                ALTER TABLE education_units
                DROP CONSTRAINT IF EXISTS fk_edu_units_jenis_unit
            ');
        }

        Schema::table('education_units', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('education_units', 'jenis_unit_id')) {
                $cols[] = 'jenis_unit_id';
            }
            if (Schema::hasColumn('education_units', 'created_by')) {
                $cols[] = 'created_by';
            }
            if (Schema::hasColumn('education_units', 'updated_by')) {
                $cols[] = 'updated_by';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
