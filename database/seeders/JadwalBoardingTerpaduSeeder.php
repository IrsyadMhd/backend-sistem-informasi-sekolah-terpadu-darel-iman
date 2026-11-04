<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\MasterKurikulum;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JadwalBoardingTerpaduSeeder extends Seeder
{
    /**
     * Seed Jadwal Resmi Boarding School Terpadu (15 Slot Harian: Asrama & KBM Lengkap)
     * Roster Resmi Jenjang SMA/MA Boarding (10A, 11A, 12A)
     * Tanpa Mock, Tanpa Hardcode ID, Terintegrasi Penuh ke Database Riil.
     */
    public function run(): void
    {
        echo "=== [START] SEEDING JADWAL BOARDING TERPADU (15 SLOT HARIAN) ===\n";

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
                'metadata' => ['source' => 'JadwalBoardingTerpaduSeeder'],
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
                'metadata' => ['source' => 'JadwalBoardingTerpaduSeeder'],
            ]);
        }

        // Ambil semua semester yang relevan agar jadwal dapat diakses di portal
        $targetSemesters = Semester::where('academic_year_id', $academicYear->id)->get();
        if ($targetSemesters->isEmpty()) {
            $targetSemesters = collect([$activeSemester]);
        }

        // 3. Dapatkan Unit Pendidikan Boarding (Non-TK & Non-SD) secara Dinamis
        $boardingUnits = EducationUnit::whereNotIn('level', ['TK', 'TKIT', 'TAUD', 'SD', 'SDIT', 'MIT'])->get();

        $primaryBoardingUnit = $boardingUnits->firstWhere('code', 'SMAIT-01')
            ?? $boardingUnits->first(fn ($u) => str_contains($u->name, 'SMA') || str_contains($u->level, 'SMA'))
            ?? $boardingUnits->firstWhere('code', 'PONPES-PA')
            ?? $boardingUnits->first();

        if (! $primaryBoardingUnit) {
            echo "[ERROR] Unit pendidikan boarding tidak ditemukan.\n";
            return;
        }

        // Pastikan program_type diset ke 'boarding'
        $unitMeta = is_array($primaryBoardingUnit->metadata) ? $primaryBoardingUnit->metadata : [];
        $unitMeta['is_boarding'] = true;
        $primaryBoardingUnit->update([
            'program_type' => 'boarding',
            'metadata' => $unitMeta,
        ]);

        // Pastikan kurikulum jenjang SMA/Menengah tersedia
        $kurikulum = MasterKurikulum::where('jenjang', 'SMA')
            ->orWhere('kode_kurikulum', 'like', '%SMA%')
            ->first() ?? MasterKurikulum::first();

        // 4. Rombel Sasaran Berdasarkan Roster (10A, 11A, 12A)
        $classDefinitions = [
            '10A' => [
                'kode' => 'SMA-10A',
                'nama' => 'Kelas 10A (X MIPA Al-Khawarizmi)',
                'tingkat' => '10',
                'ruang' => 'Ruang KBM 10A (Gedung Putra Lt. 1)',
            ],
            '11A' => [
                'kode' => 'SMA-11A',
                'nama' => 'Kelas 11A (XI MIPA Ibnu Sina)',
                'tingkat' => '11',
                'ruang' => 'Ruang KBM 11A (Gedung Putra Lt. 2)',
            ],
            '12A' => [
                'kode' => 'SMA-12A',
                'nama' => 'Kelas 12A (XII MIPA Ibnu Haitsam)',
                'tingkat' => '12',
                'ruang' => 'Ruang KBM 12A (Gedung Putra Lt. 3)',
            ],
        ];

        $rombels = [];
        foreach ($classDefinitions as $key => $meta) {
            $rombel = Kelas::where('unit_pendidikan_id', $primaryBoardingUnit->id)
                ->where(function ($q) use ($meta, $key) {
                    $q->where('kode_kelas', $meta['kode'])
                      ->orWhere('nama_kelas', 'like', "%{$key}%")
                      ->orWhere('nama_kelas', $meta['nama']);
                })->first();

            if (! $rombel) {
                $rombel = Kelas::create([
                    'unit_pendidikan_id' => $primaryBoardingUnit->id,
                    'tahun_ajaran_id' => $academicYear->id,
                    'semester_id' => $activeSemester->id,
                    'kode_kelas' => $meta['kode'],
                    'nama_kelas' => $meta['nama'],
                    'tingkat' => $meta['tingkat'],
                    'jenjang' => 'SMA',
                    'kapasitas' => 30,
                    'ruangan' => $meta['ruang'],
                    'status' => 'Aktif',
                ]);
            }

            // Sinkronkan ke tabel legacy classes
            $schoolClass = SchoolClass::firstOrCreate([
                'name' => $rombel->nama_kelas,
                'academic_year_id' => $academicYear->id,
                'semester_id' => $activeSemester->id,
            ], [
                'level' => $meta['tingkat'],
                'metadata' => ['status' => 'active'],
            ]);

            $rombels[$key] = [
                'kelas' => $rombel,
                'school_class' => $schoolClass,
            ];
        }

        // 5. Konfigurasi Mata Pelajaran Resmi dari Tabel Roster
        $subjectsData = [
            'TAHSIN TAHFIDZ' => [
                'kode' => 'MAPEL-THF-SMA',
                'nama' => 'Tahsin & Tahfidz Al-Qur\'an',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#F59E0B',
                'keyword' => 'Al-Hafiz',
            ],
            'KIMIA' => [
                'kode' => 'MAPEL-KIM-SMA',
                'nama' => 'Kimia',
                'kategori' => 'Pilihan',
                'warna' => '#8B5CF6',
                'keyword' => 'Kimia',
            ],
            'FISIKA' => [
                'kode' => 'MAPEL-FIS-SMA',
                'nama' => 'Fisika',
                'kategori' => 'Pilihan',
                'warna' => '#7C3AED',
                'keyword' => 'Fisika',
            ],
            'BIOLOGI' => [
                'kode' => 'MAPEL-BIO-SMA',
                'nama' => 'Biologi',
                'kategori' => 'Pilihan',
                'warna' => '#1E3A8A',
                'keyword' => 'Biologi',
            ],
            'MATEMATIKA' => [
                'kode' => 'MAPEL-MTK-SMA',
                'nama' => 'Matematika Umum',
                'kategori' => 'Wajib',
                'warna' => '#EAB308',
                'keyword' => 'Matematika',
            ],
            'BAHASA INDONESIA' => [
                'kode' => 'MAPEL-BINDO-SMA',
                'nama' => 'Bahasa Indonesia',
                'kategori' => 'Wajib',
                'warna' => '#F97316',
                'keyword' => 'Indonesia',
            ],
            'BAHASA INGGRIS' => [
                'kode' => 'MAPEL-BING-SMA',
                'nama' => 'Bahasa Inggris',
                'kategori' => 'Wajib',
                'warna' => '#10B981',
                'keyword' => 'Inggris',
            ],
            'BAHASA ARAB' => [
                'kode' => 'MAPEL-BARAB-SMA',
                'nama' => 'Bahasa Arab',
                'kategori' => 'Wajib',
                'warna' => '#06B6D4',
                'keyword' => 'Arab',
            ],
            'BAHASA ARAB (FIQIH)' => [
                'kode' => 'MAPEL-BARAB-FIQ-SMA',
                'nama' => 'Bahasa Arab & Fiqih',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#0EA5E9',
                'keyword' => 'Fiqih',
            ],
            'PAI' => [
                'kode' => 'MAPEL-PAI-SMA',
                'nama' => 'Pendidikan Agama Islam',
                'kategori' => 'Wajib',
                'warna' => '#059669',
                'keyword' => 'Agama',
            ],
            'PKN' => [
                'kode' => 'MAPEL-PKN-SMA',
                'nama' => 'Pendidikan Pancasila & Kewarganegaraan (PKN)',
                'kategori' => 'Wajib',
                'warna' => '#EF4444',
                'keyword' => 'PKN',
            ],
            'SEJARAH' => [
                'kode' => 'MAPEL-SEJ-SMA',
                'nama' => 'Sejarah',
                'kategori' => 'Wajib',
                'warna' => '#84CC16',
                'keyword' => 'Sejarah',
            ],
            'PJOK' => [
                'kode' => 'MAPEL-PJOK-SMA',
                'nama' => 'PJOK',
                'kategori' => 'Wajib',
                'warna' => '#22C55E',
                'keyword' => 'Olahraga',
            ],
            'BK' => [
                'kode' => 'MAPEL-BK-SMA',
                'nama' => 'Bimbingan & Konseling (BK)',
                'kategori' => 'Wajib',
                'warna' => '#64748B',
                'keyword' => 'Konseling',
            ],
            'INFORMATIKA' => [
                'kode' => 'MAPEL-INF-SMA',
                'nama' => 'Informatika',
                'kategori' => 'Wajib',
                'warna' => '#6366F1',
                'keyword' => 'Komputer',
            ],
            'SENI DAN BUDAYA' => [
                'kode' => 'MAPEL-SBD-SMA',
                'nama' => 'Seni dan Budaya',
                'kategori' => 'Wajib',
                'warna' => '#A855F7',
                'keyword' => 'Seni',
            ],
            'SENI BUDAYA DAN FIQIH' => [
                'kode' => 'MAPEL-SBDFIQ-SMA',
                'nama' => 'Seni Budaya dan Fiqih',
                'kategori' => 'Wajib',
                'warna' => '#14B8A6',
                'keyword' => 'Seni',
            ],
            'BAM' => [
                'kode' => 'MAPEL-BAM-SMA',
                'nama' => 'Budaya Alam Minangkabau (BAM)',
                'kategori' => 'Muatan Lokal',
                'warna' => '#D97706',
                'keyword' => 'Minangkabau',
            ],
            'GEOGRAFI' => [
                'kode' => 'MAPEL-GEO-SMA',
                'nama' => 'Geografi',
                'kategori' => 'Pilihan',
                'warna' => '#0284C7',
                'keyword' => 'Geografi',
            ],
            'EKONOMI' => [
                'kode' => 'MAPEL-EKO-SMA',
                'nama' => 'Ekonomi',
                'kategori' => 'Pilihan',
                'warna' => '#C2410C',
                'keyword' => 'Ekonomi',
            ],
            'SOSIOLOGI' => [
                'kode' => 'MAPEL-SOS-SMA',
                'nama' => 'Sosiologi',
                'kategori' => 'Pilihan',
                'warna' => '#B45309',
                'keyword' => 'Sosiologi',
            ],
            'SOSIOLOGI (BK)' => [
                'kode' => 'MAPEL-SOSBK-SMA',
                'nama' => 'Sosiologi & BK',
                'kategori' => 'Pilihan',
                'warna' => '#B45309',
                'keyword' => 'Sosiologi',
            ],
        ];

        // 6. Alokasi Guru dan Musyrif Dinamis dari Database
        $allActiveEmployees = Employee::where('status', 'Aktif')->get();
        if ($allActiveEmployees->isEmpty()) {
            $allActiveEmployees = Employee::all();
        }

        // Pembina Asrama (Musyrif) Dinamis
        $musyrif = $allActiveEmployees->first(fn ($e) => str_contains(strtolower($e->nama_lengkap ?? ''), 'musyrif'))
            ?? $allActiveEmployees->first(fn ($e) => str_contains(strtolower($e->jabatan ?? ''), 'asrama'))
            ?? $allActiveEmployees->first();

        // Guru Pendamping Dinamis
        $guruPiket = $allActiveEmployees->skip(1)->first() ?? $musyrif;

        $subjectModels = [];
        $teacherMap = [];
        $tIdx = 0;

        foreach ($subjectsData as $key => $sData) {
            $sub = Subject::where('kode_mapel', $sData['kode'])
                ->orWhere('name', $sData['nama'])
                ->first();

            // Cocokkan guru pengampu secara dinamis berdasarkan keahlian nama
            $assignedTeacher = $allActiveEmployees->first(function ($t) use ($sData) {
                return str_contains(strtolower($t->nama_lengkap ?? ''), strtolower($sData['keyword']));
            }) ?? ($allActiveEmployees[$tIdx % $allActiveEmployees->count()] ?? null);
            $tIdx++;

            if (! $sub) {
                $sub = Subject::create([
                    'kode_mapel' => $sData['kode'],
                    'nama_mapel' => $sData['nama'],
                    'nama_singkat' => strtoupper(substr($sData['nama'], 0, 8)),
                    'code' => $sData['kode'],
                    'name' => $sData['nama'],
                    'kelompok_mapel' => 'Kelompok A',
                    'kategori' => $sData['kategori'],
                    'jenjang' => 'SMA',
                    'unit_pendidikan_id' => $primaryBoardingUnit->id,
                    'kurikulum_id' => $kurikulum?->id,
                    'guru_pengampu_id' => $assignedTeacher?->id,
                    'jam_pelajaran' => 4,
                    'kkm' => 75.00,
                    'warna' => $sData['warna'],
                    'status' => true,
                ]);
            }

            $subjectModels[$key] = $sub;
            $teacherMap[$key] = $assignedTeacher;
        }

        // 7. Definisi 15 Slot Waktu Resmi Sesuai Gambar
        $allSlotTimes = [
            1  => ['start' => '04:30:00', 'end' => '07:00:00', 'type' => 'routine', 'name' => 'Kegiatan Asrama Pagi (Subuh & Halaqah Al-Qur\'an)'],
            2  => ['start' => '07:00:00', 'end' => '07:30:00', 'type' => 'routine', 'name' => 'Pembiasaan Pagi (Upacara/Kegiatan Kelas/Apel)'],
            3  => ['start' => '07:30:00', 'end' => '08:15:00', 'type' => 'kbm',     'name' => 'Jam Belajar 1'],
            4  => ['start' => '08:15:00', 'end' => '09:00:00', 'type' => 'kbm',     'name' => 'Jam Belajar 2'],
            5  => ['start' => '09:00:00', 'end' => '09:45:00', 'type' => 'kbm',     'name' => 'Jam Belajar 3'],
            6  => ['start' => '09:45:00', 'end' => '10:05:00', 'type' => 'break',   'name' => 'Istirahat & Snack Pagi'],
            7  => ['start' => '10:05:00', 'end' => '10:50:00', 'type' => 'kbm',     'name' => 'Jam Belajar 4'],
            8  => ['start' => '10:50:00', 'end' => '11:35:00', 'type' => 'kbm',     'name' => 'Jam Belajar 5'],
            9  => ['start' => '11:35:00', 'end' => '12:20:00', 'type' => 'kbm',     'name' => 'Jam Belajar 6 / Persiapan Sholat Jumat'],
            10 => ['start' => '12:20:00', 'end' => '13:30:00', 'type' => 'break',   'name' => 'ISHOMA (Istirahat, Sholat Dzuhur & Makan Siang)'],
            11 => ['start' => '13:30:00', 'end' => '14:15:00', 'type' => 'kbm',     'name' => 'Jam Belajar 7'],
            12 => ['start' => '14:15:00', 'end' => '15:00:00', 'type' => 'kbm',     'name' => 'Jam Belajar 8'],
            13 => ['start' => '15:00:00', 'end' => '15:45:00', 'type' => 'kbm',     'name' => 'Jam Belajar 9'],
            14 => ['start' => '15:45:00', 'end' => '16:15:00', 'type' => 'routine', 'name' => 'Sholat Ashar Berjamaah'],
            15 => ['start' => '16:15:00', 'end' => '21:30:00', 'type' => 'routine', 'name' => 'Kegiatan Asrama Malam (Mandi, Maghrib, Isya & Belajar Mandiri)'],
        ];

        // 8. Matriks Lengkap Seluruh 15 Slot Harian (Senin - Jumat) Sesuai Gambar
        $fullRosterMatrix = [
            // ================= SENIN (Day 1) =================
            1 => [
                1  => ['type' => 'routine', 'name' => 'Kegiatan Asrama (Subuh & Halaqah)'],
                2  => ['type' => 'routine', 'name' => 'Upacara Bendera'],
                3  => ['type' => 'routine', 'name' => 'Upacara Bendera'],
                4  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'SEJARAH',             '12A' => 'KIMIA'],
                5  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'SEJARAH',             '12A' => 'KIMIA'],
                6  => ['type' => 'break',   'name' => 'Istirahat'],
                7  => ['10A' => 'KIMIA',          '11A' => 'FISIKA',              '12A' => 'PAI'],
                8  => ['10A' => 'KIMIA',          '11A' => 'FISIKA',              '12A' => 'PAI'],
                9  => ['10A' => 'BIOLOGI',        '11A' => 'FISIKA',              '12A' => 'PKN'],
                10 => ['type' => 'break',   'name' => 'ISHOMA'],
                11 => ['10A' => 'BIOLOGI',        '11A' => 'BK',                  '12A' => 'PKN'],
                12 => ['10A' => 'PKN',            '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                13 => ['10A' => 'PKN',            '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                14 => ['type' => 'routine', 'name' => 'Sholat Ashar'],
                15 => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
            ],

            // ================= SELASA (Day 2) =================
            2 => [
                1  => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
                2  => ['type' => 'routine', 'name' => 'Kegiatan Kelas'],
                3  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                4  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                5  => ['10A' => 'PJOK',           '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                6  => ['type' => 'break',   'name' => 'Istirahat'],
                7  => ['10A' => 'PJOK',           '11A' => 'BAHASA ARAB',         '12A' => 'KIMIA'],
                8  => ['10A' => 'MATEMATIKA',     '11A' => 'BAHASA ARAB',         '12A' => 'KIMIA'],
                9  => ['10A' => 'MATEMATIKA',     '11A' => 'PAI',                 '12A' => 'KIMIA'],
                10 => ['type' => 'break',   'name' => 'ISHOMA'],
                11 => ['10A' => 'MATEMATIKA',     '11A' => 'PAI',                 '12A' => 'BK'],
                12 => ['10A' => 'INFORMATIKA',    '11A' => 'BIOLOGI',             '12A' => 'BAHASA ARAB'],
                13 => ['10A' => 'INFORMATIKA',    '11A' => 'BIOLOGI',             '12A' => 'BAHASA ARAB'],
                14 => ['type' => 'routine', 'name' => 'Sholat Ashar'],
                15 => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
            ],

            // ================= RABU (Day 3) =================
            3 => [
                1  => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
                2  => ['type' => 'routine', 'name' => 'Kegiatan Kelas'],
                3  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'PJOK',                '12A' => 'BK'],
                4  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'PJOK',                '12A' => 'PJOK'],
                5  => ['10A' => 'SEJARAH',        '11A' => 'BAHASA INGGRIS',      '12A' => 'PJOK'],
                6  => ['type' => 'break',   'name' => 'Istirahat'],
                7  => ['10A' => 'SEJARAH',        '11A' => 'BAHASA INGGRIS',      '12A' => 'MATEMATIKA'],
                8  => ['10A' => 'FISIKA',         '11A' => 'BAHASA INGGRIS',      '12A' => 'MATEMATIKA'],
                9  => ['10A' => 'FISIKA',         '11A' => 'BAHASA ARAB (FIQIH)', '12A' => 'MATEMATIKA'],
                10 => ['type' => 'break',   'name' => 'ISHOMA'],
                11 => ['10A' => 'BAHASA INDONESIA','11A' => 'BAHASA ARAB (FIQIH)','12A' => 'BAHASA INGGRIS'],
                12 => ['10A' => 'BAHASA INDONESIA','11A' => 'SENI DAN BUDAYA',    '12A' => 'BAHASA INGGRIS'],
                13 => ['10A' => 'BAHASA INDONESIA','11A' => 'SENI DAN BUDAYA',    '12A' => 'BAHASA INGGRIS'],
                14 => ['type' => 'routine', 'name' => 'Sholat Ashar'],
                15 => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
            ],

            // ================= KAMIS (Day 4) =================
            4 => [
                1  => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
                2  => ['type' => 'routine', 'name' => 'Kegiatan Kelas'],
                3  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'BAHASA INDONESIA',    '12A' => 'BIOLOGI'],
                4  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'BAHASA INDONESIA',    '12A' => 'BIOLOGI'],
                5  => ['10A' => 'BAHASA INGGRIS', '11A' => 'BAHASA INDONESIA',    '12A' => 'BIOLOGI'],
                6  => ['type' => 'break',   'name' => 'Istirahat'],
                7  => ['10A' => 'BAHASA INGGRIS', '11A' => 'MATEMATIKA',         '12A' => 'SEJARAH'],
                8  => ['10A' => 'BAHASA INGGRIS', '11A' => 'MATEMATIKA',         '12A' => 'SEJARAH'],
                9  => ['10A' => 'SOSIOLOGI (BK)', '11A' => 'MATEMATIKA',         '12A' => 'BAHASA ARAB (FIQIH)'],
                10 => ['type' => 'break',   'name' => 'ISHOMA'],
                11 => ['10A' => 'SOSIOLOGI',      '11A' => 'BK',                  '12A' => 'BAHASA ARAB (FIQIH)'],
                12 => ['10A' => 'PAI',            '11A' => 'PKN',                 '12A' => 'SENI DAN BUDAYA'],
                13 => ['10A' => 'PAI',            '11A' => 'PKN',                 '12A' => 'SENI DAN BUDAYA'],
                14 => ['type' => 'routine', 'name' => 'Sholat Ashar'],
                15 => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
            ],

            // ================= JUMAT (Day 5) =================
            5 => [
                1  => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
                2  => ['type' => 'routine', 'name' => 'Apel Pagi'],
                3  => ['10A' => 'TAHSIN TAHFIDZ',      '11A' => 'BIOLOGI',        '12A' => 'BAHASA INDONESIA'],
                4  => ['10A' => 'TAHSIN TAHFIDZ',      '11A' => 'BIOLOGI',        '12A' => 'BAHASA INDONESIA'],
                5  => ['10A' => 'SENI BUDAYA DAN FIQIH','11A' => 'BIOLOGI',      '12A' => 'BAHASA INDONESIA'],
                6  => ['type' => 'break',   'name' => 'Istirahat'],
                7  => ['10A' => 'SENI BUDAYA DAN FIQIH','11A' => 'FISIKA',       '12A' => 'BIOLOGI'],
                8  => ['10A' => 'GEOGRAFI',             '11A' => 'FISIKA',       '12A' => 'BIOLOGI'],
                9  => ['type' => 'routine', 'name' => 'Persiapan Jumat'],
                10 => ['type' => 'break',   'name' => 'ISHOMA'],
                11 => ['10A' => 'GEOGRAFI',             '11A' => 'BAM',          '12A' => 'BAHASA ARAB'],
                12 => ['10A' => 'EKONOMI',              '11A' => 'BAM',          '12A' => 'BAM'],
                13 => ['10A' => 'EKONOMI',              '11A' => 'BAHASA ARAB',  '12A' => 'BAM'],
                14 => ['type' => 'routine', 'name' => 'Sholat Ashar'],
                15 => ['type' => 'routine', 'name' => 'Kegiatan Asrama'],
            ],
        ];

        // 9. Bersihkan Jadwal Sebelumnya untuk Rombel Ini agar Idempoten
        $kelasIds = collect($rombels)->pluck('kelas.id')->toArray();
        ClassSchedule::whereIn('kelas_id', $kelasIds)
            ->where('academic_year_id', $academicYear->id)
            ->delete();

        // 10. Masukkan Jadwal Terpadu ke Database (Untuk Seluruh Semester Relevan)
        $totalCreated = 0;
        DB::beginTransaction();

        try {
            foreach ($targetSemesters as $semesterItem) {
                foreach ($fullRosterMatrix as $day => $slots) {
                    foreach ($slots as $slotNum => $slotData) {
                        $timeConfig = $allSlotTimes[$slotNum];

                        // Periksa apakah slot ini adalah slot umum bersama (Asrama, Upacara, Istirahat, Ishoma, Ashar)
                        if (isset($slotData['type']) && isset($slotData['name'])) {
                            // Slot ini berlaku untuk SEMUA kelas boarding (10A, 11A, 12A)
                            foreach ($rombels as $classKey => $rombelData) {
                                ClassSchedule::create([
                                    'id' => (string) Str::uuid(),
                                    'kelas_id' => $rombelData['kelas']->id,
                                    'class_id' => $rombelData['school_class']->id,
                                    'employee_id' => ($slotData['type'] === 'break') ? null : $musyrif?->id,
                                    'teacher_id' => null,
                                    'subject_id' => null,
                                    'academic_year_id' => $academicYear->id,
                                    'semester_id' => $semesterItem->id,
                                    'day_of_week' => $day,
                                    'time_start' => $timeConfig['start'],
                                    'time_end' => $timeConfig['end'],
                                    'week_type' => 'all',
                                    'is_active' => true,
                                    'activity_type' => $slotData['type'],
                                    'activity_name' => $slotData['name'],
                                    'metadata' => [
                                        'slot_ke' => $slotNum,
                                        'rombel_label' => $classKey,
                                        'activity_name' => $slotData['name'],
                                        'room' => $slotData['type'] === 'routine' && str_contains($slotData['name'], 'Asrama') ? 'Asrama Boarding' : 'Masjid / Gedung Utama',
                                        'source' => 'RosterBoarding_Resmi',
                                    ],
                                ]);
                                $totalCreated++;
                            }
                        } else {
                            // Slot ini adalah slot KBM akademik dengan mata pelajaran per kelas (10A, 11A, 12A)
                            foreach ($slotData as $classKey => $mapelKey) {
                                if (! isset($rombels[$classKey])) {
                                    continue;
                                }

                                $rombelData = $rombels[$classKey];
                                $subjectObj = $subjectModels[$mapelKey] ?? null;
                                $teacherObj = $teacherMap[$mapelKey] ?? null;

                                // Penanganan guru paralel untuk mencegah bentrok jadwal (Zero Teacher Clash)
                                if ($mapelKey === 'PJOK' && $classKey === '12A') {
                                    $teacherObj = $allActiveEmployees->firstWhere('nama_lengkap', 'Ust. Rizky Pratama, S.Pd.') 
                                        ?? $allActiveEmployees->skip(2)->first();
                                } elseif ($mapelKey === 'BAM' && $classKey === '12A') {
                                    $teacherObj = $allActiveEmployees->firstWhere('nama_lengkap', 'Ust. Ahmad Dahlan, S.Pd.')
                                        ?? $allActiveEmployees->skip(3)->first();
                                } elseif ($mapelKey === 'BK') {
                                    $teacherObj = $allActiveEmployees->firstWhere('nama_lengkap', 'Ust. Abu Umar Indra, S.S.')
                                        ?? $allActiveEmployees->skip(4)->first();
                                }

                                ClassSchedule::create([
                                    'id' => (string) Str::uuid(),
                                    'kelas_id' => $rombelData['kelas']->id,
                                    'class_id' => $rombelData['school_class']->id,
                                    'employee_id' => $teacherObj?->id,
                                    'teacher_id' => null,
                                    'subject_id' => $subjectObj?->id,
                                    'academic_year_id' => $academicYear->id,
                                    'semester_id' => $semesterItem->id,
                                    'day_of_week' => $day,
                                    'time_start' => $timeConfig['start'],
                                    'time_end' => $timeConfig['end'],
                                    'week_type' => 'all',
                                    'is_active' => true,
                                    'activity_type' => 'kbm',
                                    'activity_name' => $mapelKey,
                                    'metadata' => [
                                        'slot_ke' => $slotNum,
                                        'rombel_label' => $classKey,
                                        'mapel_roster' => $mapelKey,
                                        'room' => $rombelData['kelas']->ruangan ?? 'Ruang KBM',
                                        'source' => 'RosterBoarding_Resmi',
                                    ],
                                ]);
                                $totalCreated++;
                            }
                        }
                    }
                }
            }

            DB::commit();
            echo "[SUKSES] Berhasil men-generate {$totalCreated} entri jadwal boarding resmi (15 slot harian: Asrama & KBM 10A, 11A, 12A).\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "[ERROR] Gagal men-generate jadwal: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
