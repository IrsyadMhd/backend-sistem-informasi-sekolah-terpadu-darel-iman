<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Position;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruBkAuthorizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_jbt022_position_is_mapped_to_guru_bk_role(): void
    {
        $role = Role::firstOrCreate(['name' => 'Guru BK', 'guard_name' => 'web']);
        $position = Position::create([
            'code' => 'JBT-022',
            'name' => 'Guru BK',
            'role_sistem_id' => $role->id,
            'satuan_kerja' => 'Unit Pendidikan',
            'level_jabatan' => 8,
            'is_active' => true,
        ]);

        $this->assertNotNull($position);
        $this->assertEquals($role->id, $position->role_sistem_id);
    }

    public function test_guru_bk_can_access_own_unit_dashboard(): void
    {
        [$unitA] = $this->createUnits();
        $counselor = $this->createCounselor($unitA, 'counselor-a');

        $response = $this->actingAs($counselor, 'web')->getJson('/api/dashboard/guru-bk');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'context' => ['role', 'tahun_ajaran', 'semester'],
                'kpis' => ['total_konseling', 'siswa_dalam_pendampingan', 'kasus_menunggu_tindak_lanjut', 'kasus_prioritas_tinggi'],
                'tables' => ['cases'],
            ],
        ]);
    }

    public function test_guru_bk_cannot_access_foreign_unit_dashboard(): void
    {
        [$unitA, $unitB] = $this->createUnits();
        $counselor = $this->createCounselor($unitA, 'counselor-scope');

        $response = $this->actingAs($counselor, 'web')
            ->getJson('/api/dashboard/guru-bk?unit_id=' . $unitB->id);

        $response->assertStatus(403);
    }

    public function test_unbounded_query_protection_returns_empty_scope_for_unitless_guru_bk(): void
    {
        $unitlessUser = User::create([
            'name' => 'Unitless Counselor',
            'email' => 'unitless@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $unitlessUser->assignRole('Guru BK');

        $response = $this->actingAs($unitlessUser, 'web')->getJson('/api/dashboard/guru-bk');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(0, $data['kpis']['total_konseling']['total']);
        $this->assertEquals(0, $data['kpis']['siswa_dalam_pendampingan']['total']);
        $this->assertEmpty($data['tables']['cases']);
    }

    public function test_guru_bk_cannot_access_or_write_notes_for_foreign_unit_student(): void
    {
        [$unitA, $unitB] = $this->createUnits();
        $counselor = $this->createCounselor($unitA, 'counselor-write');
        $studentB = $this->createStudent($unitB, 'B');

        $response = $this->actingAs($counselor, 'web')->postJson('/api/teacher/student-notes', [
            'student_id' => $studentB->id,
            'category' => 'Konseling',
            'title' => 'Cross Unit Note Test',
            'content' => 'Testing cross unit note protection',
            'priority' => 'high',
        ]);

        $this->assertContains($response->status(), [403, 404]);
    }

    /** @return array{EducationUnit, EducationUnit} */
    private function createUnits(): array
    {
        return [
            EducationUnit::create(['code' => 'UNIT-A', 'name' => 'Unit Sekolah A', 'level' => 'SMP', 'is_active' => true]),
            EducationUnit::create(['code' => 'UNIT-B', 'name' => 'Unit Sekolah B', 'level' => 'SMA', 'is_active' => true]),
        ];
    }

    private function createCounselor(EducationUnit $unit, string $slug): User
    {
        $user = User::create([
            'name' => 'Konselor ' . $unit->name,
            'email' => $slug . '@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole('Guru BK');

        $emp = Employee::create([
            'niy' => 'NIY-' . $slug,
            'nama_lengkap' => 'Konselor ' . $unit->name,
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);

        return $user;
    }

    private function createStudent(EducationUnit $unit, string $suffix): Student
    {
        return Student::create([
            'full_name' => 'Siswa ' . $suffix,
            'nisn' => '111111111' . $suffix,
            'nis' => 'SISWA-' . $suffix,
            'gender' => 'male',
            'unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'aktif',
        ]);
    }
}
