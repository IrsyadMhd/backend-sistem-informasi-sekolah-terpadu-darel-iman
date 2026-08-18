<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\LessonAttendanceSession;
use App\Models\TeachingAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TeacherMonitoringService
{
    public function __construct(private AccessScopeService $scope) {}

    public function overview(User $user, Carbon $date): array
    {
        $schedulesQuery = ClassSchedule::query()
            ->with([
                'subject',
                'kelas.unitPendidikan',
                'academicYear',
                'semester',
                'employee.user.devices',
                'employee.user.loginEvents',
                'employee.unit',
            ])
            ->whereNotNull('employee_id')
            ->where('day_of_week', $date->dayOfWeekIso)
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereNull('is_active'))
            ->whereHas('academicYear', function (Builder $query) use ($date): void {
                $query->whereDate('start_date', '<=', $date->toDateString())
                    ->whereDate('end_date', '>=', $date->toDateString());
            })
            ->whereHas('semester', function (Builder $query) use ($date): void {
                $query->whereDate('start_date', '<=', $date->toDateString())
                    ->whereDate('end_date', '>=', $date->toDateString());
            });

        if (! $user->hasAnyRole(['Super Admin', 'super_admin'])) {
            $schedulesQuery->whereIn('employee_id', $this->scope->accessibleEmployees($user)->select('id'));
        }

        $schedules = $schedulesQuery->orderBy('time_start')->get();
        $scheduleIds = $schedules->pluck('id');
        $attendances = TeachingAttendance::query()
            ->whereIn('schedule_id', $scheduleIds)
            ->whereDate('attendance_date', $date->toDateString())
            ->with('session')
            ->get()
            ->keyBy('schedule_id');
        $sessions = LessonAttendanceSession::query()
            ->whereIn('schedule_id', $scheduleIds)
            ->whereDate('attendance_date', $date->toDateString())
            ->with('teachingAttendance')
            ->get()
            ->keyBy('schedule_id');

        $now = now();
        $threshold = (int) config('attendance.presence_online_threshold_seconds', 90);
        $rows = $schedules->map(function (ClassSchedule $schedule) use ($attendances, $sessions, $now, $threshold): array {
            $employee = $schedule->employee;
            $user = $employee?->user;
            $attendance = $attendances->get($schedule->id);
            $session = $sessions->get($schedule->id) ?: $attendance?->session;
            $lastDeviceSeen = $user?->devices?->sortByDesc('last_active_at')->first()?->last_active_at;
            $lastLogin = $user?->loginEvents?->sortByDesc('created_at')->first()?->created_at;
            $lastActivity = collect([$lastDeviceSeen, $attendance?->check_in_at, $session?->session_started_at, $session?->session_closed_at, $lastLogin])
                ->filter()
                ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
                ->sortDesc()
                ->first();
            $online = $lastDeviceSeen && ! $lastDeviceSeen->lt($now->copy()->subSeconds($threshold));

            return [
                'id' => $schedule->id,
                'teacher' => [
                    'id' => $employee?->id,
                    'name' => $employee?->nama_lengkap ?: $user?->name,
                    'photo_url' => $employee?->photo_url,
                ],
                'unit' => $employee?->unit?->only(['id', 'name', 'code']),
                'schedule' => [
                    'id' => $schedule->id,
                    'subject' => $schedule->subject?->name ?: $schedule->subject?->nama_mapel,
                    'class' => $schedule->kelas?->nama_kelas ?: $schedule->kelas?->kode_kelas,
                    'time_start' => $schedule->time_start,
                    'time_end' => $schedule->time_end,
                ],
                'online_status' => $online ? 'online' : 'offline',
                'last_seen_at' => $lastDeviceSeen?->toIso8601String(),
                'last_activity_at' => $lastActivity?->toIso8601String(),
                'attendance_status' => $attendance?->status ?: 'belum_presensi',
                'attendance_at' => $attendance?->check_in_at?->toIso8601String(),
                'teaching_status' => $session?->teaching_session_status ?: 'belum_presensi',
                'session_started_at' => $session?->session_started_at?->toIso8601String(),
                'session_completed_at' => $session?->session_closed_at?->toIso8601String(),
                'student_attendance_status' => 'Belum tersedia (Step 05)',
            ];
        })->values();

        return [
            'date' => $date->toDateString(),
            'server_time' => $now->toIso8601String(),
            'timezone' => config('app.timezone'),
            'presence_threshold_seconds' => $threshold,
            'summary' => [
                'scheduled_today' => $rows->count(),
                'checked_in' => $rows->whereNotIn('attendance_status', ['belum_presensi'])->count(),
                'not_checked_in' => $rows->where('attendance_status', 'belum_presensi')->count(),
                'late' => $rows->where('attendance_status', 'terlambat')->count(),
                'active' => $rows->where('teaching_status', 'active')->count(),
                'completed' => $rows->where('teaching_status', 'completed')->count(),
            ],
            'rows' => $rows,
        ];
    }
}
