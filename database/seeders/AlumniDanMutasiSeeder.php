<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AlumniDanMutasiSeeder extends Seeder
{
    /**
     * Seeder Khusus Alumni dan Siswa Mutasi Realistis (Zero Nama Dummy / Siswa Test).
     * Aman dijalankan di lingkungan staging/local tanpa merusak integritas database produksi.
     */
    public function run(): void
    {
        $this->command?->info('=== MEMULAI SEEDER ALUMNI & SISWA MUTASI REALISTIS ===');

        // Pastikan role Siswa, Orang Tua, dan Alumni tersedia
        $alumniRole = DB::table('roles')->where('name', 'Alumni')->first();
        $siswaRole = DB::table('roles')->where('name', 'Siswa')->first();
        $parentRole = DB::table('roles')->where('name', 'Orang Tua')->first();

        $alumniRoleId = $alumniRole?->id;
        $siswaRoleId = $siswaRole?->id;
        $parentRoleId = $parentRole?->id;

        // Ambil data unit pendidikan aktif
        $units = EducationUnit::all()->keyBy('code');
        $smait = $units->get('SMAIT-01') ?? EducationUnit::where('name', 'like', '%SMA%')->first();
        $smpit1 = $units->get('SMPIT-01') ?? EducationUnit::where('name', 'like', '%SMPIT 1%')->first();
        $smpit2 = $units->get('SMPIT-02') ?? EducationUnit::where('name', 'like', '%SMPIT 2%')->first();
        $sdit1 = $units->get('SDIT-01') ?? EducationUnit::where('name', 'like', '%SDIT 1%')->first();
        $sdit2 = $units->get('SDIT-02') ?? EducationUnit::where('name', 'like', '%SDIT 2%')->first();
        $sdit3 = $units->get('SDIT-03') ?? EducationUnit::where('name', 'like', '%SDIT 3%')->first();
        $mit = $units->get('MIT-01') ?? EducationUnit::where('name', 'like', '%MIT%')->first();
        $ponpesPa = $units->get('PONPES-PA') ?? EducationUnit::where('name', 'like', '%PONPES Putra%')->first();
        $ponpesPi = $units->get('PONPES-PI') ?? EducationUnit::where('name', 'like', '%PONPES Putri%')->first();
        $mahad = $units->get('MAHAD-01') ?? EducationUnit::where('name', 'like', '%Mahad%')->first();

        $defaultUnit = $smait ?? EducationUnit::first();
        $academicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        $semester = Semester::where('is_active', true)->first() ?? Semester::first();
        $defaultPassword = Hash::make('password');

        // -------------------------------------------------------------
        // 1. CLEANUP & NORMALISASI RECORD LEGACY BERPOLA TEST / SISWA MUTASI
        // -------------------------------------------------------------
        $this->command?->info('1. Membersihkan & menormalisasi record lama berlabel "Test" atau "Siswa Mutasi"...');

        $realisticReplacements = [
            'DEMO-MUT-0007' => ['name' => 'Fahri Ramadhan Putra', 'gender' => 'male', 'unit' => $sdit1],
            'DEMO-MUT-0008' => ['name' => 'Aisyah Humaira Putri', 'gender' => 'female', 'unit' => $sdit2],
            'DEMO-MUT-0009' => ['name' => 'Zaidan Al-Ghifari', 'gender' => 'male', 'unit' => $smpit1],
            'DEMO-MUT-0010' => ['name' => 'Khansa Salsabila Firdaus', 'gender' => 'female', 'unit' => $smpit2],
            'DEMO-MUT-0011' => ['name' => 'Rayhan Pratama Syahputra', 'gender' => 'male', 'unit' => $sdit3],
            'DEMO-MUT-0012' => ['name' => 'Nabila Syakira Anwar', 'gender' => 'female', 'unit' => $smpit1],
            'DEMO-MUT-0013' => ['name' => 'Muhammad Bilal Al-Fatih', 'gender' => 'male', 'unit' => $smait],
            'DEMO-MUT-0014' => ['name' => 'Zulfa Muthmainnah', 'gender' => 'female', 'unit' => $ponpesPi],
            'DEMO-MUT-0015' => ['name' => 'Ibrahim Khalilullah', 'gender' => 'male', 'unit' => $ponpesPa],
            'DEMO-MUT-0016' => ['name' => 'Salma Fauziyah Hanum', 'gender' => 'female', 'unit' => $mit],
            'DEMO-MUT-0017' => ['name' => 'Hafizh Abdurrahman', 'gender' => 'male', 'unit' => $mahad],
            'DEMO-MUT-0018' => ['name' => 'Zahra Nurul Izzah', 'gender' => 'female', 'unit' => $smpit2],
            'DEMO-MUT-0019' => ['name' => 'Farhan Aditia Rahmat', 'gender' => 'male', 'unit' => $smait],
            'DEMO-MUT-0020' => ['name' => 'Maryam Fitriani', 'gender' => 'female', 'unit' => $sdit2],
            'DEMO-MUT-0021' => ['name' => 'Ahmad Shodiq Munawar', 'gender' => 'male', 'unit' => $sdit1],
            'DEMO-MUT-0022' => ['name' => 'Syifa Azzahra Putri', 'gender' => 'female', 'unit' => $sdit3],
            'DEMO-MUT-0023' => ['name' => 'Umar Faruq Al-Hadi', 'gender' => 'male', 'unit' => $smpit1],
            'DEMO-MUT-0024' => ['name' => 'Naura Hasna Kamila', 'gender' => 'female', 'unit' => $sdit2],
            'DEMO-MUT-0025' => ['name' => 'Hamzah Asadullah', 'gender' => 'male', 'unit' => $ponpesPa],
            'DEMO-MUT-0026' => ['name' => 'Raudhah Jannah Wardani', 'gender' => 'female', 'unit' => $ponpesPi],
            'DEMO-MUT-0027' => ['name' => 'Tariq Ziyad Al-Farisi', 'gender' => 'male', 'unit' => $mahad],
            'DEMO-MUT-0028' => ['name' => 'Fathiyya Nuha Rahadatul', 'gender' => 'female', 'unit' => $smait],
            'DEMO-MUT-0029' => ['name' => 'Khalid Walid Rabbani', 'gender' => 'male', 'unit' => $smpit1],
            'DEMO-MUT-0030' => ['name' => 'Tsabita Qolbi Salsabila', 'gender' => 'female', 'unit' => $smpit2],
            'TEST-NIS-024'  => ['name' => 'Ahmad Fauzi Ridwan', 'gender' => 'male', 'unit' => $smait],
            'TEST-NIS-025'  => ['name' => 'Nabil Rabbani Pratama', 'gender' => 'male', 'unit' => $mahad],
            '999999'        => ['name' => 'Zulfa Azzahra Ramadhani', 'gender' => 'female', 'unit' => $smait],
            'TEST-NIS-023'  => ['name' => 'Muhammad Fathan Mubarok', 'gender' => 'male', 'unit' => $sdit1],
        ];

        foreach ($realisticReplacements as $nis => $info) {
            $student = Student::where('nis', $nis)->first();
            if (! $student) {
                continue;
            }

            $targetUnitId = $info['unit']?->id ?? $student->unit_id;
            $matchedClassId = $targetUnitId ? Kelas::where('unit_pendidikan_id', $targetUnitId)->value('id') : null;

            $student->update([
                'full_name' => $info['name'],
                'gender' => $info['gender'],
                'unit_id' => $targetUnitId,
                'kelas_id' => $matchedClassId,
                'class_id' => $matchedClassId,
            ]);

            // Jika belum punya user_id, buatkan akun user
            if (! $student->user_id) {
                $userEmail = 'siswa.' . strtolower(str_replace([' ', '-', '.'], '', $nis)) . '@siswa.dareliman.sch.id';
                $user = User::where('email', $userEmail)->first();
                if (! $user) {
                    $user = User::create([
                        'id' => (string) Str::uuid(),
                        'name' => $info['name'],
                        'email' => $userEmail,
                        'password' => $defaultPassword,
                        'is_active' => true,
                    ]);
                }
                $student->update(['user_id' => $user->id]);

                if ($siswaRoleId) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $siswaRoleId,
                        'model_type' => 'App\Models\User',
                        'model_id' => $user->id,
                    ]);
                }
            } else {
                $user = User::find($student->user_id);
                if ($user) {
                    $user->update(['name' => $info['name'], 'is_active' => true]);
                    if ($siswaRoleId) {
                        DB::table('model_has_roles')->insertOrIgnore([
                            'role_id' => $siswaRoleId,
                            'model_type' => 'App\Models\User',
                            'model_id' => $user->id,
                        ]);
                    }
                }
            }

            // Pastikan orang tua juga punya akun & relasi valid
            if (! $student->parent_id) {
                $parentEmail = 'ortu.' . strtolower(str_replace([' ', '-', '.'], '', $nis)) . '@family.dareliman.sch.id';
                $parentUser = User::where('email', $parentEmail)->first();
                if (! $parentUser) {
                    $parentUser = User::create([
                        'id' => (string) Str::uuid(),
                        'name' => 'Ayah ' . $info['name'],
                        'email' => $parentEmail,
                        'password' => $defaultPassword,
                        'is_active' => true,
                    ]);
                }
                if ($parentRoleId) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $parentRoleId,
                        'model_type' => 'App\Models\User',
                        'model_id' => $parentUser->id,
                    ]);
                }
                $parent = ParentModel::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $parentUser->id,
                    'full_name' => 'Ayah ' . $info['name'],
                    'phone' => '0812' . mt_rand(10000000, 99999999),
                    'email' => $parentEmail,
                    'occupation' => 'Wiraswasta',
                    'address' => 'Jl. Belanti Barat No. 12, Lolong Belanti, Padang Utara, Kota Padang',
                    'nik' => '1371' . mt_rand(100000000000, 999999999999),
                ]);
                $student->update(['parent_id' => $parent->id]);

                DB::table('student_parents')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'parent_id' => $parent->id,
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // -------------------------------------------------------------
        // 2. SEEDER DATA SISWA ALUMNI (LULUSAN 2024, 2025, 2026)
        // -------------------------------------------------------------
        $this->command?->info('2. Membuat dataset siswa alumni realistis dengan data tracer study komprehensif...');

        $alumniDataset = [
            // Angkatan 2024 (Lulus 2024)
            [
                'nis' => 'ALUM-2024-001',
                'nisn' => '0061234501',
                'name' => 'Muhammad Rifqi Pratama',
                'gender' => 'male',
                'unit' => $smait,
                'tahun_masuk' => '2021',
                'tahun_lulus' => '2024',
                'angkatan' => 'Angkatan 2024',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Indonesia - Fakultas Kedokteran',
                'perguruan_tinggi' => 'Universitas Indonesia',
                'fakultas' => 'Kedokteran (Pendidikan Dokter)',
                'pekerjaan' => 'Mahasiswa Kedokteran',
                'perusahaan' => 'Universitas Indonesia',
                'nilai_akhir' => 93.8,
                'catatan' => 'Juara 1 OSN Biologi Sumatera Barat & Hafal 30 Juz Al-Qur\'an bersanad',
                'phone' => '081266551101',
            ],
            [
                'nis' => 'ALUM-2024-002',
                'nisn' => '0061234502',
                'name' => 'Fathimah Az-Zahra Al-Banjari',
                'gender' => 'female',
                'unit' => $smait,
                'tahun_masuk' => '2021',
                'tahun_lulus' => '2024',
                'angkatan' => 'Angkatan 2024',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Institut Teknologi Bandung - Sekolah Teknik Elektro & Informatika (STEI)',
                'perguruan_tinggi' => 'Institut Teknologi Bandung',
                'fakultas' => 'Teknik Informatika (STEI-R)',
                'pekerjaan' => 'Mahasiswi Teknik Informatika',
                'perusahaan' => 'ITB Bandung',
                'nilai_akhir' => 95.2,
                'catatan' => 'Lulusan terbaik SMAIT Dar el-Iman 2024, medalis Olimpiade Komputer',
                'phone' => '081266551102',
            ],
            [
                'nis' => 'ALUM-2024-003',
                'nisn' => '0061234503',
                'name' => 'Abdullah Azzam Munawar',
                'gender' => 'male',
                'unit' => $mahad,
                'tahun_masuk' => '2021',
                'tahun_lulus' => '2024',
                'angkatan' => 'Angkatan 2024',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Islam Madinah - Fakultas Syariah (KSA)',
                'perguruan_tinggi' => 'Universitas Islam Madinah',
                'fakultas' => 'Kulliyah Asy-Syari\'ah',
                'pekerjaan' => 'Mahasiswa Beasiswa Penuh Madinah',
                'perusahaan' => 'Islamic University of Madinah',
                'nilai_akhir' => 97.5,
                'catatan' => 'Diterima beasiswa penuh kerajaan Arab Saudi, predikat Mumtaz Ma\'asy-Syaraf',
                'phone' => '081266551103',
            ],
            [
                'nis' => 'ALUM-2024-004',
                'nisn' => '0061234504',
                'name' => 'Zaid bin Haritsah Al-Makki',
                'gender' => 'male',
                'unit' => $mahad,
                'tahun_masuk' => '2021',
                'tahun_lulus' => '2024',
                'angkatan' => 'Angkatan 2024',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'LIPIA Jakarta - Prodi Syariah & Dirasat Islamiyah',
                'perguruan_tinggi' => 'LIPIA Jakarta',
                'fakultas' => 'Syariah Wal Qanun',
                'pekerjaan' => 'Mahasiswa LIPIA Jakarta',
                'perusahaan' => 'LIPIA Jakarta',
                'nilai_akhir' => 94.0,
                'catatan' => 'Lulus Takhasus Bahasa Arab tingkat advance & pengajar tahfizh',
                'phone' => '081266551104',
            ],
            [
                'nis' => 'ALUM-2024-005',
                'nisn' => '0061234505',
                'name' => 'Nurul Hidayati Safitri',
                'gender' => 'female',
                'unit' => $smait,
                'tahun_masuk' => '2021',
                'tahun_lulus' => '2024',
                'angkatan' => 'Angkatan 2024',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Andalas - Fakultas Farmasi',
                'perguruan_tinggi' => 'Universitas Andalas',
                'fakultas' => 'Farmasi',
                'pekerjaan' => 'Mahasiswi Farmasi',
                'perusahaan' => 'Universitas Andalas Padang',
                'nilai_akhir' => 91.0,
                'catatan' => 'Jalur SNBP Prestasi Tahfizh 15 Juz',
                'phone' => '081266551105',
            ],
            [
                'nis' => 'ALUM-2024-006',
                'nisn' => '0061234506',
                'name' => 'Rafi Ahmad Pratama',
                'gender' => 'male',
                'unit' => $smait,
                'tahun_masuk' => '2021',
                'tahun_lulus' => '2024',
                'angkatan' => 'Angkatan 2024',
                'status_lanjutan' => 'Bekerja',
                'tujuan_kelulusan' => 'PT Telkom Indonesia - Junior Software Engineer',
                'perguruan_tinggi' => '-',
                'fakultas' => '-',
                'pekerjaan' => 'Junior Software Engineer',
                'perusahaan' => 'PT Telkom Indonesia (Persero) Tbk',
                'nilai_akhir' => 89.5,
                'catatan' => 'Berkarir di bidang teknologi sambil melanjutkan kuliah daring',
                'phone' => '081266551106',
            ],

            // Angkatan 2025 (Lulus 2025)
            [
                'nis' => 'ALUM-2025-001',
                'nisn' => '0071234501',
                'name' => 'Bilal Ramadhan Asy-Syamil',
                'gender' => 'male',
                'unit' => $smait,
                'tahun_masuk' => '2022',
                'tahun_lulus' => '2025',
                'angkatan' => 'Angkatan 2025',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Gadjah Mada - Teknik Sipil & Lingkungan',
                'perguruan_tinggi' => 'Universitas Gadjah Mada',
                'fakultas' => 'Teknik (Teknik Sipil)',
                'pekerjaan' => 'Mahasiswa Teknik Sipil UGM',
                'perusahaan' => 'UGM Yogyakarta',
                'nilai_akhir' => 93.1,
                'catatan' => 'Lulus SNBT peringkat 1 Saintek regional Sumbar',
                'phone' => '081266551201',
            ],
            [
                'nis' => 'ALUM-2025-002',
                'nisn' => '0071234502',
                'name' => 'Syifa Nuraini Rahmadani',
                'gender' => 'female',
                'unit' => $smait,
                'tahun_masuk' => '2022',
                'tahun_lulus' => '2025',
                'angkatan' => 'Angkatan 2025',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Padjadjaran - Fakultas Kedokteran Gigi',
                'perguruan_tinggi' => 'Universitas Padjadjaran',
                'fakultas' => 'Kedokteran Gigi',
                'pekerjaan' => 'Mahasiswi FKG Unpad',
                'perusahaan' => 'Unpad Jatinangor',
                'nilai_akhir' => 94.7,
                'catatan' => 'Duta Pelajar Muslim Berprestasi Padang 2025',
                'phone' => '081266551202',
            ],
            [
                'nis' => 'ALUM-2025-003',
                'nisn' => '0071234503',
                'name' => 'Tariq Ziyad Al-Habsyi',
                'gender' => 'male',
                'unit' => $mahad,
                'tahun_masuk' => '2022',
                'tahun_lulus' => '2025',
                'angkatan' => 'Angkatan 2025',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Islam Negeri Imam Bonjol - Dirasat Islamiyah',
                'perguruan_tinggi' => 'UIN Imam Bonjol Padang',
                'fakultas' => 'Ushuluddin & Studi Agama',
                'pekerjaan' => 'Mahasiswa & Da\'i Muda',
                'perusahaan' => 'UIN IB Padang',
                'nilai_akhir' => 92.5,
                'catatan' => 'Hafal Al-Qur\'an 30 Juz Mutqin & Kitab Arbain Nawawi',
                'phone' => '081266551203',
            ],
            [
                'nis' => 'ALUM-2025-004',
                'nisn' => '0071234504',
                'name' => 'Hafshah Maryam Al-Qonitah',
                'gender' => 'female',
                'unit' => $smpit1,
                'tahun_masuk' => '2022',
                'tahun_lulus' => '2025',
                'angkatan' => 'Angkatan 2025',
                'status_lanjutan' => 'Pesantren Lanjutan',
                'tujuan_kelulusan' => 'Pondok Modern Darussalam Gontor Putri - Mantingan Ngawi',
                'perguruan_tinggi' => '-',
                'fakultas' => 'KMI Gontor Putri',
                'pekerjaan' => 'Santriwati KMI Gontor Putri',
                'perusahaan' => 'Pondok Modern Darussalam Gontor',
                'nilai_akhir' => 96.0,
                'catatan' => 'Lulusan terbaik SMPIT 1 Dar el-Iman 2025, lulus tes KMI Kelas 1 Intensif',
                'phone' => '081266551204',
            ],
            [
                'nis' => 'ALUM-2025-005',
                'nisn' => '0071234505',
                'name' => 'Farhan Aditia Rahmatullah',
                'gender' => 'male',
                'unit' => $smpit2,
                'tahun_masuk' => '2022',
                'tahun_lulus' => '2025',
                'angkatan' => 'Angkatan 2025',
                'status_lanjutan' => 'Pesantren Lanjutan',
                'tujuan_kelulusan' => 'SMAIT Insantama Bogor (Islamic Boarding School)',
                'perguruan_tinggi' => '-',
                'fakultas' => 'SMA Boarding School',
                'pekerjaan' => 'Santri SMAIT Insantama',
                'perusahaan' => 'SMAIT Insantama Bogor',
                'nilai_akhir' => 90.8,
                'catatan' => 'Melanjutkan pendidikan berasrama leadership Islam',
                'phone' => '081266551205',
            ],
            [
                'nis' => 'ALUM-2025-006',
                'nisn' => '0071234506',
                'name' => 'Ibrahim Khalilullah Al-Qudsi',
                'gender' => 'male',
                'unit' => $smait,
                'tahun_masuk' => '2022',
                'tahun_lulus' => '2025',
                'angkatan' => 'Angkatan 2025',
                'status_lanjutan' => 'Wirausaha',
                'tujuan_kelulusan' => 'CV Berkah Sunnah Herbal - Owner & Co-Founder',
                'perguruan_tinggi' => '-',
                'fakultas' => '-',
                'pekerjaan' => 'Owner & Founder Bisnis Sunnah',
                'perusahaan' => 'CV Berkah Sunnah Herbal Padang',
                'nilai_akhir' => 88.6,
                'catatan' => 'Mengembangkan usaha e-commerce herbal sunnah nasional',
                'phone' => '081266551206',
            ],

            // Angkatan 2026 (Lulus 2026 / Tamat Baru)
            [
                'nis' => 'ALUM-2026-001',
                'nisn' => '0081234501',
                'name' => 'Aisyah Humaira Zulfa',
                'gender' => 'female',
                'unit' => $smait,
                'tahun_masuk' => '2023',
                'tahun_lulus' => '2026',
                'angkatan' => 'Angkatan 2026',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Indonesia - Fakultas Ekonomi & Bisnis (Ilmu Ekonomi Islam)',
                'perguruan_tinggi' => 'Universitas Indonesia',
                'fakultas' => 'Ekonomi & Bisnis (Ekonomi Syariah)',
                'pekerjaan' => 'Calon Mahasiswi FEB UI',
                'perusahaan' => 'Universitas Indonesia',
                'nilai_akhir' => 96.4,
                'catatan' => 'Lulusan Berprestasi Terbaik Angkatan 2026, Nilai Ujian Sekolah Tertinggi',
                'phone' => '081266551301',
            ],
            [
                'nis' => 'ALUM-2026-002',
                'nisn' => '0081234502',
                'name' => 'Hamzah Asadullah Al-Khattab',
                'gender' => 'male',
                'unit' => $smait,
                'tahun_masuk' => '2023',
                'tahun_lulus' => '2026',
                'angkatan' => 'Angkatan 2026',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Institut Teknologi Sepuluh Nopember - Teknik Elektro',
                'perguruan_tinggi' => 'Institut Teknologi Sepuluh Nopember (ITS)',
                'fakultas' => 'Teknologi Elektro & Informatika Cerdas',
                'pekerjaan' => 'Calon Mahasiswa ITS Surabaya',
                'perusahaan' => 'ITS Surabaya',
                'nilai_akhir' => 92.8,
                'catatan' => 'Juara Robotik Tingkat Nasional & Hafal 20 Juz Al-Qur\'an',
                'phone' => '081266551302',
            ],
            [
                'nis' => 'ALUM-2026-003',
                'nisn' => '0081234503',
                'name' => 'Zulfa Rahmah Al-Fatih',
                'gender' => 'female',
                'unit' => $smait,
                'tahun_masuk' => '2023',
                'tahun_lulus' => '2026',
                'angkatan' => 'Angkatan 2026',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Andalas - Pendidikan Dokter',
                'perguruan_tinggi' => 'Universitas Andalas',
                'fakultas' => 'Kedokteran',
                'pekerjaan' => 'Calon Mahasiswi Kedokteran UNAND',
                'perusahaan' => 'Universitas Andalas',
                'nilai_akhir' => 95.0,
                'catatan' => 'Lulus SNBP 2026 Fakultas Kedokteran Unand',
                'phone' => '081266551303',
            ],
            [
                'nis' => 'ALUM-2026-004',
                'nisn' => '0081234504',
                'name' => 'Yusuf Maulana Al-Qudwah',
                'gender' => 'male',
                'unit' => $mahad,
                'tahun_masuk' => '2023',
                'tahun_lulus' => '2026',
                'angkatan' => 'Angkatan 2026',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Islam Madinah - Fakultas Hadits & Studi Islam',
                'perguruan_tinggi' => 'Universitas Islam Madinah',
                'fakultas' => 'Kulliyah Al-Hadits Asy-Syarif',
                'pekerjaan' => 'Mahasiswa Terpilih Delegasi Mahad Dar el-Iman',
                'perusahaan' => 'Islamic University of Madinah',
                'nilai_akhir' => 98.0,
                'catatan' => 'Peringkat 1 Ujian Akhir Mahad Abu Ja\'far 2026, Sanad Hadits Arbain & Jazariyyah',
                'phone' => '081266551304',
            ],
            [
                'nis' => 'ALUM-2026-005',
                'nisn' => '0081234505',
                'name' => 'Raihana Syakira Putri',
                'gender' => 'female',
                'unit' => $sdit2,
                'tahun_masuk' => '2020',
                'tahun_lulus' => '2026',
                'angkatan' => 'Angkatan 2026',
                'status_lanjutan' => 'Pesantren Lanjutan',
                'tujuan_kelulusan' => 'SMPIT 1 Dar el-Iman Boarding Putri - Padang',
                'perguruan_tinggi' => '-',
                'fakultas' => 'Tingkat SMPIT',
                'pekerjaan' => 'Santriwati Baru SMPIT Boarding',
                'perusahaan' => 'SMPIT 1 Dar el-Iman Padang',
                'nilai_akhir' => 94.2,
                'catatan' => 'Lulusan SDIT 2 dengan predikat lulusan teladan tahfizh 5 juz',
                'phone' => '081266551305',
            ],
        ];

        $totalAlumniCreated = 0;

        foreach ($alumniDataset as $item) {
            $unitTarget = $item['unit'] ?? $defaultUnit;
            $existing = Student::where('nis', $item['nis'])->first();

            $metadata = [
                'is_alumni' => true,
                'status_siswa' => 'alumni',
                'status_alumni' => 'Lulus',
                'tahun_lulus' => $item['tahun_lulus'],
                'tahun_mutasi' => $item['tahun_lulus'],
                'angkatan' => $item['angkatan'],
                'status_lanjutan' => $item['status_lanjutan'],
                'tujuan_kelulusan' => $item['tujuan_kelulusan'],
                'perguruan_tinggi' => $item['perguruan_tinggi'],
                'fakultas' => $item['fakultas'],
                'pekerjaan' => $item['pekerjaan'],
                'perusahaan' => $item['perusahaan'],
                'nilai_akhir_rata_rata' => $item['nilai_akhir'],
                'no_hp_alumni' => $item['phone'],
                'catatan_alumni' => $item['catatan'],
                'nomor_ijazah' => 'DN-03/Ma.03/PP.01.1/' . $item['tahun_lulus'] . '/' . substr($item['nisn'], -4),
                'tanggal_lulus' => $item['tahun_lulus'] . '-06-15',
                'academic_year_id' => $academicYear?->id,
            ];

            // 1. Buat / Update Akun User Siswa
            $studentEmail = 'alumni.' . strtolower(str_replace('-', '', $item['nis'])) . '@alumni.dareliman.sch.id';
            $user = User::where('email', $studentEmail)->first();
            if (! $user) {
                $user = User::create([
                    'id' => (string) Str::uuid(),
                    'name' => $item['name'],
                    'email' => $studentEmail,
                    'password' => $defaultPassword,
                    'phone' => $item['phone'],
                    'is_active' => true,
                ]);
            } else {
                $user->update([
                    'name' => $item['name'],
                    'phone' => $item['phone'],
                    'is_active' => true,
                ]);
            }

            // Pasang Spatie Role: Alumni dan Siswa
            if ($alumniRoleId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $alumniRoleId,
                    'model_type' => 'App\Models\User',
                    'model_id' => $user->id,
                ]);
            }
            if ($siswaRoleId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $siswaRoleId,
                    'model_type' => 'App\Models\User',
                    'model_id' => $user->id,
                ]);
            }

            // 2. Buat / Update Akun User Orang Tua
            $parentEmail = 'ortu.' . strtolower(str_replace('-', '', $item['nis'])) . '@family.dareliman.sch.id';
            $parentUser = User::where('email', $parentEmail)->first();
            if (! $parentUser) {
                $parentUser = User::create([
                    'id' => (string) Str::uuid(),
                    'name' => 'Ayah ' . $item['name'],
                    'email' => $parentEmail,
                    'password' => $defaultPassword,
                    'phone' => '0813' . mt_rand(10000000, 99999999),
                    'is_active' => true,
                ]);
            }
            if ($parentRoleId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $parentRoleId,
                    'model_type' => 'App\Models\User',
                    'model_id' => $parentUser->id,
                ]);
            }

            $parent = ParentModel::where('email', $parentEmail)->first();
            if (! $parent) {
                $parent = ParentModel::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $parentUser->id,
                    'full_name' => 'Ayah ' . $item['name'],
                    'phone' => $parentUser->phone,
                    'email' => $parentEmail,
                    'occupation' => 'Wiraswasta / Profesional',
                    'address' => 'Jl. Khatib Sulaiman No. ' . mt_rand(10, 80) . ', Padang',
                    'nik' => '1371' . mt_rand(100000000000, 999999999999),
                ]);
            }

            // 3. Simpan Student Model
            if ($existing) {
                $existing->update([
                    'full_name' => $item['name'],
                    'nisn' => $item['nisn'],
                    'gender' => $item['gender'],
                    'unit_id' => $unitTarget->id,
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'is_active' => false,
                    'tahun_masuk' => (int) $item['tahun_masuk'],
                    'metadata' => array_merge($existing->metadata ?? [], $metadata),
                ]);
            } else {
                $studentId = (string) Str::uuid();
                $newStudent = Student::create([
                    'id' => $studentId,
                    'nis' => $item['nis'],
                    'nisn' => $item['nisn'],
                    'full_name' => $item['name'],
                    'gender' => $item['gender'],
                    'birth_place' => 'Padang',
                    'birth_date' => Carbon::createFromDate((int) $item['tahun_masuk'] - 15, mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                    'address' => 'Jl. Gurun Laweh No. ' . mt_rand(5, 50) . ', Padang',
                    'unit_id' => $unitTarget->id,
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'is_active' => false,
                    'tahun_masuk' => (int) $item['tahun_masuk'],
                    'metadata' => $metadata,
                ]);

                DB::table('student_parents')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'student_id' => $newStudent->id,
                    'parent_id' => $parent->id,
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $totalAlumniCreated++;
            }
        }

        $this->command?->info("Berhasil menambahkan {$totalAlumniCreated} data siswa alumni baru.");

        // -------------------------------------------------------------
        // 3. SEEDER DATA SISWA MUTASI KELUAR, MASUK, BERHENTI & PINDAH UNIT
        // -------------------------------------------------------------
        $this->command?->info('3. Membuat dataset siswa mutasi (keluar, masuk, pindah unit, berhenti) yang valid & realistis...');

        $mutationDataset = [
            // Mutasi Keluar (Pindah ke sekolah lain di luar kota / provinsi)
            [
                'nis' => 'MUT-2026-OUT-001',
                'nisn' => '0098765001',
                'name' => 'Fathan Al-Ghazali Siregar',
                'gender' => 'male',
                'unit' => $sdit1,
                'mutasi_type' => 'keluar',
                'mutasi_status' => 'Selesai',
                'is_active' => false,
                'tanggal_efektif' => '2026-08-01',
                'sekolah_eksternal' => 'SDIT Nurul Fikri Depok - Jawa Barat',
                'alasan' => 'Mengikuti perpindahan tugas kedinasan orang tua ke Kementerian Pusat Jakarta',
                'catatan' => 'Surat pindah resmi terbit Nomor: 421.2/108/DISDIK/2026',
            ],
            [
                'nis' => 'MUT-2026-OUT-002',
                'nisn' => '0098765002',
                'name' => 'Anindita Keisha Zahra',
                'gender' => 'female',
                'unit' => $smpit1,
                'mutasi_type' => 'keluar',
                'mutasi_status' => 'Selesai',
                'is_active' => false,
                'tanggal_efektif' => '2026-08-10',
                'sekolah_eksternal' => 'SMPIT Al-Azhar Pekanbaru - Riau',
                'alasan' => 'Pindah domisili keluarga ke Pekanbaru Riau karena usaha orang tua',
                'catatan' => 'Rapor semester ganjil dan surat rekomendasi sekolah telah diserahterimakan',
            ],
            [
                'nis' => 'MUT-2026-OUT-003',
                'nisn' => '0098765003',
                'name' => 'Muhammad Daffa Al-Farizi',
                'gender' => 'male',
                'unit' => $smait,
                'mutasi_type' => 'keluar',
                'mutasi_status' => 'Selesai',
                'is_active' => false,
                'tanggal_efektif' => '2026-08-15',
                'sekolah_eksternal' => 'Pondok Pesantren Al-Irsyad Salatiga - Jawa Tengah',
                'alasan' => 'Melanjutkan program khusus takhasus tahfizh 30 juz dan bahasa Arab intensif',
                'catatan' => 'Siswa telah menuntaskan setoran hafalan 12 juz di SMAIT Dar el-Iman',
            ],
            [
                'nis' => 'MUT-2026-OUT-004',
                'nisn' => '0098765004',
                'name' => 'Zahira Callista Putri',
                'gender' => 'female',
                'unit' => $sdit2,
                'mutasi_type' => 'keluar',
                'mutasi_status' => 'Selesai',
                'is_active' => false,
                'tanggal_efektif' => '2026-08-20',
                'sekolah_eksternal' => 'SDIT Insan Mandiri Bukittinggi',
                'alasan' => 'Mendekati domisili kakek-nenek yang membutuhkan pendampingan keluarga di Bukittinggi',
                'catatan' => 'Administrasi dan bebas pustaka selesai 100%',
            ],

            // Siswa Berhenti / Mengundurkan Diri (Non-Aktif)
            [
                'nis' => 'MUT-2026-STOP-001',
                'nisn' => '0098765005',
                'name' => 'Arkananta Rayyan Al-Hakim',
                'gender' => 'male',
                'unit' => $sdit3,
                'mutasi_type' => 'berhenti',
                'mutasi_status' => 'Selesai',
                'is_active' => false,
                'tanggal_efektif' => '2026-08-18',
                'sekolah_eksternal' => 'Homeschooling Mandiri (Pendidikan Rumah)',
                'alasan' => 'Fokus perawatan medis dan terapi intensif tumbuh kembang anak secara mandiri',
                'catatan' => 'Permohonan tertulis orang tua bermeterai lengkap dengan surat dokter',
            ],
            [
                'nis' => 'MUT-2026-STOP-002',
                'nisn' => '0098765006',
                'name' => 'Najwa Khairunnisa',
                'gender' => 'female',
                'unit' => $smpit2,
                'mutasi_type' => 'berhenti',
                'mutasi_status' => 'Selesai',
                'is_active' => false,
                'tanggal_efektif' => '2026-08-25',
                'sekolah_eksternal' => 'Pendidikan Non-Formal Khusus',
                'alasan' => 'Permintaan orang tua untuk program tahfizh privat terpadu keluarga',
                'catatan' => 'Surat pengunduran diri ditandatangani kedua orang tua',
            ],

            // Mutasi Masuk (Siswa Pindahan dari Luar Masuk ke Dar el-Iman) - Siswa Aktif
            [
                'nis' => 'MUT-2026-IN-001',
                'nisn' => '0098765007',
                'name' => 'Rayhan Ghibran Al-Farisi',
                'gender' => 'male',
                'unit' => $sdit1,
                'mutasi_type' => 'masuk',
                'mutasi_status' => 'Selesai',
                'is_active' => true,
                'tanggal_efektif' => '2026-07-15',
                'sekolah_eksternal' => 'SDIT Al-Hikmah Jakarta Selatan',
                'alasan' => 'Perpindahan tugas dinas ayah ke Padang & memilih Dar el-Iman karena kurikulum sunnah',
                'catatan' => 'Surat pindah resmi Dinas Pendidikan DKI Jakarta terlampir lengkap',
            ],
            [
                'nis' => 'MUT-2026-IN-002',
                'nisn' => '0098765008',
                'name' => 'Safira Azzahra Medina',
                'gender' => 'female',
                'unit' => $sdit2,
                'mutasi_type' => 'masuk',
                'mutasi_status' => 'Selesai',
                'is_active' => true,
                'tanggal_efektif' => '2026-07-20',
                'sekolah_eksternal' => 'SD Islam Al-Azhar 32 Padang',
                'alasan' => 'Keinginan memperdalam tahfizh Al-Qur\'an dan pembinaan akhlak Islami terpadu',
                'catatan' => 'Telah lulus matrikulasi tahfizh & tes akademik kelas',
            ],
            [
                'nis' => 'MUT-2026-IN-003',
                'nisn' => '0098765009',
                'name' => 'Faris Hamizan Rabbani',
                'gender' => 'male',
                'unit' => $smpit1,
                'mutasi_type' => 'masuk',
                'mutasi_status' => 'Selesai',
                'is_active' => true,
                'tanggal_efektif' => '2026-07-22',
                'sekolah_eksternal' => 'SMP IT Ibnu Abbas Klaten',
                'alasan' => 'Kembali ke domisili orang tua di Sumatera Barat',
                'catatan' => 'Bawa bekal hafalan 8 juz mutqin',
            ],

            // Pindah Unit (Antar-Unit Internal Yayasan Dar el-Iman) - Siswa Aktif
            [
                'nis' => 'MUT-2026-TR-001',
                'nisn' => '0098765010',
                'name' => 'Haikal Rasyid Al-Banna',
                'gender' => 'male',
                'unit' => $ponpesPa,
                'unit_asal' => $smpit1,
                'mutasi_type' => 'antar_unit',
                'mutasi_status' => 'Selesai',
                'is_active' => true,
                'tanggal_efektif' => '2026-07-10',
                'sekolah_eksternal' => 'Internal Yayasan Dar el-Iman',
                'alasan' => 'Pindah dari kelas fullday SMPIT 1 ke sistem asrama PONPES Putra Dar el-Iman',
                'catatan' => 'Proses verifikasi musyrif asrama disetujui',
            ],
            [
                'nis' => 'MUT-2026-TR-002',
                'nisn' => '0098765011',
                'name' => 'Khadijah Maryam Qonita',
                'gender' => 'female',
                'unit' => $ponpesPi,
                'unit_asal' => $smpit2,
                'mutasi_type' => 'antar_unit',
                'mutasi_status' => 'Selesai',
                'is_active' => true,
                'tanggal_efektif' => '2026-07-12',
                'sekolah_eksternal' => 'Internal Yayasan Dar el-Iman',
                'alasan' => 'Pindah dari unit 50 Kota ke asrama pesantren putri Padang',
                'catatan' => 'Disetujui Kepala Bidang Pendidikan',
            ],
            [
                'nis' => 'MUT-2026-TR-003',
                'nisn' => '0098765012',
                'name' => 'Althaf Raditya Pratama',
                'gender' => 'male',
                'unit' => $sdit2,
                'unit_asal' => $sdit1,
                'mutasi_type' => 'antar_unit',
                'mutasi_status' => 'Selesai',
                'is_active' => true,
                'tanggal_efektif' => '2026-07-14',
                'sekolah_eksternal' => 'Internal Yayasan Dar el-Iman',
                'alasan' => 'Pindah domisili rumah keluarga dari Lima Puluh Kota ke Kota Padang',
                'catatan' => 'Kapasitas rombel kelas SDIT 2 mencukupi',
            ],
        ];

        $totalMutasiCreated = 0;

        foreach ($mutationDataset as $m) {
            $existing = Student::where('nis', $m['nis'])->first();
            $unitTarget = $m['unit'] ?? $defaultUnit;
            $unitAsal = $m['unit_asal'] ?? null;

            $meta = [
                'nomor_mutasi' => $m['nis'],
                'mutasi_type' => $m['mutasi_type'],
                'mutasi_status' => $m['mutasi_status'],
                'tanggal_efektif' => $m['tanggal_efektif'],
                'tanggal_pengajuan' => Carbon::parse($m['tanggal_efektif'])->subDays(5)->toDateString(),
                'tahun_mutasi' => Carbon::parse($m['tanggal_efektif'])->format('Y'),
                'sekolah_eksternal' => $m['sekolah_eksternal'],
                'sekolah_tujuan' => in_array($m['mutasi_type'], ['keluar', 'berhenti']) ? $m['sekolah_eksternal'] : $unitTarget->name,
                'sekolah_asal' => $m['mutasi_type'] === 'masuk' ? $m['sekolah_eksternal'] : ($unitAsal?->name ?? $unitTarget->name),
                'unit_asal_id' => $unitAsal?->id,
                'unit_asal_name' => $unitAsal?->name ?? ($m['mutasi_type'] === 'masuk' ? $m['sekolah_eksternal'] : $unitTarget->name),
                'unit_tujuan_id' => $unitTarget->id,
                'unit_tujuan_name' => in_array($m['mutasi_type'], ['keluar', 'berhenti']) ? $m['sekolah_eksternal'] : $unitTarget->name,
                'alasan' => $m['alasan'],
                'catatan' => $m['catatan'],
                'academic_year_id' => $academicYear?->id,
                'semester_id' => $semester?->id,
            ];

            // Akun User Siswa
            $studentEmail = 'siswa.' . strtolower(str_replace('-', '', $m['nis'])) . '@siswa.dareliman.sch.id';
            $user = User::where('email', $studentEmail)->first();
            if (! $user) {
                $user = User::create([
                    'id' => (string) Str::uuid(),
                    'name' => $m['name'],
                    'email' => $studentEmail,
                    'password' => $defaultPassword,
                    'phone' => '0812' . mt_rand(10000000, 99999999),
                    'is_active' => true,
                ]);
            }
            if ($siswaRoleId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $siswaRoleId,
                    'model_type' => 'App\Models\User',
                    'model_id' => $user->id,
                ]);
            }

            // Akun User Orang Tua
            $parentEmail = 'ortu.' . strtolower(str_replace('-', '', $m['nis'])) . '@family.dareliman.sch.id';
            $parentUser = User::where('email', $parentEmail)->first();
            if (! $parentUser) {
                $parentUser = User::create([
                    'id' => (string) Str::uuid(),
                    'name' => 'Ayah ' . $m['name'],
                    'email' => $parentEmail,
                    'password' => $defaultPassword,
                    'phone' => '0813' . mt_rand(10000000, 99999999),
                    'is_active' => true,
                ]);
            }
            if ($parentRoleId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $parentRoleId,
                    'model_type' => 'App\Models\User',
                    'model_id' => $parentUser->id,
                ]);
            }

            $parent = ParentModel::where('email', $parentEmail)->first();
            if (! $parent) {
                $parent = ParentModel::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $parentUser->id,
                    'full_name' => 'Ayah ' . $m['name'],
                    'phone' => $parentUser->phone,
                    'email' => $parentEmail,
                    'occupation' => 'Pegawai Swasta / Wiraswasta',
                    'address' => 'Jl. Veteran No. ' . mt_rand(10, 90) . ', Padang',
                    'nik' => '1371' . mt_rand(100000000000, 999999999999),
                ]);
            }

            if ($existing) {
                $existing->update([
                    'full_name' => $m['name'],
                    'nisn' => $m['nisn'],
                    'gender' => $m['gender'],
                    'unit_id' => $unitTarget->id,
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'is_active' => $m['is_active'],
                    'metadata' => array_merge($existing->metadata ?? [], $meta),
                ]);
            } else {
                $newStd = Student::create([
                    'id' => (string) Str::uuid(),
                    'nis' => $m['nis'],
                    'nisn' => $m['nisn'],
                    'full_name' => $m['name'],
                    'gender' => $m['gender'],
                    'birth_place' => 'Padang',
                    'birth_date' => Carbon::createFromDate(2013, mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                    'address' => 'Jl. Sawahan No. ' . mt_rand(1, 40) . ', Padang',
                    'unit_id' => $unitTarget->id,
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'is_active' => $m['is_active'],
                    'tahun_masuk' => 2024,
                    'metadata' => $meta,
                ]);

                DB::table('student_parents')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'student_id' => $newStd->id,
                    'parent_id' => $parent->id,
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $totalMutasiCreated++;
            }
        }

        $this->command?->info("Berhasil menambahkan {$totalMutasiCreated} data siswa mutasi baru.");
        $this->command?->info('=== SEEDER ALUMNI & SISWA MUTASI SELESAI DENGAN SUKSES ===');
    }
}
