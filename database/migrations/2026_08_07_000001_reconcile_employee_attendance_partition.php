<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reconciles partitioned attendances table on PostgreSQL so that employee attendance
     * records (tipe_presensi = 'Pegawai' / 'Guru') can be stored cleanly without requiring student_id / class_id.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $isPartitioned = DB::select("
                SELECT 1 FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE c.relname = 'attendances'
                  AND n.nspname = 'public'
                  AND c.relkind = 'p'
            ");

            if (! empty($isPartitioned)) {
                DB::statement('ALTER TABLE attendances ALTER COLUMN student_id DROP NOT NULL;');
                DB::statement('ALTER TABLE attendances ALTER COLUMN class_id DROP NOT NULL;');
                DB::statement("ALTER TABLE attendances ADD COLUMN IF NOT EXISTS tipe_presensi VARCHAR(20) DEFAULT 'Siswa';");
                DB::statement('ALTER TABLE attendances ADD COLUMN IF NOT EXISTS employee_id UUID NULL;');
                DB::statement('ALTER TABLE attendances ADD COLUMN IF NOT EXISTS unit_pendidikan_id UUID NULL;');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive down method to preserve existing attendance historical data
    }
};
