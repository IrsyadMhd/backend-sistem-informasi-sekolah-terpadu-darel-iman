<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration: Fix LMS CP & TP Cascade Delete → Restrict
 *
 * Masalah (Critical):
 * 1. `lms_capaian_pembelajaran.kurikulum_id` menggunakan cascadeOnDelete
 *    → Jika kurikulum dihapus (atau soft deleted dan kemudian force deleted),
 *      seluruh CP akan terhapus permanen bersama semua TP dan Modul Ajar turunannya.
 *
 * 2. `lms_tujuan_pembelajaran.cp_id` menggunakan cascadeOnDelete
 *    → Jika CP dihapus, seluruh TP hilang permanen.
 *    → Karena TP CASCADE ke Modul Ajar, maka Modul Ajar juga ikut terhapus.
 *
 * Solusi:
 * - Ubah FK behavior dari CASCADE DELETE → RESTRICT DELETE
 * - Ini memaksa pengguna untuk menghapus turunannya secara eksplisit sebelum hapus induk
 * - Kompatibel dengan soft delete karena soft delete tidak menyentuh FK constraint
 *
 * Catatan PostgreSQL:
 * - Tidak bisa langsung ALTER CONSTRAINT, harus DROP lalu ADD baru
 * - Nama constraint otomatis Laravel harus ditemukan dulu sebelum di-drop
 *
 * Aturan SAFE REFACTOR:
 * - Tidak mengubah data
 * - Tidak mengubah kolom
 * - Hanya mengubah behavior ON DELETE pada FK constraint
 * - Dapat di-rollback
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // 1. lms_capaian_pembelajaran.kurikulum_id: CASCADE → RESTRICT
        // ─────────────────────────────────────────────────────────────────────
        if (DB::getDriverName() === 'pgsql') {
            // Drop existing FK
            DB::statement('
                ALTER TABLE lms_capaian_pembelajaran
                DROP CONSTRAINT IF EXISTS lms_capaian_pembelajaran_kurikulum_id_foreign
            ');
            // Re-add with RESTRICT
            DB::statement('
                ALTER TABLE lms_capaian_pembelajaran
                ADD CONSTRAINT lms_capaian_pembelajaran_kurikulum_id_foreign
                FOREIGN KEY (kurikulum_id)
                REFERENCES master_kurikulum(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
            ');

            // ─────────────────────────────────────────────────────────────────────
            // 2. lms_tujuan_pembelajaran.cp_id: CASCADE → RESTRICT
            // ─────────────────────────────────────────────────────────────────────
            DB::statement('
                ALTER TABLE lms_tujuan_pembelajaran
                DROP CONSTRAINT IF EXISTS lms_tujuan_pembelajaran_cp_id_foreign
            ');
            DB::statement('
                ALTER TABLE lms_tujuan_pembelajaran
                ADD CONSTRAINT lms_tujuan_pembelajaran_cp_id_foreign
                FOREIGN KEY (cp_id)
                REFERENCES lms_capaian_pembelajaran(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
            ');
        }
        // SQLite tidak memiliki FK enforcement real, skip untuk dev environment
        // Behavior akan diterapkan saat deploy ke PostgreSQL production
    }

    public function down(): void
    {
        // Rollback: kembalikan ke CASCADE (perilaku lama)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                ALTER TABLE lms_capaian_pembelajaran
                DROP CONSTRAINT IF EXISTS lms_capaian_pembelajaran_kurikulum_id_foreign
            ');
            DB::statement('
                ALTER TABLE lms_capaian_pembelajaran
                ADD CONSTRAINT lms_capaian_pembelajaran_kurikulum_id_foreign
                FOREIGN KEY (kurikulum_id)
                REFERENCES master_kurikulum(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ');

            DB::statement('
                ALTER TABLE lms_tujuan_pembelajaran
                DROP CONSTRAINT IF EXISTS lms_tujuan_pembelajaran_cp_id_foreign
            ');
            DB::statement('
                ALTER TABLE lms_tujuan_pembelajaran
                ADD CONSTRAINT lms_tujuan_pembelajaran_cp_id_foreign
                FOREIGN KEY (cp_id)
                REFERENCES lms_capaian_pembelajaran(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ');
        }
    }
};
