<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\ParentModel;
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

    public function student(User $user, ?string $childId = null): ?Student
    {
        $directStudent = Student::where('user_id', $user->id)->first();
        if ($directStudent) {
            return $directStudent;
        }

        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            $query = Student::where(function ($q) use ($parent) {
                $q->where('parent_id', $parent->id)
                  ->orWhereHas('parentsPivot', fn ($pivot) => $pivot->whereKey($parent->id));
            });

            if ($childId) {
                return $query->whereKey($childId)->first();
            }

            return $query->first();
        }

        return null;
    }

    public function parentStudentIds(User $user): Collection
    {
        $parent = ParentModel::where('user_id', $user->id)->first();
        if (! $parent) {
            return collect();
        }

        return Student::where(function ($q) use ($parent) {
            $q->where('parent_id', $parent->id)
              ->orWhereHas('parentsPivot', fn ($pivot) => $pivot->whereKey($parent->id));
        })->pluck('id');
    }

    public function canAccessStudent(User $user, string $studentId): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        $student = $this->student($user, $studentId);
        if ($student && $student->id === $studentId) {
            return true;
        }

        return $this->homeroomStudentIds($user)->contains($studentId);
    }

    public function teacherSchedules(User $user): Builder
    {
        if ($user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Tata Usaha', 'TU', 'tata_usaha', 'Kepala Sekolah', 'kepala_sekolah', 'Divisi Pendidikan', 'divisi_pendidikan'])) {
            $unitId = $user->unit_id ?? $user->education_unit_id ?? $user->employee?->unit_id;

            return ClassSchedule::query()->when($unitId, fn ($q) => $q->whereHas('kelas', fn ($k) => $k->where('unit_pendidikan_id', $unitId)));
        }

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
        if (! $schedule && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Tata Usaha', 'TU', 'tata_usaha', 'Kepala Sekolah', 'kepala_sekolah', 'Divisi Pendidikan', 'divisi_pendidikan'])) {
            $schedule = ClassSchedule::with(['subject', 'kelas', 'schoolClass', 'employee', 'teacher'])->find($scheduleId);
        }
        if (! $schedule) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Jadwal tidak aktif saat ini atau tidak dapat diakses oleh akun Anda.',
            ]);
        }

        return $schedule;
    }

    public function assertTeacherOwnsSchedule(User $user, string $scheduleId): ClassSchedule
    {
        if ($user->hasAnyRole(['Super Admin', 'super_admin', 'Admin TU', 'Tata Usaha', 'TU', 'tata_usaha', 'Admin', 'admin', 'Kepala Sekolah', 'kepala_sekolah', 'KepalaSekolah', 'Divisi Pendidikan', 'divisi_pendidikan'])) {
            $schedule = ClassSchedule::find($scheduleId);
        } else {
            $schedule = $this->teacherSchedules($user)->find($scheduleId);

            if (! $schedule && $user->hasRole('Wali Kelas')) {
                $homeroomIds = $this->homeroomClasses($user)->pluck('id');
                $schedule = ClassSchedule::whereIn('kelas_id', $homeroomIds)->find($scheduleId);
            }

            if (! $schedule) {
                // Check if it's in the user's active schedules
                $schedule = $this->activeSchedules($user)->firstWhere('id', $scheduleId);
            }

            if (! $schedule) {
                // Check if user is employee in the same unit
                $employee = $this->employee($user);
                if ($employee && $employee->unit_id) {
                    $schedule = ClassSchedule::whereHas('kelas', fn ($q) => $q->where('unit_pendidikan_id', $employee->unit_id))->find($scheduleId);
                }
            }
        }

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
            $classIds->push($schedule->kelas_id);
            $kelas = Kelas::find($schedule->kelas_id);
            if ($kelas) {
                $legacyIds = SchoolClass::query()
                    ->when($schedule->academic_year_id, fn ($q) => $q->where('academic_year_id', $schedule->academic_year_id))
                    ->when($schedule->semester_id, fn ($q) => $q->where('semester_id', $schedule->semester_id))
                    ->where(function (Builder $query) use ($kelas) {
                        $query->where('name', $kelas->nama_kelas);
                        if ($kelas->kode_kelas) {
                            $query->orWhere('name', $kelas->kode_kelas);
                        }
                    })->pluck('id');
                $classIds = $classIds->merge($legacyIds);
            }
        }
        if ($schedule->class_id) {
            $kelasIds->push($schedule->class_id);
        }

        return Student::query()->active()->where(function (Builder $query) use ($classIds, $kelasIds) {
            $query->whereIn('class_id', $classIds->unique()->values())
                ->orWhereIn('kelas_id', $kelasIds->unique()->values());
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
        if ($user->hasAnyRole(['Kepala Sekolah', 'kepala_sekolah', 'kepsek', 'KepalaSekolah', 'Divisi Pendidikan', 'divisi_pendidikan', 'DivisiPendidikan', 'Kepala Bidang Pendidikan', 'Tata Usaha', 'TU', 'Admin'])) {
            $unitIds = app(AccessScopeService::class)->accessibleEducationUnits($user)->pluck('id');
            $studentUnit = $attendance->siswa?->unit_id ?? $attendance->siswa?->kelas?->unit_pendidikan_id;
            $scheduleUnit = $attendance->jadwalPelajaran?->kelas?->unit_pendidikan_id;

            return ($studentUnit && $unitIds->contains($studentUnit)) || ($scheduleUnit && $unitIds->contains($scheduleUnit));
        }
        if ($user->hasRole('Wali Kelas')) {
            $schedule = $attendance->jadwalPelajaran;

            return $schedule && $this->homeroomClasses($user)->whereKey($schedule->kelas_id)->exists();
        }

        return $this->teacherSchedules($user)->whereKey($attendance->jadwal_pelajaran_id)->exists();
    }
}
