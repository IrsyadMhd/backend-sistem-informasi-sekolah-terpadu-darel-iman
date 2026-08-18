<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Doa;
use App\Models\QuranSurah;
use App\Services\EQuranSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EQuranController extends Controller
{
    protected EQuranSyncService $syncService;

    public function __construct(EQuranSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * GET /api/equran/surah
     * List all surahs from database (with search & filter)
     */
    public function surahs(Request $request): JsonResponse
    {
        $query = QuranSurah::query();

        if ($search = $request->query('search')) {
            $query->where('nama_latin', 'like', "%{$search}%")
                ->orWhere('nama', 'like', "%{$search}%")
                ->orWhere('arti', 'like', "%{$search}%")
                ->orWhere('nomor', $search);
        }

        if ($tempat = $request->query('tempat_turun')) {
            $query->where('tempat_turun', $tempat);
        }

        $surahs = $query->orderBy('nomor', 'asc')->get();

        // Auto-sync if database is completely empty
        if ($surahs->isEmpty() && !$search) {
            $this->syncService->syncSurahList();
            $surahs = QuranSurah::orderBy('nomor', 'asc')->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar surah berhasil dimuat',
            'data' => $surahs,
        ]);
    }

    /**
     * POST /api/equran/surah
     * Create a new surah entry
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor' => 'required|integer|unique:quran_surahs,nomor',
            'nama' => 'required|string|max:100',
            'nama_latin' => 'required|string|max:100',
            'jumlah_ayat' => 'required|integer|min:1',
            'tempat_turun' => 'required|string|max:50',
            'arti' => 'required|string|max:150',
            'deskripsi' => 'nullable|string',
            'audio_full' => 'nullable|string|max:255',
        ]);

        $surah = QuranSurah::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Surah berhasil ditambahkan ke database',
            'data' => $surah,
        ], 201);
    }

    /**
     * GET /api/equran/surah/{id}
     * Show single surah along with detail ayahs (Nomor Ayat, Teks Arab, Teks Latin, Terjemahan)
     */
    public function show($id): JsonResponse
    {
        $detail = $this->syncService->getSurahDetail($id);

        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Surah tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail surah & rincian ayat berhasil dimuat',
            'data' => $detail['surah'],
            'ayat' => $detail['ayat'],
        ]);
    }

    /**
     * PUT /api/equran/surah/{id}
     * Update surah data
     */
    public function update(Request $request, $id): JsonResponse
    {
        $surah = QuranSurah::find($id);

        if (!$surah) {
            return response()->json([
                'success' => false,
                'message' => 'Surah tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'nomor' => 'required|integer|unique:quran_surahs,nomor,' . $id,
            'nama' => 'required|string|max:100',
            'nama_latin' => 'required|string|max:100',
            'jumlah_ayat' => 'required|integer|min:1',
            'tempat_turun' => 'required|string|max:50',
            'arti' => 'required|string|max:150',
            'deskripsi' => 'nullable|string',
            'audio_full' => 'nullable|string|max:255',
        ]);

        $surah->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data surah berhasil diperbarui',
            'data' => $surah,
        ]);
    }

    /**
     * DELETE /api/equran/surah/{id}
     * Delete surah entry
     */
    public function destroy($id): JsonResponse
    {
        $surah = QuranSurah::find($id);

        if (!$surah) {
            return response()->json([
                'success' => false,
                'message' => 'Surah tidak ditemukan',
            ], 404);
        }

        $surah->delete();

        return response()->json([
            'success' => true,
            'message' => 'Surah berhasil dihapus dari database',
        ]);
    }

    /**
     * GET /api/equran/jadwal-sholat
     */
    public function jadwalSholat(Request $request): JsonResponse
    {
        $kabkotaId = $request->query('kabkota_id', '1');
        $tanggal = $request->query('tanggal', date('Y-m-d'));

        $result = $this->syncService->getJadwalSholat($kabkotaId, $tanggal);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal sholat berhasil dimuat',
            'source' => $result['source'],
            'data' => $result['data'],
        ]);
    }

    /**
     * GET /api/v2/shalat/provinsi
     */
    public function provinsi(Request $request): JsonResponse
    {
        $result = $this->syncService->getProvinsiList();
        return response()->json($result, $result['code'] ?? 200);
    }

    /**
     * POST /api/v2/shalat/kabkota
     */
    public function kabkota(Request $request): JsonResponse
    {
        $provinsi = $request->input('provinsi', 'Jawa Barat');
        $result = $this->syncService->getKabkotaList($provinsi);
        return response()->json($result, $result['code'] ?? 200);
    }

    /**
     * POST /api/v2/shalat
     */
    public function shalat(Request $request): JsonResponse
    {
        $provinsi = $request->input('provinsi', 'Jawa Barat');
        $kabkota = $request->input('kabkota', 'Kota Bogor');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        $result = $this->syncService->getJadwalShalatBulanan($provinsi, $kabkota, $bulan, $tahun);
        return response()->json($result, $result['code'] ?? 200);
    }

    /**
     * POST /api/v2/shalat/save-master
     */
    public function saveMaster(Request $request): JsonResponse
    {
        $result = $this->syncService->saveJadwalShalatToDb($request->all());
        return response()->json($result, 200);
    }

    /**
     * GET /api/v2/shalat/master-list
     */
    public function masterList(Request $request): JsonResponse
    {
        $result = $this->syncService->getMasterJadwalShalatFromDb($request->all());
        return response()->json($result, 200);
    }

    /**
     * DELETE /api/v2/shalat/master-list/{id}
     */
    public function deleteMaster($id): JsonResponse
    {
        $record = \App\Models\JadwalSholatCache::find($id);
        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Data jadwal sholat tidak ditemukan',
            ], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data master jadwal sholat berhasil dihapus',
        ]);
    }

    /**
     * POST /api/equran/sync-surah
     */
    public function syncSurah(): JsonResponse
    {
        $res = $this->syncService->syncSurahList();

        return response()->json($res, $res['success'] ? 200 : 500);
    }

    /**
     * GET /api/doa or GET /api/equran/doa
     * Parameter Query (Opsional): grup, tag, search
     */
    public function doas(Request $request): JsonResponse
    {
        $params = [
            'grup' => $request->query('grup'),
            'tag' => $request->query('tag'),
            'search' => $request->query('search'),
        ];

        $res = $this->syncService->getDoaList($params);

        return response()->json($res, 200);
    }

    /**
     * GET /api/doa/{id} or GET /api/equran/doa/{id}
     */
    public function doaDetail($id): JsonResponse
    {
        $detail = $this->syncService->getDoaDetail($id);

        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Doa tidak ditemukan',
            ], 404);
        }

        return response()->json($detail, 200);
    }

    /**
     * POST /api/doa/sync or POST /api/equran/sync-doa
     */
    public function syncDoa(): JsonResponse
    {
        $res = $this->syncService->syncDoaList();

        return response()->json($res, $res['success'] ? 200 : 500);
    }

    /**
     * POST /api/doa
     * Create manual Doa entry
     */
    public function storeDoa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'nama' => 'required|string|max:150',
            'grup' => 'nullable|string|max:100',
            'ar' => 'nullable|string',
            'tr' => 'nullable|string',
            'idn' => 'nullable|string',
            'tentang' => 'nullable|string',
            'tag' => 'nullable|array',
        ]);

        if (empty($validated['id'])) {
            $validated['id'] = (Doa::max('id') ?? 0) + 1;
        }

        $doa = Doa::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Doa berhasil ditambahkan ke database',
            'data' => $doa,
        ], 201);
    }

    /**
     * PUT /api/doa/{id}
     * Update Doa entry
     */
    public function updateDoa(Request $request, $id): JsonResponse
    {
        $doa = Doa::find((int)$id);

        if (!$doa) {
            return response()->json([
                'success' => false,
                'message' => 'Doa tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'grup' => 'nullable|string|max:100',
            'ar' => 'nullable|string',
            'tr' => 'nullable|string',
            'idn' => 'nullable|string',
            'tentang' => 'nullable|string',
            'tag' => 'nullable|array',
        ]);

        $doa->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data doa berhasil diperbarui',
            'data' => $doa,
        ]);
    }

    /**
     * DELETE /api/doa/{id}
     * Delete Doa entry
     */
    public function destroyDoa($id): JsonResponse
    {
        $doa = Doa::find((int)$id);

        if (!$doa) {
            return response()->json([
                'success' => false,
                'message' => 'Doa tidak ditemukan',
            ], 404);
        }

        $doa->delete();

        return response()->json([
            'success' => true,
            'message' => 'Doa berhasil dihapus dari database',
        ]);
    }
}

