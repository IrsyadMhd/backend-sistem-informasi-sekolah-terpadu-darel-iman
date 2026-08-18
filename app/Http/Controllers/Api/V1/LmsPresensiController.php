<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsPresensiBulkRequest;
use App\Http\Requests\V1\LmsPresensiRequest;
use App\Http\Resources\V1\LmsPresensiResource;
use App\Services\AttendanceAccessService;
use App\Services\LmsPresensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsPresensiController extends Controller
{
    public function __construct(
        protected LmsPresensiService $service,
        protected AttendanceAccessService $access
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'jadwal_pelajaran_id',
            'siswa_id',
            'status_hadir',
            'tanggal',
            'tanggal_mulai',
            'tanggal_selesai',
            'kelas_id',
            'subject_id',
        ]);
        $user = $request->user();
        if ($user->hasRole('Siswa')) {
            $filters['siswa_id'] = $this->access->student($user)?->id ?? '__none__';
        } elseif ($user->hasRole('Wali Kelas') && ! $user->hasRole('Super Admin')) {
            $filters['class_ids'] = $this->access->homeroomClasses($user)->pluck('id')->all();
        } elseif ($user->hasRole('Guru') && ! $user->hasRole('Super Admin')) {
            $filters['employee_id'] = $this->access->employee($user)?->id ?? '__none__';
        }
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'tanggal');
        $orderDir = $request->get('order_dir', 'desc');

        $data = $this->service->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsPresensiResource::collection($data);
    }

    public function store(LmsPresensiRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['lesson_attendance.create']) || $request->user()->hasAnyRole(['Guru', 'Super Admin']), 403);
        $schedule = $this->access->assertTeacherOwnsSchedule($request->user(), $request->validated('jadwal_pelajaran_id'));
        $this->access->assertStudentInSchedule($schedule, $request->validated('siswa_id'));
        $presensi = $this->service->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Presensi pembelajaran berhasil disimpan.',
            'data' => new LmsPresensiResource($presensi->load(['jadwalPelajaran.subject', 'jadwalPelajaran.kelas', 'siswa'])),
        ], 201);
    }

    public function bulkStore(LmsPresensiBulkRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['lesson_attendance.create']) || $request->user()->hasAnyRole(['Guru', 'Super Admin']), 403);
        $validated = $request->validated();
        $jadwalPelajaranId = $validated['jadwal_pelajaran_id'];
        $tanggal = $validated['tanggal'];
        $pertemuanKe = $validated['pertemuan_ke'] ?? 1;
        $items = $validated['items'];
        $schedule = $this->access->assertTeacherOwnsSchedule($request->user(), $jadwalPelajaranId);
        foreach ($items as $item) {
            $this->access->assertStudentInSchedule($schedule, $item['siswa_id']);
        }

        $results = $this->service->simpanBulk($jadwalPelajaranId, $tanggal, $pertemuanKe, $items);

        return response()->json([
            'success' => true,
            'message' => sprintf('Berhasil mencatat presensi untuk %d siswa.', $results->count()),
            'data' => LmsPresensiResource::collection($results),
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $presensi = $this->service->cariBerdasarkanId($id);

        if (! $presensi) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak ditemukan.',
            ], 404);
        }
        abort_unless($this->access->canAccessAttendance(request()->user(), $presensi), 403);

        return response()->json([
            'success' => true,
            'data' => new LmsPresensiResource($presensi),
        ]);
    }

    public function update(LmsPresensiRequest $request, string $id): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['lesson_attendance.update']) || $request->user()->hasAnyRole(['Guru', 'Super Admin']), 403);
        $existing = $this->service->cariBerdasarkanId($id);
        abort_unless($existing && $this->access->canAccessAttendance($request->user(), $existing), 403);
        abort_if(in_array($existing->session?->status, ['final', 'locked']), 422, 'Presensi final atau terkunci hanya dapat diubah melalui koreksi.');
        $presensi = $this->service->ubah($id, $request->validated());

        if (! $presensi) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil diperbarui.',
            'data' => new LmsPresensiResource($presensi),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        abort_unless(request()->user()->hasAnyPermission(['lesson_attendance.update']) || request()->user()->hasAnyRole(['Guru', 'Super Admin']), 403);
        $existing = $this->service->cariBerdasarkanId($id);
        abort_unless($existing && $this->access->canAccessAttendance(request()->user(), $existing), 403);
        abort_if(in_array($existing->session?->status, ['final', 'locked']), 422, 'Presensi final atau terkunci tidak dapat dibatalkan langsung.');
        $deleted = $this->service->hapus($id);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        abort_unless(request()->user()->hasAnyPermission(['lesson_attendance.unlock']) || request()->user()->hasRole('Super Admin'), 403);
        $restored = $this->service->pulihkan($id);

        if (! $restored) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan data presensi.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil dipulihkan.',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['Guru', 'Super Admin', 'Wali Kelas', 'Waka Kurikulum', 'Kepala Sekolah', 'Ketua Yayasan']) || $user->hasAnyPermission(['lesson_attendance.view', 'lesson_attendance.create']), 403);
        $filters = $request->only(['jadwal_pelajaran_id', 'tanggal']);
        if ($user->hasRole('Siswa')) {
            $filters['siswa_id'] = $this->access->student($user)?->id ?? '__none__';
        } elseif ($user->hasRole('Wali Kelas') && ! $user->hasRole('Super Admin')) {
            $filters['class_ids'] = $this->access->homeroomClasses($user)->pluck('id')->all();
        } elseif ($user->hasRole('Guru') && ! $user->hasRole('Super Admin')) {
            if (! empty($filters['jadwal_pelajaran_id'])) {
                abort_unless(
                    $this->access->teacherSchedules($user)->whereKey($filters['jadwal_pelajaran_id'])->exists(),
                    403
                );
            } else {
                $filters['employee_id'] = $this->access->employee($user)?->id ?? '__none__';
            }
        }
        $stats = $this->service->dapatkanStatistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['Super Admin', 'Guru', 'Wali Kelas', 'Waka Kurikulum', 'Kepala Sekolah', 'Ketua Yayasan']) || $user->hasAnyPermission(['lesson_attendance.view', 'lesson_attendance.create']), 403);
        $employeeId = null;
        if ($user->hasRole('Guru') && ! $user->hasRole('Super Admin')) {
            $employeeId = $this->access->employee($user)?->id;
        }
        $options = $this->service->dapatkanOpsi($employeeId);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
