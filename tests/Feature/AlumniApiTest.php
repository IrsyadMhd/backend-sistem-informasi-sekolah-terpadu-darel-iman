<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlumniApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_alumni_list_and_stats(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        Student::create([
            'nis' => '123456789',
            'full_name' => 'Siswa Alumni Test',
            'gender' => 'L',
            'tahun_masuk' => 2022,
            'is_active' => false,
            'metadata' => ['status_alumni' => 'Tamat', 'tahun_lulus' => 2025],
        ]);


        $response = $this->actingAs($user)
            ->getJson('/api/alumni');

        $response->assertStatus(200);

        $statsResponse = $this->actingAs($user)
            ->getJson('/api/alumni/stats');

        $statsResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

    }
}
