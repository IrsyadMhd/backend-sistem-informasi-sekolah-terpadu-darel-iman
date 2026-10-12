<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TestingRoleAccountsSeeder
 *
 * Seeder khusus akun pengujian RBAC untuk audit mobile dan web dashboard.
 * Memenuhi batasan:
 * - Ditolak keras jika dijalankan pada environment produksi.
 * - Idempoten: aman dijalankan berulang kali tanpa membuat duplikat akun.
 * - Tidak mengubah atau menimpa akun Super Admin produksi (superadmin@dareliman.sch.id).
 * - Tidak membuat data transaksi bisnis fiktif (tanpa jadwal, rombel, nilai, tagihan, presensi).
 * - Hanya membuat fixture minimum User + relasi Role + minimal Employee record
 *   yang diwajibkan oleh AuthIdentifierResolver agar endpoint autentikasi mobile/web berhasil.
 */
class TestingRoleAccountsSeeder extends Seeder
{
    public const TEST_METADATA_FLAG = 'is_test_account';

    /**
     * Definisi akun uji coba per role kanonik.
     */
    public const TEST_ACCOUNTS = [
        'Super Admin' => [
            'email' => 'superadmin@dareliman.sch.id',
            'password' => 'SuperAdmin@2026!',
            'name' => 'Super Admin Test',
            'phone' => '081299990001',
            'role' => 'Super Admin',
            'scope' => 'global',
            'unit_code' => null, // Global access
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
    ];

    public function run(): void
    {
        // 1. Validasi perlindungan environment produksi
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'KEAMANAN: TestingRoleAccountsSeeder DITOLAK keras pada environment production. Seeder ini hanya untuk audit lokal & staging.'
            );
        }

        // Cache unit agar tidak query berulang
        $unitsByCode = EducationUnit::all()->keyBy('code');
        $defaultUnit = EducationUnit::where('is_active', true)->orderBy('code')->first();

        foreach (self::TEST_ACCOUNTS as $key => $config) {
            $roleName = $config['role'];
            $email = $config['email'];
            $isPermanentAdmin = $config['is_permanent_admin'] ?? false;

            // Pastikan Role terdaftar
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            // Tentukan Unit Scope
            $targetUnit = $config['unit_code'] ? ($unitsByCode->get($config['unit_code']) ?? $defaultUnit) : null;
            $unitId = $targetUnit?->id;

            // Cek user yang sudah ada
            $user = User::where('email', $email)->first();

            if ($isPermanentAdmin && $user) {
                // Untuk Super Admin eksisting: JANGAN ubah passwordnya, cukup pastikan role tersinkronisasi
                $user->syncRoles([$roleName]);
                continue;
            }

            if (! $user) {
                $user = User::create([
                    'name' => $config['name'],
                    'email' => $email,
                    'password' => Hash::make($config['password']),
                    'phone' => $config['phone'],
                    'is_active' => true,
                    'metadata' => [
                        self::TEST_METADATA_FLAG => true,
                        'created_by' => 'TestingRoleAccountsSeeder',
                        'data_scope' => $config['scope'],
                        'unit_id' => $unitId,
                        'unit_code' => $targetUnit?->code,
                        'unit_name' => $targetUnit?->name,
                    ],
                ]);
            } else {
                // Update akun testing (idempoten)
                $user->update([
                    'name' => $config['name'],
                    'password' => Hash::make($config['password']),
                    'phone' => $config['phone'],
                    'is_active' => true,
                    'metadata' => array_merge($user->metadata ?? [], [
                        self::TEST_METADATA_FLAG => true,
                        'created_by' => 'TestingRoleAccountsSeeder',
                        'data_scope' => $config['scope'],
                        'unit_id' => $unitId,
                        'unit_code' => $targetUnit?->code,
                        'unit_name' => $targetUnit?->name,
                    ]),
                ]);
            }

            $user->syncRoles([$roleName]);

            // Buat profil minimum Employee untuk staf/guru agar AuthIdentifierResolver::resolveEmployee mengenali akun
            if (! in_array($roleName, ['Super Admin', 'Admin'], true)) {
                $niy = 'TEST-NIY-' . Str::slug($key, '-');
                $employee = Employee::withTrashed()->where('email', $email)->orWhere('user_id', $user->id)->first();

                if (! $employee) {
                    Employee::create([
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

                // Jika role tergolong guru, siapkan juga Teacher minimum link
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
        }
    }

    /**
     * Metode pembersihan akun pengujian secara bersih dan aman.
     * Hanya menghapus data yang memiliki flag `is_test_account = true`.
     */
    public static function cleanup(): int
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Cleanup DITOLAK keras pada environment production.');
        }

        // Hapus Teacher test
        Teacher::where('metadata->' . self::TEST_METADATA_FLAG, true)->forceDelete();

        // Hapus Employee test
        Employee::where('metadata->' . self::TEST_METADATA_FLAG, true)->forceDelete();

        // Hapus User test (JANGAN hapus Super Admin produksi)
        $deletedUsers = User::where('metadata->' . self::TEST_METADATA_FLAG, true)
            ->where('email', '!=', 'superadmin@dareliman.sch.id')
            ->forceDelete();

        return $deletedUsers;
    }
}
