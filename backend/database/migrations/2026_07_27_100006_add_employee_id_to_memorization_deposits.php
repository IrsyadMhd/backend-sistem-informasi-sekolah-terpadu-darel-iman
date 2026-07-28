<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 06
 *
 * Masalah: memorization_deposits.teacher_id FK ke `teachers` (legacy),
 * tapi sistem sekarang menggunakan `employees` sebagai master guru.
 *
 * Solusi: Tambah kolom `employee_id` nullable ke `memorization_deposits`.
 * Backfill menggunakan bridge yang dibuat di Migration 05 (teachers.employee_id).
 *
 * Aturan SAFE REFACTOR:
 * - `teacher_id` FK ke `teachers` TIDAK dihapus — tetap berfungsi.
 * - `employee_id` nullable — data lama tidak rusak.
 * - TahfizhController TIDAK diubah (masih menulis ke tahfizh_records partisi).
 * - TahfizhRecord model TIDAK diubah (masih membaca memorization_deposits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memorization_deposits', function (Blueprint $table) {
            if (! Schema::hasColumn('memorization_deposits', 'employee_id')) {
                $table->uuid('employee_id')->nullable()->after('teacher_id');
                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            }
        });

        // Backfill: isi employee_id dari teachers.employee_id (bridge Migration 05)
        if (Schema::hasColumn('teachers', 'employee_id')) {
            DB::statement('
                UPDATE memorization_deposits md
                SET employee_id = t.employee_id
                FROM teachers t
                WHERE md.teacher_id = t.id
                  AND t.employee_id IS NOT NULL
                  AND md.employee_id IS NULL
            ');
        }

        // Backfill tahfizh_records (tabel partisi) — tambah employee_id jika kolom belum ada
        // Cek apakah tabel partisi ada dan belum memiliki kolom employee_id
        $hasPartition = DB::select("
            SELECT 1 FROM information_schema.tables
            WHERE table_name = 'tahfizh_records'
              AND table_schema = 'public'
        ");

        if (! empty($hasPartition)) {
            $hasEmployeeId = DB::select("
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'tahfizh_records'
                  AND column_name = 'employee_id'
                  AND table_schema = 'public'
            ");

            if (empty($hasEmployeeId)) {
                // Tambah kolom ke tabel partisi induk (otomatis ter-propagasi ke partisi anak)
                DB::statement('ALTER TABLE tahfizh_records ADD COLUMN IF NOT EXISTS employee_id UUID NULL');

                // Backfill tahfizh_records dari teachers.employee_id
                if (Schema::hasColumn('teachers', 'employee_id')) {
                    DB::statement('
                        UPDATE tahfizh_records tr
                        SET employee_id = t.employee_id
                        FROM teachers t
                        WHERE tr.teacher_id = t.id
                          AND t.employee_id IS NOT NULL
                          AND tr.employee_id IS NULL
                    ');
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('memorization_deposits', function (Blueprint $table) {
            if (Schema::hasColumn('memorization_deposits', 'employee_id')) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            }
        });

        // Hapus dari tahfizh_records partisi
        DB::statement('ALTER TABLE tahfizh_records DROP COLUMN IF EXISTS employee_id');
    }
};
