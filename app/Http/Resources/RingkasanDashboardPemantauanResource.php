<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RingkasanDashboardPemantauanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kartu_statistik' => [
                'total_siswa' => $this['total_siswa'] ?? 0,
                'total_guru' => $this['total_guru'] ?? 0,
                'kehadiran_hari_ini' => $this['kehadiran_hari_ini'] ?? 0,
                'statistik_keterlambatan' => $this['statistik_keterlambatan'] ?? 0,
                'statistik_ketidakhadiran' => $this['statistik_ketidakhadiran'] ?? 0,
            ],
            'donut_chart' => $this['donut_chart'] ?? [],
            'line_chart_kehadiran_mingguan' => $this['line_chart_kehadiran_mingguan'] ?? [],
            'bar_chart_tahfizh' => $this['bar_chart_tahfizh'] ?? [],
            'progress_target_tahfizh' => $this['progress_target_tahfizh'] ?? [],
            'progress_ibadah_siswa' => $this['progress_ibadah_siswa'] ?? [],
            'data_tabel_rekap_prestasi' => $this['data_tabel_rekap_prestasi'] ?? [],
            'pengumuman_sekolah' => $this['pengumuman_sekolah'] ?? [],
            'indikator_kinerja_utama' => $this['indikator_kinerja_utama'] ?? [],
        ];
    }
}
