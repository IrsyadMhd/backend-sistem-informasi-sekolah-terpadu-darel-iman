<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\AccessScopeService;
use Database\Seeders\DefaultRoleUserSeeder;
use Database\Seeders\MasterJabatanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase2RoleReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DefaultRoleUserSeeder::class);
        $this->seed(MasterJabatanSeeder::class);
    }

    /**
     * 1. Verifikasi role kanonikal baru terdaftar di database.
     */
    public function test_new_canonical_roles_exist_in_database(): void
    {
        $humasRole = Role::where('name', 'Wakil Humas dan Program Khusus')->where('guard_name', 'web')->first();
        $this->assertNotNull($humasRole, 'Role Wakil Humas dan Program Khusus harus terdaftar di tabel roles');

        $bendaharaRole = Role::where('name', 'Bendahara')->where('guard_name', 'web')->first();
        $this->assertNotNull($bendaharaRole, 'Role Bendahara harus terdaftar di tabel roles');
    }

    /**
     * 2. Verifikasi role kanonikal baru memiliki permission standar yang presisi.
     */
    public function test_new_canonical_roles_have_expected_permissions(): void
    {
        $humasRole = Role::where('name', 'Wakil Humas dan Program Khusus')->firstOrFail();
        $this->assertTrue($humasRole->hasPermissionTo('sekolah.informasi_sekolah'));
        $this->assertTrue($humasRole->hasPermissionTo('student.view'));
        $this->assertTrue($humasRole->hasPermissionTo('kesiswaan.data_lengkap_siswa'));
        $this->assertTrue($humasRole->hasPermissionTo('dashboard.view'));

        $bendaharaRole = Role::where('name', 'Bendahara')->firstOrFail();
        $this->assertTrue($bendaharaRole->hasPermissionTo('student.view'));
        $this->assertTrue($bendaharaRole->hasPermissionTo('approval.manage'));
        $this->assertTrue($bendaharaRole->hasPermissionTo('kesiswaan.kelas_rombel'));
        $this->assertTrue($bendaharaRole->hasPermissionTo('dashboard.view'));
    }

    /**
     * 3. Verifikasi posisi jabatan terstandarisasi terhubung ke role kanonikal di positions.role_sistem_id.
     */
    public function test_positions_role_sistem_id_are_aligned_to_canonical_roles(): void
    {
        $posHumas = Position::where('code', 'JBT-021')->firstOrFail();
        $this->assertNotNull($posHumas->role_sistem_id);
        $this->assertEquals('Wakil Humas dan Program Khusus', $posHumas->roleSistem->name);

        $posBendahara = Position::where('code', 'JBT-008')->firstOrFail();
        $this->assertNotNull($posBendahara->role_sistem_id);
        $this->assertEquals('Bendahara', $posBendahara->roleSistem->name);

        $posKurikulum = Position::where('code', 'JBT-004')->firstOrFail();
        $this->assertEquals('Wakil Kurikulum', $posKurikulum->roleSistem->name);

        $posKesiswaan = Position::where('code', 'JBT-020')->firstOrFail();
        $this->assertEquals('Wakil Kesiswaan', $posKesiswaan->roleSistem->name);
    }

    /**
     * 4. Verifikasi dual authorization pada akun pilot mempertahankan akses legitimate dan menambah kapabilitas.
     */
    public function test_pilot_dual_authorization_preserves_legitimate_access_and_grants_new_capabilities(): void
    {
        $wakaRole = Role::where('name', 'Wakil Kepala Sekolah')->firstOrFail();
        $humasRole = Role::where('name', 'Wakil Humas dan Program Khusus')->firstOrFail();

        $user = User::factory()->create();
        $user->assignRole([$wakaRole, $humasRole]);

        // Harus memiliki permission lama (misal report.view dari Wakil Kepala Sekolah)
        $this->assertTrue($user->can('report.view'));
        // Dan memiliki permission baru (sekolah.informasi_sekolah dari Wakil Humas)
        $this->assertTrue($user->can('sekolah.informasi_sekolah'));
    }

    /**
     * 5. Verifikasi isolasi cross-unit tetap terlindungi pada akun pilot.
     */
    public function test_pilot_user_cannot_access_foreign_unit_data_cross_unit_isolation(): void
    {
        $unitA = EducationUnit::factory()->create();
        $unitB = EducationUnit::factory()->create();

        $humasRole = Role::where('name', 'Wakil Humas dan Program Khusus')->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole($humasRole);

        $employee = Employee::create([
            'niy' => 'PILOT-HUMAS-01',
            'nama_lengkap' => 'Pilot Humas User',
            'user_id' => $user->id,
            'unit_id' => $unitA->id,
            'status_pegawai' => 'tetap',
            'jenis_kelamin' => 'L',
        ]);

        $accessScope = app(AccessScopeService::class);

        // Akses unit sendiri sukses
        $queryA = Student::query();
        $resultA = $accessScope->applyStrictUnitScope($queryA, $user, $unitA->id);
        $this->assertNotNull($resultA);

        // Akses unit asing memicu HTTP 403 Abort
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $queryB = Student::query();
        $accessScope->applyStrictUnitScope($queryB, $user, $unitB->id);
    }

    /**
     * 6. Verifikasi akun pilot tidak mengalami eskalasi hak istimewa (Privilege Escalation).
     */
    public function test_pilot_user_cannot_perform_privilege_escalation_to_hak_akses(): void
    {
        $humasRole = Role::where('name', 'Wakil Humas dan Program Khusus')->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole($humasRole);

        Sanctum::actingAs($user);

        // Mencoba mengakses endpoint hak-akses harus ditolak dengan 403
        $this->getJson('/api/hak-akses/users')->assertForbidden();
    }

    /**
     * 7. Verifikasi idempotency seeder: menjalankan seeder ulang tidak memutasi role atau data yang sudah benar.
     */
    public function test_seeder_idempotency(): void
    {
        $rolesCountBefore = Role::count();

        $seeder = new RolePermissionSeeder();
        $seeder->run();

        $rolesCountAfter = Role::count();
        $this->assertEquals($rolesCountBefore, $rolesCountAfter, 'Seeder harus bersifat idempotent dan tidak menggandakan roles');
    }
}
