<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalRouteRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Pastikan keempat route dev/seeder/migrasi berisiko tinggi telah dihapus dari HTTP API (404 Not Found).
     */
    public function test_removed_operational_routes_return_404_for_guest(): void
    {
        $this->getJson('/api/seed-sdit1-data')->assertNotFound();
        $this->getJson('/api/seed-all-units')->assertNotFound();
        $this->getJson('/api/run-chat-migrations')->assertNotFound();
        $this->getJson('/api/debug-chat-contacts')->assertNotFound();
    }

    public function test_removed_operational_routes_return_404_even_for_super_admin(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($superAdminRole);

        $this->actingAs($user, 'sanctum')->getJson('/api/seed-sdit1-data')->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson('/api/seed-all-units')->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson('/api/run-chat-migrations')->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson('/api/debug-chat-contacts')->assertNotFound();
    }
}
