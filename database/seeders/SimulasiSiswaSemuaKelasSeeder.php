<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SimulasiSiswaSemuaKelasSeeder extends Seeder
{
    private array $usedNames = [];
    private array $usedNis = [];
    private array $usedNisn = [];
    private array $usedEmails = [];

    private array $maleFirstNames = [
        'Muhammad', 'Ahmad', 'Abdullah', 'Zaid', 'Umar', 'Ali', 'Hamzah', 'Hasan', 'Husain', 'Bilal',
        'Salman', 'Khalid', 'Thariq', 'Usamah', 'Fatih', 'Rayyan', 'Farhan', 'Fadhil', 'Naufal', 'Rasyid',
        'Syakir', 'Munir', 'Ihsan', 'Fauzan', 'Hafizh', 'Luqman', 'Yusuf', 'Ibrahim', 'Ismail', 'Idris',
        'Zakariya', 'Yahya', 'Harun', 'Sulaiman', 'Dawud', 'Syamil', 'Wildan', 'Azzam', 'Rifqi', 'Hanif',
        'Akram', 'Karim', 'Basil', 'Dzaki', 'Ghazi', 'Hilal', 'Irfan', 'Jafar', 'Kamil', 'Labib',
        'Miqdad', 'Nabil', 'Qasim', 'Rafi', 'Salim', 'Tariq', 'Ubaid', 'Wafi', 'Yasin', 'Ziyad',
        'Arfan', 'Baqir', 'Chairil', 'Danish', 'Ehsan', 'Faris', 'Gibran', 'Habibi', 'Ilham', 'Jundi',
        'Khaidir', 'Latif', 'Musthafa', 'Najib', 'Raihan', 'Syafiq', 'Thalhah', 'Waqas', 'Yasir', 'Zubair'
    ];

    private array $femaleFirstNames = [
        'Aisha', 'Fatimah', 'Maryam', 'Khadijah', 'Zainab', 'Ruqayyah', 'Hafshah', 'Juwairiyah', 'Maimunah', 'Safiyyah',
        'Asma', 'Sarah', 'Hajar', 'Zahra', 'Salma', 'Naila', 'Yasmin', 'Hana', 'Dina', 'Lubna',
        'Marwa', 'Shafa', 'Farah', 'Rania', 'Syifa', 'Layla', 'Nada', 'Alya', 'Sabrina', 'Husna',
        'Azizah', 'Inayah', 'Shakira', 'Khalila', 'Humaira', 'Samira', 'Thahirah', 'Zakiyah', 'Aminah', 'Halimah',
        'Barakah', 'Nusaibah', 'Afifah', 'Basimah', 'Dzakiyyah', 'Faiqah', 'Ghina', 'Habibah', 'Ilma', 'Jamilah',
        'Kamilah', 'Latifah', 'Mawaddah', 'Najwa', 'Qanita', 'Raudhah', 'Salsabila', 'Tasnim', 'Ufairah', 'Wardah',
        'Adiba', 'Atikah', 'Balqis', 'Dhafitha', 'Fauziyyah', 'Hafizhah', 'Iffah', 'Kamila', 'Munirah', 'Zhafira'
    ];

    private array $middleNames = [
        'Hafizh', 'Rasyid', 'Syakir', 'Munir', 'Naufal', 'Fadhil', 'Ihsan', 'Hakim', 'Maulana', 'Farid',
        'Fauzi', 'Habibi', 'Izzuddin', 'Jauhari', 'Kamal', 'Luthfi', 'Mubarak', 'Nasir', 'Qudrat', 'Rizal',
        'Shiddiq', 'Thahir', 'Ushaimi', 'Wahid', 'Zuhdi', 'Aqil', 'Burhan', 'Dhiya', 'Faiz', 'Ghaisan',
        'Anindya', 'Az-Zahra', 'Khairunnisa', 'Nabilah', 'Rahadatul', 'Syakirah', 'Tsabita', 'Wafiqah', 'Yusra', 'Zulaikha',
        'Nuraini', 'Fatimah', 'Auliya', 'Hidayah', 'Mufidah', 'Sakinah', 'Rahimah', 'Karimah', 'Sholihat', 'Kamilah'
    ];

    private array $lastNames = [
        'Al-Ghifari', 'Al-Fatih', 'Al-Banjari', 'Al-Habsyi', 'Al-Attas', 'Al-Munawwar', 'Firdaus', 'Ramadhan', 'Syahputra', 'Permana',
        'Setiawan', 'Kurniawan', 'Pratama', 'Nugraha', 'Santoso', 'Wijaya', 'Kusuma', 'Siregar', 'Nasution', 'Tanjung',
        'Lubis', 'Harahap', 'Daulay', 'Chaniago', 'Koto', 'Piliang', 'Guci', 'Sikumbang', 'Caniago', 'Jambak',
        'Mandailing', 'Melayu', 'Putra', 'Hidayat', 'Rahman', 'Suryana', 'Saputra', 'Budiman', 'Iskandar', 'Firmansyah',
        'Marpaung', 'Batubara', 'Rangkuti', 'Simbolon', 'Pasaribu', 'Manurung', 'Pohan', 'Hasibuan', 'Pulungan', 'Sinaga'
    ];

    private array $padangAddresses = [
        'Jl. Khatib Sulaiman No. ',
        'Jl. Belanti Indah No. ',
        'Jl. Raden Saleh No. ',
        'Jl. Gajah Mada No. ',
        'Jl. Hamka No. ',
        'Jl. S. Parman No. ',
        'Jl. Dr. Sutomo No. ',
        'Jl. Sawahan No. ',
        'Jl. Perintis Kemerdekaan No. ',
        'Jl. Kuranji No. ',
        'Jl. Bypass KM 7 No. ',
        'Jl. Lubuk Begalung No. ',
        'Jl. Payakumbuh Indah No. ',
        'Jl. Harapan Mulia No. ',
        'Jl. Al-Furqan No. ',
        'Jl. Darul Arqam No. '
    ];

    private array $occupations = [
        'Wiraswasta', 'PNS / Guru', 'Karyawan Swasta', 'Dosen', 'Pedagang',
        'Dokter', 'Arsitek / Kontraktor', 'Karyawan BUMN', 'Aparatur Sipil Negara',
        'Pengusaha Konveksi', 'Advokat / Notaris', 'Tenaga Medis'
    ];

    public function run(): void
    {
        $this->command->info('=== MEMULAI SIMULASI SISWA SEMUA KELAS & ROMBEL (ZERO DATA KOSONG) ===');

        DB::disableQueryLog();

        // 1. Ambil Tahun Ajaran Aktif 2026/2027
        $academicYear = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::where('name', '2026/2027')->first();

        if (!$academicYear) {
            $this->command->error('Tahun Ajaran 2026/2027 tidak ditemukan!');
            return;
        }

        $semesterGanjil = Semester::where('academic_year_id', $academicYear->id)
            ->where(function ($q) {
                $q->where('sequence', 1)->orWhere('name', 'like', '%Ganjil%');
            })->first()
            ?? Semester::where('is_active', true)->first();

        // Muat data existing agar tidak terjadi collision
        $this->command->info('Memuat data identitas siswa unik yang sudah ada di database...');
        $existingNis = DB::table('students')->pluck('nis')->filter()->all();
        $this->usedNis = array_fill_keys($existingNis, true);

        $existingNisn = DB::table('students')->whereNotNull('nisn')->pluck('nisn')->all();
        $this->usedNisn = array_fill_keys($existingNisn, true);

        $existingEmails = DB::table('users')->pluck('email')->filter()->all();
        $this->usedEmails = array_fill_keys($existingEmails, true);

        $hashedPassword = Hash::make('password');
        $defaultPasswordHash = $hashedPassword;

        $studentRoleId = DB::table('roles')->where('name', 'Siswa')->value('id');
        $parentRoleId = DB::table('roles')->where('name', 'Orang Tua')->value('id');

        // 2. Ambil Semua Kelas (181 Kelas)
        $allClasses = Kelas::with('unitPendidikan')->orderBy('nama_kelas')->get();
        $this->command->info(sprintf('Total Kelas terdaftar: %d kelas.', $allClasses->count()));

        $sequenceCounter = DB::table('students')->count() + 1000;
        $totalNewStudents = 0;
        $classesPopulated = 0;

        foreach ($allClasses as $kelas) {
            $unit = $kelas->unitPendidikan;
            $currentCount = DB::table('students')
                ->where(function ($q) use ($kelas) {
                    $q->where('kelas_id', $kelas->id)->orWhere('class_id', $kelas->id);
                })
                ->whereNull('deleted_at')
                ->count();

            // Target kapasitas per kelas: tepat 30 siswa (atau 50 untuk asrama/boarding)
            $targetClassSize = in_array(strtoupper($kelas->tingkat ?? ''), ['BOARDING', 'MAHAD', 'ASRAMA']) ? 50 : 30;

            if ($currentCount >= $targetClassSize) {
                continue; // Sudah terisi penuh
            }

            $needed = $targetClassSize - $currentCount;
            $classesPopulated++;

            $this->command->info(sprintf(
                'Mengisi %d siswa baru ke Kelas [%s] (%s) - Unit: %s...',
                $needed,
                $kelas->nama_kelas,
                $kelas->kode_kelas,
                $unit->name ?? 'Tanpa Unit'
            ));

            // Pastikan entri di tabel legacy `classes` sinkron (jika belum ada)
            if (!DB::table('classes')->where('id', $kelas->id)->exists()) {
                $baseName = mb_substr($kelas->nama_kelas, 0, 40);
                $candidateName = $baseName;
                $suffix = 1;
                while (DB::table('classes')->where('academic_year_id', $academicYear->id)->where('semester_id', $semesterGanjil ? $semesterGanjil->id : null)->where('name', $candidateName)->exists()) {
                    $candidateName = mb_substr(sprintf('%s · %s', $baseName, $kelas->kode_kelas), 0, 50);
                    if (DB::table('classes')->where('academic_year_id', $academicYear->id)->where('semester_id', $semesterGanjil ? $semesterGanjil->id : null)->where('name', $candidateName)->exists()) {
                        $candidateName = mb_substr(sprintf('%s (%d)', $candidateName, ++$suffix), 0, 50);
                    }
                }
                DB::table('classes')->insert([
                    'id' => $kelas->id,
                    'academic_year_id' => $academicYear->id,
                    'semester_id' => $semesterGanjil ? $semesterGanjil->id : null,
                    'name' => $candidateName,
                    'level' => mb_substr($kelas->tingkat ?: ($kelas->jenjang ?: 'SD'), 0, 20),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Tentukan gender berdasarkan unit / nama kelas
            $unitNameUpper = strtoupper($unit->name ?? '');
            $classNameUpper = strtoupper($kelas->nama_kelas ?? '');

            $isAllFemale = str_contains($unitNameUpper, 'PUTRI') || str_contains($unitNameUpper, 'AKHWAT') || str_contains($classNameUpper, 'AKHWAT');
            $isAllMale = str_contains($unitNameUpper, 'PUTRA') || str_contains($unitNameUpper, 'IKHWAN') || str_contains($classNameUpper, 'IKHWAN');

            // Hitung usia / tahun lahir berdasarkan tingkat
            $birthYear = $this->calculateBirthYear($kelas->jenjang, $kelas->tingkat);
            $tahunMasuk = $this->calculateTahunMasuk($kelas->tingkat);

            $usersToInsert = [];
            $parentsToInsert = [];
            $studentsToInsert = [];
            $studentParentsToInsert = [];
            $rolesToInsert = [];

            for ($i = 1; $i <= $needed; $i++) {
                $sequenceCounter++;

                // Gender
                $isMale = $isAllMale ? true : ($isAllFemale ? false : ($i % 2 === 1));

                // Nama Unik
                $studentName = $this->generateUniqueName($isMale);
                $parentFather = $this->generateUniqueName(true);
                $parentMother = $this->generateUniqueName(false);

                // NIS & NISN Unik
                $nis = $this->generateUniqueNis($sequenceCounter);
                $nisn = $this->generateUniqueNisn($sequenceCounter);

                // Email Unik
                $studentEmail = $this->generateUniqueEmail('siswa.' . $sequenceCounter . '@siswa.dareliman.sch.id');
                $parentEmail = $this->generateUniqueEmail('ortu.' . $sequenceCounter . '@family.dareliman.sch.id');

                $studentUserId = (string) Str::uuid();
                $parentUserId = (string) Str::uuid();
                $parentId = (string) Str::uuid();
                $studentId = (string) Str::uuid();

                // User Akun Siswa
                $usersToInsert[] = [
                    'id' => $studentUserId,
                    'name' => $studentName,
                    'email' => $studentEmail,
                    'password' => $defaultPasswordHash,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // User Akun Orang Tua
                $usersToInsert[] = [
                    'id' => $parentUserId,
                    'name' => $parentFather,
                    'email' => $parentEmail,
                    'password' => $defaultPasswordHash,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($studentRoleId) {
                    $rolesToInsert[] = [
                        'role_id' => $studentRoleId,
                        'model_type' => 'App\Models\User',
                        'model_id' => $studentUserId,
                    ];
                }
                if ($parentRoleId) {
                    $rolesToInsert[] = [
                        'role_id' => $parentRoleId,
                        'model_type' => 'App\Models\User',
                        'model_id' => $parentUserId,
                    ];
                }

                // Data Orang Tua
                $addr = $this->padangAddresses[($sequenceCounter + $i) % count($this->padangAddresses)] . ($i * 3 + 2) . ', Kota Padang';
                $job = $this->occupations[($sequenceCounter + $i) % count($this->occupations)];
                $nikPadang = '1371' . str_pad((string)(($sequenceCounter * 7) % 899999999999 + 100000000000), 12, '0', STR_PAD_LEFT);

                $parentsToInsert[] = [
                    'id' => $parentId,
                    'user_id' => $parentUserId,
                    'full_name' => $parentFather,
                    'phone' => '08' . (1200000000 + ($sequenceCounter % 899999999)),
                    'email' => $parentEmail,
                    'occupation' => $job,
                    'address' => $addr,
                    'nik' => $nikPadang,
                    'metadata' => json_encode([
                        'nama_ibu' => $parentMother,
                        'pekerjaan_ibu' => 'Ibu Rumah Tangga',
                        'kontak_darurat' => '08' . (1300000000 + ($sequenceCounter % 899999999))
                    ]),
                    'created_at' => '2026-07-01 08:00:00',
                    'updated_at' => '2026-07-01 08:00:00',
                ];

                // Tanggal Lahir (Bulan & Hari acak realistis)
                $bMonth = str_pad((string)(($i % 12) + 1), 2, '0', STR_PAD_LEFT);
                $bDay = str_pad((string)(($i % 27) + 1), 2, '0', STR_PAD_LEFT);
                $dob = sprintf('%04d-%s-%s', $birthYear, $bMonth, $bDay);

                // Data Siswa
                $studentsToInsert[] = [
                    'id' => $studentId,
                    'user_id' => $studentUserId,
                    'parent_id' => $parentId,
                    'unit_id' => $kelas->unit_pendidikan_id,
                    'kelas_id' => $kelas->id,
                    'class_id' => $kelas->id,
                    'nis' => $nis,
                    'nisn' => $nisn,
                    'full_name' => $studentName,
                    'gender' => $isMale ? 'male' : 'female',
                    'birth_date' => $dob,
                    'birth_place' => ($i % 3 === 0) ? 'Padang' : (($i % 3 === 1) ? 'Bukittinggi' : 'Payakumbuh'),
                    'address' => $addr,
                    'tahun_masuk' => $tahunMasuk,
                    'is_active' => true,
                    'created_at' => '2026-07-01 08:00:00',
                    'updated_at' => '2026-07-01 08:00:00',
                ];

                // Pivot Relasi StudentParent
                $studentParentsToInsert[] = [
                    'id' => (string) Str::uuid(),
                    'student_id' => $studentId,
                    'parent_id' => $parentId,
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'created_at' => '2026-07-01 08:00:00',
                    'updated_at' => '2026-07-01 08:00:00',
                ];

                $totalNewStudents++;
            }

            // Insert Batch per Kelas
            DB::table('users')->insert($usersToInsert);
            if (!empty($rolesToInsert)) {
                DB::table('model_has_roles')->insertOrIgnore($rolesToInsert);
            }
            DB::table('parents')->insert($parentsToInsert);
            DB::table('students')->insert($studentsToInsert);
            DB::table('student_parents')->insert($studentParentsToInsert);
        }

        $this->command->info('====================================================');
        $this->command->info(sprintf(
            'SELESAI: Berhasil mengisi %d siswa baru ke %d kelas!',
            $totalNewStudents,
            $classesPopulated
        ));

        // 3. Verifikasi Akhir
        $remainingEmpty = 0;
        foreach ($allClasses as $c) {
            $cnt = DB::table('students')
                ->where(function ($q) use ($c) {
                    $q->where('kelas_id', $c->id)->orWhere('class_id', $c->id);
                })
                ->whereNull('deleted_at')
                ->count();
            if ($cnt === 0) {
                $remainingEmpty++;
            }
        }

        $totalSiswaSistem = DB::table('students')->whereNull('deleted_at')->count();
        $this->command->info(sprintf('Total Siswa Aktif Sekarang: %d Siswa.', $totalSiswaSistem));
        $this->command->info(sprintf('Kelas dengan 0 siswa: %d Kelas.', $remainingEmpty));
        $this->command->info('====================================================');
    }

    private function generateUniqueName(bool $isMale): string
    {
        $firstArr = $isMale ? $this->maleFirstNames : $this->femaleFirstNames;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $first = $firstArr[array_rand($firstArr)];
            $mid = $this->middleNames[array_rand($this->middleNames)];
            $last = $this->lastNames[array_rand($this->lastNames)];

            $fullName = $first . ' ' . $mid . ' ' . $last;
            if (!isset($this->usedNames[$fullName])) {
                $this->usedNames[$fullName] = true;
                return $fullName;
            }
        }
        $fallback = ($isMale ? 'Muhammad' : 'Aisyah') . ' ' . Str::random(5);
        $this->usedNames[$fallback] = true;
        return $fallback;
    }

    private function generateUniqueNis(int $seq): string
    {
        for ($i = 0; $i < 100; $i++) {
            $nis = '26' . str_pad((string)($seq + $i), 6, '0', STR_PAD_LEFT);
            if (!isset($this->usedNis[$nis])) {
                $this->usedNis[$nis] = true;
                return $nis;
            }
        }
        $fallback = '26' . mt_rand(100000, 999999);
        $this->usedNis[$fallback] = true;
        return $fallback;
    }

    private function generateUniqueNisn(int $seq): string
    {
        for ($i = 0; $i < 100; $i++) {
            $nisn = '009' . str_pad((string)($seq + $i), 7, '0', STR_PAD_LEFT);
            if (!isset($this->usedNisn[$nisn])) {
                $this->usedNisn[$nisn] = true;
                return $nisn;
            }
        }
        $fallback = '009' . mt_rand(1000000, 9999999);
        $this->usedNisn[$fallback] = true;
        return $fallback;
    }

    private function generateUniqueEmail(string $candidate): string
    {
        if (!isset($this->usedEmails[$candidate])) {
            $this->usedEmails[$candidate] = true;
            return $candidate;
        }
        $unique = 'user.' . Str::random(8) . '@dareliman.sch.id';
        $this->usedEmails[$unique] = true;
        return $unique;
    }

    private function calculateBirthYear(?string $jenjang, ?string $tingkat): int
    {
        $j = strtoupper($jenjang ?? '');
        $t = strtoupper($tingkat ?? '');

        if (str_contains($j, 'TK') || str_contains($t, 'TK') || str_contains($t, 'PAUD') || str_contains($j, 'PAUD')) {
            return str_contains($t, 'B') ? 2020 : 2021;
        }

        if (str_contains($t, '1') || str_contains($t, 'I\'DAD') || str_contains($t, 'IDAD')) {
            return 2019;
        }
        if (str_contains($t, '2')) return 2018;
        if (str_contains($t, '3')) return 2017;
        if (str_contains($t, '4')) return 2016;
        if (str_contains($t, '5')) return 2015;
        if (str_contains($t, '6')) return 2014;

        if (str_contains($t, '7')) return 2013;
        if (str_contains($t, '8')) return 2012;
        if (str_contains($t, '9')) return 2011;

        if (str_contains($t, '10') || str_contains($t, 'X')) return 2010;
        if (str_contains($t, '11') || str_contains($t, 'XI')) return 2009;
        if (str_contains($t, '12') || str_contains($t, 'XII')) return 2008;

        if (str_contains($j, 'SMP')) return 2013;
        if (str_contains($j, 'SMA') || str_contains($j, 'MA')) return 2010;
        if (str_contains($t, 'BOARDING') || str_contains($t, 'MAHAD') || str_contains($t, 'ASRAMA')) return 2011;

        return 2017;
    }

    private function calculateTahunMasuk(?string $tingkat): string
    {
        $t = strtoupper($tingkat ?? '');
        if (str_contains($t, '2') || str_contains($t, '8') || str_contains($t, '11') || str_contains($t, 'XI')) return '2025';
        if (str_contains($t, '3') || str_contains($t, '9') || str_contains($t, '12') || str_contains($t, 'XII')) return '2024';
        if (str_contains($t, '4')) return '2023';
        if (str_contains($t, '5')) return '2022';
        if (str_contains($t, '6')) return '2021';
        return '2026';
    }
}
