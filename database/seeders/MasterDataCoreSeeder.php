<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MasterDataCoreSeeder extends Seeder
{
    /**
     * Seeder Induk untuk memuat seluruh 11 Master Data Resmi SIMSIT:
     * 1. Jenis Unit Pendidikan (/dashboard/master-jenis-unit)
     * 2. Unit Pendidikan (/dashboard/master/unit-pendidikan)
     * 3. Master Jabatan (/dashboard/master-jabatan)
     * 4. Tahun Ajaran & Semester (/dashboard/master/tahun-ajaran)
     * 5. Kelas & Rombel (/dashboard/master/kelas)
     * 6. Mata Pelajaran (/dashboard/master/mata-pelajaran)
     * 7. Status Siswa (/dashboard/master/status-siswa)
     * 8. Pegawai & Guru (/dashboard/master/pegawai)
     * 9. Buku Induk Siswa & Orang Tua (/dashboard/master/siswa)
     * 10. Manajemen Akun & Hak Akses (/dashboard/master-hak-akses)
     * 11. Tata Tertib & Poin Disiplin (/dashboard/tata-tertib)
     */
    public function run(): void
    {
        $this->command?->info('====================================================');
        $this->command?->info('>>> MEMULAI SEEDING 11 MASTER DATA RESMI SIMSIT <<<');
        $this->command?->info('====================================================');

        // 1. Jenis Unit Pendidikan
        $this->command?->info('[1/11] Seeding Jenis Unit Pendidikan...');
        $this->call(MasterJenisUnitPendidikanSeeder::class);

        // 2. Unit Pendidikan
        $this->command?->info('[2/11] Seeding Unit Pendidikan...');
        $this->call(DataDummyUnitPendidikanSeeder::class);

        // 3. Master Jabatan
        $this->command?->info('[3/11] Seeding Master Jabatan...');
        $this->call(MasterJabatanSeeder::class);

        // 4. Tahun Ajaran & Semester
        $this->command?->info('[4/11] Seeding Tahun Ajaran & Semester...');
        $this->call(MasterTahunAjaranSeeder::class);

        // 5. Kelas & Rombel
        $this->command?->info('[5/11] Seeding Kelas & Rombel...');
        $this->call(KelasSeeder::class);

        // 6. Mata Pelajaran
        $this->command?->info('[6/11] Seeding Mata Pelajaran...');
        $this->call(SubjectSeeder::class);

        // 7. Status Siswa
        $this->command?->info('[7/11] Seeding Master Status Siswa...');
        $this->call(StudentStatusSeeder::class);

        // 8. Pegawai & Guru (Cek Google Sheet import atau Dummy fallback)
        $this->command?->info('[8/11] Seeding Pegawai & Guru...');
        try {
            $this->call(ImportGoogleSheetPegawaiSeeder::class);
        } catch (\Throwable $e) {
            $this->command?->warn('Google Sheet Pegawai offline/gagal, menjalankan DataDummyPegawaiSeeder fallback: ' . $e->getMessage());
            $this->call(DataDummyPegawaiSeeder::class);
        }

        // 9. Buku Induk Siswa (Cek Google Sheet import atau Dummy fallback)
        $this->command?->info('[9/11] Seeding Buku Induk Siswa & Wali...');
        try {
            $this->call(ImportGoogleSheetSiswaSeeder::class);
        } catch (\Throwable $e) {
            $this->command?->warn('Google Sheet Siswa offline/gagal, menjalankan DataDummySiswaSeeder fallback: ' . $e->getMessage());
            $this->call(DataDummySiswaSeeder::class);
        }

        // 10. Manajemen Akun & Hak Akses
        $this->command?->info('[10/11] Seeding Manajemen Akun & Hak Akses...');
        $this->call(RolePermissionSeeder::class);
        $this->call(AttendancePermissionSeeder::class);
        $this->call(CoreAdminUserSeeder::class);
        $this->call(TestingRoleAccountsSeeder::class);
        $this->call(ProvisionDefaultUserPasswordsSeeder::class);

        // 11. Tata Tertib & Poin
        $this->command?->info('[11/11] Seeding Tata Tertib & Poin Disiplin...');
        $this->call(PesantrenDisciplineRulesSeeder::class);

        $this->command?->info('====================================================');
        $this->command?->info('>>> SELURUH 11 MASTER DATA BERHASIL DI-SEED! <<<');
        $this->command?->info('====================================================');
    }
}

