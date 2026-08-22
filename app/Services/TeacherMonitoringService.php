<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\LessonAttendanceSession;
use App\Models\Semester;
use App\Models\TeachingAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TeacherMonitoringService
{
    public function __construct(private AccessScopeService $scope) {}

    public function overview(User $user, Carbon $date, array $params = []): array
    {
        $period = $params['period'] ?? 'harian';
        $unitId = $params['unit_id'] ?? null;
        $now = now();
        $threshold = (int) config('attendance.presence_online_threshold_seconds', 90);

        // Master data untuk dropdown filter di UI
        $academicYears = AcademicYear::query()
            ->select(['id', 'name', 'is_active', 'start_date', 'end_date'])
            ->orderByDesc('start_date')
            ->get();

        $semesters = Semester::query()
            ->select(['id', 'academic_year_id', 'name', 'sequence', 'is_active', 'start_date', 'end_date'])
            ->orderBy('sequence')
            ->get();

        // Cek Hak Akses Global (Super Admin, Admin, Yayasan, Pengurus Yayasan, dll) vs Pengelola Unit (Kepala Sekolah)
        $hasGlobalAccess = $this->scope->hasGlobalScope($user);
        $accessibleUnitQuery = $this->scope->accessibleEducationUnits($user);
        $accessibleUnitIds = $accessibleUnitQuery->pluck('id');

        // Master data unit pendidikan untuk UI:
        // Jika SuperAdmin/Admin/Yayasan -> Tampilkan seluruh unit pendidikan
        // Jika Kepala Sekolah -> Hanya tampilkan unit pendidikan yang dipimpin/dikelola olehnya
        $educationUnitsQuery = $hasGlobalAccess
            ? EducationUnit::query()
            : $accessibleUnitQuery;

        $educationUnits = $educationUnitsQuery
            ->select(['id', 'name', 'code'])
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->orderBy('name')
            ->get();

        // Target Date parsing
        $targetDate = !empty($params['date']) ? Carbon::parse($params['date'])->startOfDay() : $date->copy()->startOfDay();

        // Menentukan rentang tanggal (Start & End Date) sesuai periode
        if ($period === 'mingguan') {
            $startDate = !empty($params['start_date'])
                ? Carbon::parse($params['start_date'])->startOfDay()
                : $targetDate->copy()->startOfWeek(Carbon::MONDAY);
            $endDate = !empty($params['end_date'])
                ? Carbon::parse($params['end_date'])->endOfDay()
                : $targetDate->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif ($period === 'bulanan') {
            $month = !empty($params['month']) ? (int) $params['month'] : (int) $targetDate->format('n');
            $year = !empty($params['year']) ? (int) $params['year'] : (int) $targetDate->format('Y');
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        } elseif ($period === 'semester') {
            $semesterObj = !empty($params['semester_id'])
                ? $semesters->firstWhere('id', $params['semester_id'])
                : ($semesters->firstWhere('is_active', true) ?? $semesters->first());
            $startDate = $semesterObj?->start_date ? Carbon::parse($semesterObj->start_date)->startOfDay() : $targetDate->copy()->startOfMonth();
            $endDate = $semesterObj?->end_date ? Carbon::parse($semesterObj->end_date)->endOfDay() : $targetDate->copy()->endOfMonth();
        } elseif ($period === 'tahunan') {
            $academicYearObj = !empty($params['academic_year_id'])
                ? $academicYears->firstWhere('id', $params['academic_year_id'])
                : ($academicYears->firstWhere('is_active', true) ?? $academicYears->first());
            $startDate = $academicYearObj?->start_date ? Carbon::parse($academicYearObj->start_date)->startOfDay() : $targetDate->copy()->startOfYear();
            $endDate = $academicYearObj?->end_date ? Carbon::parse($academicYearObj->end_date)->endOfDay() : $targetDate->copy()->endOfYear();
        } else {
            // Harian
            $startDate = $targetDate->copy()->startOfDay();
            $endDate = $targetDate->copy()->endOfDay();
        }

        // Query Dasar Jadwal Guru
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
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereNull('is_active'));

        // Jika bukan global access (misal Kepala Sekolah), batasi jadwal hanya pada unit pendidikan yang dipimpin/dikelola
        if (! $hasGlobalAccess) {
            $schedulesQuery->where(function (Builder $query) use ($accessibleUnitIds) {
                $query->whereHas('employee', fn (Builder $q) => $q->whereIn('unit_id', $accessibleUnitIds))
                    ->orWhereHas('kelas', fn (Builder $q) => $q->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        }

        if ($period === 'harian') {
            $schedulesQuery->where('day_of_week', $targetDate->dayOfWeekIso)
                ->whereHas('academicYear', function (Builder $query) use ($targetDate): void {
                    $query->whereDate('start_date', '<=', $targetDate->toDateString())
                        ->whereDate('end_date', '>=', $targetDate->toDateString());
                })
                ->whereHas('semester', function (Builder $query) use ($targetDate): void {
                    $query->whereDate('start_date', '<=', $targetDate->toDateString())
                        ->whereDate('end_date', '>=', $targetDate->toDateString());
                });
        }

        if (!empty($unitId)) {
            $schedulesQuery->where(function (Builder $query) use ($unitId) {
                $query->whereHas('employee', fn (Builder $q) => $q->where('unit_id', $unitId))
                    ->orWhereHas('kelas', fn (Builder $q) => $q->where('unit_pendidikan_id', $unitId));
            });
        }

        if (! $user->hasAnyRole(['Super Admin', 'super_admin'])) {
            $schedulesQuery->whereIn('employee_id', $this->scope->accessibleEmployees($user)->select('id'));
        }

        $schedules = $schedulesQuery->orderBy('time_start')->get();
        $scheduleIds = $schedules->pluck('id');

        // Ambil Data Presensi & Sesi Mengajar pada Rentang Tanggal
        $attendances = TeachingAttendance::query()
            ->whereIn('schedule_id', $scheduleIds)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('session')
            ->get();

        $sessions = LessonAttendanceSession::query()
            ->whereIn('schedule_id', $scheduleIds)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('teachingAttendance')
            ->get();

        if ($period === 'harian') {
            $attendancesBySchedule = $attendances->keyBy('schedule_id');
            $sessionsBySchedule = $sessions->keyBy('schedule_id');

            $rows = $schedules->map(function (ClassSchedule $schedule) use ($attendancesBySchedule, $sessionsBySchedule, $now, $threshold): array {
                $employee = $schedule->employee;
                $user = $employee?->user;
                $attendance = $attendancesBySchedule->get($schedule->id);
                $session = $sessionsBySchedule->get($schedule->id) ?: $attendance?->session;
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
                        'day_of_week' => $schedule->day_of_week,
                        'nama_hari' => ClassSchedule::DAY_NAMES[$schedule->day_of_week] ?? '-',
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

            $summary = [
                'scheduled_today' => $rows->count(),
                'checked_in' => $rows->whereNotIn('attendance_status', ['belum_presensi'])->count(),
                'not_checked_in' => $rows->where('attendance_status', 'belum_presensi')->count(),
                'late' => $rows->where('attendance_status', 'terlambat')->count(),
                'active' => $rows->where('teaching_status', 'active')->count(),
                'completed' => $rows->where('teaching_status', 'completed')->count(),
            ];
        } else {
            // Mode Periode Banyak Hari (mingguan, bulanan, semester, tahunan)
            $attendancesGrouped = $attendances->groupBy('schedule_id');
            $sessionsGrouped = $sessions->groupBy('schedule_id');

            $rows = $schedules->map(function (ClassSchedule $schedule) use ($attendancesGrouped, $sessionsGrouped, $now, $threshold): array {
                $employee = $schedule->employee;
                $user = $employee?->user;
                $schedAttendances = $attendancesGrouped->get($schedule->id, collect());
                $schedSessions = $sessionsGrouped->get($schedule->id, collect());

                $totalPresensi = $schedAttendances->count();
                $totalHadir = $schedAttendances->whereIn('status', ['hadir', 'tepat_waktu'])->count();
                $totalTerlambat = $schedAttendances->where('status', 'terlambat')->count();
                $totalBelumPresensi = max(0, $schedAttendances->where('status', 'belum_presensi')->count());

                $lastAttendance = $schedAttendances->sortByDesc('check_in_at')->first();
                $lastSession = $schedSessions->sortByDesc('session_started_at')->first();

                $lastDeviceSeen = $user?->devices?->sortByDesc('last_active_at')->first()?->last_active_at;
                $lastLogin = $user?->loginEvents?->sortByDesc('created_at')->first()?->created_at;
                $lastActivity = collect([$lastDeviceSeen, $lastAttendance?->check_in_at, $lastSession?->session_started_at, $lastSession?->session_closed_at, $lastLogin])
                    ->filter()
                    ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
                    ->sortDesc()
                    ->first();
                $online = $lastDeviceSeen && ! $lastDeviceSeen->lt($now->copy()->subSeconds($threshold));

                $ketercapaian = $totalPresensi > 0 ? round((($totalHadir + $totalTerlambat) / $totalPresensi) * 100, 1) : 0;

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
                        'day_of_week' => $schedule->day_of_week,
                        'nama_hari' => ClassSchedule::DAY_NAMES[$schedule->day_of_week] ?? '-',
                        'time_start' => $schedule->time_start,
                        'time_end' => $schedule->time_end,
                    ],
                    'online_status' => $online ? 'online' : 'offline',
                    'last_seen_at' => $lastDeviceSeen?->toIso8601String(),
                    'last_activity_at' => $lastActivity?->toIso8601String(),
                    'attendance_status' => $lastAttendance?->status ?: 'belum_presensi',
                    'attendance_at' => $lastAttendance?->check_in_at?->toIso8601String(),
                    'teaching_status' => $lastSession?->teaching_session_status ?: 'belum_presensi',
                    'session_started_at' => $lastSession?->session_started_at?->toIso8601String(),
                    'session_completed_at' => $lastSession?->session_closed_at?->toIso8601String(),
                    'student_attendance_status' => 'Belum tersedia (Step 05)',
                    'period_stats' => [
                        'total_records' => $totalPresensi,
                        'total_hadir' => $totalHadir,
                        'total_terlambat' => $totalTerlambat,
                        'total_belum_presensi' => $totalBelumPresensi,
                        'ketercapaian_persen' => $ketercapaian,
                    ],
                ];
            })->values();

            $summary = [
                'scheduled_today' => $rows->count(),
                'checked_in' => $rows->reduce(fn ($acc, $r) => $acc + ($r['period_stats']['total_hadir'] ?? 0), 0),
                'not_checked_in' => $rows->reduce(fn ($acc, $r) => $acc + ($r['period_stats']['total_belum_presensi'] ?? 0), 0),
                'late' => $rows->reduce(fn ($acc, $r) => $acc + ($r['period_stats']['total_terlambat'] ?? 0), 0),
                'active' => $rows->where('teaching_status', 'active')->count(),
                'completed' => $rows->reduce(fn ($acc, $r) => $acc + ($r['period_stats']['total_hadir'] + $r['period_stats']['total_terlambat'] > 0 ? 1 : 0), 0),
            ];
        }

        return [
            'date' => $targetDate->toDateString(),
            'period' => $period,
            'range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'filters' => [
                'unit_id' => $unitId,
                'period' => $period,
                'date' => $targetDate->toDateString(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'month' => $params['month'] ?? (int) $targetDate->format('n'),
                'year' => $params['year'] ?? (int) $targetDate->format('Y'),
                'semester_id' => $params['semester_id'] ?? null,
                'academic_year_id' => $params['academic_year_id'] ?? null,
            ],
            'master_data' => [
                'academic_years' => $academicYears,
                'semesters' => $semesters,
                'education_units' => $educationUnits,
            ],
            'server_time' => $now->toIso8601String(),
            'timezone' => config('app.timezone'),
            'presence_threshold_seconds' => $threshold,
            'summary' => $summary,
            'rows' => $rows,
        ];
    }
}
