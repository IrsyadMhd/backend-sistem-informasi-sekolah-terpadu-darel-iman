<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanModulAjarRequest;
use App\Http\Requests\V1\UbahModulAjarRequest;
use App\Http\Resources\V1\LmsModulAjarResource;
use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\LmsModulAjarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsModulAjarController extends Controller
{
    public function __construct(
        protected LmsModulAjarService $modulAjarService,
        protected AccessScopeService $accessScope
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());

        $filters = [
            'search' => $request->query('search'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'semester_id' => $request->query('semester_id'),
            'kurikulum_id' => $request->query('kurikulum_id'),
            'mata_pelajaran_id' => $request->query('mata_pelajaran_id'),
            'guru_id' => $request->query('guru_id'),
            'kelas_id' => $request->query('kelas_id'),
            'fase' => $request->query('fase'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $user = $request->user();
        if ($this->isTeacher($user)) {
            $filters['guru_id'] = $this->teacherEmployeeId($user);
            $employee = Employee::query()->where('user_id', $user->id)->first();
            if ($employee && $employee->unit_id && empty($filters['unit_pendidikan_id'])) {
                $filters['unit_pendidikan_id'] = $employee->unit_id;
            }
        } elseif (! $this->canAccessAllUnits($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            if (empty($filters['unit_pendidikan_id'])) {
                $filters['unit_ids'] = $allowedUnitIds;
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $moduls = $this->modulAjarService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        $statsFilters = [];
        if ($this->isTeacher($user)) {
            $statsFilters['guru_id'] = $this->teacherEmployeeId($user);
            $employee = Employee::query()->where('user_id', $user->id)->first();
            if ($employee && $employee->unit_id) {
                $statsFilters['unit_pendidikan_id'] = $employee->unit_id;
            }
        } elseif (! empty($filters['unit_pendidikan_id'])) {
            $statsFilters['unit_pendidikan_id'] = $filters['unit_pendidikan_id'];
        } elseif (! empty($filters['unit_ids'])) {
            $statsFilters['unit_ids'] = $filters['unit_ids'];
        }
        if (! empty($filters['tahun_ajaran_id'])) {
            $statsFilters['tahun_ajaran_id'] = $filters['tahun_ajaran_id'];
        }
        if (! empty($filters['semester_id'])) {
            $statsFilters['semester_id'] = $filters['semester_id'];
        }
        if (! empty($filters['mata_pelajaran_id'])) {
            $statsFilters['mata_pelajaran_id'] = $filters['mata_pelajaran_id'];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data Modul Ajar berhasil dimuat.',
            'data' => LmsModulAjarResource::collection($moduls),
            'meta' => [
                'current_page' => $moduls->currentPage(),
                'from' => $moduls->firstItem(),
                'last_page' => $moduls->lastPage(),
                'per_page' => $moduls->perPage(),
                'to' => $moduls->lastItem(),
                'total' => $moduls->total(),
            ],
            'statistik' => $this->modulAjarService->dapatkanStatistik($statsFilters),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeView($user);

        $filters = [];
        if ($this->isTeacher($user)) {
            $filters['guru_id'] = $this->teacherEmployeeId($user);
            $employee = Employee::query()->where('user_id', $user->id)->first();
            if ($employee && $employee->unit_id) {
                $filters['unit_pendidikan_id'] = $employee->unit_id;
            }
        } elseif (! $this->canAccessAllUnits($user)) {
            $filters['unit_ids'] = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
        }

        if ($request->filled('unit_pendidikan_id')) {
            $filters['unit_pendidikan_id'] = $request->query('unit_pendidikan_id');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Statistik Modul Ajar berhasil dimuat.',
            'data' => $this->modulAjarService->dapatkanStatistik($filters),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());

        $learningContext = $request->only([
            'unit_pendidikan_id',
            'tahun_ajaran_id',
            'kurikulum_id',
            'mata_pelajaran_id',
        ]);

        $units = $this->accessScope->accessibleEducationUnits($request->user())
            ->select('id', 'name', 'code')
            ->get();
        $kurikulums = MasterKurikulum::query()
            ->select('id', 'nama_kurikulum', 'kode_kurikulum')
            ->where('status', true)
            ->when($learningContext['unit_pendidikan_id'] ?? null, fn ($query, $unitId) => $query->where('unit_pendidikan_id', $unitId))
            ->when($learningContext['tahun_ajaran_id'] ?? null, fn ($query, $yearId) => $query->where('tahun_ajaran_id', $yearId))
            ->get();
        $subjects = Subject::query()
            ->select('id', 'nama_mapel', 'kode_mapel', 'name', 'code')
            ->where('status', true)
            ->when($learningContext['unit_pendidikan_id'] ?? null, fn ($query, $unitId) => $query->where('unit_pendidikan_id', $unitId))
            ->when($learningContext['kurikulum_id'] ?? null, fn ($query, $curriculumId) => $query->where('kurikulum_id', $curriculumId))
            ->get();

        $teachers = Employee::query()
            ->select('id', 'nama_lengkap', 'niy', 'nik')
            ->when($learningContext['unit_pendidikan_id'] ?? null, fn ($query, $unitId) => $query->where('unit_id', $unitId))
            ->get();
        if ($teachers->isEmpty()) {
            $teachers = Teacher::select('id', 'full_name as nama_lengkap', 'employee_number as niy')->get();
        }

        $classes = Kelas::query()
            ->select('id', 'nama_kelas', 'kode_kelas')
            ->when($learningContext['unit_pendidikan_id'] ?? null, fn ($query, $unitId) => $query->where('unit_pendidikan_id', $unitId))
            ->when($learningContext['tahun_ajaran_id'] ?? null, fn ($query, $yearId) => $query->where('tahun_ajaran_id', $yearId))
            ->when($request->query('semester_id'), fn ($query, $semesterId) => $query->where('semester_id', $semesterId))
            ->get();

        if ($this->isTeacher($request->user())) {
            $employeeId = $this->teacherEmployeeId($request->user());
            $employee = Employee::query()->where('id', $employeeId)->first();
            if ($units->isEmpty() && $employee?->unit_id) {
                $units = EducationUnit::where('id', $employee->unit_id)->select('id', 'name', 'code')->get();
            }
            $teachers = Employee::query()
                ->select('id', 'nama_lengkap', 'niy', 'nik')
                ->where('id', $employeeId)
                ->get();
            $classes = $this->accessScope->accessibleRombels($request->user())
                ->select('id', 'nama_kelas', 'kode_kelas')
                ->get();
            $allowedMapelIds = $this->accessScope->accessibleSchedules($request->user())
                ->pluck('subject_id')
                ->filter()
                ->unique();
            if ($allowedMapelIds->isNotEmpty()) {
                $subjects = Subject::query()
                    ->select('id', 'nama_mapel', 'kode_mapel', 'name', 'code')
                    ->whereIn('id', $allowedMapelIds)
                    ->get();
            }
        }

        $years = AcademicYear::select('id', 'name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'tahun' => $item->name,
            ];
        });

        $semesters = Semester::query()
            ->select('id', 'name')
            ->when($learningContext['tahun_ajaran_id'] ?? null, fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
            ->get()
            ->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'nama' => $item->name,
            ];
        });

        $cps = CapaianPembelajaran::query()
            ->select('id', 'kode_cp', 'nama_cp', 'fase')
            ->filter($learningContext)
            ->where('status', true)
            ->get();
        $tps = TujuanPembelajaran::query()
            ->select('id', 'kode_tp', 'nama_tp')
            ->where('status', true)
            ->whereHas('capaianPembelajaran', function ($query) use ($learningContext) {
                $query->filter($learningContext)->where('status', true);
            })
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'education_units' => $units,
                'kurikulums' => $kurikulums,
                'subjects' => $subjects,
                'teachers' => $teachers,
                'classes' => $classes,
                'academic_years' => $years,
                'semesters' => $semesters,
                'capaian_pembelajaran' => $cps,
                'tujuan_pembelajaran' => $tps,
                'fases' => ['Fase A', 'Fase B', 'Fase C', 'Fase D', 'Fase E', 'Fase F'],
                'statuses' => ['Draft', 'Review', 'Publish', 'Arsip'],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeView(request()->user());

        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Modul Ajar tidak ditemukan.',
            ], 404);
        }

        $this->assertCanViewModule(request()->user(), $modul);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data Modul Ajar berhasil dimuat.',
            'data' => new LmsModulAjarResource($modul),
        ]);
    }

    public function store(SimpanModulAjarRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManage($user, 'create');
        $data = $request->validated();

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            if (! empty($request->input('guru_id')) && $request->input('guru_id') !== $employeeId) {
                abort(403, 'Akses ditolak: Anda tidak dapat membuat Modul Ajar untuk guru lain.');
            }
            $data['guru_id'] = $employeeId;

            $employee = Employee::query()->where('id', $employeeId)->first();
            if ($employee && $employee->unit_id) {
                $data['unit_pendidikan_id'] = $employee->unit_id;
            }

            $this->assertTeacherAssignment($user, $data['kelas_id'] ?? null, $data['mata_pelajaran_id'] ?? null, $data['unit_pendidikan_id'] ?? null);
        }

        $modul = $this->modulAjarService->simpan($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Ajar berhasil dibuat.',
            'data' => new LmsModulAjarResource($modul),
        ], 201);
    }

    public function update(UbahModulAjarRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->authorizeManage($user, 'edit');

        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Modul Ajar tidak ditemukan.',
            ], 404);
        }

        $data = $request->validated();

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            abort_unless($modul->guru_id === $employeeId, 403, 'Akses ditolak: Modul Ajar milik guru lain.');
            if (! empty($request->input('guru_id')) && $request->input('guru_id') !== $employeeId) {
                abort(403, 'Akses ditolak: Anda tidak dapat mengubah kepemilikan guru Modul Ajar.');
            }
            $data['guru_id'] = $employeeId;
            $kelasId = $data['kelas_id'] ?? $modul->kelas_id;
            $mapelId = $data['mata_pelajaran_id'] ?? $modul->mata_pelajaran_id;
            $this->assertTeacherAssignment($user, $kelasId, $mapelId);
        }

        $modul = $this->modulAjarService->ubah($id, $data);

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Ajar berhasil diperbarui.',
            'data' => new LmsModulAjarResource($modul),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = request()->user();
        $this->authorizeManage($user, 'delete');

        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Modul Ajar tidak ditemukan.',
            ], 404);
        }

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            abort_unless($modul->guru_id === $employeeId, 403, 'Akses ditolak: Modul Ajar milik guru lain.');
        }

        $deleted = $this->modulAjarService->hapus($id);
        if (! $deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Modul Ajar gagal dihapus atau tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Ajar berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $user = request()->user();
        $this->authorizeManage($user, 'restore');

        $modul = $this->modulAjarService->cariBerdasarkanIdDenganSampah($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan Modul Ajar atau data tidak ditemukan.',
            ], 404);
        }

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            abort_unless($modul->guru_id === $employeeId, 403, 'Akses ditolak: Modul Ajar milik guru lain.');
        }

        $restored = $this->modulAjarService->pulihkan($id);
        if (! $restored) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan Modul Ajar atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Ajar berhasil dipulihkan.',
        ]);
    }

    public function publish(string $id): JsonResponse
    {
        $user = request()->user();
        $this->authorizeManage($user, 'edit');

        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mempublikasikan Modul Ajar.',
            ], 404);
        }

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            abort_unless($modul->guru_id === $employeeId, 403, 'Akses ditolak: Modul Ajar milik guru lain.');
        }

        $modul = $this->modulAjarService->publikasikan($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mempublikasikan Modul Ajar.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Ajar berhasil dipublikasikan.',
            'data' => new LmsModulAjarResource($modul),
        ]);
    }

    public function duplicate(string $id): JsonResponse
    {
        $user = request()->user();
        $this->authorizeManage($user, 'create');

        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menduplikasi Modul Ajar.',
            ], 404);
        }

        if ($this->isTeacher($user)) {
            $employeeId = $this->teacherEmployeeId($user);
            abort_unless($modul->guru_id === $employeeId, 403, 'Akses ditolak: Modul Ajar milik guru lain.');
        }

        $modul = $this->modulAjarService->duplikasi($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menduplikasi Modul Ajar.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Ajar berhasil diduplikasi.',
            'data' => new LmsModulAjarResource($modul),
        ], 201);
    }

    public function revisions(string $id): JsonResponse
    {
        $this->authorizeView(request()->user());
        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Modul Ajar tidak ditemukan.',
            ], 404);
        }

        $this->assertCanViewModule(request()->user(), $modul);

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat revisi Modul Ajar berhasil dimuat.',
            'data' => $modul->revisions,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeView($request->user());
        $filters = $this->isTeacher($request->user())
            ? ['guru_id' => $this->teacherEmployeeId($request->user())]
            : [];
        $moduls = $this->modulAjarService->dapatkanDaftar($filters, 500);
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=Modul_Ajar_'.date('Y-m-d').'.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['Kode Modul', 'Judul Modul', 'Mata Pelajaran', 'Guru Pengampu', 'Kelas', 'Fase', 'Status', 'Versi'];

        $callback = function () use ($moduls, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($moduls as $m) {
                fputcsv($file, [
                    $m->kode_modul,
                    $m->judul_modul,
                    $m->subject->nama_mapel ?? $m->subject->name ?? '',
                    $m->guru->nama_lengkap ?? $m->guru->name ?? '',
                    $m->kelas->nama_kelas ?? $m->kelas->name ?? '',
                    $m->fase,
                    $m->status,
                    $m->versi,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request, string $id)
    {
        $this->authorizeView($request->user());
        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (! $modul) {
            return response()->json(['status' => 'error', 'message' => 'Modul Ajar tidak ditemukan.'], 404);
        }

        $this->assertCanViewModule($request->user(), $modul);

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen PDF Modul Ajar berhasil dicetak.',
            'data' => [
                'document_title' => 'MODUL AJAR (RPP DIGITAL) - '.$modul->judul_modul,
                'modul' => new LmsModulAjarResource($modul),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorizeManage($request->user(), 'import');

        return response()->json([
            'status' => 'error',
            'message' => 'Import data Modul Ajar belum tersedia.',
        ], 501);
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
        if ($this->canAccessAllUnits($user) || $user->hasAnyPermission(["pembelajaran.kurikulum.{$action}"])) {
            return;
        }

        if ($this->isTeacher($user)) {
            $teacherPermMap = [
                'create' => 'teacher.material.create',
                'edit' => 'teacher.material.update',
                'delete' => 'teacher.material.delete',
                'restore' => 'teacher.material.update',
                'force_delete' => 'teacher.material.delete',
            ];
            $perm = $teacherPermMap[$action] ?? null;
            if ($user->hasRole(['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran']) || ($perm && $user->hasPermissionTo($perm))) {
                return;
            }
        }

        abort(403, 'Akses ditolak: Anda tidak memiliki izin untuk mengelola Modul Ajar.');
    }

    private function assertTeacherAssignment(User $user, ?string $kelasId, ?string $mataPelajaranId, ?string $unitId = null): void
    {
        if ($this->canAccessAllUnits($user)) {
            return;
        }

        if ($unitId) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            if (! empty($allowedUnitIds)) {
                abort_unless(in_array($unitId, $allowedUnitIds, true), 403, 'Akses ditolak: Unit pendidikan di luar cakupan akses Anda.');
            }
        }

        if ($kelasId) {
            $allowedKelasIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            abort_unless(in_array($kelasId, $allowedKelasIds, true), 403, 'Akses ditolak: Rombel di luar penugasan mengajar Anda.');
        }

        if ($mataPelajaranId) {
            $allowedMapelIds = $this->accessScope->accessibleSchedules($user)->pluck('subject_id')->filter()->unique()->all();
            if (! empty($allowedMapelIds)) {
                abort_unless(in_array($mataPelajaranId, $allowedMapelIds, true), 403, 'Akses ditolak: Mata pelajaran di luar penugasan mengajar Anda.');
            }
        }
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

    private function assertCanViewModule(User $user, $modul): void
    {
        if ($this->isTeacher($user)) {
            abort_unless($modul->guru_id === $this->teacherEmployeeId($user), 403);
        }
    }
}
