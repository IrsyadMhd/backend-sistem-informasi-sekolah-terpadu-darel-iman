<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\GenerateLmsRaporRequest;
use App\Http\Requests\Lms\StoreLmsRaporRequest;
use App\Http\Requests\Lms\UpdateLmsRaporRequest;
use App\Http\Resources\LmsRaporResource;
use App\Models\LmsRapor;
use App\Services\AccessScopeService;
use App\Services\LmsRaporService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class LmsRaporController extends Controller
{
    public function __construct(
        protected LmsRaporService $raporService,
        protected AccessScopeService $accessScope
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->only([
            'search',
            'kelas_id',
            'semester_id',
            'tahun_ajaran_id',
            'status_rapor',
            'siswa_id',
            'with_trashed',
        ]);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar penugasan mengajar Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }

            if (! empty($filters['siswa_id'])) {
                $accessibleStudentIds = $this->accessScope->accessibleStudents($user)->pluck('id')->all();
                abort_unless(in_array($filters['siswa_id'], $accessibleStudentIds, true), 403, 'Akses ditolak: Siswa di luar penugasan mengajar Anda.');
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $rapors = $this->raporService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsRaporResource::collection($rapors);
    }

    public function store(StoreLmsRaporRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertKelasAccess($request, $validated['kelas_id']);
        $rapor = $this->raporService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dibuat.',
            'data' => new LmsRaporResource($rapor->load(['siswa', 'kelas', 'semester', 'tahunAjaran', 'waliKelas'])),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $rapor = $this->assertRaporAccess($request, $id);

        return response()->json([
            'success' => true,
            'data' => new LmsRaporResource($rapor),
        ]);
    }

    public function update(UpdateLmsRaporRequest $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
        $validated = $request->validated();
        if (! empty($validated['kelas_id'])) {
            $this->assertKelasAccess($request, $validated['kelas_id']);
        }
        $rapor = $this->raporService->ubah($id, $validated);

        if (! $rapor) {
            return response()->json([
                'success' => false,
                'message' => 'Rapor Digital tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil diperbarui.',
            'data' => new LmsRaporResource($rapor),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
        $success = $this->raporService->hapus($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Rapor Digital tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
        $success = $this->raporService->pulihkan($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Rapor Digital tidak ditemukan atau tidak dalam status terhapus.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dipulihkan.',
        ]);
    }

    public function generateClass(GenerateLmsRaporRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertKelasAccess($request, $validated['kelas_id']);

        $records = $this->raporService->generateClass(
            $validated['kelas_id'],
            $validated['semester_id'],
            $validated['tahun_ajaran_id']
        );

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengolah & mengkalkulasi {$records->count()} Rapor Digital siswa dalam kelas.",
            'data' => LmsRaporResource::collection($records),
        ]);
    }

    public function exportPdf(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);

        try {
            $pdfData = $this->raporService->getPdfData($id);

            return response()->json([
                'success' => true,
                'message' => 'Data cetak Rapor PDF berhasil disiapkan.',
                'data' => [
                    'rapor' => new LmsRaporResource($pdfData['rapor']),
                    'school_info' => $pdfData['school_info'],
                    'grades' => $pdfData['grades'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['kelas_id', 'semester_id', 'tahun_ajaran_id']);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar penugasan mengajar Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }
        }

        $stats = $this->raporService->statistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $isGlobal = $user && $this->accessScope->hasGlobalScope($user);

        if ($isGlobal) {
            $options = Cache::remember('lms_rapor_options_global', 1800, function () {
                return $this->raporService->opsi();
            });
        } else {
            $options = $this->raporService->opsi();
            $accessibleRombels = $this->accessScope->accessibleRombels($user)->get();
            $accessibleStudents = $this->accessScope->accessibleStudents($user)->get();

            $options['kelases'] = $accessibleRombels->map(fn ($k) => [
                'id' => $k->id,
                'nama_kelas' => $k->nama_kelas ?? $k->name,
                'tingkat' => $k->tingkat ?? $k->level,
            ])->values()->all();

            $options['students'] = $accessibleStudents->map(fn ($s) => [
                'id' => $s->id,
                'full_name' => $s->full_name ?? $s->name,
                'nisn' => $s->nisn,
                'nis' => $s->nis,
                'kelas_id' => $s->kelas_id,
            ])->values()->all();

            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            $options['employees'] = \App\Models\Employee::query()
                ->whereIn('unit_id', $allowedUnitIds)
                ->select('id', 'nama_lengkap', 'niy', 'nik')
                ->orderBy('nama_lengkap')
                ->get()
                ->all();
        }

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
        $rapor = $this->raporService->ubah($id, [
            'status_rapor' => 'published',
            'tanggal_terbit' => now()->toDateString(),
        ]);

        if (! $rapor) {
            return response()->json(['success' => false, 'message' => 'Rapor Digital tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dipublikasikan.',
            'data' => new LmsRaporResource($rapor),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
        $rapor = $this->raporService->ubah($id, ['status_rapor' => 'final']);

        if (! $rapor) {
            return response()->json(['success' => false, 'message' => 'Rapor Digital tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil disetujui (Approved).',
            'data' => new LmsRaporResource($rapor),
        ]);
    }

    private function assertKelasAccess(Request $request, string $kelasId): void
    {
        $user = $request->user();
        if (! $user || $this->accessScope->hasGlobalScope($user)) {
            return;
        }

        $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
        abort_unless(in_array($kelasId, $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar penugasan mengajar Anda.');
    }

    private function assertRaporAccess(Request $request, string $id): LmsRapor
    {
        $rapor = $this->raporService->cariBerdasarkanId($id, true);
        abort_unless($rapor, 404, 'Rapor Digital tidak ditemukan.');

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            abort_unless(in_array($rapor->kelas_id, $accessibleRombelIds, true), 403, 'Akses ditolak: Rapor berada di luar rombel penugasan Anda.');
        }

        return $rapor;
    }

    /**
     * Impor rapor digital massal dari CSV.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'kelas_id' => 'nullable|string',
            'semester_id' => 'nullable|string',
            'tahun_ajaran_id' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $lines = preg_split('/\r\n|\r|\n/', trim($content));

        if (empty($lines)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas CSV kosong.',
            ], 422);
        }

        $header = str_getcsv(array_shift($lines));
        $importedCount = 0;

        $defaultClass = \App\Models\Rombel::first();
        $defaultSemester = \App\Models\Semester::where('is_active', true)->first() ?? \App\Models\Semester::first();
        $defaultTahun = \App\Models\TahunAjaran::where('is_active', true)->first() ?? \App\Models\TahunAjaran::first();

        $kelasId = $request->get('kelas_id') ?: $defaultClass?->id;
        $semesterId = $request->get('semester_id') ?: $defaultSemester?->id;
        $tahunAjaranId = $request->get('tahun_ajaran_id') ?: $defaultTahun?->id;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            if (count($row) < 2) continue;

            $nisnOrId = trim($row[0] ?? '');
            $namaSiswa = trim($row[1] ?? '');
            $namaKelas = trim($row[2] ?? '');
            $namaSemester = trim($row[3] ?? '');
            $rataRata = (float) ($row[4] ?? 80);
            $peringkat = (int) ($row[5] ?? ($importedCount + 1));
            $hadir = (int) ($row[6] ?? 100);
            $izin = (int) ($row[7] ?? 0);
            $sakit = (int) ($row[8] ?? 0);
            $alpha = (int) ($row[9] ?? 0);
            $status = trim($row[10] ?? 'draft');

            $siswa = null;
            if ($nisnOrId) {
                $siswa = \App\Models\Student::where('nisn', $nisnOrId)
                    ->orWhere('nis', $nisnOrId)
                    ->orWhere('id', $nisnOrId)
                    ->first();
            }
            if (!$siswa && $namaSiswa) {
                $siswa = \App\Models\Student::where('nama_lengkap', 'like', "%{$namaSiswa}%")
                    ->orWhere('name', 'like', "%{$namaSiswa}%")
                    ->first();
            }

            if (!$siswa) {
                $siswa = \App\Models\Student::first();
            }

            if (!$siswa) continue;

            $targetKelasId = $kelasId;
            if ($namaKelas) {
                $foundKelas = \App\Models\Rombel::where('nama_kelas', 'like', "%{$namaKelas}%")->first();
                if ($foundKelas) $targetKelasId = $foundKelas->id;
            }

            LmsRapor::updateOrCreate(
                [
                    'siswa_id' => $siswa->id,
                    'kelas_id' => $targetKelasId,
                    'semester_id' => $semesterId,
                ],
                [
                    'tahun_ajaran_id' => $tahunAjaranId,
                    'rata_rata' => $rataRata,
                    'peringkat_kelas' => $peringkat,
                    'total_nilai' => $rataRata * 10,
                    'total_mapel' => 10,
                    'mapel_lulus' => 10,
                    'mapel_tidak_lulus' => 0,
                    'total_hari_efektif' => $hadir + $izin + $sakit + $alpha,
                    'total_hadir' => $hadir,
                    'total_izin' => $izin,
                    'total_sakit' => $sakit,
                    'total_alpha' => $alpha,
                    'status_rapor' => in_array($status, ['draft', 'review', 'approved', 'published']) ? $status : 'draft',
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]
            );

            $importedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil mengimpor {$importedCount} data Rapor Digital.",
            'data' => [
                'imported_count' => $importedCount,
            ],
        ]);
    }
}
