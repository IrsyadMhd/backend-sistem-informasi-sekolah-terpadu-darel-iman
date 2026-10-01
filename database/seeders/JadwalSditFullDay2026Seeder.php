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
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class JadwalSditFullDay2026Seeder extends Seeder
{
    /**
     * Seed Roster Resmi SDIT Full Day School T.P 2026/2027 (19 Slot Harian Termasuk Qailulah & Ibadah)
     * Berdasarkan Dokumen Jadwal Pembiasaan & KBM Terpadu SDIT Dar El-Iman.
     */
    public function run(): void
    {
        echo "=== [START] SEEDING JADWAL SDIT FULL DAY SCHOOL T.P 2026/2027 ===\n";

        // 1. Tahun Ajaran 2026/2027 & Semester Aktif
        $academicYear = AcademicYear::where('name', 'like', '%2026/2027%')->first()
            ?? AcademicYear::where('is_active', true)->first();

        $semester = Semester::where('academic_year_id', $academicYear?->id)->first()
            ?? Semester::where('is_active', true)->first();

        if (! $academicYear || ! $semester) {
            echo "[FAIL] Tahun Ajaran atau Semester belum tersedia.\n";
            return;
        }

        // 2. Unit Pendidikan SDIT & Kurikulum SD
        $unitSdit = EducationUnit::where('code', 'SDIT-02')
            ->orWhere('code', 'SDIT-01')
            ->orWhere('name', 'like', '%SDIT%')
            ->first() ?? EducationUnit::first();

        $kurikulum = MasterKurikulum::where('jenjang', 'SD')
            ->orWhere('kode_kurikulum', 'like', '%SD%')
            ->first() ?? MasterKurikulum::first();

        // 3. Rombel Sasaran (Kelas 1 SDIT Tematik)
        $rombel = Kelas::where('unit_pendidikan_id', $unitSdit?->id)
            ->where(function ($q) {
                $q->where('tingkat', '1')
                  ->orWhere('nama_kelas', 'like', '%Kelas 1%')
                  ->orWhere('kode_kelas', 'like', '%SD%1%');
            })->first();

        if (! $rombel) {
            $rombel = Kelas::create([
                'unit_pendidikan_id' => $unitSdit?->id,
                'tahun_ajaran_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'kode_kelas' => 'SD2-1A-MAKKAH',
                'nama_kelas' => 'Kelas 1 Makkah (Full Day)',
                'tingkat' => '1',
                'jenjang' => 'SDIT',
                'kapasitas' => 28,
                'ruangan' => 'Ruang Kelas 1A (Gedung Utama Lt. 1)',
                'status' => 'Aktif',
            ]);
        }

        $schoolClass = SchoolClass::firstOrCreate([
            'name' => $rombel->nama_kelas,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
        ], [
            'level' => '1',
            'metadata' => ['status' => 'active'],
        ]);

        // 4. Guru-guru Sesuai Roster Riil
        // Pastikan guru wali kelas dan guru bidang studi tersedia
        $guruRoster = [
            'Walas'         => ['nama' => 'Ustzh. Khadijah Azzahra, S.Pd', 'niy' => 'GUR-SD-WALAS01'],
            'Ust. Fauzan'   => ['nama' => 'Ust. Fauzan Azhim, Lc.',        'niy' => 'GUR-SD-BARAB01'],
            'Ustz. Aisyah'  => ['nama' => 'Zulfa Nur Hamidah S.Pd.I.',      'niy' => 'Y-2017-245'],
            'Ustz. Intan'   => ['nama' => 'Ustzh. Intan Permatasari, S.Pd','niy' => 'GUR-SD-THF02'],
            'Ust. Maulana'  => ['nama' => 'Ust. Maulana Malik, M.Pd',      'niy' => 'GUR-SD-BING01'],
            'Ust. Didi'     => ['nama' => 'Ust. Didi Wahyudi, S.Pd.I',     'niy' => 'GUR-SD-PAI01'],
            'Ust. Igen'     => ['nama' => 'Ust. Igen Saputra, S.Pd',       'niy' => 'GUR-SD-PJOK01'],
        ];

        $guruModels = [];
        foreach ($guruRoster as $key => $gData) {
            $emp = Employee::where('nama_lengkap', $gData['nama'])
                ->orWhere('niy', $gData['niy'])
                ->first();

            $email = strtolower(Str::slug($key, '.')) . '@dareliman.sch.id';

            if (! $emp) {
                $emp = Employee::create([
                    'nama_lengkap' => $gData['nama'],
                    'nama_panggilan' => explode(' ', $gData['nama'])[1] ?? 'Guru',
                    'niy' => $gData['niy'],
                    'email' => $email,
                    'unit_id' => $unitSdit?->id,
                    'status' => 'Aktif',
                    'status_pegawai' => 'Tetap',
                    'jenis_kelamin' => str_contains($gData['nama'], 'Ustzh') ? 'P' : 'L',
                ]);
            }

            // DEF-ORPHAN-002 FIX: Ensure user exists and is linked
            if (! $emp->user_id) {
                $user = User::firstOrCreate(
                    ['email' => $emp->email ?: $email],
                    [
                        'name' => $emp->nama_lengkap,
                        'password' => Hash::make('Password123!'),
                        'is_active' => true,
                    ]
                );
                $role = ($key === 'Walas') ? 'Wali Kelas' : 'Guru';
                $user->syncRoles([$role]);
                $emp->update(['user_id' => $user->id]);
            }

            $guruModels[$key] = $emp;
        }

        // Set Walas ke Rombel
        if (! $rombel->wali_kelas_id) {
            $rombel->update(['wali_kelas_id' => $guruModels['Walas']->id]);
        }

        // 5. Master Mata Pelajaran Tematik SDIT
        $subjectsData = [
            'Do\'a - Hadits' => [
                'kode' => 'MAPEL-DOAHADITS-SD',
                'nama' => 'Do\'a & Hadits Pilihan',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#10B981',
                'default_guru' => 'Walas',
            ],
            'B.Indonesia' => [
                'kode' => 'MAPEL-BINDO-SD',
                'nama' => 'Bahasa Indonesia',
                'kategori' => 'Wajib',
                'warna' => '#2563EB',
                'default_guru' => 'Walas',
            ],
            'Keminangkabauan' => [
                'kode' => 'MAPEL-MINANG-SD',
                'nama' => 'Keminangkabauan (Mulok)',
                'kategori' => 'Muatan Lokal',
                'warna' => '#D97706',
                'default_guru' => 'Walas',
            ],
            'B.Arab' => [
                'kode' => 'MAPEL-BARAB-SD',
                'nama' => 'Bahasa Arab',
                'kategori' => 'Kekhasan SIT',
                'warna' => '#059669',
                'default_guru' => 'Ust. Fauzan',
            ],
            'SENI' => [
                'kode' => 'MAPEL-SENI-SD',
                'nama' => 'Seni Budaya & Prakarya',
                'kategori' => 'Wajib',
                'warna' => '#A855F7',
                'default_guru' => 'Walas',
            ],
            'Dirosah' => [
                'kode' => 'MAPEL-DIROSAH-SD',
                'nama' => 'Dirosah Islamiyyah',
                'kategori' => 'Kekhasan SIT',
                'warna' => '#0D9488',
                'default_guru' => 'Ust. Didi',
            ],
            'MTK' => [
                'kode' => 'MAPEL-MTK-SD',
                'nama' => 'Matematika',
                'kategori' => 'Wajib',
                'warna' => '#EAB308',
                'default_guru' => 'Walas',
            ],
            'Tahsin/Tahfizh' => [
                'kode' => 'MAPEL-TAHFIZH-SD',
                'nama' => 'Tahsin & Tahfizh Al-Qur\'an',
                'kategori' => 'Tahfizh/Diniyah',
                'warna' => '#1E8E5A',
                'default_guru' => 'Ustz. Aisyah',
            ],
            'B.Inggris' => [
                'kode' => 'MAPEL-BING-SD',
                'nama' => 'Bahasa Inggris',
                'kategori' => 'Wajib',
                'warna' => '#0284C7',
                'default_guru' => 'Ust. Maulana',
            ],
            'Baca Tulis' => [
                'kode' => 'MAPEL-CALISTUNG-SD',
                'nama' => 'Baca Tulis (Calistung)',
                'kategori' => 'Wajib',
                'warna' => '#6366F1',
                'default_guru' => 'Walas',
            ],
            'PAI' => [
                'kode' => 'MAPEL-PAI-SD',
                'nama' => 'Pendidikan Agama Islam',
                'kategori' => 'Wajib',
                'warna' => '#15803D',
                'default_guru' => 'Ust. Didi',
            ],
            'Pend. Pancasila' => [
                'kode' => 'MAPEL-PPKN-SD',
                'nama' => 'Pendidikan Pancasila',
                'kategori' => 'Wajib',
                'warna' => '#DC2626',
                'default_guru' => 'Walas',
            ],
            'PJOK' => [
                'kode' => 'MAPEL-PJOK-SD',
                'nama' => 'Pendidikan Jasmani & Olahraga',
                'kategori' => 'Wajib',
                'warna' => '#F59E0B',
                'default_guru' => 'Ust. Igen',
            ],
        ];

        $subjectModels = [];
        foreach ($subjectsData as $key => $sInfo) {
            $sub = Subject::where('kode_mapel', $sInfo['kode'])
                ->orWhere('name', $sInfo['nama'])
                ->first();

            $assignedTeacher = $guruModels[$sInfo['default_guru']] ?? $guruModels['Walas'];

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
                    'unit_pendidikan_id' => $unitSdit?->id,
                    'kurikulum_id' => $kurikulum?->id,
                    'guru_pengampu_id' => $assignedTeacher->id,
                    'jam_pelajaran' => 4,
                    'kkm' => 75.00,
                    'warna' => $sInfo['warna'],
                    'status' => true,
                ]);
            }
            $subjectModels[$key] = $sub;
        }

        // 6. Matriks Slot KBM Resmi SDIT (Senin - Jumat)
        // Format slot KBM (hanya yang memiliki mapel akademik):
        // Slot 2: 07.35-08.10 (Jumat: 08.00-08.30)
        // Slot 3: 08.10-08.45 (Jumat: 08.30-09.00)
        // Slot 4: 08.45-09.20 (Jumat: 09.00-09.30)
        // Slot 8: 10.00-10.35
        // Slot 9: 10.35-11.10
        // Slot 10: 11.10-11.45
        // Slot 14: 13.30-14.00
        // Slot 15: 14.00-14.30
        // Slot 16: 14.30-15.00
        // Slot 17: 15.00-15.30
        $kbmMatrix = [
            // SENIN (Day 1)
            1 => [
                2  => ['mapel' => 'Do\'a - Hadits',  'guru' => 'Walas',        'start' => '07:35:00', 'end' => '08:10:00'],
                3  => ['mapel' => 'B.Indonesia',     'guru' => 'Walas',        'start' => '08:10:00', 'end' => '08:45:00'],
                4  => ['mapel' => 'B.Indonesia',     'guru' => 'Walas',        'start' => '08:45:00', 'end' => '09:20:00'],
                8  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Intan',  'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '11:10:00', 'end' => '11:45:00'],
                14 => ['mapel' => 'B.Inggris',       'guru' => 'Ust. Maulana', 'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['mapel' => 'B.Inggris',       'guru' => 'Ust. Maulana', 'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['mapel' => 'Pend. Pancasila', 'guru' => 'Walas',        'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['mapel' => 'Pend. Pancasila', 'guru' => 'Walas',        'start' => '15:00:00', 'end' => '15:30:00'],
            ],
            // SELASA (Day 2)
            2 => [
                2  => ['mapel' => 'Keminangkabauan', 'guru' => 'Walas',        'start' => '07:35:00', 'end' => '08:10:00'],
                3  => ['mapel' => 'B.Arab',          'guru' => 'Ust. Fauzan',  'start' => '08:10:00', 'end' => '08:45:00'],
                4  => ['mapel' => 'B.Arab',          'guru' => 'Ust. Fauzan',  'start' => '08:45:00', 'end' => '09:20:00'],
                8  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Intan',  'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '11:10:00', 'end' => '11:45:00'],
                14 => ['mapel' => 'Baca Tulis',      'guru' => 'Walas',        'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['mapel' => 'Baca Tulis',      'guru' => 'Walas',        'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['mapel' => 'MTK',             'guru' => 'Walas',        'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['mapel' => 'MTK',             'guru' => 'Walas',        'start' => '15:00:00', 'end' => '15:30:00'],
            ],
            // RABU (Day 3)
            3 => [
                2  => ['mapel' => 'SENI',            'guru' => 'Walas',        'start' => '07:35:00', 'end' => '08:10:00'],
                3  => ['mapel' => 'SENI',            'guru' => 'Walas',        'start' => '08:10:00', 'end' => '08:45:00'],
                4  => ['mapel' => 'Dirosah',         'guru' => 'Walas',        'start' => '08:45:00', 'end' => '09:20:00'],
                8  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Intan',  'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '11:10:00', 'end' => '11:45:00'],
                14 => ['mapel' => 'Dirosah',         'guru' => 'Ust. Didi',    'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['mapel' => 'Dirosah',         'guru' => 'Ust. Didi',    'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['mapel' => 'Pend. Pancasila', 'guru' => 'Walas',        'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['mapel' => 'Pend. Pancasila', 'guru' => 'Walas',        'start' => '15:00:00', 'end' => '15:30:00'],
            ],
            // KAMIS (Day 4)
            4 => [
                2  => ['mapel' => 'Do\'a - Hadits',  'guru' => 'Walas',        'start' => '07:35:00', 'end' => '08:10:00'],
                3  => ['mapel' => 'B.Indonesia',     'guru' => 'Walas',        'start' => '08:10:00', 'end' => '08:45:00'],
                4  => ['mapel' => 'B.Indonesia',     'guru' => 'Walas',        'start' => '08:45:00', 'end' => '09:20:00'],
                8  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Intan',  'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '11:10:00', 'end' => '11:45:00'],
                14 => ['mapel' => 'PAI',             'guru' => 'Ust. Didi',    'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['mapel' => 'PAI',             'guru' => 'Ust. Didi',    'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['mapel' => 'Baca Tulis',      'guru' => 'Walas',        'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['mapel' => 'Baca Tulis',      'guru' => 'Walas',        'start' => '15:00:00', 'end' => '15:30:00'],
            ],
            // JUMAT (Day 5) - Jadwal waktu KBM disesuaikan dengan sesi Jumat
            5 => [
                2  => ['mapel' => 'MTK',             'guru' => 'Walas',        'start' => '08:00:00', 'end' => '08:30:00'],
                3  => ['mapel' => 'MTK',             'guru' => 'Walas',        'start' => '08:30:00', 'end' => '09:00:00'],
                4  => ['mapel' => 'MTK',             'guru' => 'Walas',        'start' => '09:00:00', 'end' => '09:30:00'],
                8  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '10:00:00', 'end' => '10:35:00'],
                9  => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Intan',  'start' => '10:35:00', 'end' => '11:10:00'],
                10 => ['mapel' => 'Tahsin/Tahfizh',  'guru' => 'Ustz. Aisyah', 'start' => '11:10:00', 'end' => '11:45:00'],
                14 => ['mapel' => 'SENI',            'guru' => 'Walas',        'start' => '13:30:00', 'end' => '14:00:00'],
                15 => ['mapel' => 'SENI',            'guru' => 'Walas',        'start' => '14:00:00', 'end' => '14:30:00'],
                16 => ['mapel' => 'PJOK',            'guru' => 'Ust. Igen',    'start' => '14:30:00', 'end' => '15:00:00'],
                17 => ['mapel' => 'PJOK',            'guru' => 'Ust. Igen',    'start' => '15:00:00', 'end' => '15:30:00'],
            ],
            // SABTU (Day 6) - Ekstrakurikuler Pagi (Silat, Karate, Mewarnai)
            6 => [
                1 => ['mapel' => 'SENI', 'guru' => 'Walas', 'start' => '08:00:00', 'end' => '09:30:00', 'label' => 'Ekstrakurikuler: Mewarnai & Seni Kaligrafi'],
                2 => ['mapel' => 'PJOK', 'guru' => 'Ust. Igen', 'start' => '09:45:00', 'end' => '11:15:00', 'label' => 'Ekstrakurikuler: Tapak Suci (Silat) & Karate'],
            ],
        ];

        // 7. Bersihkan Jadwal Lama Rombel Ini
        ClassSchedule::where('kelas_id', $rombel->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('semester_id', $semester->id)
            ->delete();

        // 8. Masukkan Jadwal Baru ke Database
        $totalInserted = 0;
        DB::beginTransaction();
        try {
            foreach ($kbmMatrix as $day => $slots) {
                foreach ($slots as $slotNum => $item) {
                    $subModel = $subjectModels[$item['mapel']] ?? null;
                    $teacherModel = $guruModels[$item['guru']] ?? $guruModels['Walas'];

                    ClassSchedule::create([
                        'id' => (string) Str::uuid(),
                        'kelas_id' => $rombel->id,
                        'class_id' => $schoolClass->id,
                        'employee_id' => $teacherModel->id,
                        'teacher_id' => null,
                        'subject_id' => $subModel?->id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semester->id,
                        'day_of_week' => $day,
                        'time_start' => $item['start'],
                        'time_end' => $item['end'],
                        'week_type' => 'all',
                        'is_active' => true,
                        'metadata' => [
                            'slot_ke' => $slotNum,
                            'rombel_nama' => $rombel->nama_kelas,
                            'guru_roster' => $item['guru'],
                            'mapel_label' => $item['mapel'],
                            'keterangan' => $item['label'] ?? 'KBM Tematik SDIT Full Day',
                            'source' => 'RosterResmiSDIT_FullDay',
                        ],
                    ]);

                    $totalInserted++;
                }
            }

            DB::commit();
            echo "[SUKSES] Berhasil men-generate {$totalInserted} jadwal KBM resmi SDIT Full Day (Kelas 1) T.P 2026/2027.\n";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "[ERROR] Gagal men-generate jadwal SDIT: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
