<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsPengumpulanTugasRequest;
use App\Http\Requests\V1\LmsPenugasanRequest;
use App\Http\Resources\V1\LmsPengumpulanTugasResource;
use App\Http\Resources\V1\LmsPenugasanResource;
use App\Models\LmsPenugasan;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\LmsPenugasanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsPenugasanController extends Controller
{
    public function __construct(
        protected LmsPenugasanService $penugasanService,
        protected AccessScopeService $accessScope
    ) {}

    private function isSupervisorOrStaff(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->accessScope->hasGlobalScope($user)
            || $this->accessScope->isSupervisor($user)
            || $user->hasAnyRole([
                'Super Admin', 'super_admin', 'super-admin',
                'Admin', 'admin',
                'Operator', 'operator',
                'Tata Usaha', 'tata_usaha', 'TU', 'tu',
                'Kepala Sekolah', 'kepala_sekolah', 'kepala-sekolah', 'Kepsek', 'kepsek',
                'Yayasan', 'Ketua Yayasan', 'ketua_yayasan', 'Pengurus Yayasan', 'pengurus_yayasan',
                'Sekretaris Yayasan', 'sekretaris_yayasan', 'Bendahara Yayasan', 'bendahara_yayasan',
                'Waka Kurikulum', 'waka_kurikulum', 'Waka Kesiswaan', 'waka_kesiswaan',
                'Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Wakil Kesiswaan',
                'Divisi Pendidikan', 'divisi_pendidikan', 'Kepala Bidang Pendidikan',
            ]);
    }

    private function isTeacherOnly(?User $user): bool
    {
        if (! $user || $this->isSupervisorOrStaff($user)) {
            return false;
        }

        return $this->accessScope->isTeacher($user) || $user->hasAnyRole([
            'Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran',
            'Wali Kelas', 'wali_kelas', 'wali-kelas',
            'Musyrif', 'musyrif', 'Musyrifah', 'musyrifah',
            'Pembimbing', 'pembimbing',
        ]);
    }

    private function getTeacherOwnerIds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $employee = \App\Models\Employee::where('user_id', $user->id)->first();
        $teacher = \App\Models\Teacher::where('user_id', $user->id)
            ->when($employee, fn ($q) => $q->orWhere('employee_id', $employee->id))
            ->first();

        return array_values(array_unique(array_filter([
            $teacher?->id,
            $teacher?->employee_id,
            $employee?->id,
            $user->id,
        ])));
    }

    private function assertCanAccessPenugasan(?User $user, LmsPenugasan $penugasan): void
    {
        if (! $user || $this->accessScope->hasGlobalScope($user)) {
            return;
        }

        if ($this->isTeacherOnly($user)) {
            $ownerIds = $this->getTeacherOwnerIds($user);
            abort_unless(
                in_array($penugasan->guru_id, $ownerIds, true)
                || in_array($penugasan->teacher_id, $ownerIds, true)
                || $penugasan->created_by === $user->id,
                403,
                'Akses ditolak: Anda hanya dapat mengakses penugasan milik Anda sendiri.'
            );
            return;
        }

        $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
        $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

        $match = false;
        if ($penugasan->kelas_id && in_array($penugasan->kelas_id, $accessibleRombelIds, true)) {
            $match = true;
        } elseif ($penugasan->kelas && in_array($penugasan->kelas->unit_pendidikan_id, $allowedUnitIds, true)) {
            $match = true;
        } elseif ($penugasan->modulAjar && in_array($penugasan->modulAjar->unit_pendidikan_id, $allowedUnitIds, true)) {
            $match = true;
        } elseif ($penugasan->subject && in_array($penugasan->subject->unit_pendidikan_id, $allowedUnitIds, true)) {
            $match = true;
        }

        abort_unless($match, 403, 'Akses ditolak: Penugasan berada di luar unit wewenang Anda.');
    }

    private function assertCanManagePenugasan(?User $user, LmsPenugasan $penugasan): void
    {
        if (! $user || $this->isSupervisorOrStaff($user)) {
            return;
        }

        if ($this->isTeacherOnly($user)) {
            $ownerIds = $this->getTeacherOwnerIds($user);

            abort_unless(
                in_array($penugasan->guru_id, $ownerIds, true)
                || in_array($penugasan->teacher_id, $ownerIds, true)
                || $penugasan->created_by === $user->id,
                403,
                'Akses ditolak: Anda hanya dapat mengelola penugasan milik Anda sendiri.'
            );
        }
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->only(['search', 'modul_ajar_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'tipe', 'status', 'is_published', 'unit_pendidikan_id']);

        if ($this->isTeacherOnly($user)) {
            $ownerIds = $this->getTeacherOwnerIds($user);
            $employeeId = \App\Models\Employee::where('user_id', $user->id)->value('id');
            $filters['guru_id'] = $employeeId ?: ($ownerIds[0] ?? $user->id);
            $filters['teacher_owner_ids'] = $ownerIds;
            $filters['created_by'] = $user->id;
        } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

            if (! empty($filters['unit_pendidikan_id'])) {
                abort_unless(
                    in_array($filters['unit_pendidikan_id'], $allowedUnitIds, false) ||
                    in_array((int) $filters['unit_pendidikan_id'], array_map('intval', $allowedUnitIds), true),
                    403,
                    'Akses ditolak: Unit pendidikan di luar cakupan wewenang Anda.'
                );
            } else {
                $filters['unit_ids'] = $allowedUnitIds;
            }

            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, false), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $data = $this->penugasanService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsPenugasanResource::collection($data);
    }

    public function store(LmsPenugasanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($this->isTeacherOnly($user)) {
            $ownerIds = $this->getTeacherOwnerIds($user);
            $employeeId = \App\Models\Employee::where('user_id', $user->id)->value('id');
            if (! empty($validated['guru_id'])) {
                abort_unless(in_array($validated['guru_id'], $ownerIds, true), 403, 'Akses ditolak: Anda tidak dapat membuat tugas atas nama guru lain.');
            } else {
                $validated['guru_id'] = $employeeId ?: ($ownerIds[0] ?? $user->id);
            }
            $validated['created_by'] = $user->id;
        } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            if (! empty($validated['kelas_id'])) {
                abort_unless(in_array($validated['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            }
        }

        $penugasan = $this->penugasanService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dibuat.',
            'data' => new LmsPenugasanResource($penugasan->load(['modulAjar', 'guru', 'kelas', 'subject', 'semester', 'tahunAjaran', 'creator', 'pengumpulan'])),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);

        return response()->json([
            'success' => true,
            'data' => new LmsPenugasanResource($penugasan),
        ]);
    }

    public function update(LmsPenugasanRequest $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);
        $this->assertCanManagePenugasan($request->user(), $penugasan);
        $validated = $request->validated();

        if ($request->user() && ! $this->accessScope->hasGlobalScope($request->user()) && ! empty($validated['kelas_id'])) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($request->user())->pluck('id')->all();
            abort_unless(in_array($validated['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel tujuan di luar cakupan unit Anda.');
        }

        $updated = $this->penugasanService->ubah($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil diperbarui.',
            'data' => new LmsPenugasanResource($updated),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);
        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);
        $this->assertCanManagePenugasan($request->user(), $penugasan);

        $this->penugasanService->hapus($id);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dihapus.',
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);
        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);
        $this->assertCanManagePenugasan($request->user(), $penugasan);

        $restored = $this->penugasanService->pulihkan($id);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dipulihkan.',
        ]);
    }

    public function togglePublish(Request $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);
        $this->assertCanManagePenugasan($request->user(), $penugasan);

        $updated = $this->penugasanService->togglePublish($id);

        return response()->json([
            'success' => true,
            'message' => $updated->is_published ? 'Penugasan berhasil dipublikasikan.' : 'Penugasan dikembalikan ke status Draft.',
            'data' => new LmsPenugasanResource($updated),
        ]);
    }

    public function gradeSubmission(LmsPengumpulanTugasRequest $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);

        $pengumpulan = $this->penugasanService->submitOrGrade($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pengumpulan/penilaian tugas berhasil disimpan.',
            'data' => new LmsPengumpulanTugasResource($pengumpulan),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only([
            'unit_pendidikan_id',
            'kelas_id',
            'modul_ajar_id',
            'guru_id',
            'mata_pelajaran_id',
            'tipe',
        ]);

        if ($this->isTeacherOnly($user)) {
            $ownerIds = $this->getTeacherOwnerIds($user);
            $employeeId = \App\Models\Employee::where('user_id', $user->id)->value('id');
            $filters['guru_id'] = $employeeId ?: ($ownerIds[0] ?? $user->id);
            $filters['teacher_owner_ids'] = $ownerIds;
            $filters['created_by'] = $user->id;
        } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

            if (! empty($filters['unit_pendidikan_id'])) {
                abort_unless(
                    in_array($filters['unit_pendidikan_id'], $allowedUnitIds, false) ||
                    in_array((int) $filters['unit_pendidikan_id'], array_map('intval', $allowedUnitIds), true),
                    403,
                    'Akses ditolak: Unit pendidikan di luar cakupan wewenang Anda.'
                );
            } else {
                $filters['unit_ids'] = $allowedUnitIds;
            }

            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, false), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }
        }

        $stats = $this->penugasanService->dapatkanStatistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $options = $this->penugasanService->dapatkanOpsi($request->user());

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
