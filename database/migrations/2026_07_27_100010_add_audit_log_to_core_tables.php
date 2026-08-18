<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 10
 *
 * Masalah: Tabel core penting tidak memiliki audit log (created_by, updated_by).
 *
 * Solusi: Tambah created_by dan updated_by (UUID, nullable) ke:
 * - academic_years
 * - semesters
 * - subjects
 * - parents
 *
 * Catatan: Tidak ditambahkan FK ke users untuk menghindari dependency
 * yang bisa menyebabkan issue jika user dihapus. Cukup UUID plain.
 *
 * Aturan SAFE REFACTOR:
 * - Semua kolom nullable — data lama tidak rusak.
 * - Tidak ada controller yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = ['academic_years', 'semesters', 'subjects', 'parents'];

        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (! Schema::hasColumn($tbl, 'created_by')) {
                    $table->uuid('created_by')->nullable()->after('metadata');
                }
                if (! Schema::hasColumn($tbl, 'updated_by')) {
                    $table->uuid('updated_by')->nullable()->after('created_by');
                }
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            // Tambahkan partial unique index untuk academic_years: maks 1 aktif
            // Ini constraint yang aman — tidak break data lama, hanya cegah tambah baru
            $existingIdx = DB::select("
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'academic_years'
                  AND indexname = 'uniq_one_active_academic_year'
            ");
            if (empty($existingIdx)) {
                DB::statement('
                    CREATE UNIQUE INDEX uniq_one_active_academic_year
                    ON academic_years (is_active)
                    WHERE is_active = true AND deleted_at IS NULL
                ');
            }

            // Partial unique index untuk semesters: maks 1 semester aktif per tahun ajaran
            $existingIdx2 = DB::select("
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'semesters'
                  AND indexname = 'uniq_one_active_semester_per_year'
            ");
            if (empty($existingIdx2)) {
                DB::statement('
                    CREATE UNIQUE INDEX uniq_one_active_semester_per_year
                    ON semesters (academic_year_id, is_active)
                    WHERE is_active = true AND deleted_at IS NULL
                ');
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS uniq_one_active_academic_year');
            DB::statement('DROP INDEX IF EXISTS uniq_one_active_semester_per_year');
        }

        $tables = ['academic_years', 'semesters', 'subjects', 'parents'];

        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                $toDrop = [];
                if (Schema::hasColumn($tbl, 'created_by')) {
                    $toDrop[] = 'created_by';
                }
                if (Schema::hasColumn($tbl, 'updated_by')) {
                    $toDrop[] = 'updated_by';
                }
                if (! empty($toDrop)) {
                    $table->dropColumn($toDrop);
                }
            });
        }
    }
};
