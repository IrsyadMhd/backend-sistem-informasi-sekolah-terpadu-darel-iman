<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\MasterKurikulum;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JadwalSditFullDayTerpaduSeeder extends Seeder
{
    /**
     * Seed Jadwal Resmi SDIT Full Day School (19 Slot Harian + Ekstrakurikuler Sabtu)
     * Tanpa Guru/Pengampu (employee_id & teacher_id = null),
     * Pencegahan Duplikasi (skip jika sudah ada),
     * Terintegrasi Penuh ke Database.
     */
    public function run(): void
    {
        echo "=== [START] SEEDING JADWAL SDIT FULL DAY (19 SLOT HARIAN - TANPA GURU & BEBAS DUPLIKAT) ===\n";

        // 1. Dapatkan Tahun Ajaran Aktif secara Dinamis
        $academicYear = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::where('name', 'like', '%2026/2027%')->first()
            ?? AcademicYear::first();

        if (! $academicYear) {
            $academicYear = AcademicYear::create([
                'name' => '2026/2027',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
                'metadata' => ['source' => 'JadwalSditFullDayTerpaduSeeder'],
            ]);
        }

        // 2. Dapatkan Semester Aktif secara Dinamis
        $activeSemester = Semester::where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->first();

        if (! $activeSemester) {
            $activeSemester = Semester::where('academic_year_id', $academicYear->id)->first();
        }

        if (! $activeSemester) {
            $activeSemester = Semester::create([
                'academic_year_id' => $academicYear->id,
                'name' => 'Semester Ganjil',
                'sequence' => 1,
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
                'metadata' => ['source' => 'JadwalSditFullDayTerpaduSeeder'],
            ]);
        }

        $targetSemesters = Semester::where('academic_year_id', $academicYear->id)->get();
        if ($targetSemesters->isEmpty()) {
            $targetSemesters = collect([$activeSemester]);
        }

        // 3. Dapatkan Unit Pendidikan SDIT (Full Day)
        $unitSdit = EducationUnit::where('code', 'SDIT-01')->first()
            ?? EducationUnit::where('code', 'SDIT-02')->first()
            ?? EducationUnit::where('level', 'SDIT')->first()
            ?? EducationUnit::where('name', 'like', '%SDIT%')->first()
            ?? EducationUnit::first();

        // Pastikan program_type adalah 'fullday'
        if ($unitSdit) {
            $unitMeta = is_array($unitSdit->metadata) ? $unitSdit->metadata : [];
            $unitMeta['is_boarding'] = false;
            $unitSdit->update([
                'program_type' => 'fullday',
                'metadata' => $unitMeta,
            ]);
        }

        // Pastikan kurikulum SD tersedia
        $kurikulum = MasterKurikulum::where('jenjang', 'SD')
            ->orWhere('kode_kurikulum', 'like', '%SD%')
            ->first() ?? MasterKurikulum::first();

        // 4. Rombel Sasaran (Kelas 1 SDIT)
        $rombel = Kelas::where('unit_pendidikan_id', $unitSdit->id)
            ->where(function ($q) {
                $q->where('tingkat', '1')
                  ->orWhere('nama_kelas', 'like', '%Kelas 1%')
                  ->orWhere('kode_kelas', 'like', '%1A%');
            })->first();

        if (! $rombel) {
            $rombel = Kelas::create([
                'unit_pendidikan_id' => $unitSdit->id,
                'tahun_ajaran_id' => $academicYear->id,
                'semester_id' => $activeSemester->id,
                'kode_kelas' => 'SDIT-1A',
                'nama_kelas' => 'Kelas 1 SDIT-01',
                'tingkat' => '1',
                'jenjang' => 'SDIT',
                'kapasitas' => 30,
                'ruangan' => 'Ruang KBM 1A',
                'status' => 'Aktif',
            ]);
        }

        $schoolClass = SchoolClass::firstOrCreate([
            'name' => $rombel->nama_kelas,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $activeSemester->id,
        ], [
            'level' => '1',
            'metadata' => ['status' => 'active'],
        ]);

        // 5. Master Mata Pelajaran SDIT (Tanpa Menentukan Guru Pengampu)
        $subjectsData = [
            'Do\'a - Hadits' => [
                'kode' => 'MAPEL-DOA-SD',
                'nama' => 'Do\'a & Hadits',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#059669',
            ],
            'Keminangkabauan' => [
                'kode' => 'MAPEL-BAM-SD',
                'nama' => 'Keminangkabauan (BAM)',
                'kategori' => 'Muatan Lokal',
                'warna' => '#D97706',
            ],
            'SENI' => [
                'kode' => 'MAPEL-SENI-SD',
                'nama' => 'Seni Budaya & Prakarya',
                'kategori' => 'Wajib',
                'warna' => '#EC4899',
            ],
            'B.Indonesia' => [
                'kode' => 'MAPEL-BINDO-SD',
                'nama' => 'Bahasa Indonesia',
                'kategori' => 'Wajib',
                'warna' => '#EA580C',
            ],
            'B.Arab' => [
                'kode' => 'MAPEL-BARAB-SD',
                'nama' => 'Bahasa Arab',
                'kategori' => 'Wajib',
                'warna' => '#0D9488',
            ],
            'Dirosah' => [
                'kode' => 'MAPEL-DIROSAH-SD',
                'nama' => 'Dirosah Islamiyah',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#16A34A',
            ],
            'MTK' => [
                'kode' => 'MAPEL-MTK-SD',
                'nama' => 'Matematika Dasar',
                'kategori' => 'Wajib',
                'warna' => '#CA8A04',
            ],
            'Tahsin/Tahfizh' => [
                'kode' => 'MAPEL-THF-SD',
                'nama' => 'Tahsin & Tahfizh Al-Qur\'an',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#10B981',
            ],
            'B.Inggris' => [
                'kode' => 'MAPEL-BING-SD',
                'nama' => 'Bahasa Inggris',
                'kategori' => 'Wajib',
                'warna' => '#0284C7',
            ],
            'Baca Tulis' => [
                'kode' => 'MAPEL-CALISTUNG-SD',
                'nama' => 'Baca Tulis (Calistung)',
                'kategori' => 'Wajib',
                'warna' => '#6366F1',
            ],
            'PAI' => [
                'kode' => 'MAPEL-PAI-SD',
                'nama' => 'Pendidikan Agama Islam',
                'kategori' => 'Wajib',
                'warna' => '#15803D',
            ],
            'Pend. Pancasila' => [
                'kode' => 'MAPEL-PPKN-SD',
                'nama' => 'Pendidikan Pancasila',
                'kategori' => 'Wajib',
                'warna' => '#DC2626',
            ],
            'PJOK' => [
                'kode' => 'MAPEL-PJOK-SD',
                'nama' => 'Pendidikan Jasmani & Olahraga',
                'kategori' => 'Wajib',
                'warna' => '#F59E0B',
            ],
        ];

        $subjectModels = [];
        foreach ($subjectsData as $key => $sInfo) {
            $sub = Subject::where('kode_mapel', $sInfo['kode'])
                ->orWhere('name', $sInfo['nama'])
                ->first();

            if (! $sub) {
                $sub = Subject::create([
                    'kode_mapel' => $sInfo['kode'],
                    'nama_mapel' => $sInfo['nama'],
                    'nama_singkat' => strtoupper(substr($key, 0, 8)),
                    'code' => $sInfo['kode'],
                    'name' => $sInfo['nama'],
                    'kelompok_mapel' => 'Kelompok A',
                    'kategori' => $sInfo['kategori'],
                    'jenjang' => 'SD',
                    'unit_pendidikan_id' => $unitSdit->id,
                    'kurikulum_id' => $kurikulum?->id,
                    'guru_pengampu_id' => null, // GURU DISINGKIRKAN SESUAI INSTRUKSI USER
                    'jam_pelajaran' => 4,
                    'kkm' => 75.00,
                    'warna' => $sInfo['warna'],
                    'status' => true,
                ]);
            }
            $subjectModels[$key] = $sub;
        }

        // 6. Matriks Lengkap Roster SDIT Full Day (19 Slot Waktu Sesuai Gambar)
        // Senin - Kamis:
        // 1: 07.00 - 07.35 | Zikir Pagi dan Kokurikuler (Jumat: 07.00 - 08.00 Berbaris Jumat)
        // 2: 07.35 - 08.10 | KBM (Jumat: 08.00 - 08.30)
        // 3: 08.10 - 08.45 | KBM (Jumat: 08.30 - 09.00)
        // 4: 08.45 - 09.20 | KBM (Jumat: 09.00 - 09.30)
        // 5: 09.20 - 09.30 | Sholat Dhuha (Jumat: 09.30 - 09.50)
        // 6: 09.30 - 09.50 | Istirahat dan Sarapan
        // 7: 09.50 - 10.00 | Ke Kamar Mandi
        // 8: 10.00 - 10.35 | Tahsin/Tahfizh
        // 9: 10.35 - 11.10 | Tahsin/Tahfizh
        // 10: 11.10 - 11.45 | Tahsin/Tahfizh
        // 11: 11.45 - 12.15 | Makan Siang (Jumat: 11.45 - 12.50)
        // 12: 12.15 - 12.50 | Sholat Zuhur / Jumat Berjamaah dan Muraja'ah Hafalan
        // 13: 12.50 - 13.20 | Qailulah dan ke Kamar Mandi
        // 14: 13.30 - 14.00 | KBM
        // 15: 14.00 - 14.30 | KBM
        // 16: 14.30 - 15.00 | KBM
        // 17: 15.00 - 15.30 | KBM
        // 18: 15.30 - 15.50 | Shalat Ashar
        // 19: 15.50 - 16.00 | Dzikir Sore
        // Sabtu: Ekstrakurikuler (Silat, Karate, Mewarnai)

        $scheduleDays = [
            // SENIN (Day 1)
            1 => [
                1  => ['type' => 'routine', 'name' => 'Zikir Pagi dan Kokurikuler', 'start' => '07:00:00', 'end' => '07:35:00'],
                2  => ['type' => 'kbm',     'mapel' => 'Do\'a - Hadits',             'start' => '07:35:00', 'end' => '08:10:00'],
                3  => ['type' => 'kbm',     'mapel' => 'B.Indonesia',                'start' => '08:10:00', 'end' => '08:45:00'],
                4  => ['type' => 'kbm',     'mapel' => 'B.Indonesia',                'start' => '08:45:00', 'end' => '09:20:00'],
                5  => ['type' => 'routine', 'name' => 'Sholat Dhuha',                 'start' => '09:20:00', 'end' => '09:30:00'],
                6  => ['type' => 'break',   'name' => 'Istirahat dan Sarapan',        'start' => '09:30:00', 'end' => '09:50:00'],
                7  => ['type' => 'routine', 'name' => 'Ke Kamar Mandi',               'start' => '09:50:00', 'end' => '10:00:00'],
                8  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '11:10:00', 'end' => '11:45:00'],
                11 => ['type' => 'break',   'name' => 'Makan Siang',                  'start' => '11:45:00', 'end' => '12:15:00'],
                12 => ['type' => 'routine', 'name' => 'Sholat Zuhur Berjamaah & Muraja\'ah Hafalan', 'start' => '12:15:00', 'end' => '12:50:00'],
                13 => ['type' => 'break',   'name' => 'Qailulah dan ke Kamar Mandi',  'start' => '12:50:00', 'end' => '13:20:00'],
                14 => ['type' => 'kbm',     'mapel' => 'B.Inggris',                  'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['type' => 'kbm',     'mapel' => 'B.Inggris',                  'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['type' => 'kbm',     'mapel' => 'Pend. Pancasila',            'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['type' => 'kbm',     'mapel' => 'Pend. Pancasila',            'start' => '15:00:00', 'end' => '15:30:00'],
                18 => ['type' => 'routine', 'name' => 'Shalat Ashar',                 'start' => '15:30:00', 'end' => '15:50:00'],
                19 => ['type' => 'routine', 'name' => 'Dzikir Sore',                  'start' => '15:50:00', 'end' => '16:00:00'],
            ],

            // SELASA (Day 2)
            2 => [
                1  => ['type' => 'routine', 'name' => 'Zikir Pagi dan Kokurikuler', 'start' => '07:00:00', 'end' => '07:35:00'],
                2  => ['type' => 'kbm',     'mapel' => 'Keminangkabauan',            'start' => '07:35:00', 'end' => '08:10:00'],
                3  => ['type' => 'kbm',     'mapel' => 'B.Arab',                     'start' => '08:10:00', 'end' => '08:45:00'],
                4  => ['type' => 'kbm',     'mapel' => 'B.Arab',                     'start' => '08:45:00', 'end' => '09:20:00'],
                5  => ['type' => 'routine', 'name' => 'Sholat Dhuha',                 'start' => '09:20:00', 'end' => '09:30:00'],
                6  => ['type' => 'break',   'name' => 'Istirahat dan Sarapan',        'start' => '09:30:00', 'end' => '09:50:00'],
                7  => ['type' => 'routine', 'name' => 'Ke Kamar Mandi',               'start' => '09:50:00', 'end' => '10:00:00'],
                8  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '11:10:00', 'end' => '11:45:00'],
                11 => ['type' => 'break',   'name' => 'Makan Siang',                  'start' => '11:45:00', 'end' => '12:15:00'],
                12 => ['type' => 'routine', 'name' => 'Sholat Zuhur Berjamaah & Muraja\'ah Hafalan', 'start' => '12:15:00', 'end' => '12:50:00'],
                13 => ['type' => 'break',   'name' => 'Qailulah dan ke Kamar Mandi',  'start' => '12:50:00', 'end' => '13:20:00'],
                14 => ['type' => 'kbm',     'mapel' => 'Baca Tulis',                 'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['type' => 'kbm',     'mapel' => 'Baca Tulis',                 'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['type' => 'kbm',     'mapel' => 'MTK',                        'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['type' => 'kbm',     'mapel' => 'MTK',                        'start' => '15:00:00', 'end' => '15:30:00'],
                18 => ['type' => 'routine', 'name' => 'Shalat Ashar',                 'start' => '15:30:00', 'end' => '15:50:00'],
                19 => ['type' => 'routine', 'name' => 'Dzikir Sore',                  'start' => '15:50:00', 'end' => '16:00:00'],
            ],

            // RABU (Day 3)
            3 => [
                1  => ['type' => 'routine', 'name' => 'Zikir Pagi dan Kokurikuler', 'start' => '07:00:00', 'end' => '07:35:00'],
                2  => ['type' => 'kbm',     'mapel' => 'SENI',                       'start' => '07:35:00', 'end' => '08:10:00'],
                3  => ['type' => 'kbm',     'mapel' => 'SENI',                       'start' => '08:10:00', 'end' => '08:45:00'],
                4  => ['type' => 'kbm',     'mapel' => 'Dirosah',                    'start' => '08:45:00', 'end' => '09:20:00'],
                5  => ['type' => 'routine', 'name' => 'Sholat Dhuha',                 'start' => '09:20:00', 'end' => '09:30:00'],
                6  => ['type' => 'break',   'name' => 'Istirahat dan Sarapan',        'start' => '09:30:00', 'end' => '09:50:00'],
                7  => ['type' => 'routine', 'name' => 'Ke Kamar Mandi',               'start' => '09:50:00', 'end' => '10:00:00'],
                8  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '11:10:00', 'end' => '11:45:00'],
                11 => ['type' => 'break',   'name' => 'Makan Siang',                  'start' => '11:45:00', 'end' => '12:15:00'],
                12 => ['type' => 'routine', 'name' => 'Sholat Zuhur Berjamaah & Muraja\'ah Hafalan', 'start' => '12:15:00', 'end' => '12:50:00'],
                13 => ['type' => 'break',   'name' => 'Qailulah dan ke Kamar Mandi',  'start' => '12:50:00', 'end' => '13:20:00'],
                14 => ['type' => 'kbm',     'mapel' => 'Dirosah',                    'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['type' => 'kbm',     'mapel' => 'Dirosah',                    'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['type' => 'kbm',     'mapel' => 'Pend. Pancasila',            'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['type' => 'kbm',     'mapel' => 'Pend. Pancasila',            'start' => '15:00:00', 'end' => '15:30:00'],
                18 => ['type' => 'routine', 'name' => 'Shalat Ashar',                 'start' => '15:30:00', 'end' => '15:50:00'],
                19 => ['type' => 'routine', 'name' => 'Dzikir Sore',                  'start' => '15:50:00', 'end' => '16:00:00'],
            ],

            // KAMIS (Day 4)
            4 => [
                1  => ['type' => 'routine', 'name' => 'Zikir Pagi dan Kokurikuler', 'start' => '07:00:00', 'end' => '07:35:00'],
                2  => ['type' => 'kbm',     'mapel' => 'Do\'a - Hadits',             'start' => '07:35:00', 'end' => '08:10:00'],
                3  => ['type' => 'kbm',     'mapel' => 'B.Indonesia',                'start' => '08:10:00', 'end' => '08:45:00'],
                4  => ['type' => 'kbm',     'mapel' => 'B.Indonesia',                'start' => '08:45:00', 'end' => '09:20:00'],
                5  => ['type' => 'routine', 'name' => 'Sholat Dhuha',                 'start' => '09:20:00', 'end' => '09:30:00'],
                6  => ['type' => 'break',   'name' => 'Istirahat dan Sarapan',        'start' => '09:30:00', 'end' => '09:50:00'],
                7  => ['type' => 'routine', 'name' => 'Ke Kamar Mandi',               'start' => '09:50:00', 'end' => '10:00:00'],
                8  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '11:10:00', 'end' => '11:45:00'],
                11 => ['type' => 'break',   'name' => 'Makan Siang',                  'start' => '11:45:00', 'end' => '12:15:00'],
                12 => ['type' => 'routine', 'name' => 'Sholat Zuhur Berjamaah & Muraja\'ah Hafalan', 'start' => '12:15:00', 'end' => '12:50:00'],
                13 => ['type' => 'break',   'name' => 'Qailulah dan ke Kamar Mandi',  'start' => '12:50:00', 'end' => '13:20:00'],
                14 => ['type' => 'kbm',     'mapel' => 'PAI',                        'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['type' => 'kbm',     'mapel' => 'PAI',                        'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['type' => 'kbm',     'mapel' => 'Baca Tulis',                 'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['type' => 'kbm',     'mapel' => 'Baca Tulis',                 'start' => '15:00:00', 'end' => '15:30:00'],
                18 => ['type' => 'routine', 'name' => 'Shalat Ashar',                 'start' => '15:30:00', 'end' => '15:50:00'],
                19 => ['type' => 'routine', 'name' => 'Dzikir Sore',                  'start' => '15:50:00', 'end' => '16:00:00'],
            ],

            // JUMAT (Day 5) - Waktu Khusus Jumat
            5 => [
                1  => ['type' => 'routine', 'name' => 'Zikir Pagi & Berbaris Jumat',  'start' => '07:00:00', 'end' => '08:00:00'],
                2  => ['type' => 'kbm',     'mapel' => 'MTK',                        'start' => '08:00:00', 'end' => '08:30:00'],
                3  => ['type' => 'kbm',     'mapel' => 'MTK',                        'start' => '08:30:00', 'end' => '09:00:00'],
                4  => ['type' => 'kbm',     'mapel' => 'MTK',                        'start' => '09:00:00', 'end' => '09:30:00'],
                5  => ['type' => 'routine', 'name' => 'Sholat Dhuha, Istirahat & Sarapan', 'start' => '09:30:00', 'end' => '09:50:00'],
                6  => ['type' => 'routine', 'name' => 'Ke Kamar Mandi',               'start' => '09:50:00', 'end' => '10:00:00'],
                7  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:00:00', 'end' => '10:35:00'],
                8  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '10:35:00', 'end' => '11:10:00'],
                9  => ['type' => 'kbm',     'mapel' => 'Tahsin/Tahfizh',             'start' => '11:10:00', 'end' => '11:45:00'],
                10 => ['type' => 'break',   'name' => 'Makan Siang & Persiapan Sholat Jumat', 'start' => '11:45:00', 'end' => '12:15:00'],
                11 => ['type' => 'routine', 'name' => 'Sholat Jumat Berjamaah',       'start' => '12:15:00', 'end' => '12:50:00'],
                12 => ['type' => 'break',   'name' => 'Qailulah dan ke Kamar Mandi',  'start' => '12:50:00', 'end' => '13:20:00'],
                13 => ['type' => 'kbm',     'mapel' => 'SENI',                       'start' => '13:30:00', 'end' => '14:00:00'],
                14 => ['type' => 'kbm',     'mapel' => 'SENI',                       'start' => '14:00:00', 'end' => '14:30:00'],
                15 => ['type' => 'kbm',     'mapel' => 'PJOK',                       'start' => '14:30:00', 'end' => '15:00:00'],
                16 => ['type' => 'kbm',     'mapel' => 'PJOK',                       'start' => '15:00:00', 'end' => '15:30:00'],
                17 => ['type' => 'routine', 'name' => 'Shalat Ashar',                 'start' => '15:30:00', 'end' => '15:50:00'],
                18 => ['type' => 'routine', 'name' => 'Dzikir Sore',                  'start' => '15:50:00', 'end' => '16:00:00'],
            ],

            // SABTU (Day 6) - Ekstrakurikuler Pagi
            6 => [
                1 => ['type' => 'activity', 'name' => 'Ekstrakurikuler: Silat, Karate & Mewarnai', 'start' => '08:00:00', 'end' => '10:30:00'],
            ],
        ];

        // 7. Masukkan Jadwal Baru ke Database Tanpa Duplikat & Tanpa Guru
        $totalInserted = 0;
        $totalSkipped = 0;

        DB::beginTransaction();
        try {
            foreach ($targetSemesters as $semesterItem) {
                foreach ($scheduleDays as $day => $slots) {
                    foreach ($slots as $slotNum => $item) {
                        $startTime = $item['start'];
                        $endTime = $item['end'];

                        // CEK DUPLIKASI: Jika jadwal untuk kelas, tahun ajaran, semester, hari, dan jam mulai ini sudah ada, lewati!
                        $exists = ClassSchedule::where('kelas_id', $rombel->id)
                            ->where('academic_year_id', $academicYear->id)
                            ->where('semester_id', $semesterItem->id)
                            ->where('day_of_week', $day)
                            ->where('time_start', $startTime)
                            ->exists();

                        if ($exists) {
                            $totalSkipped++;
                            continue;
                        }

                        $isKbm = ($item['type'] === 'kbm');
                        $subModel = $isKbm ? ($subjectModels[$item['mapel']] ?? null) : null;
                        $actName = $isKbm ? ($item['mapel'] ?? 'KBM') : ($item['name'] ?? 'Kegiatan');

                        ClassSchedule::create([
                            'id' => (string) Str::uuid(),
                            'kelas_id' => $rombel->id,
                            'class_id' => $schoolClass->id,
                            'employee_id' => null, // GURU PENGAMPU DISINGKIRKAN SESUAI INSTRUKSI USER
                            'teacher_id' => null,  // GURU PENGAMPU DISINGKIRKAN SESUAI INSTRUKSI USER
                            'subject_id' => $subModel?->id,
                            'academic_year_id' => $academicYear->id,
                            'semester_id' => $semesterItem->id,
                            'day_of_week' => $day,
                            'time_start' => $startTime,
                            'time_end' => $endTime,
                            'week_type' => 'all',
                            'is_active' => true,
                            'activity_type' => $item['type'],
                            'activity_name' => $actName,
                            'metadata' => [
                                'slot_ke' => $slotNum,
                                'program_type' => 'fullday',
                                'source' => 'RosterSDITFullDay_Resmi',
                                'label' => $actName,
                            ],
                        ]);

                        $totalInserted++;
                    }
                }
            }

            DB::commit();
            echo "[SUKSES] Berhasil memasukkan {$totalInserted} data jadwal SDIT Full Day (Bebas guru/pengampu, dilewati {$totalSkipped} duplikat).\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "[ERROR] Gagal memasukkan jadwal: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
