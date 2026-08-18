<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 05
 *
 * Masalah: Dua entitas guru (`teachers` vs `employees`) tidak terhubung.
 * FK tahfizh → teachers, FK kelas → employees — inkonsisten.
 *
 * Solusi: Bridge Pattern — tambah kolom `employee_id` nullable ke `teachers`.
 * Backfill: coba match berdasarkan email atau employee_number = niy.
 *
 * Aturan SAFE REFACTOR:
 * - Tabel `teachers` TIDAK dihapus.
 * - Model `Teacher` TIDAK diubah.
 * - `employee_id` nullable → data lama tidak rusak.
 * - Backfill hanya mengisi data yang bisa dicocokkan — sisanya NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            // Bridge: link teacher ke employee
            if (! Schema::hasColumn('teachers', 'employee_id')) {
                $table->uuid('employee_id')->nullable()->unique()->after('user_id');
                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            }

            // Tambah flag untuk menandai teacher yang sudah di-migrate ke employees
            if (! Schema::hasColumn('teachers', 'migrated_to_employee')) {
                $table->boolean('migrated_to_employee')->default(false)->after('employee_id');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            // Backfill 1: Match berdasarkan email
            if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'email')) {
                DB::statement('
                    UPDATE teachers t
                    SET employee_id = e.id,
                        migrated_to_employee = true
                    FROM employees e
                    WHERE LOWER(t.email) = LOWER(e.email)
                      AND t.email IS NOT NULL
                      AND e.email IS NOT NULL
                      AND t.employee_id IS NULL
                      AND e.deleted_at IS NULL
                ');
            }

            // Backfill 2: Match berdasarkan employee_number = niy
            if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'niy')) {
                DB::statement('
                    UPDATE teachers t
                    SET employee_id = e.id,
                        migrated_to_employee = true
                    FROM employees e
                    WHERE t.employee_number = e.niy
                      AND t.employee_number IS NOT NULL
                      AND e.niy IS NOT NULL
                      AND t.employee_id IS NULL
                      AND e.deleted_at IS NULL
                ');
            }

            // Backfill 3: Match berdasarkan full_name (last resort, jika masih NULL)
            DB::statement('
                UPDATE teachers t
                SET employee_id = e.id,
                    migrated_to_employee = true
                FROM employees e
                WHERE LOWER(TRIM(t.full_name)) = LOWER(TRIM(e.nama_lengkap))
                  AND t.full_name IS NOT NULL
                  AND e.nama_lengkap IS NOT NULL
                  AND t.employee_id IS NULL
                  AND e.deleted_at IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            if (Schema::hasColumn('teachers', 'employee_id')) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            }
            if (Schema::hasColumn('teachers', 'migrated_to_employee')) {
                $table->dropColumn('migrated_to_employee');
            }
        });
    }
};
