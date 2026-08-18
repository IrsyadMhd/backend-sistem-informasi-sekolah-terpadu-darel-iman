<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration: Fix Student Kelas Relation
 *
 * Masalah (Critical):
 * Tabel `students.class_id` memiliki FK ke `classes` (tabel lama dari migration awal),
 * namun data aktual di seeder dan operasional menyimpan ID dari `tbl_kelas` (tabel kelas aktif).
 *
 * Ini terjadi karena:
 * 1. Migration awal (2026_07_21_030000) membuat tabel `classes` sebagai rombel dasar
 * 2. Migration selanjutnya (2026_07_26_010000) membuat `tbl_kelas` sebagai modul kelas utama
 * 3. Seeder & operasional menggunakan `tbl_kelas`, tapi FK pada `students.class_id` masih ke `classes`
 * 4. Model `Student::schoolClass()` menunjuk ke model `SchoolClass` (tabel `classes`), bukan `Kelas` (tbl_kelas)
 *
 * Solusi Non-Breaking:
 * 1. Tambah kolom `kelas_id` (FK ke tbl_kelas) pada tabel `students` — ini menjadi FK primer baru
 * 2. Backfill `kelas_id` dari `class_id` yang sudah berisi UUID tbl_kelas
 * 3. Jangan drop `class_id` (backward compat)
 * 4. Tambah index pada kelas_id
 *
 * Aturan SAFE REFACTOR:
 * - Tidak menghapus/mengubah migration lama
 * - `class_id` tetap ada untuk backward compat
 * - Semua perubahan dapat di-rollback
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Tambah kolom kelas_id yang merujuk ke tbl_kelas (tabel kelas aktif)
            if (! Schema::hasColumn('students', 'kelas_id')) {
                $table->uuid('kelas_id')->nullable()->after('class_id')
                    ->comment('FK primer ke tbl_kelas (kelas/rombel aktif)');

                // Index untuk performa query filter per kelas
                $table->index('kelas_id', 'students_kelas_id_idx');
            }
        });

        // Tambah FK constraint setelah kolom dibuat
        // Untuk SQLite (dev): tidak support ADD CONSTRAINT secara partial
        // Untuk PostgreSQL (prod): tambah FK penuh
        if (DB::getDriverName() === 'pgsql') {
            // Cek apakah constraint sudah ada
            $exists = DB::selectOne("
                SELECT 1 FROM information_schema.table_constraints
                WHERE constraint_name = 'students_kelas_id_fk'
                  AND table_name = 'students'
            ");

            if (! $exists) {
                DB::statement('
                    ALTER TABLE students
                    ADD CONSTRAINT students_kelas_id_fk
                    FOREIGN KEY (kelas_id)
                    REFERENCES tbl_kelas(id)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
                ');
            }

            // Backfill kelas_id dari class_id yang sudah berisi UUID tbl_kelas
            // Hanya update jika class_id ada di tbl_kelas (bukan di classes)
            DB::statement('
                UPDATE students s
                SET kelas_id = s.class_id
                FROM tbl_kelas tk
                WHERE s.class_id = tk.id
                  AND s.kelas_id IS NULL
                  AND s.class_id IS NOT NULL
            ');
        } else {
            // SQLite: manual backfill tanpa JOIN UPDATE
            $rows = DB::table('students')
                ->whereNotNull('class_id')
                ->whereNull('kelas_id')
                ->select('id', 'class_id')
                ->get();

            $tblKelasIds = DB::table('tbl_kelas')->pluck('id')->toArray();

            foreach ($rows as $student) {
                if (in_array($student->class_id, $tblKelasIds)) {
                    DB::table('students')
                        ->where('id', $student->id)
                        ->update(['kelas_id' => $student->class_id]);
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_kelas_id_fk');
        }

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'kelas_id')) {
                $table->dropIndex('students_kelas_id_idx');
                $table->dropColumn('kelas_id');
            }
        });
    }
};
