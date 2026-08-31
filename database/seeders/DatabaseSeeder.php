<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Order specified according to Foreign Key dependencies:
     * 1. Core Auth & System Settings (Role & Permission, Site Settings, Student Card Settings)
     * 2. Master Data Organizations & Education Units
     * 3. Kepegawaian (Jabatan, Pegawai, Guru)
     * 4. Orang Tua & Siswa
     * 5. Akademik Core (Kurikulum, Mapel, Kelas, Jadwal, Modul Semester)
     * 6. LMS Content & Evaluasi
     * 7. Nilai Siswa (StudentGrades) & Rapor Akademik
     * 8. Presensi, Absensi, Mutabaah & Module Keislaman
     */
    public function run(): void
    {
        // Roles/permissions and system defaults are safe bootstrap data.
        $this->call([
            RolePermissionSeeder::class,
            AttendancePermissionSeeder::class,
            SiteSettingsSeeder::class,
            StudentCardSettingsSeeder::class,
        ]);

        // All records below are development/acceptance fixtures. Never seed
        // them through the production DatabaseSeeder.
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        // 1. Master data and the deterministic login/ownership graph.
        $this->call([
            SuperAdminAcceptanceSeeder::class,
            ModulSemesterSeeder::class,
            QrCredentialSeeder::class,
            PemantauanDivisiSeeder::class,
            PengumumanSekolahSeeder::class,
            IndikatorKinerjaUtamaSeeder::class,
        ]);

        // 5. LMS Core & Content
        $this->call([
            ModulAjarSeeder::class,
            LmsReferensiSeeder::class,
            LmsAktivitasBelajarSeeder::class,
            LmsMediaSeeder::class,
            LmsDiskusiSeeder::class,
            LmsPenugasanSeeder::class,
            LmsPengumpulanTugasSeeder::class,
            LmsPresensiSeeder::class,
            PresensiPembelajaranSeeder::class,
            Step04DemoSeeder::class,
            LmsBankSoalSeeder::class,
            LmsUjianSeeder::class,
            LmsPenilaianSeeder::class,
        ]);

        // 6. Rekap Nilai Akademik & Rapor
        $this->call([
            StudentGradesSeeder::class,
            LmsRaporSeeder::class,
        ]);

        // 7. Attendance, Mutabaah & Islamic Modules
        $this->call([
            AttendanceSeeder::class,
            WorshipAttendanceSeeder::class,
            MutabaahEnterpriseSeeder::class,
            QuranSurahSeeder::class,
            DoaSeeder::class,
            PrayerScheduleSeeder::class,
            TahfizhSeeder::class,
        ]);

        if (app()->environment(['local', 'development', 'testing'])) {
            $this->call(StudentMutationSeeder::class);
            $this->call(RekapPrestasiSiswaSeeder::class);
        }
    }
}
