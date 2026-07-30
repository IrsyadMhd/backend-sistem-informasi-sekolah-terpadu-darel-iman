<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Order specified:
     * 1. Role & Permission
     * 2. Unit
     * 3. Guru
     * 4. Pegawai
     * 5. OrangTua
     * 6. Siswa
     * 7. Mapel
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AttendancePermissionSeeder::class,
            DefaultRoleUserSeeder::class,
        ]);

        $this->call([
            MasterJenisUnitPendidikanSeeder::class,
            DataDummyUnitPendidikanSeeder::class,
            MasterJabatanSeeder::class,
            DataDummyPegawaiSeeder::class,
            TeacherSeeder::class,
            ParentSeeder::class,
            DataDummySiswaSeeder::class,
            MasterKurikulumSeeder::class,
            SubjectSeeder::class,
            KelasSeeder::class,
            JadwalPelajaranSeeder::class,
            ModulSemesterSeeder::class,
            ModulAjarSeeder::class,
            LmsReferensiSeeder::class,
            LmsAktivitasBelajarSeeder::class,
            LmsMediaSeeder::class,
            LmsDiskusiSeeder::class,
            LmsPenugasanSeeder::class,
            LmsPengumpulanTugasSeeder::class,
            LmsPresensiSeeder::class,
            PresensiPembelajaranSeeder::class,
            LmsBankSoalSeeder::class,
            LmsUjianSeeder::class,
            LmsPenilaianSeeder::class,
            LmsRaporSeeder::class,
            AttendanceSeeder::class,
        ]);
    }
}
