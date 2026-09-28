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
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JadwalPonpesIkhwan2026Seeder extends Seeder
{
    /**
     * Seed jadwal pelajaran resmi Putra (Ikhwan) Semester Genap T.P 2026/2027
     * Berdasarkan Roster Resmi Yayasan Dar El-Iman - SMA Dar El-Iman Islamic Boarding School (DIBS).
     */
    public function run(): void
    {
        echo "=== [START] SEEDING JADWAL PELAJARAN IKHWAN T.P 2026/2027 ===\n";

        // 1. Dapatkan / Pastikan Tahun Ajaran 2026/2027 & Semester Genap
        $academicYear = AcademicYear::where('name', 'like', '%2026/2027%')->first()
            ?? AcademicYear::where('is_active', true)->first();

        if (! $academicYear) {
            $academicYear = AcademicYear::create([
                'name' => '2026/2027',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
                'metadata' => ['source' => 'JadwalPonpesIkhwan2026Seeder'],
            ]);
        }

        $semesterGenap = Semester::where('academic_year_id', $academicYear->id)
            ->where(function ($q) {
                $q->where('sequence', 2)->orWhere('name', 'like', '%Genap%');
            })->first();

        if (! $semesterGenap) {
            $semesterGenap = Semester::create([
                'academic_year_id' => $academicYear->id,
                'name' => 'Genap',
                'sequence' => 2,
                'start_date' => '2027-01-01',
                'end_date' => '2027-06-30',
                'is_active' => false,
                'metadata' => ['source' => 'JadwalPonpesIkhwan2026Seeder'],
            ]);
        }

        // 2. Dapatkan Unit Pendidikan SMA & Kurikulum
        $unitSma = EducationUnit::where('code', 'SMAIT-01')
            ->orWhere('name', 'like', '%SMA%')
            ->first() ?? EducationUnit::first();

        $kurikulum = MasterKurikulum::where('jenjang', 'SMA')
            ->orWhere('kode_kurikulum', 'like', '%SMA%')
            ->first() ?? MasterKurikulum::first();

        // 3. Dapatkan / Buat Rombel 10A, 11A, 12A
        $classMappings = [
            '10A' => [
                'kode' => 'SMA-10-MIPA',
                'aliases' => ['Kelas X MIPA (Al-Khawarizmi)', 'X MIPA 1', '10A', 'X-MIPA'],
                'nama' => 'Kelas 10A (X MIPA Al-Khawarizmi)',
                'tingkat' => '10',
                'ruang' => 'Ruang KBM 10A (Gedung Putra Lt. 1)',
            ],
            '11A' => [
                'kode' => 'SMA-11-MIPA',
                'aliases' => ['Kelas XI MIPA (Ibnu Sina)', 'XI MIPA 1', '11A', 'XI-MIPA'],
                'nama' => 'Kelas 11A (XI MIPA Ibnu Sina)',
                'tingkat' => '11',
                'ruang' => 'Ruang KBM 11A (Gedung Putra Lt. 2)',
            ],
            '12A' => [
                'kode' => 'SMA-12-MIPA',
                'aliases' => ['Kelas XII MIPA (Ibnu Haitsam)', 'XII MIPA 1', '12A', 'XII-MIPA'],
                'nama' => 'Kelas 12A (XII MIPA Ibnu Haitsam)',
                'tingkat' => '12',
                'ruang' => 'Ruang KBM 12A (Gedung Putra Lt. 3)',
            ],
        ];

        $rombels = [];
        foreach ($classMappings as $key => $meta) {
            $rombel = Kelas::where('unit_pendidikan_id', $unitSma?->id)
                ->where(function ($q) use ($meta) {
                    $q->where('kode_kelas', $meta['kode'])
                      ->orWhereIn('nama_kelas', $meta['aliases']);
                })->first();

            if (! $rombel) {
                $rombel = Kelas::create([
                    'unit_pendidikan_id' => $unitSma?->id,
                    'tahun_ajaran_id' => $academicYear->id,
                    'semester_id' => $semesterGenap->id,
                    'kode_kelas' => $meta['kode'],
                    'nama_kelas' => $meta['nama'],
                    'tingkat' => $meta['tingkat'],
                    'jenjang' => 'SMA',
                    'kapasitas' => 30,
                    'ruangan' => $meta['ruang'],
                    'status' => 'Aktif',
                ]);
            }

            // Sync with SchoolClass (classes table) for legacy compatibility
            $schoolClass = SchoolClass::firstOrCreate([
                'name' => $rombel->nama_kelas,
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semesterGenap->id,
            ], [
                'level' => $meta['tingkat'],
                'metadata' => ['status' => 'active'],
            ]);

            $rombels[$key] = [
                'kelas' => $rombel,
                'school_class' => $schoolClass,
            ];
        }

        // 4. Pastikan Seluruh Mata Pelajaran Roster Tersedia
        $subjectsConfig = [
            'TAHSIN TAHFIDZ' => [
                'kode' => 'MAPEL-THF-SMA',
                'nama' => 'Tahsin & Tahfidz Al-Qur\'an',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#F59E0B',
                'guru_keyword' => 'Al-Hafiz',
            ],
            'KIMIA' => [
                'kode' => 'MAPEL-KIM-SMA',
                'nama' => 'Kimia',
                'kategori' => 'Pilihan',
                'warna' => '#8B5CF6',
                'guru_keyword' => 'Kimia',
            ],
            'FISIKA' => [
                'kode' => 'MAPEL-FIS-SMA',
                'nama' => 'Fisika',
                'kategori' => 'Pilihan',
                'warna' => '#7C3AED',
                'guru_keyword' => 'Fisika',
            ],
            'BIOLOGI' => [
                'kode' => 'MAPEL-BIO-SMA',
                'nama' => 'Biologi',
                'kategori' => 'Pilihan',
                'warna' => '#1E3A8A',
                'guru_keyword' => 'Biologi',
            ],
            'MATEMATIKA' => [
                'kode' => 'MAPEL-MTK-SMA',
                'nama' => 'Matematika Umum',
                'kategori' => 'Wajib',
                'warna' => '#EAB308',
                'guru_keyword' => 'Matematika',
            ],
            'BAHASA INDONESIA' => [
                'kode' => 'MAPEL-BINDO-SMA',
                'nama' => 'Bahasa Indonesia',
                'kategori' => 'Wajib',
                'warna' => '#F97316',
                'guru_keyword' => 'Indonesia',
            ],
            'BAHASA INGGRIS' => [
                'kode' => 'MAPEL-BING-SMA',
                'nama' => 'Bahasa Inggris',
                'kategori' => 'Wajib',
                'warna' => '#10B981',
                'guru_keyword' => 'Inggris',
            ],
            'BAHASA ARAB' => [
                'kode' => 'MAPEL-BARAB-SMA',
                'nama' => 'Bahasa Arab',
                'kategori' => 'Wajib',
                'warna' => '#06B6D4',
                'guru_keyword' => 'Arab',
            ],
            'BAHASA ARAB (FIQIH)' => [
                'kode' => 'MAPEL-BARAB-FIQ-SMA',
                'nama' => 'Bahasa Arab & Fiqih',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#0EA5E9',
                'guru_keyword' => 'Fiqih',
            ],
            'PAI' => [
                'kode' => 'MAPEL-PAI-SMA',
                'nama' => 'Pendidikan Agama Islam',
                'kategori' => 'Wajib',
                'warna' => '#059669',
                'guru_keyword' => 'Agama',
            ],
            'PKN' => [
                'kode' => 'MAPEL-PKN-SMA',
                'nama' => 'Pendidikan Pancasila & Kewarganegaraan (PKN)',
                'kategori' => 'Wajib',
                'warna' => '#EF4444',
                'guru_keyword' => 'PKN',
            ],
            'SEJARAH' => [
                'kode' => 'MAPEL-SEJ-SMA',
                'nama' => 'Sejarah',
                'kategori' => 'Wajib',
                'warna' => '#84CC16',
                'guru_keyword' => 'Sejarah',
            ],
            'PJOK' => [
                'kode' => 'MAPEL-PJOK-SMA',
                'nama' => 'PJOK',
                'kategori' => 'Wajib',
                'warna' => '#22C55E',
                'guru_keyword' => 'Olahraga',
            ],
            'BK' => [
                'kode' => 'MAPEL-BK-SMA',
                'nama' => 'Bimbingan & Konseling (BK)',
                'kategori' => 'Wajib',
                'warna' => '#64748B',
                'guru_keyword' => 'Konseling',
            ],
            'INFORMATIKA' => [
                'kode' => 'MAPEL-INF-SMA',
                'nama' => 'Informatika',
                'kategori' => 'Wajib',
                'warna' => '#6366F1',
                'guru_keyword' => 'Komputer',
            ],
            'SENI DAN BUDAYA' => [
                'kode' => 'MAPEL-SBD-SMA',
                'nama' => 'Seni dan Budaya',
                'kategori' => 'Wajib',
                'warna' => '#A855F7',
                'guru_keyword' => 'Seni',
            ],
            'SENI BUDAYA DAN FIQIH' => [
                'kode' => 'MAPEL-SBDFIQ-SMA',
                'nama' => 'Seni Budaya dan Fiqih',
                'kategori' => 'Wajib',
                'warna' => '#14B8A6',
                'guru_keyword' => 'Seni',
            ],
            'BAM' => [
                'kode' => 'MAPEL-BAM-SMA',
                'nama' => 'Budaya Alam Minangkabau (BAM)',
                'kategori' => 'Muatan Lokal',
                'warna' => '#D97706',
                'guru_keyword' => 'Minangkabau',
            ],
            'GEOGRAFI' => [
                'kode' => 'MAPEL-GEO-SMA',
                'nama' => 'Geografi',
                'kategori' => 'Pilihan',
                'warna' => '#0284C7',
                'guru_keyword' => 'Geografi',
            ],
            'EKONOMI' => [
                'kode' => 'MAPEL-EKO-SMA',
                'nama' => 'Ekonomi',
                'kategori' => 'Pilihan',
                'warna' => '#C2410C',
                'guru_keyword' => 'Ekonomi',
            ],
            'SOSIOLOGI' => [
                'kode' => 'MAPEL-SOS-SMA',
                'nama' => 'Sosiologi',
                'kategori' => 'Pilihan',
                'warna' => '#B45309',
                'guru_keyword' => 'Sosiologi',
            ],
            'SOSIOLOGI (BK)' => [
                'kode' => 'MAPEL-SOSBK-SMA',
                'nama' => 'Sosiologi & BK',
                'kategori' => 'Pilihan',
                'warna' => '#B45309',
                'guru_keyword' => 'Sosiologi',
            ],
        ];

        // Dapatkan guru-guru aktif untuk dipasangkan
        $availableTeachers = Employee::where('status', 'Aktif')->get();
        if ($availableTeachers->isEmpty()) {
            $availableTeachers = Employee::all();
        }

        $mapelModels = [];
        $teacherMap = [];
        $tIndex = 0;

        foreach ($subjectsConfig as $key => $sData) {
            $subModel = Subject::where('kode_mapel', $sData['kode'])
                ->orWhere('name', $sData['nama'])
                ->first();

            // Pasangkan satu guru spesifik per rumpun mapel untuk menjamin konsistensi
            $assignedTeacher = $availableTeachers->first(function ($t) use ($sData) {
                return str_contains(strtolower($t->nama_lengkap ?? ''), strtolower($sData['guru_keyword']));
            }) ?? ($availableTeachers[$tIndex % $availableTeachers->count()] ?? null);
            $tIndex++;

            if (! $subModel) {
                $subModel = Subject::create([
                    'kode_mapel' => $sData['kode'],
                    'nama_mapel' => $sData['nama'],
                    'nama_singkat' => strtoupper(substr($sData['nama'], 0, 8)),
                    'code' => $sData['kode'],
                    'name' => $sData['nama'],
                    'kelompok_mapel' => 'Kelompok A',
                    'kategori' => $sData['kategori'],
                    'jenjang' => 'SMA',
                    'unit_pendidikan_id' => $unitSma?->id,
                    'kurikulum_id' => $kurikulum?->id,
                    'guru_pengampu_id' => $assignedTeacher?->id,
                    'jam_pelajaran' => 4,
                    'kkm' => 75.00,
                    'warna' => $sData['warna'],
                    'status' => true,
                ]);
            }

            $mapelModels[$key] = $subModel;
            $teacherMap[$key] = $assignedTeacher;
        }

        // 5. Definisi Slot Waktu Sesuai Roster
        $slotTimes = [
            3  => ['start' => '07:30:00', 'end' => '08:15:00'],
            4  => ['start' => '08:15:00', 'end' => '09:00:00'],
            5  => ['start' => '09:00:00', 'end' => '09:45:00'],
            7  => ['start' => '10:05:00', 'end' => '10:50:00'],
            8  => ['start' => '10:50:00', 'end' => '11:35:00'],
            9  => ['start' => '11:35:00', 'end' => '12:20:00'],
            11 => ['start' => '13:30:00', 'end' => '14:15:00'],
            12 => ['start' => '14:15:00', 'end' => '15:00:00'],
            13 => ['start' => '15:00:00', 'end' => '15:45:00'],
        ];

        // 6. Matriks Lengkap Roster KBM (Senin - Jumat, 10A, 11A, 12A)
        $rosterMatrix = [
            // ================= SENIN (Day 1) =================
            1 => [
                4  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'SEJARAH',             '12A' => 'KIMIA'],
                5  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'SEJARAH',             '12A' => 'KIMIA'],
                7  => ['10A' => 'KIMIA',          '11A' => 'FISIKA',              '12A' => 'PAI'],
                8  => ['10A' => 'KIMIA',          '11A' => 'FISIKA',              '12A' => 'PAI'],
                9  => ['10A' => 'BIOLOGI',        '11A' => 'FISIKA',              '12A' => 'PKN'],
                11 => ['10A' => 'BIOLOGI',        '11A' => 'BK',                  '12A' => 'PKN'],
                12 => ['10A' => 'PKN',            '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                13 => ['10A' => 'PKN',            '11A' => 'KIMIA',               '12A' => 'FISIKA'],
            ],

            // ================= SELASA (Day 2) =================
            2 => [
                3  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                4  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                5  => ['10A' => 'PJOK',           '11A' => 'KIMIA',               '12A' => 'FISIKA'],
                7  => ['10A' => 'PJOK',           '11A' => 'BAHASA ARAB',         '12A' => 'KIMIA'],
                8  => ['10A' => 'MATEMATIKA',     '11A' => 'BAHASA ARAB',         '12A' => 'KIMIA'],
                9  => ['10A' => 'MATEMATIKA',     '11A' => 'PAI',                 '12A' => 'KIMIA'],
                11 => ['10A' => 'MATEMATIKA',     '11A' => 'PAI',                 '12A' => 'BK'],
                12 => ['10A' => 'INFORMATIKA',    '11A' => 'BIOLOGI',             '12A' => 'BAHASA ARAB'],
                13 => ['10A' => 'INFORMATIKA',    '11A' => 'BIOLOGI',             '12A' => 'BAHASA ARAB'],
            ],

            // ================= RABU (Day 3) =================
            3 => [
                3  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'PJOK',                '12A' => 'BK'],
                4  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'PJOK',                '12A' => 'PJOK'],
                5  => ['10A' => 'SEJARAH',        '11A' => 'BAHASA INGGRIS',      '12A' => 'PJOK'],
                7  => ['10A' => 'SEJARAH',        '11A' => 'BAHASA INGGRIS',      '12A' => 'MATEMATIKA'],
                8  => ['10A' => 'FISIKA',         '11A' => 'BAHASA INGGRIS',      '12A' => 'MATEMATIKA'],
                9  => ['10A' => 'FISIKA',         '11A' => 'BAHASA ARAB (FIQIH)', '12A' => 'MATEMATIKA'],
                11 => ['10A' => 'BAHASA INDONESIA','11A' => 'BAHASA ARAB (FIQIH)','12A' => 'BAHASA INGGRIS'],
                12 => ['10A' => 'BAHASA INDONESIA','11A' => 'SENI DAN BUDAYA',    '12A' => 'BAHASA INGGRIS'],
                13 => ['10A' => 'BAHASA INDONESIA','11A' => 'SENI DAN BUDAYA',    '12A' => 'BAHASA INGGRIS'],
            ],

            // ================= KAMIS (Day 4) =================
            4 => [
                3  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'BAHASA INDONESIA',    '12A' => 'BIOLOGI'],
                4  => ['10A' => 'TAHSIN TAHFIDZ', '11A' => 'BAHASA INDONESIA',    '12A' => 'BIOLOGI'],
                5  => ['10A' => 'BAHASA INGGRIS', '11A' => 'BAHASA INDONESIA',    '12A' => 'BIOLOGI'],
                7  => ['10A' => 'BAHASA INGGRIS', '11A' => 'MATEMATIKA',         '12A' => 'SEJARAH'],
                8  => ['10A' => 'BAHASA INGGRIS', '11A' => 'MATEMATIKA',         '12A' => 'SEJARAH'],
                9  => ['10A' => 'SOSIOLOGI (BK)', '11A' => 'MATEMATIKA',         '12A' => 'BAHASA ARAB (FIQIH)'],
                11 => ['10A' => 'SOSIOLOGI',      '11A' => 'BK',                  '12A' => 'BAHASA ARAB (FIQIH)'],
                12 => ['10A' => 'PAI',            '11A' => 'PKN',                 '12A' => 'SENI DAN BUDAYA'],
                13 => ['10A' => 'PAI',            '11A' => 'PKN',                 '12A' => 'SENI DAN BUDAYA'],
            ],

            // ================= JUMAT (Day 5) =================
            5 => [
                3  => ['10A' => 'TAHSIN TAHFIDZ',     '11A' => 'BIOLOGI',         '12A' => 'BAHASA INDONESIA'],
                4  => ['10A' => 'TAHSIN TAHFIDZ',     '11A' => 'BIOLOGI',         '12A' => 'BAHASA INDONESIA'],
                5  => ['10A' => 'SENI BUDAYA DAN FIQIH','11A' => 'BIOLOGI',       '12A' => 'BAHASA INDONESIA'],
                7  => ['10A' => 'SENI BUDAYA DAN FIQIH','11A' => 'FISIKA',        '12A' => 'BIOLOGI'],
                8  => ['10A' => 'GEOGRAFI',            '11A' => 'FISIKA',        '12A' => 'BIOLOGI'],
                // Slot 9: Persiapan Jumat (semua kelas)
                11 => ['10A' => 'GEOGRAFI',            '11A' => 'BAM',           '12A' => 'BAHASA ARAB'],
                12 => ['10A' => 'EKONOMI',             '11A' => 'BAM',           '12A' => 'BAM'],
                13 => ['10A' => 'EKONOMI',             '11A' => 'BAHASA ARAB',   '12A' => 'BAM'],
            ],
        ];

        // 7. Hapus jadwal lama untuk 3 kelas ini pada semester ini agar idempoten
        $kelasIds = collect($rombels)->pluck('kelas.id')->toArray();
        ClassSchedule::whereIn('kelas_id', $kelasIds)
            ->where('academic_year_id', $academicYear->id)
            ->where('semester_id', $semesterGenap->id)
            ->delete();

        // 8. Masukkan Jadwal Baru (Total 129 Record)
        $totalCreated = 0;
        DB::beginTransaction();
        try {
            foreach ($rosterMatrix as $day => $slots) {
                foreach ($slots as $slotNum => $classMap) {
                    $times = $slotTimes[$slotNum];

                    foreach ($classMap as $classKey => $mapelKey) {
                        $rombelData = $rombels[$classKey];
                        $subjectObj = $mapelModels[$mapelKey] ?? null;
                        $teacherObj = $teacherMap[$mapelKey] ?? null;

                        // Khusus untuk mapel paralel pada jam yang sama (PJOK, BAM, BK):
                        // Tugaskan guru kedua/spesifik agar bebas bentrok (Zero Clash)
                        if ($mapelKey === 'PJOK' && $classKey === '12A') {
                            $teacherObj = $availableTeachers->firstWhere('nama_lengkap', 'Ust. Rizky Pratama, S.Pd.') 
                                ?? $availableTeachers->skip(1)->first();
                        } elseif ($mapelKey === 'BAM' && $classKey === '12A') {
                            $teacherObj = $availableTeachers->firstWhere('nama_lengkap', 'Ust. Ahmad Dahlan, S.Pd.')
                                ?? $availableTeachers->skip(2)->first();
                        } elseif ($mapelKey === 'BK') {
                            $teacherObj = $availableTeachers->firstWhere('nama_lengkap', 'Ust. Abu Umar Indra, S.S.')
                                ?? $availableTeachers->skip(3)->first();
                        }

                        ClassSchedule::create([
                            'id' => (string) Str::uuid(),
                            'kelas_id' => $rombelData['kelas']->id,
                            'class_id' => $rombelData['school_class']->id,
                            'employee_id' => $teacherObj?->id,
                            'teacher_id' => null,
                            'subject_id' => $subjectObj?->id,
                            'academic_year_id' => $academicYear->id,
                            'semester_id' => $semesterGenap->id,
                            'day_of_week' => $day,
                            'time_start' => $times['start'],
                            'time_end' => $times['end'],
                            'week_type' => 'all',
                            'is_active' => true,
                            'metadata' => [
                                'slot_ke' => $slotNum,
                                'rombel_label' => $classKey,
                                'mapel_roster' => $mapelKey,
                                'room' => $rombelData['kelas']->ruangan ?? 'Ruang KBM',
                                'source' => 'RosterResmiDIBS_2026',
                            ],
                        ]);

                        $totalCreated++;
                    }
                }
            }

            DB::commit();
            echo "[SUKSES] Berhasil men-generate {$totalCreated} slot KBM resmi Ikhwan (10A, 11A, 12A) Semester Genap 2026/2027.\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "[ERROR] Gagal men-generate jadwal: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
