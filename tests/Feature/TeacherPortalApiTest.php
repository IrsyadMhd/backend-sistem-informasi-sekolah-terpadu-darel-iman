<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function teacherUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        return $user;
    }

    public function test_authenticated_user_can_access_teacher_dashboard()
    {
        $user = $this->teacherUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/teacher/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'teacher',
                    'academic_context',
                    'kpi',
                    'schedules_today',
                    'announcements',
                ],
            ]);
    }

    public function test_authenticated_user_can_access_teacher_schedules()
    {
        $user = $this->teacherUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/teacher/schedules');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_authenticated_user_can_access_teacher_classes()
    {
        $user = $this->teacherUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/teacher/classes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_authenticated_user_can_create_material_with_teacher_payload()
    {
        $user = $this->teacherUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/teacher/materials', [
                'judul' => 'Materi Uji Coba',
                'subject_id' => '00000000-0000-0000-0000-000000000001',
                'class_id' => '00000000-0000-0000-0000-000000000002',
                'ringkasan' => 'Ringkasan uji coba',
                'isi' => 'Isi materi uji coba',
                'status' => 'published',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('message', 'Materi berhasil disimpan.');

        $this->assertDatabaseHas('lms_materi', [
            'judul' => 'Materi Uji Coba',
        ]);
    }

    public function test_authenticated_user_can_update_and_delete_own_material(): void
    {
        $user = $this->teacherUser();
        $created = $this->actingAs($user, 'sanctum')->postJson('/api/teacher/materials', [
            'judul' => 'Materi Awal',
            'subject_id' => '00000000-0000-0000-0000-000000000001',
            'class_id' => '00000000-0000-0000-0000-000000000002',
            'ringkasan' => 'Ringkasan awal',
            'isi' => 'Isi awal',
            'status' => 'draft',
        ])->assertOk();

        $materialId = $created->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/teacher/materials')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $materialId);

        $this->actingAs($user, 'sanctum')->putJson("/api/teacher/materials/{$materialId}", [
            'judul' => 'Materi Diperbarui',
            'ringkasan' => 'Ringkasan baru',
            'isi' => 'Isi baru',
            'status' => 'published',
        ])->assertOk()->assertJsonPath('data.judul', 'Materi Diperbarui');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/teacher/materials/{$materialId}")
            ->assertOk();

        $this->assertSoftDeleted('lms_materi', ['id' => $materialId]);
    }

    public function test_authenticated_user_can_create_assignment_with_teacher_payload()
    {
        $user = $this->teacherUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/teacher/assignments', [
                'judul' => 'Tugas Uji Coba',
                'subject_id' => '00000000-0000-0000-0000-000000000001',
                'class_id' => '00000000-0000-0000-0000-000000000002',
                'instruksi' => 'Kerjakan sesuai petunjuk.',
                'deadline' => '2026-08-10',
                'bobot' => 100,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('message', 'Penugasan berhasil dibuat.');

        $this->assertDatabaseHas('lms_penugasan', [
            'judul_tugas' => 'Tugas Uji Coba',
        ]);
    }
}
