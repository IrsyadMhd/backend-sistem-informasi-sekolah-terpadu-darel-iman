<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GateAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'AttendancePermissionSeeder']);
    }

    private function createUnit(): EducationUnit
    {
        $name = 'Unit SD Test '.Str::upper(Str::random(5));

        return EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-TEST-'.rand(100, 999),
            'nama' => $name,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createStudent(EducationUnit $unit): Student
    {
        return Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nisn' => '999'.rand(10000, 99999),
            'nis' => '100'.rand(1000, 9999),
            'nama_lengkap' => 'Siswa Test Gate',
            'full_name' => 'Siswa Test Gate',
            'gender' => 'L',
            'jenis_kelamin' => 'L',
            'is_active' => true,
        ]);
    }

    public function test_gate_checkin_scan_creates_attendance_record(): void
    {
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', [
                'student_id' => $student->id,
                'check_in_time' => '07:05:00',
                'attendance_method' => 'QRCODE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'HADIR');

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'status' => 'HADIR',
        ]);
    }

    public function test_late_checkin_sets_terlambat_status(): void
    {
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', [
                'student_id' => $student->id,
                'check_in_time' => '07:35:00',
                'attendance_method' => 'RFID',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'TERLAMBAT');
    }

    public function test_duplicate_checkin_is_rejected(): void
    {
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // First scan
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', [
                'student_id' => $student->id,
                'check_in_time' => '07:00:00',
                'attendance_method' => 'QRCODE',
            ]);

        // Second scan on same day
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', [
                'student_id' => $student->id,
                'check_in_time' => '07:05:00',
                'attendance_method' => 'QRCODE',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_CHECKIN');
    }

    public function test_gate_checkout_updates_record(): void
    {
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // Initial checkin
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', [
                'student_id' => $student->id,
                'check_in_time' => '07:00:00',
            ]);

        // Checkout
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-out', [
                'student_id' => $student->id,
                'check_out_time' => '14:20:00',
                'attendance_method' => 'QRCODE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'check_out_status' => 'pulang_normal',
        ]);
    }

    public function test_gate_checkout_before_checkin_time_is_rejected(): void
    {
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', [
                'student_id' => $student->id,
                'check_in_time' => '07:30:00',
            ])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-out', [
                'student_id' => $student->id,
                'check_out_time' => '07:15:00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CHECKOUT_BEFORE_CHECKIN');
    }

    public function test_gate_scan_is_restricted_to_employee_unit_scope(): void
    {
        $terminalUnit = $this->createUnit();
        $foreignUnit = $this->createUnit();
        $student = $this->createStudent($foreignUnit);
        $user = User::factory()->create();
        $user->assignRole('Tata Usaha');
        Employee::create([
            'niy' => 'TU-'.rand(10000, 99999),
            'nama_lengkap' => 'Petugas Gate Unit',
            'unit_id' => $terminalUnit->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', ['student_id' => $student->id])
            ->assertForbidden();
    }

    public function test_gate_stats_counts_students_from_unit_id(): void
    {
        $unit = $this->createUnit();
        $this->createStudent($unit);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/gate-attendance/stats?unit_id='.$unit->id)
            ->assertOk()
            ->assertJsonPath('data.total_siswa', 1);
    }

    public function test_gate_stats_returns_zero_scanned_when_no_records_exist(): void
    {
        $unit = $this->createUnit();
        $this->createStudent($unit);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/gate-attendance/stats?unit_id='.$unit->id)
            ->assertOk();

        $response->assertJsonPath('data.total_siswa', 1)
            ->assertJsonPath('data.total_scanned', 0)
            ->assertJsonPath('data.total_data', 0)
            ->assertJsonPath('data.hadir', 0)
            ->assertJsonPath('data.terlambat', 0)
            ->assertJsonPath('data.izin', 0)
            ->assertJsonPath('data.alpha', 0)
            ->assertJsonPath('data.alpa', 0)
            ->assertJsonPath('data.persentase_kehadiran', 0);
    }

    public function test_gate_stats_filters_by_date_range_and_calculates_synonyms(): void
    {
        $unit = $this->createUnit();
        $student1 = $this->createStudent($unit);
        $student2 = $this->createStudent($unit);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // Hari 1: Student 1 HADIR_DALAM_TOLERANSI, Student 2 ALPA
        Attendance::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student1->id,
            'attendance_date' => '2026-10-01',
            'status' => 'HADIR_DALAM_TOLERANSI',
            'attendance_method' => 'QRCODE',
        ]);
        Attendance::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student2->id,
            'attendance_date' => '2026-10-01',
            'status' => 'ALPA',
            'attendance_method' => 'MANUAL',
        ]);

        // Hari 2 (di luar rentang query): Student 1 TERLAMBAT
        Attendance::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student1->id,
            'attendance_date' => '2026-10-05',
            'status' => 'TERLAMBAT',
            'attendance_method' => 'RFID',
        ]);

        // Request query dengan rentang 2026-10-01 s.d 2026-10-02
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/gate-attendance/stats?unit_id='.$unit->id.'&date_from=2026-10-01&date_to=2026-10-02')
            ->assertOk();

        $response->assertJsonPath('data.total_scanned', 2)
            ->assertJsonPath('data.hadir', 1)
            ->assertJsonPath('data.alpha', 1)
            ->assertJsonPath('data.alpa', 1)
            ->assertJsonPath('data.terlambat', 0);
        $this->assertEquals(50.0, (float) $response->json('data.persentase_kehadiran'));
    }

    public function test_gate_stats_ignores_unknown_status_and_maintains_category_sum(): void
    {
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $statuses = ['HADIR', 'TERLAMBAT', 'IZIN', 'SAKIT', 'ALPHA', 'INVALID_STATUS_CODE'];
        foreach ($statuses as $st) {
            Attendance::create([
                'id' => (string) Str::uuid(),
                'unit_pendidikan_id' => $unit->id,
                'student_id' => $student->id,
                'attendance_date' => today()->toDateString(),
                'status' => $st,
                'attendance_method' => 'RFID',
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/gate-attendance/stats?unit_id='.$unit->id)
            ->assertOk();

        // 6 total log riil
        $response->assertJsonPath('data.total_scanned', 6)
            ->assertJsonPath('data.hadir', 1)
            ->assertJsonPath('data.terlambat', 1)
            ->assertJsonPath('data.izin', 1)
            ->assertJsonPath('data.sakit', 1)
            ->assertJsonPath('data.alpha', 1)
            ->assertJsonPath('data.alpa', 1);

        // Kehadiran: 1 dari 6 = 16.7%
        $this->assertEquals(16.7, (float) $response->json('data.persentase_kehadiran'));
    }

    public function test_gate_stats_strictly_distinguishes_student_population_from_scanned_logs(): void
    {
        $unit = $this->createUnit();
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // Buat 10 siswa aktif
        $students = [];
        for ($i = 1; $i <= 10; $i++) {
            $students[] = $this->createStudent($unit);
        }

        // Hanya 2 siswa yang scan masuk hari ini
        Attendance::create([
            'id' => (string) Str::uuid(),
            'unit_pendidikan_id' => $unit->id,
            'student_id' => $students[0]->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'HADIR',
            'attendance_method' => 'QRCODE',
        ]);
        Attendance::create([
            'id' => (string) Str::uuid(),
            'unit_pendidikan_id' => $unit->id,
            'student_id' => $students[1]->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'TERLAMBAT',
            'attendance_method' => 'QRCODE',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/gate-attendance/stats?unit_id='.$unit->id)
            ->assertOk();

        // total_siswa HARUS 10 (populasi master), total_scanned HARUS 2 (log aktual)
        $response->assertJsonPath('data.total_siswa', 10)
            ->assertJsonPath('data.total_scanned', 2)
            ->assertJsonPath('data.total_data', 2)
            ->assertJsonPath('data.hadir', 1)
            ->assertJsonPath('data.terlambat', 1)
            ->assertJsonPath('data.alpha', 0) // Dilarang phantom alpha 8 siswa!
            ->assertJsonPath('data.alpa', 0);

        // Persentase kehadiran: 1 hadir dari 2 scan log = 50.0%
        $this->assertEquals(50.0, (float) $response->json('data.persentase_kehadiran'));
    }

    public function test_student_cannot_bypass_gate_permission_with_direct_request(): void
    {
        $unit = $this->createUnit();
        $student = $this->createStudent($unit);
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', ['student_id' => $student->id])
            ->assertForbidden();
    }
}

