<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationUnit;
use App\Models\RadarAkreditasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RadarAkreditasiController extends Controller
{
    /**
     * Default 8 SNP + 1 Kekhasan JSIT Standard Structure
     */
    public static function getDefaultStandards(): array
    {
        return [
            ['nama' => 'Standar Kompetensi Lulusan (SKL)', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
            ['nama' => 'Standar Isi (Kurikulum SIT)', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
            ['nama' => 'Standar Proses (KBM & LMS)', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
            ['nama' => 'Standar Penilaian Pendidikan', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
            ['nama' => 'Standar Pendidik & Tenaga Kependidikan', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
            ['nama' => 'Standar Sarana & Prasarana', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
            ['nama' => 'Standar Pengelolaan Sekolah', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
            ['nama' => 'Standar Pembiayaan', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
            ['nama' => 'Standar Kekhasan JSIT / Karakter Adab', 'skor' => 0, 'bukti' => 'Belum Lengkap', 'status' => 'belum_lengkap'],
        ];
    }

    /**
     * Display a listing of accreditation radar data.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RadarAkreditasi::query()
            ->with(['unit:id,name,code', 'auditor:id,name'])
            ->latest('updated_at');

        if ($request->filled('unit_id') && $request->unit_id !== 'all') {
            $query->where('unit_id', $request->unit_id);
        }

        $items = $query->get()->map(function ($item) {
            $standar = is_array($item->standar_evaluasi) && count($item->standar_evaluasi) > 0
                ? $item->standar_evaluasi
                : self::getDefaultStandards();

            $habisBerlaku = $item->habis_berlaku ? $item->habis_berlaku->format('Y-m-d') : '-';
            $sisaWaktu = $item->sisa_waktu;
            if (!$sisaWaktu && $item->habis_berlaku) {
                $now = Carbon::now();
                if ($item->habis_berlaku->isPast()) {
                    $sisaWaktu = 'Kadaluarsa';
                } else {
                    $diffMonths = $now->diffInMonths($item->habis_berlaku);
                    $sisaWaktu = $diffMonths > 0 ? "{$diffMonths} Bulan" : "{$now->diffInDays($item->habis_berlaku)} Hari";
                }
            }

            return [
                'id' => $item->id,
                'unit_id' => $item->unit_id,
                'kode_audit' => $item->kode_audit ?: 'AKR-' . strtoupper(substr($item->id, 0, 8)),
                'unit_name' => $item->unit ? $item->unit->name : 'Unit Sekolah',
                'unit_code' => $item->unit ? $item->unit->code : '',
                'peringkat_saat_ini' => $item->peringkat_saat_ini ?: 'Belum Terakreditasi',
                'skor_akreditasi' => $item->skor_akreditasi,
                'habis_berlaku' => $habisBerlaku,
                'sisa_waktu' => $sisaWaktu ?: '-',
                'kesiapan_total' => $item->kesiapan_total ?: 0,
                'standar' => $standar,
                'rekomendasi_divisi' => $item->rekomendasi_divisi,
                'auditor_name' => $item->auditor ? $item->auditor->name : null,
                'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d H:i') : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    /**
     * Provide selection options for accreditation radar.
     */
    public function options(): JsonResponse
    {
        $units = EducationUnit::query()
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'units' => $units,
                'default_standards' => self::getDefaultStandards(),
                'ranks' => [
                    'A (Unggul)',
                    'B (Baik)',
                    'C (Cukup)',
                    'Belum Terakreditasi',
                    'Proses Re-Akreditasi',
                ],
            ],
        ]);
    }

    /**
     * Store or initialize accreditation audit for a unit.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'required|uuid|exists:education_units,id',
            'peringkat_saat_ini' => 'required|string|max:60',
            'skor_akreditasi' => 'nullable|integer|min:0|max:100',
            'habis_berlaku' => 'nullable|date',
            'sisa_waktu' => 'nullable|string|max:50',
            'rekomendasi_divisi' => 'nullable|string',
            'standar' => 'nullable|array',
        ]);

        $standards = $validated['standar'] ?? self::getDefaultStandards();
        $totalSkor = array_reduce($standards, fn($acc, $s) => $acc + (int)($s['skor'] ?? 0), 0);
        $avgKesiapan = count($standards) > 0 ? (int)round($totalSkor / count($standards)) : 0;

        $count = RadarAkreditasi::count() + 1;
        $kode = 'AKR-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $audit = RadarAkreditasi::updateOrCreate(
            ['unit_id' => $validated['unit_id']],
            [
                'kode_audit' => $kode,
                'peringkat_saat_ini' => $validated['peringkat_saat_ini'],
                'skor_akreditasi' => $validated['skor_akreditasi'] ?? null,
                'habis_berlaku' => $validated['habis_berlaku'] ?? null,
                'sisa_waktu' => $validated['sisa_waktu'] ?? null,
                'kesiapan_total' => $avgKesiapan,
                'standar_evaluasi' => $standards,
                'rekomendasi_divisi' => $validated['rekomendasi_divisi'] ?? null,
                'auditor_id' => $request->user()?->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Data radar akreditasi unit berhasil disimpan.',
            'data' => $audit->load('unit'),
        ], 201);
    }

    /**
     * Update an accreditation audit item.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $audit = RadarAkreditasi::findOrFail($id);

        $validated = $request->validate([
            'peringkat_saat_ini' => 'nullable|string|max:60',
            'skor_akreditasi' => 'nullable|integer|min:0|max:100',
            'habis_berlaku' => 'nullable|date',
            'sisa_waktu' => 'nullable|string|max:50',
            'rekomendasi_divisi' => 'nullable|string',
            'standar' => 'nullable|array',
        ]);

        if (isset($validated['standar'])) {
            $standards = $validated['standar'];
            $totalSkor = array_reduce($standards, fn($acc, $s) => $acc + (int)($s['skor'] ?? 0), 0);
            $audit->kesiapan_total = count($standards) > 0 ? (int)round($totalSkor / count($standards)) : 0;
            $audit->standar_evaluasi = $standards;
        }

        if (isset($validated['peringkat_saat_ini'])) $audit->peringkat_saat_ini = $validated['peringkat_saat_ini'];
        if (isset($validated['skor_akreditasi'])) $audit->skor_akreditasi = $validated['skor_akreditasi'];
        if (isset($validated['habis_berlaku'])) $audit->habis_berlaku = $validated['habis_berlaku'];
        if (isset($validated['sisa_waktu'])) $audit->sisa_waktu = $validated['sisa_waktu'];
        if (isset($validated['rekomendasi_divisi'])) $audit->rekomendasi_divisi = $validated['rekomendasi_divisi'];

        if ($request->user()) {
            $audit->auditor_id = $request->user()->id;
        }

        $audit->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Data radar akreditasi berhasil diperbarui.',
            'data' => $audit->load('unit'),
        ]);
    }

    /**
     * Specific endpoint to update a standard's score and recalculate total readiness.
     */
    public function updateStandar(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'standar_index' => 'required|integer|min:0|max:20',
            'skor' => 'required|integer|min:0|max:100',
            'bukti' => 'nullable|string|max:255',
        ]);

        $audit = RadarAkreditasi::findOrFail($id);
        $standards = is_array($audit->standar_evaluasi) && count($audit->standar_evaluasi) > 0
            ? $audit->standar_evaluasi
            : self::getDefaultStandards();

        $idx = $validated['standar_index'];
        if (isset($standards[$idx])) {
            $standards[$idx]['skor'] = $validated['skor'];
            if (!empty($validated['bukti'])) {
                $standards[$idx]['bukti'] = $validated['bukti'];
            }
        }

        $totalSkor = array_reduce($standards, fn($acc, $s) => $acc + (int)($s['skor'] ?? 0), 0);
        $audit->kesiapan_total = count($standards) > 0 ? (int)round($totalSkor / count($standards)) : 0;
        $audit->standar_evaluasi = $standards;
        if ($request->user()) {
            $audit->auditor_id = $request->user()->id;
        }
        $audit->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Skor butir standar akreditasi berhasil diperbarui.',
            'data' => $audit->load('unit'),
        ]);
    }

    /**
     * Remove the specified audit.
     */
    public function destroy(string $id): JsonResponse
    {
        $audit = RadarAkreditasi::findOrFail($id);
        $audit->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data audit akreditasi unit berhasil dihapus.',
        ]);
    }
}
