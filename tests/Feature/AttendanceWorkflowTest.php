<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\LmsPresensi;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $teacherUser = User::factory()->create();
        Role::findOrCreate('Guru', 'web');
        $teacherUser->assignRole('Guru');
        $teacher = Teacher::create([
            'user_id' => $teacherUser->id,
            'employee_number' => 'G-001',
            'full_name' => 'Guru Penguji',
        ]);
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => 'Ganjil', 'sequence' => 1,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $year->id, 'semester_id' => $semester->id,
            'homeroom_teacher_id' => $teacher->id, 'name' => 'VII-A', 'level' => '7',
        ]);
        $subject = Subject::create(['code' => 'MTK-1', 'name' => 'Matematika']);
        $schedule = ClassSchedule::create([
            'class_id' => $class->id, 'teacher_id' => $teacher->id,
            'subject_id' => $subject->id, 'academic_year_id' => $year->id,
            'semester_id' => $semester->id, 'day_of_week' => 3,
            'time_start' => '08:00', 'time_end' => '09:00', 'is_active' => true,
        ]);
        $studentUser = User::factory()->create();
        Role::findOrCreate('Siswa', 'web');
        $studentUser->assignRole('Siswa');
        $student = Student::create([
            'user_id' => $studentUser->id, 'class_id' => $class->id,
            'nis' => 'S-001', 'full_name' => 'Siswa Penguji', 'gender' => 'male',
            'is_active' => true,
        ]);

        return compact('teacherUser', 'studentUser', 'schedule', 'student');
    }

    public function test_teacher_can_save_draft_and_finalize_own_schedule(): void
    {
        $ctx = $this->context();
        Sanctum::actingAs($ctx['teacherUser']);
        $payload = [
            'schedule_id' => $ctx['schedule']->id,
            'attendance_date' => '2026-07-29',
            'meeting_number' => 1,
            'items' => [[
                'student_id' => $ctx['student']->id,
                'status' => 'hadir',
            ]],
        ];

        $draft = $this->postJson('/api/lesson-attendances', $payload)
            ->assertCreated()->assertJsonPath('data.status', 'draft');
        $sessionId = $draft->json('data.id');

        $this->postJson("/api/lesson-attendances/{$sessionId}/finalize")
            ->assertOk()->assertJsonPath('data.status', 'final');
        $this->getJson("/api/lesson-attendances/{$sessionId}")->assertOk();
        $this->postJson('/api/lesson-attendances', $payload)->assertStatus(422);
    }

    public function test_logged_in_teacher_sees_only_schedule_active_at_server_time(): void
    {
        $ctx = $this->context();
        Sanctum::actingAs($ctx['teacherUser']);
        Carbon::setTestNow('2026-07-29 08:15:00');

        $this->getJson('/api/lesson-attendance/active-schedules')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-07-29')
            ->assertJsonPath('data.schedules.0.id', $ctx['schedule']->id)
            ->assertJsonPath('data.schedules.0.attendance_access', 'teacher')
            ->assertJsonPath('data.schedules.0.attendance_status', 'not_started');

        Carbon::setTestNow('2026-07-29 10:00:00');
        $this->getJson('/api/lesson-attendance/active-schedules')
            ->assertOk()
            ->assertJsonCount(0, 'data.schedules');

        Carbon::setTestNow();
    }

    public function test_student_only_reads_own_attendance_and_cannot_submit_permission(): void
    {
        $ctx = $this->context();
        Sanctum::actingAs($ctx['studentUser']);

        $this->getJson('/api/student-attendance/me')->assertOk();
        $this->postJson('/api/student-attendance/permissions', [
            'start_date' => '2026-07-29', 'end_date' => '2026-07-30',
            'type' => 'sakit', 'reason' => 'Demam', 'status' => 'submitted',
        ])->assertForbidden();
    }

    public function test_student_portal_reads_only_its_own_lesson_attendance(): void
    {
        $ctx = $this->context();
        LmsPresensi::create([
            'jadwal_pelajaran_id' => $ctx['schedule']->id,
            'siswa_id' => $ctx['student']->id,
            'tanggal' => '2026-07-29',
            'status_hadir' => 'hadir',
        ]);

        Sanctum::actingAs($ctx['studentUser']);

        $this->getJson('/api/portal/attendance')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.siswa_id', $ctx['student']->id)
            ->assertJsonPath('data.data.0.status_hadir', 'hadir');
    }

    public function test_teacher_cannot_use_another_teachers_schedule(): void
    {
        $ctx = $this->context();
        $other = User::factory()->create();
        $other->assignRole('Guru');
        Sanctum::actingAs($other);

        $this->postJson('/api/lesson-attendance/sessions', [
            'schedule_id' => $ctx['schedule']->id,
            'attendance_date' => '2026-07-29',
            'items' => [['student_id' => $ctx['student']->id, 'status' => 'hadir']],
        ])->assertStatus(422);
    }

    public function test_barcode_scan_records_once_and_writes_scan_log(): void
    {
        $ctx = $this->context();
        Sanctum::actingAs($ctx['teacherUser']);
        $draft = $this->postJson('/api/lesson-attendance/sessions', [
            'schedule_id' => $ctx['schedule']->id,
            'attendance_date' => '2026-07-29',
            'items' => [[
                'student_id' => $ctx['student']->id,
                'status' => 'belum_diverifikasi',
            ]],
        ])->assertCreated();
        $id = $draft->json('data.id');

        $this->postJson("/api/lesson-attendance/sessions/{$id}/start-session")
            ->assertOk()->assertJsonStructure(['data' => ['token', 'session']]);
        $this->postJson("/api/lesson-attendance/sessions/{$id}/scan/barcode", [
            'identifier' => 'S-001',
        ])->assertOk()->assertJsonPath('data.scan_status', 'success');
        $this->postJson("/api/lesson-attendance/sessions/{$id}/scan/barcode", [
            'identifier' => 'S-001',
        ])->assertOk()->assertJsonPath('data.scan_status', 'duplicate_scan');

        $this->assertDatabaseHas('lms_presensi', [
            'session_id' => $id, 'siswa_id' => $ctx['student']->id, 'recorded_method' => 'barcode',
        ]);
        $this->assertDatabaseCount('attendance_scan_logs', 2);
    }

    public function test_teacher_can_identify_student_qr_and_rfid_card_for_own_schedule(): void
    {
        $ctx = $this->context();
        $ctx['student']->update(['metadata' => ['card_number' => 'RFID-001', 'rfid_uid' => 'UID-001']]);
        Sanctum::actingAs($ctx['teacherUser']);
        $qrToken = app(AttendanceCaptureService::class)->studentQrToken($ctx['student']);

        $this->postJson('/api/lesson-attendance/identify-card/qr', [
            'schedule_id' => $ctx['schedule']->id,
            'identifier' => $qrToken,
        ])->assertOk()
            ->assertJsonPath('data.student.id', $ctx['student']->id)
            ->assertJsonPath('data.method', 'qr_code');

        $this->postJson('/api/lesson-attendance/identify-card/rfid', [
            'schedule_id' => $ctx['schedule']->id,
            'identifier' => 'UID-001',
        ])->assertOk()
            ->assertJsonPath('data.student.id', $ctx['student']->id)
            ->assertJsonPath('data.method', 'rfid');
    }

    public function test_low_confidence_face_requires_manual_review(): void
    {
        $ctx = $this->context();
        $ctx['student']->update(['metadata' => ['face_template_reference' => 'face-ref-001']]);
        Sanctum::actingAs($ctx['teacherUser']);
        $draft = $this->postJson('/api/lesson-attendance/sessions', [
            'schedule_id' => $ctx['schedule']->id,
            'attendance_date' => '2026-07-29',
            'items' => [['student_id' => $ctx['student']->id, 'status' => 'belum_diverifikasi']],
        ])->assertCreated();
        $id = $draft->json('data.id');
        $this->postJson("/api/lesson-attendance/sessions/{$id}/start-session")->assertOk();
        $this->postJson("/api/lesson-attendance/sessions/{$id}/scan/face", [
            'template_reference' => 'face-ref-001', 'confidence_score' => 40,
        ])->assertOk()->assertJsonPath('data.scan_status', 'low_confidence');
        $this->assertDatabaseHas('attendance_scan_logs', ['result_status' => 'low_confidence']);
    }

    public function test_unregistered_fingerprint_device_is_rejected(): void
    {
        $this->postJson('/api/attendance/devices/events/fingerprint', [
            'session_id' => '00000000-0000-0000-0000-000000000000',
            'template_reference' => 'finger-ref',
        ], ['X-Device-Code' => 'UNKNOWN', 'Authorization' => 'Bearer invalid'])
            ->assertUnauthorized();
    }
}
