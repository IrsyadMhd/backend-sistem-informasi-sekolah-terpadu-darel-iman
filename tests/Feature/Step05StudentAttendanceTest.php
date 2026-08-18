<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\ParentModel;
use App\Models\QrCredential;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AttendanceCaptureService;
use Database\Seeders\AttendancePermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Step05StudentAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AttendancePermissionSeeder::class);
    }

    public function test_student_qr_is_stable_and_gate_requires_valid_in_out_sequence(): void
    {
        $ctx = $this->context();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $firstToken = app(AttendanceCaptureService::class)->studentQrToken($ctx['students'][0]);
        $secondToken = app(AttendanceCaptureService::class)->studentQrToken($ctx['students'][0]);

        $this->assertSame($firstToken, $secondToken);
        $this->assertStringStartsWith('stuqr:v1:', $firstToken);
        $this->assertStringNotContainsString($ctx['students'][0]->id, $firstToken);
        $this->assertSame(1, QrCredential::query()
            ->where('student_id', $ctx['students'][0]->id)
            ->where('status', 'active')
            ->count());

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', [
                'qr_token' => $firstToken,
                'unit_id' => $ctx['unit']->id,
                'check_in_time' => '07:05:00',
                'attendance_method' => 'QRCODE',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student.id', $ctx['students'][0]->id);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/gate-attendance/scan-in', [
                'qr_token' => $firstToken,
                'unit_id' => $ctx['unit']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_CHECKIN');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/gate-attendance/scan-out', [
                'qr_token' => $firstToken,
                'unit_id' => $ctx['unit']->id,
                'check_out_time' => '14:20:00',
                'attendance_method' => 'QRCODE',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/gate-attendance/scan-out', [
                'qr_token' => $firstToken,
                'unit_id' => $ctx['unit']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DUPLICATE_CHECKOUT');

        $this->assertDatabaseHas('attendances', [
            'student_id' => $ctx['students'][0]->id,
            'check_out_status' => 'pulang_normal',
        ]);
    }

    public function test_lesson_session_creates_complete_roster_and_blocks_unmarked_finalization(): void
    {
        $ctx = $this->context();
        $teacher = $ctx['teacher'];
        $items = [[
            'student_id' => $ctx['students'][0]->id,
            'status' => 'hadir',
        ]];

        $draft = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/lesson-attendances', [
                'schedule_id' => $ctx['schedule']->id,
                'attendance_date' => today()->toDateString(),
                'items' => $items,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
        $sessionId = $draft->json('data.id');

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/lesson-attendance/my-schedules/'.$ctx['schedule']->id.'/students?date='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId);

        $this->assertDatabaseCount('lms_presensi', 2);
        $this->assertDatabaseHas('lms_presensi', [
            'session_id' => $sessionId,
            'siswa_id' => $ctx['students'][1]->id,
            'status_hadir' => 'belum_diverifikasi',
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendances/{$sessionId}/finalize")
            ->assertStatus(422);

        $this->actingAs($teacher, 'sanctum')
            ->putJson("/api/lesson-attendances/{$sessionId}", [
                'items' => [[
                    'student_id' => $ctx['students'][1]->id,
                    'status' => 'izin',
                ]],
            ])
            ->assertCreated();

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendances/{$sessionId}/finalize")
            ->assertOk()
            ->assertJsonPath('data.status', 'final');
    }

    public function test_lesson_qr_scan_uses_credential_roster_and_duplicate_guard(): void
    {
        $ctx = $this->context();
        $teacher = $ctx['teacher'];
        $draft = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/lesson-attendances', [
                'schedule_id' => $ctx['schedule']->id,
                'attendance_date' => today()->toDateString(),
                'items' => collect($ctx['students'])->map(fn (Student $student) => [
                    'student_id' => $student->id,
                    'status' => 'belum_diverifikasi',
                ])->all(),
            ])
            ->assertCreated();
        $sessionId = $draft->json('data.id');

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendance/sessions/{$sessionId}/start-session")
            ->assertOk();

        $token = app(AttendanceCaptureService::class)->studentQrToken($ctx['students'][0]);
        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendance/sessions/{$sessionId}/scan/qr", ['identifier' => $token])
            ->assertOk()
            ->assertJsonPath('data.scan_status', 'success')
            ->assertJsonPath('data.student.id', $ctx['students'][0]->id);

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendance/sessions/{$sessionId}/scan/qr", ['identifier' => $token])
            ->assertOk()
            ->assertJsonPath('data.scan_status', 'duplicate_scan');

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendance/sessions/{$sessionId}/close-session")
            ->assertOk();

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendance/sessions/{$sessionId}/scan/qr", ['identifier' => $ctx['students'][1]->nis])
            ->assertStatus(422);

        $this->assertDatabaseHas('lms_presensi', [
            'session_id' => $sessionId,
            'siswa_id' => $ctx['students'][0]->id,
            'recorded_method' => 'qr_code',
        ]);
    }

    public function test_lesson_qr_scan_restores_soft_deleted_roster_row(): void
    {
        $ctx = $this->context();
        $teacher = $ctx['teacher'];
        $draft = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/lesson-attendances', [
                'schedule_id' => $ctx['schedule']->id,
                'attendance_date' => today()->toDateString(),
                'items' => collect($ctx['students'])->map(fn (Student $student) => [
                    'student_id' => $student->id,
                    'status' => 'belum_diverifikasi',
                ])->all(),
            ])
            ->assertCreated();
        $sessionId = $draft->json('data.id');
        $attendance = LmsPresensi::query()
            ->where('session_id', $sessionId)
            ->where('siswa_id', $ctx['students'][0]->id)
            ->firstOrFail();
        $attendance->delete();

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendance/sessions/{$sessionId}/start-session")
            ->assertOk();

        $token = app(AttendanceCaptureService::class)->studentQrToken($ctx['students'][0]);
        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendance/sessions/{$sessionId}/scan/qr", ['identifier' => $token])
            ->assertOk()
            ->assertJsonPath('data.scan_status', 'success');

        $this->assertNull(LmsPresensi::withTrashed()->findOrFail($attendance->id)->deleted_at);
    }

    public function test_parent_student_qr_endpoint_is_child_scoped_and_opaque(): void
    {
        $parentUser = User::factory()->create();
        $parentUser->assignRole('Orang Tua');
        $parent = ParentModel::create(['user_id' => $parentUser->id, 'full_name' => 'Wali Step 05']);
        $child = Student::create([
            'parent_id' => $parent->id,
            'nis' => 'PORTAL-05',
            'full_name' => 'Anak Step 05',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $foreign = Student::create([
            'nis' => 'FOREIGN-05',
            'full_name' => 'Anak Asing Step 05',
            'gender' => 'female',
            'is_active' => true,
        ]);

        $response = $this->actingAs($parentUser, 'sanctum')
            ->getJson('/api/portal/attendance-qr?child_id='.$child->id)
            ->assertOk()
            ->assertJsonPath('data.student_id', $child->id);
        $token = $response->json('data.qr_token');

        $this->assertStringStartsWith('stuqr:v1:', $token);
        $this->assertStringNotContainsString($child->id, $token);
        $this->assertStringNotContainsString($child->nis, $token);
        $this->assertStringNotContainsString($child->full_name, $token);

        $this->actingAs($parentUser, 'sanctum')
            ->getJson('/api/portal/attendance-qr?child_id='.$foreign->id)
            ->assertNotFound();
    }

    public function test_lesson_capture_and_finalize_require_active_step04_session_state(): void
    {
        $ctx = $this->context();
        $teacher = $ctx['teacher'];
        $draft = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/lesson-attendances', [
                'schedule_id' => $ctx['schedule']->id,
                'attendance_date' => today()->toDateString(),
                'items' => collect($ctx['students'])->map(fn (Student $student) => [
                    'student_id' => $student->id,
                    'status' => 'hadir',
                ])->all(),
            ])
            ->assertCreated();
        $sessionId = $draft->json('data.id');

        LessonAttendanceSession::query()->findOrFail($sessionId)->update(['teaching_session_status' => 'ready']);

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendance/sessions/{$sessionId}/start-session")
            ->assertStatus(422);

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/lesson-attendances/{$sessionId}/finalize")
            ->assertStatus(422);
    }

    private function context(): array
    {
        $now = now();
        $unit = EducationUnit::factory()->create();
        $year = AcademicYear::create([
            'name' => 'Step05/'.Str::upper(Str::random(6)),
            'start_date' => $now->copy()->subMonth()->toDateString(),
            'end_date' => $now->copy()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => $now->copy()->subMonth()->toDateString(),
            'end_date' => $now->copy()->addMonth()->toDateString(),
            'is_active' => true,
        ]);
        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');
        $employee = Employee::create([
            'niy' => 'STEP05-'.Str::upper(Str::random(8)),
            'nama_lengkap' => 'Guru Step 05',
            'unit_id' => $unit->id,
            'user_id' => $teacher->id,
            'status' => 'Aktif',
        ]);
        $kelas = Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $year->id,
            'semester_id' => $semester->id,
            'jenjang' => 'SD',
            'tingkat' => '1',
            'kode_kelas' => 'STEP05-'.Str::upper(Str::random(5)),
            'nama_kelas' => 'Step 05',
            'kapasitas' => 30,
            'status' => 'Aktif',
        ]);
        $subject = Subject::create([
            'code' => 'STEP05-'.Str::upper(Str::random(5)),
            'name' => 'Mapel Step 05',
        ]);
        $schedule = ClassSchedule::create([
            'kelas_id' => $kelas->id,
            'employee_id' => $employee->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'day_of_week' => $now->dayOfWeekIso,
            'time_start' => $now->copy()->subMinutes(5)->format('H:i:00'),
            'time_end' => $now->copy()->addMinutes(30)->format('H:i:00'),
            'is_active' => true,
        ]);
        $students = collect(range(1, 2))->map(fn (int $index) => Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'kelas_id' => $kelas->id,
            'nis' => "STEP05-{$index}",
            'full_name' => "Siswa Step 05 {$index}",
            'gender' => 'male',
            'is_active' => true,
        ]));

        return compact('unit', 'teacher', 'schedule', 'students');
    }
}
