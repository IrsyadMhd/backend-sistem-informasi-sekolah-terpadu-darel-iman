<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\LessonAttendanceSession;
use App\Models\QrCredential;
use App\Models\TeachingAttendance;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeachingAttendanceService
{
    public function __construct(private AttendanceAuditService $audit) {}

    public function teacherEmployee(User $user): Employee
    {
        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->with('user')
            ->first();

        if (! $employee) {
            $teacherEmployeeId = Teacher::query()->where('user_id', $user->id)->value('employee_id');
            $employee = $teacherEmployeeId ? Employee::query()->with('user')->find($teacherEmployeeId) : null;
        }

        if (! $employee || ! in_array(strtolower((string) $employee->status), ['aktif', 'active'], true)) {
            throw ValidationException::withMessages([
                'teacher' => 'Akun guru belum terhubung ke pegawai aktif.',
            ]);
        }

        return $employee;
    }

    public function ownedSchedule(User $user, string $scheduleId): ClassSchedule
    {
        $employee = $this->teacherEmployee($user);
        $schedule = ClassSchedule::query()
            ->with([
                'subject',
                'kelas.unitPendidikan',
                'academicYear',
                'semester',
                'employee.user',
                'teacher.employee',
            ])
            ->whereKey($scheduleId)
            ->where(function (Builder $query) use ($employee, $user): void {
                $query->where('employee_id', $employee->id)
                    ->orWhereHas('teacher', function (Builder $teacherQuery) use ($employee, $user): void {
                        $teacherQuery->where('employee_id', $employee->id)
                            ->orWhere('user_id', $user->id);
                    });
            })
            ->first();

        if (! $schedule) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal tidak ditemukan atau bukan jadwal mengajar Anda.',
            ]);
        }

        return $schedule;
    }

    public function todaySchedules(User $user, Carbon $date): array
    {
        $employee = $this->teacherEmployee($user);
        $schedules = ClassSchedule::query()
            ->with([
                'subject',
                'kelas.unitPendidikan',
                'academicYear',
                'semester',
                'employee.user',
                'teacher.employee',
            ])
            ->where('day_of_week', $date->dayOfWeekIso)
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereNull('is_active'))
            ->where(function (Builder $query) use ($employee, $user): void {
                $query->where('employee_id', $employee->id)
                    ->orWhereHas('teacher', function (Builder $teacherQuery) use ($employee, $user): void {
                        $teacherQuery->where('employee_id', $employee->id)
                            ->orWhere('user_id', $user->id);
                    });
            })
            ->whereHas('academicYear', function (Builder $query) use ($date): void {
                $query->whereDate('start_date', '<=', $date->toDateString())
                    ->whereDate('end_date', '>=', $date->toDateString());
            })
            ->whereHas('semester', function (Builder $query) use ($date): void {
                $query->whereDate('start_date', '<=', $date->toDateString())
                    ->whereDate('end_date', '>=', $date->toDateString());
            })
            ->orderBy('time_start')
            ->get();

        $scheduleIds = $schedules->pluck('id');
        $attendances = TeachingAttendance::query()
            ->whereIn('schedule_id', $scheduleIds)
            ->whereDate('attendance_date', $date->toDateString())
            ->with('session')
            ->get()
            ->keyBy('schedule_id');

        return $schedules->map(function (ClassSchedule $schedule) use ($attendances, $date): array {
            $attendance = $attendances->get($schedule->id);

            return $this->schedulePayload($schedule, $attendance, $attendance?->session, $date);
        })->values()->all();
    }

    public function scan(Request $request, string $scheduleId, string $qrToken): array
    {
        $user = $request->user();
        $employee = $this->teacherEmployee($user);
        $schedule = $this->ownedSchedule($user, $scheduleId);
        $now = now();

        $this->validateScheduleContext($schedule, $now);
        $this->validateScheduleUnit($schedule, $employee);

        $rawToken = trim($qrToken);
        $credential = QrCredential::query()
            ->active()
            ->where('card_type', 'employee_card')
            ->where('token_hash', hash('sha256', $rawToken))
            ->with('employee.user')
            ->first();

        if (! $credential || ! $credential->employee) {
            throw ValidationException::withMessages([
                'qr_token' => 'QR kartu guru tidak valid, sudah dicabut, atau sudah kedaluwarsa.',
            ]);
        }

        if ((string) $credential->employee_id !== (string) $employee->id) {
            throw ValidationException::withMessages([
                'qr_token' => 'QR kartu bukan milik akun guru yang sedang masuk.',
            ]);
        }

        $scheduleEmployeeId = $schedule->employee_id ?: $schedule->teacher?->employee_id;
        if ((string) $scheduleEmployeeId !== (string) $employee->id) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal bukan penugasan guru yang sedang masuk.',
            ]);
        }

        $lateAt = Carbon::parse($now->toDateString().' '.$schedule->time_start)
            ->addMinutes((int) config('attendance.late_tolerance_minutes', 10));
        $status = $now->greaterThan($lateAt) ? 'terlambat' : 'hadir';
        $lateMinutes = $status === 'terlambat'
            ? Carbon::parse($now->toDateString().' '.$schedule->time_start)->diffInMinutes($now)
            : 0;

        $result = DB::transaction(function () use ($request, $employee, $schedule, $credential, $now, $status, $lateMinutes): array {
            // Locking the schedule serializes two scans for the same meeting;
            // the unique schedule/date key remains the final database guard.
            $lockedSchedule = ClassSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $attendance = TeachingAttendance::query()
                ->where('schedule_id', $lockedSchedule->id)
                ->whereDate('attendance_date', $now->toDateString())
                ->lockForUpdate()
                ->first();

            $duplicate = (bool) $attendance;
            if (! $attendance) {
                $unitId = $employee->unit_id ?: $lockedSchedule->kelas?->unit_pendidikan_id;
                if (! $unitId) {
                    throw ValidationException::withMessages([
                        'schedule_id' => 'Jadwal belum memiliki unit pendidikan yang valid.',
                    ]);
                }

                $attendance = TeachingAttendance::query()->create([
                    'schedule_id' => $lockedSchedule->id,
                    'employee_id' => $employee->id,
                    'education_unit_id' => $unitId,
                    'academic_year_id' => $lockedSchedule->academic_year_id,
                    'semester_id' => $lockedSchedule->semester_id,
                    'attendance_date' => $now->toDateString(),
                    'check_in_at' => $now,
                    'status' => $status,
                    'attendance_method' => 'qr_card',
                    'qr_credential_id' => $credential->id,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                    'metadata' => ['late_minutes' => $lateMinutes],
                ]);

                $this->audit->record($request, 'teaching_attendance.scan_qr', $attendance, null, $attendance->toArray());
            }

            $session = LessonAttendanceSession::query()
                ->where('schedule_id', $lockedSchedule->id)
                ->whereDate('attendance_date', $now->toDateString())
                ->lockForUpdate()
                ->first();

            if ($session && in_array($session->status, ['final', 'locked', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi pembelajaran pada jadwal ini sudah ditutup atau dibatalkan.',
                ]);
            }

            if (! $session) {
                $session = LessonAttendanceSession::query()->create([
                    'schedule_id' => $lockedSchedule->id,
                    'attendance_date' => $now->toDateString(),
                    'meeting_number' => 1,
                    'status' => 'draft',
                    'attendance_method' => 'qr_card',
                    'teaching_attendance_id' => $attendance->id,
                    'teaching_session_status' => 'ready',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
            } else {
                $session->update([
                    'teaching_attendance_id' => $session->teaching_attendance_id ?: $attendance->id,
                    'teaching_session_status' => $session->teaching_session_status ?: 'ready',
                    'updated_by' => $request->user()->id,
                ]);
            }

            $credential->update(['last_used_at' => $now]);

            return [
                'duplicate' => $duplicate,
                'attendance' => $attendance->fresh(),
                'session' => $session->fresh(),
                'schedule' => $lockedSchedule->load(['subject', 'kelas.unitPendidikan', 'academicYear', 'semester']),
            ];
        });

        return [
            'scan_status' => $result['duplicate'] ? 'duplicate' : 'success',
            'message' => $result['duplicate']
                ? 'Presensi guru sudah tercatat untuk jadwal ini.'
                : 'Presensi guru berhasil dicatat.',
            ...$result,
        ];
    }

    public function startSession(Request $request, LessonAttendanceSession $session, int $durationMinutes = 60): LessonAttendanceSession
    {
        $employee = $this->teacherEmployee($request->user());
        $schedule = $this->ownedSchedule($request->user(), $session->schedule_id);

        $updated = DB::transaction(function () use ($request, $session, $employee, $schedule, $durationMinutes): LessonAttendanceSession {
            $locked = LessonAttendanceSession::query()->lockForUpdate()->findOrFail($session->id);
            $attendance = $locked->teachingAttendance;

            if (! $attendance || (string) $attendance->employee_id !== (string) $employee->id) {
                throw ValidationException::withMessages([
                    'attendance' => 'Presensi guru yang valid wajib ada sebelum sesi dimulai.',
                ]);
            }
            if ($locked->attendance_date->toDateString() !== now()->toDateString()) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi hanya dapat dimulai pada tanggal presensi.',
                ]);
            }
            if ($locked->teaching_session_status === 'active') {
                return $locked;
            }
            if (! in_array($locked->teaching_session_status, ['ready', 'scheduled'], true)) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi tidak berada pada status siap mengajar.',
                ]);
            }

            $locked->update([
                'teaching_session_status' => 'active',
                'session_started_at' => now(),
                'session_expires_at' => now()->addMinutes($durationMinutes),
                'session_closed_at' => null,
                'updated_by' => $request->user()->id,
            ]);
            $this->audit->record($request, 'teaching_session.start', $locked, null, $locked->toArray());

            return $locked->fresh();
        });

        return $updated->load(['schedule.subject', 'schedule.kelas', 'teachingAttendance']);
    }

    public function closeSession(Request $request, LessonAttendanceSession $session): LessonAttendanceSession
    {
        $employee = $this->teacherEmployee($request->user());
        $this->ownedSchedule($request->user(), $session->schedule_id);

        $updated = DB::transaction(function () use ($request, $session, $employee): LessonAttendanceSession {
            $locked = LessonAttendanceSession::query()->lockForUpdate()->findOrFail($session->id);
            $attendance = $locked->teachingAttendance;
            if (! $attendance || (string) $attendance->employee_id !== (string) $employee->id) {
                throw ValidationException::withMessages([
                    'attendance' => 'Presensi guru tidak ditemukan untuk sesi ini.',
                ]);
            }
            if ($locked->teaching_session_status === 'completed') {
                return $locked;
            }
            if ($locked->teaching_session_status !== 'active') {
                throw ValidationException::withMessages([
                    'session' => 'Sesi belum aktif.',
                ]);
            }

            $locked->update([
                'teaching_session_status' => 'completed',
                'session_closed_at' => now(),
                'session_token_hash' => null,
                'updated_by' => $request->user()->id,
            ]);
            $this->audit->record($request, 'teaching_session.close', $locked, null, $locked->toArray());

            return $locked->fresh();
        });

        return $updated->load(['schedule.subject', 'schedule.kelas', 'teachingAttendance']);
    }

    public function heartbeat(Request $request, string $deviceId, ?string $deviceName = null): UserDevice
    {
        return UserDevice::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'device_id' => $deviceId],
            [
                'device_name' => $deviceName ?: 'Browser Guru',
                'device_type' => 'web',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_active_at' => now(),
            ],
        );
    }

    private function validateScheduleContext(ClassSchedule $schedule, Carbon $at): void
    {
        if ($schedule->is_active === false) {
            throw ValidationException::withMessages(['schedule_id' => 'Jadwal pelajaran tidak aktif.']);
        }
        if (! $schedule->academicYear || ! $schedule->semester) {
            throw ValidationException::withMessages(['schedule_id' => 'Jadwal belum memiliki tahun ajaran dan semester.']);
        }
        if ((int) $schedule->day_of_week !== $at->dayOfWeekIso) {
            throw ValidationException::withMessages(['schedule_id' => 'Hari scan tidak sesuai dengan jadwal.']);
        }

        $date = $at->toDateString();
        if ($at->toDateString() !== now()->toDateString()) {
            throw ValidationException::withMessages(['attendance_date' => 'Presensi guru hanya dapat dicatat untuk hari ini.']);
        }
        if ($schedule->academicYear->start_date && ($at->lt(Carbon::parse($schedule->academicYear->start_date)->startOfDay()) || $at->gt(Carbon::parse($schedule->academicYear->end_date)->endOfDay()))) {
            throw ValidationException::withMessages(['attendance_date' => 'Tanggal berada di luar tahun ajaran jadwal.']);
        }
        if ($schedule->semester->start_date && ($at->lt(Carbon::parse($schedule->semester->start_date)->startOfDay()) || $at->gt(Carbon::parse($schedule->semester->end_date)->endOfDay()))) {
            throw ValidationException::withMessages(['attendance_date' => 'Tanggal berada di luar semester jadwal.']);
        }

        $windowStart = Carbon::parse($date.' '.$schedule->time_start)
            ->subMinutes((int) config('attendance.active_schedule_early_minutes', 15));
        $windowEnd = Carbon::parse($date.' '.$schedule->time_end)
            ->addMinutes((int) config('attendance.active_schedule_late_minutes', 0));
        if (! $at->betweenIncluded($windowStart, $windowEnd)) {
            throw ValidationException::withMessages(['schedule_id' => 'Scan berada di luar waktu presensi jadwal.']);
        }
    }

    private function validateScheduleUnit(ClassSchedule $schedule, Employee $employee): void
    {
        $scheduleUnitId = $schedule->kelas?->unit_pendidikan_id;

        if (! $employee->unit_id || ! $scheduleUnitId || (string) $employee->unit_id !== (string) $scheduleUnitId) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Unit jadwal tidak sesuai dengan unit guru.',
            ]);
        }
    }

    private function schedulePayload(ClassSchedule $schedule, ?TeachingAttendance $attendance, ?LessonAttendanceSession $session, Carbon $date): array
    {
        return [
            'id' => $schedule->id,
            'attendance_date' => $date->toDateString(),
            'day_of_week' => $schedule->day_of_week,
            'day_name' => $schedule->day_name,
            'time_start' => $schedule->time_start,
            'time_end' => $schedule->time_end,
            'subject' => $schedule->subject?->only(['id', 'name', 'nama_mapel', 'kode_mapel']),
            'class' => $schedule->kelas?->only(['id', 'nama_kelas', 'kode_kelas']),
            'unit' => $schedule->kelas?->unitPendidikan?->only(['id', 'name', 'code']),
            'academic_year' => $schedule->academicYear?->only(['id', 'name', 'start_date', 'end_date']),
            'semester' => $schedule->semester?->only(['id', 'name', 'sequence', 'start_date', 'end_date']),
            'attendance' => $attendance?->only(['id', 'status', 'attendance_method', 'attendance_date', 'check_in_at']),
            'session' => $session?->only(['id', 'teaching_session_status', 'session_started_at', 'session_closed_at']),
        ];
    }
}
