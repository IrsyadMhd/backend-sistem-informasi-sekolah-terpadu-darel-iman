<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use App\Models\HomeroomAttendanceFollowUp;
use App\Models\LessonAttendanceCorrection;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\Student;
use App\Models\StudentAttendancePermission;
use App\Services\AttendanceAccessService;
use App\Services\AttendanceAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceWorkflowController extends Controller
{
    public function __construct(
        private AttendanceAccessService $access,
        private AttendanceAuditService $audit,
        private \App\Services\AccessScopeService $accessScopeService,
    ) {}

    private function permit(Request $request, array $permissions, array $roles = []): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasAnyPermission($permissions) || $user->hasAnyRole(array_merge(['Super Admin'], $roles))), 403);
    }

    public function teacherDashboard(Request $request): JsonResponse
    {
        $this->permit($request, ['attendance.teacher.dashboard', 'lesson_attendance.view_own'], ['Guru']);
        $schedules = $this->access->teacherSchedules($request->user());
        $scheduleIds = (clone $schedules)->pluck('id');
        $sessions = LessonAttendanceSession::whereIn('schedule_id', $scheduleIds);
        $todayIds = (clone $schedules)->where('day_of_week', now()->dayOfWeekIso)->pluck('id');

        return response()->json(['success' => true, 'data' => [
            'schedules_today' => $todayIds->count(),
            'attendance_missing' => $todayIds->count() - LessonAttendanceSession::whereIn('schedule_id', $todayIds)->whereDate('attendance_date', today())->count(),
            'draft' => (clone $sessions)->where('status', 'draft')->count(),
            'final' => (clone $sessions)->where('status', 'final')->count(),
            'total_meetings' => (clone $sessions)->count(),
            'schedules' => (clone $schedules)->with(['subject', 'kelas'])->where('day_of_week', now()->dayOfWeekIso)->get(),
        ]]);
    }

    public function showPermission(Request $request, StudentAttendancePermission $permission): JsonResponse
    {
        $studentId = $this->access->student($request->user())?->id;
        $allowed = $request->user()->hasRole('Super Admin')
            || ($studentId && $permission->student_id === $studentId)
            || $this->access->homeroomStudentIds($request->user())->contains($permission->student_id);
        abort_unless($allowed, 403);

        return response()->json(['success' => true, 'data' => $permission->load('student')]);
    }

    public function showCorrection(Request $request, LessonAttendanceCorrection $correction): JsonResponse
    {
        abort_unless($this->access->canAccessAttendance($request->user(), $correction->attendance), 403);

        return response()->json(['success' => true, 'data' => $correction->load(['attendance.siswa', 'attendance.jadwalPelajaran.subject'])]);
    }

    public function showFollowUp(Request $request, HomeroomAttendanceFollowUp $followUp): JsonResponse
    {
        abort_unless($request->user()->hasRole('Super Admin') || $this->access->homeroomClasses($request->user())->whereKey($followUp->class_id)->exists(), 403);

        return response()->json(['success' => true, 'data' => $followUp->load('student')]);
    }

    public function updateSession(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $request->merge(['schedule_id' => $session->schedule_id, 'attendance_date' => $session->attendance_date->format('Y-m-d')]);

        return $this->storeSession($request);
    }

    public function permissionIndex(Request $request): JsonResponse
    {
        return $request->user()->hasAnyRole(['Wali Kelas', 'Super Admin'])
            ? $this->homeroomPermissions($request)
            : $this->permissions($request);
    }

    public function permissionCreate(Request $request): JsonResponse
    {
        return $this->permissions($request);
    }

    public function permissionReviewAction(Request $request, StudentAttendancePermission $permission, string $action): JsonResponse
    {
        $request->merge(['status' => match ($action) {
            'approve' => 'approved', 'reject' => 'rejected', default => 'needs_revision',
        }]);

        return $this->reviewPermission($request, $permission);
    }

    public function correctionReviewAction(Request $request, LessonAttendanceCorrection $correction, string $action): JsonResponse
    {
        $request->merge(['status' => $action === 'approve' ? 'approved' : 'rejected']);

        return $this->reviewCorrection($request, $correction);
    }

    public function schedules(Request $request): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.view_own', 'lesson_attendance.create'], ['Guru']);
        $date = ($request->date('date') ?: now())->startOfDay();
        $schedules = $this->access->teacherSchedules($request->user())
            ->with(['subject', 'kelas.unitPendidikan', 'kelas.tahunAjaran', 'kelas.semester', 'employee'])
            ->when($request->filled('date'), fn ($q) => $q->where('day_of_week', $date->dayOfWeekIso))
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->orderBy('time_start')->get();

        $schedules = $schedules->map(function (ClassSchedule $schedule) use ($date) {
            $session = LessonAttendanceSession::query()
                ->where('schedule_id', $schedule->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->first();
            $schedule->setAttribute('attendance_session_id', $session?->id);
            $schedule->setAttribute('attendance_status', $session?->status ?? 'not_started');
            $schedule->setAttribute('teaching_session_status', $session?->teaching_session_status);

            return $schedule;
        });

        return response()->json(['success' => true, 'data' => $schedules]);
    }

    public function activeSchedules(Request $request): JsonResponse
    {
        $this->permit(
            $request,
            ['lesson_attendance.view_own', 'lesson_attendance.create', 'homeroom_attendance.view'],
            ['Guru', 'Wali Kelas']
        );

        $now = now();

        return response()->json(['success' => true, 'data' => [
            'server_time' => $now->toIso8601String(),
            'timezone' => config('app.timezone'),
            'date' => $now->toDateString(),
            'schedules' => $this->access->activeSchedules($request->user(), $now),
        ]]);
    }

    public function scheduleStudents(Request $request, string $schedule): JsonResponse
    {
        $this->permit(
            $request,
            ['lesson_attendance.view_own', 'lesson_attendance.create', 'homeroom_attendance.view'],
            ['Guru', 'Wali Kelas']
        );
        $scheduleModel = $request->string('attendance_context')->toString() === 'active_login'
            ? $this->access->assertCanTakeActiveSchedule($request->user(), $schedule, now())
            : $this->access->assertTeacherOwnsSchedule($request->user(), $schedule);
        $students = $this->access->studentsForSchedule($scheduleModel)
            ->select('id', 'nis', 'nisn', 'full_name', 'class_id', 'kelas_id')
            ->orderBy('full_name')->get();

        $date = $request->date('date') ?: now();
        $session = LessonAttendanceSession::query()
            ->where('schedule_id', $scheduleModel->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();
        $recommendations = StudentAttendancePermission::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get()->keyBy('student_id');

        return response()->json(['success' => true, 'data' => $students->map(function (Student $student) use ($recommendations) {
            $permission = $recommendations->get($student->id);

            return [
                ...$student->only(['id', 'nis', 'nisn', 'full_name', 'class_id', 'kelas_id']),
                'recommended_status' => $permission?->type,
                'recommendation_verified' => (bool) $permission,
            ];
        }), 'session' => $session]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.view', 'lesson_attendance.view_own'], ['Guru', 'Wali Kelas']);
        $query = LessonAttendanceSession::query()->with([
            'schedule.subject', 'schedule.kelas', 'schedule.employee', 'attendances.siswa',
        ]);
        if (! $request->user()->hasRole('Super Admin')) {
            if ($request->user()->hasRole('Wali Kelas')) {
                $query->whereHas('schedule', fn ($q) => $q->whereIn('kelas_id', $this->access->homeroomClasses($request->user())->pluck('id')));
            } else {
                $query->whereIn('schedule_id', $this->access->teacherSchedules($request->user())->select('id'));
            }
        }
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('schedule_id'), fn ($q) => $q->where('schedule_id', $request->string('schedule_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('attendance_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('attendance_date', '<=', $request->date('date_to')));

        return response()->json(['success' => true, 'data' => $query->latest('attendance_date')->paginate($request->integer('per_page', 15))]);
    }

    public function showSession(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.view', 'lesson_attendance.view_own', 'homeroom_attendance.view'], ['Guru', 'Wali Kelas']);
        $sample = $session->attendances()->first();
        $schedule = $session->schedule;
        $canAccess = $request->user()->hasRole('Super Admin')
            || ($sample && $this->access->canAccessAttendance($request->user(), $sample))
            || ($schedule && $request->user()->hasRole('Wali Kelas') && $this->access->homeroomClasses($request->user())->whereKey($schedule->kelas_id)->exists())
            || ($schedule && $this->access->teacherSchedules($request->user())->whereKey($schedule->id)->exists());
        abort_unless($canAccess, 403);

        return response()->json(['success' => true, 'data' => $session->load([
            'schedule.subject', 'schedule.kelas.unitPendidikan', 'schedule.kelas.tahunAjaran',
            'schedule.semester', 'schedule.employee', 'attendances.siswa',
        ])]);
    }

    public function storeSession(Request $request): JsonResponse
    {
        $this->permit(
            $request,
            ['lesson_attendance.create', 'homeroom_attendance.view'],
            ['Guru', 'Wali Kelas']
        );
        $data = $request->validate([
            'schedule_id' => ['required', 'uuid', 'exists:class_schedules,id'],
            'attendance_date' => ['required', 'date'],
            'meeting_number' => ['nullable', 'integer', 'min:1'],
            'learning_module_id' => ['nullable', 'uuid'],
            'learning_material_id' => ['nullable', 'uuid'],
            'learning_activity_id' => ['nullable', 'uuid'],
            'learning_material' => ['nullable', 'string'],
            'learning_activity' => ['nullable', 'string'],
            'topic' => ['nullable', 'string', 'max:255'],
            'meeting_notes' => ['nullable', 'string'],
            'attendance_context' => ['nullable', Rule::in(['active_login'])],
            'substitute_reason' => ['nullable', 'string', 'min:5', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', 'uuid', 'distinct', 'exists:students,id'],
            'items.*.status' => ['required', Rule::in(['hadir', 'terlambat', 'izin', 'sakit', 'alpa', 'dispensasi', 'belum_diverifikasi', 'belum_diisi'])],
            'items.*.arrival_time' => ['nullable', 'date_format:H:i'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.recorded_method' => ['nullable', Rule::in(['manual', 'qr_code', 'barcode', 'face_recognition', 'fingerprint'])],
        ]);
        $activeLogin = ($data['attendance_context'] ?? null) === 'active_login';
        abort_if($activeLogin && $request->date('attendance_date')->toDateString() !== now()->toDateString(), 422, 'Presensi dari jadwal aktif hanya dapat diambil untuk hari ini.');
        $schedule = $activeLogin
            ? $this->access->assertCanTakeActiveSchedule($request->user(), $data['schedule_id'], now())
            : $this->access->assertTeacherOwnsSchedule($request->user(), $data['schedule_id']);
        $isSubstitute = $schedule->getAttribute('attendance_access') === 'homeroom_substitute';
        abort_if($isSubstitute && empty($data['substitute_reason']), 422, 'Alasan penggantian guru wajib diisi oleh wali kelas.');
        abort_unless($schedule->is_active !== false, 422, 'Jadwal pelajaran tidak aktif.');
        $attendanceDate = $request->date('attendance_date');
        abort_unless($attendanceDate->dayOfWeekIso === (int) $schedule->day_of_week, 422, 'Tanggal tidak sesuai dengan hari pada jadwal pelajaran.');
        abort_if($schedule->academicYear && ($attendanceDate->lt($schedule->academicYear->start_date) || $attendanceDate->gt($schedule->academicYear->end_date)), 422, 'Tanggal di luar tahun ajaran jadwal.');
        abort_if($schedule->semester && ($attendanceDate->lt($schedule->semester->start_date) || $attendanceDate->gt($schedule->semester->end_date)), 422, 'Tanggal di luar semester jadwal.');
        foreach ($data['items'] as $item) {
            $this->access->assertStudentInSchedule($schedule, $item['student_id']);
        }
        $rosterIds = $this->access->studentsForSchedule($schedule)->pluck('id')->map(fn ($id) => (string) $id);
        abort_if($rosterIds->isEmpty(), 422, 'Rombel jadwal belum memiliki siswa aktif.');

        $session = DB::transaction(function () use ($data, $request, $rosterIds, $activeLogin) {
             $session = LessonAttendanceSession::query()
                 ->where('schedule_id', $data['schedule_id'])
                 ->whereDate('attendance_date', $data['attendance_date'])
                 ->lockForUpdate()
                 ->first();
             if (! $session) {
                 $session = new LessonAttendanceSession([
                     'schedule_id' => $data['schedule_id'],
                     'attendance_date' => $data['attendance_date'],
                 ]);
             }
             abort_if($session->exists && in_array($session->status, ['final', 'locked']), 422, 'Presensi final atau terkunci tidak dapat diedit.');
             abort_if($activeLogin && $session->teaching_session_status !== 'active', 422, 'Sesi mengajar Step 04 harus aktif untuk konteks jadwal berjalan.');
             abort_if(! $activeLogin && $session->teaching_session_status !== null && $session->teaching_session_status !== 'active', 422, 'Sesi mengajar Step 04 belum aktif atau sudah ditutup.');
            $session->fill([
                'meeting_number' => $data['meeting_number'] ?? 1,
                'learning_module_id' => $data['learning_module_id'] ?? null,
                'learning_material_id' => $data['learning_material_id'] ?? null,
                'learning_activity_id' => $data['learning_activity_id'] ?? null,
                'learning_material' => $data['learning_material'] ?? null,
                'learning_activity' => $data['learning_activity'] ?? null,
                'topic' => $data['topic'] ?? null,
                'meeting_notes' => $data['meeting_notes'] ?? null,
                'status' => 'draft', 'created_by' => $session->created_by ?: $request->user()->id,
                'updated_by' => $request->user()->id,
            ])->save();
            $submitted = collect($data['items'])->keyBy('student_id');
            $existing = $session->exists
                ? $session->attendances()->get()->keyBy('siswa_id')
                : collect();

            foreach ($rosterIds as $studentId) {
                $item = $submitted->get($studentId);
                $previous = $existing->get($studentId);
                if (! $item && $previous) {
                    continue;
                }

                $status = $item['status'] ?? 'belum_diverifikasi';
                $isUnmarked = in_array($status, ['belum_diverifikasi', 'belum_diisi'], true);
                LmsPresensi::updateOrCreate([
                    'jadwal_pelajaran_id' => $data['schedule_id'], 'siswa_id' => $studentId,
                    'tanggal' => $data['attendance_date'],
                ], [
                    'session_id' => $session->id, 'status_hadir' => $status,
                    'arrival_time' => $item['arrival_time'] ?? null, 'keterangan' => $item['notes'] ?? null,
                    'pertemuan_ke' => $data['meeting_number'] ?? 1, 'waktu_presensi' => now(),
                    'verification_status' => $isUnmarked ? 'unverified' : (in_array($status, ['izin', 'sakit']) ? 'pending' : 'verified'),
                    'recorded_method' => $item['recorded_method'] ?? null,
                    'recorded_at' => ! $isUnmarked && ! empty($item['recorded_method']) ? now() : null,
                    'recorded_by' => ! $isUnmarked && ! empty($item['recorded_method']) ? $request->user()->id : null,
                    'updated_by' => $request->user()->id,
                ]);
            }
            $methods = $session->attendances()->whereNotNull('recorded_method')->distinct()->pluck('recorded_method');
            $session->update(['attendance_method' => $methods->count() > 1 ? 'mixed' : ($methods->first() ?: 'manual')]);
            if (! empty($data['substitute_reason'])) {
                $session->update(['metadata' => array_merge($session->metadata ?? [], [
                    'attendance_context' => 'active_login',
                    'substitute_reason' => $data['substitute_reason'],
                    'substitute_user_id' => $request->user()->id,
                ])]);
            }

            return $session->fresh();
        });
        $this->audit->record($request, $session->wasRecentlyCreated ? 'create_draft' : 'update_draft', $session, null, $session->toArray());

        return response()->json(['success' => true, 'message' => 'Draft presensi tersimpan.', 'data' => $session->load('attendances.siswa')], 201);
    }

    public function finalize(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $this->permit(
            $request,
            ['lesson_attendance.finalize', 'homeroom_attendance.view'],
            ['Guru', 'Wali Kelas']
        );
        $isRecordedSubstitute = $request->user()->hasRole('Wali Kelas')
            && data_get($session->metadata, 'attendance_context') === 'active_login'
            && data_get($session->metadata, 'substitute_user_id') === $request->user()->id;
        if ($isRecordedSubstitute) {
            abort_unless($this->access->homeroomClasses($request->user())
                ->whereKey($session->schedule?->kelas_id)->exists(), 403);
        } else {
            $this->access->assertTeacherOwnsSchedule($request->user(), $session->schedule_id);
        }
        $finalized = DB::transaction(function () use ($request, $session): array {
            $locked = LessonAttendanceSession::query()
                ->with('schedule')
                ->lockForUpdate()
                ->findOrFail($session->id);
            abort_unless(in_array($locked->status, ['draft', 'revised']), 422, 'Hanya draft atau revisi yang dapat difinalisasi.');
            abort_if($locked->teaching_session_status !== null && $locked->teaching_session_status !== 'active', 422, 'Sesi mengajar Step 04 harus aktif sebelum presensi difinalisasi.');

            $rosterIds = $this->access->studentsForSchedule($locked->schedule)->pluck('id')->map(fn ($id) => (string) $id);
            $attendances = $locked->attendances()->get()->keyBy(fn (LmsPresensi $item) => (string) $item->siswa_id);
            abort_if($attendances->keys()->map(fn ($id) => (string) $id)->diff($rosterIds)->isNotEmpty(), 422, 'Roster presensi memuat siswa di luar rombel jadwal.');
            abort_unless($rosterIds->diff($attendances->keys())->isEmpty(), 422, 'Roster presensi belum lengkap.');
            abort_unless(
                $attendances->filter(fn (LmsPresensi $item, $studentId) => $rosterIds->contains((string) $studentId))
                    ->every(fn (LmsPresensi $item) => ! in_array($item->status_hadir, ['belum_diverifikasi', 'belum_diisi', null, ''], true)),
                422,
                'Semua siswa harus memiliki status sebelum finalisasi.'
            );

            $oldStatus = $locked->status;
            $locked->update([
                'status' => 'final',
                'finalized_at' => now(),
                'finalized_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            return ['session' => $locked->fresh(), 'old_status' => $oldStatus];
        });
        $session = $finalized['session'];
        $this->audit->record($request, 'finalize', $session, ['status' => $finalized['old_status']], $session->toArray());

        return response()->json(['success' => true, 'message' => 'Presensi berhasil difinalisasi.', 'data' => $session]);
    }

    public function unlock(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.unlock'], []);
        abort_unless(in_array($session->status, ['final', 'locked']), 422, 'Presensi belum final atau terkunci.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $old = $session->toArray();
        $session->update(['status' => 'revised', 'locked_at' => null, 'updated_by' => $request->user()->id]);
        $this->audit->record($request, 'unlock', $session, $old, $session->toArray(), $data['reason']);

        return response()->json(['success' => true, 'message' => 'Presensi dibuka untuk revisi.', 'data' => $session]);
    }

    public function cancelSession(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.cancel', 'lesson_attendance.update'], ['Guru']);
        $this->access->assertTeacherOwnsSchedule($request->user(), $session->schedule_id);
        abort_if($session->status === 'locked', 422, 'Presensi terkunci tidak dapat dibatalkan.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $old = $session->toArray();
        $session->update(['status' => 'cancelled', 'updated_by' => $request->user()->id]);
        $this->audit->record($request, 'cancel', $session, $old, $session->toArray(), $data['reason']);

        return response()->json(['success' => true, 'message' => 'Presensi dibatalkan.', 'data' => $session]);
    }

    public function myAttendance(Request $request): JsonResponse
    {
        $this->permit($request, ['student_attendance.view_own'], ['Siswa']);
        $student = $this->access->student($request->user());
        abort_unless($student, 403, 'Akun belum terhubung dengan data siswa.');
        $data = LmsPresensi::with(['jadwalPelajaran.subject', 'jadwalPelajaran.kelas', 'session'])
            ->where('siswa_id', $student->id)->latest('tanggal')->paginate($request->integer('per_page', 15));

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function permissions(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            $this->permit($request, ['student_attendance.view_own'], ['Siswa']);
        } else {
            $this->permit($request, ['student_attendance.permission.create', 'student_attendance.permission.update']);
        }
        $student = $this->access->student($request->user());
        abort_unless($student, 403);
        if ($request->isMethod('get')) {
            return response()->json(['success' => true, 'data' => StudentAttendancePermission::where('student_id', $student->id)->latest()->paginate(15)]);
        }
        $data = $request->validate([
            'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', Rule::in(['izin', 'sakit'])], 'reason' => ['required', 'string'], 'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'submitted'])],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $status = $data['status'] ?? 'submitted';
        $attachment = $request->file('attachment')?->store('attendance/permissions', 'public');
        unset($data['attachment']);
        $item = StudentAttendancePermission::create($data + [
            'student_id' => $student->id, 'class_id' => $student->class_id,
            'academic_year_id' => $student->schoolClass?->academic_year_id,
            'semester_id' => $student->schoolClass?->semester_id,
            'attachment_path' => $attachment, 'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id, 'status' => $status,
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);
        $this->audit->record($request, 'submit_permission', $item, null, $item->toArray());

        return response()->json(['success' => true, 'message' => 'Pengajuan berhasil disimpan.', 'data' => $item], 201);
    }

    public function cancelPermission(Request $request, StudentAttendancePermission $permission): JsonResponse
    {
        $this->permit($request, ['student_attendance.permission.cancel']);
        abort_unless($permission->student_id === $this->access->student($request->user())?->id, 403);
        abort_unless(in_array($permission->status, ['draft', 'submitted', 'waiting_verification']), 422, 'Pengajuan tidak dapat dibatalkan.');
        $old = $permission->toArray();
        $permission->update(['status' => 'cancelled', 'updated_by' => $request->user()->id]);
        $this->audit->record($request, 'cancel_permission', $permission, $old, $permission->toArray());

        return response()->json(['success' => true, 'message' => 'Pengajuan dibatalkan.', 'data' => $permission]);
    }

    public function updatePermission(Request $request, StudentAttendancePermission $permission): JsonResponse
    {
        $this->permit($request, ['student_attendance.permission.update']);
        abort_unless($permission->student_id === $this->access->student($request->user())?->id, 403);
        abort_unless(in_array($permission->status, ['draft', 'needs_revision', 'revision_required']), 422, 'Hanya draft atau pengajuan revisi yang dapat diubah.');
        $data = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'type' => ['sometimes', Rule::in(['izin', 'sakit'])],
            'reason' => ['sometimes', 'string', 'min:3'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('attendance/permissions', 'public');
        }
        unset($data['attachment']);
        $old = $permission->toArray();
        $permission->update($data + ['status' => 'draft', 'updated_by' => $request->user()->id]);
        $this->audit->record($request, 'update_permission', $permission, $old, $permission->toArray());

        return response()->json(['success' => true, 'message' => 'Draft pengajuan diperbarui.', 'data' => $permission]);
    }

    public function submitPermission(Request $request, StudentAttendancePermission $permission): JsonResponse
    {
        $this->permit($request, ['student_attendance.permission.update']);
        abort_unless($permission->student_id === $this->access->student($request->user())?->id, 403);
        abort_unless(in_array($permission->status, ['draft', 'needs_revision', 'revision_required']), 422, 'Pengajuan tidak dapat dikirim.');
        $old = $permission->toArray();
        $permission->update(['status' => 'submitted', 'submitted_at' => now(), 'updated_by' => $request->user()->id]);
        $this->audit->record($request, 'submit_permission', $permission, $old, $permission->toArray());

        return response()->json(['success' => true, 'message' => 'Pengajuan dikirim untuk diverifikasi.', 'data' => $permission]);
    }

    public function homeroomPermissions(Request $request): JsonResponse
    {
        $this->permit($request, ['homeroom_attendance.view', 'homeroom_attendance.verify_permission'], ['Wali Kelas']);
        $studentIds = $this->access->homeroomStudentIds($request->user());

        return response()->json(['success' => true, 'data' => StudentAttendancePermission::with('student')
            ->whereIn('student_id', $studentIds)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()->paginate($request->integer('per_page', 15))]);
    }

    public function reviewPermission(Request $request, StudentAttendancePermission $permission): JsonResponse
    {
        $this->permit($request, ['homeroom_attendance.verify_permission'], ['Wali Kelas']);
        abort_unless($this->access->homeroomStudentIds($request->user())->contains($permission->student_id), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['needs_revision', 'approved', 'rejected'])], 'review_notes' => ['nullable', 'string']]);
        $permission->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $this->audit->record($request, 'review_permission', $permission, null, $permission->toArray(), $data['review_notes'] ?? null);

        return response()->json(['success' => true, 'data' => $permission]);
    }

    public function correction(Request $request): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.correct', 'homeroom_attendance.view'], ['Guru', 'Wali Kelas']);
        $data = $request->validate(['attendance_id' => ['required', 'uuid', 'exists:lms_presensi,id'], 'proposed_status' => ['required', Rule::in(['hadir', 'terlambat', 'izin', 'sakit', 'alpa', 'dispensasi'])], 'reason' => ['required', 'string']]);
        $attendance = LmsPresensi::findOrFail($data['attendance_id']);
        abort_unless($this->access->canAccessAttendance($request->user(), $attendance), 403);
        $correction = LessonAttendanceCorrection::create([
            'attendance_id' => $attendance->id, 'previous_status' => $attendance->status_hadir,
            'proposed_status' => $data['proposed_status'], 'reason' => $data['reason'], 'status' => 'submitted',
            'before_data' => $attendance->toArray(), 'after_data' => array_merge($attendance->toArray(), ['status_hadir' => $data['proposed_status']]),
            'requested_by' => $request->user()->id, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
        $this->audit->record($request, 'request_correction', $correction, null, $correction->toArray(), $data['reason']);

        return response()->json(['success' => true, 'message' => 'Koreksi diajukan tanpa mengubah presensi final.', 'data' => $correction], 201);
    }

    public function corrections(Request $request): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.correct', 'homeroom_attendance.view'], ['Guru', 'Wali Kelas']);
        $query = LessonAttendanceCorrection::with(['attendance.siswa', 'attendance.jadwalPelajaran.subject']);
        if (! $request->user()->hasRole('Super Admin')) {
            $query->whereHas('attendance', function ($q) use ($request) {
                if ($request->user()->hasRole('Wali Kelas')) {
                    $q->whereHas('jadwalPelajaran', fn ($sq) => $sq->whereIn('kelas_id', $this->access->homeroomClasses($request->user())->pluck('id')));
                } else {
                    $q->whereIn('jadwal_pelajaran_id', $this->access->teacherSchedules($request->user())->select('id'));
                }
            });
        }

        return response()->json(['success' => true, 'data' => $query->latest()->paginate($request->integer('per_page', 15))]);
    }

    public function reviewCorrection(Request $request, LessonAttendanceCorrection $correction): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.unlock'], []);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_unless(in_array($correction->status, ['submitted', 'reviewing']), 422, 'Koreksi sudah diproses.');
        DB::transaction(function () use ($correction, $data, $request) {
            if ($data['status'] === 'approved') {
                $correction->attendance()->lockForUpdate()->firstOrFail()->update([
                    'status_hadir' => $correction->proposed_status,
                    'verification_status' => 'verified',
                    'updated_by' => $request->user()->id,
                ]);
            }
            $correction->update([
                ...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
                'approved_by' => $data['status'] === 'approved' ? $request->user()->id : null,
                'approved_at' => $data['status'] === 'approved' ? now() : null,
            ]);
        });
        $this->audit->record($request, $data['status'].'_correction', $correction, null, $correction->fresh()->toArray(), $data['review_notes'] ?? null);

        return response()->json(['success' => true, 'message' => 'Koreksi berhasil diproses.', 'data' => $correction->fresh('attendance')]);
    }

    public function cancelCorrection(Request $request, LessonAttendanceCorrection $correction): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.correct'], ['Guru']);
        abort_unless($correction->requested_by === $request->user()->id || $request->user()->hasRole('Super Admin'), 403);
        abort_unless(in_array($correction->status, ['submitted', 'reviewing']), 422, 'Koreksi tidak dapat dibatalkan.');
        $old = $correction->toArray();
        $correction->update(['status' => 'cancelled']);
        $this->audit->record($request, 'cancel_correction', $correction, $old, $correction->toArray());

        return response()->json(['success' => true, 'message' => 'Koreksi dibatalkan.', 'data' => $correction]);
    }

    public function homeroomDashboard(Request $request): JsonResponse
    {
        $this->permit($request, ['homeroom_attendance.dashboard'], ['Wali Kelas']);
        $classIds = $this->access->homeroomClasses($request->user())->pluck('id');
        $studentIds = $this->access->homeroomStudentIds($request->user());
        $base = LmsPresensi::whereIn('siswa_id', $studentIds)->whereDate('tanggal', today());

        return response()->json(['success' => true, 'data' => [
            'total_students' => $studentIds->count(), 'present' => (clone $base)->whereIn('status_hadir', ['hadir', 'terlambat'])->count(),
            'late' => (clone $base)->where('status_hadir', 'terlambat')->count(), 'permission' => (clone $base)->where('status_hadir', 'izin')->count(),
            'sick' => (clone $base)->where('status_hadir', 'sakit')->count(), 'absent' => (clone $base)->where('status_hadir', 'alpa')->count(),
            'unverified' => (clone $base)->where('verification_status', '!=', 'verified')->count(),
            'open_follow_ups' => HomeroomAttendanceFollowUp::whereIn('class_id', $classIds)->whereNotIn('status', ['completed', 'closed'])->count(),
        ]]);
    }

    public function followUps(Request $request): JsonResponse
    {
        $this->permit($request, ['homeroom_attendance.follow_up'], ['Wali Kelas']);
        $employee = $this->access->employee($request->user());
        abort_unless($employee, 403, 'Akun belum terhubung dengan data pegawai.');
        $classIds = $this->access->homeroomClasses($request->user())->pluck('id');

        if ($request->isMethod('get')) {
            return response()->json(['success' => true, 'data' => HomeroomAttendanceFollowUp::with('student')
                ->whereIn('class_id', $classIds)
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->latest('case_date')->paginate($request->integer('per_page', 15))]);
        }

        $data = $request->validate([
            'class_id' => ['required', 'uuid', Rule::in($classIds->all())],
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'case_type' => ['required', Rule::in(['absent', 'late_repeatedly', 'permission_repeatedly', 'sick_repeatedly', 'subject_absence', 'unverified'])],
            'case_date' => ['required', 'date'],
            'occurrence_count' => ['nullable', 'integer', 'min:1'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'action' => ['required', 'string'],
            'parent_communication' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:case_date'],
            'notes' => ['nullable', 'string'],
        ]);
        abort_unless($this->access->homeroomStudentIds($request->user())->contains($data['student_id']), 422, 'Siswa bukan anggota rombel tanggung jawab Anda.');
        $item = HomeroomAttendanceFollowUp::create($data + [
            'homeroom_teacher_id' => $employee->id,
            'status' => 'new', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]);
        $this->audit->record($request, 'create_follow_up', $item, null, $item->toArray());

        return response()->json(['success' => true, 'message' => 'Tindak lanjut dibuat.', 'data' => $item], 201);
    }

    public function updateFollowUp(Request $request, HomeroomAttendanceFollowUp $followUp): JsonResponse
    {
        $this->permit($request, ['homeroom_attendance.follow_up'], ['Wali Kelas']);
        abort_unless($this->access->homeroomClasses($request->user())->whereKey($followUp->class_id)->exists(), 403);
        $data = $request->validate([
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'action' => ['sometimes', 'string'],
            'parent_communication' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['new', 'in_progress', 'waiting_parent', 'completed', 'closed'])],
            'notes' => ['nullable', 'string'],
        ]);
        $old = $followUp->toArray();
        $followUp->update($data + ['updated_by' => $request->user()->id]);
        $this->audit->record($request, 'update_follow_up', $followUp, $old, $followUp->toArray());

        return response()->json(['success' => true, 'message' => 'Tindak lanjut diperbarui.', 'data' => $followUp]);
    }

    public function completeFollowUp(Request $request, HomeroomAttendanceFollowUp $followUp): JsonResponse
    {
        $request->merge(['status' => 'completed']);

        return $this->updateFollowUp($request, $followUp);
    }

    public function closeFollowUp(Request $request, HomeroomAttendanceFollowUp $followUp): JsonResponse
    {
        $request->merge(['status' => 'closed']);

        return $this->updateFollowUp($request, $followUp);
    }

    public function report(Request $request): JsonResponse
    {
        $this->permit($request, ['lesson_attendance.export', 'homeroom_attendance.export', 'student_attendance.view_own'], [
            'Guru', 'Wali Kelas', 'Siswa', 'Kepala Sekolah', 'kepala_sekolah', 'kepsek',
            'Yayasan', 'Pengurus Yayasan', 'Ketua Yayasan', 'Divisi Pendidikan', 'Tata Usaha', 'Admin',
        ]);
        $query = LmsPresensi::query()->with([
            'siswa:id,nis,nisn,full_name,gender,photo,photo_thumb,kelas_id,unit_id,is_active',
            'siswa.kelas:id,nama_kelas,kode_kelas,unit_pendidikan_id',
            'siswa.kelas.unitPendidikan:id,code,name,level',
            'siswa.educationUnit:id,code,name,level',
            'jadwalPelajaran.subject:id,code,name',
            'jadwalPelajaran.kelas:id,nama_kelas,kode_kelas,unit_pendidikan_id',
            'jadwalPelajaran.kelas.unitPendidikan:id,code,name,level',
        ]);
        $user = $request->user();

        $isFoundationAdmin = $user->hasAnyRole([
            'Super Admin', 'super_admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
            'Pengurus Yayasan', 'pengurus_yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan',
        ]);

        if (! $isFoundationAdmin) {
            if ($user->hasRole('Siswa')) {
                $query->where('siswa_id', $this->access->student($user)?->id ?? '__none__');
            } elseif ($user->hasAnyRole(['Kepala Sekolah', 'kepala_sekolah', 'kepsek', 'Divisi Pendidikan', 'Tata Usaha', 'Admin'])) {
                $unitIds = $this->accessScopeService->accessibleEducationUnits($user)->pluck('id')->filter()->values();
                if ($request->filled('unit_id')) {
                    $requestedUnit = $request->string('unit_id')->toString();
                    $this->accessScopeService->assertEducationUnitAccess($user, $requestedUnit);
                    $unitIds = collect([$requestedUnit]);
                }
                if ($unitIds->isNotEmpty()) {
                    $query->where(function ($q) use ($unitIds) {
                        $q->whereHas('siswa', function ($sq) use ($unitIds) {
                            $sq->where(function ($sq2) use ($unitIds) {
                                $sq2->whereIn('unit_id', $unitIds)
                                    ->orWhere(function ($sq3) use ($unitIds) {
                                        $sq3->whereNull('unit_id')
                                            ->whereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $unitIds));
                                    });
                            });
                        })->orWhereHas('jadwalPelajaran.kelas', function ($jq) use ($unitIds) {
                            $jq->whereIn('unit_pendidikan_id', $unitIds);
                        });
                    })
                    ->whereDoesntHave('siswa', function ($sq) use ($unitIds) {
                        $sq->whereNotNull('unit_id')->whereNotIn('unit_id', $unitIds);
                    })
                    ->whereDoesntHave('jadwalPelajaran.kelas', function ($jq) use ($unitIds) {
                        $jq->whereNotNull('unit_pendidikan_id')->whereNotIn('unit_pendidikan_id', $unitIds);
                    });
                }
            } elseif ($user->hasRole('Wali Kelas')) {
                $query->whereHas('jadwalPelajaran', fn ($q) => $q->whereIn('kelas_id', $this->access->homeroomClasses($user)->pluck('id')));
            } else {
                $query->whereIn('jadwal_pelajaran_id', $this->access->teacherSchedules($user)->select('id'));
            }
        }

        if ($isFoundationAdmin && $request->filled('unit_id')) {
            $requestedUnit = $request->string('unit_id')->toString();
            $unitIds = collect([$requestedUnit]);
            $query->where(function ($q) use ($unitIds) {
                $q->whereHas('siswa', function ($sq) use ($unitIds) {
                    $sq->where(function ($sq2) use ($unitIds) {
                        $sq2->whereIn('unit_id', $unitIds)
                            ->orWhere(function ($sq3) use ($unitIds) {
                                $sq3->whereNull('unit_id')
                                    ->whereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $unitIds));
                            });
                    });
                })->orWhereHas('jadwalPelajaran.kelas', function ($jq) use ($unitIds) {
                    $jq->whereIn('unit_pendidikan_id', $unitIds);
                });
            })
            ->whereDoesntHave('siswa', function ($sq) use ($unitIds) {
                $sq->whereNotNull('unit_id')->whereNotIn('unit_id', $unitIds);
            })
            ->whereDoesntHave('jadwalPelajaran.kelas', function ($jq) use ($unitIds) {
                $jq->whereNotNull('unit_pendidikan_id')->whereNotIn('unit_pendidikan_id', $unitIds);
            });
        }

        // Only include active students (is_active = true)
        $query->whereHas('siswa', function ($sq) {
            $sq->where('is_active', true);
            if (\Illuminate\Support\Facades\Schema::hasColumn('students', 'status')) {
                $sq->whereNotIn('status', ['Berhenti', 'Lulus', 'Nonaktif', 'berhenti', 'lulus', 'nonaktif']);
            }
        });

        $query->when($request->filled('class_id'), function ($q) use ($request) {
            $classId = $request->string('class_id')->toString();
            $q->where(function ($sq) use ($classId) {
                $sq->whereHas('jadwalPelajaran', fn ($jq) => $jq->where('kelas_id', $classId))
                  ->orWhereHas('siswa', fn ($sq2) => $sq2->where('kelas_id', $classId));
            });
        })
            ->when($request->filled('student_id'), fn ($q) => $q->where('siswa_id', $request->string('student_id')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('tanggal', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('tanggal', '<=', $request->date('date_to')))
            ->when($request->filled('status'), fn ($q) => $q->where('status_hadir', $request->string('status')->toString()))
            ->when($request->filled('subject_id'), fn ($q) => $q->whereHas('jadwalPelajaran', fn ($sq) => $sq->where('subject_id', $request->string('subject_id')->toString())));

        $rows = $query->get();

        return response()->json(['success' => true, 'data' => [
            'summary' => [
                'total' => $rows->count(),
                'present' => $rows->where('status_hadir', 'hadir')->count(),
                'late' => $rows->where('status_hadir', 'terlambat')->count(),
                'permission' => $rows->where('status_hadir', 'izin')->count(),
                'sick' => $rows->where('status_hadir', 'sakit')->count(),
                'absent' => $rows->where('status_hadir', 'alpa')->count(),
            ],
            'rows' => $rows,
        ]]);
    }
}
