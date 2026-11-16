<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for Staging and Production.
     * Order specified according to Foreign Key dependencies:
     * 1. Core Auth & System Settings
     * 2. Master Organizations & Units
     * 3. Keagamaan & Standar Tata Tertib Pesantren
     * 4. Civitas Akademika Riil (Pegawai & Siswa dari Google Sheet SIPDEI-Aligned)
     * 5. Testing Accounts (Staging / Development only)
     * 6. Simulation & Dummy Fixtures (Strictly gated by SEED_DUMMY_DATA=true)
     */
    public function run(): void
    {
        // --------------------------------------------------------------------------
        // TAHAP 1: PONDASI HAK AKSES, AUTENTIKASI & KONFIGURASI SISTEM
        // --------------------------------------------------------------------------
        $this->call([
            RolePermissionSeeder::class,
            AttendancePermissionSeeder::class,
            SiteSettingsSeeder::class,
            StudentCardSettingsSeeder::class,
            CoreAdminUserSeeder::class,
            AppNavigationSeeder::class,
            BusinessOptionSeeder::class,
        ]);

        // --------------------------------------------------------------------------
        // TAHAP 2: MASTER STRUKTUR ORGANISASI & KURIKULUM RIIL
        // --------------------------------------------------------------------------
        $this->call([
            MasterJenisUnitPendidikanSeeder::class,
            MasterTahunAjaranSeeder::class,
            MasterJabatanSeeder::class,
            MasterKurikulumSeeder::class,
            SubjectSeeder::class,
            KonsolidasiMasterMapelSeeder::class,
            KelasSeeder::class,
            StudentStatusSeeder::class,
            TransportationModeSeeder::class,
        ]);

        // --------------------------------------------------------------------------
        // TAHAP 3: MODUL KEISLAMAN & TATA TERTIB PESANTREN
        // --------------------------------------------------------------------------
        $this->call([
            QuranSurahSeeder::class,
            DoaSeeder::class,
            PesantrenDisciplineRulesSeeder::class,
            VocabularyBankSeeder::class,
            SupervisiKbmSeeder::class,
        ]);

        // --------------------------------------------------------------------------
        // TAHAP 4: IMPORT DATA RIIL CIVITAS YAYASAN (GOOGLE SHEETS & SIPDEI-ALIGNED)
        // --------------------------------------------------------------------------
        $this->call([
            ImportGoogleSheetPegawaiSeeder::class,
            ImportGoogleSheetSiswaSeeder::class,
            ProvisionDefaultUserPasswordsSeeder::class,
        ]);

        // --------------------------------------------------------------------------
        // TAHAP 5: AKUN PENGUJIAN MULTI-PERAN (STAGING & LOCAL TESTING ONLY)
        // --------------------------------------------------------------------------
        if (! app()->environment('production')) {
            $this->call([
                TestingRoleAccountsSeeder::class,
                UserTestSeeder::class,
                AddChildrenToTestParentSeeder::class,
            ]);
        }

        // --------------------------------------------------------------------------
        // TAHAP 6: DUMMY / SIMULASI FIXTURES (DINONAKTIFKAN SECARA DEFAULT DI STAGING)
        // Hanya dijalankan jika sengaja disetel SEED_DUMMY_DATA=true di file .env
        // --------------------------------------------------------------------------
        if (! env('SEED_DUMMY_DATA', false)) {
            return;
        }

        // Simulasi fixtures lama jika pengembang membutuhkan data mock dummy buatan
        $this->call([
            ModulAjarSeeder::class,
            WorshipAttendanceSeeder::class,
        ]);
    }
}
