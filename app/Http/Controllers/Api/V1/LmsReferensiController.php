<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanReferensiRequest;
use App\Http\Requests\V1\UbahReferensiRequest;
use App\Http\Resources\V1\LmsReferensiResource;
use App\Models\Employee;
use App\Models\LmsModulAjar;
use App\Models\LmsReferensi;
use App\Models\User;
use App\Services\LmsReferensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsReferensiController extends Controller
{
    public function __construct(
        protected LmsReferensiService $referensiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());
        $filters = [
            'search' => $request->query('search'),
            'modul_ajar_id' => $request->query('modul_ajar_id'),
            'status' => $request->query('status'),
        ];
        if ($this->isTeacher($request->user())) {
            $filters['guru_id'] = $this->teacherEmployeeId($request->user());
        }

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $referensis = $this->referensiService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Referensi Pembelajaran berhasil dimuat.',
            'data' => LmsReferensiResource::collection($referensis),
            'meta' => [
                'current_page' => $referensis->currentPage(),
                'from' => $referensis->firstItem(),
                'last_page' => $referensis->lastPage(),
                'per_page' => $referensis->perPage(),
                'to' => $referensis->lastItem(),
                'total' => $referensis->total(),
            ],
            'statistik' => $this->referensiService->statistik(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeView(request()->user());
        $referensi = $this->referensiService->cariBerdasarkanId($id);

        if (! $referensi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Referensi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        $this->assertCanManageReferensi(request()->user(), $referensi);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Referensi Pembelajaran berhasil dimuat.',
            'data' => new LmsReferensiResource($referensi),
        ]);
    }

    public function store(SimpanReferensiRequest $request): JsonResponse
    {
        $this->authorizeManage($request->user(), 'create');
        $data = $request->validated();
        $this->assertCanManageModul($request->user(), $data['modul_ajar_id'] ?? null);
        $file = $request->file('file');

        $referensi = $this->referensiService->simpan($data, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Referensi Pembelajaran berhasil disimpan.',
            'data' => new LmsReferensiResource($referensi),
        ], 201);
    }

    public function update(UbahReferensiRequest $request, string $id): JsonResponse
    {
        $this->authorizeManage($request->user(), 'update');
        $existing = $this->referensiService->cariBerdasarkanId($id);
        if (! $existing) {
            return response()->json(['status' => 'error', 'message' => 'Referensi Pembelajaran tidak ditemukan.'], 404);
        }
        $this->assertCanManageReferensi($request->user(), $existing);
        $data = $request->validated();
        if (array_key_exists('modul_ajar_id', $data)) {
            $this->assertCanManageModul($request->user(), $data['modul_ajar_id']);
        }
        $file = $request->file('file');

        $referensi = $this->referensiService->ubah($id, $data, $file);

        if (! $referensi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Referensi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Referensi Pembelajaran berhasil diperbarui.',
            'data' => new LmsReferensiResource($referensi),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeManage(request()->user(), 'delete');
        $referensi = $this->referensiService->cariBerdasarkanId($id);
        if (! $referensi) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus Referensi Pembelajaran atau data tidak ditemukan.'], 404);
        }
        $this->assertCanManageReferensi(request()->user(), $referensi);
        $berhasil = $this->referensiService->hapus($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus Referensi Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Referensi Pembelajaran berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $this->authorizeManage(request()->user(), 'restore');
        $berhasil = $this->referensiService->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan Referensi Pembelajaran.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Referensi Pembelajaran berhasil dipulihkan.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $this->authorizeView(request()->user());
        return response()->json([
            'status' => 'success',
            'data' => $this->referensiService->statistik(),
        ]);
    }

    public function options(): JsonResponse
    {
        $this->authorizeView(request()->user());
        return response()->json([
            'status' => 'success',
            'data' => $this->referensiService->opsi(),
        ]);
    }

    private function authorizeView(User $user): void
    {
        abort_unless(
            $this->canAccessAllUnits($user)
            || $user->hasAnyPermission(['pembelajaran.kurikulum.view', 'pembelajaran.materi', 'teacher.material.view'])
            || $this->isTeacher($user)
            || $user->hasAnyRole(['Siswa', 'siswa', 'student', 'Orang Tua', 'orang_tua', 'parent']),
            403
        );
    }

    private function authorizeManage(User $user, string $action): void
    {
        abort_unless($this->canAccessAllUnits($user) || $user->hasAnyPermission(['pembelajaran.materi', "teacher.material.{$action}"]), 403);
    }

    private function canAccessAllUnits(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan', 'sekretaris_yayasan', 'bendahara_yayasan', 'pengurus_yayasan']);
    }

    private function isTeacher(User $user): bool
    {
        return $user->hasAnyRole(['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran']);
    }

    private function teacherEmployeeId(User $user): string
    {
        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');
        abort_unless($employeeId, 403);

        return $employeeId;
    }

    private function assertCanManageModul(User $user, ?string $modulId): void
    {
        if (! $this->isTeacher($user)) {
            return;
        }

        abort_unless($modulId && LmsModulAjar::query()->whereKey($modulId)->where('guru_id', $this->teacherEmployeeId($user))->exists(), 403);
    }

    private function assertCanManageReferensi(User $user, LmsReferensi $referensi): void
    {
        $this->assertCanManageModul($user, $referensi->modul_ajar_id);
    }
}
