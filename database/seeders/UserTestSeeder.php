<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UserTestSeeder
 *
 * Seeder komprehensif untuk pengujian & simulasi multi-role (Web Dashboard & Mobile App):
 * 1. Super Admin & Admin Sistem (Akses global + izin role switching/impersonate).
 * 2. Pengurus Yayasan & Divisi Pendidikan (Akses monitoring eksekutif & kurikulum).
 * 3. Kepala Sekolah untuk MASING-MASING Unit Pendidikan.
 * 4. Tata Usaha (TU) untuk MASING-MASING Unit Pendidikan.
 * 5. Operator untuk MASING-MASING Unit Pendidikan.
 * 6. Musrif Asrama untuk Unit Boarding (Ponpes Putra).
 * 7. 1 Guru Mapel untuk Unit Boarding (Ponpes).
 * 8. 1 Guru Mapel untuk Unit Fullday (SDIT).
 * 9. 1 Akun Orang Tua (Wali Murid) yang memiliki 4 ANAK:
 *    - 2 Anak di Unit Fullday (pada 2 unit sekolah berbeda).
 *    - 2 Anak di Unit Boarding/Pesantren (pada 2 unit pesantren berbeda).
 *
 * Karakteristik Teknis:
 * - Idempoten: aman dieksekusi berulang tanpa membuat data duplikat.
 * - Production Safety Guard: ditolak keras jika dijalankan di environment 'production'.
 * - Kompatibel 100% dengan mekanisme Role Impersonation (AuthController::impersonate).
 * - Menampilkan tabel kredensial rapi di terminal setelah seeding selesai.
 *
 * Cara menjalankan:
 * php artisan db:seed --class=UserTestSeeder
 */
class UserTestSeeder extends Seeder
{
    public const TEST_METADATA_FLAG = 'is_test_account';

