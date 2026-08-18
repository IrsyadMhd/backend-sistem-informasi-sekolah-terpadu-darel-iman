<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttendanceAccessService
{
    public function employee(User $user): ?Employee
    {
        return Employee::where('user_id', $user->id)->first();
    }

    public function student(User $user): ?Student
    {
        return Student::where('user_id', $user->id)->first();
    }

    public function teacherSchedules(User $user): Builder
    {
        $employee = $this->employee($user);

        return ClassSchedule::query()->where(function (Builder $query) use ($employee, $user) {
            if ($employee) {
                $query->where('employee_id', $employee->id);
            }
            $query->orWhereHas('teacher', fn (Builder $q) => $q->where('user_id', $user->id));
        });
    }

    public function homeroomClasses(User $user): Builder
    {
        $employee = $this->employee($user);

        return Kelas::query()->where('wali_kelas_id', $employee?->id ?? '__none__');
    }

    public function homeroomStudentIds(User $user): Collection
    {
        $kelasIds = $this->homeroomClasses($user)->pluck('id');
        $classIds = collect();
        foreach ($this->homeroomClasses($user)->get(['id', 'tahun_ajaran_id', 'semester_id', 'nama_kelas', 'kode_kelas']) as $kelas) {
            $classIds = $classIds->merge(SchoolClass::query()
                ->where('academic_year_id', $kelas->tahun_ajaran_id)
                ->where('semester_id', $kelas->semester_id)
                ->whereIn('name', array_filter([$kelas->nama_kelas, $kelas->kode_kelas]))
                ->pluck('id'));
        }

        return Student::active()->where(function (Builder $query) use ($kelasIds, $classIds) {
            $query->whereIn('kelas_id', $kelasIds)
                ->orWhereIn('class_id', $classIds->unique());
        })->pluck('id');
    }

    /**
     * Jadwal yang sedang dapat diambil presensinya oleh user.
     * Guru memakai jadwal mengajarnya, sedangkan wali kelas memakai jadwal
     * rombelnya sebagai petugas pengganti yang tetap tercatat pada audit.
     */
    public function activeSchedules(User $user, ?Carbon $at = null): Collection
    {
        $at ??= now();
        $early = (int) config('attendance.active_schedule_early_minutes', 15);
        $late = (int) config('attendance.active_schedule_late_minutes', 0);
        $teacherScheduleIds = $this->teacherSchedules($user)->pluck('id');
        $homeroomClassIds = $user->hasRole('Wali Kelas')
            ? $this->homeroomClasses($user)->pluck('id')
            : collect();

        if ($teacherScheduleIds->isEmpty() && $homeroomClassIds->isEmpty()) {
            return collect();
        }

        return ClassSchedule::query()
            ->with(['subject', 'kelas', 'schoolClass', 'employee', 'teacher'])
            ->where('day_of_week', $at->dayOfWeekIso)
            ->where(fn (Builder $q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->where(function (Builder $query) use ($teacherScheduleIds, $homeroomClassIds) {
                $query->whereIn('id', $teacherScheduleIds);
                if ($homeroomClassIds->isNotEmpty()) {
                    $query->orWhereIn('kelas_id', $homeroomClassIds);
                }
            })
            ->orderBy('time_start')
            ->get()
            ->filter(function (ClassSchedule $schedule) use ($at, $early, $late) {
                $start = $at->copy()->setTimeFromTimeString($schedule->time_start)->subMinutes($early);
                $end = $at->copy()->setTimeFromTimeString($schedule->time_end)->addMinutes($late);

                return $at->betweenIncluded($start, $end);
            })
            ->map(function (ClassSchedule $schedule) use ($teacherScheduleIds, $at) {
                // A Step 04 scan creates the lesson session before any
                // student row exists, so resolving through lms_presensi
                // would hide the active schedule at the start of class.
                $session = LessonAttendanceSession::query()
                    ->where('schedule_id', $schedule->id)
                    ->whereDate('attendance_date', $at->toDateString())
                    ->first();
                $isOwner = $teacherScheduleIds->contains($schedule->id);

                $schedule->setAttribute('attendance_access', $isOwner ? 'teacher' : 'homeroom_substitute');
                $schedule->setAttribute('requires_substitute_reason', ! $isOwner);
                $schedule->setAttribute('attendance_status', $session?->status ?? 'not_started');
                $schedule->setAttribute('attendance_session_id', $session?->id);

                return $schedule;
            })
            ->values();
    }

    public function assertCanTakeActiveSchedule(User $user, string $scheduleId, Carbon $at): ClassSchedule
    {
        $schedule = $this->activeSchedules($user, $at)->firstWhere('id', $scheduleId);
        if (! $schedule) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal tidak aktif saat ini atau tidak dapat diakses oleh akun Anda.',
            ]);
        }

        return $schedule;
    }

    public function assertTeacherOwnsSchedule(User $user, string $scheduleId): ClassSchedule
    {
        $schedule = $user->hasRole('Super Admin')
            ? ClassSchedule::find($scheduleId)
            : $this->teacherSchedules($user)->find($scheduleId);
        if (! $schedule) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal tidak ditemukan atau bukan jadwal mengajar Anda.',
            ]);
        }

        return $schedule;
    }

    public function assertStudentInSchedule(ClassSchedule $schedule, string $studentId): void
    {
        if (! $this->studentsForSchedule($schedule)->whereKey($studentId)->exists()) {
            throw ValidationException::withMessages([
                'student_id' => 'Siswa tidak terdaftar pada rombel jadwal ini.',
            ]);
        }
    }

    public function studentsForSchedule(ClassSchedule $schedule): Builder
    {
        $classIds = collect([$schedule->class_id])->filter();
        $kelasIds = collect([$schedule->kelas_id])->filter();
        if ($schedule->kelas_id) {
            $kelas = Kelas::find($schedule->kelas_id);
            if ($kelas) {
                $legacyIds = SchoolClass::query()
                    ->where('academic_year_id', $schedule->academic_year_id)
                    ->where('semester_id', $schedule->semester_id)
                    ->where(function (Builder $query) use ($kelas) {
                        $query->where('name', $kelas->nama_kelas);
                        if ($kelas->kode_kelas) {
                            $query->orWhere('name', $kelas->kode_kelas);
                        }
                    })->pluck('id');
                $classIds = $classIds->merge($legacyIds);
            }
        }

        $scheduleUnitId = $schedule->kelas?->unit_pendidikan_id;

        return Student::query()->active()->where(function (Builder $query) use ($classIds, $kelasIds) {
            $query->whereIn('class_id', $classIds->unique()->values())
                ->orWhereIn('kelas_id', $kelasIds->unique()->values());
        })->when($scheduleUnitId, function (Builder $query) use ($scheduleUnitId): void {
            $query->where(function (Builder $unitQuery) use ($scheduleUnitId): void {
                $unitQuery->where('unit_id', $scheduleUnitId)
                    ->orWhereHas('kelas', fn (Builder $kelasQuery) => $kelasQuery->where('unit_pendidikan_id', $scheduleUnitId));
            });
        });
    }

    public function canAccessAttendance(User $user, LmsPresensi $attendance): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        if ($user->hasRole('Siswa')) {
            return $attendance->siswa_id === $this->student($user)?->id;
        }
        if ($user->hasRole('Wali Kelas')) {
            $schedule = $attendance->jadwalPelajaran;

            return $schedule && $this->homeroomClasses($user)->whereKey($schedule->kelas_id)->exists();
        }

        return $this->teacherSchedules($user)->whereKey($attendance->jadwal_pelajaran_id)->exists();
    }
}
