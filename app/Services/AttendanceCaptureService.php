<?php

namespace App\Services;

use App\Models\AttendanceScanLog;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceCaptureService
{
    public function __construct(
        private AttendanceAccessService $access,
        private StudentQrCredentialService $studentQr,
    ) {}

    public function start(LessonAttendanceSession $session, int $minutes = 60): array
    {
        $this->ensureDraft($session);
        $token = Str::random(64);
        $session->update([
            'session_token_hash' => hash('sha256', $token),
            'session_started_at' => now(),
            'session_expires_at' => now()->addMinutes($minutes),
            'session_closed_at' => null,
        ]);

        return ['session' => $session->fresh(), 'token' => $token];
    }

    public function close(LessonAttendanceSession $session): LessonAttendanceSession
    {
        $this->ensureActive($session);
        $session->update(['session_closed_at' => now(), 'session_token_hash' => null]);

        return $session->fresh();
    }

    public function studentQrToken(Student $student): string
    {
        return $this->studentQr->issue($student)['raw_token'];
    }

    public function resolveStudent(string $method, string $identifier): ?Student
    {
        if ($method === 'qr_code') {
            if ($student = $this->studentQr->resolve($identifier)) {
                return $student;
            }

            // A newly issued credential must not fall through to a legacy
            // identifier after it has been revoked or expired.
            if (str_starts_with(trim($identifier), 'stuqr:v1:')) {
                return null;
            }

            try {
                $payload = json_decode(Crypt::decryptString($identifier), true, flags: JSON_THROW_ON_ERROR);
                if (($payload['purpose'] ?? null) !== 'attendance-qr') {
                    throw new \RuntimeException('Invalid QR purpose.');
                }

                return Student::active()->find($payload['student_id'] ?? null);
            } catch (\Throwable) {
                return Student::active()
                    ->where(function ($query) use ($identifier) {
                        $query->where('metadata->qr_code', $identifier)
                            ->orWhere('metadata->card_number', $identifier)
                            ->orWhere('nis', $identifier)
                            ->orWhere('nisn', $identifier);
                    })
                    ->first();
            }
        }

        if ($method === 'rfid') {
            return Student::active()
                ->where(function ($query) use ($identifier) {
                    $query->where('metadata->card_number', $identifier)
                        ->orWhere('metadata->rfid_uid', $identifier)
                        ->orWhere('nis', $identifier)
                        ->orWhere('nisn', $identifier);
                })
                ->first();
        }

        return Student::active()->where(fn ($q) => $q->where('nis', $identifier)->orWhere('nisn', $identifier))->first();
    }

    public function record(Request $request, LessonAttendanceSession $session, string $method, ?Student $student, array $input = []): array
    {
        $this->ensureActive($session);
        if (! $student) {
            return $this->failure($request, $session, $method, 'student_not_found', 'Siswa tidak ditemukan.', $input);
        }
        if (! $this->access->studentsForSchedule($session->schedule)->whereKey($student->id)->exists()) {
            return $this->failure($request, $session, $method, 'student_not_in_rombel', 'Siswa bukan anggota rombel jadwal.', $input, $student);
        }
        if ($method === 'face_recognition' && (float) ($input['confidence_score'] ?? 0) < (float) config('attendance.face_confidence_threshold', 85)) {
            return $this->failure($request, $session, $method, 'low_confidence', 'Confidence rendah; perlu verifikasi guru.', $input, $student);
        }

        return DB::transaction(function () use ($request, $session, $method, $student, $input) {
            // Lock the session, not only an existing attendance row. The
            // first two concurrent scans otherwise both observe an empty row.
            $lockedSession = LessonAttendanceSession::query()->lockForUpdate()->findOrFail($session->id);
            $this->ensureActive($lockedSession);
            $attendance = LmsPresensi::withTrashed()
                ->where('session_id', $lockedSession->id)
                ->where('siswa_id', $student->id)
                ->lockForUpdate()
                ->first();
            if ($attendance?->trashed()) {
                $attendance->restore();
                $attendance->refresh();
            }
            $hasReviewedStatus = $attendance && ! in_array($attendance->status_hadir, ['belum_diverifikasi', 'belum_diisi', null, ''], true);
            if ($attendance && ($hasReviewedStatus || $attendance->recorded_at || AttendanceScanLog::where('lesson_attendance_id', $lockedSession->id)
                ->where('student_id', $student->id)->where('result_status', 'success')->exists())) {
                return $this->failure($request, $session, $method, 'duplicate_scan', 'Siswa sudah tercatat.', $input, $student);
            }
            $scannedAt = isset($input['scanned_at']) ? now()->parse($input['scanned_at']) : now();
            $start = now()->parse($lockedSession->attendance_date->format('Y-m-d').' '.$lockedSession->schedule->time_start);
            $lateMinutes = max(0, $start->diffInMinutes($scannedAt, false) - (int) config('attendance.late_tolerance_minutes', 10));
            $status = $lateMinutes > 0 ? 'terlambat' : 'hadir';
            $log = $this->log($request, $lockedSession, $method, 'success', null, $input, $student);
            $attendance = LmsPresensi::updateOrCreate([
                'jadwal_pelajaran_id' => $lockedSession->schedule_id,
                'siswa_id' => $student->id,
                'tanggal' => $lockedSession->attendance_date,
            ], [
                'session_id' => $lockedSession->id, 'status_hadir' => $status,
                'arrival_time' => $scannedAt->format('H:i:s'), 'waktu_presensi' => $scannedAt,
                'verification_status' => $method === 'face_recognition' ? 'pending' : 'verified',
                'recorded_method' => $method, 'recorded_at' => $scannedAt,
                'recorded_by' => $request->user()?->id, 'scan_log_id' => $log->id,
                'confidence_score' => $input['confidence_score'] ?? null,
                'device_identifier' => $input['device_id'] ?? null,
                'capture_metadata' => ['late_minutes' => $lateMinutes],
                'updated_by' => $request->user()?->id,
            ]);
            $methods = $lockedSession->attendances()->whereNotNull('recorded_method')->distinct()->pluck('recorded_method');
            $lockedSession->update(['attendance_method' => $methods->count() > 1 ? 'mixed' : ($methods->first() ?: $method)]);

            return ['scan_status' => 'success', 'message' => 'Presensi berhasil dicatat.', 'student' => $this->studentPayload($student), 'attendance_status' => $status, 'recorded_at' => $scannedAt, 'attendance' => $attendance];
        });
    }

    public function log(Request $request, LessonAttendanceSession $session, string $method, string $status, ?string $reason, array $input, ?Student $student = null): AttendanceScanLog
    {
        $identifier = $input['identifier'] ?? $input['barcode'] ?? $input['template_reference'] ?? null;

        return AttendanceScanLog::create([
            'lesson_attendance_id' => $session->id, 'student_id' => $student?->id,
            'class_schedule_id' => $session->schedule_id, 'scan_method' => $method,
            'hashed_identifier' => $identifier ? hash('sha256', $identifier) : null,
            'device_id' => $input['device_uuid'] ?? null, 'scanned_at' => now(),
            'result_status' => $status, 'failure_reason' => $reason,
            'confidence_score' => $input['confidence_score'] ?? null,
            'request_ip' => $request->ip(), 'user_agent' => $request->userAgent(),
            'metadata' => array_filter(['provider' => $input['provider'] ?? null]),
            'created_by' => $request->user()?->id,
        ]);
    }

    private function failure(Request $request, LessonAttendanceSession $session, string $method, string $status, string $message, array $input, ?Student $student = null): array
    {
        $this->log($request, $session, $method, $status, $message, $input, $student);

        return ['scan_status' => $status, 'message' => $message, 'student' => $this->studentPayload($student), 'attendance_status' => null, 'recorded_at' => now()];
    }

    private function studentPayload(?Student $student): ?array
    {
        if (! $student) {
            return null;
        }

        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'nama_lengkap' => $student->nama_lengkap,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'class_id' => $student->class_id,
            'kelas_id' => $student->kelas_id,
        ];
    }

    private function ensureDraft(LessonAttendanceSession $session): void
    {
        if (! in_array($session->status, ['draft', 'revised'])) {
            throw ValidationException::withMessages(['session' => 'Presensi final atau dibatalkan tidak menerima input.']);
        }
    }

    private function ensureActive(LessonAttendanceSession $session): void
    {
        $this->ensureDraft($session);
        if ($session->teaching_session_status !== null && $session->teaching_session_status !== 'active') {
            throw ValidationException::withMessages(['session' => 'Sesi mengajar Step 04 belum aktif atau sudah ditutup.']);
        }
        if (! $session->session_started_at || $session->session_closed_at) {
            throw ValidationException::withMessages(['session' => 'Sesi belum dimulai atau sudah ditutup.']);
        }
        if ($session->session_expires_at?->isPast()) {
            throw ValidationException::withMessages(['session' => 'Sesi presensi sudah kedaluwarsa.']);
        }
    }
}
