<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LessonAttendanceSession;
use App\Models\QrCredential;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\AttendancePermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class Step04TeachingAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AttendancePermissionSeeder::class);
    }

    public function test_teacher_qr_creates_idempotent_attendance_and_ready_session(): void
    {
        [$user, $schedule, $rawToken] = $this->teacherContext();

        $first = $this->actingAs($user, 'sanctum')->postJson('/api/teacher/teaching-attendance/scan', [
            'schedule_id' => $schedule->id,
            'qr_token' => $rawToken,
        ]);

        $first->assertOk()
            ->assertJsonPath('data.scan_status', 'success')
            ->assertJsonPath('data.attendance.status', 'hadir')
            ->assertJsonPath('data.session.teaching_session_status', 'ready');

        $this->assertDatabaseCount('teaching_attendances', 1);
        $this->assertDatabaseMissing('teaching_attendances', ['metadata->raw_token' => $rawToken]);

        $second = $this->actingAs($user, 'sanctum')->postJson('/api/teacher/teaching-attendance/scan', [
            'schedule_id' => $schedule->id,
            'qr_token' => $rawToken,
        ]);

        $second->assertOk()->assertJsonPath('data.scan_status', 'duplicate');
        $this->assertDatabaseCount('teaching_attendances', 1);
        $this->assertDatabaseCount('lesson_attendance_sessions', 1);
    }

    public function test_invalid_or_other_teacher_qr_is_rejected(): void
    {
        [$user, $schedule, $rawToken] = $this->teacherContext();
        $otherUser = User::factory()->create();
        $otherUser->assignRole('Guru');
        $otherEmployee = Employee::create([
            'niy' => 'OTHER-'.Str::upper(Str::random(8)),
            'nama_lengkap' => 'Guru Lain',
            'unit_id' => $schedule->employee->unit_id,
            'user_id' => $otherUser->id,
            'status' => 'Aktif',
        ]);
        $otherToken = 'other-'.Str::uuid();
        QrCredential::create([
            'user_id' => $otherUser->id,
            'employee_id' => $otherEmployee->id,
            'card_type' => 'employee_card',
            'token_hash' => hash('sha256', $otherToken),
            'status' => 'active',
            'issued_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/teacher/teaching-attendance/scan', ['schedule_id' => $schedule->id, 'qr_token' => 'invalid'])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/teacher/teaching-attendance/scan', ['schedule_id' => $schedule->id, 'qr_token' => $otherToken])
            ->assertStatus(422);

        $otherUnit = EducationUnit::factory()->create();
        $schedule->kelas->update(['unit_pendidikan_id' => $otherUnit->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/teacher/teaching-attendance/scan', ['schedule_id' => $schedule->id, 'qr_token' => $rawToken])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedule_id');
    }

    public function test_scan_outside_schedule_time_window_is_rejected(): void
    {
        [$user, $schedule, $rawToken] = $this->teacherContext();
        $now = now();
        $start = $now->copy()->addHours(2);
        if (! $start->isSameDay($now) || $start->hour >= 23) {
            $start = $now->copy()->subHours(3);
        }
        $schedule->update([
            'time_start' => $start->format('H:i:00'),
            'time_end' => $start->copy()->addMinutes(10)->format('H:i:00'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/teacher/teaching-attendance/scan', ['schedule_id' => $schedule->id, 'qr_token' => $rawToken])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedule_id');
    }

    public function test_teacher_can_start_and_complete_own_session_only_after_qr_attendance(): void
    {
        [$user, $schedule, $rawToken] = $this->teacherContext();
        $this->actingAs($user, 'sanctum')->postJson('/api/teacher/teaching-attendance/scan', [
            'schedule_id' => $schedule->id,
            'qr_token' => $rawToken,
        ])->assertOk();

        $session = LessonAttendanceSession::query()->firstOrFail();
        $started = $this->actingAs($user, 'sanctum')->postJson("/api/teacher/teaching-sessions/{$session->id}/start");
        $started->assertOk()->assertJsonPath('data.teaching_session_status', 'active');

        $closed = $this->actingAs($user, 'sanctum')->postJson("/api/teacher/teaching-sessions/{$session->id}/close");
        $closed->assertOk()->assertJsonPath('data.teaching_session_status', 'completed');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/teacher/teaching-sessions/{$session->id}/start")
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');
    }

    public function test_recent_heartbeat_is_separate_from_attendance(): void
    {
        [$user, $schedule] = $this->teacherContext();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/teacher/presence/heartbeat', ['device_id' => 'step04-test-browser'])
            ->assertOk()
            ->assertJsonPath('data.device_id', 'step04-test-browser');

        $this->assertDatabaseCount('teaching_attendances', 0);
        $this->assertDatabaseHas('user_devices', ['user_id' => $user->id, 'device_id' => 'step04-test-browser']);
    }

    public function test_monitoring_is_scoped_and_guru_cannot_open_executive_monitoring(): void
    {
        [$teacherUser, $schedule, $rawToken] = $this->teacherContext();
        $this->actingAs($teacherUser, 'sanctum')->postJson('/api/teacher/teaching-attendance/scan', [
            'schedule_id' => $schedule->id,
            'qr_token' => $rawToken,
        ])->assertOk();

        $monitorUser = User::factory()->create();
        $monitorUser->assignRole('Kepala Sekolah');
        Employee::create([
            'niy' => 'MONITOR-'.Str::upper(Str::random(8)),
            'nama_lengkap' => 'Kepala Sekolah Uji',
            'unit_id' => $schedule->employee->unit_id,
            'user_id' => $monitorUser->id,
            'status' => 'Aktif',
        ]);

        $this->actingAs($monitorUser, 'sanctum')
            ->getJson('/api/teacher-monitoring')
            ->assertOk()
            ->assertJsonPath('data.summary.checked_in', 1)
            ->assertJsonPath('data.rows.0.attendance_status', 'hadir');

        $this->actingAs($teacherUser, 'sanctum')
            ->getJson('/api/teacher-monitoring')
            ->assertForbidden();
    }

    private function teacherContext(): array
    {
        $now = now();
        $unit = EducationUnit::factory()->create();
        $academicYear = AcademicYear::query()->create([
            'name' => 'Step04/'.Str::random(6),
            'start_date' => $now->copy()->subMonth()->toDateString(),
            'end_date' => $now->copy()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => $now->copy()->subMonth()->toDateString(),
            'end_date' => $now->copy()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $employee = Employee::create([
            'niy' => 'TEST-'.Str::upper(Str::random(8)),
            'nama_lengkap' => 'Guru Step 04',
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);
        Teacher::create([
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'employee_number' => 'TCH-'.Str::upper(Str::random(8)),
            'full_name' => 'Guru Step 04',
        ]);
        $kelas = Kelas::query()->create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'jenjang' => 'SD',
            'tingkat' => '1',
            'kode_kelas' => 'STEP04-'.Str::upper(Str::random(5)),
            'nama_kelas' => 'Step 04',
            'kapasitas' => 30,
            'status' => 'Aktif',
        ]);
        $subject = Subject::query()->create([
            'code' => 'STEP04-'.Str::upper(Str::random(5)),
            'name' => 'Mapel Step 04',
        ]);
        $schedule = ClassSchedule::query()->create([
            'kelas_id' => $kelas->id,
            'employee_id' => $employee->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'day_of_week' => $now->dayOfWeekIso,
            'time_start' => $now->copy()->subMinutes(5)->format('H:i:00'),
            'time_end' => $now->copy()->addMinutes(30)->format('H:i:00'),
            'is_active' => true,
        ]);
        $rawToken = 'step04-'.Str::uuid();
        QrCredential::query()->create([
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'card_type' => 'employee_card',
            'token_hash' => hash('sha256', $rawToken),
            'status' => 'active',
            'issued_at' => $now,
        ]);

        return [$user, $schedule->fresh(['employee']), $rawToken];
    }
}
