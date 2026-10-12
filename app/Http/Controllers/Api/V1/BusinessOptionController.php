<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessOption;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusinessOptionController extends Controller
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    /**
     * Dapatkan daftar dropdown opsi bisnis berdasarkan group (public authenticated).
     */
    public function dropdown(Request $request, string $group): JsonResponse
    {
        $options = BusinessOption::byGroup($group)
            ->active()
            ->select('id', 'group', 'code', 'name', 'order', 'is_active')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'value' => $item->name,
                'label' => $item->name,
                'code' => $item->code,
                'group' => $item->group,
                'order' => $item->order,
                'is_active' => (bool) $item->is_active,
            ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $options,
        ]);
    }

    /**
     * Tampilkan seluruh data master opsi bisnis (manajemen).
     */
    public function index(Request $request): JsonResponse
    {
        $group = $request->query('group');
        $search = $request->query('search');

        $query = BusinessOption::query()
            ->when($group, fn ($q) => $q->where('group', $group))
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))
            ->orderBy('group')
            ->orderBy('order');

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($request->query('per_page', 25)),
        ]);
    }

    /**
     * Simpan opsi bisnis baru.
     */
    public function store(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $validated = $request->validate([
            'group' => ['required', 'string', 'max:60'],
            'code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('business_options')->where('group', $request->input('group')),
            ],
            'name' => ['required', 'string', 'max:120'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name'], '_');
        }

        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $option = BusinessOption::create($validated);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Opsi bisnis berhasil ditambahkan.',
            'data' => $option,
        ], 201);
    }

    /**
     * Detail opsi bisnis.
     */
    public function show(string $id): JsonResponse
    {
        $option = BusinessOption::findOrFail($id);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $option,
        ]);
    }

    /**
     * Perbarui opsi bisnis.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $option = BusinessOption::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:60',
                Rule::unique('business_options')->where('group', $option->group)->ignore($option->id),
            ],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $validated['updated_by'] = $request->user()->id;
        $option->update($validated);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Opsi bisnis berhasil diperbarui.',
            'data' => $option,
        ]);
    }

    /**
     * Toggle status aktif opsi bisnis.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $option = BusinessOption::findOrFail($id);
        $option->is_active = ! $option->is_active;
        $option->updated_by = $request->user()->id;
        $option->save();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Status opsi bisnis berhasil diubah.',
            'data' => $option,
        ]);
    }

    /**
     * Hapus opsi bisnis (soft delete).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $option = BusinessOption::findOrFail($id);
        $option->delete();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Opsi bisnis berhasil dihapus.',
        ]);
    }
}
