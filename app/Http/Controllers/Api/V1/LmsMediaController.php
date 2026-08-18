<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanMediaRequest;
use App\Http\Requests\V1\UbahMediaRequest;
use App\Http\Resources\V1\LmsMediaResource;
use App\Models\Employee;
use App\Models\LmsMateri;
use App\Models\LmsMedia;
use App\Models\User;
use App\Services\LmsMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsMediaController extends Controller
{
    public function __construct(
        protected LmsMediaService $mediaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());

        $filters = [
            'search' => $request->query('search'),
            'materi_id' => $request->query('materi_id'),
            'tipe_file' => $request->query('tipe_file'),
        ];
        if ($this->isTeacher($request->user())) {
            $filters['guru_id'] = $this->teacherEmployeeId($request->user());
        }

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'urutan');
        $orderDir = (string) $request->query('order_dir', 'asc');

        $medias = $this->mediaService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Media Pembelajaran berhasil dimuat.',
            'data' => LmsMediaResource::collection($medias),
            'meta' => [
                'current_page' => $medias->currentPage(),
                'from' => $medias->firstItem(),
                'last_page' => $medias->lastPage(),
                'per_page' => $medias->perPage(),
                'to' => $medias->lastItem(),
                'total' => $medias->total(),
            ],
            'statistik' => $this->mediaService->statistik(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeView(request()->user());

        $media = $this->mediaService->cariBerdasarkanId($id);

        if (! $media) {
            return response()->json([
                'status' => 'error',
                'message' => 'Media Pembelajaran tidak ditemukan.',
            ], 404);
        }

        $this->assertCanManageMedia(request()->user(), $media);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Media Pembelajaran berhasil dimuat.',
            'data' => new LmsMediaResource($media),
        ]);
    }

    public function store(SimpanMediaRequest $request): JsonResponse
    {
        $this->authorizeManage($request->user(), 'create');
        $data = $request->validated();
        $this->assertCanManageMateri($request->user(), $data['materi_id']);
        $file = $request->file('file');

        $media = $this->mediaService->simpan($data, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Media Pembelajaran berhasil disimpan.',
            'data' => new LmsMediaResource($media),
        ], 201);
    }

    public function update(UbahMediaRequest $request, string $id): JsonResponse
    {
        $this->authorizeManage($request->user(), 'update');
        $existing = $this->mediaService->cariBerdasarkanId($id);
        if (! $existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Media Pembelajaran tidak ditemukan.',
            ], 404);
        }
        $this->assertCanManageMedia($request->user(), $existing);
        $data = $request->validated();
        if (! empty($data['materi_id'])) {
            $this->assertCanManageMateri($request->user(), $data['materi_id']);
        }
        $file = $request->file('file');

        $media = $this->mediaService->ubah($id, $data, $file);

        if (! $media) {
            return response()->json([
                'status' => 'error',
                'message' => 'Media Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Media Pembelajaran berhasil diperbarui.',
            'data' => new LmsMediaResource($media),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeManage(request()->user(), 'delete');
        $media = $this->mediaService->cariBerdasarkanId($id);
        if (! $media) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus Media Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }
        $this->assertCanManageMedia(request()->user(), $media);
        $berhasil = $this->mediaService->hapus($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus Media Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Media Pembelajaran berhasil dihapus.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorizeManage($request->user(), 'update');
        $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'uuid'],
            'orders.*.urutan' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($request->input('orders') as $item) {
            $media = $this->mediaService->cariBerdasarkanId($item['id']);
            abort_unless($media, 404, 'Media Pembelajaran tidak ditemukan.');
            $this->assertCanManageMedia($request->user(), $media);
        }

        $this->mediaService->reorder($request->input('orders'));

        return response()->json([
            'status' => 'success',
            'message' => 'Urutan Media Pembelajaran berhasil diperbarui.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $this->authorizeView(request()->user());

        return response()->json([
            'status' => 'success',
            'data' => $this->mediaService->statistik(),
        ]);
    }

    public function options(): JsonResponse
    {
        $this->authorizeView(request()->user());

        return response()->json([
            'status' => 'success',
            'data' => $this->mediaService->opsi(),
        ]);
    }

    private function authorizeView(User $user): void
    {
        abort_unless(
            $this->canAccessAllUnits($user)
            || $user->hasAnyPermission([
                'pembelajaran.kurikulum.view',
                'pembelajaran.materi',
                'teacher.material.view',
            ])
            || $user->hasAnyRole([
                'Guru',
                'guru',
                'Guru Mata Pelajaran',
                'guru_mata_pelajaran',
                'Siswa',
                'siswa',
                'student',
                'Orang Tua',
                'orang_tua',
                'parent',
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
            'Yayasan',
            'Ketua Yayasan',
            'ketua_yayasan',
            'sekretaris_yayasan',
            'bendahara_yayasan',
            'pengurus_yayasan',
        ]);
    }

    private function isTeacher(User $user): bool
    {
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

    private function assertCanManageMateri(User $user, string $materiId): void
    {
        if (! $this->isTeacher($user)) {
            return;
        }

        $isOwner = LmsMateri::query()
            ->whereKey($materiId)
            ->where('guru_id', $this->teacherEmployeeId($user))
            ->exists();

        abort_unless($isOwner, 403);
    }

    private function assertCanManageMedia(User $user, LmsMedia $media): void
    {
        $this->assertCanManageMateri($user, $media->materi_id);
    }
}
