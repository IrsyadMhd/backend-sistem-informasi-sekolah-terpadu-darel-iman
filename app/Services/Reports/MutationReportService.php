<?php

namespace App\Services\Reports;

use App\Models\EducationUnit;
use App\Models\Student;
use Carbon\Carbon;

class MutationReportService
{
    public function getReport(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        // Base query for students with mutation metadata
        $baseQuery = Student::with(['educationUnit', 'kelas'])
            ->whereNotNull('metadata->mutasi_type');

        if (!empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $unitId = $filters['unit_id'];
            $baseQuery->where(function ($q) use ($unitId) {
                $q->where('unit_id', $unitId)
                  ->orWhere('metadata->unit_asal_id', $unitId)
                  ->orWhere('metadata->unit_tujuan_id', $unitId);
            });
        }

        if (!empty($filters['jenis_mutasi']) && $filters['jenis_mutasi'] !== 'all') {
            $baseQuery->where('metadata->mutasi_type', $filters['jenis_mutasi']);
        }

        if (!empty($filters['status_proses']) && $filters['status_proses'] !== 'all') {
            $statusFilter = strtolower($filters['status_proses']);
            $baseQuery->where(function ($q) use ($statusFilter) {
                if ($statusFilter === 'selesai' || $statusFilter === 'disetujui') {
                    $q->whereIn('metadata->mutasi_status', ['Selesai', 'Disetujui', 'approved', 'selesai']);
                } elseif ($statusFilter === 'proses' || $statusFilter === 'dalam_proses') {
                    $q->whereIn('metadata->mutasi_status', ['Proses', 'proses', 'dalam_proses', 'pending']);
                } elseif ($statusFilter === 'diajukan') {
                    $q->whereIn('metadata->mutasi_status', ['Diajukan', 'diajukan']);
                } elseif ($statusFilter === 'ditolak') {
                    $q->whereIn('metadata->mutasi_status', ['Ditolak', 'ditolak', 'rejected']);
                } else {
                    $q->where('metadata->mutasi_status', $filters['status_proses']);
                }
            });
        }

        if (!empty($filters['tanggal_mulai'])) {
            $baseQuery->where('metadata->tanggal_efektif', '>=', $filters['tanggal_mulai']);
        }

        if (!empty($filters['tanggal_selesai'])) {
            $baseQuery->where('metadata->tanggal_efektif', '<=', $filters['tanggal_selesai']);
        }

        if (!empty($filters['search'])) {
            $search = (string) $filters['search'];
            $baseQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('metadata->nomor_mutasi', 'like', "%{$search}%")
                  ->orWhere('metadata->sekolah_eksternal', 'like', "%{$search}%");
            });
        }

        $allMutations = (clone $baseQuery)->get();
        $totalMutasi = $allMutations->count();

        $pindahMasuk = $allMutations->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'masuk')->count();
        $pindahKeluar = $allMutations->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'keluar')->count();
        $berhentiCount = $allMutations->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'berhenti')->count();
        $pindahAntarunit = $allMutations->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'antar_unit')->count();

        $selesaiCount = $allMutations->filter(fn ($s) => in_array(strtolower($s->metadata['mutasi_status'] ?? ''), ['selesai', 'disetujui', 'approved', 'completed']))->count();
        $prosesCount = $allMutations->filter(fn ($s) => in_array(strtolower($s->metadata['mutasi_status'] ?? ''), ['proses', 'pending', 'dalam_proses']))->count();
        $diajukanCount = $allMutations->filter(fn ($s) => strtolower($s->metadata['mutasi_status'] ?? '') === 'diajukan')->count();
        $ditolakCount = $allMutations->filter(fn ($s) => in_array(strtolower($s->metadata['mutasi_status'] ?? ''), ['ditolak', 'rejected']))->count();

        // Standardize fallback for statuses
        $otherStatusCount = max(0, $totalMutasi - ($selesaiCount + $prosesCount + $diajukanCount + $ditolakCount));
        if ($otherStatusCount > 0) {
            $selesaiCount += $otherStatusCount;
        }

        $selisih = $pindahMasuk - $pindahKeluar;

        // Recap per unit
        $units = EducationUnit::all();
        $unitRecaps = $units->map(function ($u) {
            $muts = Student::where(function ($q) use ($u) {
                $q->where('unit_id', $u->id)
                  ->orWhere('metadata->unit_asal_id', $u->id)
                  ->orWhere('metadata->unit_tujuan_id', $u->id);
            })->whereNotNull('metadata->mutasi_type')->get();

            $pIn = $muts->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'masuk' && (($s->metadata['unit_tujuan_id'] ?? $s->unit_id) == $u->id))->count();
            $pOut = $muts->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'keluar' && (($s->metadata['unit_asal_id'] ?? $s->unit_id) == $u->id))->count();
            $pStopped = $muts->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'berhenti' && (($s->metadata['unit_asal_id'] ?? $s->unit_id) == $u->id))->count();
            $pInterIn = $muts->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'antar_unit' && ($s->metadata['unit_tujuan_id'] ?? '') == $u->id)->count();
            $pInterOut = $muts->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'antar_unit' && ($s->metadata['unit_asal_id'] ?? '') == $u->id)->count();

            $inProgress = $muts->filter(fn ($s) => in_array(strtolower($s->metadata['mutasi_status'] ?? ''), ['proses', 'pending', 'diajukan']))->count();
            $done = $muts->filter(fn ($s) => in_array(strtolower($s->metadata['mutasi_status'] ?? ''), ['selesai', 'disetujui']))->count();
            $net = $pIn - $pOut;

            return [
                'unit_id' => $u->id,
                'unit_code' => $u->code,
                'unit_name' => $u->name,
                'pindah_masuk' => $pIn,
                'pindah_keluar' => $pOut,
                'berhenti' => $pStopped,
                'antarunit_masuk' => $pInterIn,
                'antarunit_keluar' => $pInterOut,
                'dalam_proses' => $inProgress,
                'selesai' => $done,
                'selisih' => $net,
                'is_negative' => $net < 0,
            ];
        })->values();

        $topMasukUnit = $unitRecaps->sortByDesc('pindah_masuk')->first();
        $topKeluarUnit = $unitRecaps->sortByDesc('pindah_keluar')->first();

        $summary = [
            'total' => $totalMutasi,
            'total_mutasi' => $totalMutasi,
            'incoming' => $pindahMasuk,
            'pindah_masuk' => $pindahMasuk,
            'outgoing' => $pindahKeluar,
            'pindah_keluar' => $pindahKeluar,
            'stopped' => $berhentiCount,
            'berhenti' => $berhentiCount,
            'inter_unit' => $pindahAntarunit,
            'pindah_antarunit' => $pindahAntarunit,
            'pending' => $prosesCount + $diajukanCount,
            'mutasi_selesai' => $selesaiCount,
            'mutasi_dalam_proses' => $prosesCount + $diajukanCount,
            'mutasi_ditolak' => $ditolakCount,
            'selisih' => $selisih,
            'unit_masuk_tertinggi' => $topMasukUnit && $topMasukUnit['pindah_masuk'] > 0 ? "{$topMasukUnit['unit_name']} ({$topMasukUnit['pindah_masuk']})" : '-',
            'unit_keluar_tertinggi' => $topKeluarUnit && $topKeluarUnit['pindah_keluar'] > 0 ? "{$topKeluarUnit['unit_name']} ({$topKeluarUnit['pindah_keluar']})" : '-',
        ];

        // Monthly trend chart
        $monthlyTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthObj = Carbon::now()->subMonths($i);
            $monthKey = $monthObj->format('Y-m');
            $monthLabel = $monthObj->translatedFormat('M Y');

            $monthMutations = $allMutations->filter(function ($s) use ($monthKey) {
                $effDate = $s->metadata['tanggal_efektif'] ?? $s->created_at->format('Y-m-d');
                return str_starts_with($effDate, $monthKey);
            });

            $monthlyTrend[] = [
                'month' => $monthLabel,
                'masuk' => $monthMutations->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'masuk')->count(),
                'keluar' => $monthMutations->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'keluar')->count(),
                'berhenti' => $monthMutations->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'berhenti')->count(),
                'antarunit' => $monthMutations->filter(fn ($s) => ($s->metadata['mutasi_type'] ?? '') === 'antar_unit')->count(),
            ];
        }

        // Charts
        $charts = [
            'jenis_mutasi' => [
                ['name' => 'Pindah Masuk', 'value' => $pindahMasuk],
                ['name' => 'Pindah Keluar', 'value' => $pindahKeluar],
                ['name' => 'Berhenti', 'value' => $berhentiCount],
                ['name' => 'Pindah Antarunit', 'value' => $pindahAntarunit],
            ],
            'status_proses' => [
                ['name' => 'Selesai', 'value' => $selesaiCount],
                ['name' => 'Dalam Proses', 'value' => $prosesCount],
                ['name' => 'Diajukan', 'value' => $diajukanCount],
                ['name' => 'Ditolak', 'value' => $ditolakCount],
            ],
            'unit_comparison' => $unitRecaps->map(fn ($r) => [
                'name' => $r['unit_code'] ?: $r['unit_name'],
                'masuk' => $r['pindah_masuk'],
                'keluar' => $r['pindah_keluar'],
            ])->toArray(),
            'monthly_trend' => $monthlyTrend,
        ];

        // Paginated details
        $perPage = max(1, (int) ($filters['per_page'] ?? 15));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $paginated = (clone $baseQuery)->paginate($perPage, ['*'], 'page', $page);

        $formattedDetails = collect($paginated->items())->map(function ($s) {
            $meta = $s->metadata ?? [];
            $jenisRaw = $meta['mutasi_type'] ?? 'masuk';
            $jenisLabel = match ($jenisRaw) {
                'masuk' => 'Pindah Masuk',
                'keluar' => 'Pindah Keluar',
                'berhenti' => 'Berhenti',
                'antar_unit' => 'Pindah Antarunit',
                default => ucfirst($jenisRaw),
            };

            return [
                'id' => $s->id,
                'nomor_mutasi' => $meta['nomor_mutasi'] ?? ('MUT-' . strtoupper(substr($s->id, 0, 8))),
                'nis' => $s->nis ?? '-',
                'nisn' => $s->nisn ?? '-',
                'nama_siswa' => $s->full_name,
                'jenis_mutasi' => $jenisLabel,
                'jenis_mutasi_raw' => $jenisRaw,
                'unit_asal' => $meta['unit_asal_name'] ?? ($s->educationUnit->name ?? '-'),
                'unit_tujuan' => $meta['unit_tujuan_name'] ?? '-',
                'sekolah_eksternal' => $meta['sekolah_eksternal'] ?? '-',
                'kelas_asal' => $meta['kelas_asal_name'] ?? ($s->kelas->nama_kelas ?? '-'),
                'kelas_tujuan' => $meta['kelas_tujuan_name'] ?? '-',
                'alasan' => $meta['alasan'] ?? '-',
                'tanggal_efektif' => isset($meta['tanggal_efektif']) ? Carbon::parse($meta['tanggal_efektif'])->format('d M Y') : $s->created_at->format('d M Y'),
                'status' => ucfirst($meta['mutasi_status'] ?? 'Selesai'),
            ];
        })->values()->toArray();

        return [
            'report' => [
                'title' => 'Laporan Mutasi Siswa',
                'description' => 'Laporan perpindahan siswa masuk, keluar, berhenti, dan antarunit dalam periode tertentu.',
                'period' => $period,
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => $summary,
            'charts' => $charts,
            'unit_recaps' => $unitRecaps->toArray(),
            'unit_recaps_total' => [
                'unit_name' => 'TOTAL KESELURUHAN',
                'pindah_masuk' => $unitRecaps->sum('pindah_masuk'),
                'pindah_keluar' => $unitRecaps->sum('pindah_keluar'),
                'berhenti' => $unitRecaps->sum('berhenti'),
                'antarunit_masuk' => $unitRecaps->sum('antarunit_masuk'),
                'antarunit_keluar' => $unitRecaps->sum('antarunit_keluar'),
                'dalam_proses' => $unitRecaps->sum('dalam_proses'),
                'selesai' => $unitRecaps->sum('selesai'),
                'selisih' => $unitRecaps->sum('selisih'),
            ],
            'items' => $formattedDetails,
            'details' => $formattedDetails,
            'insights' => [
                'selisih_trend' => $selisih >= 0 ? "Surplus {$selisih} Siswa (Pertumbuhan positif)" : "Defisit " . abs($selisih) . " Siswa",
                'mutasi_terbanyak' => $topMasukUnit && $topMasukUnit['pindah_masuk'] > 0 ? "{$topMasukUnit['unit_name']} tertinggi masuk" : '-',
            ],
            'filters' => $filters,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    public function getDetail(string $id): array
    {
        $s = Student::with(['educationUnit', 'kelas'])->findOrFail($id);
        $meta = $s->metadata ?? [];

        $jenisRaw = $meta['mutasi_type'] ?? 'masuk';
        $jenisLabel = match ($jenisRaw) {
            'masuk' => 'Pindah Masuk',
            'keluar' => 'Pindah Keluar',
            'berhenti' => 'Berhenti',
            'antar_unit' => 'Pindah Antarunit',
            default => ucfirst($jenisRaw),
        };

        return [
            'id' => $s->id,
            'nomor_mutasi' => $meta['nomor_mutasi'] ?? ('MUT-' . strtoupper(substr($s->id, 0, 8))),
            'nis' => $s->nis ?? '-',
            'nisn' => $s->nisn ?? '-',
            'nama' => $s->full_name,
            'jenis_mutasi' => $jenisLabel,
            'unit_asal' => $meta['unit_asal_name'] ?? ($s->educationUnit->name ?? '-'),
            'unit_tujuan' => $meta['unit_tujuan_name'] ?? '-',
            'kelas_asal' => $meta['kelas_asal_name'] ?? ($s->kelas->nama_kelas ?? '-'),
            'kelas_tujuan' => $meta['kelas_tujuan_name'] ?? '-',
            'sekolah_eksternal' => $meta['sekolah_eksternal'] ?? '-',
            'tanggal_pengajuan' => isset($meta['tanggal_pengajuan']) ? Carbon::parse($meta['tanggal_pengajuan'])->format('d F Y') : $s->created_at->format('d F Y'),
            'tanggal_efektif' => isset($meta['tanggal_efektif']) ? Carbon::parse($meta['tanggal_efektif'])->format('d F Y') : $s->created_at->format('d F Y'),
            'alasan' => $meta['alasan'] ?? 'Pindah domisili orang tua / keputusan keluarga',
            'status' => ucfirst($meta['mutasi_status'] ?? 'Selesai'),
            'riwayat' => [
                ['tanggal' => isset($meta['tanggal_pengajuan']) ? Carbon::parse($meta['tanggal_pengajuan'])->format('d M Y') : $s->created_at->format('d M Y'), 'tindakan' => 'Pengajuan Mutasi', 'oleh' => $meta['created_by_name'] ?? 'Orang Tua / Administrasi'],
                ['tanggal' => isset($meta['tanggal_efektif']) ? Carbon::parse($meta['tanggal_efektif'])->subDays(1)->format('d M Y') : $s->created_at->addDays(1)->format('d M Y'), 'tindakan' => 'Verifikasi Berkas & Persetujuan Unit', 'oleh' => 'Kepala Sekolah'],
                ['tanggal' => isset($meta['tanggal_efektif']) ? Carbon::parse($meta['tanggal_efektif'])->format('d M Y') : $s->created_at->addDays(2)->format('d M Y'), 'tindakan' => 'SK Mutasi Diterbitkan', 'oleh' => 'Pengurus Yayasan'],
            ],
        ];
    }

    private function resolvePeriod(array $filters): array
    {
        return [
            'type' => 'year',
            'label' => 'Tahun Ajaran ' . date('Y'),
            'start_date' => date('Y-01-01'),
            'end_date' => date('Y-12-31'),
        ];
    }
}
