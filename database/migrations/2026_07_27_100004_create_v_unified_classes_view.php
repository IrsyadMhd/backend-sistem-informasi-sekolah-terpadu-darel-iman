<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SAFE REFACTOR — Migration 04
 *
 * Masalah: Dua tabel Kelas (`classes` dan `tbl_kelas`) tidak terintegrasi.
 * Query tidak bisa join, data tersebar.
 *
 * Solusi: PostgreSQL VIEW `v_unified_classes` sebagai Bridge/Adapter Layer.
 * Kedua tabel tetap ada dan writable — View hanya untuk READ.
 *
 * Pemetaan field:
 *   tbl_kelas → sumber primer (data lebih lengkap)
 *   classes   → sumber sekunder/legacy (ditampilkan untuk backward compat)
 *
 * Aturan SAFE REFACTOR:
 * - Tabel `classes` dan `tbl_kelas` TIDAK diubah.
 * - Model `Kelas` dan `SchoolClass` TIDAK diubah.
 * - View hanya dibaca oleh query analytics/report, bukan CRUD.
 *
 * Keputusan desain: `classes` di-FREEZE sebagai read-only legacy.
 * Semua operasi CRUD kelas baru menggunakan `tbl_kelas` (via KelasController).
 * ClassController yang membaca `classes` tetap berfungsi untuk backward compat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Drop view dulu jika sudah ada (idempotent)
            DB::statement('DROP VIEW IF EXISTS v_unified_classes');

            DB::statement("
                CREATE VIEW v_unified_classes AS
                SELECT
                    tk.id,
                    'tbl_kelas'::VARCHAR(20)        AS source_table,
                    tk.unit_pendidikan_id,
                    tk.tahun_ajaran_id,
                    tk.semester_id,
                    tk.jenjang,
                    tk.tingkat,
                    tk.kode_kelas                   AS code,
                    tk.nama_kelas                   AS name,
                    tk.wali_kelas_id                AS homeroom_employee_id,
                    NULL::UUID                      AS homeroom_teacher_id,
                    tk.kapasitas                    AS capacity,
                    tk.ruangan                      AS room,
                    tk.status,
                    tk.yayasan_id,
                    tk.created_at,
                    tk.updated_at,
                    tk.deleted_at
                FROM tbl_kelas tk

                UNION ALL

                SELECT
                    c.id,
                    'classes'::VARCHAR(20)          AS source_table,
                    NULL::UUID                      AS unit_pendidikan_id,
                    c.academic_year_id              AS tahun_ajaran_id,
                    c.semester_id,
                    c.level                         AS jenjang,
                    c.level                         AS tingkat,
                    c.name                          AS code,
                    c.name                          AS name,
                    NULL::UUID                      AS homeroom_employee_id,
                    c.homeroom_teacher_id,
                    cr.capacity                     AS capacity,
                    cr.name::VARCHAR(50)            AS room,
                    'Aktif'::VARCHAR(20)            AS status,
                    NULL::UUID                      AS yayasan_id,
                    c.created_at,
                    c.updated_at,
                    c.deleted_at
                FROM classes c
                LEFT JOIN classrooms cr ON c.classroom_id = cr.id
                WHERE c.id NOT IN (SELECT id FROM tbl_kelas WHERE deleted_at IS NULL)
            ");

            // View kedua: hanya data aktif (tidak ter-soft-delete)
            DB::statement('DROP VIEW IF EXISTS v_active_classes');
            DB::statement("
                CREATE VIEW v_active_classes AS
                SELECT * FROM v_unified_classes
                WHERE deleted_at IS NULL
                  AND status = 'Aktif'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS v_active_classes');
            DB::statement('DROP VIEW IF EXISTS v_unified_classes');
        }
    }
};
