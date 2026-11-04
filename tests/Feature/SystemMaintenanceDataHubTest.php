<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemMaintenanceDataHubTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('Siswa');
    }

    public function test_superadmin_can_access_status(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/system-maintenance/status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'metrics' => [
                        'students',
                        'employees',
                        'positions',
                        'education_units',
                        'classes',
                        'subjects',
                    ],
                    'server' => [
                        'php_version',
                        'laravel_version',
                        'database_driver',
                        'cache_driver',
                    ],
                ],
            ]);
    }

    public function test_superadmin_can_run_optimize_clear(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/system-maintenance/optimize-clear');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'duration_ms',
                    'logs',
                    'timestamp',
                ],
            ]);
    }

    public function test_reset_database_requires_exact_confirmation_key(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/system-maintenance/reset-database', [
                'confirmation_key' => 'WRONG KEY',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_non_admin_cannot_access_system_maintenance(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/v1/system-maintenance/status');

        $response->assertStatus(403);
    }
}
