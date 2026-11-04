<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\LaporanBulanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReviewLaporanBulananController extends Controller
{
    /**
     * Display a listing of monthly reports for Divisi Pendidikan review.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LaporanBulanan::query()
            ->with(['unit:id,name,code', 'penginput:id,name,email', 'pemeriksa:id,name'])
            ->latest('created_at');

        // Filter status validasi
        if ($request->filled('status_validasi') && $request->status_validasi !== 'all') {
            $query->where('status_validasi', $request->status_validasi);
        }

        // Filter unit
        if ($request->filled('unit_id') && $request->unit_id !== 'all') {
            $query->where('unit_id', $request->unit_id);
        }

        // Filter search
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(judul_laporan) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(kode_laporan) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(catatan_divisi) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(ringkasan_laporan) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('unit', function ($qu) use ($search) {
                        $qu->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(code) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        $items = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'kode_laporan' => $item->kode_laporan ?: 'LPR-' . strtoupper(substr($item->id, 0, 8)),
                'judul' => $item->judul_laporan,
                'unit_id' => $item->unit_id,
                'unit_name' => $item->unit ? $item->unit->name : 'Semua Unit',
                'penginput' => $item->penginput ? $item->penginput->name : 'Kepala Sekolah',
                'pemeriksa' => $item->pemeriksa ? $item->pemeriksa->name : null,
                'periode' => $item->periode ?: ($item->bulan && $item->tahun ? "Bulan {$item->bulan}/{$item->tahun}" : 'Tahun Ajaran 2026/2027'),
                'bulan' => $item->bulan,
                'tahun' => $item->tahun,
                'kategori_laporan' => $item->kategori_laporan ?: 'Laporan Kinerja Manajerial',
                'ringkasan_laporan' => $item->ringkasan_laporan,
                'tindak_lanjut' => $item->tindak_lanjut,
                'status_validasi' => $item->status_validasi ?: 'menunggu',
                'catatan_divisi' => $item->catatan_divisi ?: 'Menunggu tinjauan evaluasi akademik dari Divisi Pendidikan.',
                'persentase_kelengkapan' => $item->persentase_kelengkapan ?: 100,
                'tanggal_kirim' => $item->tanggal_kirim ? $item->tanggal_kirim->format('Y-m-d') : ($item->created_at ? $item->created_at->format('Y-m-d') : date('Y-m-d')),
                'data_tambahan' => $item->data_tambahan,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    /**
     * Provide selection options for reviewing monthly reports.
     */
    public function options(): JsonResponse
    {
        $units = EducationUnit::query()
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $activeYear = AcademicYear::where('is_active', true)->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'units' => $units,
                'active_year' => $activeYear ? $activeYear->name : '2026/2027',
                'statuses' => [
                    ['value' => 'menunggu', 'label' => 'Menunggu Reviu'],
                    ['value' => 'disetujui', 'label' => 'Disetujui Penuh'],
                    ['value' => 'revisi', 'label' => 'Perlu Revisi Unit'],
                ],
            ],
        ]);
    }

    /**
     * Store a newly created monthly report in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'nullable|uuid|exists:education_units,id',
            'judul_laporan' => 'required|string|max:180',
            'periode' => 'nullable|string|max:50',
            'bulan' => 'nullable|integer|min:1|max:12',
            'tahun' => 'nullable|integer|min:2020|max:2030',
            'kategori_laporan' => 'nullable|string|max:100',
            'ringkasan_laporan' => 'nullable|string',
            'status_validasi' => 'nullable|string|in:menunggu,disetujui,revisi,draf',
            'catatan_divisi' => 'nullable|string',
            'tanggal_kirim' => 'nullable|date',
            'persentase_kelengkapan' => 'nullable|integer|min:0|max:100',
        ]);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $count = LaporanBulanan::count() + 1;
        $kode = 'LPR-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        $report = LaporanBulanan::create([
            'kode_laporan' => $kode,
            'id_tahun_ajaran' => $activeYear?->id,
            'unit_id' => $validated['unit_id'] ?? null,
            'judul_laporan' => $validated['judul_laporan'],
            'periode' => $validated['periode'] ?? date('F Y'),
            'bulan' => $validated['bulan'] ?? (int) date('n'),
            'tahun' => $validated['tahun'] ?? (int) date('Y'),
            'kategori_laporan' => $validated['kategori_laporan'] ?? 'Laporan Kinerja Manajerial',
            'ringkasan_laporan' => $validated['ringkasan_laporan'] ?? 'Laporan bulanan operasional akademik dan kesiswaan.',
            'status_validasi' => $validated['status_validasi'] ?? 'menunggu',
            'catatan_divisi' => $validated['catatan_divisi'] ?? null,
            'tanggal_kirim' => $validated['tanggal_kirim'] ?? date('Y-m-d'),
            'persentase_kelengkapan' => $validated['persentase_kelengkapan'] ?? 100,
            'id_penginput' => $request->user()?->id ?: $this->getFallbackUserId(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan bulanan berhasil dibuat dan tercatat di database.',
            'data' => $report->load(['unit', 'penginput']),
        ], 201);
    }

    /**
     * Display the specified report.
     */
    public function show(string $id): JsonResponse
    {
        $report = LaporanBulanan::with(['unit', 'penginput', 'pemeriksa'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * Submit review / evaluation from Divisi Pendidikan.
     */
    public function review(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status_validasi' => 'required|string|in:disetujui,revisi,menunggu',
            'catatan_divisi' => 'required|string',
        ]);

        $report = LaporanBulanan::findOrFail($id);
        $report->status_validasi = $validated['status_validasi'];
        $report->catatan_divisi = $validated['catatan_divisi'];
        if ($request->user()) {
            $report->id_pemeriksa = $request->user()->id;
        }
        $report->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan evaluasi laporan berhasil diperbarui di database.',
            'data' => $report->load(['unit', 'penginput', 'pemeriksa']),
        ]);
    }

    /**
     * Remove the specified report.
     */
    public function destroy(string $id): JsonResponse
    {
        $report = LaporanBulanan::findOrFail($id);
        $report->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan bulanan berhasil dihapus.',
        ]);
    }

    private function getFallbackUserId(): ?string
    {
        $user = \App\Models\User::first();
        return $user?->id;
    }
}
