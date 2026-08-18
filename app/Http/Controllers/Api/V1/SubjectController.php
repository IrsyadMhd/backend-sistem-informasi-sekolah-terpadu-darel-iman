<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanSubjectRequest;
use App\Http\Requests\V1\UbahSubjectRequest;
use App\Http\Resources\V1\SubjectResource;
use App\Exports\SubjectExport;
use App\Services\SubjectService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Class SubjectController
 * Controller REST API untuk manajemen Master Data Mata Pelajaran (Subject).
 */
class SubjectController extends Controller
{
    public function __construct(
        protected SubjectService $subjectService
    ) {}

    /**
     * Dapatkan daftar mata pelajaran terpaginasi.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id'),
            'kurikulum_id' => $request->query('kurikulum_id'),
            'kelompok_mapel' => $request->query('kelompok_mapel'),
            'kategori' => $request->query('kategori'),
            'jenjang' => $request->query('jenjang'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $subjects = $this->subjectService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data mata pelajaran berhasil dimuat.',
            'data' => SubjectResource::collection($subjects),
            'meta' => [
                'current_page' => $subjects->currentPage(),
                'from' => $subjects->firstItem(),
                'last_page' => $subjects->lastPage(),
                'per_page' => $subjects->perPage(),
                'to' => $subjects->lastItem(),
                'total' => $subjects->total(),
            ],
            'statistik' => $this->subjectService->dapatkanStatistik(),
        ]);
    }

    /**
     * Dapatkan opsi dropdown master mata pelajaran.
     */
    public function dropdown(Request $request): JsonResponse
    {
        $options = $this->subjectService->dapatkanOpsiDropdown($request->only([
            'search', 'unit_pendidikan_id', 'kurikulum_id',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar opsi mata pelajaran berhasil dimuat.',
            'data' => $options,
        ]);
    }

    /**
     * Dapatkan statistik data mata pelajaran.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->subjectService->dapatkanStatistik();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Simpan data mata pelajaran baru.
     */
    public function store(SimpanSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjectService->simpan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data mata pelajaran berhasil ditambahkan.',
            'data' => new SubjectResource($subject),
        ], Response::HTTP_CREATED);
    }

    /**
     * Tampilkan detail data mata pelajaran berdasarkan ID.
     */
    public function show(string $id): JsonResponse
    {
        $subject = $this->subjectService->cariBerdasarkanId($id);

        if (! $subject) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mata pelajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data mata pelajaran berhasil ditemukan.',
            'data' => new SubjectResource($subject),
        ]);
    }

    /**
     * Perbarui data mata pelajaran.
     */
    public function update(UbahSubjectRequest $request, string $id): JsonResponse
    {
        $subject = $this->subjectService->ubah($id, $request->validated());

        if (! $subject) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mata pelajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data mata pelajaran berhasil diperbarui.',
            'data' => new SubjectResource($subject),
        ]);
    }

    /**
     * Hapus data mata pelajaran (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $berhasil = $this->subjectService->hapus($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus data mata pelajaran.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data mata pelajaran berhasil dihapus.',
        ]);
    }

    /**
     * Pulihkan data mata pelajaran yang telah terhapus.
     */
    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->subjectService->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data mata pelajaran.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data mata pelajaran berhasil dipulihkan.',
        ]);
    }

    /**
     * Ubah status mata pelajaran secara massal (Bulk Status).
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
            'status' => ['required', 'boolean'],
        ]);

        $jumlah = $this->subjectService->ubahStatusMassal($request->input('ids'), $request->input('status'));

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil memperbarui status {$jumlah} mata pelajaran.",
            'affected_rows' => $jumlah,
        ]);
    }

    /**
     * Hapus data mata pelajaran secara massal (Bulk Delete).
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
        ]);

        $jumlah = $this->subjectService->hapusMassal($request->input('ids'));

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menghapus {$jumlah} data mata pelajaran.",
            'affected_rows' => $jumlah,
        ]);
    }

    /**
     * Ekspor data mata pelajaran ke format Excel / CSV.
     */
    public function exportExcel(Request $request)
    {
        $filters = $request->only(['search', 'unit_pendidikan_id', 'kurikulum_id', 'kelompok_mapel', 'kategori', 'jenjang', 'status']);
        $subjects = $this->subjectService->dapatkanDataEkspor($filters);

        $data = $subjects->map(function ($s) {
            return [
                'Kode Mapel' => $s->kode_mapel ?? $s->code,
                'Nama Mapel' => $s->nama_mapel ?? $s->name,
                'Nama Singkat' => $s->nama_singkat ?? '-',
                'Unit Pendidikan' => $s->unitPendidikan->name ?? '-',
                'Kurikulum' => $s->kurikulum->nama_kurikulum ?? '-',
                'Kelompok' => $s->kelompok_mapel ?? 'Kelompok A',
                'Kategori' => $s->kategori ?? 'Wajib',
                'Jenjang' => $s->jenjang ?? 'SD',
                'Jam Pelajaran' => $s->jam_pelajaran ?? 2,
                'KKM' => $s->kkm ?? 75.00,
                'Bobot Pengetahuan' => $s->bobot_pengetahuan ?? 40,
                'Bobot Keterampilan' => $s->bobot_keterampilan ?? 40,
                'Bobot Sikap' => $s->bobot_sikap ?? 20,
                'Status' => $s->status ? 'Aktif' : 'Non-Aktif',
            ];
        });

        return Excel::download(new SubjectExport($subjects), 'master_mata_pelajaran_'.date('Ymd_His').'.xlsx');
    }

    /**
     * Ekspor data mata pelajaran ke format PDF.
     */
    public function exportPdf(Request $request)
    {
        $filters = $request->only(['search', 'unit_pendidikan_id', 'kurikulum_id', 'kelompok_mapel', 'kategori', 'jenjang', 'status']);
        $subjects = $this->subjectService->dapatkanDataEkspor($filters);

        return Pdf::loadView('exports.subjects', ['subjects' => $subjects])
            ->setPaper('a4', 'landscape')
            ->download('laporan_master_mata_pelajaran_'.date('Ymd_His').'.pdf');
    }

    /**
     * Impor data mata pelajaran.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,json'],
        ]);

        $sheet = Excel::toCollection(null, $request->file('file'))->first();
        abort_if(! $sheet || $sheet->isEmpty(), 422, 'File tidak memiliki data mata pelajaran.');
        $headings = $sheet->shift()->map(fn ($value) => str($value)->trim()->snake()->value())->all();
        $rows = $sheet->map(fn ($row) => array_filter(array_combine($headings, $row->all()), fn ($value) => $value !== null && $value !== ''))->all();
        $result = $this->subjectService->prosesImport($rows, $request->user()?->id);

        return response()->json([
            'status' => 'success',
            'message' => "Impor selesai. {$result['imported_rows']} data berhasil diproses.",
            'data' => $result,
        ]);
    }
}
