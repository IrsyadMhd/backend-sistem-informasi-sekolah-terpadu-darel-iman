<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\LessonAttendanceSession;
use App\Models\Student;
use App\Services\AttendanceAccessService;
use App\Services\AttendanceCaptureService;
use App\Services\TeachingAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AttendanceCaptureController extends Controller
{
    public function __construct(
        private AttendanceCaptureService $capture,
        private AttendanceAccessService $access,
        private TeachingAttendanceService $teachingAttendance,
    ) {}

    private function teacher(Request $request, LessonAttendanceSession $session, string $permission): void
    {
        abort_unless($request->user()?->hasAnyRole(['Guru', 'Super Admin']) || $request->user()?->hasPermissionTo($permission), 403);
        $this->access->assertTeacherOwnsSchedule($request->user(), $session->schedule_id);
    }

    public function start(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $this->teacher($request, $session, 'lesson_attendance.session.start');
        $data = $request->validate(['duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240']]);

        if ($session->teaching_attendance_id || $session->teaching_session_status !== null) {
            return response()->json(['success' => true, 'data' => $this->teachingAttendance->startSession(
                $request,
                $session,
                $data['duration_minutes'] ?? config('attendance.session_duration_minutes'),
            )]);
        }

        return response()->json(['success' => true, 'data' => $this->capture->start($session, $data['duration_minutes'] ?? config('attendance.session_duration_minutes'))]);
    }

    public function close(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $this->teacher($request, $session, 'lesson_attendance.session.close');

        if ($session->teaching_attendance_id || $session->teaching_session_status !== null) {
            return response()->json(['success' => true, 'data' => $this->teachingAttendance->closeSession($request, $session)]);
        }

        return response()->json(['success' => true, 'data' => $this->capture->close($session)]);
    }

    public function manual(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $this->teacher($request, $session, 'lesson_attendance.manual');
        $data = $request->validate(['student_id' => ['required', 'uuid', 'exists:students,id']]);

        return response()->json(['success' => true, 'data' => $this->capture->record($request, $session, 'manual', Student::find($data['student_id']), $data)]);
    }

    public function scan(Request $request, LessonAttendanceSession $session, string $method): JsonResponse
    {
        $method = ['qr' => 'qr_code', 'rfid' => 'rfid', 'barcode' => 'barcode', 'face' => 'face_recognition'][$method] ?? null;
        abort_unless($method, 404);
        $this->teacher($request, $session, 'lesson_attendance.'.match ($method) {
            'qr_code' => 'qr_scan', 'rfid', 'barcode' => 'barcode_scan', default => 'face_scan',
        });
        $data = $request->validate([
            'identifier' => [Rule::requiredIf($method !== 'face_recognition'), 'nullable', 'string', 'max:2048'],
            'template_reference' => [Rule::requiredIf($method === 'face_recognition'), 'nullable', 'string', 'max:255'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scanned_at' => ['nullable', 'date'], 'device_id' => ['nullable', 'string', 'max:255'],
        ]);
        $student = $method === 'face_recognition'
            ? Student::active()->where('metadata->face_template_reference', $data['template_reference'])->first()
            : $this->capture->resolveStudent($method, $data['identifier']);

        return response()->json(['success' => true, 'data' => $this->capture->record($request, $session, $method, $student, $data)]);
    }

    public function identifyCard(Request $request, string $method): JsonResponse
    {
        $resolvedMethod = ['qr' => 'qr_code', 'rfid' => 'rfid'][$method] ?? null;
        abort_unless($resolvedMethod, 404);
        abort_unless(
            $request->user()?->hasAnyRole(['Guru', 'Super Admin'])
                || $request->user()?->hasAnyPermission(['lesson_attendance.qr_scan', 'lesson_attendance.create']),
            403,
        );
        $data = $request->validate([
            'schedule_id' => ['required', 'uuid', 'exists:class_schedules,id'],
            'identifier' => ['required', 'string', 'max:2048'],
        ]);
        $schedule = $this->access->assertTeacherOwnsSchedule($request->user(), $data['schedule_id']);
        $student = $this->capture->resolveStudent($resolvedMethod, trim($data['identifier']));

        abort_unless($student, 404, 'Kartu siswa tidak dikenali.');
        abort_unless(
            $this->access->studentsForSchedule($schedule)->whereKey($student->id)->exists(),
            422,
            'Siswa tidak terdaftar pada rombel jadwal ini.'
        );

        return response()->json([
            'success' => true,
            'message' => 'Kartu siswa berhasil diidentifikasi.',
            'data' => [
                'student' => array_merge(
                    $student->only(['id', 'full_name', 'class_id', 'kelas_id']),
                    ['nama_lengkap' => $student->nama_lengkap]
                ),
                'method' => $resolvedMethod,
                'identified_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function logs(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $this->teacher($request, $session, 'lesson_attendance.scan_logs.view');

        return response()->json(['success' => true, 'data' => $session->scanLogs()->with('student:id,nis,nisn,full_name')->latest('scanned_at')->paginate($request->integer('per_page', 20))]);
    }

    public function studentToken(Request $request, Student $student): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['Super Admin', 'Admin TU']) || $request->user()?->hasPermissionTo('attendance_device.manage'), 403);

        return response()->json(['success' => true, 'data' => ['student_id' => $student->id, 'qr_token' => $this->capture->studentQrToken($student)]]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $device->update(['last_seen_at' => now(), 'status' => 'active']);

        return response()->json(['success' => true, 'data' => $device]);
    }

    public function fingerprint(Request $request): JsonResponse
    {
        $device = $this->device($request, 'fingerprint');
        $data = $request->validate([
            'session_id' => ['required', 'uuid', 'exists:lesson_attendance_sessions,id'],
            'template_reference' => ['required', 'string', 'max:255'],
            'scanned_at' => ['nullable', 'date'],
        ]);
        $session = LessonAttendanceSession::findOrFail($data['session_id']);
        $student = Student::active()->where('metadata->fingerprint_template_reference', $data['template_reference'])->first();
        $data['device_uuid'] = $device->id;
        $data['device_id'] = $device->device_code;

        return response()->json(['success' => true, 'data' => $this->capture->record($request, $session, 'fingerprint', $student, $data)]);
    }

    private function device(Request $request, ?string $type = null): AttendanceDevice
    {
        $code = $request->header('X-Device-Code');
        $key = $request->bearerToken();
        $device = AttendanceDevice::where('device_code', $code)->first();
        abort_unless($device && $key && Hash::check($key, $device->api_key_hash), 401, 'Perangkat tidak terdaftar.');
        abort_unless($device->status !== 'blocked' && (! $type || $device->device_type === $type), 403, 'Perangkat tidak aktif atau tidak sesuai.');
        $device->update(['last_seen_at' => now()]);

        return $device;
    }
}
