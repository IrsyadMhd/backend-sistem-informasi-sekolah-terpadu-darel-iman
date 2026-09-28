<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsPenugasan;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsRapor;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahMentorAssignment;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentParent;
use App\Models\Subject;
use App\Models\TahfizhDailyLog;
use App\Models\TahfizhExam;
use App\Models\TahfizhRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SimulasiTerpadu2026Seeder extends Seeder
{
    private array $usedNames = [];
    private array $maleFirstNames = [
        'Muhammad', 'Ahmad', 'Abdullah', 'Zaid', 'Umar', 'Ali', 'Hamzah', 'Hasan', 'Husain', 'Bilal',
        'Salman', 'Khalid', 'Thariq', 'Usamah', 'Fatih', 'Rayyan', 'Farhan', 'Fadhil', 'Naufal', 'Rasyid',
        'Syakir', 'Munir', 'Ihsan', 'Fauzan', 'Hafizh', 'Luqman', 'Yusuf', 'Ibrahim', 'Ismail', 'Idris',
        'Zakariya', 'Yahya', 'Harun', 'Sulaiman', 'Dawud', 'Syamil', 'Wildan', 'Azzam', 'Rifqi', 'Hanif',
        'Akram', 'Karim', 'Basil', 'Dzaki', 'Ghazi', 'Hilal', 'Irfan', 'Jafar', 'Kamil', 'Labib',
        'Miqdad', 'Nabil', 'Qasim', 'Rafi', 'Salim', 'Tariq', 'Ubaid', 'Wafi', 'Yasin', 'Ziyad',
        'Arfan', 'Baqir', 'Chairil', 'Danish', 'Ehsan', 'Faris', 'Gibran', 'Habibi', 'Ilham', 'Jundi'
    ];

    private array $femaleFirstNames = [
        'Aisha', 'Fatimah', 'Maryam', 'Khadijah', 'Zainab', 'Ruqayyah', 'Hafshah', 'Juwairiyah', 'Maimunah', 'Safiyyah',
        'Asma', 'Sarah', 'Hajar', 'Zahra', 'Salma', 'Naila', 'Yasmin', 'Hana', 'Dina', 'Lubna',
        'Marwa', 'Shafa', 'Farah', 'Rania', 'Syifa', 'Layla', 'Nada', 'Alya', 'Sabrina', 'Husna',
        'Azizah', 'Inayah', 'Shakira', 'Khalila', 'Humaira', 'Samira', 'Thahirah', 'Zakiyah', 'Aminah', 'Halimah',
        'Barakah', 'Nusaibah', 'Afifah', 'Basimah', 'Dzakiyyah', 'Faiqah', 'Ghina', 'Habibah', 'Ilma', 'Jamilah',
        'Kamilah', 'Latifah', 'Mawaddah', 'Najwa', 'Qanita', 'Raudhah', 'Salsabila', 'Tasnim', 'Ufairah', 'Wardah'
    ];

    private array $middleNames = [
        'Hafizh', 'Rasyid', 'Syakir', 'Munir', 'Naufal', 'Fadhil', 'Ihsan', 'Hakim', 'Maulana', 'Farid',
        'Fauzi', 'Habibi', 'Izzuddin', 'Jauhari', 'Kamal', 'Luthfi', 'Mubarak', 'Nasir', 'Qudrat', 'Rizal',
        'Shiddiq', 'Thahir', 'Ushaimi', 'Wahid', 'Zuhdi', 'Aqil', 'Burhan', 'Dhiya', 'Faiz', 'Ghaisan',
        'Anindya', 'Az-Zahra', 'Khairunnisa', 'Nabilah', 'Rahadatul', 'Syakirah', 'Tsabita', 'Wafiqah', 'Yusra', 'Zulaikha'
    ];

    private array $lastNames = [
        'Al-Ghifari', 'Al-Fatih', 'Al-Banjari', 'Al-Habsyi', 'Al-Attas', 'Al-Munawwar', 'Firdaus', 'Ramadhan', 'Syahputra', 'Permana',
        'Setiawan', 'Kurniawan', 'Pratama', 'Nugraha', 'Santoso', 'Wijaya', 'Kusuma', 'Siregar', 'Nasution', 'Tanjung',
        'Lubis', 'Harahap', 'Daulay', 'Chaniago', 'Koto', 'Piliang', 'Guci', 'Sikumbang', 'Caniago', 'Jambak',
        'Mandailing', 'Melayu', 'Putra', 'Hidayat', 'Rahman', 'Suryana', 'Saputra', 'Budiman', 'Iskandar', 'Firmansyah'
    ];

    public function run(): void
    {
        $this->command->info('=== MEMULAI SEEDER SIMULASI TERPADU 2026/2027 ===');

        $hashedPassword = Hash::make('password');

        // 1. Tahun Ajaran & Semester
        $this->command->info('1. Menyiapkan Tahun Ajaran 2026/2027 & Semester...');
        AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

        $academicYear = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            [
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
            ]
        );
        if (!$academicYear->is_active) {
            $academicYear->forceFill(['is_active' => true])->save();
        }

        $semesterGanjil = Semester::firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'sequence' => 1],
            [
                'name' => 'Ganjil',
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
            ]
        );
        $semesterGenap = Semester::firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'sequence' => 2],
            [
                'name' => 'Genap',
                'start_date' => '2027-01-01',
                'end_date' => '2027-06-30',
                'is_active' => false,
            ]
        );

        // Membersihkan data simulasi lama untuk tahun ajaran 2026/2027 (Idempotent)
        DB::table('student_grades')->where('academic_year_id', $academicYear->id)->delete();
        DB::table('lms_rapor')->where('tahun_ajaran_id', $academicYear->id)->delete();
        DB::table('attendances')->where('academic_year_id', $academicYear->id)->delete();
        DB::table('tahfizh_daily_logs')->where('academic_year_id', $academicYear->id)->delete();

        // 2. Unit Pendidikan
        $this->command->info('2. Memvalidasi Unit Pendidikan...');
        $units = EducationUnit::where('is_active', true)->get()->keyBy('code');

        // 3. Tenaga Pendidik (Wali Kelas & Musyrif - 33 Orang Unik)
        $this->command->info('3. Membuat 33 Tenaga Pendidik Unik (Wali Kelas & Musyrif)...');
        $waliKelasList = [];
        $waliData = [
            // 12 TKIT
            ['nama' => 'Ustzh. Aisyah Humaira, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-A1 TKIT 1', 'unit' => 'TKIT-01'],
            ['nama' => 'Ustzh. Fatimah Zahra, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-A2 TKIT 1', 'unit' => 'TKIT-01'],
            ['nama' => 'Ustzh. Maryam Khadijah, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-B1 TKIT 1', 'unit' => 'TKIT-01'],
            ['nama' => 'Ustzh. Zainab Safiyyah, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-B2 TKIT 1', 'unit' => 'TKIT-01'],
            ['nama' => 'Ustzh. Hafshah Juwairiyah, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-A1 TKIT 2', 'unit' => 'TKIT-02'],
            ['nama' => 'Ustzh. Maimunah Salma, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-A2 TKIT 2', 'unit' => 'TKIT-02'],
            ['nama' => 'Ustzh. Asma Naila, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-B1 TKIT 2', 'unit' => 'TKIT-02'],
            ['nama' => 'Ustzh. Sarah Yasmin, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-B2 TKIT 2', 'unit' => 'TKIT-02'],
            ['nama' => 'Ustzh. Hajar Lubna, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-A1 TKIT 3', 'unit' => 'TKIT-03'],
            ['nama' => 'Ustzh. Hana Marwa, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-A2 TKIT 3', 'unit' => 'TKIT-03'],
            ['nama' => 'Ustzh. Shafa Rania, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-B1 TKIT 3', 'unit' => 'TKIT-03'],
            ['nama' => 'Ustzh. Farah Syifa, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TK-B2 TKIT 3', 'unit' => 'TKIT-03'],

            // 6 SDIT
            ['nama' => 'Ust. Afrizal Rahman, S.Pd.I', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas 1-A SDIT', 'unit' => 'SDIT-02'],
            ['nama' => 'Ust. Hendra Gunawan, S.Pd', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas 2-A SDIT', 'unit' => 'SDIT-02'],
            ['nama' => 'Ust. Fadli Pratama, S.Pd', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas 3-A SDIT', 'unit' => 'SDIT-02'],
            ['nama' => 'Ustzh. Nurul Aini, S.Pd.SD', 'gender' => 'Perempuan', 'role' => 'Wali Kelas 4-A SDIT', 'unit' => 'SDIT-02'],
            ['nama' => 'Ust. Yogi Firmansyah, S.Pd', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas 5-A SDIT', 'unit' => 'SDIT-02'],
            ['nama' => 'Ust. Rahmat Hidayat, M.Pd', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas 6-A SDIT', 'unit' => 'SDIT-02'],

            // 2 TAUD SaQu
            ['nama' => 'Ustzh. Siti Rahmah, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TAUD-A Shighor', 'unit' => 'TAUD-01'],
            ['nama' => 'Ustzh. Khadijah Al-Kubro, S.Pd.I', 'gender' => 'Perempuan', 'role' => 'Wali Kelas TAUD-B Kibar', 'unit' => 'TAUD-01'],

            // 3 MIT SaQu
            ['nama' => 'Ust. Irfan Maulana, S.Pd.I', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas 1-A MIT', 'unit' => 'MIT-01'],
            ['nama' => 'Ust. Zulkifli Harahap, S.Pd', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas 2-A MIT', 'unit' => 'MIT-01'],
            ['nama' => 'Ustzh. Wardah Salsabila, S.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas 3-A MIT', 'unit' => 'MIT-01'],

            // 3 SMPIT
            ['nama' => 'Ust. Budi Santoso, S.Pd.I', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas VII-A SMPIT (Ikhwan)', 'unit' => 'SMPIT-01'],
            ['nama' => 'Ustzh. Rina Marlina, M.Pd', 'gender' => 'Perempuan', 'role' => 'Wali Kelas VII-B SMPIT (Akhwat)', 'unit' => 'SMPIT-01'],
            ['nama' => 'Ust. M. Ikhsan Kamil, S.Pd', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas VIII-A SMPIT', 'unit' => 'SMPIT-01'],

            // 3 SMAIT
            ['nama' => 'Ust. Ahmad Fauzi, S.Pd.Si', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas X-MIPA SMAIT', 'unit' => 'SMAIT-01'],
            ['nama' => 'Ust. Ilham Kurniawan, M.Si', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas XI-MIPA SMAIT', 'unit' => 'SMAIT-01'],
            ['nama' => 'Ust. Rizal Efendi, S.Pd', 'gender' => 'Laki-laki', 'role' => 'Wali Kelas XII-IPS SMAIT', 'unit' => 'SMAIT-01'],

            // 3 Musyrif Asrama Ponpes & Mahad
            ['nama' => 'Ust. Ahmad Mudzakkir, Lc.', 'gender' => 'Laki-laki', 'role' => 'Musyrif Asrama Al-Farabi (Putra)', 'unit' => 'PONPES-PA'],
            ['nama' => 'Ustzh. Fatimah Az-Zahra, S.Pd.I', 'gender' => 'Perempuan', 'role' => 'Musyrifah Asrama Khadijah (Putri)', 'unit' => 'PONPES-PI'],
            ['nama' => 'Ust. Muhammad Ridwan, M.Ag', 'gender' => 'Laki-laki', 'role' => 'Musyrif Asrama Abu Bakar (Ma\'had)', 'unit' => 'MAHAD-01'],

            // 1 Playhouse
            ['nama' => 'Bunda Rahmawati, S.Psi', 'gender' => 'Perempuan', 'role' => 'Koordinator Caregiver Playhouse Group A', 'unit' => 'PLAYHOUSE-01'],
        ];

        foreach ($waliData as $idx => $wd) {
            $cleanName = $wd['nama'];
            $this->usedNames[$cleanName] = true;
            $unitTarget = $units[$wd['unit']] ?? null;
            if (!$unitTarget) {
                continue;
            }
            $email = 'wali' . ($idx + 1) . '@dareliman.sch.id';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $cleanName,
                    'password' => $hashedPassword,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // DEF-ORPHAN-001 FIX: Assign canonical role to user
            $targetRole = str_contains($wd['role'], 'Musyrif') ? 'Musyrif' : 'Wali Kelas';
            $user->syncRoles([$targetRole]);

            $emp = Employee::firstOrCreate(
                ['nama_lengkap' => $cleanName],
                [
                    'id' => (string) Str::uuid(),
                    'niy' => 'GUR-2026-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                    'nama_panggilan' => explode(' ', $cleanName)[1] ?? 'Guru',
                    'jenis_kelamin' => (str_contains($wd['gender'], 'Laki') || $wd['gender'] === 'L') ? 'L' : 'P',
                    'status' => 'Aktif',
                    'status_pegawai' => 'Tetap',
                    'unit_id' => $unitTarget ? $unitTarget->id : null,
                    'user_id' => $user->id,
                    'email' => $email,
                    'no_hp' => '0812' . str_pad((string)($idx + 1000), 8, '0', STR_PAD_LEFT),
                    'tanggal_masuk' => '2026-07-01',
                    'metadata' => ['role_tugas' => $wd['role']],
                ]
            );
            $waliKelasList[$idx] = $emp;
        }

        // 4. Mata Pelajaran Utama
        $this->command->info('4. Menyiapkan Master Mata Pelajaran...');
        $mapelTemplates = [
            ['kode' => 'TAHF-01', 'nama' => 'Tahfizh Al-Qur\'an', 'kategori' => 'Al-Quran'],
            ['kode' => 'DINI-01', 'nama' => 'Pendidikan Agama Islam & Adab', 'kategori' => 'Diniyyah'],
            ['kode' => 'BARB-01', 'nama' => 'Bahasa Arab', 'kategori' => 'Bahasa'],
            ['kode' => 'BIND-01', 'nama' => 'Bahasa Indonesia', 'kategori' => 'Umum'],
            ['kode' => 'MTK-01',  'nama' => 'Matematika', 'kategori' => 'Eksak'],
            ['kode' => 'SAIN-01', 'nama' => 'Ilmu Pengetahuan Alam (IPA/Sains)', 'kategori' => 'Eksak'],
            ['kode' => 'PJOK-01', 'nama' => 'PJOK (Olahraga & Kesehatan)', 'kategori' => 'Umum'],
        ];

        $subjectMap = [];
        foreach ($units as $uCode => $unitObj) {
            foreach ($mapelTemplates as $mt) {
                $subj = Subject::firstOrCreate(
                    [
                        'unit_pendidikan_id' => $unitObj->id,
                        'kode_mapel' => $uCode . '-' . $mt['kode'],
                    ],
                    [
                        'nama_mapel' => $mt['nama'],
                        'kategori' => $mt['kategori'],
                        'kkm' => 75.0,
                        'jam_pelajaran' => 2,
                        'status' => true,
                        'bobot_nilai' => ['tugas' => 30, 'uts' => 30, 'uas' => 40],
                    ]
                );
                $subjectMap[$uCode][] = $subj;
            }
        }

        // 5. Membuat 33 Rombel & Asrama
        $this->command->info('5. Mengonfigurasi 33 Rombel & Asrama...');
        $classDefinitions = [
            // TKIT-01 (4 kelas @ 30 = 120)
            ['unit' => 'TKIT-01', 'tingkat' => 'TK-A', 'kode' => 'TK1-A1', 'nama' => 'Kelas TK-A1 (Usamah)', 'ruang' => 'R-TK1-01', 'kapasitas' => 30, 'wali_idx' => 0],
            ['unit' => 'TKIT-01', 'tingkat' => 'TK-A', 'kode' => 'TK1-A2', 'nama' => 'Kelas TK-A2 (Thalhah)', 'ruang' => 'R-TK1-02', 'kapasitas' => 30, 'wali_idx' => 1],
            ['unit' => 'TKIT-01', 'tingkat' => 'TK-B', 'kode' => 'TK1-B1', 'nama' => 'Kelas TK-B1 (Zubair)', 'ruang' => 'R-TK1-03', 'kapasitas' => 30, 'wali_idx' => 2],
            ['unit' => 'TKIT-01', 'tingkat' => 'TK-B', 'kode' => 'TK1-B2', 'nama' => 'Kelas TK-B2 (Sa\'ad)', 'ruang' => 'R-TK1-04', 'kapasitas' => 30, 'wali_idx' => 3],

            // TKIT-02 (4 kelas @ 30 = 120)
            ['unit' => 'TKIT-02', 'tingkat' => 'TK-A', 'kode' => 'TK2-A1', 'nama' => 'Kelas TK-A1 (Hamzah)', 'ruang' => 'R-TK2-01', 'kapasitas' => 30, 'wali_idx' => 4],
            ['unit' => 'TKIT-02', 'tingkat' => 'TK-A', 'kode' => 'TK2-A2', 'nama' => 'Kelas TK-A2 (Bilal)', 'ruang' => 'R-TK2-02', 'kapasitas' => 30, 'wali_idx' => 5],
            ['unit' => 'TKIT-02', 'tingkat' => 'TK-B', 'kode' => 'TK2-B1', 'nama' => 'Kelas TK-B1 (Salman)', 'ruang' => 'R-TK2-03', 'kapasitas' => 30, 'wali_idx' => 6],
            ['unit' => 'TKIT-02', 'tingkat' => 'TK-B', 'kode' => 'TK2-B2', 'nama' => 'Kelas TK-B2 (Khalid)', 'ruang' => 'R-TK2-04', 'kapasitas' => 30, 'wali_idx' => 7],

            // TKIT-03 (4 kelas @ 30 = 120)
            ['unit' => 'TKIT-03', 'tingkat' => 'TK-A', 'kode' => 'TK3-A1', 'nama' => 'Kelas TK-A1 (Abu Ubaidah)', 'ruang' => 'R-TK3-01', 'kapasitas' => 30, 'wali_idx' => 8],
            ['unit' => 'TKIT-03', 'tingkat' => 'TK-A', 'kode' => 'TK3-A2', 'nama' => 'Kelas TK-A2 (Abdurrahman)', 'ruang' => 'R-TK3-02', 'kapasitas' => 30, 'wali_idx' => 9],
            ['unit' => 'TKIT-03', 'tingkat' => 'TK-B', 'kode' => 'TK3-B1', 'nama' => 'Kelas TK-B1 (Ja\'far)', 'ruang' => 'R-TK3-03', 'kapasitas' => 30, 'wali_idx' => 10],
            ['unit' => 'TKIT-03', 'tingkat' => 'TK-B', 'kode' => 'TK3-B2', 'nama' => 'Kelas TK-B2 (Mu\'adz)', 'ruang' => 'R-TK3-04', 'kapasitas' => 30, 'wali_idx' => 11],

            // SDIT-02 (6 kelas @ 30 = 180)
            ['unit' => 'SDIT-02', 'tingkat' => '1', 'kode' => 'SD-1A', 'nama' => 'Kelas 1-A (Abu Bakar)', 'ruang' => 'Gedung A R-101', 'kapasitas' => 30, 'wali_idx' => 12],
            ['unit' => 'SDIT-02', 'tingkat' => '2', 'kode' => 'SD-2A', 'nama' => 'Kelas 2-A (Umar bin Khattab)', 'ruang' => 'Gedung A R-201', 'kapasitas' => 30, 'wali_idx' => 13],
            ['unit' => 'SDIT-02', 'tingkat' => '3', 'kode' => 'SD-3A', 'nama' => 'Kelas 3-A (Utsman bin Affan)', 'ruang' => 'Gedung A R-301', 'kapasitas' => 30, 'wali_idx' => 14],
            ['unit' => 'SDIT-02', 'tingkat' => '4', 'kode' => 'SD-4A', 'nama' => 'Kelas 4-A (Ali bin Abi Thalib)', 'ruang' => 'Gedung B R-101', 'kapasitas' => 30, 'wali_idx' => 15],
            ['unit' => 'SDIT-02', 'tingkat' => '5', 'kode' => 'SD-5A', 'nama' => 'Kelas 5-A (Thalhah bin Ubaidillah)', 'ruang' => 'Gedung B R-201', 'kapasitas' => 30, 'wali_idx' => 16],
            ['unit' => 'SDIT-02', 'tingkat' => '6', 'kode' => 'SD-6A', 'nama' => 'Kelas 6-A (Zubair bin Awwam)', 'ruang' => 'Gedung B R-301', 'kapasitas' => 30, 'wali_idx' => 17],

            // TAUD-01 (2 kelas @ 30 = 60)
            ['unit' => 'TAUD-01', 'tingkat' => 'PAUD', 'kode' => 'TAUD-A', 'nama' => 'Kelas TAUD-A (Shighor)', 'ruang' => 'R-TAUD-01', 'kapasitas' => 30, 'wali_idx' => 18],
            ['unit' => 'TAUD-01', 'tingkat' => 'PAUD', 'kode' => 'TAUD-B', 'nama' => 'Kelas TAUD-B (Kibar)', 'ruang' => 'R-TAUD-02', 'kapasitas' => 30, 'wali_idx' => 19],

            // MIT-01 (3 kelas @ 30 = 90)
            ['unit' => 'MIT-01', 'tingkat' => '1', 'kode' => 'MIT-1A', 'nama' => 'Kelas 1-A MIT SaQu', 'ruang' => 'Gedung MIT R-101', 'kapasitas' => 30, 'wali_idx' => 20],
            ['unit' => 'MIT-01', 'tingkat' => '2', 'kode' => 'MIT-2A', 'nama' => 'Kelas 2-A MIT SaQu', 'ruang' => 'Gedung MIT R-201', 'kapasitas' => 30, 'wali_idx' => 21],
            ['unit' => 'MIT-01', 'tingkat' => '3', 'kode' => 'MIT-3A', 'nama' => 'Kelas 3-A MIT SaQu', 'ruang' => 'Gedung MIT R-301', 'kapasitas' => 30, 'wali_idx' => 22],

            // SMPIT-01 (3 kelas @ 30 = 90)
            ['unit' => 'SMPIT-01', 'tingkat' => '7', 'kode' => 'SMP-7A', 'nama' => 'Kelas VII-A (Ikhwan)', 'ruang' => 'Gedung SMP R-101', 'kapasitas' => 30, 'wali_idx' => 23],
            ['unit' => 'SMPIT-01', 'tingkat' => '7', 'kode' => 'SMP-7B', 'nama' => 'Kelas VII-B (Akhwat)', 'ruang' => 'Gedung SMP R-102', 'kapasitas' => 30, 'wali_idx' => 24],
            ['unit' => 'SMPIT-01', 'tingkat' => '8', 'kode' => 'SMP-8A', 'nama' => 'Kelas VIII-A (Reguler)', 'ruang' => 'Gedung SMP R-201', 'kapasitas' => 30, 'wali_idx' => 25],

            // SMAIT-01 (3 kelas @ 30 = 90)
            ['unit' => 'SMAIT-01', 'tingkat' => '10', 'kode' => 'SMA-10MIPA', 'nama' => 'Kelas X-MIPA (Tahfizh Sains)', 'ruang' => 'Gedung SMA R-101', 'kapasitas' => 30, 'wali_idx' => 26],
            ['unit' => 'SMAIT-01', 'tingkat' => '11', 'kode' => 'SMA-11MIPA', 'nama' => 'Kelas XI-MIPA (Olimpiade)', 'ruang' => 'Gedung SMA R-201', 'kapasitas' => 30, 'wali_idx' => 27],
            ['unit' => 'SMAIT-01', 'tingkat' => '12', 'kode' => 'SMA-12IPS',  'nama' => 'Kelas XII-IPS (Dirasat Islam)', 'ruang' => 'Gedung SMA R-301', 'kapasitas' => 30, 'wali_idx' => 28],

            // PONPES & MA'HAD (3 Asrama / Kamar @ 50 = 150)
            ['unit' => 'PONPES-PA', 'tingkat' => 'Boarding', 'kode' => 'ASR-FARABI', 'nama' => 'Asrama Gedung Al-Farabi (Putra)', 'ruang' => 'Kamar Asrama 101-105', 'kapasitas' => 50, 'wali_idx' => 29],
            ['unit' => 'PONPES-PI', 'tingkat' => 'Boarding', 'kode' => 'ASR-KHADIJAH', 'nama' => 'Asrama Gedung Khadijah (Putri)', 'ruang' => 'Kamar Asrama Putri A', 'kapasitas' => 50, 'wali_idx' => 30],
            ['unit' => 'MAHAD-01',  'tingkat' => 'Mahad',    'kode' => 'ASR-ABUBAKAR', 'nama' => 'Asrama Gedung Abu Bakar (Ma\'had)', 'ruang' => 'Kamar Tholabah 201', 'kapasitas' => 50, 'wali_idx' => 31],

            // PLAYHOUSE-01 (1 kelompok @ 30 = 30)
            ['unit' => 'PLAYHOUSE-01', 'tingkat' => 'Daycare', 'kode' => 'PLAY-GRP1', 'nama' => 'Toddler & Daycare Group A', 'ruang' => 'Aula Playhouse', 'kapasitas' => 30, 'wali_idx' => 32],
        ];

        $kelasCollection = [];
        foreach ($classDefinitions as $cd) {
            $unitTarget = $units[$cd['unit']] ?? null;
            if (!$unitTarget) {
                continue;
            }
            $waliEmp = $waliKelasList[$cd['wali_idx']] ?? null;
            if (!$waliEmp) {
                continue;
            }

            // 1 baris di tbl_kelas
            $kelas = Kelas::firstOrCreate(
                [
                    'unit_pendidikan_id' => $unitTarget->id,
                    'kode_kelas' => $cd['kode'],
                    'tahun_ajaran_id' => $academicYear->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'semester_id' => $semesterGanjil->id,
                    'jenjang' => $unitTarget->level,
                    'tingkat' => $cd['tingkat'],
                    'nama_kelas' => $cd['nama'],
                    'wali_kelas_id' => $waliEmp->id,
                    'kapasitas' => $cd['kapasitas'],
                    'ruangan' => $cd['ruang'],
                    'status' => 'Aktif',
                ]
            );

            // Sync ke classes legacy table
            $existingClass = DB::table('classes')->where([
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semesterGanjil->id,
                'name' => $cd['nama'],
            ])->first();

            $legacyClassId = $kelas->id;
            if ($existingClass) {
                $legacyClassId = $existingClass->id;
                DB::table('classes')->where('id', $existingClass->id)->update([
                    'level' => $cd['tingkat'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('classes')->insert([
                    'id' => $kelas->id,
                    'academic_year_id' => $academicYear->id,
                    'semester_id' => $semesterGanjil->id,
                    'homeroom_teacher_id' => null,
                    'name' => $cd['nama'],
                    'level' => $cd['tingkat'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Jadwal KBM Senin s/d Jumat (Conflict-Free)
            $unitSubjs = $subjectMap[$cd['unit']] ?? [];
            if (!empty($unitSubjs)) {
                $days = [1, 2, 3, 4, 5]; // Senin s/d Jumat
                $timeSlots = [
                    ['start' => '07:30:00', 'end' => '09:00:00'], // Sesi Tahfizh Pagi
                    ['start' => '09:30:00', 'end' => '11:30:00'], // Sesi Mapel 1
                    ['start' => '13:00:00', 'end' => '14:30:00'], // Sesi Mapel 2
                ];

                foreach ($days as $dayNum) {
                    foreach ($timeSlots as $tsIdx => $ts) {
                        $subjTarget = $unitSubjs[($dayNum + $tsIdx) % count($unitSubjs)];
                        ClassSchedule::firstOrCreate(
                            [
                                'kelas_id' => $kelas->id,
                                'day_of_week' => $dayNum,
                                'time_start' => $ts['start'],
                            ],
                            [
                                'id' => (string) Str::uuid(),
                                'class_id' => $kelas->id,
                                'employee_id' => $waliEmp->id,
                                'teacher_id' => null,
                                'subject_id' => $subjTarget->id,
                                'academic_year_id' => $academicYear->id,
                                'semester_id' => $semesterGanjil->id,
                                'time_end' => $ts['end'],
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }

            // Mentor / Musyrif Assignment jika boarding
            if (in_array($cd['unit'], ['PONPES-PA', 'PONPES-PI', 'MAHAD-01'])) {
                MutabaahMentorAssignment::firstOrCreate(
                    [
                        'employee_id' => $waliEmp->id,
                        'class_id' => $kelas->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'mentor_type' => 'musyrif',
                        'unit_id' => $unitTarget->id,
                        'level' => $cd['tingkat'],
                        'dormitory' => $cd['nama'],
                        'room' => $cd['ruang'],
                        'group_name' => $cd['kode'],
                        'start_date' => '2026-07-01',
                        'is_active' => true,
                    ]
                );
            }

            $kelasCollection[] = [
                'kelas' => $kelas,
                'legacy_class_id' => $legacyClassId,
                'def' => $cd,
                'unit' => $unitTarget,
                'wali' => $waliEmp,
            ];
        }

        // 6. Membuat 1.050 Siswa, Orang Tua, dan Nilai/Rapor Unik
        $this->command->info('6. Mengenerate 1.050 Siswa & Orang Tua Unik (Zero Duplikasi)...');
        $studentCounter = 1;
        $allStudentGrades = [];
        $allRapor = [];
        $sampleAttendances = [];
        $sampleTahfizhLogs = [];

        foreach ($kelasCollection as $kc) {
            $kelasObj = $kc['kelas'];
            $legacyClassId = $kc['legacy_class_id'] ?? $kelasObj->id;
            $unitObj = $kc['unit'];
            $waliEmp = $kc['wali'];
            $kapasitas = $kc['def']['kapasitas'];
            $unitSubjs = $subjectMap[$kc['def']['unit']] ?? [];

            for ($s = 1; $s <= $kapasitas; $s++) {
                $isMale = ($kc['def']['unit'] === 'PONPES-PI') ? false : (($kc['def']['unit'] === 'PONPES-PA') ? true : ($s % 2 === 1));
                $studentName = $this->generateUniqueName($isMale);
                $parentFather = $this->generateUniqueName(true);
                $parentMother = $this->generateUniqueName(false);

                $nis = '202601' . str_pad($studentCounter, 4, '0', STR_PAD_LEFT);
                $nisn = '009123' . str_pad($studentCounter, 4, '0', STR_PAD_LEFT);

                // User Akun Siswa & Orang Tua
                $studentEmail = 'siswa' . $studentCounter . '@dareliman.sch.id';
                $parentEmail = 'wali' . $studentCounter . '@family.dareliman.sch.id';

                $existingStudentUser = DB::table('users')->where('email', $studentEmail)->first();
                $studentUserId = $existingStudentUser ? $existingStudentUser->id : (string) Str::uuid();
                if (!$existingStudentUser) {
                    DB::table('users')->insert([
                        'id' => $studentUserId,
                        'name' => $studentName,
                        'email' => $studentEmail,
                        'password' => $hashedPassword,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $existingParentUser = DB::table('users')->where('email', $parentEmail)->first();
                $parentUserId = $existingParentUser ? $existingParentUser->id : (string) Str::uuid();
                if (!$existingParentUser) {
                    DB::table('users')->insert([
                        'id' => $parentUserId,
                        'name' => $parentFather,
                        'email' => $parentEmail,
                        'password' => $hashedPassword,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Data Orang Tua
                $existingParent = DB::table('parents')->where('email', $parentEmail)->first();
                $parentId = $existingParent ? $existingParent->id : (string) Str::uuid();
                if (!$existingParent) {
                    DB::table('parents')->insert([
                        'id' => $parentId,
                        'user_id' => $parentUserId,
                        'full_name' => $parentFather,
                        'phone' => '0821' . str_pad((string)$studentCounter, 8, '0', STR_PAD_LEFT),
                        'email' => $parentEmail,
                        'occupation' => ($s % 3 === 0) ? 'Wiraswasta' : (($s % 3 === 1) ? 'PNS / Guru' : 'Karyawan Swasta'),
                        'address' => 'Jl. Harapan Mulia No. ' . $s . ', Kota Padang',
                        'metadata' => json_encode(['nama_ibu' => $parentMother]),
                        'created_at' => '2026-07-01 08:00:00',
                        'updated_at' => '2026-07-01 08:00:00',
                    ]);
                }

                // Data Siswa
                $existingStudent = DB::table('students')->where('nis', $nis)->first();
                $studentId = $existingStudent ? $existingStudent->id : (string) Str::uuid();
                if (!$existingStudent) {
                    DB::table('students')->insert([
                        'id' => $studentId,
                        'user_id' => $studentUserId,
                        'parent_id' => $parentId,
                        'unit_id' => $unitObj->id,
                        'kelas_id' => $kelasObj->id,
                        'class_id' => $legacyClassId,
                        'nis' => $nis,
                        'nisn' => $nisn,
                        'full_name' => $studentName,
                        'gender' => $isMale ? 'male' : 'female',
                        'birth_date' => Carbon::parse('2026-07-01')->subYears(6 + (int)($s % 8))->format('Y-m-d'),
                        'birth_place' => ($s % 2 === 0) ? 'Padang' : 'Bukittinggi',
                        'address' => 'Jl. Harapan Mulia No. ' . $s . ', Kota Padang',
                        'tahun_masuk' => '2026',
                        'is_active' => true,
                        'created_at' => '2026-07-01 08:00:00',
                        'updated_at' => '2026-07-01 08:00:00',
                    ]);
                }

                // Relasi StudentParent
                $existingSP = DB::table('student_parents')->where('student_id', $studentId)->where('parent_id', $parentId)->first();
                if (!$existingSP) {
                    DB::table('student_parents')->insert([
                        'id' => (string) Str::uuid(),
                        'student_id' => $studentId,
                        'parent_id' => $parentId,
                        'relationship_type' => 'father',
                        'is_primary' => true,
                        'created_at' => '2026-07-01 08:00:00',
                        'updated_at' => '2026-07-01 08:00:00',
                    ]);
                }

                // Penilaian StudentGrade untuk setiap Mapel
                $totalNilaiStudent = 0;
                $mapelCount = 0;

                foreach ($unitSubjs as $sb) {
                    $scoreTugas = 80 + ($studentCounter + $s) % 18; // 80 - 97
                    $scoreQuiz  = 78 + ($studentCounter + $s) % 20; // 78 - 97
                    $scoreMid   = 82 + ($studentCounter + $s) % 16; // 82 - 97 (Rapor Bayangan / PTS)
                    $scoreFinal = 80 + ($studentCounter + $s) % 18; // 80 - 97 (PAS)
                    $finalScore = round(($scoreTugas * 0.3) + ($scoreMid * 0.3) + ($scoreFinal * 0.4), 1);
                    $totalNilaiStudent += $finalScore;
                    $mapelCount++;

                    $allStudentGrades[] = [
                        'id' => (string) Str::uuid(),
                        'student_id' => $studentId,
                        'subject_id' => $sb->id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semesterGanjil->id,
                        'kelas_id' => $kelasObj->id,
                        'class_id' => $legacyClassId,
                        'score_assignment' => $scoreTugas,
                        'score_quiz' => $scoreQuiz,
                        'score_project' => $scoreTugas,
                        'score_midterm' => $scoreMid,
                        'score_final' => $scoreFinal,
                        'final_score' => $finalScore,
                        'grade_letter' => ($finalScore >= 90) ? 'A' : (($finalScore >= 80) ? 'B' : 'C'),
                        'is_passed' => true,
                        'notes' => 'Menunjukkan pemahaman materi yang sangat baik dan aktif dalam halaqah.',
                        'created_at' => '2026-09-08 14:00:00',
                        'updated_at' => '2026-09-08 14:00:00',
                    ];
                }

                // Rapor Semester 1 (Ganjil) & Rapor Bayangan
                $rataRata = $mapelCount > 0 ? round($totalNilaiStudent / $mapelCount, 2) : 88.5;
                $allRapor[] = [
                    'id' => (string) Str::uuid(),
                    'siswa_id' => $studentId,
                    'kelas_id' => $kelasObj->id,
                    'semester_id' => $semesterGanjil->id,
                    'tahun_ajaran_id' => $academicYear->id,
                    'guru_wali_id' => $waliEmp->id,
                    'total_nilai' => $totalNilaiStudent,
                    'rata_rata' => $rataRata,
                    'peringkat_kelas' => ($s % $kapasitas) + 1,
                    'total_siswa_kelas' => $kapasitas,
                    'total_mapel' => $mapelCount,
                    'mapel_lulus' => $mapelCount,
                    'mapel_tidak_lulus' => 0,
                    'total_hari_efektif' => 50,
                    'total_hadir' => 48,
                    'total_izin' => 1,
                    'total_sakit' => 1,
                    'total_alpha' => 0,
                    'catatan_wali_kelas' => 'Ananda menunjukkan kesungguhan dalam hafalan dan akhlak yang mulia. Pertahankan semangat belajar.',
                    'catatan_kepala_sekolah' => 'Alhamdulillah, capaian pembelajaran memuaskan.',
                    'status_rapor' => 'terbit',
                    'tanggal_terbit' => '2026-10-05', // Terbit PTS / Rapor Bayangan
                    'sudah_dilihat_ortu' => true,
                    'created_at' => '2026-10-05 10:00:00',
                    'updated_at' => '2026-10-05 10:00:00',
                ];

                // Tahfizh Record & Logs (Diverse & Realistic Historical Logs)
                $surahPool = [
                    ['number' => 78, 'name' => 'An-Naba', 'total' => 40, 'juz' => 30],
                    ['number' => 79, 'name' => 'An-Nazi\'at', 'total' => 46, 'juz' => 30],
                    ['number' => 80, 'name' => '\'Abasa', 'total' => 42, 'juz' => 30],
                    ['number' => 81, 'name' => 'At-Takwir', 'total' => 29, 'juz' => 30],
                    ['number' => 82, 'name' => 'Al-Infitar', 'total' => 19, 'juz' => 30],
                    ['number' => 83, 'name' => 'Al-Muthaffifin', 'total' => 36, 'juz' => 30],
                    ['number' => 84, 'name' => 'Al-Insyiqaq', 'total' => 25, 'juz' => 30],
                    ['number' => 85, 'name' => 'Al-Buruj', 'total' => 22, 'juz' => 30],
                    ['number' => 86, 'name' => 'At-Tariq', 'total' => 17, 'juz' => 30],
                    ['number' => 87, 'name' => 'Al-A\'la', 'total' => 19, 'juz' => 30],
                    ['number' => 88, 'name' => 'Al-Ghasyiyah', 'total' => 26, 'juz' => 30],
                    ['number' => 89, 'name' => 'Al-Fajr', 'total' => 30, 'juz' => 30],
                    ['number' => 90, 'name' => 'Al-Balad', 'total' => 20, 'juz' => 30],
                    ['number' => 91, 'name' => 'Asy-Syams', 'total' => 15, 'juz' => 30],
                    ['number' => 92, 'name' => 'Al-Lail', 'total' => 21, 'juz' => 30],
                    ['number' => 93, 'name' => 'Ad-Duha', 'total' => 11, 'juz' => 30],
                    ['number' => 95, 'name' => 'At-Tin', 'total' => 8, 'juz' => 30],
                    ['number' => 96, 'name' => 'Al-\'Alaq', 'total' => 19, 'juz' => 30],
                    ['number' => 97, 'name' => 'Al-Qadr', 'total' => 5, 'juz' => 30],
                    ['number' => 98, 'name' => 'Al-Bayyinah', 'total' => 8, 'juz' => 30],
                    ['number' => 99, 'name' => 'Az-Zalzalah', 'total' => 8, 'juz' => 30],
                    ['number' => 100, 'name' => 'Al-\'Adiyat', 'total' => 11, 'juz' => 30],
                    ['number' => 101, 'name' => 'Al-Qari\'ah', 'total' => 11, 'juz' => 30],
                    ['number' => 102, 'name' => 'At-Takasur', 'total' => 8, 'juz' => 30],
                    ['number' => 103, 'name' => 'Al-\'Asr', 'total' => 3, 'juz' => 30],
                    ['number' => 108, 'name' => 'Al-Kausar', 'total' => 3, 'juz' => 30],
                    ['number' => 112, 'name' => 'Al-Ikhlas', 'total' => 4, 'juz' => 30],
                    ['number' => 114, 'name' => 'An-Nas', 'total' => 6, 'juz' => 30],
                ];

                $unitCode = $unitObj->code;
                $isTkitOrTaud = str_contains($unitCode, 'TKIT') || str_contains($unitCode, 'TAUD');

                // 2 - 3 historical logs per student
                $logCountForStudent = ($s % 3 === 0) ? 3 : 2;
                for ($logIdx = 0; $logIdx < $logCountForStudent; $logIdx++) {
                    $surahItemIndex = ($studentCounter * 3 + $logIdx * 5) % count($surahPool);
                    if ($isTkitOrTaud) {
                        $surahItemIndex = 15 + (($studentCounter + $logIdx) % (count($surahPool) - 15));
                    }
                    $chosenSurah = $surahPool[$surahItemIndex];

                    $dateList = [
                        '2026-08-' . str_pad((string)(10 + ($s % 15)), 2, '0', STR_PAD_LEFT),
                        '2026-08-' . str_pad((string)(22 + ($s % 7)), 2, '0', STR_PAD_LEFT),
                        '2026-09-0' . (1 + ($s % 8)),
                    ];
                    $recordDate = $dateList[$logIdx] ?? '2026-09-08';
                    $dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
                    $dayName = $dayNames[($studentCounter + $logIdx) % count($dayNames)];

                    $ayahStart = ($logIdx === 0) ? 1 : min(11, $chosenSurah['total']);
                    $ayahEnd = min($chosenSurah['total'], $ayahStart + 10 + ($s % 10));

                    $types = ['Ziyadah', 'Ziyadah', 'Murajaah', 'Tasmi', 'Ujian'];
                    $type = $types[($studentCounter + $logIdx) % count($types)];

                    $predikats = [
                        ['Mumtaz', 'lancar', 'Mumtaz', 'Fasih', 'Tajwid dan makhraj sangat baik (Mumtaz).'],
                        ['Jayyid Jiddan', 'lancar', 'Jayyid Jiddan', 'Cukup Fasih', 'Bagus sekali, perhatikan mad jaiz dan ghunnah.'],
                        ['Jayyid', 'lancar', 'Jayyid', 'Standar', 'Lancar, teruskan murajaah mandiri di rumah.'],
                        ['Maqbul', 'kurang_lancar', 'Maqbul', 'Perlu Bimbingan', 'Selesai setoran, perlu pendampingan makhraj huruf.'],
                    ];
                    $pred = $predikats[($studentCounter + $logIdx * 2) % count($predikats)];

                    $sampleTahfizhLogs[] = [
                        'id' => (string) Str::uuid(),
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semesterGanjil->id,
                        'class_id' => $kelasObj->id,
                        'student_id' => $studentId,
                        'teacher_id' => $waliEmp->id,
                        'record_date' => $recordDate,
                        'day_name' => $dayName,
                        'hafalan_surah_number' => $chosenSurah['number'],
                        'hafalan_surah_name' => $chosenSurah['name'],
                        'hafalan_ayah_start' => $ayahStart,
                        'hafalan_ayah_end' => $ayahEnd,
                        'hafalan_baris' => max(1, $ayahEnd - $ayahStart + 1),
                        'murajaah_text' => 'Juz ' . $chosenSurah['juz'] . ' (' . $chosenSurah['name'] . ')',
                        'murajaah_lembar' => max(1, round(($ayahEnd - $ayahStart + 1) / 5, 1)),
                        'status' => $pred[1],
                        'notes_teacher' => $pred[4],
                        'metadata' => json_encode([
                            'type' => $type,
                            'juz' => $chosenSurah['juz'],
                            'kelancaran' => $pred[1],
                            'tajwid' => $pred[2],
                            'makhraj' => $pred[3],
                            'nilai_huruf' => $pred[0],
                            'skor' => $pred[0] === 'Mumtaz' ? 95 : ($pred[0] === 'Jayyid Jiddan' ? 88 : ($pred[0] === 'Jayyid' ? 80 : 75)),
                            'catatan' => $pred[4],
                        ]),
                        'created_at' => $recordDate . ' 08:30:00',
                        'updated_at' => $recordDate . ' 08:30:00',
                    ];
                }

                // Presensi Sampel (Hari ini: 9 September 2026 jam 07.10 WIB)
                $sampleAttendances[] = [
                    'id' => (string) Str::uuid(),
                    'academic_year_id' => $academicYear->id,
                    'semester_id' => $semesterGanjil->id,
                    'month' => 9,
                    'attendance_date' => '2026-09-09',
                    'student_id' => $studentId,
                    'employee_id' => null,
                    'class_id' => $legacyClassId,
                    'unit_pendidikan_id' => $unitObj->id,
                    'check_in_time' => '2026-09-09 07:10:00',
                    'check_out_time' => null, // Sedang KBM berjalan
                    'status' => ($s % 25 === 0) ? 'Izin' : (($s % 29 === 0) ? 'Sakit' : 'Hadir'),
                    'tipe_presensi' => 'gerbang',
                    'attendance_method' => 'RFID',
                    'created_at' => '2026-09-09 07:10:00',
                    'updated_at' => '2026-09-09 07:10:00',
                ];

                $studentCounter++;
            }
        }

        // Log Presensi Tenaga Pendidik & Guru (Agustus & September 2026)
        $this->command->info('Menyiapkan Log Presensi Resmi Tenaga Pendidik (Agustus - September 2026)...');
        $teacherAttendances = [];
        $workingDays = [];
        // Hari kerja Agustus 2026 (Senin-Jumat)
        for ($d = 3; $d <= 31; $d++) {
            $dt = sprintf('2026-08-%02d', $d);
            $w = date('N', strtotime($dt));
            if ($w <= 5) $workingDays[] = $dt;
        }
        // Hari kerja September 2026 (s.d. 9 September 2026)
        for ($d = 1; $d <= 9; $d++) {
            $dt = sprintf('2026-09-%02d', $d);
            $w = date('N', strtotime($dt));
            if ($w <= 5) $workingDays[] = $dt;
        }

        $allActiveEmployees = Employee::whereNotNull('user_id')->get();
        if ($allActiveEmployees->isEmpty()) {
            $allActiveEmployees = collect($waliKelasList);
        }

        foreach ($allActiveEmployees as $tIdx => $tEmp) {
            foreach ($workingDays as $wIdx => $wDate) {
                $monthNum = (int) substr($wDate, 5, 2);
                $isLate = (($tIdx + $wIdx) % 11 === 0);
                $checkInTime = $wDate . ($isLate ? ' 07:22:15' : ' 07:05:40');
                $checkOutTime = $wDate . ' 16:05:00';
                $checkInShort = $isLate ? '07:22' : '07:05';
                $method = (($tIdx + $wIdx) % 3 === 0) ? 'QR' : 'RFID';
                $methodLabel = $method === 'QR' ? 'QR Code Kartu' : 'RFID Tap';

                $teacherAttendances[] = [
                    'id' => (string) Str::uuid(),
                    'academic_year_id' => $academicYear->id,
                    'semester_id' => $semesterGanjil->id,
                    'month' => $monthNum,
                    'attendance_date' => $wDate,
                    'student_id' => null,
                    'employee_id' => $tEmp->id,
                    'class_id' => null,
                    'unit_pendidikan_id' => $tEmp->unit_id,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'status' => $isLate ? 'TERLAMBAT' : 'HADIR',
                    'tipe_presensi' => 'pegawai',
                    'attendance_method' => $method,
                    'location' => 'Gate Utama',
                    'metadata' => json_encode([
                        'device' => 'Gate Scanner #01',
                        'location' => 'Gate Utama',
                        'check_in' => $checkInShort,
                        'check_out' => '16:05',
                        'method' => $methodLabel,
                        'user_id' => $tEmp->user_id,
                    ]),
                    'created_at' => $checkInTime,
                    'updated_at' => $checkOutTime,
                ];
            }
        }

        // 7. Bulk Insert Nilai, Rapor, Tahfizh & Presensi
        $this->command->info('7. Menyimpan Bulk Data Nilai (' . count($allStudentGrades) . ' baris)...');
        foreach (array_chunk($allStudentGrades, 250) as $chunkGrades) {
            DB::table('student_grades')->insert($chunkGrades);
        }

        $this->command->info('8. Menyimpan Bulk Data Rapor Akademik (' . count($allRapor) . ' baris)...');
        foreach (array_chunk($allRapor, 250) as $chunkRapor) {
            DB::table('lms_rapor')->insert($chunkRapor);
        }

        $this->command->info('9. Menyimpan Log Tahfizh Harian Terpadu (' . count($sampleTahfizhLogs) . ' baris)...');
        foreach (array_chunk($sampleTahfizhLogs, 250) as $chunkTahfizh) {
            DB::table('tahfizh_daily_logs')->insert($chunkTahfizh);
        }

        $this->command->info('10. Menyimpan Presensi Siswa (' . count($sampleAttendances) . ' baris)...');
        foreach (array_chunk($sampleAttendances, 250) as $chunkAtt) {
            DB::table('attendances')->insert($chunkAtt);
        }

        $this->command->info('11. Menyimpan Presensi Pegawai & Guru (' . count($teacherAttendances) . ' baris)...');
        foreach (array_chunk($teacherAttendances, 250) as $chunkTAtt) {
            DB::table('attendances')->insert($chunkTAtt);
        }

        // 8. LMS Penugasan & CBT Ujian Contoh
        $this->command->info('11. Menyiapkan Data Penugasan & CBT Ujian...');
        $sampleKelas = $kelasCollection[12]['kelas']; // SDIT 1-A
        $sampleSubj = $subjectMap['SDIT-02'][0] ?? null;
        $sampleWali = $waliKelasList[12];

        if ($sampleSubj) {
            $tugas1 = LmsPenugasan::firstOrCreate(
                [
                    'kelas_id' => $sampleKelas->id,
                    'judul_tugas' => 'Tugas 1: Adab Menuntut Ilmu & Menghafal Al-Qur\'an',
                ],
                [
                    'id' => (string) Str::uuid(),
                    'mata_pelajaran_id' => $sampleSubj->id,
                    'guru_id' => $sampleWali->id,
                    'semester_id' => $semesterGanjil->id,
                    'tahun_ajaran_id' => $academicYear->id,
                    'deskripsi' => 'Rangkuman adab membaca Al-Qur\'an dan menghormati guru serta orang tua.',
                    'tipe_tugas' => 'Individu',
                    'nilai_maksimal' => 100,
                    'bobot_persen' => 15,
                    'tanggal_mulai' => '2026-08-10',
                    'deadline' => '2026-08-20',
                    'is_published' => true,
                ]
            );

            // CBT Ujian
            $kisi = \App\Models\LmsKisiKisi::first();
            if (!$kisi) {
                $kurikulumId = DB::table('master_kurikulum')->value('id');
                $kisi = \App\Models\LmsKisiKisi::create([
                    'id' => (string) Str::uuid(),
                    'kurikulum_id' => $kurikulumId,
                    'mata_pelajaran_id' => $sampleSubj->id,
                    'kelas_id' => $sampleKelas->id,
                    'semester_id' => $semesterGanjil->id,
                    'tahun_ajaran_id' => $academicYear->id,
                    'guru_id' => $sampleWali->id,
                    'judul_kisi' => 'Kisi-kisi Penilaian Tengah Semester (PTS) Ganjil 2026',
                    'jenis_ujian' => 'PTS',
                    'jumlah_soal' => 25,
                    'alokasi_waktu_menit' => 60,
                    'status' => true,
                ]);
            }

            LmsUjian::firstOrCreate(
                [
                    'kelas_id' => $sampleKelas->id,
                    'judul_ujian' => 'Penilaian Tengah Semester (PTS) Ganjil CBT 2026',
                ],
                [
                    'id' => (string) Str::uuid(),
                    'kisi_kisi_id' => $kisi->id,
                    'guru_id' => $sampleWali->id,
                    'semester_id' => $semesterGanjil->id,
                    'instruksi' => 'Pilihlah salah satu jawaban yang paling tepat. Waktu pengerjaan 60 menit.',
                    'waktu_mulai' => '2026-09-15 08:00:00',
                    'waktu_selesai' => '2026-09-15 09:30:00',
                    'durasi_menit' => 60,
                    'nilai_kkm' => 75,
                    'max_attempt' => 1,
                    'status' => 'aktif',
                    'tampilkan_nilai_langsung' => true,
                ]
            );
        }

        // 9. Mutaba'ah Hari Ini (9 September 2026)
        $this->command->info('12. Mengisi Mutaba\'ah Yaumiyyah Hari Ini (9 September 2026)...');
        $mutabaahTemplate = \App\Models\MutabaahTemplate::first();
        $supervisor = \App\Models\MutabaahSupervisorAssignment::first();
        if ($mutabaahTemplate && $supervisor) {
            $sampleStudents = DB::table('students')->where('kelas_id', $sampleKelas->id)->take(10)->get();
            foreach ($sampleStudents as $st) {
                MutabaahDailyHeader::firstOrCreate(
                    [
                        'student_id' => $st->id,
                        'activity_date' => '2026-09-09',
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'template_id' => $mutabaahTemplate->id,
                        'supervisor_assignment_id' => $supervisor->id,
                        'education_unit_id' => $sampleKelas->unit_pendidikan_id,
                        'kelas_id' => $sampleKelas->id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semesterGanjil->id,
                        'status' => 'draft', // Draft menunggu amalan malam & tanda tangan orang tua
                        'total_items' => 7,
                        'good_count' => 4,
                        'score' => 85.0,
                    ]
                );
            }
        }

        $this->command->info('=== SEEDER SIMULASI TERPADU 2026/2027 SELESAI BERJALAN ===');
        $this->command->info('Total Kelas: ' . count($kelasCollection) . ' Rombel');
        $this->command->info('Total Siswa Aktif: ' . ($studentCounter - 1) . ' Siswa (100% Unik)');
    }

    private function generateUniqueName(bool $isMale): string
    {
        $firstArr = $isMale ? $this->maleFirstNames : $this->femaleFirstNames;
        $attempt = 0;

        while ($attempt < 1000) {
            $f = $firstArr[array_rand($firstArr)];
            $m = $this->middleNames[array_rand($this->middleNames)];
            $l = $this->lastNames[array_rand($this->lastNames)];
            $candidate = $f . ' ' . $m . ' ' . $l;

            if (!isset($this->usedNames[$candidate])) {
                $this->usedNames[$candidate] = true;
                return $candidate;
            }
            $attempt++;
        }

        // Fallback jika tabrakan (sangat jarang dengan 70x40x40 = 112.000 kombinasi)
        $fallback = ($isMale ? 'Zaid ' : 'Aisyah ') . Str::random(5) . ' ' . $this->lastNames[array_rand($this->lastNames)];
        $this->usedNames[$fallback] = true;
        return $fallback;
    }
}
