<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\MasterKurikulum;
use App\Models\Position;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder Khusus:
 * Mengisi Guru Mapel, Guru Wali, dan Guru Pengampu di:
 * 1. Unit Boarding: PONPES Putra Dar el-Iman (PONPES-PA)
 * 2. Unit Fullday: SDIT 1 Dar el-Iman - 50 Kota (SDIT-01)
 * Termasuk Mata Pelajaran, Relasi Guru Pengampu (subject_teachers),
 * Alokasi Kelas (subject_classes), dan Jadwal Pelajaran (class_schedules).
 */
class SampleGuruMapelWaliPengampuSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        // 1. Ambil Tahun Ajaran & Semester Aktif
        $academicYear = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::firstOrCreate(
                ['name' => '2026/2027'],
                ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]
            );

        $semester = Semester::where('academic_year_id', $academicYear->id)->where('is_active', true)->first()
            ?? Semester::firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'sequence' => 1],
                ['name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]
            );

        // Jabatan
        $posWali = Position::where('name', 'like', '%Wali Kelas%')->first();
        $posMapel = Position::where('name', 'like', '%Guru Mapel%')->first();
        $posTahfizh = Position::where('name', 'like', '%Guru Tahfizh%')->first();
        $posMusyrif = Position::where('name', 'like', '%Musyrif%')->first();

        // Kurikulum
        $kurikulumSmp = MasterKurikulum::where('jenjang', 'SMP')->first()
            ?? MasterKurikulum::firstOrCreate(
                ['kode_kurikulum' => 'KUR-PONPES-2026'],
                ['nama_kurikulum' => 'Kurikulum Pesantren & Tahfizh SIT', 'jenis_kurikulum' => 'SIT', 'jenjang' => 'SMP', 'status' => true]
            );

        $kurikulumSd = MasterKurikulum::where('jenjang', 'SD')->first()
            ?? MasterKurikulum::firstOrCreate(
                ['kode_kurikulum' => 'KUR-SDIT-2026'],
                ['nama_kurikulum' => 'Kurikulum Merdeka SDIT Terpadu', 'jenis_kurikulum' => 'SIT', 'jenjang' => 'SD', 'status' => true]
            );

        // Helper untuk registrasi Pegawai / Guru
        $createTeacherEmployee = function ($data, $unit) use ($defaultPassword) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => $defaultPassword,
                    'is_active' => true,
                ]
            );

            // Sync role Spatie
            if (! empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            $employee = Employee::updateOrCreate(
                ['email' => $data['email']],
                [
                    'user_id' => $user->id,
                    'niy' => $data['niy'],
                    'nama_lengkap' => $data['name'],
                    'jenis_kelamin' => $data['gender'],
                    'unit_id' => $unit->id,
                    'jabatan_id' => $data['position_id'] ?? null,
                    'status_pegawai' => 'Tetap',
                    'status' => 'Aktif',
                    'no_hp' => $data['phone'],
                    'alamat' => $data['address'] ?? 'Kota Padang, Sumatera Barat',
                    'agama' => 'Islam',
                    'metadata' => [
                        'spesialisasi' => $data['specialty'] ?? 'Pendidik',
                    ],
                ]
            );

            // Mirror ke tabel legacy teachers
            Teacher::withTrashed()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'user_id' => $user->id,
                    'employee_id' => $employee->id,
                    'employee_number' => $data['niy'],
                    'full_name' => $data['name'],
                    'phone' => $data['phone'],
                    'join_date' => '2024-07-01',
                    'metadata' => ['unit_id' => $unit->id],
                ]
            );

            return $employee;
        };

        // =========================================================================
        // 1. UNIT BOARDING: PONPES Putra Dar el-Iman (PONPES-PA)
        // =========================================================================
        $boardingUnit = EducationUnit::where('code', 'PONPES-PA')->first();
        if (! $boardingUnit) {
            $this->command->error("Unit PONPES-PA tidak ditemukan!");
            return;
        }

        $kelasBoarding = Kelas::where('kode_kelas', 'PONPES-7A')->first();

        // 1.A Guru Wali & Musyrif Ponpes
        $waliPonpes = $createTeacherEmployee([
            'niy' => 'NIY-PONPES-001',
            'name' => 'Ust. Ahmad Al-Hafizh, S.Pd.I',
            'email' => 'wali.ponpes7a@dareliman.sch.id',
            'phone' => '081270010001',
            'gender' => 'L',
            'roles' => ['Wali Kelas', 'Musyrif', 'Guru'],
            'position_id' => $posWali?->id ?? $posMusyrif?->id,
            'specialty' => 'Fiqih & Pengasuhan Asrama',
        ], $boardingUnit);

        // Update wali kelas pada tbl_kelas
        if ($kelasBoarding) {
            $kelasBoarding->update(['wali_kelas_id' => $waliPonpes->id]);
        }

        // 1.B Guru-guru Mapel & Pengampu Ponpes
        $gurusBoarding = [
            'fiqih' => $waliPonpes,
            'tahfizh' => $createTeacherEmployee([
                'niy' => 'NIY-PONPES-002',
                'name' => 'Ust. Muhammad Ilham, Lc., Al-Hafizh',
                'email' => 'guru.tahfizh.ponpes@dareliman.sch.id',
                'phone' => '081270010002',
                'gender' => 'L',
                'roles' => ['Guru Tahfizh', 'Guru'],
                'position_id' => $posTahfizh?->id,
                'specialty' => 'Tahfizh & Qira\'at Al-Qur\'an',
            ], $boardingUnit),
            'lughoh' => $createTeacherEmployee([
                'niy' => 'NIY-PONPES-003',
                'name' => 'Ust. Farhan Al-Manshuri, Lc., M.A.',
                'email' => 'guru.barab.ponpes@dareliman.sch.id',
                'phone' => '081270010003',
                'gender' => 'L',
                'roles' => ['Guru Mata Pelajaran', 'Guru'],
                'position_id' => $posMapel?->id,
                'specialty' => 'Bahasa Arab & Nahwu Sharaf',
            ], $boardingUnit),
            'aqidah' => $createTeacherEmployee([
                'niy' => 'NIY-PONPES-004',
                'name' => 'Ust. Zulfikar Al-Atsary, S.Pd.I.',
                'email' => 'guru.aqidah.ponpes@dareliman.sch.id',
                'phone' => '081270010004',
                'gender' => 'L',
                'roles' => ['Guru Mata Pelajaran', 'Guru'],
                'position_id' => $posMapel?->id,
                'specialty' => 'Aqidah & Akhlak Islami',
            ], $boardingUnit),
            'mtk' => $createTeacherEmployee([
                'niy' => 'NIY-PONPES-005',
                'name' => 'Ust. Rahmat Hidayat, S.Pd.',
                'email' => 'guru.mtk.ponpes@dareliman.sch.id',
                'phone' => '081270010005',
                'gender' => 'L',
                'roles' => ['Guru Mata Pelajaran', 'Guru'],
                'position_id' => $posMapel?->id,
                'specialty' => 'Matematika Terpadu SMP',
            ], $boardingUnit),
            'bing' => $createTeacherEmployee([
                'niy' => 'NIY-PONPES-006',
                'name' => 'Ust. Dedi Kurniawan, S.Pd., M.Ed.',
                'email' => 'guru.bing.ponpes@dareliman.sch.id',
                'phone' => '081270010006',
                'gender' => 'L',
                'roles' => ['Guru Mata Pelajaran', 'Guru'],
                'position_id' => $posMapel?->id,
                'specialty' => 'Bahasa Inggris & Public Speaking',
            ], $boardingUnit),
        ];

        // 1.C Daftar Mata Pelajaran Ponpes Boarding
        $subjectsBoardingData = [
            [
                'kode' => 'MAPEL-THF-PONPES-7',
                'nama' => 'Tahfizh & Tahsin Al-Qur\'an',
                'singkat' => 'TAHFIZH',
                'kelompok' => 'Kekhasan Pesantren',
                'kategori' => 'Tahfizh/Diniyah',
                'jam' => 6,
                'kkm' => 80,
                'guru' => $gurusBoarding['tahfizh'],
                'warna' => '#10B981',
                'ikon' => 'BookOpenCheck',
                'day' => 1, // Senin
                'start' => '07:30:00',
                'end' => '09:00:00',
            ],
            [
                'kode' => 'MAPEL-BAR-PONPES-7',
                'nama' => 'Bahasa Arab & Nahwu Sharaf',
                'singkat' => 'B.ARAB',
                'kelompok' => 'Kekhasan Pesantren',
                'kategori' => 'Diniyah',
                'jam' => 4,
                'kkm' => 75,
                'guru' => $gurusBoarding['lughoh'],
                'warna' => '#059669',
                'ikon' => 'BookOpen',
                'day' => 2, // Selasa
                'start' => '07:30:00',
                'end' => '09:00:00',
            ],
            [
                'kode' => 'MAPEL-AQD-PONPES-7',
                'nama' => 'Aqidah & Akhlak Islami',
                'singkat' => 'AQIDAH',
                'kelompok' => 'Kelompok A',
                'kategori' => 'Diniyah',
                'jam' => 3,
                'kkm' => 78,
                'guru' => $gurusBoarding['aqidah'],
                'warna' => '#0D9488',
                'ikon' => 'ShieldCheck',
                'day' => 3, // Rabu
                'start' => '07:30:00',
                'end' => '09:00:00',
            ],
            [
                'kode' => 'MAPEL-FQH-PONPES-7',
                'nama' => 'Fiqih Ibadah & Muamalah',
                'singkat' => 'FIQIH',
                'kelompok' => 'Kelompok A',
                'kategori' => 'Diniyah',
                'jam' => 3,
                'kkm' => 78,
                'guru' => $gurusBoarding['fiqih'],
                'warna' => '#047857',
                'ikon' => 'HeartHandshake',
                'day' => 4, // Kamis
                'start' => '07:30:00',
                'end' => '09:00:00',
            ],
            [
                'kode' => 'MAPEL-MTK-PONPES-7',
                'nama' => 'Matematika Terpadu',
                'singkat' => 'MTK',
                'kelompok' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jam' => 4,
                'kkm' => 75,
                'guru' => $gurusBoarding['mtk'],
                'warna' => '#0284C7',
                'ikon' => 'Calculator',
                'day' => 5, // Jumat
                'start' => '07:30:00',
                'end' => '09:00:00',
            ],
            [
                'kode' => 'MAPEL-BIG-PONPES-7',
                'nama' => 'Bahasa Inggris Terpadu',
                'singkat' => 'B.ING',
                'kelompok' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jam' => 4,
                'kkm' => 75,
                'guru' => $gurusBoarding['bing'],
                'warna' => '#4F46E5',
                'ikon' => 'Globe',
                'day' => 1, // Senin siang
                'start' => '09:30:00',
                'end' => '11:00:00',
            ],
        ];

        foreach ($subjectsBoardingData as $s) {
            $subject = Subject::updateOrCreate(
                ['kode_mapel' => $s['kode']],
                [
                    'unit_pendidikan_id' => $boardingUnit->id,
                    'kurikulum_id' => $kurikulumSmp->id,
                    'nama_mapel' => $s['nama'],
                    'nama_singkat' => $s['singkat'],
                    'code' => $s['kode'],
                    'name' => $s['nama'],
                    'kelompok_mapel' => $s['kelompok'],
                    'kategori' => $s['kategori'],
                    'jenjang' => 'SMP',
                    'tingkat_kelas' => '7',
                    'jam_pelajaran' => $s['jam'],
                    'guru_pengampu_id' => $s['guru']->id,
                    'kkm' => $s['kkm'],
                    'bobot_pengetahuan' => 40,
                    'bobot_keterampilan' => 40,
                    'bobot_sikap' => 20,
                    'bobot_nilai' => ['pengetahuan' => 40, 'keterampilan' => 40, 'sikap' => 20],
                    'warna' => $s['warna'],
                    'ikon' => $s['ikon'],
                    'status' => true,
                    'deskripsi' => "Mata pelajaran {$s['nama']} jenjang SMP / Boarding Pesantren.",
                ]
            );

            // Hubungkan ke subject_teachers (Guru Pengampu)
            DB::table('subject_teachers')->updateOrInsert(
                ['subject_id' => $subject->id, 'guru_id' => $s['guru']->id],
                ['is_utama' => true, 'updated_at' => now()]
            );

            // Hubungkan ke subject_classes & subject_rombel
            if ($kelasBoarding) {
                DB::table('subject_classes')->updateOrInsert(
                    ['subject_id' => $subject->id, 'kelas_id' => $kelasBoarding->id],
                    ['updated_at' => now()]
                );
                DB::table('subject_rombel')->updateOrInsert(
                    ['subject_id' => $subject->id, 'rombel_id' => $kelasBoarding->id],
                    ['updated_at' => now()]
                );

                // Buatkan Jadwal Pelajaran (class_schedules)
                ClassSchedule::updateOrCreate(
                    [
                        'kelas_id' => $kelasBoarding->id,
                        'subject_id' => $subject->id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semester->id,
                    ],
                    [
                        'class_id' => $kelasBoarding->id,
                        'employee_id' => $s['guru']->id,
                        'day_of_week' => $s['day'],
                        'time_start' => $s['start'],
                        'time_end' => $s['end'],
                        'week_type' => 'regular',
                        'is_active' => true,
                        'metadata' => [
                            'unit' => $boardingUnit->name,
                            'guru' => $s['guru']->nama_lengkap,
                        ],
                    ]
                );
            }
        }

        // =========================================================================
        // 2. UNIT FULLDAY: SDIT 1 Dar el-Iman - 50 Kota (SDIT-01)
        // =========================================================================
        $fulldayUnit = EducationUnit::where('code', 'SDIT-01')->first();
        if (! $fulldayUnit) {
            $this->command->error("Unit SDIT-01 tidak ditemukan!");
            return;
        }

        $kelasFullday = Kelas::where('kode_kelas', 'SDIT1-1A')->first();

        // 2.A Guru Wali Kelas SDIT
        $waliSdit = $createTeacherEmployee([
            'niy' => 'NIY-SDIT1-001',
            'name' => 'Usth. Maryam Safitri, S.Pd.',
            'email' => 'wali.sdit1a@dareliman.sch.id',
            'phone' => '081370020001',
            'gender' => 'P',
            'roles' => ['Wali Kelas', 'Guru'],
            'position_id' => $posWali?->id,
            'specialty' => 'Pendidikan Guru SD (PGSD) & Tematik',
        ], $fulldayUnit);

        // Update wali kelas pada tbl_kelas
        if ($kelasFullday) {
            $kelasFullday->update(['wali_kelas_id' => $waliSdit->id]);
        }

        // 2.B Guru-guru Mapel & Pengampu SDIT
        $gurusFullday = [
            'tematik' => $waliSdit,
            'pai' => $createTeacherEmployee([
                'niy' => 'NIY-SDIT1-002',
                'name' => 'Ust. Salman Al-Farisi, S.Pd.I.',
                'email' => 'guru.pai.sdit1@dareliman.sch.id',
                'phone' => '081370020002',
                'gender' => 'L',
                'roles' => ['Guru PAI', 'Guru'],
                'position_id' => $posMapel?->id,
                'specialty' => 'Pendidikan Agama Islam SD',
            ], $fulldayUnit),
            'tahfizh' => $createTeacherEmployee([
                'niy' => 'NIY-SDIT1-003',
                'name' => 'Usth. Fatimah Az-Zahra, S.Pd.',
                'email' => 'guru.tahfizh.sdit1@dareliman.sch.id',
                'phone' => '081370020003',
                'gender' => 'P',
                'roles' => ['Guru Tahfizh', 'Guru'],
                'position_id' => $posTahfizh?->id,
                'specialty' => 'Tahfizh Juz 30 Anak & Adab Islami',
            ], $fulldayUnit),
            'mtk' => $createTeacherEmployee([
                'niy' => 'NIY-SDIT1-004',
                'name' => 'Usth. Nurul Aini, S.Pd.',
                'email' => 'guru.mtk.sdit1@dareliman.sch.id',
                'phone' => '081370020004',
                'gender' => 'P',
                'roles' => ['Guru Mata Pelajaran', 'Guru'],
                'position_id' => $posMapel?->id,
                'specialty' => 'Matematika Ceria Kelas Rendah',
            ], $fulldayUnit),
            'bind' => $createTeacherEmployee([
                'niy' => 'NIY-SDIT1-005',
                'name' => 'Usth. Khadijah Putri, S.Pd.',
                'email' => 'guru.bind.sdit1@dareliman.sch.id',
                'phone' => '081370020005',
                'gender' => 'P',
                'roles' => ['Guru Mata Pelajaran', 'Guru'],
                'position_id' => $posMapel?->id,
                'specialty' => 'Literasi Bahasa Indonesia Terpadu',
            ], $fulldayUnit),
            'barab' => $createTeacherEmployee([
                'niy' => 'NIY-SDIT1-006',
                'name' => 'Ust. Fauzan Adhim, S.Pd.I.',
                'email' => 'guru.barab.sdit1@dareliman.sch.id',
                'phone' => '081370020006',
                'gender' => 'L',
                'roles' => ['Guru Mata Pelajaran', 'Guru'],
                'position_id' => $posMapel?->id,
                'specialty' => 'Bahasa Arab Dasar SDIT',
            ], $fulldayUnit),
        ];

        // 2.C Daftar Mata Pelajaran SDIT Fullday
        $subjectsFulldayData = [
            [
                'kode' => 'MAPEL-PAI-SD-1',
                'nama' => 'Pendidikan Agama Islam (PAI)',
                'singkat' => 'PAI',
                'kelompok' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jam' => 4,
                'kkm' => 75,
                'guru' => $gurusFullday['pai'],
                'warna' => '#0E5C44',
                'ikon' => 'BookOpen',
                'day' => 1, // Senin
                'start' => '08:00:00',
                'end' => '09:30:00',
            ],
            [
                'kode' => 'MAPEL-THF-SD-1',
                'nama' => 'Tahfizh Al-Qur\'an Juz 30',
                'singkat' => 'TAHFIZH',
                'kelompok' => 'Kekhasan SIT',
                'kategori' => 'Tahfizh/Diniyah',
                'jam' => 6,
                'kkm' => 80,
                'guru' => $gurusFullday['tahfizh'],
                'warna' => '#1E8E5A',
                'ikon' => 'Sparkles',
                'day' => 2, // Selasa
                'start' => '08:00:00',
                'end' => '09:30:00',
            ],
            [
                'kode' => 'MAPEL-TMT-SD-1',
                'nama' => 'Pendidikan Pancasila & Tematik',
                'singkat' => 'TEMATIK',
                'kelompok' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jam' => 5,
                'kkm' => 75,
                'guru' => $gurusFullday['tematik'],
                'warna' => '#3B82F6',
                'ikon' => 'Users',
                'day' => 3, // Rabu
                'start' => '08:00:00',
                'end' => '09:30:00',
            ],
            [
                'kode' => 'MAPEL-MTK-SD-1',
                'nama' => 'Matematika Dasar Ceria',
                'singkat' => 'MTK',
                'kelompok' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jam' => 4,
                'kkm' => 75,
                'guru' => $gurusFullday['mtk'],
                'warna' => '#F59E0B',
                'ikon' => 'Calculator',
                'day' => 4, // Kamis
                'start' => '08:00:00',
                'end' => '09:30:00',
            ],
            [
                'kode' => 'MAPEL-BIN-SD-1',
                'nama' => 'Bahasa Indonesia Terpadu',
                'singkat' => 'B.INDO',
                'kelompok' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jam' => 4,
                'kkm' => 75,
                'guru' => $gurusFullday['bind'],
                'warna' => '#6366F1',
                'ikon' => 'BookMarked',
                'day' => 5, // Jumat
                'start' => '08:00:00',
                'end' => '09:30:00',
            ],
            [
                'kode' => 'MAPEL-BAR-SD-1',
                'nama' => 'Bahasa Arab Dasar (Kekhasan SIT)',
                'singkat' => 'B.ARAB',
                'kelompok' => 'Kekhasan SIT',
                'kategori' => 'Muatan Lokal SIT',
                'jam' => 2,
                'kkm' => 75,
                'guru' => $gurusFullday['barab'],
                'warna' => '#14B8A6',
                'ikon' => 'Languages',
                'day' => 1, // Senin siang
                'start' => '10:00:00',
                'end' => '11:30:00',
            ],
        ];

        foreach ($subjectsFulldayData as $s) {
            $subject = Subject::updateOrCreate(
                ['kode_mapel' => $s['kode']],
                [
                    'unit_pendidikan_id' => $fulldayUnit->id,
                    'kurikulum_id' => $kurikulumSd->id,
                    'nama_mapel' => $s['nama'],
                    'nama_singkat' => $s['singkat'],
                    'code' => $s['kode'],
                    'name' => $s['nama'],
                    'kelompok_mapel' => $s['kelompok'],
                    'kategori' => $s['kategori'],
                    'jenjang' => 'SD',
                    'tingkat_kelas' => '1',
                    'jam_pelajaran' => $s['jam'],
                    'guru_pengampu_id' => $s['guru']->id,
                    'kkm' => $s['kkm'],
                    'bobot_pengetahuan' => 40,
                    'bobot_keterampilan' => 40,
                    'bobot_sikap' => 20,
                    'bobot_nilai' => ['pengetahuan' => 40, 'keterampilan' => 40, 'sikap' => 20],
                    'warna' => $s['warna'],
                    'ikon' => $s['ikon'],
                    'status' => true,
                    'deskripsi' => "Mata pelajaran {$s['nama']} jenjang SDIT Fullday.",
                ]
            );

            // Hubungkan ke subject_teachers (Guru Pengampu)
            DB::table('subject_teachers')->updateOrInsert(
                ['subject_id' => $subject->id, 'guru_id' => $s['guru']->id],
                ['is_utama' => true, 'updated_at' => now()]
            );

            // Hubungkan ke subject_classes & subject_rombel
            if ($kelasFullday) {
                DB::table('subject_classes')->updateOrInsert(
                    ['subject_id' => $subject->id, 'kelas_id' => $kelasFullday->id],
                    ['updated_at' => now()]
                );
                DB::table('subject_rombel')->updateOrInsert(
                    ['subject_id' => $subject->id, 'rombel_id' => $kelasFullday->id],
                    ['updated_at' => now()]
                );

                // Buatkan Jadwal Pelajaran (class_schedules)
                ClassSchedule::updateOrCreate(
                    [
                        'kelas_id' => $kelasFullday->id,
                        'subject_id' => $subject->id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semester->id,
                    ],
                    [
                        'class_id' => $kelasFullday->id,
                        'employee_id' => $s['guru']->id,
                        'day_of_week' => $s['day'],
                        'time_start' => $s['start'],
                        'time_end' => $s['end'],
                        'week_type' => 'regular',
                        'is_active' => true,
                        'metadata' => [
                            'unit' => $fulldayUnit->name,
                            'guru' => $s['guru']->nama_lengkap,
                        ],
                    ]
                );
            }
        }

        $this->command->info("Berhasil mengisi Guru Mapel, Guru Wali, Guru Pengampu, Mapel, dan Jadwal Pelajaran untuk PONPES-PA (Boarding) dan SDIT-01 (Fullday)!");
    }
}
