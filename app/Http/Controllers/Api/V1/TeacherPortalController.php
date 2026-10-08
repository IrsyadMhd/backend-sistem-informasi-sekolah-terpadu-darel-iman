<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LessonAttendanceSession;
use App\Models\LmsMateri;
use App\Models\LmsModulAjar;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\LmsPresensi;
use App\Models\MasterKurikulum;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\Notification;
use App\Models\PengumumanSekolah;
use App\Models\PortalMessage;
use App\Models\QuranSurah;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAttendancePermission;
use App\Models\StudentGrade;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\TahfizhDailyLog;
use App\Models\Teacher;
use App\Services\AccessScopeService;
use App\Services\RealtimeBroadcastService;
use App\Support\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TeacherPortalController extends Controller
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    private function getTeacherContext(Request $request): ?Teacher
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $teacher = Teacher::query()->where('user_id', $user->id)->first();
        $employee = Employee::query()->where('user_id', $user->id)->first();

        if (! $employee) {
            $employee = Employee::query()->where('email', $user->email)->first();
        }

        if (! $teacher && $employee) {
            $teacher = Teacher::query()->where('employee_id', $employee->id)->first();
            if (! $teacher) {
                $teacher = Teacher::query()->firstOrCreate(
                    ['employee_id' => $employee->id],
                    [
                        'user_id' => $user->id,
                        'employee_number' => $employee->niy ?? $employee->nik ?? ('EMP-' . substr((string) $employee->id, 0, 8)),
                        'full_name' => $employee->nama_lengkap ?? $user->name,
                        'email' => $employee->email ?? $user->email,
                        'education_unit_id' => $employee->unit_id,
                    ]
                );
            }
        }

        return $teacher;
    }

    private function resolveSchoolReferenceId(?string $value, string $modelClass, ?callable $fallback = null): ?string
    {
        if (empty($value)) {
            return null;
        }

        $model = new $modelClass;
        $exists = $model::query()->whereKey($value)->exists();
        if ($exists) {
            return $value;
        }

        return $fallback ? $fallback() : null;
    }

    private function ensureEducationUnit(?string $requestedId = null): EducationUnit
    {
        $educationUnit = EducationUnit::query()->first();
        if ($educationUnit) {
            return $educationUnit;
        }

        return EducationUnit::query()->create([
            'id' => $requestedId ?: (string) Str::uuid(),
            'code' => 'AUTO'.substr((string) Str::uuid(), 0, 6),
            'name' => 'Unit Pendidikan Otomatis',
            'level' => 'Sekolah',
            'description' => 'Dibuat otomatis untuk portal guru',
            'is_active' => true,
        ]);
    }

    private function ensureAcademicYear(?string $requestedId = null): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        if ($academicYear) {
            return $academicYear;
        }

        return AcademicYear::query()->create([
            'id' => $requestedId ?: (string) Str::uuid(),
            'name' => '2025/2026',
            'start_date' => now()->subYear()->startOfYear()->toDateString(),
            'end_date' => now()->subYear()->endOfYear()->toDateString(),
            'is_active' => true,
        ]);
    }

    private function ensureSemester(?string $academicYearId = null, ?string $requestedId = null): Semester
    {
        $semester = Semester::query()->where('academic_year_id', $academicYearId)->first();
        if ($semester) {
            return $semester;
        }

        return Semester::query()->create([
            'id' => $requestedId ?: (string) Str::uuid(),
            'academic_year_id' => $academicYearId ?: $this->ensureAcademicYear()->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_active' => true,
        ]);
    }

    private function ensureKurikulum(?string $educationUnitId = null, ?string $academicYearId = null, ?string $semesterId = null, ?string $requestedId = null): MasterKurikulum
    {
        $educationUnitId = $educationUnitId ?: $this->ensureEducationUnit()->id;
        $academicYearId = $academicYearId ?: $this->ensureAcademicYear()->id;
        $semesterId = $semesterId ?: $this->ensureSemester($academicYearId)->id;

        $kurikulum = MasterKurikulum::query()
            ->where('unit_pendidikan_id', $educationUnitId)
            ->where('tahun_ajaran_id', $academicYearId)
            ->first();
        if ($kurikulum) {
            return $kurikulum;
        }

        $academicYear = AcademicYear::query()->find($academicYearId);
        $startDate = $academicYear?->start_date ?? now()->subYear()->startOfYear()->toDateString();
        $endDate = $academicYear?->end_date ?? now()->addYear()->endOfYear()->toDateString();

        return MasterKurikulum::query()->create([
            'id' => $requestedId ?: (string) Str::uuid(),
            'kode_kurikulum' => 'AUTO-'.substr((string) Str::uuid(), 0, 8),
            'nama_kurikulum' => 'Kurikulum Otomatis',
            'jenis_kurikulum' => 'Nasional',
            'unit_pendidikan_id' => $educationUnitId,
            'jenjang' => 'SD',
            'tahun_ajaran_id' => $academicYearId,
            'semester_id' => $semesterId,
            'tanggal_mulai' => $startDate,
            'tanggal_selesai' => $endDate,
            'status' => true,
        ]);
    }

    private function ensureSubject(?string $requestedId = null, ?string $educationUnitId = null, ?string $createdBy = null): Subject
    {
        if ($requestedId) {
            $subject = Subject::query()->find($requestedId);
            if ($subject) {
                return $subject;
            }
        }

        return Subject::query()->create([
            'id' => $requestedId ?: (string) Str::uuid(),
            'unit_pendidikan_id' => $educationUnitId ?: $this->ensureEducationUnit()->id,
            'kurikulum_id' => $this->ensureKurikulum($educationUnitId)->id,
            'kode_mapel' => 'AUTO'.substr((string) Str::uuid(), 0, 4),
            'nama_mapel' => 'Mapel Otomatis',
            'name' => 'Mapel Otomatis',
            'status' => true,
            'created_by' => $createdBy,
        ]);
    }

    private function ensureKelas(?string $requestedId = null, ?string $educationUnitId = null, ?string $academicYearId = null, ?string $semesterId = null, ?string $createdBy = null): Kelas
    {
        if ($requestedId) {
            $kelas = Kelas::query()->find($requestedId);
            if ($kelas) {
                return $kelas;
            }
        }

        $educationUnitId = $educationUnitId ?: $this->ensureEducationUnit()->id;
        $academicYearId = $academicYearId ?: $this->ensureAcademicYear()->id;
        $semesterId = $semesterId ?: $this->ensureSemester($academicYearId)->id;

        return Kelas::query()->create([
            'id' => $requestedId ?: (string) Str::uuid(),
            'unit_pendidikan_id' => $educationUnitId,
            'tahun_ajaran_id' => $academicYearId,
            'semester_id' => $semesterId,
            'jenjang' => 'SD',
            'tingkat' => '1',
            'kode_kelas' => 'AUTO'.substr((string) Str::uuid(), 0, 4),
            'nama_kelas' => 'Kelas Otomatis',
            'status' => 'Aktif',
            'created_by' => $createdBy,
        ]);
    }

    private function ensureLmsModulAjar(string $guruId, ?string $subjectId, ?string $classId, ?string $semesterId, ?string $academicYearId, ?string $createdBy = null): LmsModulAjar
    {
        $existing = LmsModulAjar::query()
            ->where('guru_id', $guruId)
            ->where('mata_pelajaran_id', $subjectId)
            ->where('kelas_id', $classId)
            ->where('semester_id', $semesterId)
            ->where('tahun_ajaran_id', $academicYearId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $kelas = Kelas::query()->findOrFail($classId);
        $educationUnit = EducationUnit::query()->findOrFail($kelas->unit_pendidikan_id);
        $academicYear = AcademicYear::query()->findOrFail($academicYearId);
        $semester = Semester::query()
            ->where('academic_year_id', $academicYear->id)
            ->findOrFail($semesterId);
        $subject = Subject::query()
            ->where(fn (Builder $query) => $query
                ->whereNull('unit_pendidikan_id')
                ->orWhere('unit_pendidikan_id', $educationUnit->id))
            ->findOrFail($subjectId);

        $kurikulum = MasterKurikulum::query()
            ->where('unit_pendidikan_id', $educationUnit->id)
            ->where('tahun_ajaran_id', $academicYear->id)
            ->firstOrFail();
        $subjectName = $subject->name ?? $subject->nama_mapel;

        return LmsModulAjar::create([
            'unit_pendidikan_id' => $educationUnit->id,
            'kurikulum_id' => $kurikulum->id,
            'mata_pelajaran_id' => $subject->id,
            'guru_id' => $guruId,
            'kelas_id' => $kelas->id,
            'semester_id' => $semester->id,
            'tahun_ajaran_id' => $academicYear->id,
            'judul_modul' => 'Modul otomatis '.$subjectName,
            'tujuan_pembelajaran' => 'Dibuat otomatis dari portal guru.',
            'status' => 'draft',
            'created_by' => $createdBy,
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $this->getTeacherContext($request);
        $employee = Employee::query()->where('user_id', $user?->id)->first();

        $activeAcademicYear = AcademicYear::query()->where('is_active', true)->first();
        $activeSemester = Semester::query()->where('is_active', true)->first();
        $educationUnit = $teacher?->educationUnit ?? $employee?->unit;

        $todayDayNum = now()->dayOfWeekIso;

        // Schedules today
        $schedulesQuery = $this->accessScope->accessibleSchedules($user)
            ->with(['kelas', 'subject'])
            ->when($activeAcademicYear, fn ($q) => $q->where('academic_year_id', $activeAcademicYear->id))
            ->when($activeSemester, fn ($q) => $q->where('semester_id', $activeSemester->id));

        $schedulesToday = (clone $schedulesQuery)
            ->where('day_of_week', $todayDayNum)
            ->orderBy('time_start')
            ->get();

        // Classes/Rombel taught
        $classIds = (clone $schedulesQuery)->pluck('kelas_id')
            ->merge((clone $schedulesQuery)->pluck('class_id'))
            ->unique()
            ->filter();
        $totalClasses = $classIds->count();

        // Total students
        $totalStudents = Student::query()
            ->where(fn ($q) => $q->whereIn('kelas_id', $classIds)->orWhereIn('class_id', $classIds))
            ->active()
            ->count();

        // Pending assignments to grade
        $pendingGrading = LmsPengumpulanTugas::query()
            ->whereNull('nilai_guru')
            ->whereHas('penugasan', function ($q) use ($teacher, $employee) {
                $employeeIds = array_values(array_unique(array_filter([
                    $employee?->id,
                    $teacher?->employee_id,
                ])));

                if ($employeeIds) {
                    $q->whereIn('guru_id', $employeeIds);
                }
            })
            ->count();

        // Tahfizh deposits today
        $tahfizhTodayCount = $teacher
            ? TahfizhDailyLog::query()
                ->whereDate('record_date', now()->toDateString())
                ->where('teacher_id', $teacher->id)
                ->count()
            : 0;

        // Mutabaah unverified count - scoped to the teacher's own supervisor assignments
        $unverifiedMutabaah = 0;
        $assignmentIds = MutabaahSupervisorAssignment::query()
            ->active()
            ->when($employee, fn ($q) => $q->where('employee_id', $employee->id))
            ->pluck('id');
        if ($assignmentIds->isNotEmpty()) {
            $unverifiedMutabaah = MutabaahDailyHeader::query()
                ->where('status', 'draft')
                ->whereIn('supervisor_assignment_id', $assignmentIds)
                ->count();
        }

        // Notifications
        $unreadNotifications = $user
            ? Notification::userQuery((string) $user->id)->unread()->count()
            : 0;

        // Teacher attendance log (View Only - Loaded from attendances table)
        $teacherAttendanceLogs = $this->formatTeacherAttendanceLogs($employee, $user, $request->query('month'));

        // Announcements
        $announcements = PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'teacher' => [
                    'id' => $teacher?->id ?? $employee?->id,
                    'name' => $user?->name ?? 'Pengajar',
                    'nip_niy' => $teacher?->employee_number ?? $employee?->nip_niy ?? $user?->email,
                    'education_unit' => $educationUnit?->name ?? 'Unit Utama',
                    'education_unit_data' => $educationUnit,
                ],
                'academic_context' => [
                    'academic_year' => $activeAcademicYear?->name ?? '2025/2026',
                    'semester' => $activeSemester?->name ?? 'Ganjil',
                    'date' => now()->translatedFormat('l, d F Y'),
                ],
                'kpi' => [
                    'schedules_today_count' => $schedulesToday->count(),
                    'total_students' => $totalStudents,
                    'total_classes' => $totalClasses,
                    'pending_grading_count' => $pendingGrading,
                    'tahfizh_today_count' => $tahfizhTodayCount,
                    'unverified_mutabaah_count' => $unverifiedMutabaah,
                    'unread_notifications' => $unreadNotifications,
                ],
                'schedules_today' => $schedulesToday,
                'announcements' => $announcements,
                'teacher_attendance_logs' => $teacherAttendanceLogs,
            ],
        ]);
    }

    public function academicCalendar(Request $request): JsonResponse
    {
        $events = PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->orderBy('mulai_tampil')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $query = PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->when($search, fn ($q) => $q->where(function ($sq) use ($search) {
                $sq->where('judul_pengumuman', 'ilike', "%{$search}%")
                   ->orWhere('isi_pengumuman', 'ilike', "%{$search}%");
            }))
            ->orderBy('created_at', 'desc');

        $announcements = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    public function attendanceLogs(Request $request): JsonResponse
    {
        $employee = Employee::query()->where('user_id', $request->user()?->id)->first();
        $month = $request->query('month');
        $logs = $this->formatTeacherAttendanceLogs($employee, $request->user(), $month);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    private function formatTeacherAttendanceLogs(?Employee $employee, $user, ?string $month = null): array
    {
        $employeeId = $employee?->id;
        $userId = $user?->id;

        if (! $employeeId && ! $userId) {
            return [];
        }

        $query = DB::table('attendances')
            ->where(function ($q) use ($employeeId, $userId) {
                if ($employeeId) {
                    $q->where('employee_id', $employeeId);
                }
                if ($userId) {
                    $q->orWhere('metadata->user_id', (string) $userId);
                }
            });

        if ($month) {
            $query->where('attendance_date', 'like', $month . '%');
        }

        $rawLogs = $query->orderByDesc('attendance_date')
            ->limit(100)
            ->get();

        return $rawLogs->map(function ($row) {
            $dateObj = Carbon::parse($row->attendance_date);
            $checkIn = $row->check_in_time ? Carbon::parse($row->check_in_time) : null;
            $checkOut = $row->check_out_time ? Carbon::parse($row->check_out_time) : null;
            $duration = ($checkIn && $checkOut)
                ? sprintf('%d jam %d mnt', (int) $checkIn->diffInHours($checkOut), ((int) $checkIn->diffInMinutes($checkOut)) % 60)
                : ($checkIn ? 'Sedang Berlangsung' : '-');

            $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : (array) ($row->metadata ?? []);

            return [
                'id' => $row->id,
                'date' => $dateObj->toDateString(),
                'attendance_date' => $dateObj->toDateString(),
                'day' => $dateObj->translatedFormat('l'),
                'check_in_time' => $row->check_in_time,
                'check_out_time' => $row->check_out_time,
                'check_in' => $checkIn ? $checkIn->format('H:i') : null,
                'check_out' => $checkOut ? $checkOut->format('H:i') : null,
                'duration' => $duration,
                'status' => $row->status ?? 'HADIR',
                'method' => $row->attendance_method ?? ($metadata['method'] ?? 'RFID Tap'),
                'device' => $metadata['device'] ?? ($row->location ?? 'Gate Utama'),
                'location' => $row->location ?? ($metadata['location'] ?? 'Kampus Utama'),
            ];
        })->all();
    }

    public function schedules(Request $request): JsonResponse
    {
        $teacher = $this->getTeacherContext($request);
        $employee = Employee::query()->where('user_id', $request->user()?->id)->first();
        $day = $request->query('day');
        $classId = $request->query('class_id') ?: $request->query('kelas_id');
        $academicYearId = $request->query('academic_year_id');
        $semesterId = $request->query('semester_id');

        // Otomatis prioritaskan semester dan tahun ajaran aktif jika tidak dispesifikasikan secara eksplisit
        if (! $semesterId) {
            $activeSemester = Semester::query()->where('is_active', true)->first();
            $semesterId = $activeSemester?->id;
        }
        if (! $academicYearId) {
            $activeYear = AcademicYear::query()->where('is_active', true)->first();
            $academicYearId = $activeYear?->id;
        }

        $schedules = $this->accessScope->accessibleSchedules($request->user())
            ->with(['kelas', 'subject', 'employee', 'teacher'])
            ->when($academicYearId, fn ($q) => $q->where(fn ($sq) => $sq->whereNull('academic_year_id')->orWhere('academic_year_id', $academicYearId)))
            ->when($semesterId, fn ($q) => $q->where(fn ($sq) => $sq->whereNull('semester_id')->orWhere('semester_id', $semesterId)))
            ->when($day, function ($q) use ($day) {
                if (is_numeric($day)) {
                    $q->where('day_of_week', (int) $day);
                } else {
                    $dayMap = [
                        'senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4,
                        'jumat' => 5, 'sabtu' => 6, 'minggu' => 7, 'ahad' => 7,
                    ];
                    $dayNum = $dayMap[Str::lower($day)] ?? null;
                    if ($dayNum) {
                        $q->where('day_of_week', $dayNum);
                    }
                }
            })
            ->when($classId && $classId !== 'all', fn ($q) => $q->where(fn ($sq) => $sq->where('class_id', $classId)->orWhere('kelas_id', $classId)))
            ->orderBy('time_start')
            ->get();

        // Bersihkan data kembar jika ada duplikasi entri jadwal (hari, jam mulai, jam selesai, kelas, mapel yang sama)
        $uniqueSchedules = $schedules->unique(function ($item) {
            $cId = $item->kelas_id ?: $item->class_id;
            $sId = $item->subject_id;
            $dow = $item->day_of_week;
            $start = substr((string) ($item->time_start ?? $item->start_time ?? ''), 0, 5);
            $end = substr((string) ($item->time_end ?? $item->end_time ?? ''), 0, 5);
            return "{$dow}-{$start}-{$end}-{$cId}-{$sId}";
        })->values();

        return response()->json([
            'success' => true,
            'data' => $uniqueSchedules,
        ]);
    }

    public function classes(Request $request): JsonResponse
    {
        $classes = $this->accessScope->accessibleRombels($request->user())
            ->with(['unitPendidikan'])
            ->orderBy('nama_kelas')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $classId = $request->query('class_id') ?? $request->query('kelas_id');
        $classIds = $this->accessScope->accessibleRombels($request->user())->pluck('id');

        if ($classId && $classId !== 'all') {
            abort_unless($classIds->contains($classId), 403, 'Rombel berada di luar scope guru.');
        }

        $students = $this->accessScope->accessibleStudents($request->user())
            ->with(['kelas.unitPendidikan', 'educationUnit', 'parent', 'parentsPivot'])
            ->where(function ($query) use ($classIds) {
                $query->whereIn('kelas_id', $classIds)->orWhereIn('class_id', $classIds);
            })
            ->when($classId && $classId !== 'all', fn ($q) => $q->byClass($classId))
            ->active()
            ->orderBy('full_name')
            ->paginate($request->query('per_page', 50));

        $today = $request->date('date') ?: now();
        $studentIds = $students->getCollection()->pluck('id');
        $permissions = StudentAttendancePermission::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['approved', 'submitted', 'pending'])
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get()->keyBy('student_id');

        // Workspace guru lama memakai nama field Indonesia. Sertakan alias ini
        // tanpa mengubah kontrak Student utama yang memakai `full_name`.
        $students->getCollection()->each(function (Student $student) use ($permissions): void {
            $student->append(['nama_lengkap']);
            $perm = $permissions->get($student->id);
            if ($perm) {
                $student->setAttribute('recommended_status', $perm->type);
                $student->setAttribute('recommendation_verified', $perm->status === 'approved');
                $student->setAttribute('permission_id', $perm->id);
                $student->setAttribute('permission_status', $perm->status);
                $student->setAttribute('permission_reason', $perm->reason);
                $student->setAttribute('permission_start_date', $perm->start_date ? Carbon::parse($perm->start_date)->toDateString() : null);
                $student->setAttribute('permission_end_date', $perm->end_date ? Carbon::parse($perm->end_date)->toDateString() : null);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $classId = $request->query('class_id');
        $date = $request->query('date', now()->toDateString());
        $scheduleIds = $this->accessScope->accessibleSchedules($request->user())->pluck('id');

        $sessions = LessonAttendanceSession::query()
            ->with(['classSchedule', 'kelas', 'subject', 'attendances.student'])
            ->whereIn('schedule_id', $scheduleIds)
            ->when($classId, function ($q) use ($classId) {
                $q->whereHas('schedule', fn ($sq) => $sq->where('kelas_id', $classId)->orWhere('class_id', $classId));
            })
            ->whereDate('attendance_date', $date)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function saveAttendance(Request $request): JsonResponse
    {
        $request->validate([
            'class_schedule_id' => 'required|uuid',
            'date' => 'required|date',
            'meeting_number' => 'required|integer',
            'topic' => 'required|string',
            'students' => 'required|array',
            'students.*.student_id' => 'required|uuid',
            'students.*.status' => 'required|string|in:Hadir,Izin,Sakit,Alpha,Terlambat,Belum Dicatat',
            'students.*.notes' => 'nullable|string',
        ]);

        $teacher = $this->getTeacherContext($request);
        $schedule = $this->accessScope->accessibleSchedules($request->user())
            ->findOrFail($request->class_schedule_id);
        abort_unless($schedule->kelas_id || $schedule->class_id, 403, 'Jadwal belum terhubung dengan kelas.');
        $studentIds = collect($request->students)->pluck('student_id')->unique();
        $allowedStudentCount = $this->accessScope->accessibleStudents($request->user())
            ->whereIn('id', $studentIds)
            ->where(function (Builder $query) use ($schedule) {
                $query->when($schedule->kelas_id, fn (Builder $scope, string $id) => $scope->orWhere('kelas_id', $id))
                    ->when($schedule->class_id, fn (Builder $scope, string $id) => $scope->orWhere('class_id', $id));
            })
            ->count();
        abort_unless($allowedStudentCount === $studentIds->count(), 403, 'Daftar siswa berada di luar kelas assignment guru.');

        $session = LessonAttendanceSession::updateOrCreate(
            [
                'schedule_id' => $schedule->id,
                'attendance_date' => $request->date,
            ],
            [
                'meeting_number' => $request->meeting_number,
                'topic' => $request->topic,
                'learning_material' => $request->topic,
                'status' => 'final',
                'finalized_at' => now(),
                'finalized_by' => $request->user()?->id,
                'attendance_method' => $request->input('method', 'rollcall'),
                'updated_by' => $request->user()?->id,
            ]
        );

        foreach ($request->students as $st) {
            $statusHadir = strtolower($st['status']) === 'alpha' ? 'alpa' : strtolower($st['status']);
            LmsPresensi::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'siswa_id' => $st['student_id'],
                ],
                [
                    'jadwal_pelajaran_id' => $schedule->id,
                    'tanggal' => $request->date,
                    'status_hadir' => $statusHadir,
                    'keterangan' => $st['notes'] ?? null,
                    'pertemuan_ke' => $request->meeting_number,
                    'waktu_presensi' => now(),
                    'verification_status' => 'verified',
                    'updated_by' => $request->user()?->id,
                ]
            );

            // Realtime Broadcast to student channel
            try {
                $subjName = $schedule->subject?->name ?? 'Pelajaran';
                app(RealtimeBroadcastService::class)->broadcastStudentActivity(
                    $st['student_id'],
                    [
                        'id' => 'lesson-presensi-' . (string) Str::uuid(),
                        'type' => 'mapel',
                        'title' => 'Absensi kelas ' . $subjName,
                        'subtitle' => 'Guru: ' . ($request->user()?->name ?? 'Guru Pengajar'),
                        'date_label' => 'Hari ini',
                        'time_label' => now()->format('H:i'),
                        'badge_label' => ucfirst($statusHadir),
                        'badge_type' => $statusHadir === 'hadir' ? 'green' : ($statusHadir === 'terlambat' ? 'amber' : 'red'),
                        'icon' => 'account-check',
                        'icon_bg' => '#ECFDF5',
                        'icon_color' => '#10B981',
                        'screen' => 'Absensi',
                        'verifier_role' => 'Guru Pengampu ' . $subjName,
                        'verifier_name' => $request->user()?->name ?? 'Guru Pengajar',
                    ]
                );
            } catch (\Throwable) {}
        }

        return response()->json([
            'success' => true,
            'message' => 'Presensi pembelajaran berhasil disimpan dan difinalisasi.',
            'session' => $session->load('attendances.student'),
        ]);
    }

    public function materials(Request $request): JsonResponse
    {
        $ownerIds = $this->teacherMaterialOwnerIds($request);
        $classId = $request->query('class_id') ?? $request->query('kelas_id');
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $subjectId = $request->query('subject_id');

        $query = LmsMateri::query()
            ->with(['subject', 'modulAjar.kelas'])
            ->whereIn('guru_id', $ownerIds);

        if ($classId && $classId !== 'all') {
            $modulIds = LmsModulAjar::query()
                ->where(function ($q) use ($classId) {
                    $q->where('kelas_id', $classId)
                      ->orWhere('rombel_id', $classId);
                })
                ->pluck('id');

            $query->whereIn('modul_ajar_id', $modulIds);
        }

        if ($status === 'published') {
            $query->where('is_published', true);
        } elseif ($status === 'draft') {
            $query->where('is_published', false);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $query) use ($like) {
                $query->where('judul', 'ilike', $like)
                    ->orWhere('catatan', 'ilike', $like)
                    ->orWhere('konten', 'ilike', $like)
                    ->orWhereHas('subject', fn (Builder $subject) => $subject
                        ->where('nama_mapel', 'ilike', $like)
                        ->orWhere('name', 'ilike', $like));
            });
        }

        // Filter by mata pelajaran (subject_id)
        if ($subjectId && $subjectId !== 'all') {
            $query->where(function (Builder $q) use ($subjectId) {
                $q->where('mata_pelajaran_id', $subjectId)
                  ->orWhereHas('subject', fn (Builder $s) => $s->whereKey($subjectId));
            });
        }

        $materials = $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->query('per_page', 24), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $materials,
        ]);
    }

    private function calculatePekanFromDate(?string $dateStr): array
    {
        $d = $dateStr ? \Carbon\Carbon::parse($dateStr) : now();
        $startGanjil = \Carbon\Carbon::parse('2026-07-13 00:00:00');
        $startGenap  = \Carbon\Carbon::parse('2027-01-04 00:00:00');

        if ($d->lessThan($startGenap)) {
            $diffDays = max(0, (int) $startGanjil->diffInDays($d, false));
            $pekan = max(1, min(16, (int) floor($diffDays / 7) + 1));
            $semester = 1;
        } else {
            $diffDays = max(0, (int) $startGenap->diffInDays($d, false));
            $pekan = min(32, 16 + max(1, (int) floor($diffDays / 7) + 1));
            $semester = 2;
        }

        return [
            'pekan' => $pekan,
            'semester' => $semester,
            'tanggal' => $d->format('Y-m-d H:i:s'),
        ];
    }

    public function saveMaterial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'subject_id' => 'required|uuid',
            'class_id' => 'required|uuid',
            'ringkasan' => 'nullable|string',
            'isi' => 'nullable|string',
            'file' => 'nullable|string|max:1000',
            'video' => 'nullable|string|max:1000',
            'link' => 'nullable|string|max:1000',
            'tipe' => 'nullable|string|max:50',
            'status' => 'required|string|in:draft,published',
            'tanggal' => 'nullable|date',
            'tanggal_publish' => 'nullable|date',
            'urutan' => 'nullable|integer|min:1|max:50',
            'pekan' => 'nullable|integer|min:1|max:50',
        ]);

        [$schedule, $teacher, $employee] = $this->assignedTeachingContext(
            $request,
            $request->class_id,
            $request->subject_id
        );
        $guruId = $teacher?->employee_id ?? $employee?->id ?? $request->user()?->id;

        $subjectId = $schedule->subject_id;
        $classId = $schedule->kelas_id;
        $semesterId = $schedule->semester_id;
        $academicYearId = $schedule->academic_year_id;
        $module = $this->ensureLmsModulAjar($guruId, $subjectId, $classId, $semesterId, $academicYearId, $request->user()?->id);

        $isPublished = $request->status === 'published';
        $tipe = $request->tipe ?: ($request->video ? 'video' : ($request->file ? 'dokumen' : ($request->link ? 'link' : 'teks')));
        $fileUrl = $request->file ?: ($request->link && filter_var($request->link, FILTER_VALIDATE_URL) ? $request->link : null);
        $linkUrl = $request->link ?: ($request->file ?: null);

        $dateInfo = $this->calculatePekanFromDate($request->tanggal_publish ?: $request->tanggal);
        $urutan = $request->urutan ?: ($request->pekan ?: $dateInfo['pekan']);
        $tanggalPublish = $request->tanggal_publish ?: ($request->tanggal ?: ($isPublished ? now() : null));

        $material = LmsMateri::create([
            'teacher_id' => $guruId,
            'subject_id' => $subjectId,
            'mata_pelajaran_id' => $subjectId,
            'modul_ajar_id' => $module->id,
            'guru_id' => $guruId,
            'judul' => $request->judul,
            'tipe' => $tipe,
            'tipe_materi' => $tipe,
            'ringkasan' => $request->ringkasan,
            'isi' => $request->isi,
            'file' => $fileUrl,
            'video' => $request->video,
            'link' => $linkUrl,
            'urutan' => $urutan,
            'status' => $request->status,
            'is_published' => $isPublished,
            'tanggal_publish' => $tanggalPublish,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil disimpan.',
            'data' => $material->load(['subject', 'modulAjar.kelas']),
            'pekan' => $urutan,
        ]);
    }

    public function updateMaterial(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'ringkasan' => 'nullable|string',
            'isi' => 'nullable|string',
            'file' => 'nullable|string|max:1000',
            'video' => 'nullable|string|max:1000',
            'link' => 'nullable|string|max:1000',
            'tipe' => 'nullable|string|max:50',
            'status' => 'required|string|in:draft,published',
            'tanggal' => 'nullable|date',
            'tanggal_publish' => 'nullable|date',
            'urutan' => 'nullable|integer|min:1|max:50',
            'pekan' => 'nullable|integer|min:1|max:50',
        ]);

        $material = $this->teacherMaterial($request, $id);
        $material->fill($validated);

        if ($request->has('link') || $request->has('file')) {
            $incomingLink = $request->link ?: $request->file;
            $material->link = $incomingLink;
            if ($request->has('file')) {
                $material->file = $request->file;
            } elseif (! $material->file && $incomingLink) {
                $material->file = $incomingLink;
            }
        }

        if ($request->has('video')) {
            $material->video = $request->video;
        }

        if ($request->has('tanggal') || $request->has('tanggal_publish')) {
            $tgl = $request->tanggal_publish ?: $request->tanggal;
            $material->tanggal_publish = $tgl;
            if (! $request->has('urutan') && ! $request->has('pekan')) {
                $calc = $this->calculatePekanFromDate($tgl);
                $material->urutan = $calc['pekan'];
            }
        }

        if ($request->has('urutan') || $request->has('pekan')) {
            $material->urutan = (int) ($request->urutan ?: $request->pekan);
        }

        $isPublished = $validated['status'] === 'published';
        $material->is_published = $isPublished;
        if (! $material->tanggal_publish && $isPublished) {
            $material->tanggal_publish = now();
        }

        if (! $material->tipe || $request->has('tipe')) {
            $material->tipe = $request->tipe ?: ($material->video ? 'video' : ($material->file ? 'dokumen' : ($material->link ? 'link' : 'teks')));
            $material->tipe_materi = $material->tipe;
        }

        $material->save();

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil diperbarui.',
            'data' => $material->fresh(['subject', 'guru']),
            'pekan' => $material->urutan,
        ]);
    }

    public function deleteMaterial(Request $request, string $id): JsonResponse
    {
        $material = $this->teacherMaterial($request, $id);
        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil dihapus.',
        ]);
    }

    private function teacherMaterial(Request $request, string $id): LmsMateri
    {
        $material = LmsMateri::query()->with('guru')->find($id);
        if (! $material) {
            abort(404, 'Materi pembelajaran tidak ditemukan.');
        }

        if ($this->isSuperAdmin($request) || $request->user()?->hasAnyRole(['Super Admin', 'Admin', 'super_admin', 'admin', 'Yayasan'])) {
            return $material;
        }

        $ownerIds = $this->teacherMaterialOwnerIds($request);
        $isOwner = in_array($material->guru_id, $ownerIds, true)
            || in_array($material->teacher_id, $ownerIds, true);

        if (! $isOwner) {
            $namaGuru = $material->guru?->nama_lengkap ?? 'guru pengampu lain';
            abort(403, "Anda tidak memiliki hak akses untuk mengubah materi ini karena materi ini diampu oleh {$namaGuru}. Silakan pilih materi dari kelas yang Anda ampu.");
        }

        return $material;
    }

    private function teacherMaterialOwnerIds(Request $request): array
    {
        $teacher = $this->getTeacherContext($request);
        $employee = Employee::query()->where('user_id', $request->user()?->id)->first();

        return array_values(array_unique(array_filter([
            $teacher?->id,
            $teacher?->employee_id,
            $employee?->id,
            $request->user()?->id,
        ])));
    }

    /** @return array{ClassSchedule, ?Teacher, ?Employee} */
    private function assignedTeachingContext(Request $request, string $classId, string $subjectId): array
    {
        $schedule = $this->accessScope->accessibleSchedules($request->user())
            ->where('subject_id', $subjectId)
            ->where(function (Builder $query) use ($classId) {
                $query->where('kelas_id', $classId)->orWhere('class_id', $classId);
            })
            ->first();

        $teacher = $this->getTeacherContext($request);
        $employee = Employee::query()->where('user_id', $request->user()?->id)->first();
        abort_unless($teacher || $employee || $this->isSuperAdmin($request), 403, 'Akun belum terhubung dengan data guru.');

        if (! $schedule) {
            $kelas = Kelas::find($classId);
            $subject = Subject::find($subjectId);
            if ($kelas && $subject) {
                $activeYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
                $activeSemester = Semester::where('is_active', true)->first() ?? Semester::first();
                $schedule = ClassSchedule::firstOrCreate(
                    [
                        'kelas_id' => $kelas->id,
                        'subject_id' => $subject->id,
                        'employee_id' => $employee?->id,
                    ],
                    [
                        'class_id' => $kelas->id,
                        'academic_year_id' => $activeYear?->id,
                        'semester_id' => $activeSemester?->id,
                        'teacher_id' => $teacher?->id,
                        'day_of_week' => 1,
                        'time_start' => '07:30',
                        'time_end' => '09:00',
                    ]
                );
            }
        }

        abort_unless($schedule, 404, 'Jadwal mengajar guru tidak ditemukan untuk kelas dan mapel ini.');
        abort_unless($schedule->kelas_id, 403, 'Portal guru membutuhkan rombel primer yang terhubung.');

        return [$schedule, $teacher, $employee];
    }

    private function isSuperAdmin(Request $request): bool
    {
        $normalize = static fn (string $role): string => strtolower((string) preg_replace('/[\s_-]+/', '', $role));

        return $request->user()?->getRoleNames()->map($normalize)->contains('superadmin') ?? false;
    }

    public function assignments(Request $request): JsonResponse
    {
        $ownerIds = $this->teacherMaterialOwnerIds($request);

        $query = LmsPenugasan::query()
            ->with(['subject', 'kelas', 'pengumpulanTugas', 'materi', 'modulAjar'])
            ->whereIn('guru_id', $ownerIds);

        if ($request->filled('class_id') && $request->class_id !== 'all') {
            $query->where('kelas_id', $request->query('class_id'));
        } elseif ($request->filled('kelas_id') && $request->kelas_id !== 'all') {
            $query->where('kelas_id', $request->query('kelas_id'));
        }

        if ($request->filled('subject_id') && $request->subject_id !== 'all') {
            $query->where('mata_pelajaran_id', $request->query('subject_id'));
        }

        if ($request->filled('materi_id') && $request->materi_id !== 'all') {
            $targetMateriId = $request->query('materi_id');
            $query->where(function ($q) use ($targetMateriId) {
                $q->where('materi_id', $targetMateriId)
                  ->orWhereJsonContains('materi_ids', $targetMateriId);
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('judul_tugas', 'like', "%{$search}%")
                  ->orWhere('instruksi', 'like', "%{$search}%");
            });
        }

        $assignments = $query
            ->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 100));

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function saveAssignment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'subject_id' => 'required|uuid',
            'class_id' => 'required|uuid',
            'instruksi' => 'required|string',
            'deadline' => 'required|date',
            'bobot' => 'nullable|numeric',
            'materi_id' => 'nullable|uuid',
            'materi_ids' => 'nullable',
            'materi_ids_json' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'soal_json' => 'nullable|string',
            'tipe_tugas' => 'nullable|string',
            'jenis_soal' => 'nullable|string',
            'jenis_tugas' => 'nullable|string',
        ]);

        [$schedule, $teacher, $employee] = $this->assignedTeachingContext(
            $request,
            $request->class_id,
            $request->subject_id
        );
        $guruId = $teacher?->employee_id ?? $employee?->id ?? $request->user()?->id;

        $academicYear = AcademicYear::query()->findOrFail($schedule->academic_year_id);
        $semester = Semester::query()->findOrFail($schedule->semester_id);
        $subject = Subject::query()->findOrFail($schedule->subject_id);
        $kelas = Kelas::query()->findOrFail($schedule->kelas_id);

        $subjectId = $subject->id;
        $classId = $kelas->id;
        $semesterId = $semester->id;
        $academicYearId = $academicYear->id;

        $materiIds = [];
        if ($request->filled('materi_ids_json')) {
            $decoded = json_decode($request->materi_ids_json, true);
            if (is_array($decoded)) {
                $materiIds = array_values(array_filter($decoded));
            }
        } elseif ($request->has('materi_ids')) {
            $raw = $request->input('materi_ids');
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $materiIds = is_array($decoded) ? $decoded : [$raw];
            } elseif (is_array($raw)) {
                $materiIds = array_values(array_filter($raw));
            }
        }
        if ($request->filled('materi_id') && ! in_array($request->materi_id, $materiIds)) {
            array_unshift($materiIds, $request->materi_id);
        }

        $primaryMateriId = ! empty($materiIds) ? $materiIds[0] : ($request->materi_id ?: null);
        $materi = null;
        if ($primaryMateriId) {
            $materi = LmsMateri::query()->find($primaryMateriId);
        }

        $moduleId = $materi?->modul_ajar_id;
        if (! $moduleId) {
            $module = $this->ensureLmsModulAjar($guruId, $subjectId, $classId, $semesterId, $academicYearId, $request->user()?->id);
            $moduleId = $module->id;
        }

        $fileLampiran = null;
        if ($request->hasFile('file_lampiran')) {
            $fileLampiran = $request->file('file_lampiran')->store('assignments', 'public');
        } elseif ($request->filled('file_lampiran') && is_string($request->file_lampiran)) {
            $fileLampiran = $request->file_lampiran;
        }

        $isDraft = $request->input('status') === 'draft' || $request->input('is_published') === false || $request->input('is_published') === '0' || $request->input('is_published') === 'false';

        $assignment = LmsPenugasan::create([
            'teacher_id' => $guruId,
            'subject_id' => $subjectId,
            'class_id' => $classId,
            'judul' => $request->judul,
            'judul_tugas' => $request->judul,
            'instruksi' => $request->instruksi,
            'deskripsi' => $request->soal_json ?: $request->deskripsi,
            'deadline' => $request->deadline,
            'bobot' => $request->bobot ?? 100,
            'bobot_persen' => $request->bobot ?? 100,
            'tipe_tugas' => $request->tipe_tugas ?: 'both',
            'jenis_tugas' => $request->jenis_tugas ?: ($request->jenis_soal ?: 'tugas'),
            'file_lampiran' => $fileLampiran,
            'izin_kumpul_terlambat' => filter_var($request->input('izin_kumpul_terlambat', true), FILTER_VALIDATE_BOOLEAN),
            'status' => $isDraft ? 'draft' : 'published',
            'mata_pelajaran_id' => $subjectId,
            'kelas_id' => $classId,
            'guru_id' => $guruId,
            'semester_id' => $semesterId,
            'tahun_ajaran_id' => $academicYearId,
            'modul_ajar_id' => $moduleId,
            'materi_id' => $primaryMateriId,
            'materi_ids' => ! empty($materiIds) ? $materiIds : null,
            'is_published' => ! $isDraft,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dibuat.',
            'data' => $assignment->load(['subject', 'kelas', 'materi']),
        ]);
    }

    public function updateAssignment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'instruksi' => 'required|string',
            'deadline' => 'required|date',
            'bobot' => 'nullable|numeric',
            'materi_id' => 'nullable|uuid',
            'materi_ids' => 'nullable',
            'materi_ids_json' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'soal_json' => 'nullable|string',
            'tipe_tugas' => 'nullable|string',
            'jenis_soal' => 'nullable|string',
            'jenis_tugas' => 'nullable|string',
        ]);

        $ownerIds = $this->teacherMaterialOwnerIds($request);
        $assignment = LmsPenugasan::query()
            ->whereKey($id)
            ->whereIn('guru_id', $ownerIds)
            ->firstOrFail();

        $assignment->judul = $validated['judul'];
        $assignment->judul_tugas = $validated['judul'];
        $assignment->instruksi = $validated['instruksi'];
        $assignment->deadline = $validated['deadline'];
        if (isset($validated['bobot'])) {
            $assignment->bobot = $validated['bobot'];
            $assignment->bobot_persen = $validated['bobot'];
        }
        if ($request->filled('jenis_tugas')) {
            $assignment->jenis_tugas = $request->jenis_tugas;
        }
        if ($request->filled('tipe_tugas')) {
            $assignment->tipe_tugas = $request->tipe_tugas;
        }
        if ($request->has('izin_kumpul_terlambat')) {
            $assignment->izin_kumpul_terlambat = filter_var($request->izin_kumpul_terlambat, FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->filled('status')) {
            $isDraft = $request->status === 'draft';
            $assignment->status = $isDraft ? 'draft' : 'published';
            $assignment->is_published = ! $isDraft;
        }
        if ($request->hasFile('file_lampiran')) {
            $assignment->file_lampiran = $request->file('file_lampiran')->store('assignments', 'public');
        } elseif ($request->filled('file_lampiran') && is_string($request->file_lampiran)) {
            $assignment->file_lampiran = $request->file_lampiran;
        }
        if ($request->has('soal_json') || $request->has('deskripsi')) {
            $assignment->deskripsi = $request->soal_json ?: $request->deskripsi;
        }

        if ($request->has('materi_ids') || $request->has('materi_ids_json') || $request->has('materi_id')) {
            $materiIds = [];
            if ($request->filled('materi_ids_json')) {
                $decoded = json_decode($request->materi_ids_json, true);
                if (is_array($decoded)) {
                    $materiIds = array_values(array_filter($decoded));
                }
            } elseif ($request->has('materi_ids')) {
                $raw = $request->input('materi_ids');
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $materiIds = is_array($decoded) ? $decoded : [$raw];
                } elseif (is_array($raw)) {
                    $materiIds = array_values(array_filter($raw));
                }
            }
            if ($request->filled('materi_id') && ! in_array($request->materi_id, $materiIds)) {
                array_unshift($materiIds, $request->materi_id);
            }

            $primaryMateriId = ! empty($materiIds) ? $materiIds[0] : null;
            $assignment->materi_id = $primaryMateriId;
            $assignment->materi_ids = ! empty($materiIds) ? $materiIds : null;

            if ($primaryMateriId) {
                $materi = LmsMateri::find($primaryMateriId);
                if ($materi?->modul_ajar_id) {
                    $assignment->modul_ajar_id = $materi->modul_ajar_id;
                }
            }
        }
        $assignment->save();

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil diperbarui.',
            'data' => $assignment->fresh(['subject', 'kelas', 'materi']),
        ]);
    }

    public function deleteAssignment(Request $request, string $id): JsonResponse
    {
        $ownerIds = $this->teacherMaterialOwnerIds($request);
        $assignment = LmsPenugasan::query()
            ->whereKey($id)
            ->whereIn('guru_id', $ownerIds)
            ->firstOrFail();

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dihapus.',
        ]);
    }

    public function submissions(Request $request): JsonResponse
    {
        $assignmentId = $request->query('assignment_id');
        $classId = $request->query('class_id') ?: $request->query('kelas_id');
        $subjectId = $request->query('subject_id') ?: $request->query('mata_pelajaran_id');
        $ownerIds = $this->teacherMaterialOwnerIds($request);

        $query = LmsPengumpulanTugas::query()
            ->with(['student.kelas', 'penugasan.subject', 'penugasan.kelas'])
            ->whereHas('penugasan', fn (Builder $q) => $q->whereIn('guru_id', $ownerIds));

        if ($assignmentId) {
            $query->where('penugasan_id', $assignmentId);
        }

        if ($classId && $classId !== 'all') {
            $query->whereHas('penugasan', fn (Builder $q) => $q->where('kelas_id', $classId));
        }

        if ($subjectId && $subjectId !== 'all') {
            $query->whereHas('penugasan', fn (Builder $q) => $q->where('mata_pelajaran_id', $subjectId));
        }

        $submissions = $query
            ->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 100));

        return response()->json([
            'success' => true,
            'data' => $submissions,
        ]);
    }

    public function gradeSubmission(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'nilai' => 'required|numeric|min:0|max:100',
            'catatan_guru' => 'nullable|string',
        ]);

        $submission = LmsPengumpulanTugas::query()
            ->whereHas('penugasan', fn (Builder $query) => $query->whereIn('guru_id', $this->teacherMaterialOwnerIds($request)))
            ->findOrFail($id);
        $submission->update([
            'nilai_guru' => $request->nilai,
            'catatan_guru' => $request->catatan_guru,
            'status' => 'dinilai',
            'waktu_dinilai' => now(),
            'dinilai_oleh' => Employee::query()->where('user_id', $request->user()->id)->value('id'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nilai pengumpulan tugas berhasil disimpan.',
            'data' => $submission,
        ]);
    }

    public function grades(Request $request): JsonResponse
    {
        $classId = $request->query('class_id');
        $subjectId = $request->query('subject_id');
        $schedulePairs = $this->accessScope->accessibleSchedules($request->user())
            ->get(['kelas_id', 'class_id', 'subject_id']);

        $grades = StudentGrade::query()
            ->with(['student', 'subject', 'kelas'])
            ->whereIn('student_id', $this->accessScope->accessibleStudents($request->user())->select('id'))
            ->where(function (Builder $scope) use ($schedulePairs) {
                foreach ($schedulePairs as $schedule) {
                    $scope->orWhere(function (Builder $assignment) use ($schedule) {
                        $assignment->where('subject_id', $schedule->subject_id);
                        if ($schedule->kelas_id) {
                            $assignment->where('kelas_id', $schedule->kelas_id);
                        } elseif ($schedule->class_id) {
                            $assignment->where('class_id', $schedule->class_id);
                        } else {
                            $assignment->whereRaw('1 = 0');
                        }
                    });
                }
                if ($schedulePairs->isEmpty()) {
                    $scope->whereRaw('1 = 0');
                }
            })
            ->when($classId, fn ($q) => $q->where(fn (Builder $scope) => $scope->where('class_id', $classId)->orWhere('kelas_id', $classId)))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $grades,
        ]);
    }

    public function saveGrades(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|uuid',
            'subject_id' => 'required|uuid',
            'grades' => 'required|array',
            'grades.*.student_id' => 'required|uuid',
            'grades.*.nilai_tugas' => 'nullable|numeric|min:0|max:100',
            'grades.*.nilai_kuis' => 'nullable|numeric|min:0|max:100',
            'grades.*.score_quiz' => 'nullable|numeric|min:0|max:100',
            'grades.*.nilai_uts' => 'nullable|numeric|min:0|max:100',
            'grades.*.nilai_uas' => 'nullable|numeric|min:0|max:100',
            'grades.*.nilai_akhir' => 'nullable|numeric|min:0|max:100',
        ]);

        [$schedule] = $this->assignedTeachingContext($request, $request->class_id, $request->subject_id);
        $studentIds = collect($request->grades)->pluck('student_id')->unique();
        $allowedStudentCount = $this->accessScope->accessibleStudents($request->user())
            ->whereIn('id', $studentIds)
            ->where(function (Builder $query) use ($schedule) {
                $query->when($schedule->kelas_id, fn (Builder $scope, string $id) => $scope->orWhere('kelas_id', $id))
                    ->when($schedule->class_id, fn (Builder $scope, string $id) => $scope->orWhere('class_id', $id));
            })
            ->count();
        abort_unless($allowedStudentCount === $studentIds->count(), 403, 'Daftar siswa berada di luar kelas assignment guru.');

        foreach ($request->grades as $g) {
            $tugas = $g['nilai_tugas'] ?? null;
            $kuis = $g['nilai_kuis'] ?? $g['score_quiz'] ?? null;
            $uts = $g['nilai_uts'] ?? null;
            $uas = $g['nilai_uas'] ?? null;
            $calcAkhir = null;
            if ($tugas !== null || $kuis !== null || $uts !== null || $uas !== null) {
                $calcAkhir = round((($tugas ?? 0) * 0.2) + (($kuis ?? 0) * 0.2) + (($uts ?? 0) * 0.3) + (($uas ?? 0) * 0.3), 1);
            }

            StudentGrade::updateOrCreate(
                [
                    'student_id' => $g['student_id'],
                    'subject_id' => $request->subject_id,
                    'kelas_id' => $schedule->kelas_id,
                    'class_id' => $schedule->class_id,
                    'academic_year_id' => $schedule->academic_year_id,
                    'semester_id' => $schedule->semester_id,
                ],
                [
                    'score_assignment' => $tugas,
                    'score_quiz' => $kuis,
                    'score_midterm' => $uts,
                    'score_final' => $uas,
                    'final_score' => $g['nilai_akhir'] ?? $calcAkhir,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar nilai berhasil disimpan.',
        ]);
    }

    public function tahfizh(Request $request): JsonResponse
    {
        $studentId = $request->query('student_id');
        $classId = $request->query('class_id');
        $user = $request->user();
        $isSupervisor = $user?->hasRole(['Kepala Sekolah', 'kepala_sekolah', 'kepsek', 'Super Admin', 'super_admin', 'Tata Usaha', 'tu', 'tata_usaha']) || $user?->can('tahfizh.monitoring_target');
        $teacher = $this->getTeacherContext($request);
        $classIds = $this->teacherClassIds($request, $teacher);
        if ($classId && ! $isSupervisor) {
            abort_unless($classIds->contains($classId), 403, 'Rombel berada di luar scope pembimbing.');
        }

        $logs = TahfizhDailyLog::query()
            ->with(['student', 'teacher', 'classModel'])
            ->whereIn('student_id', $this->accessScope->accessibleStudents($request->user())->select('id'))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($classId && $classId !== 'all', function ($q) use ($classId) {
                $q->where(function ($sub) use ($classId) {
                    $sub->where('class_id', $classId)
                        ->orWhereHas('student', function ($sq) use ($classId) {
                            $sq->where('kelas_id', $classId)
                               ->orWhere('class_id', $classId);
                        });
                });
            })
            ->when($teacher && ! $isSupervisor, fn ($q) => $q->where('teacher_id', $teacher->id))
            ->orderBy('record_date', 'desc')
            ->paginate($request->query('per_page', 200));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function saveTahfizh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid',
            'class_id' => 'nullable|uuid',
            'type' => 'required|string|in:Ziyadah,Murajaah,Tasmi,Ujian',
            'juz' => 'required|integer|min:1|max:30',
            'surah_number' => 'required|integer|exists:quran_surahs,nomor',
            'ayat_start' => 'required|integer|min:1',
            'ayat_end' => 'required|integer|gte:ayat_start',
            'kelancaran' => 'required|string',
            'tajwid' => 'required|string',
            'makhraj' => 'required|string',
            'notes_teacher' => 'nullable|string',
        ]);

        $teacher = $this->getTeacherContext($request);
        $employee = Employee::query()->where('user_id', $request->user()->id)->first();
        $student = $this->accessScope->accessibleStudents($request->user())
            ->findOrFail($validated['student_id']);
        $targetClassId = $student->kelas_id ?? $student->class_id ?? ($validated['class_id'] ?? null);
        abort_unless(($teacher || $employee) && $this->isAssignedToStudent($request, $student), 403, 'Siswa berada di luar assignment pembimbing.');
        $surah = QuranSurah::query()->where('nomor', $validated['surah_number'])->firstOrFail();

        if ($validated['ayat_end'] > $surah->jumlah_ayat) {
            return response()->json([
                'success' => false,
                'message' => "Nomor ayat melebihi jumlah ayat Surah {$surah->nama_latin}.",
            ], 422);
        }

        $log = TahfizhDailyLog::updateOrCreate(
            ['student_id' => $validated['student_id'], 'record_date' => now()->toDateString()],
            [
                'class_id' => $targetClassId,
                'teacher_id' => $teacher?->id ?? $employee?->id,
                'day_name' => now()->locale('id')->isoFormat('dddd'),
                'hafalan_surah_number' => $surah->nomor,
                'hafalan_surah_name' => $surah->nama_latin,
                'hafalan_ayah_start' => $validated['ayat_start'],
                'hafalan_ayah_end' => $validated['ayat_end'],
                'hafalan_baris' => $validated['ayat_end'] - $validated['ayat_start'] + 1,
                'notes_teacher' => $validated['notes_teacher'] ?? null,
                'status' => 'verified',
                'metadata' => [
                    'type' => $validated['type'],
                    'juz' => $validated['juz'],
                    'kelancaran' => $validated['kelancaran'],
                    'tajwid' => $validated['tajwid'],
                    'makhraj' => $validated['makhraj'],
                ],
            ]
        );

        // Realtime Broadcast to student channel
        try {
            app(RealtimeBroadcastService::class)->broadcastStudentActivity(
                $validated['student_id'],
                [
                    'id' => 'tahfizh-' . $log->id,
                    'type' => 'tahfizh',
                    'title' => 'Membaca Al-Qur’an (Tahfizh)',
                    'subtitle' => 'Surah ' . $surah->nama_latin . ' (Ayat ' . $validated['ayat_start'] . '–' . $validated['ayat_end'] . ')',
                    'date_label' => 'Hari ini',
                    'time_label' => now()->format('H:i'),
                    'badge_label' => 'Selesai',
                    'badge_type' => 'green',
                    'icon' => 'book-open-page-variant',
                    'icon_bg' => '#FEF2F2',
                    'icon_color' => '#EF4444',
                    'screen' => 'Tahfizh',
                    'verifier_role' => 'Guru Pembimbing Tahfizh',
                    'verifier_name' => $request->user()?->name ?? 'Ustadz Pembina Tahfizh',
                ]
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Setoran tahfizh berhasil dicatat.',
            'data' => $log,
        ]);
    }

    public function mutabaah(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $user = $request->user();
        $isSupervisor = $user->hasRole(['Kepala Sekolah', 'kepala_sekolah', 'kepsek', 'Super Admin', 'super_admin', 'Tata Usaha', 'tu', 'tata_usaha'])
            || $user->can('mutabaah.supervisor.view')
            || $user->can('mutabaah.report.view');

        abort_unless($isSupervisor || $user->can('mutabaah.daily.view'), 403);

        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');
        abort_unless($employeeId || $isSupervisor, 403, 'Akun belum terhubung dengan data pembimbing.');

        $query = MutabaahDailyHeader::query()
            ->with(['student', 'details'])
            ->whereDate('activity_date', $date)
            ->whereIn('student_id', $this->accessScope->accessibleStudents($user)->select('id'));

        if (! $isSupervisor) {
            $assignmentIds = MutabaahSupervisorAssignment::query()
                ->active()->byDate($date)
                ->where('employee_id', $employeeId)
                ->pluck('id');
            $query->whereIn('supervisor_assignment_id', $assignmentIds);
        }

        $headers = $query->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $headers,
        ]);
    }

    public function verifyMutabaah(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('Super Admin') || $user->can('mutabaah.daily.finalize'), 403);
        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');

        $header = MutabaahDailyHeader::query()
            ->whereHas('supervisorAssignment', function ($query) use ($user, $employeeId) {
                $query->active()->when(! $user->hasRole('Super Admin'), fn ($scope) => $scope->where('employee_id', $employeeId));
            })
            ->findOrFail($id);
        abort_unless($header->supervisorAssignment?->can_finalize || $user->hasRole('Super Admin'), 403, 'Assignment tidak memiliki hak finalisasi.');
        $header->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mutabaah siswa berhasil diverifikasi.',
            'data' => $header,
        ]);
    }

    public function studentNotes(Request $request): JsonResponse
    {
        $studentId = $request->query('student_id');
        $classId = $request->query('class_id');
        $teacher = $this->getTeacherContext($request);
        $classIds = $this->teacherClassIds($request, $teacher);

        if ($classId) {
            abort_unless($classIds->contains($classId), 403, 'Rombel berada di luar scope guru.');
            $classIds = collect([$classId]);
        }

        $notes = StudentNote::query()
            ->with(['student', 'teacher'])
            ->whereHas('student', fn ($query) => $query->where(fn ($scope) => $scope->whereIn('kelas_id', $classIds)->orWhereIn('class_id', $classIds)))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->when($request->query('priority'), fn ($q, $priority) => $q->where('priority', $priority))
            ->when($request->query('search'), function ($query, $search) {
                $query->where(fn ($scope) => $scope
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($student) => $student->where('full_name', 'like', "%{$search}%")));
            })
            ->orderBy('date', 'desc')
            ->latest('created_at')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function saveStudentNote(Request $request): JsonResponse
    {
        $validated = $request->validate($this->studentNoteRules());

        $teacher = $this->getTeacherContext($request);
        $classIds = $this->teacherClassIds($request, $teacher);
        $student = Student::query()->where(fn ($query) => $query->whereIn('kelas_id', $classIds)->orWhereIn('class_id', $classIds))->findOrFail($validated['student_id']);
        $activeAcademicYear = AcademicYear::query()->where('is_active', true)->first();
        $activeSemester = Semester::query()->where('is_active', true)->first();

        $note = StudentNote::create([
            ...$validated,
            'teacher_id' => $teacher?->id,
            'education_unit_id' => $student->unit_id,
            'academic_year_id' => $activeAcademicYear?->id,
            'semester_id' => $activeSemester?->id,
            'date' => $validated['date'] ?? now()->toDateString(),
        ]);

        // Realtime Broadcast to student channel
        try {
            if ($note->visible_to_parent || $note->visible_to_student) {
                app(RealtimeBroadcastService::class)->broadcastStudentActivity(
                    $note->student_id,
                    [
                        'id' => 'note-' . $note->id,
                        'type' => 'komentar',
                        'title' => 'Guru memberikan komentar',
                        'subtitle' => '“' . ($note->content ?? $note->title ?? 'Catatan guru') . '”',
                        'date_label' => 'Hari ini',
                        'time_label' => now()->format('H:i'),
                        'badge_label' => 'Baru',
                        'badge_type' => 'purple',
                        'has_red_dot' => true,
                        'icon' => 'message-text',
                        'icon_bg' => '#F5F3FF',
                        'icon_color' => '#8B5CF6',
                        'screen' => 'Komentar',
                        'verifier_role' => 'Wali Kelas / Guru Pengampu',
                        'verifier_name' => $teacher?->name ?? $request->user()?->name ?? 'Guru Pengajar',
                    ]
                );
            }
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Catatan siswa berhasil disimpan.',
            'data' => $note->load(['student', 'teacher']),
        ], 201);
    }

    public function showStudentNote(Request $request, string $id): JsonResponse
    {
        $note = $this->scopedStudentNote($request, $id, false);

        return response()->json(['success' => true, 'data' => $note->load(['student', 'teacher'])]);
    }

    public function updateStudentNote(Request $request, string $id): JsonResponse
    {
        $note = $this->scopedStudentNote($request, $id);
        $validated = $request->validate($this->studentNoteRules());
        $teacher = $this->getTeacherContext($request);
        $classIds = $this->teacherClassIds($request, $teacher);
        Student::query()->where(fn ($query) => $query->whereIn('kelas_id', $classIds)->orWhereIn('class_id', $classIds))->findOrFail($validated['student_id']);
        $note->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Catatan siswa berhasil diperbarui.',
            'data' => $note->fresh()->load(['student', 'teacher']),
        ]);
    }

    public function deleteStudentNote(Request $request, string $id): JsonResponse
    {
        $note = $this->scopedStudentNote($request, $id);
        $note->delete();

        return response()->json(['success' => true, 'message' => 'Catatan siswa berhasil dihapus.']);
    }

    private function studentNoteRules(): array
    {
        return [
            'student_id' => 'required|uuid',
            'date' => 'nullable|date',
            'category' => 'required|string|in:Akademik,Perilaku,Kedisiplinan,Prestasi,Konseling,Tahfizh,Ibadah,Kesehatan',
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'follow_up' => 'nullable|string|max:5000',
            'visible_to_parent' => 'boolean',
            'visible_to_student' => 'boolean',
        ];
    }

    private function scopedStudentNote(Request $request, string $id, bool $ownerOnly = true): StudentNote
    {
        $teacher = $this->getTeacherContext($request);

        return StudentNote::query()
            ->whereHas('student', function ($query) use ($request, $teacher) {
                $classIds = $this->teacherClassIds($request, $teacher);
                $query->where(fn ($scope) => $scope->whereIn('kelas_id', $classIds)->orWhereIn('class_id', $classIds));
            })
            ->when($ownerOnly, fn ($query) => $query->where('teacher_id', $teacher?->id))
            ->findOrFail($id);
    }

    private function teacherClassIds(Request $request, ?Teacher $teacher = null)
    {
        $primary = $this->accessScope->accessibleRombels($request->user())->pluck('id');
        $legacy = $this->accessScope->accessibleSchedules($request->user())->pluck('class_id');

        return $primary->merge($legacy)->filter()->unique()->values();
    }

    public function notifications(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $notifications = Notification::userQuery($request->user()->id, [
            'search' => $request->query('search'),
            'type' => $request->query('type'),
            'is_read' => $request->query('is_read'),
        ])->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $this->getTeacherContext($request);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'teacher' => $teacher?->load(['educationUnit', 'kelas']),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'phone' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6',
        ]);

        if ($request->filled('phone')) {
            $user->phone = $request->phone;
        }
        if ($request->filled('email')) {
            $user->email = $request->email;
        }
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => $user,
        ]);
    }

    /**
     * Pastikan guru/pegawai login memang terhubung dengan siswa tersebut
     * (sebagai wali kelas atau guru mapel di jadwal aktif kelas siswa).
     */
    private function isAssignedToStudent(Request $request, Student $student): bool
    {
        $user = $request->user();
        if ($user && (
            RoleName::userHasAny($user, ['Super Admin', 'super_admin'])
            || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin']))
        )) {
            return true;
        }

        $teacher = $this->getTeacherContext($request);
        $employee = Employee::query()->where('user_id', $user->id)->first();

        $kelasId = $student->kelas_id ?? $student->class_id;
        if ($kelasId) {
            $isHomeroom = Kelas::query()
                ->whereKey($kelasId)
                ->where(fn ($q) => $q->where('wali_kelas_id', $teacher?->id)->orWhere('wali_kelas_id', $employee?->id))
                ->exists();

            if ($isHomeroom) {
                return true;
            }

            $isScheduleTeacher = ClassSchedule::query()
                ->where(fn ($q) => $q->where('kelas_id', $kelasId)->orWhere('class_id', $kelasId))
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('teacher_id', $teacher?->id)
                    ->orWhere('employee_id', $employee?->id)
                    ->orWhere('teacher_id', $employee?->id)
                    ->orWhere('employee_id', $teacher?->id)
                )
                ->exists();

            if ($isScheduleTeacher) {
                return true;
            }
        }

        // Check if teacher/employee shares education unit with student
        $teacherUnitId = $employee?->unit_id ?? $teacher?->employee?->unit_id;
        $studentUnitId = $student->unit_id ?? $student->kelas?->unit_pendidikan_id;
        if ($teacherUnitId && (string) $studentUnitId === (string) $teacherUnitId) {
            return true;
        }

        // Check if any prior message exists between this teacher and student
        $hasExistingChat = PortalMessage::query()
            ->where('student_id', $student->id)
            ->where(fn ($q) => $q->where('sender_user_id', $user->id)->orWhere('recipient_user_id', $user->id))
            ->exists();
        if ($hasExistingChat) {
            return true;
        }

        return false;
    }

    public function chatConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $teacher = $this->getTeacherContext($request);
        $employee = Employee::query()->where('user_id', $user->id)->first();

        $homeroomKelasIds = Kelas::query()
            ->where(fn ($q) => $q->where('wali_kelas_id', $teacher?->id)->orWhere('wali_kelas_id', $employee?->id))
            ->pluck('id')
            ->toArray();

        $teachingKelasIds = ClassSchedule::query()
            ->where(fn ($q) => $q->where('teacher_id', $teacher?->id)->orWhere('employee_id', $employee?->id))
            ->where('is_active', true)
            ->pluck('kelas_id')
            ->filter()
            ->unique()
            ->toArray();

        $isElevated = $user->hasAnyRole([
            'Super Admin', 'super_admin', 'Admin', 'admin',
            'Kepala Sekolah', 'kepala_sekolah', 'Divisi Pendidikan', 'Pengurus Yayasan',
            'Tata Usaha', 'tata_usaha', 'TU', 'tu', 'staf_tu', 'Staff TU', 'Operator', 'operator'
        ]);

        $messagesQuery = PortalMessage::query()
            ->with(['student.kelas.unitPendidikan', 'student.educationUnit', 'sender:id,name,email', 'recipient:id,name,email'])
            ->whereNotNull('student_id')
            ->orderBy('created_at', 'desc');

        if (! $isElevated) {
            $messagesQuery->where(fn ($q) => $q->where('sender_user_id', $user->id)->orWhere('recipient_user_id', $user->id));
        }

        $messages = $messagesQuery->get();

        $grouped = [];

        foreach ($messages as $msg) {
            $student = $msg->student;
            if (! $student) {
                continue;
            }

            $otherUserId = $msg->sender_user_id === $user->id ? $msg->recipient_user_id : $msg->sender_user_id;
            $key = $student->id.'_'.$otherUserId;

            if (! isset($grouped[$key])) {
                $otherUser = $msg->sender_user_id === $user->id ? $msg->recipient : $msg->sender;
                $isHomeroom = in_array($student->kelas_id, $homeroomKelasIds, true);

                $unreadCount = PortalMessage::query()
                    ->where('student_id', $student->id)
                    ->where('sender_user_id', $otherUserId)
                    ->where('recipient_user_id', $user->id)
                    ->whereNull('read_at')
                    ->count();

                $unitName = $student->educationUnit?->name
                    ?? $student->kelas?->unitPendidikan?->name
                    ?? $student->kelas?->unit_pendidikan?->name
                    ?? $student->unit_name
                    ?? '-';
                $unitId = $student->unit_id
                    ?? $student->kelas?->unit_pendidikan_id
                    ?? $student->kelas?->unit_id;

                $grouped[$key] = [
                    'id' => $key,
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'parent_user_id' => $otherUserId,
                    'parent_name' => $otherUser?->name ?? 'Orang Tua/Wali',
                    'class_name' => $student->kelas?->nama_kelas ?? '-',
                    'unit_name' => $unitName,
                    'unit_id' => $unitId,
                    'teacher_type' => $isHomeroom ? 'wali_kelas' : 'guru_mapel',
                    'role_label' => $isHomeroom ? 'Wali Kelas' : 'Guru Mapel',
                    'last_message' => $msg->message,
                    'last_message_at' => $msg->created_at?->toIso8601String(),
                    'unread_count' => $unreadCount,
                ];
            }
        }

        // Also populate all students under teacher's classes/homeroom so teacher can initiate conversations
        $relevantKelasIds = array_unique(array_filter(array_merge($homeroomKelasIds, $teachingKelasIds)));
        $teacherUnitId = $employee?->unit_id ?? $teacher?->employee?->unit_id;

        $studentsQuery = Student::query()
            ->with(['parent.user', 'parents.user', 'kelas.unitPendidikan', 'educationUnit'])
            ->where('is_active', true);

        if (! empty($relevantKelasIds)) {
            $studentsQuery->where(fn ($q) => $q->whereIn('kelas_id', $relevantKelasIds)->orWhereIn('class_id', $relevantKelasIds));
        } elseif ($teacherUnitId) {
            $studentsQuery->where(function ($q) use ($teacherUnitId) {
                $q->where('unit_id', $teacherUnitId)
                  ->orWhereHas('kelas', fn ($k) => $k->where('unit_pendidikan_id', $teacherUnitId));
            });
        }

        $assignedStudents = $studentsQuery->limit(80)->get();

        foreach ($assignedStudents as $student) {
            $parent = $student->parent;
            $parentUser = $parent?->user ?? $student->parents?->first()?->user;

            if (! $parentUser && $parent?->email) {
                $parentUser = User::query()->where('email', $parent->email)->first();
            }

            if (! $parentUser) {
                continue;
            }

            $key = $student->id.'_'.$parentUser->id;

            if (! isset($grouped[$key])) {
                $isHomeroom = in_array($student->kelas_id, $homeroomKelasIds, true);
                $assignedUnitName = $student->educationUnit?->name
                    ?? $student->kelas?->unitPendidikan?->name
                    ?? $student->kelas?->unit_pendidikan?->name
                    ?? $student->unit_name
                    ?? '-';
                $assignedUnitId = $student->unit_id
                    ?? $student->kelas?->unit_pendidikan_id
                    ?? $student->kelas?->unit_id;

                $grouped[$key] = [
                    'id' => $key,
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'parent_user_id' => $parentUser->id,
                    'parent_name' => $parent?->full_name ?? $parentUser->name ?? 'Orang Tua/Wali',
                    'class_name' => $student->kelas?->nama_kelas ?? '-',
                    'unit_name' => $assignedUnitName,
                    'unit_id' => $assignedUnitId,
                    'teacher_type' => $isHomeroom ? 'wali_kelas' : 'guru_mapel',
                    'role_label' => $isHomeroom ? 'Wali Kelas' : 'Guru Mapel',
                    'last_message' => null,
                    'last_message_at' => null,
                    'unread_count' => 0,
                ];
            }
        }

        $list = array_values($grouped);
        usort($list, function ($a, $b) {
            if ($a['last_message_at'] && $b['last_message_at']) {
                return strcmp($b['last_message_at'], $a['last_message_at']);
            }
            if ($a['last_message_at']) {
                return -1;
            }
            if ($b['last_message_at']) {
                return 1;
            }
            return strcmp($a['student_name'], $b['student_name']);
        });

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    public function chatMessages(Request $request, string $parentUserId, string $studentId): JsonResponse
    {
        $user = $request->user();

        $student = Student::query()->with(['kelas.unitPendidikan', 'educationUnit'])->find($studentId);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan.'], 404);
        }

        if (! $this->isAssignedToStudent($request, $student)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak terhubung dengan siswa ini.'], 403);
        }

        PortalMessage::query()
            ->where('student_id', $studentId)
            ->where('sender_user_id', $parentUserId)
            ->where('recipient_user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $isElevated = $user->hasAnyRole([
            'Super Admin', 'super_admin', 'Admin', 'admin',
            'Kepala Sekolah', 'kepala_sekolah', 'Divisi Pendidikan', 'Pengurus Yayasan',
            'Guru', 'guru', 'Wali Kelas', 'wali_kelas', 'Guru Pengajar', 'Guru Tahfizh', 'Musyrif',
            'Tata Usaha', 'tata_usaha', 'TU', 'tu', 'staf_tu', 'Staff TU', 'Operator', 'operator'
        ]);

        $messagesQuery = PortalMessage::query()
            ->with(['sender:id,name', 'recipient:id,name', 'attachments'])
            ->where('student_id', $studentId);

        if ($isElevated) {
            $messagesQuery->where(function ($q) use ($parentUserId, $user) {
                $q->where('sender_user_id', $parentUserId)
                    ->orWhere('recipient_user_id', $parentUserId)
                    ->orWhere('sender_user_id', $user->id)
                    ->orWhere('recipient_user_id', $user->id);
            });
        } else {
            $messagesQuery->where(function ($q) use ($user, $parentUserId) {
                $q->where(fn ($q2) => $q2->where('sender_user_id', $user->id)->where('recipient_user_id', $parentUserId))
                    ->orWhere(fn ($q2) => $q2->where('sender_user_id', $parentUserId)->where('recipient_user_id', $user->id));
            });
        }

        $messages = $messagesQuery->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    public function sendChatMessage(Request $request, string $parentUserId, string $studentId): JsonResponse
    {
        $request->validate([
            'message' => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $user = $request->user();
        $student = Student::query()->with('kelas')->find($studentId);

        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan.'], 404);
        }

        if (! $this->isAssignedToStudent($request, $student)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak terhubung dengan siswa ini.'], 403);
        }

        $message = PortalMessage::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $studentId,
            'sender_user_id' => $user->id,
            'recipient_user_id' => $parentUserId,
            'message' => trim((string) ($request->input('message') ?? '')),
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat/attachments', 'public');
            \App\Models\PortalMessageAttachment::create([
                'message_id' => $message->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);
        }

        try {
            Notification::deliver(
                userId: $parentUserId,
                title: 'Pesan Baru Guru ('.$user->name.')',
                body: Str::limit($message->message, 100),
                channel: 'chat',
                metadata: [
                    'student_id' => $studentId,
                    'teacher_user_id' => $user->id,
                    'message_id' => $message->id,
                ],
            );
        } catch (\Throwable $e) {
            // Silence notification schema fallback
        }

        // Realtime Broadcast chat message to parent
        try {
            app(RealtimeBroadcastService::class)->broadcastChatMessage(
                $parentUserId,
                [
                    'id' => $message->id,
                    'student_id' => $studentId,
                    'sender_user_id' => $user->id,
                    'sender_name' => $user->name,
                    'recipient_user_id' => $parentUserId,
                    'message' => $message->message,
                    'created_at' => $message->created_at ? $message->created_at->toISOString() : now()->toISOString(),
                ],
                $user->id
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message->load(['sender:id,name', 'recipient:id,name', 'attachments']),
        ]);
    }
}
