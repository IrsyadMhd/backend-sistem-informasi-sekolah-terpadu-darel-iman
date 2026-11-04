<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengubah unique constraint NIS dari global unik menjadi unik per-unit pendidikan,
     * serta mendukung siswa yang belum memiliki NIS (null-safe).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS students_nis_unique');
            DB::statement('DROP INDEX IF EXISTS students_nis_unique');
            DB::statement('ALTER TABLE students ALTER COLUMN nis DROP NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS students_unit_nis_unique ON students (unit_id, nis) WHERE nis IS NOT NULL AND deleted_at IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS students_unit_nis_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS students_nis_unique ON students (nis)');
        }
    }
};
