<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanKelasRequest;
use App\Http\Requests\V1\UbahKelasRequest;
use App\Http\Resources\V1\KelasResource;
use App\Models\Kelas;
use App\Services\AccessScopeService;
use App\Services\KelasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class KelasController
 * Controller API untuk manajemen Master Data Kelas / Rombongan Belajar.
 */
class KelasController extends Controller
{
    public function __construct(
        protected KelasService $kelasService,
        protected AccessScopeService $accessScopeService
    ) {}

    /**
     * Dapatkan daftar kelas berpaginasi dengan pencarian, filter, dan sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id') ?? $request->query('unit_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'semester_id' => $request->query('semester_id'),
            'jenjang' => $request->query('jenjang'),
            'tingkat' => $request->query('tingkat'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];
        $allowedKelasIds = $this->accessibleRombelIds(
            $request,
            $request->query('dengan_sampah') === 'true'
        );
        $filters['allowed_kelas_ids'] = $allowedKelasIds;

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $kelas = $this->kelasService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data kelas berhasil dimuat.',
            'data' => KelasResource::collection($kelas),
            'meta' => [
                'current_page' => $kelas->currentPage(),
                'from' => $kelas->firstItem(),
                'last_page' => $kelas->lastPage(),
                'per_page' => $kelas->perPage(),
                'to' => $kelas->lastItem(),
                'total' => $kelas->total(),
            ],
            'statistik' => $this->kelasService->dapatkanStatistik($this->accessibleRombelIds($request)),
        ]);
    }

    /**
     * Dapatkan opsi data master dropdown (Unit, Tahun Ajaran, Semester, Pegawai/Guru).
     */
    public function options(Request $request): JsonResponse
    {
        $allowedUnitIds = $this->accessScopeService
            ->accessibleEducationUnits($request->user())
            ->pluck('education_units.id')
            ->all();
        $options = $this->kelasService->dapatkanOpsiMaster($allowedUnitIds);

        return response()->json([
            'status' => 'success',
            'message' => 'Data opsi master berhasil dimuat.',
            'data' => $options,
        ]);
    }

    /**
     * Dapatkan ringkasan statistik kelas.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->kelasService->dapatkanStatistik($this->accessibleRombelIds($request));

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Simpan data kelas baru.
     */
    public function store(SimpanKelasRequest $request): JsonResponse
    {
        $this->assertUnitScope($request, $request->validated('unit_pendidikan_id'));
        $kelas = $this->kelasService->simpan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil ditambahkan.',
            'data' => new KelasResource($kelas->load(['unitPendidikan', 'tahunAjaran', 'semester', 'waliKelas'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * Tampilkan detail data kelas berdasarkan ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $kelas = $this->scopedKelas($request, $id);

        if (! $kelas) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data kelas tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data kelas berhasil ditemukan.',
            'data' => new KelasResource($kelas),
        ]);
    }

    /**
     * Perbarui data kelas.
     */
    public function update(UbahKelasRequest $request, string $id): JsonResponse
    {
        $this->scopedKelas($request, $id);
        $this->assertUnitScope($request, $request->validated('unit_pendidikan_id'));
        $kelas = $this->kelasService->ubah($id, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil diperbarui.',
            'data' => new KelasResource($kelas),
        ]);
    }

