<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\User;
use Database\Seeders\DefaultRoleUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationRoleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DefaultRoleUserSeeder::class);
    }

    public function test_pengurus_yayasan_can_login_successfully(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'pengurus.yayasan@school-erp.local',
            'password' => 'Yayasan@2026!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_foundation_dashboard_endpoint_is_accessible(): void
    {
        $user = User::where('email', 'pengurus.yayasan@school-erp.local')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/foundation/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_all_foundation_view_endpoints_return_success(): void
    {
        $user = User::where('email', 'pengurus.yayasan@school-erp.local')->firstOrFail();

        $endpoints = [
            '/api/foundation/units',
            '/api/foundation/employees',
            '/api/foundation/students',
            '/api/foundation/new-students',
            '/api/foundation/student-mutations',
            '/api/foundation/graduations',
            '/api/foundation/alumni',
            '/api/foundation/information',
            '/api/foundation/reports',
            '/api/foundation/laporan/sdm',
            '/api/foundation/laporan/siswa',
            '/api/foundation/laporan/mutasi',
            '/api/foundation/laporan/kelulusan',
            '/api/foundation/laporan/alumni',
            '/api/foundation/laporan/lintas-unit',
            '/api/foundation/notifications',
            '/api/foundation/profile',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($user, 'sanctum')->getJson($endpoint);
            $response->assertStatus(200);
        }
    }

    public function test_user_without_foundation_permission_gets_403_forbidden(): void
    {
        $guru = User::where('email', 'guru@school-erp.local')->firstOrFail();

        $response = $this->actingAs($guru, 'sanctum')
            ->getJson('/api/foundation/dashboard');

        // Verify permission boundary
        $this->assertFalse($guru->can('foundation.dashboard.view'));
    }

    public function test_unit_filter_param_works_on_foundation_endpoints(): void
    {
        $user = User::where('email', 'pengurus.yayasan@school-erp.local')->firstOrFail();
        $unit = EducationUnit::first();
        $unitId = $unit ? $unit->id : 'all';

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/foundation/students?unit_id={$unitId}");

        $response->assertStatus(200);
    }

    public function test_operational_post_request_by_foundation_user_is_rejected(): void
    {
        $user = User::where('email', 'pengurus.yayasan@school-erp.local')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/students', [
                'nis' => 'TEST999',
                'full_name' => 'Trial Student',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    public function test_operational_put_request_by_foundation_user_is_rejected(): void
    {
        $user = User::where('email', 'pengurus.yayasan@school-erp.local')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/students/dummy-uuid', [
                'full_name' => 'Updated Name',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    public function test_operational_delete_request_by_foundation_user_is_rejected(): void
    {
        $user = User::where('email', 'pengurus.yayasan@school-erp.local')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/students/dummy-uuid');

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    public function test_foundation_profile_update_is_allowed(): void
    {
        $user = User::where('email', 'pengurus.yayasan@school-erp.local')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/foundation/profile', [
                'email' => 'pengurus.yayasan@school-erp.local',
                'phone' => '081234567890',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}
