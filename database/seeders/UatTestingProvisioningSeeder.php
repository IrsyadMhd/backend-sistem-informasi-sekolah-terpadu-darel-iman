<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UatTestingProvisioningSeeder
 *
 * Dedicated, idempotent UAT test account provisioning seeder.
 * Guarantees:
 * - Blocked unconditionally on production environment.
 * - Idempotent updateOrCreate execution.
 * - Distinct email domain `uat.<role>@example.test`.
 * - Password retrieved from environment secret (UAT_TEST_PASSWORD).
 * - Proper domain model linking:
 *   - Guru linked to Employee + Teacher + assigned Kelas 1-A & Subject.
 *   - Parent linked to ParentModel + Student in Kelas 1-A.
 *   - Kepala Sekolah & Operator & TU scoped to SDIT 1.
 *   - Yayasan & Divisi Pendidikan scoped to Global/Yayasan.
 *   - User biasa has no roles.
 *   - Super Admin has is_superadmin = true; all others strictly false.
 */
class UatTestingProvisioningSeeder extends Seeder
{
    public const UAT_FLAG = 'is_uat';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('KEAMANAN: UatTestingProvisioningSeeder DITOLAK keras pada environment production!');
        }

        $passwordRaw = env('UAT_TEST_PASSWORD');
        if (empty($passwordRaw)) {
            throw new \RuntimeException('UAT_TEST_PASSWORD wajib dikonfigurasi di environment sebelum provisioning.');
        }

        $hashedPassword = Hash::make($passwordRaw);

        // 1. Dapatkan Unit UAT SDIT 1 & Kelas 1-A & Subject Matematika
        $sditUnit = EducationUnit::where('code', 'SDIT-01')->first()
            ?? EducationUnit::where('name', 'like', '%SDIT 1%')->first()
            ?? EducationUnit::first();

        $kelas1A = Kelas::where('unit_pendidikan_id', $sditUnit?->id)->first()
            ?? Kelas::first();

        $mapelMtk = Subject::where('unit_pendidikan_id', $sditUnit?->id)->first()
            ?? Subject::first();

        // 2. Daftar 10 Akun UAT Resmi
        $accounts = [
            'user' => [
                'email' => 'uat.user@example.test',
                'name' => '[UAT] User Biasa',
                'phone' => '081299000000',
                'role' => null,
                'is_superadmin' => false,
                'unit_id' => null,
            ],
            'superadmin' => [
                'email' => 'uat.superadmin@example.test',
                'name' => '[UAT] Super Admin',
                'phone' => '081299000001',
                'role' => 'Super Admin',
                'is_superadmin' => true,
                'unit_id' => null,
            ],
            'admin' => [
                'email' => 'uat.admin@example.test',
                'name' => '[UAT] Admin Sistem',
                'phone' => '081299000002',
                'role' => 'Admin',
                'is_superadmin' => false,
                'unit_id' => null,
            ],
            'operator' => [
                'email' => 'uat.operator@example.test',
                'name' => '[UAT] Operator Sekolah',
                'phone' => '081299000003',
                'role' => 'Operator',
                'is_superadmin' => false,
                'unit_id' => $sditUnit?->id,
                'niy' => 'UAT-OP-001',
            ],
            'yayasan' => [
                'email' => 'uat.yayasan@example.test',
                'name' => '[UAT] Pengurus Yayasan',
                'phone' => '081299000004',
                'role' => 'Pengurus Yayasan',
                'is_superadmin' => false,
                'unit_id' => null,
                'niy' => 'UAT-YYS-001',
            ],
            'kepsek' => [
                'email' => 'uat.kepsek@example.test',
                'name' => '[UAT] Kepala Sekolah SDIT 1',
                'phone' => '081299000005',
                'role' => 'Kepala Sekolah',
                'is_superadmin' => false,
                'unit_id' => $sditUnit?->id,
                'niy' => 'UAT-KS-001',
            ],
            'divisi' => [
                'email' => 'uat.divisi@example.test',
                'name' => '[UAT] Divisi Pendidikan',
                'phone' => '081299000006',
                'role' => 'Divisi Pendidikan',
                'is_superadmin' => false,
                'unit_id' => null,
                'niy' => 'UAT-DIV-001',
            ],
            'tu' => [
                'email' => 'uat.tu@example.test',
                'name' => '[UAT] Tata Usaha SDIT 1',
                'phone' => '081299000007',
                'role' => 'Tata Usaha',
                'is_superadmin' => false,
                'unit_id' => $sditUnit?->id,
                'niy' => 'UAT-TU-001',
            ],
            'guru' => [
                'email' => 'uat.guru@example.test',
                'name' => '[UAT] Guru SDIT 1',
                'phone' => '081299000008',
                'role' => 'Guru',
                'is_superadmin' => false,
                'unit_id' => $sditUnit?->id,
                'niy' => 'UAT-GRU-001',
            ],
            'ortu' => [
                'email' => 'uat.ortu@example.test',
                'name' => '[UAT] Orang Tua Wali Murid',
                'phone' => '081299000009',
                'role' => 'Orang Tua',
                'is_superadmin' => false,
                'unit_id' => null,
                'nik' => '1371000000000009',
            ],
        ];

        DB::transaction(function () use ($accounts, $hashedPassword, $sditUnit, $kelas1A, $mapelMtk) {
            foreach ($accounts as $key => $data) {
                $user = User::withTrashed()->where('email', $data['email'])->first();

                if (! $user) {
                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'password' => $hashedPassword,
                        'is_active' => true,
                        'is_superadmin' => $data['is_superadmin'],
                        'metadata' => [
                            self::UAT_FLAG => true,
                            'uat_role_key' => $key,
                        ],
                    ]);
                } else {
                    $user->restore();
                    $user->update([
                        'name' => $data['name'],
                        'phone' => $data['phone'],
                        'password' => $hashedPassword,
                        'is_active' => true,
                        'is_superadmin' => $data['is_superadmin'],
                        'metadata' => array_merge($user->metadata ?? [], [
                            self::UAT_FLAG => true,
                            'uat_role_key' => $key,
                        ]),
                    ]);
                }

                // Sinkronisasi Role Spatie
                if (! empty($data['role'])) {
                    $roleObj = Role::firstOrCreate(['name' => $data['role'], 'guard_name' => 'web']);
                    $user->syncRoles([$roleObj->name]);
                } else {
                    $user->syncRoles([]);
                }

                // Buat relasi Employee jika role bertipe staf/guru
                if (isset($data['niy'])) {
                    $employee = Employee::withTrashed()
                        ->where('email', $data['email'])
                        ->orWhere('user_id', $user->id)
                        ->first();

                    if (! $employee) {
                        $employee = Employee::create([
                            'user_id' => $user->id,
                            'niy' => $data['niy'],
                            'nama_lengkap' => $data['name'],
                            'nama_panggilan' => $key,
                            'jenis_kelamin' => 'L',
                            'status_pegawai' => 'Tetap',
                            'status' => 'Aktif',
                            'unit_id' => $data['unit_id'],
                            'email' => $data['email'],
                            'no_hp' => $data['phone'],
                            'metadata' => [
                                self::UAT_FLAG => true,
                                'uat_role_key' => $key,
                            ],
                        ]);
                    } else {
                        $employee->restore();
                        $employee->update([
                            'user_id' => $user->id,
                            'niy' => $data['niy'],
                            'nama_lengkap' => $data['name'],
                            'status' => 'Aktif',
                            'unit_id' => $data['unit_id'],
                            'email' => $data['email'],
                            'no_hp' => $data['phone'],
                            'metadata' => array_merge($employee->metadata ?? [], [
                                self::UAT_FLAG => true,
                                'uat_role_key' => $key,
                            ]),
                        ]);
                    }

                    // Khusus Guru: kaitkan ke Teacher model & mata pelajaran
                    if ($key === 'guru') {
                        $teacher = Teacher::withTrashed()
                            ->where('user_id', $user->id)
                            ->first();

                        if (! $teacher) {
                            Teacher::create([
                                'user_id' => $user->id,
                                'employee_id' => $employee->id,
                                'employee_number' => $data['niy'],
                                'full_name' => $data['name'],
                                'email' => $data['email'],
                                'phone' => $data['phone'],
                                'metadata' => [
                                    self::UAT_FLAG => true,
                                    'assigned_class_id' => $kelas1A?->id,
                                    'assigned_subject_id' => $mapelMtk?->id,
                                ],
                            ]);
                        } else {
                            $teacher->restore();
                            $teacher->update([
                                'employee_id' => $employee->id,
                                'employee_number' => $data['niy'],
                                'full_name' => $data['name'],
                                'email' => $data['email'],
                                'phone' => $data['phone'],
                                'metadata' => array_merge($teacher->metadata ?? [], [
                                    self::UAT_FLAG => true,
                                    'assigned_class_id' => $kelas1A?->id,
                                    'assigned_subject_id' => $mapelMtk?->id,
                                ]),
                            ]);
                        }
                    }
                }

                // Khusus Orang Tua: buat ParentModel + Siswa UAT anak binaannya
                if ($key === 'ortu') {
                    $parent = ParentModel::withTrashed()
                        ->where('email', $data['email'])
                        ->orWhere('user_id', $user->id)
                        ->first();

                    if (! $parent) {
                        $parent = ParentModel::create([
                            'user_id' => $user->id,
                            'nik' => $data['nik'],
                            'full_name' => $data['name'],
                            'phone' => $data['phone'],
                            'email' => $data['email'],
                            'occupation' => 'Wiraswasta UAT',
                            'address' => 'Jl. UAT Mandiri No. 10',
                            'metadata' => [
                                self::UAT_FLAG => true,
                            ],
                        ]);
                    } else {
                        $parent->restore();
                        $parent->update([
                            'user_id' => $user->id,
                            'nik' => $data['nik'],
                            'full_name' => $data['name'],
                            'phone' => $data['phone'],
                            'email' => $data['email'],
                            'metadata' => array_merge($parent->metadata ?? [], [
                                self::UAT_FLAG => true,
                            ]),
                        ]);
                    }

                    // Akun Siswa UAT yang tertaut ke Orang Tua ini
                    $studentEmail = 'uat.siswa@example.test';
                    $studentUser = User::withTrashed()->where('email', $studentEmail)->first();
                    if (! $studentUser) {
                        $studentUser = User::create([
                            'name' => '[UAT] Siswa UAT SDIT 1',
                            'email' => $studentEmail,
                            'phone' => '081299000010',
                            'password' => $hashedPassword,
                            'is_active' => true,
                            'is_superadmin' => false,
                            'metadata' => [
                                self::UAT_FLAG => true,
                                'uat_role_key' => 'siswa',
                            ],
                        ]);
                    } else {
                        $studentUser->restore();
                        $studentUser->update([
                            'name' => '[UAT] Siswa UAT SDIT 1',
                            'phone' => '081299000010',
                            'password' => $hashedPassword,
                            'is_active' => true,
                            'is_superadmin' => false,
                        ]);
                    }
                    $studentRole = Role::firstOrCreate(['name' => 'Siswa', 'guard_name' => 'web']);
                    $studentUser->syncRoles([$studentRole->name]);

                    // Profil Student
                    $studentProfile = Student::withTrashed()
                        ->where('nis', 'UAT99001')
                        ->orWhere('user_id', $studentUser->id)
                        ->first();

                    if (! $studentProfile) {
                        Student::create([
                            'user_id' => $studentUser->id,
                            'parent_id' => $parent->id,
                            'unit_id' => $sditUnit?->id,
                            'kelas_id' => $kelas1A?->id,
                            'class_id' => null,
                            'nis' => 'UAT99001',
                            'nisn' => '0099990001',
                            'full_name' => '[UAT] Siswa UAT SDIT 1',
                            'gender' => 'male',
                            'birth_date' => '2019-05-15',
                            'birth_place' => 'Padang',
                            'is_active' => true,
                            'metadata' => [
                                self::UAT_FLAG => true,
                                'parent_name' => $data['name'],
                            ],
                        ]);
                    } else {
                        $studentProfile->restore();
                        $studentProfile->update([
                            'user_id' => $studentUser->id,
                            'parent_id' => $parent->id,
                            'unit_id' => $sditUnit?->id,
                            'kelas_id' => $kelas1A?->id,
                            'class_id' => null,
                            'nis' => 'UAT99001',
                            'nisn' => '0099990001',
                            'full_name' => '[UAT] Siswa UAT SDIT 1',
                            'is_active' => true,
                            'metadata' => array_merge($studentProfile->metadata ?? [], [
                                self::UAT_FLAG => true,
                                'parent_name' => $data['name'],
                            ]),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Membersihkan seluruh data UAT tanpa menyentuh data operasional.
     */
    public static function cleanup(): int
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Cleanup DITOLAK keras pada environment production.');
        }

        return DB::transaction(function () {
            // Hapus Siswa & Orang Tua UAT
            Student::where('metadata->' . self::UAT_FLAG, true)->forceDelete();
            ParentModel::where('metadata->' . self::UAT_FLAG, true)->forceDelete();

            // Hapus Guru & Pegawai UAT
            Teacher::where('metadata->' . self::UAT_FLAG, true)->forceDelete();
            Employee::where('metadata->' . self::UAT_FLAG, true)->forceDelete();

            // Hapus Tugas/Penugasan UAT jika ada
            DB::table('lms_penugasan')->where('judul_tugas', 'like', '%[UAT]%')->delete();

            // Hapus User UAT (hanya yang berakhiran @example.test atau flag is_uat)
            return User::where('metadata->' . self::UAT_FLAG, true)
                ->where('email', 'like', '%@example.test')
                ->forceDelete();
        });
    }
}
