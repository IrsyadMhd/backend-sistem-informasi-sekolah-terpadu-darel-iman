<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\QuranSurah;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TahfizhDailyLog;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TahfizhTop5AndMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected EducationUnit $unitA;
    protected EducationUnit $unitB;
    protected AcademicYear $ay;
    protected Semester $sem;
    protected Kelas $kelasA;
    protected Kelas $kelasB;
    protected User $superAdmin;
    protected User $yayasanUser;
    protected User $kepalaSekolahA;
    protected User $guruA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Quran Surah reference
        QuranSurah::firstOrCreate(
            ['nomor' => 78],
            ['nama' => 'النبأ', 'nama_latin' => 'An-Naba\'', 'jumlah_ayat' => 40, 'tempat_turun' => 'Mekah', 'arti' => 'Berita Besar', 'deskripsi' => '']
        );
        QuranSurah::firstOrCreate(
            ['nomor' => 79],
            ['nama' => 'النازعات', 'nama_latin' => 'An-Nazi\'at', 'jumlah_ayat' => 46, 'tempat_turun' => 'Mekah', 'arti' => 'Malaikat Pencabut', 'deskripsi' => '']
        );
        QuranSurah::firstOrCreate(
            ['nomor' => 112],
            ['nama' => 'الإخلاص', 'nama_latin' => 'Al-Ikhlas', 'jumlah_ayat' => 4, 'tempat_turun' => 'Mekah', 'arti' => 'Memurnikan Keesaan Allah', 'deskripsi' => '']
        );

        $this->ay = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->sem = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $this->ay->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        // Unit A
        $this->unitA = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'SMP-IT',
            'name' => 'SMP IT Al-Fityan',
            'level' => 'SMP',
        ]);

        // Unit B
        $this->unitB = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'SMA-IT',
            'name' => 'SMA IT Al-Fityan',
            'level' => 'SMA',
        ]);

        $this->kelasA = Kelas::create([
            'id' => (string) Str::uuid(),
            'unit_pendidikan_id' => $this->unitA->id,
            'tahun_ajaran_id' => $this->ay->id,
            'semester_id' => $this->sem->id,
            'jenjang' => 'SMP',
            'tingkat' => '7',
            'nama_kelas' => '7A Tahfizh',
            'kode_kelas' => '7A-TFZ',
        ]);

        $this->kelasB = Kelas::create([
            'id' => (string) Str::uuid(),
            'unit_pendidikan_id' => $this->unitB->id,
            'tahun_ajaran_id' => $this->ay->id,
            'semester_id' => $this->sem->id,
            'jenjang' => 'SMA',
            'tingkat' => '10',
            'nama_kelas' => '10A Tahfizh',
            'kode_kelas' => '10A-TFZ',
        ]);

        // Users & Roles
        $this->superAdmin = User::factory()->create(['name' => 'Super Administrator']);
        $this->superAdmin->assignRole('Super Admin');

        $this->yayasanUser = User::factory()->create(['name' => 'Pengurus Yayasan']);
        $this->yayasanUser->assignRole('Pengurus Yayasan');

        $this->kepalaSekolahA = User::factory()->create(['name' => 'Kepala SMP']);
        $this->kepalaSekolahA->assignRole('Kepala Sekolah');
        Employee::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->kepalaSekolahA->id,
            'niy' => 'KS-001',
            'nama_lengkap' => 'Kepala SMP',
            'unit_pendidikan_id' => $this->unitA->id,
            'status' => 'aktif',
        ]);

        $this->guruA = User::factory()->create(['name' => 'Ustadz Ali']);
        $this->guruA->assignRole('Guru');
        $empGuru = Employee::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->guruA->id,
            'niy' => 'G-001',
            'nama_lengkap' => 'Ustadz Ali',
            'unit_pendidikan_id' => $this->unitA->id,
            'status' => 'aktif',
        ]);
        Teacher::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->guruA->id,
            'full_name' => 'Ustadz Ali',
            'employee_number' => 'G-001',
        ]);
        $this->kelasA->update(['wali_kelas_id' => $empGuru->id]);
    }

    private function createStudent(EducationUnit $unit, Kelas $kelas, string $name, string $nis): Student
    {
        return Student::create([
            'id' => (string) Str::uuid(),
            'education_unit_id' => $unit->id,
            'unit_id' => $unit->id,
            'kelas_id' => $kelas->id,
            'nis' => $nis,
            'full_name' => $name,
            'gender' => 'male',
            'is_active' => true,
        ]);
    }

    private function createLog(Student $student, int $surah, int $start, int $end, string $type = 'ziyadah', string $status = 'approved', ?string $date = null): TahfizhDailyLog
    {
        return TahfizhDailyLog::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'class_id' => $student->kelas_id,
            'record_date' => $date ?: '2026-10-01',
            'hafalan_surah_number' => $surah,
            'hafalan_surah_name' => 'Surah ' . $surah,
            'hafalan_ayah_start' => $start,
            'hafalan_ayah_end' => $end,
            'status' => $status,
            'type' => $type,
            'metadata' => ['type' => $type],
        ]);
    }

    public function test_top_students_returns_max_5_ordered_by_unique_validated_ayats_with_tie_break(): void
    {
        // Create 6 students in Unit A
        $s1 = $this->createStudent($this->unitA, $this->kelasA, 'Badrul', 'NIS-001'); // 10 ayats
        $s2 = $this->createStudent($this->unitA, $this->kelasA, 'Zaid', 'NIS-002');   // 25 ayats
        $s3 = $this->createStudent($this->unitA, $this->kelasA, 'Ahmad', 'NIS-003');  // 15 ayats (tie with Farhan)
        $s4 = $this->createStudent($this->unitA, $this->kelasA, 'Candra', 'NIS-004'); // 5 ayats
        $s5 = $this->createStudent($this->unitA, $this->kelasA, 'Farhan', 'NIS-005'); // 15 ayats (tie with Ahmad)
        $s6 = $this->createStudent($this->unitA, $this->kelasA, 'Doni', 'NIS-006');   // 2 ayats (should be excluded from top 5)

        $this->createLog($s1, 78, 1, 10);
        $this->createLog($s2, 78, 1, 25);
        $this->createLog($s3, 78, 1, 15);
        $this->createLog($s4, 78, 1, 5);
        $this->createLog($s5, 78, 1, 15);
        $this->createLog($s6, 78, 1, 2);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/tahfizh/top-students?unit_id=' . $this->unitA->id);

        $response->assertOk();
        $data = $response->json('data');

        // Capped at 5
        $this->assertCount(5, $data);

        // Rank 1: Zaid (25 ayats)
        $this->assertEquals($s2->id, $data[0]['student_id']);
        $this->assertEquals(25, $data[0]['total_unique_ayat']);

        // Rank 2 & 3: Tie-break Ahmad vs Farhan -> Ahmad comes first alphabetically
        $this->assertEquals($s3->id, $data[1]['student_id']);
        $this->assertEquals(15, $data[1]['total_unique_ayat']);
        $this->assertEquals($s5->id, $data[2]['student_id']);
        $this->assertEquals(15, $data[2]['total_unique_ayat']);

        // Rank 4: Badrul (10 ayats)
        $this->assertEquals($s1->id, $data[3]['student_id']);
        $this->assertEquals(10, $data[3]['total_unique_ayat']);

        // Rank 5: Candra (5 ayats)
        $this->assertEquals($s4->id, $data[4]['student_id']);
        $this->assertEquals(5, $data[4]['total_unique_ayat']);
    }

    public function test_murajaah_does_not_increase_hafalan_baru(): void
    {
        $student = $this->createStudent($this->unitA, $this->kelasA, 'Hafizh', 'NIS-HFZ');

        // 10 ayats Ziyadah
        $this->createLog($student, 78, 1, 10, 'ziyadah');

        // 30 ayats Murajaah (should not be counted towards new memorization)
        $this->createLog($student, 78, 11, 40, 'murajaah');

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/tahfizh/top-students?unit_id=' . $this->unitA->id);

        $response->assertOk();
        $studentData = collect($response->json('data'))->firstWhere('student_id', $student->id);

        $this->assertNotNull($studentData);
        $this->assertEquals(10, $studentData['total_unique_ayat']);
        $this->assertEquals(2, $studentData['total_setoran']); // Total setoran still counts all records
    }

    public function test_overlapping_ranges_are_merged_without_double_counting(): void
    {
        $student = $this->createStudent($this->unitA, $this->kelasA, 'Taufik', 'NIS-TFK');

        // Setoran 1: Ayah 1 - 10 (10 ayats)
        $this->createLog($student, 78, 1, 10);
        // Setoran 2: Ayah 5 - 15 (overlapping, expands range to 1 - 15)
        $this->createLog($student, 78, 5, 15);
        // Setoran 3: Ayah 20 - 25 (6 ayats)
        $this->createLog($student, 78, 20, 25);

        // Total unique: (15 - 1 + 1) + (25 - 20 + 1) = 15 + 6 = 21 (NOT 10 + 11 + 6 = 27)
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/tahfizh/top-students?unit_id=' . $this->unitA->id);

        $response->assertOk();
        $studentData = collect($response->json('data'))->firstWhere('student_id', $student->id);

        $this->assertEquals(21, $studentData['total_unique_ayat']);
    }

    public function test_unvalidated_logs_are_excluded(): void
    {
        $student = $this->createStudent($this->unitA, $this->kelasA, 'Yusuf', 'NIS-YSF');

        // 10 approved ayats
        $this->createLog($student, 78, 1, 10, 'ziyadah', 'approved');

        // 10 pending/rejected ayats (should not be counted)
        $this->createLog($student, 78, 11, 20, 'ziyadah', 'pending');
        $this->createLog($student, 78, 21, 30, 'ziyadah', 'rejected');

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/tahfizh/top-students?unit_id=' . $this->unitA->id);

        $response->assertOk();
        $studentData = collect($response->json('data'))->firstWhere('student_id', $student->id);

        $this->assertEquals(10, $studentData['total_unique_ayat']);
    }

    public function test_period_filter_affects_rankings_correctly(): void
    {
        $student = $this->createStudent($this->unitA, $this->kelasA, 'Salman', 'NIS-SLM');

        // Log in September 2026
        $this->createLog($student, 78, 1, 10, 'ziyadah', 'approved', '2026-09-15');

        // Log in October 2026
        $this->createLog($student, 78, 11, 20, 'ziyadah', 'approved', '2026-10-05');

        // Filter October only
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/tahfizh/top-students?unit_id=' . $this->unitA->id . '&start_date=2026-10-01&end_date=2026-10-31');

        $response->assertOk();
        $studentData = collect($response->json('data'))->firstWhere('student_id', $student->id);

        $this->assertEquals(10, $studentData['total_unique_ayat']);
    }

    public function test_top_units_aggregates_unique_ayats_and_orders_consistently(): void
    {
        $sA = $this->createStudent($this->unitA, $this->kelasA, 'Santri SMP', 'NIS-SMP');
        $this->createLog($sA, 78, 1, 30); // 30 unique ayats in Unit A

        $sB = $this->createStudent($this->unitB, $this->kelasB, 'Santri SMA', 'NIS-SMA');
        $this->createLog($sB, 78, 1, 15); // 15 unique ayats in Unit B

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/tahfizh/top-units');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertEquals($this->unitA->id, $data[0]['unit_id']);
        $this->assertEquals(30, $data[0]['total_unique_ayat']);
        $this->assertEquals($this->unitB->id, $data[1]['unit_id']);
        $this->assertEquals(15, $data[1]['total_unique_ayat']);
    }

    public function test_kepala_sekolah_forbidden_to_access_foreign_unit(): void
    {
        // Kepala SMP tries to request Unit B (SMA)
        $response = $this->actingAs($this->kepalaSekolahA, 'sanctum')
            ->getJson('/api/tahfizh/top-students?unit_id=' . $this->unitB->id);

        $response->assertStatus(403);
    }

    public function test_guru_scope_restricted_to_assigned_students(): void
    {
        // Student in Guru's class
        $myStudent = $this->createStudent($this->unitA, $this->kelasA, 'Binaan Guru', 'NIS-BNG');
        $this->createLog($myStudent, 78, 1, 10);

        // Student in another class
        $kelasOther = Kelas::create([
            'id' => (string) Str::uuid(),
            'unit_pendidikan_id' => $this->unitA->id,
            'tahun_ajaran_id' => $this->ay->id,
            'semester_id' => $this->sem->id,
            'jenjang' => 'SMP',
            'tingkat' => '7',
            'nama_kelas' => '7B Tahfizh',
            'kode_kelas' => '7B-TFZ',
        ]);
        $otherStudent = $this->createStudent($this->unitA, $kelasOther, 'Lain Binaan', 'NIS-LNB');
        $this->createLog($otherStudent, 78, 1, 20);

        $response = $this->actingAs($this->guruA, 'sanctum')
            ->getJson('/api/tahfizh/top-students?unit_id=' . $this->unitA->id);

        $response->assertOk();
        $data = $response->json('data');

        $studentIds = collect($data)->pluck('student_id')->all();
        $this->assertContains($myStudent->id, $studentIds);
        $this->assertNotContains($otherStudent->id, $studentIds);
    }

    public function test_monitoring_overview_endpoint_returns_scoped_aggregates(): void
    {
        $student = $this->createStudent($this->unitA, $this->kelasA, 'Santri Mon', 'NIS-MON');
        $this->createLog($student, 78, 1, 20);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/tahfizh/monitoring');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('top_students', $data);
        $this->assertArrayHasKey('top_units', $data);
        $this->assertEquals(20, $data['summary']['total_unique_ayat']);
    }
}
