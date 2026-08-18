<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\StudentCardSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentCardSettingScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_setting_rejects_a_unit_outside_the_users_scope(): void
    {
        $unitA = $this->createUnit('A');
        $unitB = $this->createUnit('B');
        $user = $this->createUser('card-scope');
        Employee::create([
            'niy' => 'NIY-CARD',
            'nama_lengkap' => 'Operator Kartu',
            'unit_id' => $unitA->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/student-card-settings', $this->payload($unitB->id))
            ->assertForbidden();
    }

    public function test_selecting_a_default_never_changes_another_users_setting(): void
    {
        $unit = $this->createUnit('A');
        $first = $this->createUser('card-first');
        $second = $this->createUser('card-second');

        foreach ([$first, $second] as $index => $user) {
            Employee::create([
                'niy' => 'NIY-CARD-'.$index,
                'nama_lengkap' => 'Operator Kartu '.$index,
                'unit_id' => $unit->id,
                'user_id' => $user->id,
                'status' => 'Aktif',
            ]);
        }

        $otherSetting = StudentCardSetting::create([
            ...$this->payload($unit->id),
            'user_id' => $second->id,
            'created_by' => $second->id,
            'updated_by' => $second->id,
        ]);

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/student-card-settings', $this->payload($unit->id))
            ->assertOk();

        $this->assertTrue($otherSetting->fresh()->is_default);
    }

    private function createUnit(string $suffix): EducationUnit
    {
        return EducationUnit::create([
            'code' => 'UNIT-'.$suffix,
            'name' => 'Unit '.$suffix,
            'level' => 'SD',
            'is_active' => true,
        ]);
    }

    private function createUser(string $slug): User
    {
        return User::create([
            'name' => $slug,
            'email' => $slug.'@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
    }

    private function payload(string $unitId): array
    {
        return [
            'education_unit_id' => $unitId,
            'orientation' => 'horizontal',
            'template_color' => 'green',
            'show_photo' => true,
            'show_logo' => true,
            'show_qrcode' => true,
            'show_nis' => true,
            'show_nisn' => true,
            'show_class' => true,
            'show_rombel' => true,
            'show_unit' => true,
            'show_academic_year' => true,
            'show_motto' => true,
            'is_default' => true,
        ];
    }
}
