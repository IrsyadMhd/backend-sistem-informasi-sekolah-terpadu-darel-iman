<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder Khusus:
 * Mengisi 1 unit pendidikan Boarding dan 1 unit pendidikan Fullday
 * masing-masing dengan 1 data kelas (rombel) dan data siswa/santri terpadu.
 */
class SampleBoardingFulldaySeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        // 1. Ambil / Pastikan Tahun Ajaran & Semester Aktif
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

        $roleSiswa = Role::where('name', 'Siswa')->first();
        $roleOrangTua = Role::where('name', 'Orang Tua')->first();

        // =========================================================================
        // 2. UNIT 1: BOARDING (PONPES Putra Dar el-Iman)
        // =========================================================================
        $boardingUnit = EducationUnit::where('code', 'PONPES-PA')->first();
        if (! $boardingUnit) {
            $this->command->error("Unit PONPES-PA tidak ditemukan!");
            return;
        }

        $boardingMeta = is_array($boardingUnit->metadata) ? $boardingUnit->metadata : [];
        $boardingMeta['is_boarding'] = true;
        $boardingMeta['is_pesantren'] = true;
        $boardingMeta['program_type'] = 'boarding';

        $boardingUnit->update([
            'program_type' => 'boarding',
            'jam_masuk' => '04:00',
            'jam_pulang' => '21:00',
            'jam_kegiatan' => '24 Jam (Pengasuhan Asrama & Halaqah)',
            'metadata' => $boardingMeta,
        ]);

        // Musyrif / Pembina Asrama untuk Boarding
        $musyrif = Employee::where('nama_lengkap', 'like', '%Musyrif%')->first()
            ?? Employee::where('unit_id', $boardingUnit->id)->first();

        if ($musyrif) {
            $musyrif->update(['unit_id' => $boardingUnit->id]);
        }

        // 1 Data Kelas Boarding (tbl_kelas)
        $kelasBoarding = Kelas::updateOrCreate(
            ['kode_kelas' => 'PONPES-7A'],
            [
                'unit_pendidikan_id' => $boardingUnit->id,
                'tahun_ajaran_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'jenjang' => 'Pondok Pesantren',
                'tingkat' => '7',
                'nama_kelas' => 'VII-A (Asrama Al-Farabi)',
                'wali_kelas_id' => $musyrif?->id,
                'kapasitas' => 30,
                'ruangan' => 'Kamar Asrama Al-Farabi 01',
                'status' => 'Aktif',
            ]
        );

        // Sinkronkan ke tabel legacy classes
        DB::table('classes')->updateOrInsert(
            ['id' => $kelasBoarding->id],
            [
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'name' => $kelasBoarding->nama_kelas,
                'level' => '7',
                'updated_at' => now(),
            ]
        );

        // 10 Data Santri Boarding (Ikhwan)
        $santriData = [
            [
                'nis' => '202607001',
                'nisn' => '0123450001',
                'name' => 'Muhammad Fatih Al-Ayyubi',
                'birth_place' => 'Padang Panjang',
                'birth_date' => '2013-03-15',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'H. Irfan Al-Ayyubi, S.T.',
                'mother_name' => 'Hj. Rahmawati, S.Pd.',
                'phone' => '081267890001',
                'job' => 'Wiraswasta',
                'nik_parent' => '1371011503750001',
            ],
            [
                'nis' => '202607002',
                'nisn' => '0123450002',
                'name' => 'Abdullah Azzam Pratama',
                'birth_place' => 'Bukittinggi',
                'birth_date' => '2013-05-20',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'Drs. Hendri Pratama',
                'mother_name' => 'Nurhasanah, M.Pd.',
                'phone' => '081267890002',
                'job' => 'PNS / Guru',
                'nik_parent' => '1371012005780002',
            ],
            [
                'nis' => '202607003',
                'nisn' => '0123450003',
                'name' => 'Hamzah Asadullah Al-Khattab',
                'birth_place' => 'Padang',
                'birth_date' => '2013-07-11',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'Ust. Syahril Al-Khattab, Lc.',
                'mother_name' => 'Aisyah Mardhiyah',
                'phone' => '081267890003',
                'job' => 'Dosen',
                'nik_parent' => '1371011107760003',
            ],
            [
                'nis' => '202607004',
                'nisn' => '0123450004',
                'name' => 'Salman Al-Farisi Gunawan',
                'birth_place' => 'Solok',
                'birth_date' => '2013-02-18',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'Gunawan Wibisono',
                'mother_name' => 'Ratna Juwita',
                'phone' => '081267890004',
                'job' => 'Pedagang',
                'nik_parent' => '1371011802770004',
            ],
            [
                'nis' => '202607005',
                'nisn' => '0123450005',
                'name' => 'Umar Faruq Hidayatullah',
                'birth_place' => 'Payakumbuh',
                'birth_date' => '2013-09-09',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'H. Taufiq Hidayatullah',
                'mother_name' => 'Sri Wahyuni',
                'phone' => '081267890005',
                'job' => 'Pengusaha',
                'nik_parent' => '1371010909740005',
            ],
            [
                'nis' => '202607006',
                'nisn' => '0123450006',
                'name' => 'Zaid bin Tsabit As-Salim',
                'birth_place' => 'Batusangkar',
                'birth_date' => '2013-11-25',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'Salim Mahfudz',
                'mother_name' => 'Fadhilah Umari',
                'phone' => '081267890006',
                'job' => 'Karyawan Swasta',
                'nik_parent' => '1371012511790006',
            ],
            [
                'nis' => '202607007',
                'nisn' => '0123450007',
                'name' => 'Khalid Saifullah Ramadhan',
                'birth_place' => 'Pekanbaru',
                'birth_date' => '2013-08-17',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'Ramadhan Syahputra, S.E.',
                'mother_name' => 'Marlina Fitri',
                'phone' => '081267890007',
                'job' => 'Manajer',
                'nik_parent' => '1371011708780007',
            ],
            [
                'nis' => '202607008',
                'nisn' => '0123450008',
                'name' => 'Bilal Muadzin Al-Ghifari',
                'birth_place' => 'Padang',
                'birth_date' => '2013-04-03',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'Mukhlis Al-Ghifari',
                'mother_name' => 'Yuliana Santoso',
                'phone' => '081267890008',
                'job' => 'Wiraswasta',
                'nik_parent' => '1371010304750008',
            ],
            [
                'nis' => '202607009',
                'nisn' => '0123450009',
                'name' => 'Usamah Ziyadul Khair',
                'birth_place' => 'Bukittinggi',
                'birth_date' => '2013-06-30',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'Zulfikar Ahmad',
                'mother_name' => 'Khairunnisa',
                'phone' => '081267890009',
                'job' => 'Apoteker',
                'nik_parent' => '1371013006800009',
            ],
            [
                'nis' => '202607010',
                'nisn' => '0123450010',
                'name' => 'Mus\'ab Umair Al-Fath',
                'birth_place' => 'Pariaman',
                'birth_date' => '2013-10-12',
                'address' => 'Asrama Putra Al-Farabi Kamar 01, Kompleks Ponpes Dar el-Iman',
                'parent_name' => 'Al-Fath Nasution, S.Kom.',
                'mother_name' => 'Dewi Sartika',
                'phone' => '081267890010',
                'job' => 'IT Consultant',
                'nik_parent' => '1371011210770010',
            ],
        ];

        foreach ($santriData as $s) {
            // User Akun Orang Tua
            $parentEmail = 'wali.santri.' . $s['nis'] . '@dareliman.sch.id';
            $parentUser = User::firstOrCreate(
                ['email' => $parentEmail],
                [
                    'name' => $s['parent_name'],
                    'phone' => $s['phone'],
                    'password' => $defaultPassword,
                    'is_active' => true,
                ]
            );
            if ($roleOrangTua) {
                $parentUser->syncRoles([$roleOrangTua->name]);
            }

            // Data Parent
            $parent = ParentModel::updateOrCreate(
                ['user_id' => $parentUser->id],
                [
                    'full_name' => $s['parent_name'],
                    'phone' => $s['phone'],
                    'email' => $parentEmail,
                    'occupation' => $s['job'],
                    'address' => $s['address'],
                    'nik' => $s['nik_parent'],
                    'metadata' => [
                        'nama_ayah' => $s['parent_name'],
                        'nama_ibu' => $s['mother_name'],
                        'no_wa' => $s['phone'],
                    ],
                ]
            );

            // User Akun Santri
            $studentEmail = 'santri.' . $s['nis'] . '@dareliman.sch.id';
            $studentUser = User::firstOrCreate(
                ['email' => $studentEmail],
                [
                    'name' => $s['name'],
                    'password' => $defaultPassword,
                    'is_active' => true,
                ]
            );
            if ($roleSiswa) {
                $studentUser->syncRoles([$roleSiswa->name]);
            }

            // Record Siswa (Student)
            $student = Student::updateOrCreate(
                ['nis' => $s['nis']],
                [
                    'user_id' => $studentUser->id,
                    'parent_id' => $parent->id,
                    'unit_id' => $boardingUnit->id,
                    'kelas_id' => $kelasBoarding->id,
                    'class_id' => $kelasBoarding->id,
                    'nisn' => $s['nisn'],
                    'full_name' => $s['name'],
                    'gender' => 'male',
                    'birth_place' => $s['birth_place'],
                    'birth_date' => $s['birth_date'],
                    'address' => $s['address'],
                    'is_active' => true,
                    'tahun_masuk' => 2026,
                    'metadata' => [
                        'program_type' => 'boarding',
                        'status_siswa' => 'Aktif',
                        'kamar_asrama' => 'Al-Farabi 01',
                        'gedung_asrama' => 'Gedung Al-Farabi Putra',
                        'musyrif' => $musyrif?->nama_lengkap ?? 'Ust. Ahmad Al-Hafizh',
                        'nama_ayah' => $s['parent_name'],
                        'nama_ibu' => $s['mother_name'],
                        'hp_ayah' => $s['phone'],
                        'nomor_wa_ayah' => $s['phone'],
                        'pekerjaan_ayah' => $s['job'],
                    ],
                ]
            );

            // Pivot StudentParent
            StudentParent::updateOrCreate(
                ['student_id' => $student->id, 'parent_id' => $parent->id],
                [
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'metadata' => ['phone' => $s['phone']],
                ]
            );
        }

        // =========================================================================
        // 3. UNIT 2: FULLDAY (SDIT 1 Dar el-Iman - 50 Kota)
        // =========================================================================
        $fulldayUnit = EducationUnit::where('code', 'SDIT-01')->first();
        if (! $fulldayUnit) {
            $this->command->error("Unit SDIT-01 tidak ditemukan!");
            return;
        }

        $fulldayMeta = is_array($fulldayUnit->metadata) ? $fulldayUnit->metadata : [];
        $fulldayMeta['is_boarding'] = false;
        $fulldayMeta['is_pesantren'] = false;
        $fulldayMeta['program_type'] = 'fullday';

        $fulldayUnit->update([
            'program_type' => 'fullday',
            'jam_masuk' => '07:15',
            'jam_pulang' => '16:00',
            'jam_kegiatan' => '07:15 - 16:00 WIB',
            'metadata' => $fulldayMeta,
        ]);

        // Wali Kelas SDIT
        $waliKelas = Employee::where('nama_lengkap', 'like', '%Wali Kelas%')->first()
            ?? Employee::where('unit_id', $fulldayUnit->id)->first();

        if ($waliKelas) {
            $waliKelas->update(['unit_id' => $fulldayUnit->id]);
        }

        // 1 Data Kelas Fullday (tbl_kelas)
        $kelasFullday = Kelas::updateOrCreate(
            ['kode_kelas' => 'SDIT1-1A'],
            [
                'unit_pendidikan_id' => $fulldayUnit->id,
                'tahun_ajaran_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'jenjang' => 'SD',
                'tingkat' => '1',
                'nama_kelas' => 'Kelas 1-A (Abu Bakar Ash-Shiddiq)',
                'wali_kelas_id' => $waliKelas?->id,
                'kapasitas' => 30,
                'ruangan' => 'Ruang Teori 1-A',
                'status' => 'Aktif',
            ]
        );

        // Sinkronkan ke tabel legacy classes
        DB::table('classes')->updateOrInsert(
            ['id' => $kelasFullday->id],
            [
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'name' => $kelasFullday->nama_kelas,
                'level' => '1',
                'updated_at' => now(),
            ]
        );

        // 10 Data Siswa Fullday (Campuran Ikhwan & Akhwat)
        $siswaData = [
            [
                'nis' => '202601001',
                'nisn' => '0187650001',
                'name' => 'Ahmad Fauzan Rabbani',
                'gender' => 'male',
                'birth_place' => 'Payakumbuh',
                'birth_date' => '2019-04-10',
                'address' => 'Jl. Soekarno-Hatta No. 45, Payakumbuh',
                'parent_name' => 'Rahmat Fauzi, S.T.',
                'mother_name' => 'Nurlaila Sari',
                'phone' => '081374560001',
                'job' => 'PNS / Guru',
                'nik_parent' => '1376011004850001',
            ],
            [
                'nis' => '202601002',
                'nisn' => '0187650002',
                'name' => 'Aisyah Humaira Zahra',
                'gender' => 'female',
                'birth_place' => 'Limapuluh Kota',
                'birth_date' => '2019-06-18',
                'address' => 'Jl. Raya Payakumbuh - Suliki Km 7, 50 Kota',
                'parent_name' => 'dr. Hendra Pratama, Sp.A',
                'mother_name' => 'dr. Annisa Wahyuni',
                'phone' => '081374560002',
                'job' => 'Dokter',
                'nik_parent' => '1376011806840002',
            ],
            [
                'nis' => '202601003',
                'nisn' => '0187650003',
                'name' => 'Muhammad Rizky Pratama',
                'gender' => 'male',
                'birth_place' => 'Payakumbuh',
                'birth_date' => '2019-01-22',
                'address' => 'Komplek Perumahan Indah Asri Blok B No. 5, Payakumbuh',
                'parent_name' => 'Eko Prasetyo, S.E.',
                'mother_name' => 'Indah Permatasari',
                'phone' => '081374560003',
                'job' => 'Bankir',
                'nik_parent' => '1376012201860003',
            ],
            [
                'nis' => '202601004',
                'nisn' => '0187650004',
                'name' => 'Fatimah Azzahra Maharani',
                'gender' => 'female',
                'birth_place' => 'Limapuluh Kota',
                'birth_date' => '2019-08-05',
                'address' => 'Jorong Tanjung Pati, Harau, 50 Kota',
                'parent_name' => 'Bambang Irawan',
                'mother_name' => 'Siti Nurhaliza',
                'phone' => '081374560004',
                'job' => 'Wiraswasta',
                'nik_parent' => '1376010508830004',
            ],
            [
                'nis' => '202601005',
                'nisn' => '0187650005',
                'name' => 'Ibrahim Khalilullah Ramli',
                'gender' => 'male',
                'birth_place' => 'Payakumbuh',
                'birth_date' => '2019-03-30',
                'address' => 'Jl. Tan Malaka No. 88, Payakumbuh',
                'parent_name' => 'Ramli Hasibuan, M.Pd.',
                'mother_name' => 'Rosmawati, S.Pd.',
                'phone' => '081374560005',
                'job' => 'Dosen',
                'nik_parent' => '1376013003820005',
            ],
            [
                'nis' => '202601006',
                'nisn' => '0187650006',
                'name' => 'Maryam Khansa Nabila',
                'gender' => 'female',
                'birth_place' => 'Limapuluh Kota',
                'birth_date' => '2019-11-12',
                'address' => 'Jorong Koto Baru Simalanggang, 50 Kota',
                'parent_name' => 'Yusuf Mansur, S.Ag.',
                'mother_name' => 'Khadijah Al-Kautsar',
                'phone' => '081374560006',
                'job' => 'Penyuluh Agama',
                'nik_parent' => '1376011211840006',
            ],
            [
                'nis' => '202601007',
                'nisn' => '0187650007',
                'name' => 'Yusuf Mansyur Al-Fatih',
                'gender' => 'male',
                'birth_place' => 'Payakumbuh',
                'birth_date' => '2019-05-14',
                'address' => 'Jl. Ade Irma Suryani No. 12, Payakumbuh',
                'parent_name' => 'Fatih Zulkarnain',
                'mother_name' => 'Maryamah Fitri',
                'phone' => '081374560007',
                'job' => 'Kontraktor',
                'nik_parent' => '1376011405850007',
            ],
            [
                'nis' => '202601008',
                'nisn' => '0187650008',
                'name' => 'Hafizhah Nurul Izzah',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => '2019-09-27',
                'address' => 'Jl. Veteran No. 34, Payakumbuh Barat',
                'parent_name' => 'Izzuddin Al-Qassam, S.Kom.',
                'mother_name' => 'Nurul Hidayah',
                'phone' => '081374560008',
                'job' => 'Software Engineer',
                'nik_parent' => '1376012709860008',
            ],
            [
                'nis' => '202601009',
                'nisn' => '0187650009',
                'name' => 'Ali Imron Hasan Basri',
                'gender' => 'male',
                'birth_place' => 'Limapuluh Kota',
                'birth_date' => '2019-07-04',
                'address' => 'Jorong Sarilamak, Harau, 50 Kota',
                'parent_name' => 'Hasan Basri, S.H.',
                'mother_name' => 'Maimunah Syarif',
                'phone' => '081374560009',
                'job' => 'Advokat / Pengacara',
                'nik_parent' => '1376010407810009',
            ],
            [
                'nis' => '202601010',
                'nisn' => '0187650010',
                'name' => 'Zahra Salimah Syahidah',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => '2019-12-01',
                'address' => 'Jl. Kusuma Bangsa No. 19, Payakumbuh Timur',
                'parent_name' => 'Syahid Ridwan, M.M.',
                'mother_name' => 'Salimah Az-Zuhri',
                'phone' => '081374560010',
                'job' => 'Manajer Operasional',
                'nik_parent' => '1376010112830010',
            ],
        ];

        foreach ($siswaData as $s) {
            // User Akun Orang Tua
            $parentEmail = 'wali.siswa.' . $s['nis'] . '@dareliman.sch.id';
            $parentUser = User::firstOrCreate(
                ['email' => $parentEmail],
                [
                    'name' => $s['parent_name'],
                    'phone' => $s['phone'],
                    'password' => $defaultPassword,
                    'is_active' => true,
                ]
            );
            if ($roleOrangTua) {
                $parentUser->syncRoles([$roleOrangTua->name]);
            }

            // Data Parent
            $parent = ParentModel::updateOrCreate(
                ['user_id' => $parentUser->id],
                [
                    'full_name' => $s['parent_name'],
                    'phone' => $s['phone'],
                    'email' => $parentEmail,
                    'occupation' => $s['job'],
                    'address' => $s['address'],
                    'nik' => $s['nik_parent'],
                    'metadata' => [
                        'nama_ayah' => $s['parent_name'],
                        'nama_ibu' => $s['mother_name'],
                        'no_wa' => $s['phone'],
                    ],
                ]
            );

            // User Akun Siswa
            $studentEmail = 'siswa.' . $s['nis'] . '@dareliman.sch.id';
            $studentUser = User::firstOrCreate(
                ['email' => $studentEmail],
                [
                    'name' => $s['name'],
                    'password' => $defaultPassword,
                    'is_active' => true,
                ]
            );
            if ($roleSiswa) {
                $studentUser->syncRoles([$roleSiswa->name]);
            }

            // Record Siswa (Student)
            $student = Student::updateOrCreate(
                ['nis' => $s['nis']],
                [
                    'user_id' => $studentUser->id,
                    'parent_id' => $parent->id,
                    'unit_id' => $fulldayUnit->id,
                    'kelas_id' => $kelasFullday->id,
                    'class_id' => $kelasFullday->id,
                    'nisn' => $s['nisn'],
                    'full_name' => $s['name'],
                    'gender' => $s['gender'],
                    'birth_place' => $s['birth_place'],
                    'birth_date' => $s['birth_date'],
                    'address' => $s['address'],
                    'is_active' => true,
                    'tahun_masuk' => 2026,
                    'metadata' => [
                        'program_type' => 'fullday',
                        'status_siswa' => 'Aktif',
                        'wali_kelas' => $waliKelas?->nama_lengkap ?? 'Usth. Maryam',
                        'nama_ayah' => $s['parent_name'],
                        'nama_ibu' => $s['mother_name'],
                        'hp_ayah' => $s['phone'],
                        'nomor_wa_ayah' => $s['phone'],
                        'pekerjaan_ayah' => $s['job'],
                    ],
                ]
            );

            // Pivot StudentParent
            StudentParent::updateOrCreate(
                ['student_id' => $student->id, 'parent_id' => $parent->id],
                [
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'metadata' => ['phone' => $s['phone']],
                ]
            );
        }

        $this->command->info("Berhasil mengisi 1 Unit Boarding (PONPES-PA) & 1 Unit Fullday (SDIT-01) dengan 1 Kelas dan masing-masing 10 data Siswa/Santri!");
    }
}
