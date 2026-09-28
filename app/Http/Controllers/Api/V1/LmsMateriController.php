<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanMateriRequest;
use App\Http\Requests\V1\UbahMateriRequest;
use App\Http\Resources\V1\LmsMateriResource;
use App\Models\Employee;
use App\Models\LmsModulAjar;
use App\Models\User;
use App\Services\LmsMateriService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsMateriController extends Controller
{
    public function __construct(
        protected LmsMateriService $materiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());

        $filters = [
            'search' => $request->query('search'),
            'modul_ajar_id' => $request->query('modul_ajar_id'),
            'tipe' => $request->query('tipe'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];
        if ($this->isTeacher($request->user())) {
            $filters['guru_id'] = $this->teacherEmployeeId($request->user());
        }

        $perPage = (int) $request->query('per_page', 15);
        $allowedOrderBy = ['id', 'urutan', 'judul', 'tipe', 'created_at'];
        $rawOrderBy = (string) $request->query('order_by', 'urutan');
        $orderBy = in_array(strtolower($rawOrderBy), $allowedOrderBy, true) ? strtolower($rawOrderBy) : 'urutan';

        $rawOrderDir = strtolower((string) $request->query('order_dir', 'asc'));
        $orderDir = in_array($rawOrderDir, ['asc', 'desc'], true) ? $rawOrderDir : 'asc';

        $materis = $this->materiService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Materi Pembelajaran berhasil dimuat.',
            'data' => LmsMateriResource::collection($materis),
            'meta' => [
                'current_page' => $materis->currentPage(),
                'from' => $materis->firstItem(),
                'last_page' => $materis->lastPage(),
                'per_page' => $materis->perPage(),
                'to' => $materis->lastItem(),
                'total' => $materis->total(),
            ],
            'statistik' => $this->materiService->statistik(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeView(request()->user());

        $materi = $this->materiService->cariBerdasarkanId($id, true);

        if (! $materi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Materi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        $this->assertCanViewMateri(request()->user(), $materi);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Materi Pembelajaran berhasil dimuat.',
            'data' => new LmsMateriResource($materi),
        ]);
    }

    public function store(SimpanMateriRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManage($user, 'create');
        $data = $request->validated();
        $file = $request->file('file');

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            $data['guru_id'] = $employeeId;
            $modul = LmsModulAjar::find($data['modul_ajar_id']);
            if ($modul) {
                abort_unless($modul->guru_id === $employeeId, 403, 'Akses ditolak: Modul Ajar milik guru lain.');
            }
        }

        $materi = $this->materiService->simpan($data, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Materi Pembelajaran berhasil disimpan.',
            'data' => new LmsMateriResource($materi),
        ], 201);
    }

    public function update(UbahMateriRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManage($user, 'update');
        $materi = $this->materiService->cariBerdasarkanId($id);

        if (! $materi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Materi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            abort_unless($materi->guru_id === $employeeId, 403, 'Akses ditolak: Materi milik guru lain.');
        }

        $this->assertCanViewMateri($user, $materi);

        $data = $request->validated();
        $file = $request->file('file');

        $updated = $this->materiService->ubah($id, $data, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Materi Pembelajaran berhasil diperbarui.',
            'data' => new LmsMateriResource($updated),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = request()->user();
        $this->authorizeManage($user, 'delete');
        $materi = $this->materiService->cariBerdasarkanId($id);

        if (! $materi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Materi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            abort_unless($materi->guru_id === $employeeId, 403, 'Akses ditolak: Materi milik guru lain.');
        }

        $berhasil = $this->materiService->hapus($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus Materi Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Materi Pembelajaran berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $this->authorizeManage(request()->user(), 'restore');
        $berhasil = $this->materiService->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan Materi Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Materi Pembelajaran berhasil dipulihkan.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $this->authorizeView(request()->user());

        return response()->json([
            'status' => 'success',
            'data' => $this->materiService->statistik(),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());

        $user = $request->user();
        $isTeacher = $this->isTeacher($user);
        $search = $request->query('search');
        $includeId = $request->query('include_id') ?: $request->query('modul_ajar_id');

        if ($request->has('limit')) {
            $rawLimit = $request->query('limit');
            $limit = ($rawLimit === 'all' || $rawLimit === '0') ? null : max(1, min(1000, (int) $rawLimit));
        } else {
            // Scoped teachers only have their assigned modules (lightweight), so do not cap them at 50
            // Super Admin/Leadership defaults to 100 unless explicit limit or search is used
            $limit = $isTeacher ? null : 100;
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->materiService->opsi($user, $search, $limit, $includeId),
        ]);
    }

    private function authorizeView(User $user): void
    {
        abort_unless(
            $this->canAccessAllUnits($user)
            || $user->hasAnyPermission([
                'academic.view',
                'academic.view_any',
                'pembelajaran.kurikulum.view',
                'pembelajaran.materi',
                'teacher.material.view',
            ])
            || $user->hasAnyRole([
                'Guru',
                'guru',
                'Guru Mata Pelajaran',
                'guru_mata_pelajaran',
                'Kepala Sekolah',
                'kepala_sekolah',
                'Waka Kurikulum',
                'waka_kurikulum',
                'Waka Kesiswaan',
                'waka_kesiswaan',
                'Wakil Kesiswaan',
                'wakil_kesiswaan',
                'Tata Usaha',
                'tata_usaha',
            ]),
            403
        );
    }

    private function authorizeManage(User $user, string $action): void
    {
        abort_unless(
            $this->canAccessAllUnits($user)
            || $user->hasAnyPermission([
                'pembelajaran.materi',
                "teacher.material.{$action}",
            ]),
            403
        );
    }

    private function canAccessAllUnits(User $user): bool
    {
        return $user->hasAnyRole([
            'Super Admin',
            'super_admin',
            'Yayasan',
            'Ketua Yayasan',
            'ketua_yayasan',
            'sekretaris_yayasan',
            'Sekretaris Yayasan',
            'bendahara_yayasan',
            'Bendahara Yayasan',
            'pengurus_yayasan',
            'Pengurus Yayasan',
            'Kepala Bidang Pendidikan',
            'Divisi Pendidikan',
            'divisi_pendidikan',
            'Divisi Kurikulum',
            'Admin',
            'admin',
        ]);
    }

    private function isTeacher(User $user): bool
    {
        if ($this->canAccessAllUnits($user) || $user->hasAnyRole(['Kepala Sekolah', 'kepala_sekolah', 'Waka Kurikulum', 'waka_kurikulum', 'Waka Kesiswaan', 'waka_kesiswaan', 'Wakil Kesiswaan', 'wakil_kesiswaan', 'Tata Usaha', 'tata_usaha'])) {
            return false;
        }

        return $user->hasAnyRole([
            'Guru',
            'guru',
            'Guru Mata Pelajaran',
            'guru_mata_pelajaran',
        ]);
    }

    private function teacherEmployeeId(User $user): string
    {
        $employeeId = Employee::query()
            ->where('user_id', $user->id)
            ->value('id');

        abort_unless($employeeId, 403);

        return $employeeId;
    }

    private function assertCanViewMateri(User $user, $materi): void
    {
        if ($this->isTeacher($user)) {
            abort_unless($materi->guru_id === $this->teacherEmployeeId($user), 403);
        }
    }
}
