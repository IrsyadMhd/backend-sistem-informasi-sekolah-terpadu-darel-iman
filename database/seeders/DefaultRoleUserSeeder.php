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
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultRoleUserSeeder extends Seeder
{
    /** @var list<string> */
    private const CANONICAL_ROLES = [
        'Super Admin',
        'Ketua Yayasan',
        'Pengurus Yayasan',
        'Sekretaris Yayasan',
        'Bendahara Yayasan',
        'Kepala Bidang Pendidikan',
        'Divisi Kurikulum',
        'Divisi Kesiswaan',
        'Divisi Bahasa',
        'Divisi Program Khusus',
        'Kepala Sekolah',
        'Wakil Kepala Sekolah',
        'Wakil Kurikulum',
        'Wakil Kesiswaan',
        'Tata Usaha',
        'Operator',
        'Guru',
        'Guru Tahfizh',
        'Guru BK',
        'Wali Kelas',
        'Musyrif',
        'Orang Tua',
        'Siswa',
        'Alumni',
    ];

    /**
     * Membuat satu akun login berelasi untuk setiap role kanonik.
     *
     * User memakai cast `hashed`, sehingga nilai password yang tersimpan di
     * PostgreSQL selalu hash. updateOrCreate menjaga seeder idempotent.
     */
    public function run(): void
    {
        // Fixture login hanya untuk local/development/testing; production
        // tetap memakai role/permission bootstrap tanpa akun demo aktif.
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        $unit = EducationUnit::query()->where('is_active', true)->orderBy('code')->first();
        $academicYear = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->latest('start_date')->first();
        $semester = Semester::query()->where('is_active', true)->first()
            ?? Semester::query()->where('academic_year_id', $academicYear?->id)->orderBy('sequence')->first();
        $kelas = Kelas::query()->where('status', 'Aktif')->first();
        $defaultPassword = (string) env('DEFAULT_TEST_ACCOUNT_PASSWORD', 'Password123!');
        $canonicalOverrides = [
            'Super Admin' => ['superadmin@school-erp.local', (string) env('DEFAULT_SUPER_ADMIN_PASSWORD', 'Password123!')],
            'Ketua Yayasan' => ['role.ketua.yayasan@school-erp.local', $defaultPassword],
            'Pengurus Yayasan' => ['role.pengurus.yayasan@school-erp.local', $defaultPassword],
            'Sekretaris Yayasan' => ['role.sekretaris.yayasan@school-erp.local', $defaultPassword],
            'Bendahara Yayasan' => ['role.bendahara.yayasan@school-erp.local', $defaultPassword],
            'Kepala Sekolah' => ['kepsek@school-erp.local', (string) env('DEFAULT_KEPSEK_PASSWORD', 'Kepsek@2026!')],
            'Tata Usaha' => ['tu@school-erp.local', (string) env('DEFAULT_TU_PASSWORD', 'TU@2026!')],
            'Guru' => ['guru@school-erp.local', (string) env('DEFAULT_GURU_PASSWORD', 'Guru@2026!')],
            'Guru Tahfizh' => ['guru.tahfizh@school-erp.local', (string) env('DEFAULT_GURU_TAHFIZH_PASSWORD', 'Tahfizh@2026!')],
            'Musyrif' => ['musyrif@school-erp.local', (string) env('DEFAULT_MUSYRIF_PASSWORD', 'Musyrif@2026!')],
            'Orang Tua' => ['orangtua@school-erp.local', (string) env('DEFAULT_ORANG_TUA_PASSWORD', 'OrangTua@2026!')],
            'Siswa' => ['siswa@school-erp.local', (string) env('DEFAULT_SISWA_PASSWORD', 'Siswa@2026!')],
        ];

        $users = [];
        foreach (self::CANONICAL_ROLES as $index => $roleName) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $slug = Str::slug($roleName, '.');
            $sequence = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            [$email, $password] = $canonicalOverrides[$roleName] ?? [$slug.'@school-erp.local', $defaultPassword];
            $phone = '0812999900'.$sequence;

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $roleName.' Test',
                    'password' => $password,
                    'phone' => $phone,
                    'is_active' => true,
                    'metadata' => [
                        'created_by' => 'default_role_user_seeder',
                        'must_change_password' => true,
                        'bootstrap_role' => $roleName,
                        'education_unit_id' => $unit?->id,
                        'academic_year_id' => $academicYear?->id,
                        'semester_id' => $semester?->id,
                        'data_scope' => $this->dataScopeFor($roleName),
                    ],
                ],
            );

            $user->syncRoles([$role->name]);
            $users[$roleName] = $user;

            if (! in_array($roleName, ['Super Admin', 'Orang Tua', 'Siswa', 'Alumni'], true)) {
                $employee = Employee::query()->updateOrCreate(
                    ['niy' => 'TEST-NIY-'.$sequence],
                    [
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                        'unit_id' => $unit?->id,
                        'nama_lengkap' => $user->name,
                        'nama_panggilan' => $roleName,
                        'jenis_kelamin' => 'L',
                        'status_pegawai' => 'Tetap',
                        'tanggal_masuk' => now()->startOfYear()->toDateString(),
                        'status' => 'Aktif',
                        'no_hp' => $phone,
                        'email' => $email,
                        'metadata' => [
                            'fixture' => 'canonical_role_login',
                            'academic_year_id' => $academicYear?->id,
                            'semester_id' => $semester?->id,
                        ],
                    ],
                );

                if (in_array($roleName, ['Guru', 'Guru Tahfizh', 'Guru BK', 'Wali Kelas'], true)) {
                    Teacher::query()->updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'employee_id' => $employee->id,
                            'employee_number' => 'TEST-TEACHER-'.$sequence,
                            'full_name' => $user->name,
                            'email' => $email,
                            'phone' => $phone,
                            'metadata' => ['fixture' => 'canonical_role_login'],
                        ],
                    );
                }
            }
        }

        // Akun alias lama tetap disediakan agar fixture dan integrasi yang
        // sudah memakai identifier historis tidak rusak.
        $legacyAccounts = [
            ['Admin', 'Admin Sistem', 'admin@school-erp.local', (string) env('DEFAULT_ADMIN_PASSWORD', 'Admin@2026!')],
            ['Yayasan', 'Pengurus Yayasan', 'yayasan@school-erp.local', (string) env('DEFAULT_YAYASAN_PASSWORD', 'Yayasan@2026!')],
            ['ketua_yayasan', 'Ketua Yayasan', 'ketua.yayasan@school-erp.local', (string) env('DEFAULT_KETUA_YAYASAN_PASSWORD', 'Yayasan@2026!')],
            ['sekretaris_yayasan', 'Sekretaris Yayasan', 'sekretaris.yayasan@school-erp.local', (string) env('DEFAULT_SEKRETARIS_YAYASAN_PASSWORD', 'Yayasan@2026!')],
            ['bendahara_yayasan', 'Bendahara Yayasan', 'bendahara.yayasan@school-erp.local', (string) env('DEFAULT_BENDAHARA_YAYASAN_PASSWORD', 'Yayasan@2026!')],
            ['pengurus_yayasan', 'Pengurus Yayasan', 'pengurus.yayasan@school-erp.local', (string) env('DEFAULT_PENGURUS_YAYASAN_PASSWORD', 'Yayasan@2026!')],
            ['Divisi Pendidikan', 'Divisi Pendidikan', 'divisi.pendidikan@school-erp.local', (string) env('DEFAULT_DIVISI_PASSWORD', 'Divisi@2026!')],
        ];

        foreach ($legacyAccounts as $legacyIndex => [$roleName, $name, $email, $password]) {
            Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'phone' => '0812999910'.str_pad((string) ($legacyIndex + 1), 2, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'metadata' => [
                        'created_by' => 'default_role_user_seeder',
                        'must_change_password' => true,
                        'bootstrap_role' => $roleName,
                        'education_unit_id' => $unit?->id,
                        'academic_year_id' => $academicYear?->id,
                        'semester_id' => $semester?->id,
                        'data_scope' => $this->dataScopeFor($roleName),
                    ],
                ],
            );
            $user->syncRoles([$roleName]);

            if ($roleName !== 'Admin') {
                Employee::query()->updateOrCreate(
                    ['niy' => 'TEST-LEGACY-NIY-'.str_pad((string) ($legacyIndex + 1), 2, '0', STR_PAD_LEFT)],
                    [
                        'user_id' => $user->id,
                        'role_id' => Role::query()->where('name', $roleName)->value('id'),
                        'unit_id' => $unit?->id,
                        'nama_lengkap' => $name,
                        'jenis_kelamin' => 'L',
                        'status_pegawai' => 'Tetap',
                        'status' => 'Aktif',
                        'no_hp' => $user->phone,
                        'email' => $email,
                        'metadata' => ['fixture' => 'canonical_role_login'],
                    ],
                );
            }
        }

        // === Konfigurasi Akun Super Admin: Mengisi Seluruh Role Akses Sistem & Memiliki Unit Utama ===
        $superAdminUser = $users['Super Admin'] ?? User::where('email', 'superadmin@school-erp.local')->first();
        if ($superAdminUser) {
            // 1. Berikan SELURUH role akses sistem yang ada di database kepada Super Admin
            $allRoleNames = Role::pluck('name')->toArray();
            $superAdminUser->syncRoles($allRoleNames);

            // 2. Hubungkan Super Admin ke satu Unit Utama (misal: Unit Pertama yang Aktif)
            $primaryUnit = $unit ?? EducationUnit::where('is_active', true)->orderBy('code')->first();
            $posKetuaYayasan = \App\Models\Position::where('code', 'JBT-001')->first()
                ?? \App\Models\Position::where('level_jabatan', 1)->first();

            if ($primaryUnit) {
                // Tautkan Employee record untuk Super Admin
                $superAdminEmp = Employee::query()->updateOrCreate(
                    ['niy' => 'NIY-SUPERADMIN'],
                    [
                        'user_id' => $superAdminUser->id,
                        'unit_id' => $primaryUnit->id,
                        'jabatan_id' => $posKetuaYayasan?->id,
                        'nik' => '1371000000000001',
                        'nama_lengkap' => 'Super Admin',
                        'nama_panggilan' => 'SuperAdmin',
                        'gelar_depan' => 'Ust.',
                        'gelar_belakang' => 'S.Pd.',
                        'jenis_kelamin' => 'L',
                        'status_pegawai' => 'Tetap',
                        'status' => 'Aktif',
                        'no_hp' => $superAdminUser->phone ?? '081299990001',
                        'email' => $superAdminUser->email,
                        'metadata' => [
                            'fixture' => 'superadmin_multi_role',
                            'education_unit_id' => $primaryUnit->id,
                        ],
                    ]
                );

                // Tautkan Teacher record untuk Super Admin (agar bisa menjadi Guru / Wali Kelas juga)
                Teacher::query()->updateOrCreate(
                    ['user_id' => $superAdminUser->id],
                    [
                        'employee_id' => $superAdminEmp->id,
                        'employee_number' => 'NIY-SUPERADMIN',
                        'full_name' => 'Super Admin',
                        'email' => $superAdminUser->email,
                        'phone' => $superAdminUser->phone ?? '081299990001',
                        'metadata' => ['fixture' => 'superadmin_teacher'],
                    ]
                );

                // Perbarui metadata user superadmin
                $existingMeta = $superAdminUser->metadata ?? [];
                $superAdminUser->update([
                    'metadata' => array_merge($existingMeta, [
                        'education_unit_id' => $primaryUnit->id,
                        'unit_code' => $primaryUnit->code,
                        'unit_name' => $primaryUnit->name,
                        'multi_role_enabled' => true,
                    ]),
                ]);
            }
        }

        $parentUser = $users['Orang Tua'];
        $parent = ParentModel::withTrashed()->updateOrCreate(
            ['nik' => '1371000000000022'],
            [
                'user_id' => $parentUser->id,
                'full_name' => $parentUser->name,
                'phone' => $parentUser->phone,
                'email' => $parentUser->email,
                'father_nik' => '1371000000000022',
                'mother_nik' => '1371000000000122',
                'occupation' => 'Akun Uji',
                'address' => 'Data fixture pengujian sistem',
                'metadata' => ['fixture' => 'canonical_role_login'],
            ],
        );
        if ($parent->trashed()) {
            $parent->restore();
        }

        $studentsToSeed = [
            'Siswa' => ['nis' => 'TEST-NIS-023', 'name' => $users['Siswa']->name, 'is_alumni' => false, 'user_id' => $users['Siswa']->id],
            'Alumni' => ['nis' => 'TEST-NIS-024', 'name' => $users['Alumni']->name, 'is_alumni' => true, 'user_id' => $users['Alumni']->id],
            'SiswaKedua' => ['nis' => 'TEST-NIS-025', 'name' => 'Siswa Kedua Test', 'is_alumni' => false, 'user_id' => null],
        ];

        foreach ($studentsToSeed as $key => $sData) {
            $student = Student::withTrashed()->updateOrCreate(
                ['nis' => $sData['nis']],
                [
                    'user_id' => $sData['user_id'],
                    'parent_id' => $parent->id,
                    'nisn' => 'TEST-NISN-'.substr($sData['nis'], -3),
                    'kelas_id' => $kelas?->id,
                    'unit_id' => $unit?->id,
                    'full_name' => $sData['name'],
                    'gender' => 'female',
                    'birth_date' => now()->subYears($sData['is_alumni'] ? 19 : 10)->toDateString(),
                    'birth_place' => 'Padang',
                    'address' => 'Data fixture pengujian sistem',
                    'is_active' => true,
                    'metadata' => [
                        'fixture' => 'canonical_role_login',
                        'academic_year_id' => $academicYear?->id,
                        'semester_id' => $semester?->id,
                        'alumni' => $sData['is_alumni'],
                    ],
                ],
            );
            if ($student->trashed()) {
                $student->restore();
            }

            StudentParent::query()->updateOrCreate(
                ['student_id' => $student->id, 'parent_id' => $parent->id],
                ['relationship_type' => 'mother', 'is_primary' => false, 'metadata' => ['fixture' => true]],
            );
        }
    }

    private function dataScopeFor(string $roleName): string
    {
        return match (true) {
            $roleName === 'Super Admin' => 'global',
            str_contains($roleName, 'Yayasan') || str_starts_with($roleName, 'Kepala Bidang') || str_starts_with($roleName, 'Divisi ') => 'cross_unit_read',
            in_array($roleName, ['Kepala Sekolah', 'Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Wakil Kesiswaan', 'Tata Usaha', 'Operator'], true) => 'education_unit',
            in_array($roleName, ['Guru', 'Guru Tahfizh', 'Guru BK', 'Wali Kelas', 'Musyrif'], true) => 'own_assignments',
            $roleName === 'Orang Tua' => 'linked_children',
            default => 'self',
        };
    }
}
