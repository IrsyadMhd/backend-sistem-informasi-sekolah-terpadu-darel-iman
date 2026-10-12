<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TransportationMode;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ModaTransportasiController extends Controller
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    /**
     * Dapatkan daftar opsi dropdown moda transportasi (public authenticated).
     */
    public function dropdown(Request $request): JsonResponse
    {
        $modes = TransportationMode::active()
            ->select('id', 'name', 'code')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'value' => $item->name,
                    'label' => $item->name,
                    'code' => $item->code,
                ];
            });

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Data moda transportasi berhasil dimuat.',
            'data' => $modes,
        ]);
    }

    /**
     * Daftar master moda transportasi dengan pagination & search.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $modes = TransportationMode::query()
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))
            ->orderBy('order')
            ->orderBy('name')
            ->paginate($request->query('per_page', 25));

        return response()->json([
            'status' => 'success',
            'data' => $modes,
        ]);
    }

    /**
     * Simpan data moda transportasi baru.
     */
    public function store(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'unique:transportation_modes,code'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name'], '_');
        }

        $mode = TransportationMode::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Moda transportasi berhasil ditambahkan.',
            'data' => $mode,
        ], 201);
    }

    /**
     * Detail moda transportasi.
     */
    public function show(int $id): JsonResponse
    {
        $mode = TransportationMode::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $mode,
        ]);
    }

    /**
     * Perbarui data moda transportasi.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $mode = TransportationMode::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('transportation_modes', 'code')->ignore($mode->id)],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $mode->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Moda transportasi berhasil diperbarui.',
            'data' => $mode,
        ]);
    }

    /**
     * Toggle status aktif.
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $mode = TransportationMode::findOrFail($id);
        $mode->is_active = ! $mode->is_active;
        $mode->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status moda transportasi berhasil diubah.',
            'data' => $mode,
        ]);
    }

    /**
     * Hapus data moda transportasi.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $mode = TransportationMode::findOrFail($id);
        $mode->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Moda transportasi berhasil dihapus.',
        ]);
    }
}
