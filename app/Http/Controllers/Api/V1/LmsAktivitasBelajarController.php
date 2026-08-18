<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsAktivitasBelajarRequest;
use App\Http\Resources\V1\LmsAktivitasBelajarResource;
use App\Models\Employee;
use App\Models\LmsAktivitasBelajar;
use App\Models\LmsModulAjar;
use App\Models\User;
use App\Services\LmsAktivitasBelajarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsAktivitasBelajarController extends Controller
{
    public function __construct(
        protected LmsAktivitasBelajarService $aktivitasService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeView($request->user());
        $filters = $request->only(['search', 'modul_ajar_id', 'jenis_aktivitas', 'status']);
        if ($this->isTeacher($request->user())) {
            $filters['guru_id'] = $this->teacherEmployeeId($request->user());
        }
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'urutan');
        $orderDir = $request->get('order_dir', 'asc');

        $data = $this->aktivitasService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsAktivitasBelajarResource::collection($data);
    }

    public function store(LmsAktivitasBelajarRequest $request): JsonResponse
    {
        $this->authorizeManage($request->user(), 'create');
        $this->assertCanManageModul($request->user(), $request->validated('modul_ajar_id'));
        $aktivitas = $this->aktivitasService->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas Belajar berhasil dibuat.',
            'data' => new LmsAktivitasBelajarResource($aktivitas->load(['modulAjar', 'creator'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeView(request()->user());
        $aktivitas = $this->aktivitasService->cariBerdasarkanId($id);

        if (! $aktivitas) {
            return response()->json([
                'success' => false,
                'message' => 'Aktivitas Belajar tidak ditemukan.',
            ], 404);
        }

        $this->assertCanManageAktivitas(request()->user(), $aktivitas);

        return response()->json([
            'success' => true,
            'data' => new LmsAktivitasBelajarResource($aktivitas),
        ]);
    }

    public function update(LmsAktivitasBelajarRequest $request, string $id): JsonResponse
    {
        $this->authorizeManage($request->user(), 'update');
        $existing = $this->aktivitasService->cariBerdasarkanId($id);
        if (! $existing) {
            return response()->json(['success' => false, 'message' => 'Aktivitas Belajar tidak ditemukan atau gagal diperbarui.'], 404);
        }
        $this->assertCanManageAktivitas($request->user(), $existing);
        $this->assertCanManageModul($request->user(), $request->validated('modul_ajar_id'));
        $aktivitas = $this->aktivitasService->ubah($id, $request->validated());

        if (! $aktivitas) {
            return response()->json([
                'success' => false,
                'message' => 'Aktivitas Belajar tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas Belajar berhasil diperbarui.',
            'data' => new LmsAktivitasBelajarResource($aktivitas),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeManage(request()->user(), 'delete');
        $aktivitas = $this->aktivitasService->cariBerdasarkanId($id);
        if (! $aktivitas) {
            return response()->json(['success' => false, 'message' => 'Aktivitas Belajar tidak ditemukan atau gagal dihapus.'], 404);
        }
        $this->assertCanManageAktivitas(request()->user(), $aktivitas);
        $deleted = $this->aktivitasService->hapus($id);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Aktivitas Belajar tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas Belajar berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $this->authorizeManage(request()->user(), 'restore');
        $aktivitas = LmsAktivitasBelajar::withTrashed()->find($id);
        if (! $aktivitas) {
            return response()->json(['success' => false, 'message' => 'Gagal memulihkan Aktivitas Belajar.'], 400);
        }
        $this->assertCanManageAktivitas(request()->user(), $aktivitas);
        $restored = $this->aktivitasService->pulihkan($id);

        if (! $restored) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan Aktivitas Belajar.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas Belajar berhasil dipulihkan.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $this->authorizeView(request()->user());
        $stats = $this->aktivitasService->dapatkanStatistik();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $user = request()->user();
        $this->authorizeView($user);
        $modulOptions = $this->aktivitasService->dapatkanOpsiModulAjar(
            $this->isTeacher($user) ? $this->teacherEmployeeId($user) : null
        );

        return response()->json([
            'success' => true,
            'data' => [
                'modul_ajar' => $modulOptions,
                'jenis_aktivitas' => [
                    'Pendahuluan',
                    'Inti',
                    'Penutup',
                    'Diskusi',
                    'Kuis',
                    'Tugas',
                    'Presentasi',
                    'Refleksi',
                    'Eksperimen',
                    'Praktikum',
                ],
                'status' => [
                    'aktif' => 'Aktif',
                    'draft' => 'Draft',
                    'nonaktif' => 'Nonaktif',
                ],
            ],
        ]);
    }

    private function authorizeView(User $user): void
    {
        abort_unless($this->canAccessAllUnits($user) || $user->hasAnyPermission(['pembelajaran.kurikulum.view', 'pembelajaran.materi', 'teacher.material.view']) || $this->isTeacher($user), 403);
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

    private function assertCanManageModul(User $user, string $modulId): void
    {
        if (! $this->isTeacher($user)) {
            return;
        }

        abort_unless(LmsModulAjar::query()->whereKey($modulId)->where('guru_id', $this->teacherEmployeeId($user))->exists(), 403);
    }

    private function assertCanManageAktivitas(User $user, LmsAktivitasBelajar $aktivitas): void
    {
        $this->assertCanManageModul($user, $aktivitas->modul_ajar_id);
    }
}
