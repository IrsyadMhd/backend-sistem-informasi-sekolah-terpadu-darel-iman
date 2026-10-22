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

    private const SYSTEM_STATUS_CODES = [
        'aktif',
        'lulus',
        'mutasi',
        'berhenti',
        'cuti',
        'nonaktif',
    ];

    private function assertCanManageStatus(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if ($user->hasRole('Super Admin') || $user->hasRole('super_admin') || $user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            return;
        }

        if ($user->can('sistem.master_data') || $user->can('student.manage') || $user->can('operator.manage')) {
            return;
        }

        abort(403, 'Akses ditolak. Anda tidak memiliki wewenang untuk mengelola master status siswa.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->assertCanManageStatus($request);

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
        $this->assertCanManageStatus($request);
        $status = StudentStatus::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('student_statuses', 'code')->ignore($status->id)],
            'name' => ['required', 'string', 'max:100'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $targetCode = strtolower(trim($validated['code']));

        // Proteksi kode status sistem agar integritas referensi operasional tetap terjaga
        if (in_array($status->code, self::SYSTEM_STATUS_CODES, true) && $targetCode !== $status->code) {
            return response()->json([
                'success' => false,
                'message' => "Kode status sistem '{$status->code}' dilindungi dan tidak dapat diubah.",
            ], 422);
        }

        $status->update([
            'code' => $targetCode,
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
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->assertCanManageStatus($request);
        $status = StudentStatus::findOrFail($id);

        // 1. Cegah penghapusan status sistem inti
        if (in_array($status->code, self::SYSTEM_STATUS_CODES, true)) {
            return response()->json([
                'success' => false,
                'message' => "Status sistem resmi '{$status->name}' dilindungi dan tidak dapat dihapus. Silakan nonaktifkan status melalui saklar aktif.",
            ], 422);
        }

        // 2. Cegah penghapusan jika status masih direferensikan pada data siswa aktif
        $studentCount = \Illuminate\Support\Facades\DB::table('students')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($status) {
                $q->where('metadata->status_siswa', $status->code)
                    ->orWhere('metadata->status_siswa', $status->name);
            })
            ->count();

        if ($studentCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Status '{$status->name}' masih digunakan oleh {$studentCount} data siswa aktif dan tidak dapat dihapus. Silakan nonaktifkan status bila tidak lagi berlaku.",
            ], 422);
        }

        // 3. Cegah penghapusan jika tercatat pada riwayat mutasi siswa
        if (\Illuminate\Support\Facades\Schema::hasTable('student_mutations')) {
            $mutationCount = \Illuminate\Support\Facades\DB::table('student_mutations')
                ->where(function ($q) use ($status) {
                    $q->where('to_status', $status->code)
                        ->orWhere('from_status', $status->code);
                })
                ->count();

            if ($mutationCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Status '{$status->name}' masih direferensikan dalam {$mutationCount} riwayat mutasi siswa.",
                ], 422);
            }
        }

        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Status siswa berhasil dihapus.',
        ]);
    }
}
