<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\PrayerAssessmentItem;
use App\Models\PrayerGradeRule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrayerAssessmentRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $academicYear = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'semester' => '1',
            'is_active' => true,
        ]);
    }

    private function createUnit(): EducationUnit
    {
        return EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-DOA-'.rand(100, 999),
            'nama' => 'SD Islam Dar El-Iman',
            'name' => 'SD Islam Dar El-Iman',
            'is_active' => true,
        ]);
    }

    private function createStudent(EducationUnit $unit, string $name = 'Siswa Doa'): Student
    {
        return Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nis' => 'DOA-'.rand(1000, 9999),
            'full_name' => $name,
            'gender' => 'L',
            'is_active' => true,
        ]);
    }

    private function createTeacher(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        return $user;
    }

    public function test_prayer_assessment_different_passing_scores_per_item(): void
    {
        $teacher = $this->createTeacher();
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);

        // Item 1: passing_score = 70.0
        $item1 = PrayerAssessmentItem::create([
            'id' => (string) Str::uuid(),
            'order_number' => 1,
            'name' => 'Doa Sebelum Belajar',
            'group' => 'Doa Doa Harian',
            'max_score' => 100,
            'passing_score' => 70.00,
            'is_active' => true,
        ]);

        // Item 2: passing_score = 80.0
        $item2 = PrayerAssessmentItem::create([
            'id' => (string) Str::uuid(),
            'order_number' => 2,
            'name' => 'Doa Masuk Masjid',
            'group' => 'Doa Doa Harian',
            'max_score' => 100,
            'passing_score' => 80.00,
            'is_active' => true,
        ]);

        // Simpan nilai 75 untuk kedua item
        $saveResponse = $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/prayer-assessment/student/{$student->id}/save", [
                'scores' => [
                    [
                        'prayer_item_id' => $item1->id,
                        'score' => 75.0,
                    ],
                    [
                        'prayer_item_id' => $item2->id,
                        'score' => 75.0,
                    ],
                ],
            ]);

        $saveResponse->assertOk();

        // Ambil student sheet
        $sheetResponse = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/prayer-assessment/student/{$student->id}");

        $sheetResponse->assertOk()
            ->assertJsonPath('summary.total_items', 2)
            ->assertJsonPath('summary.tested_items', 2)
            ->assertJsonPath('summary.passed_items', 1);

        $items = collect($sheetResponse->json('items'))->keyBy('id');

        // Item 1: score 75 >= 70 -> LULUS
        $this->assertEquals(70.0, (float) $items->get($item1->id)['passing_score']);
        $this->assertEquals(75.0, (float) $items->get($item1->id)['poin']);
        $this->assertTrue($items->get($item1->id)['is_passed']);

        // Item 2: score 75 < 80 -> TIDAK LULUS
        $this->assertEquals(80.0, (float) $items->get($item2->id)['passing_score']);
        $this->assertEquals(75.0, (float) $items->get($item2->id)['poin']);
        $this->assertFalse($items->get($item2->id)['is_passed']);
    }

    public function test_prayer_assessment_student_untested_partially_tested_fully_tested(): void
    {
        $teacher = $this->createTeacher();
        $unit = $this->createUnit();

        // Buat 3 butir doa
        $item1 = PrayerAssessmentItem::create([
            'id' => (string) Str::uuid(),
            'order_number' => 1,
            'name' => 'Doa Bangun Tidur',
            'max_score' => 100,
            'passing_score' => 75.00,
            'is_active' => true,
        ]);
        $item2 = PrayerAssessmentItem::create([
            'id' => (string) Str::uuid(),
            'order_number' => 2,
            'name' => 'Doa Masuk Kamar Mandi',
            'max_score' => 100,
            'passing_score' => 75.00,
            'is_active' => true,
        ]);
        $item3 = PrayerAssessmentItem::create([
            'id' => (string) Str::uuid(),
            'order_number' => 3,
            'name' => 'Doa Memakai Pakaian',
            'max_score' => 100,
            'passing_score' => 75.00,
            'is_active' => true,
        ]);

        // Kondisi 1: Siswa belum dinilai sama sekali
        $studentUntested = $this->createStudent($unit, 'Siswa Belum Dinilai');
        $respUntested = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/prayer-assessment/student/{$studentUntested->id}");

        $respUntested->assertOk()
            ->assertJsonPath('summary.total_items', 3)
            ->assertJsonPath('summary.tested_items', 0)
            ->assertJsonPath('summary.passed_items', 0)
            ->assertJsonPath('summary.average_score', 0);

        // Kondisi 2: Siswa dinilai sebagian (1 dari 3 doa dinilai dengan nilai 90)
        $studentPartial = $this->createStudent($unit, 'Siswa Dinilai Sebagian');
        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/prayer-assessment/student/{$studentPartial->id}/save", [
                'scores' => [
                    ['prayer_item_id' => $item1->id, 'score' => 90.0],
                ],
            ]);

        $respPartial = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/prayer-assessment/student/{$studentPartial->id}");

        $respPartial->assertOk()
            ->assertJsonPath('summary.total_items', 3)
            ->assertJsonPath('summary.tested_items', 1)
            ->assertJsonPath('summary.passed_items', 1);
        $this->assertEquals(90.0, (float) $respPartial->json('summary.average_score'));

        // Kondisi 3: Siswa dinilai penuh (semua 3 doa: 80, 85, 90)
        $studentFull = $this->createStudent($unit, 'Siswa Dinilai Penuh');
        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/prayer-assessment/student/{$studentFull->id}/save", [
                'scores' => [
                    ['prayer_item_id' => $item1->id, 'score' => 80.0],
                    ['prayer_item_id' => $item2->id, 'score' => 85.0],
                    ['prayer_item_id' => $item3->id, 'score' => 90.0],
                ],
            ]);

        $respFull = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/prayer-assessment/student/{$studentFull->id}");

        $respFull->assertOk()
            ->assertJsonPath('summary.total_items', 3)
            ->assertJsonPath('summary.tested_items', 3)
            ->assertJsonPath('summary.passed_items', 3);
        $this->assertEquals(85.0, (float) $respFull->json('summary.average_score'));
    }

    public function test_prayer_assessment_empty_master_items_handles_zero_denominator_gracefully(): void
    {
        $teacher = $this->createTeacher();
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);

        // Pastikan tidak ada master target doa
        PrayerAssessmentItem::query()->delete();

        $sheetResponse = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/prayer-assessment/student/{$student->id}");

        $sheetResponse->assertOk()
            ->assertJsonPath('summary.total_items', 0)
            ->assertJsonPath('summary.tested_items', 0)
            ->assertJsonPath('summary.passed_items', 0)
            ->assertJsonPath('summary.average_score', 0);
        $this->assertCount(0, $sheetResponse->json('items'));
    }
}
