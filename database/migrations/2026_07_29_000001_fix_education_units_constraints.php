<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Backfill jenis_unit_id if NULL exists in education_units
        if (Schema::hasTable('education_units') && Schema::hasColumn('education_units', 'jenis_unit_id')) {
            $defaultJenisUnit = DB::table('master_jenis_unit_pendidikan')->first();

            if (! $defaultJenisUnit) {
                // Insert default record in master_jenis_unit_pendidikan if table is empty
                $defaultUuid = (string) Str::uuid();
                DB::table('master_jenis_unit_pendidikan')->insert([
                    'uuid' => $defaultUuid,
                    'kode_jenis' => 'GENERAL',
                    'nama_jenis' => 'Unit Pendidikan Umum',
                    'singkatan' => 'UMUM',
                    'jenjang' => 'Lainnya',
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $defaultUuid = $defaultJenisUnit->uuid;
            }

            // Fill all NULL jenis_unit_id records to prevent data loss or NOT NULL violation
            DB::table('education_units')
                ->whereNull('jenis_unit_id')
                ->update(['jenis_unit_id' => $defaultUuid]);
        }

        // 2. Drop legacy foreign key constraint if existing
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE education_units DROP CONSTRAINT IF EXISTS fk_edu_units_jenis_unit');
        }

        // 3. Apply NOT NULL, Foreign Key, and Composite Unique Index
        Schema::table('education_units', function (Blueprint $table) {
            $table->uuid('jenis_unit_id')->nullable(false)->change();

            $table->foreign('jenis_unit_id', 'fk_edu_units_jenis_unit')
                ->references('uuid')
                ->on('master_jenis_unit_pendidikan')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unique(['name', 'jenis_unit_id'], 'edu_units_name_jenis_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_units', function (Blueprint $table) {
            $table->dropForeign('fk_edu_units_jenis_unit');
            $table->dropUnique('edu_units_name_jenis_unique');
            $table->uuid('jenis_unit_id')->nullable()->change();
        });
    }
};