    /**
     * Hapus data kelas (Soft Delete).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->scopedKelas($request, $id);
        $berhasil = $this->kelasService->hapus($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus data kelas.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil dihapus (soft delete).',
        ]);
    }

    /**
     * Pulihkan data kelas yang terhapus.
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $this->scopedKelas($request, $id, true);
        $berhasil = $this->kelasService->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data kelas.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil dipulihkan.',
        ]);
    }

    /**
     * Dapatkan daftar siswa dalam kelas tertentu.
     */
    public function siswa(Request $request, string $id): JsonResponse
    {
        $this->scopedKelas($request, $id);
        $data = $this->kelasService->dapatkanSiswaRombel($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar siswa rombel berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Impor data kelas dari file / payload JSON.
     */
    public function import(Request $request): JsonResponse
    {
        $rows = $request->input('data', []);
        if (! is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor tidak boleh kosong.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hasil = $this->kelasService->prosesImport($this->scopedImportRows($request, $rows));

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Berhasil: {$hasil['berhasil']}, Gagal: {$hasil['gagal']}.",
            'data' => $hasil,
        ]);
    }

    private function scopedKelas(Request $request, string $id, bool $withTrashed = false): Kelas
    {
        $query = $this->accessScopeService->accessibleRombels($request->user());
        if ($withTrashed) {
            $query->withTrashed();
        }

        $kelas = (clone $query)->where(function ($q) use ($id) {
            $q->where('id', $id)->orWhere('kode_kelas', $id);
        })->first();

        if ($kelas) {
            return $kelas;
        }

        $legacyClass = \App\Models\SchoolClass::find($id);
        if ($legacyClass) {
            $matchedKelas = Kelas::where('nama_kelas', $legacyClass->name)
                ->orWhere('kode_kelas', $legacyClass->code ?? $legacyClass->name)
                ->first();
            if ($matchedKelas) {
                return $matchedKelas;
            }
            $fallback = new Kelas();
            $fallback->id = $legacyClass->id;
            $fallback->nama_kelas = $legacyClass->name;
            $fallback->kode_kelas = $legacyClass->code ?? $legacyClass->name;
            return $fallback;
        }

        return $query->whereKey($id)->firstOrFail();
    }

    private function accessibleRombelIds(Request $request, bool $withTrashed = false): array
    {
        $query = $this->accessScopeService->accessibleRombels($request->user());
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->pluck('tbl_kelas.id')->all();
    }

    private function scopedImportRows(Request $request, array $rows): array
    {
        $allowedUnitIds = $this->accessScopeService
            ->accessibleEducationUnits($request->user())
            ->pluck('education_units.id')
            ->all();
        abort_if($allowedUnitIds === [], 403, 'Tidak ada unit pendidikan dalam cakupan akun.');

        $allowedKelasIds = $this->accessibleRombelIds($request, true);
        $codes = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => trim((string) ($row['kode_kelas'] ?? $row['kode'] ?? '')))
            ->filter()
            ->unique();
        $existingByCode = Kelas::withTrashed()
            ->whereIn('kode_kelas', $codes)
            ->get(['id', 'kode_kelas'])
            ->keyBy('kode_kelas');

        return collect($rows)->map(function ($row) use ($allowedUnitIds, $allowedKelasIds, $existingByCode) {
            abort_unless(is_array($row), 422, 'Setiap baris impor harus berupa objek data.');

            $unitId = $row['unit_pendidikan_id'] ?? $row['unit_id'] ?? $allowedUnitIds[0];
            abort_unless(
                in_array($unitId, $allowedUnitIds, true),
                403,
                'Baris impor memuat unit pendidikan di luar cakupan akun.'
            );

            $code = trim((string) ($row['kode_kelas'] ?? $row['kode'] ?? ''));
            $existing = $code !== '' ? $existingByCode->get($code) : null;
            abort_unless(
                ! $existing || in_array($existing->id, $allowedKelasIds, true),
                403,
                'Data kelas yang akan diperbarui berada di luar cakupan akun.'
            );

            $row['unit_pendidikan_id'] = $unitId;
            unset($row['unit_id']);

            return $row;
        })->all();
    }

    private function assertUnitScope(Request $request, ?string $unitId): void
    {
        if (! $unitId) {
            return;
        }

        abort_unless(
            $this->accessScopeService->accessibleEducationUnits($request->user())->whereKey($unitId)->exists(),
            403,
            'Kelas berada di luar cakupan unit akun.'
        );
    }
}
