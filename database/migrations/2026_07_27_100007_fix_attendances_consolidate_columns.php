<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 07
 *
 * Masalah: Konflik dua migration mendefinisikan `attendances`.
 *   - Migration _030100: tabel PARTISI (tidak ada tipe_presensi, employee_id, dll.)
 *   - Migration _020000: tabel BIASA (tidak ada FK academic_years, semesters)
 *
 * Asumsi default: Tabel non-partisi (migration _020000) adalah yang aktif,
 * karena Model Attendance dan AttendanceController menggunakan field dari versi ini.
 *
 * Strategi: Tambah kolom yang mungkin tidak ada di kedua versi menggunakan
 * hasColumn() check — aman untuk kedua skenario.
 *
 * Aturan SAFE REFACTOR:
 * - Tidak ada data yang dihapus.
 * - Semua penambahan kolom menggunakan IF NOT EXISTS.
 * - AttendanceController TIDAK diubah.
 * - Model Attendance TIDAK diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Deteksi apakah tabel attendances adalah tabel partisi atau biasa
            $isPartitioned = DB::select("
                SELECT 1 FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE c.relname = 'attendances'
                  AND n.nspname = 'public'
                  AND c.relkind = 'p'
            ");

            if ($isPartitioned) {
                // Versi PARTISI: tambah kolom yang tidak ada
                $this->fixPartitionedTable();
            } else {
                // Versi NON-PARTISI: tambah kolom yang tidak ada
                $this->fixNonPartitionedTable();
            }
        } else {
            Schema::table('attendances', function (Blueprint $table) {
                if (! Schema::hasColumn('attendances', 'tipe_presensi')) {
                    $table->string('tipe_presensi', 20)->default('Siswa')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'student_id')) {
                    $table->uuid('student_id')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'employee_id')) {
                    $table->uuid('employee_id')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'unit_pendidikan_id')) {
                    $table->uuid('unit_pendidikan_id')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'keterangan')) {
                    $table->text('keterangan')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('attendances', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('attendances', 'attachment_path')) {
                    $table->string('attachment_path')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'created_by')) {
                    $table->string('created_by')->nullable();
                }
                if (! Schema::hasColumn('attendances', 'updated_by')) {
                    $table->string('updated_by')->nullable();
                }
            });
        }
    }

    /**
     * Fix tabel attendances versi PARTISI.
     * Tambah kolom yang ada di versi non-partisi tapi tidak ada di partisi.
     */
    private function fixPartitionedTable(): void
    {
        $cols = [
            'tipe_presensi' => "ALTER TABLE attendances ADD COLUMN IF NOT EXISTS tipe_presensi VARCHAR(20) DEFAULT 'Siswa'",
            'employee_id' => 'ALTER TABLE attendances ADD COLUMN IF NOT EXISTS employee_id UUID NULL',
            'unit_pendidikan_id' => 'ALTER TABLE attendances ADD COLUMN IF NOT EXISTS unit_pendidikan_id UUID NULL',
            'tipe_presensi_keterangan' => 'ALTER TABLE attendances ADD COLUMN IF NOT EXISTS keterangan TEXT NULL',
            'latitude' => 'ALTER TABLE attendances ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL',
            'longitude' => 'ALTER TABLE attendances ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL',
            'attachment_path' => 'ALTER TABLE attendances ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL',
            'created_by' => 'ALTER TABLE attendances ADD COLUMN IF NOT EXISTS created_by VARCHAR(255) NULL',
            'updated_by' => 'ALTER TABLE attendances ADD COLUMN IF NOT EXISTS updated_by VARCHAR(255) NULL',
        ];

        foreach ($cols as $col => $sql) {
            DB::statement($sql);
        }
    }

    /**
     * Fix tabel attendances versi NON-PARTISI.
     * Tambah FK dan kolom yang mungkin belum ada.
     */
    private function fixNonPartitionedTable(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Tambah index gabungan yang berguna untuk report
            if (! $this->indexExists('attendances', 'attendances_date_unit_idx')) {
                $table->index(
                    ['attendance_date', 'unit_pendidikan_id', 'status'],
                    'attendances_date_unit_idx'
                );
            }

            // Tambah kolom employee_id FK ke employees (jika belum ada)
            if (! Schema::hasColumn('attendances', 'employee_id')) {
                $table->uuid('employee_id')->nullable()->after('student_id');
            }
        });

        // Tambah FK employee_id → employees (terpisah karena mungkin kolom sudah ada tapi FK belum)
        $hasFk = DB::select("
            SELECT 1 FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_name = 'attendances'
              AND tc.constraint_type = 'FOREIGN KEY'
              AND kcu.column_name = 'employee_id'
              AND tc.table_schema = 'public'
        ");

        if (empty($hasFk) && Schema::hasTable('employees')) {
            DB::statement('
                ALTER TABLE attendances
                ADD CONSTRAINT fk_attendances_employee
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
            ');
        }
    }

    /**
     * Cek apakah index sudah ada.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $result = DB::select('
            SELECT 1 FROM pg_indexes
            WHERE tablename = ? AND indexname = ?
        ', [$table, $indexName]);

        return ! empty($result);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Hapus index jika ada (non-partisi)
            DB::statement('DROP INDEX IF EXISTS attendances_date_unit_idx');

            // Hapus FK employee_id (non-partisi)
            DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS fk_attendances_employee');

            // Hapus kolom yang ditambahkan di versi partisi
            DB::statement('ALTER TABLE attendances DROP COLUMN IF EXISTS tipe_presensi');
            DB::statement('ALTER TABLE attendances DROP COLUMN IF EXISTS unit_pendidikan_id');
            DB::statement('ALTER TABLE attendances DROP COLUMN IF EXISTS keterangan');
            DB::statement('ALTER TABLE attendances DROP COLUMN IF EXISTS latitude');
            DB::statement('ALTER TABLE attendances DROP COLUMN IF EXISTS longitude');
            DB::statement('ALTER TABLE attendances DROP COLUMN IF EXISTS attachment_path');
            DB::statement('ALTER TABLE attendances DROP COLUMN IF EXISTS created_by');
            DB::statement('ALTER TABLE attendances DROP COLUMN IF EXISTS updated_by');
        }
    }
};
