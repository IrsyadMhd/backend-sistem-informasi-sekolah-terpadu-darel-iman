<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 11
 *
 * Masalah: Model Student memiliki `unit_id` dan `nisn` di $fillable
 * tapi kolom tidak ada di tabel `students`.
 * Ini menyebabkan mass assignment error jika field tersebut dikirim.
 *
 * Solusi: Tambah kolom yang hilang ke tabel students.
 *
 * Aturan SAFE REFACTOR:
 * - Semua kolom baru nullable — data lama tidak rusak.
 * - Model Student TIDAK diubah (sudah memiliki $fillable yang benar).
 * - StudentController TIDAK diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // unit_id: FK ke education_units (ada di model $fillable tapi tidak di tabel)
            if (! Schema::hasColumn('students', 'unit_id')) {
                $table->uuid('unit_id')->nullable()->after('parent_id');
                $table->foreign('unit_id')
                    ->references('id')
                    ->on('education_units')
                    ->nullOnDelete();
            }

            // nisn: Nomor Induk Siswa Nasional (ada di model $fillable tapi tidak di tabel)
            if (! Schema::hasColumn('students', 'nisn')) {
                $table->string('nisn', 30)->nullable()->after('nis');
            }

            // tahun_masuk: untuk tracking angkatan siswa
            if (! Schema::hasColumn('students', 'tahun_masuk')) {
                $table->smallInteger('tahun_masuk')->nullable()->after('is_active');
            }

            // Audit log students
            if (! Schema::hasColumn('students', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('students', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            // Unique index untuk nisn (hanya untuk nilai yang tidak NULL)
            $existingIdx = DB::select("
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'students'
                  AND indexname = 'students_nisn_unique_partial'
            ");
            if (empty($existingIdx)) {
                DB::statement('
                    CREATE UNIQUE INDEX students_nisn_unique_partial
                    ON students (nisn)
                    WHERE nisn IS NOT NULL AND deleted_at IS NULL
                ');
            }

            // Backfill unit_id dari class → tbl_kelas.unit_pendidikan_id
            // Siswa yang class_id-nya ada di tbl_kelas bisa dapat unit_id
            if (Schema::hasColumn('tbl_kelas', 'unit_pendidikan_id')) {
                DB::statement('
                    UPDATE students s
                    SET unit_id = tk.unit_pendidikan_id
                    FROM tbl_kelas tk
                    WHERE s.class_id = tk.id
                      AND s.unit_id IS NULL
                      AND tk.unit_pendidikan_id IS NOT NULL
                ');
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS students_nisn_unique_partial');
        }

        Schema::table('students', function (Blueprint $table) {
            $cols = [];
            foreach (['unit_id', 'nisn', 'tahun_masuk', 'created_by', 'updated_by'] as $col) {
                if (Schema::hasColumn('students', $col)) {
                    $cols[] = $col;
                }
            }

            if (in_array('unit_id', $cols)) {
                $table->dropForeign(['unit_id']);
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
