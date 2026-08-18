<?php

namespace Tests\Feature;

use App\Models\ParentModel;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentParentPortalOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_only_select_a_child_linked_through_existing_relations(): void
    {
        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Orang Tua');
        $parent = ParentModel::create(['user_id' => $user->id, 'full_name' => 'Wali Uji']);
        $linked = Student::create(['full_name' => 'Anak Terhubung', 'nis' => 'P001', 'gender' => 'male', 'is_active' => true]);
        $foreign = Student::create(['full_name' => 'Siswa Lain', 'nis' => 'P002', 'gender' => 'female', 'is_active' => true]);
        StudentParent::create(['student_id' => $linked->id, 'parent_id' => $parent->id, 'relationship_type' => 'guardian', 'is_primary' => false]);

        $this->actingAs($user)->getJson('/api/portal/children')
            ->assertOk()
            ->assertJsonPath('data.0.id', $linked->id)
            ->assertJsonMissing(['id' => $foreign->id]);

        $this->actingAs($user)->getJson('/api/portal/profile?child_id='.$linked->id)
            ->assertOk()
            ->assertJsonPath('data.id', $linked->id);

        $this->actingAs($user)->getJson('/api/portal/profile?child_id='.$foreign->id)
            ->assertNotFound();
    }
}
