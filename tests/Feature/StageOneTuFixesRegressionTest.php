<?php

namespace Tests\Feature;

use App\Exports\MutabaahCrudExport;
use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

enum StageOneDummyBackedEnum: string
{
    case Active = 'aktif_test';
}

class StageOneTuFixesRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'AttendancePermissionSeeder']);
    }

    public function test_super_admin_can_impersonate_tata_usaha(): void
    {
        $admin = User::factory()->create(['email' => 'sa-impersonate@school.test', 'is_active' => true]);
        $admin->assignRole('Super Admin');

        $tu = User::factory()->create([
            'name' => 'TU Test Impersonate',
            'email' => 'tu-impersonate@school.test',
            'is_active' => true,
        ]);
        $tu->assignRole('Tata Usaha');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/auth/impersonate', ['role' => 'Tata Usaha'])
            ->assertOk()
            ->assertJsonPath('user.email', 'tu-impersonate@school.test');
    }

    public function test_gate_attendance_schedule_config_accepts_unit_id_in_post_body_for_unit_user(): void
    {
        $unitA = EducationUnit::factory()->create();
        $unitB = EducationUnit::factory()->create();

        $tu = User::factory()->create(['email' => 'tu-gate@school.test', 'is_active' => true]);
        $tu->assignRole('Tata Usaha');
        Employee::factory()->create(['user_id' => $tu->id, 'unit_id' => $unitA->id]);

        $this->actingAs($tu, 'sanctum')
            ->postJson('/api/gate-attendance/schedule-config', [
                'unit_id' => $unitA->id,
                'jam_masuk' => '07:15',
                'toleransi_menit' => 15,
                'jam_pulang' => '15:30',
            ])
            ->assertOk();

        // Cross-unit schedule save remains blocked
        $this->actingAs($tu, 'sanctum')
            ->postJson('/api/gate-attendance/schedule-config', [
                'unit_id' => $unitB->id,
                'jam_masuk' => '07:15',
                'toleransi_menit' => 15,
                'jam_pulang' => '15:30',
            ])
            ->assertForbidden();
    }

    public function test_alumni_manual_store_generates_unique_nis_when_blank_and_defaults_gender(): void
    {
        $unit = EducationUnit::factory()->create();
        $tu = User::factory()->create(['email' => 'tu-alumni@school.test', 'is_active' => true]);
        $tu->assignRole('Tata Usaha');
        Employee::factory()->create(['user_id' => $tu->id, 'unit_id' => $unit->id]);

        $first = $this->actingAs($tu, 'sanctum')
            ->postJson('/api/alumni', [
                'full_name' => 'Alumni Pertama',
                'unit_id' => $unit->id,
                'nis' => '',
                'tahun_lulus' => 2025,
                'status_alumni' => 'Tamat',
            ]);

        $first->assertCreated();
        $this->assertNotEmpty($first->json('data.nis'));
        $this->assertEquals('male', $first->json('data.gender'));

        $second = $this->actingAs($tu, 'sanctum')
            ->postJson('/api/alumni', [
                'full_name' => 'Alumni Kedua',
                'unit_id' => $unit->id,
                'nis' => null,
                'gender' => 'female',
                'tahun_lulus' => 2025,
                'status_alumni' => 'Tamat',
            ]);

        $second->assertCreated();
        $this->assertNotEquals($first->json('data.nis'), $second->json('data.nis'));
        $this->assertEquals('female', $second->json('data.gender'));
    }

    public function test_mutabaah_crud_export_normalizes_enums_dates_booleans_and_arrays(): void
    {
        $export = new MutabaahCrudExport(
            collect([
                [
                    'status' => StageOneDummyBackedEnum::Active,
                    'tanggal' => new \DateTimeImmutable('2026-11-11 08:30:00'),
                    'aktif' => true,
                    'meta' => ['juz' => 30],
                ],
            ]),
            ['status', 'tanggal', 'aktif', 'meta']
        );

        $rows = $export->collection()->all();
        $this->assertSame('aktif_test', $rows[0][0]);
        $this->assertSame('2026-11-11 08:30:00', $rows[0][1]);
        $this->assertSame('Ya', $rows[0][2]);
        $this->assertSame('{"juz":30}', $rows[0][3]);
    }

    public function test_tu_can_access_parent_mutabaah_monitoring_and_school_information(): void
    {
        $unit = EducationUnit::factory()->create();
        AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $tu = User::factory()->create(['email' => 'tu-portal@school.test', 'is_active' => true]);
        $tu->assignRole('Tata Usaha');
        Employee::factory()->create(['user_id' => $tu->id, 'unit_id' => $unit->id]);

        $this->actingAs($tu, 'sanctum')
            ->getJson('/api/parent/mutabaah/children')
            ->assertOk();

        $this->actingAs($tu, 'sanctum')
            ->getJson('/api/portal/school-information')
            ->assertOk();
    }
}
