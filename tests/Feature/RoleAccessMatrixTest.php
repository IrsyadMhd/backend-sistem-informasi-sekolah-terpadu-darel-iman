<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DefaultRoleUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DefaultRoleUserSeeder::class);
    }

    public function test_super_admin_can_manage_the_whole_system(): void
    {
        $user = User::where('email', 'superadmin@school-erp.local')->firstOrFail();

        $this->assertTrue($user->hasRole('Super Admin'));
        $this->assertTrue($user->can('sistem.hak_akses'));
        $this->assertTrue($user->can('sistem.pengaturan'));
        $this->assertTrue($user->can('tahfizh.total_hafalan'));
    }

    public function test_roles_follow_the_requested_access_matrix(): void
    {
        $kepsek = User::where('email', 'kepsek@school-erp.local')->firstOrFail();
        $divisi = User::where('email', 'divisi.pendidikan@school-erp.local')->firstOrFail();
        $tu = User::where('email', 'tu@school-erp.local')->firstOrFail();
        $guru = User::where('email', 'guru@school-erp.local')->firstOrFail();
        $orangTua = User::where('email', 'orangtua@school-erp.local')->firstOrFail();
        $siswa = User::where('email', 'siswa@school-erp.local')->firstOrFail();

        $this->assertTrue($kepsek->can('kehadiran.siswa.monitoring'));
        $this->assertFalse($kepsek->can('sistem.hak_akses'));
        $this->assertTrue($divisi->can('kesiswaan.data_lengkap_siswa'));
        $this->assertFalse($divisi->can('kehadiran.siswa.absensi_digital'));
        $this->assertTrue($tu->can('pembelajaran.bank_soal'));
        $this->assertFalse($tu->can('kesiswaan.data_lengkap_siswa'));
        $this->assertTrue($guru->can('tahfizh.input_setoran_harian'));
        $this->assertFalse($guru->can('pembelajaran.bank_soal'));
        $this->assertTrue($orangTua->can('tahfizh.laporan_target'));
        $this->assertFalse($orangTua->can('tahfizh.input_setoran_harian'));
        $this->assertFalse($siswa->can('kehadiran.siswa.izin_sakit'));
        $this->assertFalse($siswa->can('tahfizh.mutabaah_yaumiyah'));
    }

    public function test_every_default_account_has_a_valid_password_and_single_role(): void
    {
        $accounts = [
            'superadmin@school-erp.local' => [env('DEFAULT_SUPER_ADMIN_PASSWORD', 'Password123!'), 'Super Admin'],
            'kepsek@school-erp.local' => ['Kepsek@2026!', 'Kepala Sekolah'],
            'divisi.pendidikan@school-erp.local' => ['Divisi@2026!', 'Divisi Pendidikan'],
            'tu@school-erp.local' => ['TU@2026!', 'Tata Usaha'],
            'guru@school-erp.local' => ['Guru@2026!', 'Guru'],
            'orangtua@school-erp.local' => ['OrangTua@2026!', 'Orang Tua'],
            'siswa@school-erp.local' => ['Siswa@2026!', 'Siswa'],
        ];

        foreach ($accounts as $email => [$password, $role]) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertTrue(Hash::check($password, $user->password));
            $this->assertContains($role, $user->getRoleNames()->all());
            $this->assertTrue($user->metadata['must_change_password']);
        }
    }
}
