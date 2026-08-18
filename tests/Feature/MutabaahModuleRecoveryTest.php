<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutabaahModuleRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_superadmin_can_access_all_mutabaah_endpoints()
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $endpoints = [
            '/api/mutabaah/analytics/dashboard',
            '/api/mutabaah/analytics/recap',
            '/api/mutabaah/enterprise/options',
            '/api/mutabaah/enterprise/categories',
            '/api/mutabaah/enterprise/agendas',
            '/api/mutabaah/enterprise/templates',
            '/api/mutabaah/enterprise/template-assignments',
            '/api/mutabaah/enterprise/supervisor-assignments',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($user, 'sanctum')->getJson($endpoint);
            $response->assertStatus(200);
        }
    }

    public function test_teacher_and_homeroom_can_access_mutabaah_analytics_and_options()
    {
        $user = User::factory()->create();
        $user->assignRole('Wali Kelas');
        $unit = EducationUnit::create([
            'code' => 'UNIT-WALAS',
            'name' => 'Unit Wali Kelas',
            'level' => 'SD',
            'is_active' => true,
        ]);
        Employee::create([
            'niy' => 'NIY-WALAS',
            'nama_lengkap' => 'Wali Kelas',
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/mutabaah/analytics/dashboard');
        $response->assertStatus(200);

        $responseRecap = $this->actingAs($user, 'sanctum')->getJson('/api/mutabaah/analytics/recap');
        $responseRecap->assertStatus(200);

        $responseOptions = $this->actingAs($user, 'sanctum')->getJson('/api/mutabaah/enterprise/options');
        $responseOptions->assertStatus(200);
    }

    public function test_homeroom_without_employee_linkage_is_denied_instead_of_receiving_global_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Wali Kelas');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mutabaah/analytics/dashboard')
            ->assertForbidden();
    }

    public function test_parent_cannot_access_mutabaah_enterprise_management()
    {
        $user = User::factory()->create();
        $user->assignRole('Orang Tua');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/mutabaah/enterprise/templates');
        $response->assertStatus(403);
    }
}
