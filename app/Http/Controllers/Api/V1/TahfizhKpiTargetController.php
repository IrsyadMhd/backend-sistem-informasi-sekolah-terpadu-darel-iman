<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationUnit;
use App\Models\TahfizhKpiTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TahfizhKpiTargetController extends Controller
{
    /**
     * Menampilkan daftar target KPI Tahfizh dengan filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TahfizhKpiTarget::query()->with('educationUnit');

        if ($request->filled('education_unit_id')) {
            $query->where('education_unit_id', $request->education_unit_id);
        }

        if ($request->filled('unit_level') && $request->unit_level !== 'all') {
            $query->where('unit_level', $request->unit_level);
        }

        if ($request->filled('grade_level') && $request->grade_level !== 'all') {
            $query->where('grade_level', $request->grade_level);
        }

        if ($request->filled('program_type') && $request->program_type !== 'all') {
            $query->where('program_type', $request->program_type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('target_name', 'ilike', $search)
                    ->orWhere('target_juz', 'ilike', $search)
                    ->orWhere('description', 'ilike', $search);
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage === -1 || $request->input('paginate') === 'false') {
            $targets = $query->orderBy('unit_level')->orderBy('grade_level')->get();
            return response()->json([
                'success' => true,
                'data' => $targets,
            ]);
        }

        $targets = $query->orderBy('unit_level')->orderBy('grade_level')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $targets->items(),
            'meta' => [
                'current_page' => $targets->currentPage(),
                'last_page' => $targets->lastPage(),
                'per_page' => $targets->perPage(),
                'total' => $targets->total(),
            ],
        ]);
    }

    /**
     * Simpan target KPI Tahfizh baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_name' => 'required|string|max:150',
            'education_unit_id' => 'nullable|uuid|exists:education_units,id',
            'unit_level' => 'nullable|string|max:30',
            'grade_level' => 'nullable|string|max:20',
            'program_type' => 'required|string|in:all,fullday,boarding,regular',
            'target_juz' => 'nullable|string|max:50',
            'target_surahs' => 'required|integer|min:1|max:114',
            'target_ayats' => 'required|integer|min:1|max:6236',
            'minimum_kkm_ayats' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['unit_level']) && ! empty($validated['education_unit_id'])) {
            $unit = EducationUnit::find($validated['education_unit_id']);
            $validated['unit_level'] = $unit?->level;
        }

        $target = TahfizhKpiTarget::create(array_merge($validated, [
            'id' => (string) Str::uuid(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        $target->load('educationUnit');

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan target KPI Tahfizh berhasil disimpan.',
            'data' => $target,
        ], 201);
    }

    /**
     * Tampilkan detail target KPI.
     */
    public function show($id): JsonResponse
    {
        $target = TahfizhKpiTarget::with('educationUnit')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $target,
        ]);
    }

    /**
     * Perbarui target KPI.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $target = TahfizhKpiTarget::findOrFail($id);

        $validated = $request->validate([
            'target_name' => 'sometimes|required|string|max:150',
            'education_unit_id' => 'nullable|uuid|exists:education_units,id',
            'unit_level' => 'nullable|string|max:30',
            'grade_level' => 'nullable|string|max:20',
            'program_type' => 'sometimes|required|string|in:all,fullday,boarding,regular',
            'target_juz' => 'nullable|string|max:50',
            'target_surahs' => 'sometimes|required|integer|min:1|max:114',
            'target_ayats' => 'sometimes|required|integer|min:1|max:6236',
            'minimum_kkm_ayats' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (array_key_exists('education_unit_id', $validated) && empty($validated['unit_level']) && ! empty($validated['education_unit_id'])) {
            $unit = EducationUnit::find($validated['education_unit_id']);
            $validated['unit_level'] = $unit?->level;
        }

        $target->update(array_merge($validated, [
            'updated_by' => $request->user()?->id,
        ]));

        $target->load('educationUnit');

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan target KPI Tahfizh berhasil diperbarui.',
            'data' => $target,
        ]);
    }

    /**
     * Hapus target KPI.
     */
    public function destroy($id): JsonResponse
    {
        $target = TahfizhKpiTarget::findOrFail($id);
        $target->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan target KPI Tahfizh berhasil dihapus.',
        ]);
    }

    /**
     * Opsi referensi filter untuk form dan pencarian.
     */
    public function options(): JsonResponse
    {
        $units = EducationUnit::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'level', 'program_type']);

        $distinctLevels = TahfizhKpiTarget::distinct()->pluck('unit_level')->filter()->values();
        $fallbackLevels = ['TKIT', 'TAUD', 'SDIT', 'MIT', 'SMPIT', 'SMAIT', 'PONPES', 'MAHAD'];
        $levels = collect($distinctLevels)->merge($fallbackLevels)->unique()->values();

        $grades = ['ALL', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
        $programTypes = [
            ['value' => 'all', 'label' => 'Semua Program'],
            ['value' => 'fullday', 'label' => 'Full Day School'],
            ['value' => 'boarding', 'label' => 'Pesantren / Asrama (Boarding)'],
            ['value' => 'regular', 'label' => 'Reguler'],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'units' => $units,
                'levels' => $levels,
                'grades' => $grades,
                'program_types' => $programTypes,
            ],
        ]);
    }
}
