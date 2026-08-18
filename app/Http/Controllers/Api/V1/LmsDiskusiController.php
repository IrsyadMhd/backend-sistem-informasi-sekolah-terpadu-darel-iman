<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsDiskusiKomentarRequest;
use App\Http\Requests\V1\LmsDiskusiRequest;
use App\Http\Resources\V1\LmsDiskusiKomentarResource;
use App\Http\Resources\V1\LmsDiskusiResource;
use App\Models\Employee;
use App\Models\LmsDiskusi;
use App\Models\LmsDiskusiKomentar;
use App\Models\LmsModulAjar;
use App\Models\Student;
use App\Models\User;
use App\Services\LmsDiskusiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsDiskusiController extends Controller
{
    public function __construct(
        protected LmsDiskusiService $diskusiService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeView($request->user());
        $filters = $request->only(['search', 'modul_ajar_id', 'kategori', 'status']);
        if ($this->isTeacher($request->user())) {
            $filters['guru_id'] = $this->teacherEmployeeId($request->user());
        }
        if ($this->isStudent($request->user())) {
            $filters['kelas_ids'] = $this->studentClassIds($request->user());
            $filters['published_only'] = true;
        }
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $data = $this->diskusiService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsDiskusiResource::collection($data);
    }

    public function store(LmsDiskusiRequest $request): JsonResponse
    {
        $this->authorizeTeacherManage($request->user());
        $this->assertCanManageModul($request->user(), $request->validated('modul_ajar_id'));
        $diskusi = $this->diskusiService->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Diskusi Kelas berhasil dibuat.',
            'data' => new LmsDiskusiResource($diskusi->load(['modulAjar', 'creator', 'komentar'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeView(request()->user());
        $diskusi = $this->diskusiService->cariBerdasarkanId($id);

        if (! $diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan.',
            ], 404);
        }

        $this->assertCanViewDiskusi(request()->user(), $diskusi);

        return response()->json([
            'success' => true,
            'data' => new LmsDiskusiResource($diskusi),
        ]);
    }

    public function update(LmsDiskusiRequest $request, string $id): JsonResponse
    {
        $this->authorizeTeacherManage($request->user());
        $existing = $this->diskusiService->cariBerdasarkanId($id);
        if (! $existing) {
            return response()->json(['success' => false, 'message' => 'Diskusi Kelas tidak ditemukan atau gagal diperbarui.'], 404);
        }
        $this->assertCanManageDiskusi($request->user(), $existing);
        $this->assertCanManageModul($request->user(), $request->validated('modul_ajar_id'));
        $diskusi = $this->diskusiService->ubah($id, $request->validated());

        if (! $diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diskusi Kelas berhasil diperbarui.',
            'data' => new LmsDiskusiResource($diskusi),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeTeacherManage(request()->user());
        $diskusi = $this->diskusiService->cariBerdasarkanId($id);
        if (! $diskusi) {
            return response()->json(['success' => false, 'message' => 'Diskusi Kelas tidak ditemukan atau gagal dihapus.'], 404);
        }
        $this->assertCanManageDiskusi(request()->user(), $diskusi);
        $deleted = $this->diskusiService->hapus($id);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diskusi Kelas berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $this->authorizeTeacherManage(request()->user());
        $diskusi = LmsDiskusi::withTrashed()->find($id);
        if (! $diskusi) {
            return response()->json(['success' => false, 'message' => 'Gagal memulihkan Diskusi Kelas.'], 400);
        }
        $this->assertCanManageDiskusi(request()->user(), $diskusi);
        $restored = $this->diskusiService->pulihkan($id);

        if (! $restored) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan Diskusi Kelas.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diskusi Kelas berhasil dipulihkan.',
        ]);
    }

    public function togglePin(string $id): JsonResponse
    {
        $this->authorizeTeacherManage(request()->user());
        $existing = $this->diskusiService->cariBerdasarkanId($id);
        if (! $existing) {
            return response()->json(['success' => false, 'message' => 'Diskusi Kelas tidak ditemukan.'], 404);
        }
        $this->assertCanManageDiskusi(request()->user(), $existing);
        $diskusi = $this->diskusiService->togglePin($id);

        if (! $diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $diskusi->is_pinned ? 'Diskusi berhasil disematkan (pinned).' : 'Sematkan diskusi dilepas.',
            'data' => new LmsDiskusiResource($diskusi),
        ]);
    }

    public function toggleClose(string $id): JsonResponse
    {
        $this->authorizeTeacherManage(request()->user());
        $existing = $this->diskusiService->cariBerdasarkanId($id);
        if (! $existing) {
            return response()->json(['success' => false, 'message' => 'Diskusi Kelas tidak ditemukan.'], 404);
        }
        $this->assertCanManageDiskusi(request()->user(), $existing);
        $diskusi = $this->diskusiService->toggleClose($id);

        if (! $diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $diskusi->is_closed ? 'Diskusi berhasil ditutup.' : 'Diskusi dibuka kembali.',
            'data' => new LmsDiskusiResource($diskusi),
        ]);
    }

    public function storeKomentar(LmsDiskusiKomentarRequest $request, string $id): JsonResponse
    {
        $this->authorizeView($request->user());
        $diskusi = $this->diskusiService->cariBerdasarkanId($id);

        if (! $diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan.',
            ], 404);
        }

        if ($diskusi->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi ini telah ditutup. Komentar baru tidak dapat ditambahkan.',
            ], 422);
        }

        $this->assertCanViewDiskusi($request->user(), $diskusi);
        $data = $request->validated();
        if (! empty($data['parent_id'])) {
            abort_unless(LmsDiskusiKomentar::query()->whereKey($data['parent_id'])->where('diskusi_id', $diskusi->id)->exists(), 422, 'Komentar induk tidak termasuk dalam diskusi ini.');
        }
        $data['peran_pengirim'] = $this->isStudent($request->user()) ? 'Siswa' : 'Guru';
        if (! $this->isTeacher($request->user()) && ! $this->canAccessAllUnits($request->user())) {
            $data['is_solution'] = false;
        }

        $komentar = $this->diskusiService->tambahKomentar($id, $data);

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil ditambahkan.',
            'data' => new LmsDiskusiKomentarResource($komentar->load(['user', 'creator'])),
        ], 201);
    }

    public function destroyKomentar(string $diskusiId, string $komentarId): JsonResponse
    {
        $this->authorizeView(request()->user());
        $diskusi = $this->diskusiService->cariBerdasarkanId($diskusiId);
        $komentar = LmsDiskusiKomentar::query()->whereKey($komentarId)->where('diskusi_id', $diskusiId)->first();
        if (! $diskusi || ! $komentar) {
            return response()->json(['success' => false, 'message' => 'Komentar tidak ditemukan atau gagal dihapus.'], 404);
        }
        $this->assertCanViewDiskusi(request()->user(), $diskusi);
        abort_unless(
            $komentar->user_id === request()->user()->id
            || $this->assertCanManageDiskusi(request()->user(), $diskusi),
            403
        );
        $deleted = $this->diskusiService->hapusKomentar($komentarId);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Komentar tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil dihapus.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $this->authorizeView(request()->user());
        $stats = $this->diskusiService->dapatkanStatistik();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $user = request()->user();
        $this->authorizeView($user);
        $modulOptions = $this->diskusiService->dapatkanOpsiModulAjar(
            $this->isTeacher($user) ? $this->teacherEmployeeId($user) : null
        );

        return response()->json([
            'success' => true,
            'data' => [
                'modul_ajar' => $modulOptions,
                'modul_ajar_options' => $modulOptions,
                'kategori' => [
                    'Umum',
                    'Tanya Jawab',
                    'Tugas',
                    'Materi',
                    'Proyek',
                    'Refleksi',
                ],
                'peran' => [
                    'Guru',
                    'Siswa',
                    'Admin',
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
        abort_unless($this->canAccessAllUnits($user) || $user->hasAnyPermission(['pembelajaran.kurikulum.view', 'pembelajaran.materi', 'teacher.material.view']) || $this->isTeacher($user) || $this->isStudent($user), 403);
    }

    private function authorizeTeacherManage(User $user): void
    {
        abort_unless($this->canAccessAllUnits($user) || $this->isTeacher($user) || $user->hasAnyPermission(['pembelajaran.materi']), 403);
    }

    private function canAccessAllUnits(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Yayasan', 'Ketua Yayasan', 'ketua_yayasan', 'sekretaris_yayasan', 'bendahara_yayasan', 'pengurus_yayasan']);
    }

    private function isTeacher(User $user): bool
    {
        return $user->hasAnyRole(['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran']);
    }

    private function isStudent(User $user): bool
    {
        return $user->hasAnyRole(['Siswa', 'siswa', 'student']);
    }

    private function teacherEmployeeId(User $user): string
    {
        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');
        abort_unless($employeeId, 403);

        return $employeeId;
    }

    private function studentClassIds(User $user): array
    {
        return Student::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get(['kelas_id', 'class_id'])
            ->flatMap(fn ($student) => [$student->kelas_id, $student->class_id])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function assertCanManageModul(User $user, ?string $modulId): void
    {
        if (! $this->isTeacher($user)) {
            return;
        }

        abort_unless($modulId && LmsModulAjar::query()->whereKey($modulId)->where('guru_id', $this->teacherEmployeeId($user))->exists(), 403);
    }

    private function assertCanViewDiskusi(User $user, LmsDiskusi $diskusi): void
    {
        if ($this->isTeacher($user)) {
            $this->assertCanManageModul($user, $diskusi->modul_ajar_id);
        }

        if ($this->isStudent($user)) {
            abort_unless(
                $diskusi->status === 'aktif'
                && ! $diskusi->is_closed
                && $diskusi->modulAjar
                && in_array($diskusi->modulAjar->kelas_id, $this->studentClassIds($user), true),
                403
            );
        }
    }

    private function assertCanManageDiskusi(User $user, LmsDiskusi $diskusi): bool
    {
        if ($this->canAccessAllUnits($user) || $user->hasAnyPermission(['pembelajaran.materi'])) {
            return true;
        }

        abort_unless($this->isTeacher($user), 403);
        $this->assertCanManageModul($user, $diskusi->modul_ajar_id);

        return true;
    }
}
