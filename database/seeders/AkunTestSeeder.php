<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * AkunTestSeeder
 *
 * Seeder khusus akun pengujian (Test Accounts) multi-role untuk Web Dashboard & Mobile App.
 *
 * Fitur & Karakteristik:
 * - Idempoten: aman dijalankan berulang kali tanpa duplikasi data.
 * - Blokade produksi: otomatis menolak eksekusi jika environment adalah 'production'.
 * - Menyiapkan profil entitas lengkap: User, Role, Employee, Teacher, ParentModel, Student.
 * - Menampilkan tabel kredensial rapi di terminal saat proses seeding selesai.
 *
 * Cara menjalankan:
 * php artisan db:seed --class=AkunTestSeeder
 */
class AkunTestSeeder extends Seeder
{
    public const TEST_METADATA_FLAG = 'is_test_account';

    /**
     * Definisi lengkap 19 akun test role + 1 akun admin sistem.
     */
    public const TEST_ACCOUNTS = [
        'Super Admin' => [
            'email' => 'superadmin@dareliman.sch.id',
            'password' => 'SuperAdmin@2026!',
            'name' => 'Super Admin Test',
            'phone' => '081299990001',
            'role' => 'Super Admin',
            'scope' => 'global',
            'unit_code' => null,
            'is_permanent_admin' => true,
        ],
        'Admin' => [
            'email' => 'admin.test@dareliman.sch.id',
            'password' => 'AdminTest@2026!',
            'name' => 'Admin Sistem Test',
            'phone' => '081299990002',
            'role' => 'Admin',
            'scope' => 'global',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
        'Admin (Legacy)' => [
            'email' => 'admin@dareliman.sch.id',
            'password' => 'Admin@2026!',
            'name' => 'Admin Sistem',
            'phone' => '081299991001',
            'role' => 'Admin',
            'scope' => 'global',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
        'Ketua Yayasan' => [
            'email' => 'ketua.yayasan.test@dareliman.sch.id',
            'password' => 'YayasanTest@2026!',
            'name' => 'Ketua Yayasan Test',
            'phone' => '081299990003',
            'role' => 'Ketua Yayasan',
            'scope' => 'cross_unit_read',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
        'Pengurus Yayasan' => [
            'email' => 'pengurus.yayasan.test@dareliman.sch.id',
            'password' => 'YayasanTest@2026!',
            'name' => 'Pengurus Yayasan Test',
            'phone' => '081299990004',
            'role' => 'Pengurus Yayasan',
            'scope' => 'cross_unit_read',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
        'Bendahara Yayasan' => [
            'email' => 'bendahara.test@dareliman.sch.id',
            'password' => 'BendaharaTest@2026!',
            'name' => 'Bendahara Yayasan Test',
            'phone' => '081299990005',
            'role' => 'Bendahara Yayasan',
            'scope' => 'cross_unit_read',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
        'Kepala Bidang Pendidikan' => [
            'email' => 'kabid.pendidikan.test@dareliman.sch.id',
            'password' => 'KabidTest@2026!',
            'name' => 'Kabid Pendidikan Test',
            'phone' => '081299990006',
            'role' => 'Kepala Bidang Pendidikan',
            'scope' => 'cross_unit_read',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
        'Kepala Sekolah' => [
            'email' => 'kepsek.test@dareliman.sch.id',
            'password' => 'KepsekTest@2026!',
            'name' => 'Kepala Sekolah Test',
            'phone' => '081299990007',
            'role' => 'Kepala Sekolah',
            'scope' => 'education_unit',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Wakil Kurikulum' => [
            'email' => 'wakur.test@dareliman.sch.id',
            'password' => 'WakurTest@2026!',
            'name' => 'Waka Kurikulum Test',
            'phone' => '081299990008',
            'role' => 'Wakil Kurikulum',
            'scope' => 'education_unit',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Wakil Kesiswaan' => [
            'email' => 'wakes.test@dareliman.sch.id',
            'password' => 'WakesTest@2026!',
            'name' => 'Waka Kesiswaan Test',
            'phone' => '081299990009',
            'role' => 'Wakil Kesiswaan',
            'scope' => 'education_unit',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Tata Usaha' => [
            'email' => 'tu.test@dareliman.sch.id',
            'password' => 'TuTest@2026!',
            'name' => 'Tata Usaha Test',
            'phone' => '081299990010',
            'role' => 'Tata Usaha',
            'scope' => 'education_unit',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Operator' => [
            'email' => 'operator.test@dareliman.sch.id',
            'password' => 'OperatorTest@2026!',
            'name' => 'Operator Sekolah Test',
            'phone' => '081299990011',
            'role' => 'Operator',
            'scope' => 'education_unit',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Guru' => [
            'email' => 'guru.test@dareliman.sch.id',
            'password' => 'GuruTest@2026!',
            'name' => 'Guru Pengajar Test',
            'phone' => '081299990012',
            'role' => 'Guru',
            'scope' => 'own_assignments',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Guru Tahfizh' => [
            'email' => 'tahfizh.test@dareliman.sch.id',
            'password' => 'TahfizhTest@2026!',
            'name' => 'Guru Tahfizh Test',
            'phone' => '081299990013',
            'role' => 'Guru Tahfizh',
            'scope' => 'own_assignments',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Guru BK' => [
            'email' => 'bk.test@dareliman.sch.id',
            'password' => 'BkTest@2026!',
            'name' => 'Guru BK Test',
            'phone' => '081299990014',
            'role' => 'Guru BK',
            'scope' => 'own_assignments',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Wali Kelas' => [
            'email' => 'walas.test@dareliman.sch.id',
            'password' => 'WalasTest@2026!',
            'name' => 'Wali Kelas Test',
            'phone' => '081299990015',
            'role' => 'Wali Kelas',
            'scope' => 'own_assignments',
            'unit_code' => 'TKIT-01',
            'is_permanent_admin' => false,
        ],
        'Musyrif' => [
            'email' => 'musyrif.test@dareliman.sch.id',
            'password' => 'MusyrifTest@2026!',
            'name' => 'Musyrif Asrama Test',
            'phone' => '081299990016',
            'role' => 'Musyrif',
            'scope' => 'own_assignments',
            'unit_code' => 'PONPES-PA',
            'is_permanent_admin' => false,
        ],
        'Orang Tua' => [
            'email' => 'orangtua.test@dareliman.sch.id',
            'password' => 'OrangTuaTest@2026!',
            'name' => 'Orang Tua Test',
            'phone' => '081299990020',
            'role' => 'Orang Tua',
            'scope' => 'linked_children',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
        'Siswa' => [
            'email' => 'siswa.test@dareliman.sch.id',
            'password' => 'SiswaTest@2026!',
            'name' => 'Siswa Test',
            'phone' => '081299990021',
            'role' => 'Siswa',
            'scope' => 'self',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
        'Alumni' => [
            'email' => 'alumni.test@dareliman.sch.id',
            'password' => 'AlumniTest@2026!',
            'name' => 'Alumni Test',
            'phone' => '081299990022',
            'role' => 'Alumni',
            'scope' => 'self',
            'unit_code' => null,
            'is_permanent_admin' => false,
        ],
    ];

    public function run(): void
    {
        // 1. Validasi keamanan lingkungan produksi
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'KEAMANAN: AkunTestSeeder DITOLAK keras pada environment production. Seeder ini hanya untuk audit lokal & staging.'
            );
        }

        $this->command?->info('=== MEMPROVISI AKUN TEST KHUSUS MULTI-ROLE ===');

        $unitsByCode = EducationUnit::all()->keyBy('code');
        $defaultUnit = EducationUnit::where('is_active', true)->orderBy('code')->first();
        $summaryRows = [];

        foreach (self::TEST_ACCOUNTS as $key => $config) {
            $roleName = $config['role'];
            $email = $config['email'];
            $isPermanentAdmin = $config['is_permanent_admin'] ?? false;

            // Pastikan Role terdaftar
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            // Tentukan Unit Scope
            $targetUnit = $config['unit_code'] ? ($unitsByCode->get($config['unit_code']) ?? $defaultUnit) : null;
            $unitId = $targetUnit?->id;

            // Cari atau buat User
            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $config['name'],
                    'email' => $email,
                    'password' => Hash::make($config['password']),
                    'phone' => $config['phone'],
                    'is_active' => true,
                    'metadata' => [
                        self::TEST_METADATA_FLAG => true,
                        'created_by' => 'AkunTestSeeder',
                        'data_scope' => $config['scope'],
                        'unit_id' => $unitId,
                        'unit_code' => $targetUnit?->code,
                        'unit_name' => $targetUnit?->name,
                        'default_password' => $config['password'],
                    ],
                ]);
            } else {
                $user->update([
                    'name' => $config['name'],
                    'password' => Hash::make($config['password']),
                    'phone' => $config['phone'],
                    'is_active' => true,
                    'metadata' => array_merge($user->metadata ?? [], [
                        self::TEST_METADATA_FLAG => true,
                        'created_by' => 'AkunTestSeeder',
                        'data_scope' => $config['scope'],
                        'unit_id' => $unitId,
                        'unit_code' => $targetUnit?->code,
                        'unit_name' => $targetUnit?->name,
                        'default_password' => $config['password'],
                    ]),
                ]);
            }

            // Berikan Role ke User
            if ($isPermanentAdmin) {
                $user->syncRoles([$roleName]);
            } else {
                $user->syncRoles([$roleName]);
            }

            // Tambahan role Divisi Pendidikan untuk Kabid Pendidikan
            if ($roleName === 'Kepala Bidang Pendidikan') {
                Role::firstOrCreate(['name' => 'Divisi Pendidikan', 'guard_name' => 'web']);
                $user->assignRole('Divisi Pendidikan');
            }

            // Profil ParentModel untuk akun Orang Tua
            if ($roleName === 'Orang Tua') {
                ParentModel::withTrashed()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nik' => '1371000000000020',
                        'full_name' => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'occupation' => 'Akun Uji',
                        'address' => 'Data fixture akun uji',
                        'metadata' => [self::TEST_METADATA_FLAG => true],
                    ]
                );
            }

            // Profil Student untuk akun Siswa
            if ($roleName === 'Siswa') {
                $testParent = ParentModel::where('metadata->' . self::TEST_METADATA_FLAG, true)->first();
                $firstKelas = $unitId ? Kelas::where('unit_pendidikan_id', $unitId)->first() : Kelas::first();
                $firstSchoolClass = SchoolClass::first();

                $student = Student::withTrashed()->updateOrCreate(
                    ['nis' => 'TEST001'],
                    [
                        'user_id' => $user->id,
                        'parent_id' => $testParent?->id,
                        'unit_id' => $unitId ?? $firstKelas?->unit_pendidikan_id,
                        'kelas_id' => $firstKelas?->id,
                        'class_id' => $firstSchoolClass?->id,
                        'full_name' => $user->name,
                        'gender' => 'male',
                        'birth_date' => '2012-01-01',
                        'birth_place' => 'Padang',
                        'address' => 'Data fixture akun uji',
                        'is_active' => true,
                        'deleted_at' => null,
                        'metadata' => [
                            self::TEST_METADATA_FLAG => true,
                            'status' => 'Aktif',
                            'created_by' => 'AkunTestSeeder',
                        ],
                    ]
                );

                if ($testParent) {
                    StudentParent::updateOrCreate(
                        ['student_id' => $student->id, 'parent_id' => $testParent->id],
                        [
                            'relationship_type' => 'father',
                            'is_primary' => true,
                            'metadata' => [self::TEST_METADATA_FLAG => true],
                        ]
                    );
                }
            }

            // Profil Employee untuk Staf & Guru
            if (! in_array($roleName, ['Super Admin', 'Admin', 'Orang Tua', 'Siswa', 'Alumni'], true)) {
                $niy = 'TEST-NIY-' . Str::slug($key, '-');
                $employee = Employee::withTrashed()->where('email', $email)->orWhere('user_id', $user->id)->first();

                if (! $employee) {
                    $employee = Employee::create([
                        'user_id' => $user->id,
                        'niy' => $niy,
                        'nama_lengkap' => $user->name,
                        'nama_panggilan' => $key,
                        'jenis_kelamin' => 'L',
                        'status_pegawai' => 'Tetap',
                        'status' => 'Aktif',
                        'unit_id' => $unitId,
                        'email' => $email,
                        'no_hp' => $user->phone,
                        'metadata' => [
                            self::TEST_METADATA_FLAG => true,
                            'role' => $roleName,
                        ],
                    ]);
                } else {
                    $employee->restore();
                    $employee->update([
                        'user_id' => $user->id,
                        'niy' => $niy,
                        'nama_lengkap' => $user->name,
                        'status' => 'Aktif',
                        'unit_id' => $unitId,
                        'email' => $email,
                        'no_hp' => $user->phone,
                        'metadata' => array_merge($employee->metadata ?? [], [
                            self::TEST_METADATA_FLAG => true,
                            'role' => $roleName,
                        ]),
                    ]);
                }

                // Profil Teacher jika role guru
                if (in_array($roleName, ['Guru', 'Guru Tahfizh', 'Guru BK', 'Wali Kelas'], true)) {
                    Teacher::withTrashed()->updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'employee_id' => $employee?->id,
                            'employee_number' => $niy,
                            'full_name' => $user->name,
                            'email' => $email,
                            'phone' => $user->phone,
                            'metadata' => [self::TEST_METADATA_FLAG => true],
                        ]
                    );
                }
            }

            $summaryRows[] = [
                $roleName,
                $email,
                $config['password'],
                $config['scope'],
                $targetUnit?->code ?? 'Global',
            ];
        }

        if ($this->command) {
            $this->command->info('=== BERHASIL MEMPROVISI SEMUA AKUN TEST ===');
            $this->command->table(
                ['Role', 'Email Login', 'Password', 'Data Scope', 'Unit Scope'],
                $summaryRows
            );
        }
    }
}
