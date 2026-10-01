<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ScheduleExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ScheduleController
 *
 * CRUD Jadwal Pelajaran.
 * Endpoint: /api/v1/schedules
 */
class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());
        $query = $this->scopedQuery($request->user())->with([
            'kelas',       // tbl_kelas (primer)
            'schoolClass', // classes (legacy)
            'employee',    // employees (primer)
            'teacher',     // teachers (legacy)
            'subject',
            'academicYear',
            'semester',
        ]);

        if ($request->filled('unit_pendidikan_id') || $request->filled('unit_id')) {
            $unitId = $request->query('unit_pendidikan_id') ?: $request->query('unit_id');
            $query->whereHas('kelas', fn ($q) => $q->where('unit_pendidikan_id', $unitId));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->query('kelas_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->query('teacher_id'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->query('subject_id'));
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->whereHas('employee', fn ($q) => $q->where('nama_lengkap', 'ilike', "%{$search}%"))
                    ->orWhereHas('teacher', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('subject', fn ($q) => $q
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('nama_mapel', 'ilike', "%{$search}%"))
                    ->orWhereHas('kelas', fn ($q) => $q
                        ->where('nama_kelas', 'ilike', "%{$search}%")
                        ->orWhere('kode_kelas', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif ($request->boolean('aktif_only', false)) {
            $query->where('is_active', true);
        }

        // Deteksi ID jadwal yang mengalami bentrok
        $bentrokScheduleIds = DB::table('class_schedules as s1')
            ->join('class_schedules as s2', function ($join) {
                $join->on('s1.id', '!=', 's2.id')
                    ->on('s1.day_of_week', '=', 's2.day_of_week')
                    ->on('s1.academic_year_id', '=', 's2.academic_year_id')
                    ->on('s1.semester_id', '=', 's2.semester_id')
                    ->whereRaw('s1.time_start < s2.time_end')
                    ->whereRaw('s1.time_end > s2.time_start')
                    ->where(function ($q) {
                        $q->whereRaw('s1.employee_id IS NOT NULL AND s1.employee_id = s2.employee_id')
                          ->orWhereRaw('s1.kelas_id IS NOT NULL AND s1.kelas_id = s2.kelas_id');
                    });
            })
            ->whereNull('s1.deleted_at')
            ->whereNull('s2.deleted_at')
            ->where('s1.is_active', true)
            ->where('s2.is_active', true)
            ->distinct()
            ->pluck('s1.id')
            ->toArray();

        if ($request->boolean('conflict_only') || $request->boolean('hanya_bentrok')) {
            $query->whereIn('class_schedules.id', $bentrokScheduleIds);
        }

        $statsQuery = clone $query;

        if ($request->filled('day_of_week')) {
            $query->where('day_of_week', (int) $request->query('day_of_week'));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $data = $query->orderBy('day_of_week')->orderBy('time_start')->paginate($perPage);

        foreach ($data->items() as $item) {
            $item->has_conflict = in_array($item->id, $bentrokScheduleIds, true);
        }

        $perHari = (clone $statsQuery)
            ->select('day_of_week', DB::raw('count(*) as count'))
            ->groupBy('day_of_week')
            ->pluck('count', 'day_of_week')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar jadwal pelajaran berhasil diambil.',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'from' => $data->firstItem(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'to' => $data->lastItem(),
                'total' => $data->total(),
            ],
            'statistik' => [
                'total' => (clone $statsQuery)->count(),
                'aktif' => (clone $statsQuery)->where('is_active', true)->count(),
                'tidak_aktif' => (clone $statsQuery)->where('is_active', false)->count(),
                'guru_terjadwal' => (clone $statsQuery)->whereNotNull('employee_id')->distinct('employee_id')->count('employee_id'),
                'total_bentrok' => count($bentrokScheduleIds),
                'per_hari' => $perHari,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeView($request->user());
        $query = $this->scopedQuery($request->user())->with([
            'kelas',
            'schoolClass',
            'employee',
            'teacher',
            'subject',
            'academicYear',
            'semester',
        ]);

        if ($request->filled('unit_pendidikan_id') || $request->filled('unit_id')) {
            $unitId = $request->query('unit_pendidikan_id') ?: $request->query('unit_id');
            $query->whereHas('kelas', fn ($q) => $q->where('unit_pendidikan_id', $unitId));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->query('kelas_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->query('teacher_id'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->query('subject_id'));
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->whereHas('employee', fn ($q) => $q->where('nama_lengkap', 'ilike', "%{$search}%"))
                    ->orWhereHas('teacher', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('subject', fn ($q) => $q
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('nama_mapel', 'ilike', "%{$search}%"))
                    ->orWhereHas('kelas', fn ($q) => $q
                        ->where('nama_kelas', 'ilike', "%{$search}%")
                        ->orWhere('kode_kelas', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $schedules = $query->orderBy('day_of_week')->orderBy('time_start')->get();

        $format = strtolower($request->query('format', 'json'));
        if (in_array($format, ['xlsx', 'xls', 'csv'])) {
            $excelFormat = match ($format) {
                'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                'csv' => \Maatwebsite\Excel\Excel::CSV,
            };
            $filename = 'jadwal_pelajaran_' . date('Ymd_His') . '.' . $format;
            return Excel::download(new ScheduleExport($schedules), $filename, $excelFormat);
        }

        return response()->json([
            'status' => 'success',
            'data' => $schedules,
        ]);
    }

    public function options(): JsonResponse
    {
        $user = request()->user();
        $this->authorizeView($user);
        $unitIds = $this->accessibleUnitIds($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Opsi jadwal pelajaran berhasil diambil.',
            'data' => [
                'kelas' => Kelas::query()
                    ->when(!empty($unitIds), fn (Builder $query) => $query->whereIn('unit_pendidikan_id', $unitIds))
                    ->with(['unitPendidikan:id,name', 'tahunAjaran:id,name', 'semester:id,name'])
                    ->orderBy('nama_kelas')
                    ->get(['id', 'nama_kelas', 'kode_kelas', 'unit_pendidikan_id', 'tahun_ajaran_id', 'semester_id']),
                'guru' => $this->isGuruRole($user)
                    ? Employee::query()->where('user_id', $user->id)->where('status', 'Aktif')->get(['id', 'nama_lengkap', 'niy', 'nik', 'unit_id'])
                    : Employee::query()
                        ->when(!empty($unitIds), fn (Builder $query) => $query->whereIn('unit_id', $unitIds))
                        ->where('status', 'Aktif')
                        ->orderBy('nama_lengkap')
                        ->get(['id', 'nama_lengkap', 'niy', 'nik', 'unit_id']),
                'mata_pelajaran' => Subject::query()
                    ->when(!empty($unitIds), fn (Builder $query) => $query->whereIn('unit_pendidikan_id', $unitIds))
                    ->where(fn ($q) => $q->where('status', true)->orWhereNull('status'))
                    ->orderByRaw('COALESCE(nama_mapel, name)')
                    ->get(['id', 'nama_mapel', 'name', 'kode_mapel', 'code', 'unit_pendidikan_id']),
                'tahun_ajaran' => AcademicYear::query()
                    ->orderByDesc('start_date')
                    ->get(['id', 'name', 'is_active']),
                'tahunAjaran' => AcademicYear::query()
                    ->orderByDesc('start_date')
                    ->get(['id', 'name', 'is_active']),
                'academic_years' => AcademicYear::query()
                    ->orderByDesc('start_date')
                    ->get(['id', 'name', 'is_active']),
                'semester' => Semester::query()
                    ->with('academicYear:id,name')
                    ->orderByDesc('start_date')
                    ->get(['id', 'academic_year_id', 'name', 'is_active']),
                'hari' => collect(ClassSchedule::DAY_NAMES)
                    ->map(fn ($name, $id) => ['id' => $id, 'name' => $name])
                    ->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request->user(), 'create');
        $validated = $request->validate([
            'kelas_id' => 'nullable|uuid|exists:tbl_kelas,id',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'employee_id' => 'nullable|uuid|exists:employees,id',
            'teacher_id' => 'nullable|uuid|exists:teachers,id',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
            'academic_year_id' => 'required|uuid|exists:academic_years,id',
            'semester_id' => 'required|uuid|exists:semesters,id',
            'day_of_week' => 'required|integer|min:1|max:7',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
            'week_type' => 'nullable|string|in:all,odd,even',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        // Validasi: harus ada minimal satu referensi kelas
        if (empty($validated['kelas_id']) && empty($validated['class_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Harus mengisi salah satu dari kelas_id (tbl_kelas) atau class_id (classes).',
            ], 422);
        }

        if (empty($validated['employee_id']) && empty($validated['teacher_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Guru pengampu wajib dipilih.',
            ], 422);
        }

        $this->ensureScheduleContext($validated, $request->user());
        $this->ensureNoConflict($validated);
        $validated['created_by'] = Auth::id();

        $schedule = ClassSchedule::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal pelajaran berhasil ditambahkan.',
            'data' => $schedule->load(['kelas', 'employee', 'subject', 'semester']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeView(request()->user());
        $schedule = $this->scopedQuery(request()->user())->with([
            'kelas', 'schoolClass', 'employee', 'teacher',
            'subject', 'classroom', 'academicYear', 'semester',
        ])->find($id);

        if (! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $schedule]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeManage($request->user(), 'update');
        $schedule = $this->scopedQuery($request->user())->find($id);

        if (! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'kelas_id' => 'nullable|uuid|exists:tbl_kelas,id',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'employee_id' => 'nullable|uuid|exists:employees,id',
            'teacher_id' => 'nullable|uuid|exists:teachers,id',
            'subject_id' => 'sometimes|uuid|exists:subjects,id',
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
            'academic_year_id' => 'sometimes|uuid|exists:academic_years,id',
            'semester_id' => 'sometimes|uuid|exists:semesters,id',
            'day_of_week' => 'sometimes|integer|min:1|max:7',
            'time_start' => 'sometimes|date_format:H:i',
            'time_end' => 'sometimes|date_format:H:i|after:time_start',
            'week_type' => 'nullable|string|in:all,odd,even',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $merged = array_merge($schedule->only([
            'kelas_id', 'class_id', 'employee_id', 'teacher_id', 'academic_year_id',
            'semester_id', 'day_of_week', 'time_start', 'time_end',
        ]), $validated);

        if (empty($merged['employee_id']) && empty($merged['teacher_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Guru pengampu wajib dipilih.',
            ], 422);
        }

        $this->ensureScheduleContext($merged, $request->user());
        $this->ensureNoConflict($merged, $schedule->id);
        $validated['updated_by'] = Auth::id();
        $schedule->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal berhasil diperbarui.',
            'data' => $schedule->fresh(['kelas', 'employee', 'subject']),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeManage(request()->user(), 'delete');
        $schedule = $this->scopedQuery(request()->user())->find($id);

        if (! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
        }

        $schedule->update(['deleted_by' => Auth::id()]);
        $schedule->delete();

        return response()->json(['status' => 'success', 'message' => 'Jadwal berhasil dihapus.']);
    }

    public function checkConflict(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());

        $validated = $request->validate([
            'academic_year_id' => 'required|uuid',
            'semester_id' => 'required|uuid',
            'day_of_week' => 'required|integer|between:1,7',
            'time_start' => 'required',
            'time_end' => 'required|after:time_start',
            'employee_id' => 'nullable|uuid',
            'teacher_id' => 'nullable|uuid',
            'kelas_id' => 'nullable|uuid',
            'class_id' => 'nullable|uuid',
            'room' => 'nullable|string',
            'ignore_id' => 'nullable|uuid',
        ]);

        $conflicts = $this->detectConflicts($validated, $validated['ignore_id'] ?? null);

        return response()->json([
            'status' => 'success',
            'has_conflict' => ! empty($conflicts),
            'total_conflicts' => count($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    private function detectConflicts(array $data, ?string $ignoreId = null): array
    {
        $conflicts = [];
        $startTime = strlen($data['time_start']) === 5 ? $data['time_start'] . ':00' : $data['time_start'];
        $endTime = strlen($data['time_end']) === 5 ? $data['time_end'] . ':00' : $data['time_end'];

        $baseQuery = fn () => ClassSchedule::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('day_of_week', (int) $data['day_of_week'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('time_start', '<', $endTime)
            ->where('time_end', '>', $startTime)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));

        // 1. Cek Bentrok Guru (Employee / Teacher)
        $teacherId = $data['employee_id'] ?? $data['teacher_id'] ?? null;
        if ($teacherId) {
            $teacherCollisions = $baseQuery()
                ->where(function ($q) use ($teacherId) {
                    $q->where('employee_id', $teacherId)
                      ->orWhere('teacher_id', $teacherId);
                })
                ->with(['kelas', 'subject', 'employee'])
                ->get();

            foreach ($teacherCollisions as $tc) {
                $teacherName = $tc->employee?->nama_lengkap ?? 'Guru bersangkutan';
                $kelasName = $tc->kelas?->nama_kelas ?? 'Kelas lain';
                $mapelName = $tc->subject?->name ?? $tc->subject?->nama_mapel ?? 'Mata pelajaran';

                $conflicts[] = [
                    'type' => 'teacher',
                    'title' => 'Bentrok Jadwal Guru',
                    'message' => "Guru {$teacherName} sudah memiliki jadwal mengajar ({$mapelName}) di {$kelasName} pada pukul " . substr($tc->time_start, 0, 5) . " - " . substr($tc->time_end, 0, 5) . ".",
                    'schedule_id' => $tc->id,
                    'kelas_name' => $kelasName,
                    'subject_name' => $mapelName,
                    'time_start' => substr($tc->time_start, 0, 5),
                    'time_end' => substr($tc->time_end, 0, 5),
                ];
            }
        }

        // 2. Cek Bentrok Kelas / Rombel
        $kelasId = $data['kelas_id'] ?? $data['class_id'] ?? null;
        if ($kelasId) {
            $kelasCollisions = $baseQuery()
                ->where(function ($q) use ($kelasId) {
                    $q->where('kelas_id', $kelasId)
                      ->orWhere('class_id', $kelasId);
                })
                ->with(['kelas', 'subject', 'employee'])
                ->get();

            foreach ($kelasCollisions as $kc) {
                $teacherName = $kc->employee?->nama_lengkap ?? 'Guru lain';
                $kelasName = $kc->kelas?->nama_kelas ?? 'Kelas bersangkutan';
                $mapelName = $kc->subject?->name ?? $kc->subject?->nama_mapel ?? 'Mata pelajaran';

                $conflicts[] = [
                    'type' => 'class',
                    'title' => 'Bentrok Jadwal Kelas',
                    'message' => "Kelas {$kelasName} sudah memiliki jadwal pelajaran {$mapelName} bersama {$teacherName} pada pukul " . substr($kc->time_start, 0, 5) . " - " . substr($kc->time_end, 0, 5) . ".",
                    'schedule_id' => $kc->id,
                    'teacher_name' => $teacherName,
                    'subject_name' => $mapelName,
                    'time_start' => substr($kc->time_start, 0, 5),
                    'time_end' => substr($kc->time_end, 0, 5),
                ];
            }
        }

        // 3. Cek Bentrok Ruangan
        $room = $data['room'] ?? null;
        if (! empty($room)) {
            $roomCollisions = $baseQuery()
                ->where(function ($q) use ($room) {
                    $q->where('metadata->room', $room)
                      ->orWhereHas('kelas', fn ($k) => $k->where('ruangan', $room));
                })
                ->when($kelasId, function ($q) use ($kelasId) {
                    $q->where('kelas_id', '!=', $kelasId);
                })
                ->with(['kelas', 'subject'])
                ->get();

            foreach ($roomCollisions as $rc) {
                $kelasName = $rc->kelas?->nama_kelas ?? 'Rombel lain';
                $mapelName = $rc->subject?->name ?? 'Pelajaran';

                $conflicts[] = [
                    'type' => 'room',
                    'title' => 'Bentrok Ruangan',
                    'message' => "Ruangan '{$room}' sedang digunakan oleh {$kelasName} ({$mapelName}) pada pukul " . substr($rc->time_start, 0, 5) . " - " . substr($rc->time_end, 0, 5) . ".",
                    'schedule_id' => $rc->id,
                    'room' => $room,
                    'time_start' => substr($rc->time_start, 0, 5),
                    'time_end' => substr($rc->time_end, 0, 5),
                ];
            }
        }

        return $conflicts;
    }

    private function ensureNoConflict(array $data, ?string $ignoreId = null): void
    {
        $conflicts = $this->detectConflicts($data, $ignoreId);

        if (! empty($conflicts)) {
            $messages = array_map(fn ($c) => $c['message'], $conflicts);
            throw ValidationException::withMessages([
                'time_start' => implode(' ', $messages),
            ]);
        }
    }

    private function authorizeView(User $user): void
    {
        abort_unless(
            $this->canAccessAllUnits($user)
            || $user->hasAnyRole([
                'Kepala Sekolah',
                'kepala_sekolah',
                'Divisi Pendidikan',
                'divisi_pendidikan',
                'Guru',
                'guru',
                'Staf',
                'staf',
                'Operator',
                'operator',
            ])
            || $user->hasAnyPermission(['academic.schedule.view', 'pembelajaran.jadwal_pelajaran', 'teacher.schedule.view', 'sistem.master_data']),
            403
        );
    }

    private function authorizeManage(User $user, string $action): void
    {
        $permission = "academic.schedule.{$action}";
        abort_unless(
            $this->canAccessAllUnits($user)
            || $user->hasAnyRole([
                'Kepala Sekolah',
                'kepala_sekolah',
                'Divisi Pendidikan',
                'divisi_pendidikan',
                'Admin',
                'admin',
                'Super Admin',
                'super_admin',
            ])
            || $user->hasAnyPermission([$permission, 'sistem.master_data', 'pembelajaran.jadwal_pelajaran']),
            403
        );
    }

    private function scopedQuery(User $user): Builder
    {
        $query = ClassSchedule::query();
        $unitIds = $this->accessibleUnitIds($user);

        if (! empty($unitIds)) {
            $query->whereHas('kelas', fn (Builder $kelasQuery) => $kelasQuery->whereIn('unit_pendidikan_id', $unitIds));
        }

        // Guru hanya bisa melihat jadwal mengajar mereka sendiri
        if ($this->isGuruRole($user)) {
            $employee = Employee::query()->where('user_id', $user->id)->first();
            if ($employee) {
                $query->where('employee_id', $employee->id);
            }
        }

        return $query;
    }

    private function isGuruRole(User $user): bool
    {
        return $user->hasAnyRole([
            'Guru',
            'guru',
            'Guru Mata Pelajaran',
            'guru_mata_pelajaran',
            'Guru PAI',
            'Guru Tahfizh',
            'guru_tahfizh',
            'Guru BK',
            'guru_bk',
            'Wali Kelas',
            'walas',
            'wali_kelas',
            'Musyrif',
            'musyrif',
            'Musyrifah',
            'Musyrif / Musyrifah',
            'Pembimbing',
        ]);
    }

    private function accessibleUnitIds(User $user): ?array
    {
        if ($this->canAccessAllUnits($user)) {
            return null;
        }

        $unitIds = Employee::query()
            ->where('user_id', $user->id)
            ->whereNotNull('unit_id')
            ->pluck('unit_id')
            ->all();

        $metaUnitId = data_get($user->metadata, 'unit_id')
            ?? data_get($user->metadata, 'unit_pendidikan_id')
            ?? data_get($user->metadata, 'unit_sekolah_id');

        if ($metaUnitId) {
            $unitIds[] = $metaUnitId;
        }

        $unitIds = array_values(array_unique(array_filter($unitIds)));

        return $unitIds;
    }

    private function canAccessAllUnits(User $user): bool
    {
        return $user->hasAnyRole([
            'Super Admin',
            'super_admin',
            'Admin',
            'admin',
            'Yayasan',
            'Ketua Yayasan',
            'ketua_yayasan',
            'sekretaris_yayasan',
            'bendahara_yayasan',
            'pengurus_yayasan',
        ]);
    }

    private function ensureScheduleContext(array $data, User $user): void
    {
        $semesterMatchesYear = Semester::query()
            ->whereKey($data['semester_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->exists();
        abort_unless($semesterMatchesYear, 422, 'Semester tidak termasuk dalam tahun ajaran yang dipilih.');

        if (! empty($data['kelas_id'])) {
            $kelas = Kelas::query()->findOrFail($data['kelas_id']);
            abort_unless(
                $kelas->tahun_ajaran_id === $data['academic_year_id']
                    && $kelas->semester_id === $data['semester_id'],
                422,
                'Kelas tidak sesuai dengan tahun ajaran atau semester yang dipilih.'
            );

            if (! $this->canAccessAllUnits($user)) {
                $allowedUnitIds = $this->accessibleUnitIds($user);
                if (! empty($allowedUnitIds)) {
                    abort_unless(in_array($kelas->unit_pendidikan_id, $allowedUnitIds, true), 403, 'Anda tidak memiliki hak akses mengelola jadwal unit ini.');
                }
            }

            if (! empty($data['employee_id'])) {
                abort_unless(
                    Employee::query()->whereKey($data['employee_id'])->exists(),
                    422,
                    'Guru pengampu tidak ditemukan atau tidak valid.'
                );
            }

            if (! empty($data['subject_id'])) {
                abort_unless(
                    Subject::query()->whereKey($data['subject_id'])->exists(),
                    422,
                    'Mata pelajaran tidak ditemukan atau tidak valid.'
                );
            }
        }
    }
}