    public function run(): void
    {
        // 1. Blokade keamanan produksi
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'KEAMANAN: UserTestSeeder DITOLAK keras pada environment production. Seeder ini hanya untuk lingkungan lokal/staging.'
            );
        }

        $this->command?->info('=== MEMULAI SEEDER USER TEST TERPADU (MULTI-ROLE & MULTI-UNIT) ===');

        // 2. Pastikan seluruh Role Spatie esensial telah terdaftar
        $this->ensureEssentialRoles();

        $summaryRows = [];

        // 3. SEED: Super Admin & Admin Sistem
        $this->command?->info('-> Memproses Akun Super Admin & Admin Sistem...');
        $this->seedSuperAdminAndAdmin($summaryRows);

        // 4. SEED: Pengurus Yayasan & Divisi Pendidikan
        $this->command?->info('-> Memproses Akun Yayasan & Divisi Pendidikan...');
        $this->seedYayasanAndDivisiPendidikan($summaryRows);

        // 5. SEED: Kepala Sekolah, TU, dan Operator untuk SELURUH UNIT PENDIDIKAN
        $this->command?->info('-> Memproses Kepala Sekolah, TU, & Operator untuk masing-masing unit...');
        $this->seedUnitOfficials($summaryRows);

        // 6. SEED: Musrif Boarding, Guru Mapel Boarding, dan Guru Fullday
        $this->command?->info('-> Memproses Musrif & Guru (Boarding vs Fullday)...');
        $this->seedMusrifAndTeachers($summaryRows);

        // 7. SEED: 1 Orang Tua dengan 4 Anak (2 Fullday beda unit + 2 Boarding beda unit)
        $this->command?->info('-> Memproses Akun Orang Tua dengan 4 Anak Lintas Ekosistem...');
        $this->seedParentWithFourChildren($summaryRows);

        // 8. Tampilkan Ringkasan Kredensial di Terminal
        if ($this->command) {
            $this->command->newLine();
            $this->command->info('=== DAFTAR KREDENSIAL AKUN TEST YANG BERHASIL DI-GENERATE ===');
            $this->command->table(
                ['Role / Peran', 'Email Login', 'Password Default', 'Scope Data', 'Unit / Catatan'],
                $summaryRows
            );
            $this->command->info('Total akun siap digunakan untuk pengujian & switching impersonasi.');
        }
    }

    /**
     * Memastikan role-role utama Spatie telah terdaftar di database.
     */
    private function ensureEssentialRoles(): void
    {
        $essentialRoles = [
            'Super Admin',
            'Admin',
            'Yayasan',
            'Ketua Yayasan',
            'Pengurus Yayasan',
            'Divisi Pendidikan',
            'Kepala Bidang Pendidikan',
            'Kepala Sekolah',
            'Tata Usaha',
            'Operator',
            'Musyrif',
            'Musyrifah',
            'Guru',
            'Guru Mata Pelajaran',
            'Wali Kelas',
            'Orang Tua',
            'Siswa',
            'Alumni',
        ];

        foreach ($essentialRoles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    /**
     * Seeder untuk Super Admin dan Admin Sistem.
     */
    private function seedSuperAdminAndAdmin(array &$summaryRows): void
    {
        $accounts = [
            [
                'role' => 'Super Admin',
                'name' => 'Super Admin Test',
                'email' => 'superadmin@dareliman.sch.id',
                'password' => 'SuperAdmin@2026!',
                'phone' => '081299990001',
                'scope' => 'global',
                'is_permanent_admin' => true,
            ],
            [
                'role' => 'Admin',
                'name' => 'Admin Sistem Test',
                'email' => 'admin.test@dareliman.sch.id',
                'password' => 'AdminTest@2026!',
                'phone' => '081299990002',
                'scope' => 'global',
                'is_permanent_admin' => false,
            ],
        ];

        foreach ($accounts as $acc) {
            $user = $this->createOrUpdateUser(
                name: $acc['name'],
                email: $acc['email'],
                password: $acc['password'],
                primaryRole: $acc['role'],
                phone: $acc['phone'],
                scope: $acc['scope'],
                extraRoles: $acc['is_permanent_admin'] ? ['Admin'] : []
            );

            $summaryRows[] = [
                $acc['role'],
                $acc['email'],
                $acc['password'],
                $acc['scope'],
                'Global (Semua Unit)',
            ];
        }
    }

    /**
     * Seeder untuk Pengurus Yayasan dan Divisi Pendidikan.
     */
    private function seedYayasanAndDivisiPendidikan(array &$summaryRows): void
    {
        // 1. Pengurus Yayasan
        $userYayasan = $this->createOrUpdateUser(
            name: 'Pengurus Yayasan Test',
            email: 'pengurus.yayasan.test@dareliman.sch.id',
            password: 'YayasanTest@2026!',
            primaryRole: 'Pengurus Yayasan',
            phone: '081299990004',
            scope: 'cross_unit_read',
            extraRoles: ['Yayasan', 'Ketua Yayasan']
        );

        $this->createOrUpdateEmployee(
            user: $userYayasan,
            niy: 'TEST-NIY-YAYASAN',
            unitId: null,
            roleName: 'Pengurus Yayasan'
        );

        $summaryRows[] = [
            'Pengurus Yayasan',
            $userYayasan->email,
            'YayasanTest@2026!',
            'cross_unit_read',
            'Pimpinan Yayasan (Cross-Unit)',
        ];

        // 2. Divisi Pendidikan
        $userDivPendidikan = $this->createOrUpdateUser(
            name: 'Divisi Pendidikan Test',
            email: 'divisi.pendidikan.test@dareliman.sch.id',
            password: 'YayasanTest@2026!',
            primaryRole: 'Divisi Pendidikan',
            phone: '081299990006',
            scope: 'cross_unit_read',
            extraRoles: ['Kepala Bidang Pendidikan']
        );

        $this->createOrUpdateEmployee(
            user: $userDivPendidikan,
            niy: 'TEST-NIY-DIV-PENDIDIKAN',
            unitId: null,
            roleName: 'Divisi Pendidikan'
        );

        $summaryRows[] = [
            'Divisi Pendidikan',
            $userDivPendidikan->email,
            'YayasanTest@2026!',
            'cross_unit_read',
            'Manajemen Akademik (Cross-Unit)',
        ];
    }

    /**
     * Seeder untuk Kepala Sekolah, Tata Usaha, dan Operator pada masing-masing unit pendidikan.
     */
    private function seedUnitOfficials(array &$summaryRows): void
    {
        $units = EducationUnit::where('is_active', true)
            ->orWhereNull('is_active')
            ->orderBy('code')
            ->get();

        if ($units->isEmpty()) {
            $this->command?->warn('Perhatian: Tidak ada unit pendidikan aktif ditemukan. Silakan jalankan DataDummyUnitPendidikanSeeder terlebih dahulu.');
            return;
        }

        foreach ($units as $index => $unit) {
            $slug = Str::lower(str_replace(['-', ' ', "'", '.'], '', $unit->code));
            $unitName = $unit->name;
            $unitNumber = str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);

            $officialConfigs = [
                [
                    'role' => 'Kepala Sekolah',
                    'emailPrefix' => 'kepsek',
                    'phonePrefix' => '081288',
                    'niyPrefix' => 'KEPSEK',
                ],
                [
                    'role' => 'Tata Usaha',
                    'emailPrefix' => 'tu',
                    'phonePrefix' => '081287',
                    'niyPrefix' => 'TU',
                ],
                [
                    'role' => 'Operator',
                    'emailPrefix' => 'operator',
                    'phonePrefix' => '081286',
                    'niyPrefix' => 'OP',
                ],
            ];

            foreach ($officialConfigs as $cfg) {
                $email = "{$cfg['emailPrefix']}.{$slug}@dareliman.sch.id";
                $name = "{$cfg['role']} {$unitName}";
                // Batasi maksimal 14 digit agar selalu valid di kolom phone & format nomor telepon.
                $phone = substr($cfg['phonePrefix'] . $unitNumber, 0, 14);

                $officialUser = $this->createOrUpdateUser(
                    name: $name,
                    email: $email,
                    password: 'SekolahTest@2026!',
                    primaryRole: $cfg['role'],
                    phone: $phone,
                    scope: 'education_unit',
                    unitId: $unit->id,
                    unitCode: $unit->code,
                    unitName: $unitName
                );

                $this->createOrUpdateEmployee(
                    user: $officialUser,
                    niy: "TEST-NIY-{$cfg['niyPrefix']}-{$unit->code}",
                    unitId: $unit->id,
                    roleName: $cfg['role']
                );

                $summaryRows[] = [
                    $cfg['role'],
                    $email,
                    'SekolahTest@2026!',
                    'education_unit',
                    $unit->code . ' (' . Str::limit($unitName, 22) . ')',
                ];
            }
        }

        // Akun generik fallback (untuk kompatibilitas testing lama)
        $firstUnit = $units->first();
        if ($firstUnit) {
            $genericAccounts = [
                ['role' => 'Kepala Sekolah', 'email' => 'kepsek.test@dareliman.sch.id', 'prefix' => 'KEPSEK-TEST'],
                ['role' => 'Tata Usaha', 'email' => 'tu.test@dareliman.sch.id', 'prefix' => 'TU-TEST'],
                ['role' => 'Operator', 'email' => 'operator.test@dareliman.sch.id', 'prefix' => 'OP-TEST'],
            ];

            foreach ($genericAccounts as $gen) {
                $u = $this->createOrUpdateUser(
                    name: $gen['role'] . ' Test',
                    email: $gen['email'],
                    password: 'SekolahTest@2026!',
                    primaryRole: $gen['role'],
                    phone: '081299990010',
                    scope: 'education_unit',
                    unitId: $firstUnit->id,
                    unitCode: $firstUnit->code,
                    unitName: $firstUnit->name
                );
                $this->createOrUpdateEmployee($u, 'TEST-NIY-' . $gen['prefix'], $firstUnit->id, $gen['role']);
            }
        }
    }

    /**
     * Seeder untuk Musrif Asrama Boarding, 1 Guru Mapel Boarding, dan 1 Guru Mapel Fullday.
     */
    private function seedMusrifAndTeachers(array &$summaryRows): void
    {
        // 1. Ambil Unit Boarding & Fullday
        $boardingUnit = EducationUnit::where('code', 'PONPES-PA')->first()
            ?? EducationUnit::where('name', 'like', '%Pondok%')->orWhere('name', 'like', '%Ponpes%')->first()
            ?? EducationUnit::first();

        $fulldayUnit = EducationUnit::where('code', 'SDIT-01')->first()
            ?? EducationUnit::where('code', 'SDIT-02')->first()
            ?? EducationUnit::where('name', 'like', '%SD%')->first()
            ?? EducationUnit::first();

        // --- A. Musrif Asrama (Unit Boarding) ---
        $musrifUser = $this->createOrUpdateUser(
            name: 'Musrif Asrama Test (Ustadz Salman)',
            email: 'musyrif.test@dareliman.sch.id',
            password: 'GuruTest@2026!',
            primaryRole: 'Musyrif',
            phone: '081299990016',
            scope: 'own_assignments',
            unitId: $boardingUnit?->id,
            unitCode: $boardingUnit?->code,
            unitName: $boardingUnit?->name,
            extraMeta: [
                'asrama' => 'Gedung Asrama Al-Farabi',
                'kamar' => 'Kamar 01',
                'program_type' => 'boarding',
            ]
        );

        $this->createOrUpdateEmployee(
            user: $musrifUser,
            niy: 'TEST-NIY-MUSYRIF-PA',
            unitId: $boardingUnit?->id,
            roleName: 'Musyrif'
        );

        $summaryRows[] = [
            'Musrif Boarding',
            $musrifUser->email,
            'GuruTest@2026!',
            'own_assignments',
            ($boardingUnit?->code ?? 'PONPES') . ' - Asrama Al-Farabi',
        ];

        // --- B. Guru Mapel Boarding (Ponpes Putra) ---
        $guruBoardingUser = $this->createOrUpdateUser(
            name: 'Guru Mapel Boarding Test (Ustadz Zulkifli, Lc.)',
            email: 'guru.boarding.test@dareliman.sch.id',
            password: 'GuruTest@2026!',
            primaryRole: 'Guru',
            phone: '081299990017',
            scope: 'own_assignments',
            unitId: $boardingUnit?->id,
            unitCode: $boardingUnit?->code,
            unitName: $boardingUnit?->name,
            extraRoles: ['Guru Mata Pelajaran'],
            extraMeta: [
                'program_type' => 'boarding',
                'mapel_ampulan' => ['Bahasa Arab', 'Hadits Arbain', 'Fiqih Ibadah'],
            ]
        );

        $empBoarding = $this->createOrUpdateEmployee(
            user: $guruBoardingUser,
            niy: 'TEST-NIY-GURU-BOARDING',
            unitId: $boardingUnit?->id,
            roleName: 'Guru Mata Pelajaran'
        );

        $this->createOrUpdateTeacher(
            user: $guruBoardingUser,
            employee: $empBoarding,
            employeeNumber: 'TEST-NIY-GURU-BOARDING'
        );

        $summaryRows[] = [
            'Guru Mapel Boarding',
            $guruBoardingUser->email,
            'GuruTest@2026!',
            'own_assignments',
            ($boardingUnit?->code ?? 'PONPES') . ' - Mapel B. Arab & Fiqih',
        ];

        // --- C. Guru Mapel Fullday (SDIT) ---
        $guruFulldayUser = $this->createOrUpdateUser(
            name: 'Guru Fullday Test (Ustazah Khadijah, S.Pd.)',
            email: 'guru.fullday.test@dareliman.sch.id',
            password: 'GuruTest@2026!',
            primaryRole: 'Guru',
            phone: '081299990018',
            scope: 'own_assignments',
            unitId: $fulldayUnit?->id,
            unitCode: $fulldayUnit?->code,
            unitName: $fulldayUnit?->name,
            extraRoles: ['Guru Mata Pelajaran'],
            extraMeta: [
                'program_type' => 'fullday',
                'mapel_ampulan' => ['Matematika', 'Tematik Terpadu', 'IPA'],
            ]
        );

        $empFullday = $this->createOrUpdateEmployee(
            user: $guruFulldayUser,
            niy: 'TEST-NIY-GURU-FULLDAY',
            unitId: $fulldayUnit?->id,
            roleName: 'Guru Mata Pelajaran',
            gender: 'P'
        );

        $this->createOrUpdateTeacher(
            user: $guruFulldayUser,
            employee: $empFullday,
            employeeNumber: 'TEST-NIY-GURU-FULLDAY'
        );

        $summaryRows[] = [
            'Guru Mapel Fullday',
            $guruFulldayUser->email,
            'GuruTest@2026!',
            'own_assignments',
            ($fulldayUnit?->code ?? 'SDIT') . ' - Mapel Matematika & Tematik',
        ];

        // Akun Guru generik legacy/fallback (guru.test@dareliman.sch.id)
        $legacyGuru = $this->createOrUpdateUser(
            name: 'Guru Pengajar Test',
            email: 'guru.test@dareliman.sch.id',
            password: 'GuruTest@2026!',
            primaryRole: 'Guru',
            phone: '081299990012',
            scope: 'own_assignments',
            unitId: $fulldayUnit?->id,
            unitCode: $fulldayUnit?->code,
            unitName: $fulldayUnit?->name,
            extraRoles: ['Guru Mata Pelajaran']
        );
        $empLegacy = $this->createOrUpdateEmployee($legacyGuru, 'TEST-NIY-GURU-LEGACY', $fulldayUnit?->id, 'Guru');
        $this->createOrUpdateTeacher($legacyGuru, $empLegacy, 'TEST-NIY-GURU-LEGACY');
    }

    /**
     * Seeder untuk 1 Akun Orang Tua (Wali Murid) yang memiliki 4 ANAK:
     * - 2 Anak di Unit Fullday (pada unit sekolah berbeda)
     * - 2 Anak di Unit Boarding/Pesantren (pada unit pesantren berbeda)
     */
    private function seedParentWithFourChildren(array &$summaryRows): void
    {
        // 1. Siapkan Akun User & Profil ParentModel untuk Orang Tua
        $parentUser = $this->createOrUpdateUser(
            name: 'Bapak Rahmat Hidayat (Orang Tua Test)',
            email: 'orangtua.test@dareliman.sch.id',
            password: 'OrangTuaTest@2026!',
            primaryRole: 'Orang Tua',
            phone: '081299990020',
            scope: 'linked_children'
        );

        $parentModel = ParentModel::withTrashed()->updateOrCreate(
            ['user_id' => $parentUser->id],
            [
                'nik' => '1371000000000020',
                'full_name' => $parentUser->name,
                'phone' => $parentUser->phone,
                'email' => $parentUser->email,
                'occupation' => 'Wiraswasta / Profesional IT',
                'address' => 'Jl. Khatib Sulaiman No. 77, Padang Utara, Kota Padang',
                'metadata' => [
                    self::TEST_METADATA_FLAG => true,
                    'created_by' => 'UserTestSeeder',
                    'nama_ayah' => 'Rahmat Hidayat',
                    'nama_ibu' => 'Siti Aminah',
                    'no_wa' => $parentUser->phone,
                ],
            ]
        );
        $parentModel->restore();

        $summaryRows[] = [
            'Orang Tua (Wali 4 Anak)',
            $parentUser->email,
            'OrangTuaTest@2026!',
            'linked_children',
            'Memiliki 4 Anak (2 Fullday + 2 Boarding)',
        ];

        // 2. Ambil 4 Unit Berbeda untuk 4 Anak:
        //    - 2 Unit Fullday (misal SDIT-01 dan SMPIT-01 / SDIT-02)
        //    - 2 Unit Boarding (misal PONPES-PA dan PONPES-PI / MAHAD-01)
        $unitFullday1 = EducationUnit::where('code', 'SDIT-01')->first()
            ?? EducationUnit::where('name', 'like', '%SD%')->first()
            ?? EducationUnit::first();

        $unitFullday2 = EducationUnit::where('code', 'SMPIT-01')->first()
            ?? EducationUnit::where('code', 'SDIT-02')->first()
            ?? EducationUnit::where('id', '!=', $unitFullday1?->id)->first();

        $unitBoarding1 = EducationUnit::where('code', 'PONPES-PA')->first()
            ?? EducationUnit::where('name', 'like', '%Putra%')->orWhere('name', 'like', '%Ponpes%')->first();

        $unitBoarding2 = EducationUnit::where('code', 'PONPES-PI')->first()
            ?? EducationUnit::where('code', 'MAHAD-01')->first()
            ?? EducationUnit::whereNotIn('id', array_filter([$unitFullday1?->id, $unitFullday2?->id, $unitBoarding1?->id]))->first();

        // 3. Konfigurasi 4 Anak
        $childrenConfigs = [
            // Anak 1: Fullday Unit A (SDIT-01)
            [
                'nis' => 'TEST-SD-001',
                'nisn' => '0123450001',
                'name' => 'Ahmad Zaki Pratama (Fullday SDIT 1)',
                'email' => 'santri.zaki.test@dareliman.sch.id',
                'gender' => 'male',
                'birth_date' => '2017-05-15',
                'unit' => $unitFullday1,
                'program_type' => 'fullday',
                'category' => 'Anak 1 (Fullday SD)',
                'extra_meta' => [
                    'hobi' => 'Membaca & Robotik',
                    'golongan_darah' => 'O',
                ],
            ],
            // Anak 2: Fullday Unit B (SMPIT-01 - Unit Berbeda)
            [
                'nis' => 'TEST-SMP-002',
                'nisn' => '0123450002',
                'name' => 'Fathimah Azzahra (Fullday SMPIT 1)',
                'email' => 'siswa.fathimah.test@dareliman.sch.id',
                'gender' => 'female',
                'birth_date' => '2012-08-20',
                'unit' => $unitFullday2,
                'program_type' => 'fullday',
                'category' => 'Anak 2 (Fullday SMP)',
                'extra_meta' => [
                    'hobi' => 'Menulis & Kaligrafi',
                    'golongan_darah' => 'A',
                ],
            ],
            // Anak 3: Boarding Unit A (PONPES-PA - Ponpes Putra)
            [
                'nis' => 'TEST-PONPES-PA-003',
                'nisn' => '0123450003',
                'name' => 'Rayhan Al-Fatih (Boarding Ponpes Putra)',
                'email' => 'santri.rayhan.test@dareliman.sch.id',
                'gender' => 'male',
                'birth_date' => '2011-03-10',
                'unit' => $unitBoarding1,
                'program_type' => 'boarding',
                'category' => 'Anak 3 (Boarding Ponpes Putra)',
                'extra_meta' => [
                    'gedung_asrama' => 'Gedung Asrama Al-Farabi',
                    'kamar_asrama' => 'Kamar 01',
                    'musyrif' => 'Musrif Asrama Test (Ustadz Salman)',
                    'target_tahfizh' => '30 Juz Mutqin',
                ],
            ],
            // Anak 4: Boarding Unit B (PONPES-PI - Ponpes Putri - Unit Berbeda)
            [
                'nis' => 'TEST-PONPES-PI-004',
                'nisn' => '0123450004',
                'name' => 'Aisyah Humaira (Boarding Ponpes Putri)',
                'email' => 'santri.aisyah.test@dareliman.sch.id',
                'gender' => 'female',
                'birth_date' => '2011-11-25',
                'unit' => $unitBoarding2,
                'program_type' => 'boarding',
                'category' => 'Anak 4 (Boarding Ponpes Putri)',
                'extra_meta' => [
                    'gedung_asrama' => 'Gedung Asrama Khadijah',
                    'kamar_asrama' => 'Kamar 03',
                    'musyrif' => 'Musyrifah Asrama Putri',
                    'target_tahfizh' => '30 Juz Mutqin',
                ],
            ],
        ];

        foreach ($childrenConfigs as $childCfg) {
            $unitTarget = $childCfg['unit'];
            $unitId = $unitTarget?->id;

            // Cari kelas yang cocok di unit target
            $kelasTarget = $unitId
                ? (Kelas::where('unit_pendidikan_id', $unitId)->orderBy('kode_kelas')->first() ?? Kelas::first())
                : Kelas::first();

            // Akun User untuk Anak/Santri (Memungkinkan anak login langsung ke portal siswa)
            $childUser = $this->createOrUpdateUser(
                name: $childCfg['name'],
                email: $childCfg['email'],
                password: 'SiswaTest@2026!',
                primaryRole: 'Siswa',
                phone: '081299990' . substr($childCfg['nisn'], -3),
                scope: 'self',
                unitId: $unitId,
                unitCode: $unitTarget?->code,
                unitName: $unitTarget?->name
            );

            // Record Profil Siswa / Santri
            $student = Student::withTrashed()->updateOrCreate(
                ['nis' => $childCfg['nis']],
                [
                    'user_id' => $childUser->id,
                    'parent_id' => $parentModel->id,
                    'unit_id' => $unitId ?? $kelasTarget?->unit_pendidikan_id,
                    'kelas_id' => $kelasTarget?->id,
                    'nisn' => $childCfg['nisn'],
                    'full_name' => $childCfg['name'],
                    'gender' => $childCfg['gender'],
                    'birth_date' => $childCfg['birth_date'],
                    'birth_place' => 'Padang',
                    'address' => 'Jl. Khatib Sulaiman No. 77, Kota Padang',
                    'is_active' => true,
                    'deleted_at' => null,
                    'metadata' => array_merge([
                        self::TEST_METADATA_FLAG => true,
                        'status' => 'Aktif',
                        'program_type' => $childCfg['program_type'],
                        'created_by' => 'UserTestSeeder',
                    ], $childCfg['extra_meta']),
                ]
            );
            $student->restore();

            // Hubungkan relasi pada tabel pivot student_parents (is_primary = true)
            StudentParent::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'parent_id' => $parentModel->id,
                ],
                [
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'metadata' => [
                        self::TEST_METADATA_FLAG => true,
                        'created_by' => 'UserTestSeeder',
                    ],
                ]
            );

            $summaryRows[] = [
                $childCfg['category'],
                $childCfg['email'],
                'SiswaTest@2026!',
                'self',
                ($unitTarget?->code ?? 'UNIT') . ' - NIS: ' . $childCfg['nis'],
            ];
        }
    }

    /**
     * Helper membuat atau memperbarui User dengan metadata dan role yang aman.
     */
    private function createOrUpdateUser(
        string $name,
        string $email,
        string $password,
        string $primaryRole,
        ?string $phone = null,
        string $scope = 'global',
        ?string $unitId = null,
        ?string $unitCode = null,
        ?string $unitName = null,
        array $extraRoles = [],
        array $extraMeta = []
    ): User {
        $meta = array_merge([
            self::TEST_METADATA_FLAG => true,
            'created_by' => 'UserTestSeeder',
            'data_scope' => $scope,
            'unit_id' => $unitId,
            'unit_code' => $unitCode,
            'unit_name' => $unitName,
            'default_password' => $password,
        ], $extraMeta);

        $resolvedPhone = $phone ?? ('08129999' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT));
        $resolvedPhone = substr(preg_replace('/\D+/', '', $resolvedPhone) ?? $resolvedPhone, 0, 14);

        $user = User::withTrashed()->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'phone' => $resolvedPhone,
                'is_active' => true,
                'metadata' => $meta,
            ]);
        } else {
            $user->restore();
            $existingPhone = $user->phone
                ? substr(preg_replace('/\D+/', '', $user->phone) ?? $user->phone, 0, 14)
                : null;
            $user->update([
                'name' => $name,
                'password' => Hash::make($password),
                'phone' => $phone !== null ? $resolvedPhone : ($existingPhone ?: $resolvedPhone),
                'is_active' => true,
                'metadata' => array_merge($user->metadata ?? [], $meta),
            ]);
        }

        // Sinkronisasi Role:
        // PERHATIAN KHUSUS: User non-Super Admin JANGAN SAMPAI memiliki role 'Super Admin'
        // agar logika query impersonasi (whereDoesntHave('roles', 'Super Admin')) selalu valid.
        $allRolesToAssign = array_unique(array_merge([$primaryRole], $extraRoles));
        $user->syncRoles($allRolesToAssign);

        return $user;
    }

    /**
     * Helper membuat atau memperbarui data Employee untuk Pimpinan, TU, Operator, dan Guru.
     */
    private function createOrUpdateEmployee(
        User $user,
        string $niy,
        ?string $unitId = null,
        string $roleName = '',
        string $gender = 'L'
    ): Employee {
        $employee = Employee::withTrashed()
            ->where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();

        $empData = [
            'user_id' => $user->id,
            'niy' => $niy,
            'nama_lengkap' => $user->name,
            'nama_panggilan' => explode(' ', $user->name)[0] ?? 'Pegawai',
            'jenis_kelamin' => $gender,
            'status_pegawai' => 'Tetap',
            'status' => 'Aktif',
            'unit_id' => $unitId,
            'email' => $user->email,
            'no_hp' => $user->phone,
            'deleted_at' => null,
            'metadata' => array_merge($employee?->metadata ?? [], [
                self::TEST_METADATA_FLAG => true,
                'role' => $roleName,
                'created_by' => 'UserTestSeeder',
            ]),
        ];

        if (! $employee) {
            $employee = Employee::create($empData);
        } else {
            $employee->restore();
            $employee->update($empData);
        }

        return $employee;
    }

    /**
     * Helper membuat atau memperbarui Teacher untuk role Guru.
     */
    private function createOrUpdateTeacher(
        User $user,
        Employee $employee,
        string $employeeNumber
    ): Teacher {
        return Teacher::withTrashed()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_id' => $employee->id,
                'employee_number' => $employeeNumber,
                'full_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'deleted_at' => null,
                'metadata' => [
                    self::TEST_METADATA_FLAG => true,
                    'created_by' => 'UserTestSeeder',
                ],
            ]
        );
    }
}

