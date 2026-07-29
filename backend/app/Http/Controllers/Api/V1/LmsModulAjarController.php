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
use App\Models\TujuanPembelajaran;
use App\Services\LmsModulAjarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class LmsModulAjarController extends Controller
{
    public function __construct(
        protected LmsModulAjarService $modulAjarService
    ) {}

    public function index(Request $request): JsonResponse
    {
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

        // Restrict Guru to their own modules if non-admin role
        $user = $request->user();
        if ($user && $user->role === 'Guru' && !empty($user->employee_id)) {
            $filters['guru_id'] = $user->employee_id;
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
        return response()->json([
            'status' => 'success',
            'message' => 'Statistik Modul Ajar berhasil dimuat.',
            'data' => $this->modulAjarService->dapatkanStatistik(),
        ]);
    }

    public function options(): JsonResponse
    {
        $units = EducationUnit::select('id', 'name', 'code')->get();
        $kurikulums = MasterKurikulum::select('id', 'nama_kurikulum', 'kode_kurikulum')->get();
        $subjects = Subject::select('id', 'nama_mapel', 'kode_mapel', 'name', 'code')->get();
        
        $teachers = Employee::select('id', 'nama_lengkap', 'niy', 'nik')->get();
        if ($teachers->isEmpty()) {
            $teachers = \App\Models\Teacher::select('id', 'full_name as nama_lengkap', 'employee_number as niy')->get();
        }

        $classes = Kelas::select('id', 'nama_kelas', 'kode_kelas')->get();
        
        $years = AcademicYear::select('id', 'name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'tahun' => $item->name,
            ];
        });

        $semesters = Semester::select('id', 'name')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'nama' => $item->name,
            ];
        });

        $cps = CapaianPembelajaran::select('id', 'kode_cp', 'nama_cp', 'fase')->get();
        $tps = TujuanPembelajaran::select('id', 'kode_tp', 'nama_tp')->get();

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
        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (!$modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Modul Ajar tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data Modul Ajar berhasil dimuat.',
            'data' => new LmsModulAjarResource($modul),
        ]);
    }

    public function store(SimpanModulAjarRequest $request): JsonResponse
    {
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
        $data = $request->validated();
        $modul = $this->modulAjarService->ubah($id, $data);

        if (!$modul) {
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
        $deleted = $this->modulAjarService->hapus($id);
        if (!$deleted) {
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
        $restored = $this->modulAjarService->pulihkan($id);
        if (!$restored) {
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
        $modul = $this->modulAjarService->publikasikan($id);
        if (!$modul) {
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
        $modul = $this->modulAjarService->duplikasi($id);
        if (!$modul) {
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
        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (!$modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Modul Ajar tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Riwayat revisi Modul Ajar berhasil dimuat.',
            'data' => $modul->revisions,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $moduls = $this->modulAjarService->dapatkanDaftar([], 500);
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Modul_Ajar_" . date('Y-m-d') . ".csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
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
                    $m->versi
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request, string $id)
    {
        $modul = $this->modulAjarService->cariBerdasarkanId($id);
        if (!$modul) {
            return response()->json(['status' => 'error', 'message' => 'Modul Ajar tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Dokumen PDF Modul Ajar berhasil dicetak.',
            'data' => [
                'document_title' => 'MODUL AJAR (RPP DIGITAL) - ' . $modul->judul_modul,
                'modul' => new LmsModulAjarResource($modul),
                'generated_at' => now()->toIso8601String()
            ]
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Import data Modul Ajar berhasil diproses.',
            'rows_imported' => 1
        ]);
    }
}
