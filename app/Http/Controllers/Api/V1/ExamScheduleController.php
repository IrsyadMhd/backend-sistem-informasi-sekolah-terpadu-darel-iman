<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\ExamCategory;
use App\Models\ExamSchedule;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamScheduleController extends Controller
{
    /**
     * List Jadwal Ujian
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExamSchedule::query()->with([
            'academicYear:id,name,is_active',
            'semester:id,name,is_active',
            'examCategory:id,name,code,color',
            'kelas:id,nama_kelas,unit_pendidikan_id',
            'kelas.unitPendidikan:id,name',
            'subject:id,name,nama_mapel,code,kode_mapel',
            'invigilator:id,nama_lengkap,niy',
        ]);

        $user = $request->user();
        if ($user) {
            $accessScope = app(\App\Services\AccessScopeService::class);
            $isGlobal = $accessScope->hasGlobalScope($user) || $user->hasAnyRole([
                'Divisi Pendidikan', 'divisi_pendidikan', 'divisi-pendidikan',
                'Kepala Bidang Pendidikan', 'kepala_bidang_pendidikan',
                'Divisi Kurikulum', 'Divisi Kesiswaan',
            ]);

            if (! $isGlobal) {
                $allowedUnits = $accessScope->accessibleEducationUnits($user)->pluck('id')->all();
                if (! empty($allowedUnits)) {
                    $query->whereHas('kelas', fn ($q) => $q->whereIn('unit_pendidikan_id', $allowedUnits));
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        if ($request->filled('unit_pendidikan_id')) {
            $unitId = $request->query('unit_pendidikan_id');
            $query->whereHas('kelas', fn ($q) => $q->where('unit_pendidikan_id', $unitId));
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        if ($request->filled('exam_category_id')) {
            $query->where('exam_category_id', $request->query('exam_category_id'));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->query('kelas_id'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->query('subject_id'));
        }

        if ($request->filled('invigilator_id')) {
            $query->where('invigilator_id', $request->query('invigilator_id'));
        }

        if ($request->filled('exam_date')) {
            $query->where('exam_date', $request->query('exam_date'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->whereHas('subject', fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('nama_mapel', 'ilike', "%{$search}%"))
                    ->orWhereHas('kelas', fn ($q) => $q->where('nama_kelas', 'ilike', "%{$search}%"))
                    ->orWhereHas('invigilator', fn ($q) => $q->where('nama_lengkap', 'ilike', "%{$search}%"))
                    ->orWhereHas('examCategory', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                    ->orWhere('room', 'ilike', "%{$search}%");
            });
        }

        $statsQuery = clone $query;
        $totalJadwal = (clone $statsQuery)->count();
        $totalKelas = (clone $statsQuery)->distinct('kelas_id')->count('kelas_id');
        $totalPengawas = (clone $statsQuery)->whereNotNull('invigilator_id')->distinct('invigilator_id')->count('invigilator_id');
        $totalMapel = (clone $statsQuery)->distinct('subject_id')->count('subject_id');

        $perPage = min(max((int) $request->query('per_page', 15), 1), 500);
        $data = $query->orderBy('exam_date')->orderBy('time_start')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar jadwal ujian berhasil dimuat.',
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
                'total_jadwal' => $totalJadwal,
                'total_kelas' => $totalKelas,
                'total_pengawas' => $totalPengawas,
                'total_mapel' => $totalMapel,
            ],
        ]);
    }

    /**
     * Simpan Jadwal Ujian Baru
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|uuid|exists:academic_years,id',
            'semester_id' => 'required|uuid|exists:semesters,id',
            'exam_category_id' => 'required|uuid|exists:exam_categories,id',
            'kelas_id' => 'required|uuid|exists:tbl_kelas,id',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'exam_date' => 'required|date',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
            'duration_minutes' => 'nullable|integer|min:1',
            'room' => 'nullable|string|max:100',
            'invigilator_id' => 'nullable|uuid|exists:employees,id',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        // Cek Bentrok Kelas (Rombel yang sama tidak boleh ujian 2 mapel di jam yang sama pada hari yang sama)
        $this->ensureNoExamConflict($validated);

        if (empty($validated['duration_minutes'])) {
            $start = strtotime($validated['time_start']);
            $end = strtotime($validated['time_end']);
            $validated['duration_minutes'] = max(1, (int) round(($end - $start) / 60));
        }

        $validated['created_by'] = Auth::id();
        $exam = ExamSchedule::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal ujian berhasil ditambahkan.',
            'data' => $exam->load(['examCategory', 'subject', 'kelas', 'invigilator']),
        ], 201);
    }

    /**
     * Detail Jadwal Ujian
     */
    public function show(string $id): JsonResponse
    {
        $exam = ExamSchedule::with([
            'academicYear', 'semester', 'examCategory', 'kelas.unitPendidikan',
            'subject', 'invigilator',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $exam,
        ]);
    }

    /**
     * Update Jadwal Ujian
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $exam = ExamSchedule::findOrFail($id);

        $validated = $request->validate([
            'academic_year_id' => 'required|uuid|exists:academic_years,id',
            'semester_id' => 'required|uuid|exists:semesters,id',
            'exam_category_id' => 'required|uuid|exists:exam_categories,id',
            'kelas_id' => 'required|uuid|exists:tbl_kelas,id',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'exam_date' => 'required|date',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
            'duration_minutes' => 'nullable|integer|min:1',
            'room' => 'nullable|string|max:100',
            'invigilator_id' => 'nullable|uuid|exists:employees,id',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $this->ensureNoExamConflict($validated, $id);

        if (empty($validated['duration_minutes'])) {
            $start = strtotime($validated['time_start']);
            $end = strtotime($validated['time_end']);
            $validated['duration_minutes'] = max(1, (int) round(($end - $start) / 60));
        }

        $validated['updated_by'] = Auth::id();
        $exam->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal ujian berhasil diperbarui.',
            'data' => $exam->load(['examCategory', 'subject', 'kelas', 'invigilator']),
        ]);
    }

    /**
     * Hapus Jadwal Ujian
     */
    public function destroy(string $id): JsonResponse
    {
        $exam = ExamSchedule::findOrFail($id);
        $exam->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal ujian berhasil dihapus.',
        ]);
    }

    /**
     * Opsi Dropdown Form
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessScope = app(\App\Services\AccessScopeService::class);
        $isGlobal = $user && ($accessScope->hasGlobalScope($user) || $user->hasAnyRole([
            'Divisi Pendidikan', 'divisi_pendidikan', 'divisi-pendidikan',
            'Kepala Bidang Pendidikan', 'kepala_bidang_pendidikan',
            'Divisi Kurikulum', 'Divisi Kesiswaan',
        ]));

        $accessibleUnitsQuery = $isGlobal
            ? EducationUnit::query()
            : ($user ? $accessScope->accessibleEducationUnits($user) : EducationUnit::query());

        $unitId = $request->query('unit_pendidikan_id');
        if (! $isGlobal && empty($unitId) && $user) {
            $firstAccessible = (clone $accessibleUnitsQuery)->first();
            $unitId = $firstAccessible?->id;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'categories' => ExamCategory::query()->where('is_active', true)->orderBy('name')->get(),
                'units' => (clone $accessibleUnitsQuery)->orderBy('name')->get(['id', 'name', 'code']),
                'academic_years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'is_active']),
                'semesters' => Semester::query()->orderByDesc('start_date')->get(['id', 'academic_year_id', 'name', 'is_active']),
                'classes' => Kelas::query()
                    ->when($unitId, fn ($q) => $q->where('unit_pendidikan_id', $unitId))
                    ->with('unitPendidikan:id,name')
                    ->orderBy('nama_kelas')
                    ->get(['id', 'nama_kelas', 'unit_pendidikan_id']),
                'subjects' => Subject::query()
                    ->when($unitId, fn ($q) => $q->where('unit_pendidikan_id', $unitId))
                    ->where(fn ($q) => $q->where('status', true)->orWhereNull('status'))
                    ->orderByRaw('COALESCE(nama_mapel, name)')
                    ->get(['id', 'nama_mapel', 'name', 'code', 'kode_mapel', 'unit_pendidikan_id']),
                'invigilators' => Employee::query()
                    ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
                    ->where('status', 'Aktif')
                    ->orderBy('nama_lengkap')
                    ->get(['id', 'nama_lengkap', 'niy', 'unit_id']),
            ],
        ]);
    }

    /**
     * CRUD Kategori Ujian
     */
    public function categoriesIndex(Request $request): JsonResponse
    {
        $categories = ExamCategory::query()
            ->withCount('examSchedules')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $category = ExamCategory::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori ujian berhasil ditambahkan.',
            'data' => $category,
        ], 201);
    }

    public function categoriesUpdate(Request $request, string $id): JsonResponse
    {
        $category = ExamCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori ujian berhasil diperbarui.',
            'data' => $category,
        ]);
    }

    public function categoriesDestroy(string $id): JsonResponse
    {
        $category = ExamCategory::findOrFail($id);

        if ($category->examSchedules()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori ujian ini tidak dapat dihapus karena sudah memiliki data jadwal ujian.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori ujian berhasil dihapus.',
        ]);
    }

    /**
     * Anti Bentrok Ujian
     */
    private function ensureNoExamConflict(array $data, ?string $ignoreId = null): void
    {
        $conflictQuery = ExamSchedule::query()
            ->where('exam_date', $data['exam_date'])
            ->where('time_start', '<', $data['time_end'])
            ->where('time_end', '>', $data['time_start'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));

        // 1. Bentrok Kelas (Rombel yang sama)
        $kelasConflict = (clone $conflictQuery)
            ->where('kelas_id', $data['kelas_id'])
            ->with(['subject', 'examCategory'])
            ->first();

        if ($kelasConflict) {
            $subj = $kelasConflict->subject?->name ?? 'Mapel Lain';
            $cat = $kelasConflict->examCategory?->name ?? 'Ujian';
            throw ValidationException::withMessages([
                'kelas_id' => ["Kelas ini sudah memiliki jadwal {$cat} mata pelajaran {$subj} pada jam {$kelasConflict->time_start} - {$kelasConflict->time_end}."],
            ]);
        }

        // 2. Bentrok Pengawas (Guru yang sama tidak bisa mengawas 2 ruangan berbeda di jam yang sama)
        if (!empty($data['invigilator_id'])) {
            $invigConflict = (clone $conflictQuery)
                ->where('invigilator_id', $data['invigilator_id'])
                ->with(['kelas', 'subject'])
                ->first();

            if ($invigConflict) {
                $kls = $invigConflict->kelas?->nama_kelas ?? 'Kelas Lain';
                throw ValidationException::withMessages([
                    'invigilator_id' => ["Guru pengawas ini sudah terjadwal mengawas di {$kls} pada jam {$invigConflict->time_start} - {$invigConflict->time_end}."],
                ]);
            }
        }
    }
}
