<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use Database\Seeders\SuperAdminAcceptanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAcceptanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_seeder_is_disabled_outside_development_environments(): void
    {
        $this->app->instance('env', 'production');

        app(SuperAdminAcceptanceSeeder::class)->run();

        $this->assertDatabaseCount('education_units', 0);
        $this->assertDatabaseCount('users', 0);

        $this->app->instance('env', 'testing');
    }

    public function test_acceptance_seeder_is_idempotent_and_builds_master_graph(): void
    {
        $this->seed(SuperAdminAcceptanceSeeder::class);

        $counts = [
            'units' => EducationUnit::count(),
            'years' => AcademicYear::count(),
            'semesters' => Semester::count(),
            'employees' => Employee::count(),
            'classes' => Kelas::count(),
            'subjects' => Subject::count(),
            'schedules' => ClassSchedule::count(),
            'parents' => ParentModel::count(),
            'students' => Student::count(),
        ];

        $this->assertGreaterThanOrEqual(2, $counts['units']);
        $this->assertGreaterThanOrEqual(1, $counts['years']);
        $this->assertGreaterThanOrEqual(1, $counts['semesters']);
        $this->assertGreaterThanOrEqual(2, $counts['classes']);
        $this->assertGreaterThanOrEqual(2, $counts['subjects']);
        $this->assertGreaterThanOrEqual(2, $counts['schedules']);
        $this->assertGreaterThanOrEqual(2, $counts['parents']);
        $this->assertGreaterThanOrEqual(3, $counts['students']);

        $this->seed(SuperAdminAcceptanceSeeder::class);

        $this->assertSame($counts, [
            'units' => EducationUnit::count(),
            'years' => AcademicYear::count(),
            'semesters' => Semester::count(),
            'employees' => Employee::count(),
            'classes' => Kelas::count(),
            'subjects' => Subject::count(),
            'schedules' => ClassSchedule::count(),
            'parents' => ParentModel::count(),
            'students' => Student::count(),
        ]);
    }
}
