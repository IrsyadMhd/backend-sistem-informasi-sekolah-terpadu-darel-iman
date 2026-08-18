<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration: Fix Modul Semester Cascade Delete → Restrict
 *
 * Masalah (High):
 * `modul_semesters.kelas_id` dan `modul_semesters.mata_pelajaran_id`
 * menggunakan cascadeOnDelete, artinya:
 * - Jika kelas dihapus → SEMUA rencana pembelajaran semester guru hilang permanen
 * - Jika mata pelajaran dihapus → SEMUA rencana pembelajaran semester hilang permanen
 *
 * Ini sangat berbahaya karena modul_semesters berisi:
 * - ATP (Alur Tujuan Pembelajaran)
 * - CP (Capaian Pembelajaran)
 * - Bobot Penilaian
 * - Rencana Pertemuan
 *
 * Solusi:
 * - Ubah FK `kelas_id` dari CASCADE → RESTRICT
 * - Ubah FK `mata_pelajaran_id` dari CASCADE → RESTRICT
 * - Ubah FK `guru_id` dari CASCADE → RESTRICT → SET NULL (guru bisa resign tapi data tetap ada)
 * - Ubah FK `tahun_ajaran_id` dari CASCADE → RESTRICT
 * - Ubah FK `semester_id` dari CASCADE → RESTRICT
 *
 * Aturan SAFE REFACTOR:
 * - Tidak mengubah data
 * - Hanya mengubah behavior ON DELETE
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // SQLite: tidak enforce FK, skip
            return;
        }

        $constraints = [
            // [table, constraint_name, referenced_table, column, new_behavior]
            [
                'table'       => 'modul_semesters',
                'constraint'  => 'modul_semesters_kelas_id_foreign',
                'references'  => 'tbl_kelas',
                'column'      => 'kelas_id',
                'on_delete'   => 'RESTRICT',
            ],
            [
                'table'       => 'modul_semesters',
                'constraint'  => 'modul_semesters_mata_pelajaran_id_foreign',
                'references'  => 'subjects',
                'column'      => 'mata_pelajaran_id',
                'on_delete'   => 'RESTRICT',
            ],
            [
                'table'       => 'modul_semesters',
                'constraint'  => 'modul_semesters_guru_id_foreign',
                'references'  => 'employees',
                'column'      => 'guru_id',
                'on_delete'   => 'SET NULL', // Guru resign tapi data modul tetap ada
            ],
            [
                'table'       => 'modul_semesters',
                'constraint'  => 'modul_semesters_tahun_ajaran_id_foreign',
                'references'  => 'academic_years',
                'column'      => 'tahun_ajaran_id',
                'on_delete'   => 'RESTRICT',
            ],
            [
                'table'       => 'modul_semesters',
                'constraint'  => 'modul_semesters_semester_id_foreign',
                'references'  => 'semesters',
                'column'      => 'semester_id',
                'on_delete'   => 'RESTRICT',
            ],
        ];

        foreach ($constraints as $c) {
            DB::statement("ALTER TABLE {$c['table']} DROP CONSTRAINT IF EXISTS {$c['constraint']}");
            DB::statement("
                ALTER TABLE {$c['table']}
                ADD CONSTRAINT {$c['constraint']}
                FOREIGN KEY ({$c['column']})
                REFERENCES {$c['references']}(id)
                ON DELETE {$c['on_delete']}
                ON UPDATE CASCADE
            ");
        }

        // Jika guru_id diubah ke SET NULL, kolom harus nullable
        // (Sudah nullable di migration awal? Cek dan ensure nullable)
        $cols = DB::select("
            SELECT column_name, is_nullable
            FROM information_schema.columns
            WHERE table_name = 'modul_semesters'
              AND column_name = 'guru_id'
        ");

        if (! empty($cols) && $cols[0]->is_nullable === 'NO') {
            // Ubah kolom menjadi nullable agar SET NULL bisa bekerja
            DB::statement('ALTER TABLE modul_semesters ALTER COLUMN guru_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $constraints = [
            ['modul_semesters', 'modul_semesters_kelas_id_foreign', 'tbl_kelas', 'kelas_id', 'CASCADE'],
            ['modul_semesters', 'modul_semesters_mata_pelajaran_id_foreign', 'subjects', 'mata_pelajaran_id', 'CASCADE'],
            ['modul_semesters', 'modul_semesters_guru_id_foreign', 'employees', 'guru_id', 'CASCADE'],
            ['modul_semesters', 'modul_semesters_tahun_ajaran_id_foreign', 'academic_years', 'tahun_ajaran_id', 'CASCADE'],
            ['modul_semesters', 'modul_semesters_semester_id_foreign', 'semesters', 'semester_id', 'CASCADE'],
        ];

        foreach ($constraints as [$table, $constraint, $references, $column, $onDelete]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            DB::statement("
                ALTER TABLE {$table}
                ADD CONSTRAINT {$constraint}
                FOREIGN KEY ({$column})
                REFERENCES {$references}(id)
                ON DELETE {$onDelete}
                ON UPDATE CASCADE
            ");
        }
    }
};
