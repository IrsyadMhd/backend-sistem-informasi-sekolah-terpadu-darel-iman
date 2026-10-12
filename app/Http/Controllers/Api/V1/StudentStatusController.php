<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentStatus::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('order')->orderBy('name');

        if ($request->boolean('all', false) || $request->input('per_page') === 'all') {
            return response()->json([
                'success' => true,
                'data' => $query->get(),
            ]);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $statuses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $statuses->items(),
            'meta' => [
                'current_page' => $statuses->currentPage(),
                'last_page' => $statuses->lastPage(),
                'per_page' => $statuses->perPage(),
                'total' => $statuses->total(),
            ],
        ]);
    }

    /**
     * Get dropdown options for selection fields.
     */
    public function dropdown(): JsonResponse
    {
        $options = StudentStatus::active()
            ->get(['id', 'code', 'name', 'badge_color', 'order'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'value' => $item->code,
                'code' => $item->code,
                'label' => $item->name,
                'name' => $item->name,
                'badge_color' => $item->badge_color,
                'order' => $item->order,
            ]);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:student_statuses,code'],
            'name' => ['required', 'string', 'max:100'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $status = StudentStatus::create([
            'code' => strtolower(trim($validated['code'])),
            'name' => trim($validated['name']),
            'badge_color' => $validated['badge_color'] ?? 'emerald',
            'order' => $validated['order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status siswa berhasil ditambahkan.',
            'data' => $status,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $status = StudentStatus::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $status = StudentStatus::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('student_statuses', 'code')->ignore($status->id)],
            'name' => ['required', 'string', 'max:100'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $status->update([
            'code' => strtolower(trim($validated['code'])),
            'name' => trim($validated['name']),
            'badge_color' => $validated['badge_color'] ?? $status->badge_color,
            'order' => $validated['order'] ?? $status->order,
            'is_active' => $validated['is_active'] ?? $status->is_active,
            'description' => $validated['description'] ?? $status->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status siswa berhasil diperbarui.',
            'data' => $status,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $status = StudentStatus::findOrFail($id);
        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Status siswa berhasil dihapus.',
        ]);
    }
}
