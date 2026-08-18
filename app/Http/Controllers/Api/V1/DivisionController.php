<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * DivisionController
 *
 * CRUD Master Divisi.
 * Endpoint: /api/v1/divisions
 */
class DivisionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Division::query();

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('code', 'ILIKE', $search);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        $perPage = (int) $request->query('per_page', 15);
        $data = $query
            ->with(['parent', 'children'])
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar divisi berhasil diambil.',
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:divisions,code',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:divisions,id',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $validated['created_by'] = Auth::id();

        $division = Division::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Divisi berhasil ditambahkan.',
            'data' => $division->load('parent'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $division = Division::with(['parent', 'children', 'employees'])->find($id);

        if (! $division) {
            return response()->json(['status' => 'error', 'message' => 'Divisi tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $division]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $division = Division::find($id);

        if (! $division) {
            return response()->json(['status' => 'error', 'message' => 'Divisi tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:divisions,code,'.$id,
            'name' => 'sometimes|string|max:120',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:divisions,id',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $validated['updated_by'] = Auth::id();
        $division->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Divisi berhasil diperbarui.',
            'data' => $division->fresh('parent'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $division = Division::find($id);

        if (! $division) {
            return response()->json(['status' => 'error', 'message' => 'Divisi tidak ditemukan.'], 404);
        }

        $division->update(['deleted_by' => Auth::id()]);
        $division->delete();

        return response()->json(['status' => 'success', 'message' => 'Divisi berhasil dihapus.']);
    }

    public function dropdown(): JsonResponse
    {
        $data = Division::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'parent_id']);

        return response()->json(['status' => 'success', 'data' => $data]);
    }
}
