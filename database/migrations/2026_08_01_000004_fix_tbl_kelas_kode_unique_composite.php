<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration: Fix tbl_kelas kode_kelas Unique Constraint
 *
 * Masalah (Medium):
 * `tbl_kelas.kode_kelas` memiliki UNIQUE constraint global.
 * Ini berarti kode "7-A" tidak bisa ada di SDIT dan SMPIT sekaligus,
 * padahal keduanya adalah unit pendidikan yang berbeda.
 *
 * Kondisi saat ini (SQLite dev): UNIQUE per kolom sudah dibuat
 * Kondisi target: UNIQUE composite (unit_pendidikan_id, tahun_ajaran_id, kode_kelas)
 *
 * Langkah aman:
 * 1. Audit apakah ada data duplikat (jika ada, skip ubah constraint)
 * 2. Drop unique index lama pada kode_kelas
 * 3. Tambah composite unique index baru
 *
 * Aturan SAFE REFACTOR:
 * - Cek duplikat sebelum menambah constraint baru
 * - Jika ada duplikat, tidak menambah constraint (aman)
 * - Dapat di-rollback
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Audit data duplikat
        $duplicates = DB::table('tbl_kelas')
            ->whereNull('deleted_at')
            ->select('unit_pendidikan_id', 'tahun_ajaran_id', 'kode_kelas', DB::raw('count(*) as cnt'))
            ->groupBy('unit_pendidikan_id', 'tahun_ajaran_id', 'kode_kelas')
            ->havingRaw('count(*) > 1')
            ->count();

        if ($duplicates > 0) {
            // Ada data duplikat — tidak aman menambah unique constraint
            // Log warning tapi jangan throw exception (migration tetap pass)
            logger()->warning(
                "[Fix tbl_kelas unique] Ditemukan {$duplicates} grup duplikat kode_kelas. " .
                'Composite unique constraint TIDAK ditambahkan. ' .
                'Bersihkan duplikat terlebih dahulu sebelum jalankan migration ini ulang.'
            );

            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            // Drop unique constraint lama (di PG index dibungkus constraint,
            // sehingga harus drop CONSTRAINT bukan index)
            DB::statement('ALTER TABLE tbl_kelas DROP CONSTRAINT IF EXISTS tbl_kelas_kode_kelas_unique');

            // Tambah composite unique index baru
            $exists = DB::selectOne("
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'tbl_kelas'
                  AND indexname = 'tbl_kelas_unit_tahun_kode_unique'
            ");

            if (! $exists) {
                DB::statement('
                    CREATE UNIQUE INDEX tbl_kelas_unit_tahun_kode_unique
                    ON tbl_kelas (unit_pendidikan_id, tahun_ajaran_id, kode_kelas)
                    WHERE deleted_at IS NULL
                ');
            }
        } else {
            // SQLite: Buat index baru (SQLite tidak support partial index dengan UNIQUE)
            $existingIndexes = collect(DB::select("PRAGMA index_list('tbl_kelas')"))->pluck('name')->toArray();

            if (in_array('tbl_kelas_kode_kelas_unique', $existingIndexes)) {
                DB::statement('DROP INDEX IF EXISTS tbl_kelas_kode_kelas_unique');
            }

            if (! in_array('tbl_kelas_unit_tahun_kode_unique', $existingIndexes)) {
                DB::statement('
                    CREATE UNIQUE INDEX tbl_kelas_unit_tahun_kode_unique
                    ON tbl_kelas (unit_pendidikan_id, tahun_ajaran_id, kode_kelas)
                ');
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Hapus composite index
            DB::statement('DROP INDEX IF EXISTS tbl_kelas_unit_tahun_kode_unique');

            // Kembalikan simple unique index
            $exists = DB::selectOne("
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'tbl_kelas'
                  AND indexname = 'tbl_kelas_kode_kelas_unique'
            ");
            if (! $exists) {
                DB::statement('
                    CREATE UNIQUE INDEX tbl_kelas_kode_kelas_unique
                    ON tbl_kelas (kode_kelas)
                    WHERE deleted_at IS NULL
                ');
            }
        } else {
            DB::statement('DROP INDEX IF EXISTS tbl_kelas_unit_tahun_kode_unique');

            $existingIndexes = collect(DB::select("PRAGMA index_list('tbl_kelas')"))->pluck('name')->toArray();
            if (! in_array('tbl_kelas_kode_kelas_unique', $existingIndexes)) {
                DB::statement('CREATE UNIQUE INDEX tbl_kelas_kode_kelas_unique ON tbl_kelas (kode_kelas)');
            }
        }
    }
};
