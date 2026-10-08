<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('education_units', function (Blueprint $table) {
            if (! Schema::hasColumn('education_units', 'program_type')) {
                $table->string('program_type', 30)->default('fullday')->after('level')->comment('fullday atau boarding');
            }
            if (! Schema::hasColumn('education_units', 'jam_masuk')) {
                $table->string('jam_masuk', 10)->nullable()->after('program_type')->comment('Jam mulai kegiatan/KBM');
            }
            if (! Schema::hasColumn('education_units', 'jam_pulang')) {
                $table->string('jam_pulang', 10)->nullable()->after('jam_masuk')->comment('Jam kepulangan siswa/santri');
            }
            if (! Schema::hasColumn('education_units', 'jam_kegiatan')) {
                $table->string('jam_kegiatan', 100)->nullable()->after('jam_pulang')->comment('Rentang jam kegiatan atau keterangan jadwal');
            }
            if (! Schema::hasColumn('education_units', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('jam_kegiatan')->comment('Titik koordinat latitude map');
            }
            if (! Schema::hasColumn('education_units', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude')->comment('Titik koordinat longitude map');
            }
            if (! Schema::hasColumn('education_units', 'radius_meter')) {
                $table->integer('radius_meter')->default(100)->after('longitude')->comment('Radius geofencing lokasi unit dalam meter');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('education_units', function (Blueprint $table) {
            $columns = [
                'program_type',
                'jam_masuk',
                'jam_pulang',
                'jam_kegiatan',
                'latitude',
                'longitude',
                'radius_meter',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('education_units', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
