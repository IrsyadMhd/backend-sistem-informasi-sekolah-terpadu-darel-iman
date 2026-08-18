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
use App\Services\LmsModulAjarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsModulAjarController extends Controller
{
    public function __construct(
        protected LmsModulAjarService $modulAjarService
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
        }

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $moduls = $this->modulAjarService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

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
            'statistik' => $this->modulAjarService->dapatkanStatistik(),
        ]);
    }

    public function stats(): JsonResponse
    {
        $this->authorizeView(request()->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Statistik Modul Ajar berhasil dimuat.',
            'data' => $this->modulAjarService->dapatkanStatistik(),
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

        $units = EducationUnit::select('id', 'name', 'code')->get();
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
        $this->authorizeManage($request->user(), 'create');
        $data = $request->validated();
        $modul = $this->modulAjarService->simpan($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Ajar berhasil dibuat.',
            'data' => new LmsModulAjarResource($modul),
        ], 201);
    }

    public function update(UbahModulAjarRequest $request, string $id): JsonResponse
    {
        $this->authorizeManage($request->user(), 'edit');
        $data = $request->validated();
        $modul = $this->modulAjarService->ubah($id, $data);

        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Modul Ajar tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Ajar berhasil diperbarui.',
            'data' => new LmsModulAjarResource($modul),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeManage(request()->user(), 'delete');
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
        $this->authorizeManage(request()->user(), 'restore');
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
        $this->authorizeManage(request()->user(), 'edit');
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
        $this->authorizeManage(request()->user(), 'create');
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
                'pembelajaran.kurikulum.view',
                'pembelajaran.materi',
                'teacher.material.view',
            ])
            || $user->hasAnyRole([
                'Guru',
                'guru',
                'Guru Mata Pelajaran',
                'guru_mata_pelajaran',
            ]),
            403
        );
    }

    private function authorizeManage(User $user, string $action): void
    {
        abort_unless(
            $this->canAccessAllUnits($user)
            || $user->hasAnyPermission(["pembelajaran.kurikulum.{$action}"]),
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

    private function assertCanViewModule(User $user, $modul): void
    {
        if ($this->isTeacher($user)) {
            abort_unless($modul->guru_id === $this->teacherEmployeeId($user), 403);
        }
    }
}
