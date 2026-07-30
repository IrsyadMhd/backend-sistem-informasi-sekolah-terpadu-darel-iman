<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
        $query = ClassSchedule::with([
            'kelas',       // tbl_kelas (primer)
            'schoolClass', // classes (legacy)
            'employee',    // employees (primer)
            'teacher',     // teachers (legacy)
            'subject',
            'academicYear',
            'semester',
        ]);

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

        if ($request->filled('day_of_week')) {
            $query->where('day_of_week', (int) $request->query('day_of_week'));
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

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $data = $query->orderBy('day_of_week')->orderBy('time_start')->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar jadwal pelajaran berhasil diambil.',
            'data'    => $data->items(),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'from' => $data->firstItem(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'to' => $data->lastItem(),
                'total' => $data->total(),
            ],
            'statistik' => [
                'total' => ClassSchedule::count(),
                'aktif' => ClassSchedule::where('is_active', true)->count(),
                'tidak_aktif' => ClassSchedule::where('is_active', false)->count(),
                'guru_terjadwal' => ClassSchedule::whereNotNull('employee_id')->distinct()->count('employee_id'),
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Opsi jadwal pelajaran berhasil diambil.',
            'data' => [
                'kelas' => Kelas::query()
                    ->with(['unitPendidikan:id,name', 'tahunAjaran:id,name', 'semester:id,name'])
                    ->orderBy('nama_kelas')
                    ->get(['id', 'nama_kelas', 'kode_kelas', 'unit_pendidikan_id', 'tahun_ajaran_id', 'semester_id']),
                'guru' => Employee::query()
                    ->where('status', 'Aktif')
                    ->orderBy('nama_lengkap')
                    ->get(['id', 'nama_lengkap', 'niy', 'nik', 'unit_id']),
                'mata_pelajaran' => Subject::query()
                    ->where(fn ($q) => $q->where('status', true)->orWhereNull('status'))
                    ->orderByRaw('COALESCE(nama_mapel, name)')
                    ->get(['id', 'nama_mapel', 'name', 'kode_mapel', 'code', 'unit_pendidikan_id']),
                'tahun_ajaran' => AcademicYear::query()
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
        $validated = $request->validate([
            'kelas_id'        => 'nullable|uuid|exists:tbl_kelas,id',
            'class_id'        => 'nullable|uuid|exists:classes,id',
            'employee_id'     => 'nullable|uuid|exists:employees,id',
            'teacher_id'      => 'nullable|uuid|exists:teachers,id',
            'subject_id'      => 'required|uuid|exists:subjects,id',
            'classroom_id'    => 'nullable|uuid|exists:classrooms,id',
            'academic_year_id' => 'required|uuid|exists:academic_years,id',
            'semester_id'     => 'required|uuid|exists:semesters,id',
            'day_of_week'     => 'required|integer|min:1|max:7',
            'time_start'      => 'required|date_format:H:i',
            'time_end'        => 'required|date_format:H:i|after:time_start',
            'week_type'       => 'nullable|string|in:all,odd,even',
            'is_active'       => 'nullable|boolean',
            'metadata'        => 'nullable|array',
        ]);

        // Validasi: harus ada minimal satu referensi kelas
        if (empty($validated['kelas_id']) && empty($validated['class_id'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Harus mengisi salah satu dari kelas_id (tbl_kelas) atau class_id (classes).',
            ], 422);
        }

        if (empty($validated['employee_id']) && empty($validated['teacher_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Guru pengampu wajib dipilih.',
            ], 422);
        }

        $this->ensureNoConflict($validated);
        $validated['created_by'] = Auth::id();

        $schedule = ClassSchedule::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal pelajaran berhasil ditambahkan.',
            'data'    => $schedule->load(['kelas', 'employee', 'subject', 'semester']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $schedule = ClassSchedule::with([
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
        $schedule = ClassSchedule::find($id);

        if (! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'kelas_id'     => 'nullable|uuid|exists:tbl_kelas,id',
            'class_id'     => 'nullable|uuid|exists:classes,id',
            'employee_id'  => 'nullable|uuid|exists:employees,id',
            'teacher_id'   => 'nullable|uuid|exists:teachers,id',
            'subject_id'   => 'sometimes|uuid|exists:subjects,id',
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
            'academic_year_id' => 'sometimes|uuid|exists:academic_years,id',
            'semester_id'  => 'sometimes|uuid|exists:semesters,id',
            'day_of_week'  => 'sometimes|integer|min:1|max:7',
            'time_start'   => 'sometimes|date_format:H:i',
            'time_end'     => 'sometimes|date_format:H:i|after:time_start',
            'week_type'    => 'nullable|string|in:all,odd,even',
            'is_active'    => 'nullable|boolean',
            'metadata'     => 'nullable|array',
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

        $this->ensureNoConflict($merged, $schedule->id);
        $validated['updated_by'] = Auth::id();
        $schedule->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal berhasil diperbarui.',
            'data'    => $schedule->fresh(['kelas', 'employee', 'subject']),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $schedule = ClassSchedule::find($id);

        if (! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
        }

        $schedule->update(['deleted_by' => Auth::id()]);
        $schedule->delete();

        return response()->json(['status' => 'success', 'message' => 'Jadwal berhasil dihapus.']);
    }

    private function ensureNoConflict(array $data, ?string $ignoreId = null): void
    {
        $query = ClassSchedule::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where('time_start', '<', $data['time_end'])
            ->where('time_end', '>', $data['time_start'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($data) {
                if (!empty($data['employee_id'])) {
                    $q->where('employee_id', $data['employee_id']);
                } elseif (!empty($data['teacher_id'])) {
                    $q->where('teacher_id', $data['teacher_id']);
                }

                if (!empty($data['kelas_id'])) {
                    $q->orWhere('kelas_id', $data['kelas_id']);
                } elseif (!empty($data['class_id'])) {
                    $q->orWhere('class_id', $data['class_id']);
                }
            });

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'time_start' => 'Jadwal bentrok dengan jadwal aktif guru atau kelas pada hari dan jam yang sama.',
            ]);
        }
    }
}
