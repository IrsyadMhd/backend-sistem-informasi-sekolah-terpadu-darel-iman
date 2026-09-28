<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MusyrifAuthorizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_musyrif_cannot_perform_operations_on_foreign_unit_student(): void
    {
        [$unitA, $unitB] = $this->createUnits();

        $musyrifA = $this->createMusyrif($unitA, 'musyrif-a');
        $studentA = $this->createStudent($unitA, 'A');
        $studentB = $this->createStudent($unitB, 'B');

        // Negative: Musyrif Unit A -> Student Unit B (clinic-logs) => 403
        $this->actingAs($musyrifA, 'sanctum')
            ->postJson('/api/musyrif/clinic-logs', [
                'student_id' => $studentB->id,
                'symptoms' => 'Demam foreign unit',
                'status' => 'rawat_jalan',
            ])
            ->assertForbidden();

        // Negative: Musyrif Unit A -> Student Unit B (deposits) => 403
        $this->actingAs($musyrifA, 'sanctum')
            ->postJson('/api/musyrif/deposits', [
                'student_id' => $studentB->id,
                'item_type' => 'smartphone',
                'item_name' => 'HP Foreign Unit',
                'deposited_at' => now()->toDateString(),
            ])
            ->assertForbidden();

        // Negative: Musyrif Unit A -> Student Unit B (point-transactions) => 403
        $this->actingAs($musyrifA, 'sanctum')
            ->postJson('/api/musyrif/point-transactions', [
                'student_id' => $studentB->id,
                'points' => 10,
                'transaction_date' => now()->toDateString(),
            ])
            ->assertForbidden();

        // Negative: Musyrif Unit A -> Student Unit B (worship-attendance) => 403
        $this->actingAs($musyrifA, 'sanctum')
            ->postJson('/api/musyrif/worship-attendance', [
                'student_id' => $studentB->id,
                'prayer_name' => 'subuh',
                'attendance_status' => 'hadir_berjamaah',
            ])
            ->assertForbidden();

        // Negative: Musyrif Unit A -> Student Unit B (tahfizh/last-log) => 403
        $this->actingAs($musyrifA, 'sanctum')
            ->getJson('/api/musyrif/tahfizh/last-log?student_id='.$studentB->id)
            ->assertForbidden();
    }

    public function test_musyrif_can_perform_operations_on_own_unit_student(): void
    {
        [$unitA] = $this->createUnits();

        $musyrifA = $this->createMusyrif($unitA, 'musyrif-own');
        $studentA = $this->createStudent($unitA, 'A');

        // Positive: Musyrif Unit A -> Student Unit A (worship-attendance) => 200
        $this->actingAs($musyrifA, 'sanctum')
            ->postJson('/api/musyrif/worship-attendance', [
                'student_id' => $studentA->id,
                'prayer_name' => 'magrib',
                'attendance_status' => 'hadir_berjamaah',
            ])
            ->assertOk();

        // Positive: Musyrif Unit A -> Student Unit A (clinic-logs) => 201
        $this->actingAs($musyrifA, 'sanctum')
            ->postJson('/api/musyrif/clinic-logs', [
                'student_id' => $studentA->id,
                'symptoms' => 'Batuk ringan',
                'status' => 'rawat_jalan',
            ])
            ->assertCreated();
    }

    public function test_musyrif_students_list_is_scoped_to_assigned_unit(): void
    {
        [$unitA, $unitB] = $this->createUnits();

        $musyrifA = $this->createMusyrif($unitA, 'musyrif-list');
        $studentA = $this->createStudent($unitA, 'A');
        $studentB = $this->createStudent($unitB, 'B');

        $response = $this->actingAs($musyrifA, 'sanctum')
            ->getJson('/api/musyrif/students')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($studentA->id, $data[0]['id']);
    }

    /** @return array{EducationUnit, EducationUnit} */
    private function createUnits(): array
    {
        return [
            EducationUnit::create(['code' => 'UNIT-PA', 'name' => 'Ponpes Putra', 'level' => 'SMP', 'is_active' => true]),
            EducationUnit::create(['code' => 'UNIT-PI', 'name' => 'Ponpes Putri', 'level' => 'SMP', 'is_active' => true]),
        ];
    }

    private function createMusyrif(EducationUnit $unit, string $slug): User
    {
        $user = User::create([
            'name' => 'Musyrif '.$unit->name,
            'email' => $slug.'@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole('Musyrif');

        Employee::create([
            'niy' => 'NIY-'.$slug,
            'nama_lengkap' => 'Musyrif '.$unit->name,
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);

        return $user;
    }

    private function createStudent(EducationUnit $unit, string $suffix): Student
    {
        return Student::create([
            'full_name' => 'Santri '.$suffix,
            'nisn' => '000000000'.$suffix,
            'nis' => 'SANTRI-'.$suffix,
            'gender' => 'male',
            'unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'aktif',
        ]);
    }
}
