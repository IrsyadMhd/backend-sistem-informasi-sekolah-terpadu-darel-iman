<?php

namespace App\Services\Reports;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Student;
use Carbon\Carbon;

class CrossUnitReportService
{
    public function getReport(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        // Fetch selected units or all active units
        $unitQuery = EducationUnit::with(['jenisUnit']);

        if (!empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
            $selectedIds = array_filter($filters['unit_ids']);
            if (!empty($selectedIds)) {
                $unitQuery->whereIn('id', $selectedIds);
            }
        } elseif (!empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $unitQuery->where('id', $filters['unit_id']);
        }

        $units = $unitQuery->get();

        $mainComparison = $units->map(function ($u) {
            $allEmployees = Employee::with(['position', 'teacherBridge', 'teachings'])->where('unit_id', $u->id)->get();
            $totalPegawai = $allEmployees->count();
            $guru = $allEmployees->filter(function ($e) {
                $posName = $e->position->name ?? '';
                return $e->teacherBridge !== null
                    || $e->teachings->isNotEmpty()
                    || str_contains(strtolower($posName), 'guru')
                    || str_contains(strtolower($posName), 'pendidik')
                    || in_array($e->position?->level_jabatan, [8, 9]);
            })->count();
            $nonGuru = max(0, $totalPegawai - $guru);

            $siswaAktif = Student::where('unit_id', $u->id)->where('is_active', true)->count();
            $siswaBaru = Student::where('unit_id', $u->id)->where('is_active', true)->where(function ($q) {
                $q->where('tahun_masuk', date('Y'))->orWhere('metadata->is_new_student', true);
            })->count();

            $mutasiMasuk = Student::where('unit_id', $u->id)->where('metadata->mutasi_type', 'masuk')->count();
            $mutasiKeluar = Student::where('unit_id', $u->id)->where('metadata->mutasi_type', 'keluar')->count();

            $lulus = Student::where('unit_id', $u->id)->where('is_active', false)->where('metadata->status_siswa', 'lulus')->count();
            $alumni = Student::where('unit_id', $u->id)->where(function ($q) {
                $q->where('is_active', false)->orWhere('metadata->is_alumni', true);
            })->count();

            $kelasCount = Kelas::where('unit_pendidikan_id', $u->id)->count();
            $rombelCount = max(1, $kelasCount);

            return [
                'unit_id' => $u->id,
                'unit_code' => $u->code,
                'unit_name' => $u->name,
                'jenis_unit' => $u->jenisUnit->nama_jenis ?? $u->level ?? 'Umum',
                'guru' => $guru,
                'pegawai' => $nonGuru,
                'total_sdm' => $totalPegawai,
                'siswa' => $siswaAktif,
                'siswa_baru' => $siswaBaru,
                'mutasi_masuk' => $mutasiMasuk,
                'mutasi_keluar' => $mutasiKeluar,
                'lulus' => $lulus,
                'alumni' => $alumni,
                'kelas' => $kelasCount,
                'rombel' => $rombelCount,
            ];
        });

        // Ratio calculations table
        $ratioTable = $mainComparison->map(function ($row) {
            $siswaPerGuru = $row['guru'] > 0 ? round($row['siswa'] / $row['guru'], 1) : 0;
            $siswaPerRombel = $row['rombel'] > 0 ? round($row['siswa'] / $row['rombel'], 1) : 0;
            $guruPerRombel = $row['rombel'] > 0 ? round($row['guru'] / $row['rombel'], 1) : 0;
            $growth = $row['siswa'] > 0 ? round(($row['siswa_baru'] / $row['siswa']) * 100, 1) : 0;
            $passRate = ($row['lulus'] + 5) > 0 ? round(($row['lulus'] / ($row['lulus'] + 5)) * 100, 1) : 100;

            return [
                'unit_id' => $row['unit_id'],
                'unit_code' => $row['unit_code'],
                'unit_name' => $row['unit_name'],
                'siswa_guru' => "1 : {$siswaPerGuru}",
                'siswa_guru_num' => $siswaPerGuru,
                'siswa_rombel' => "{$siswaPerRombel} Siswa/Rombel",
                'siswa_rombel_num' => $siswaPerRombel,
                'guru_rombel' => "{$guruPerRombel} Guru/Rombel",
                'pertumbuhan_siswa' => "{$growth}%",
                'growth_num' => $growth,
                'persentase_kelulusan' => "{$passRate}%",
            ];
        });

        // Executive KPIs
        $totalUnits = $units->count();
        $totalSdm = $mainComparison->sum('total_sdm');
        $totalSiswa = $mainComparison->sum('siswa');
        $totalMutasi = $mainComparison->sum('mutasi_masuk') + $mainComparison->sum('mutasi_keluar');
        $totalLulus = $mainComparison->sum('lulus');
        $totalAlumni = $mainComparison->sum('alumni');

        $totalGuru = $mainComparison->sum('guru');
        $avgSiswaPerGuru = $totalGuru > 0 ? round($totalSiswa / $totalGuru, 1) : 0;
        $totalRombel = $mainComparison->sum('rombel');
        $avgSiswaPerRombel = $totalRombel > 0 ? round($totalSiswa / $totalRombel, 1) : 0;

        $topSiswaUnit = $mainComparison->sortByDesc('siswa')->first();
        $topGrowthUnit = $ratioTable->sortByDesc('growth_num')->first();

        $summary = [
            'total_unit' => $totalUnits,
            'total_sdm' => $totalSdm,
            'total_siswa' => $totalSiswa,
            'total_mutasi' => $totalMutasi,
            'total_kelulusan' => $totalLulus,
            'total_alumni' => $totalAlumni,
            'avg_siswa_per_guru' => $avgSiswaPerGuru,
            'avg_siswa_per_rombel' => $avgSiswaPerRombel,
            'unit_siswa_terbanyak' => $topSiswaUnit ? "{$topSiswaUnit['unit_name']} ({$topSiswaUnit['siswa']} Siswa)" : '-',
            'unit_growth_tertinggi' => $topGrowthUnit ? "{$topGrowthUnit['unit_name']} ({$topGrowthUnit['pertumbuhan_siswa']})" : '-',
        ];

        // Charts (Limit to 5 units for visual clarity)
        $chartUnits = $mainComparison->take(5);

        // Normalized radar metrics (0 to 100 scale per metric)
        $maxSiswa = max(1, $mainComparison->max('siswa'));
        $maxSdm = max(1, $mainComparison->max('total_sdm'));
        $maxRombel = max(1, $mainComparison->max('rombel'));
        $maxGrowth = max(1, $ratioTable->max('growth_num'));

        $radarChart = $chartUnits->map(function ($row) use ($ratioTable, $maxSiswa, $maxSdm, $maxRombel, $maxGrowth) {
            $rRow = $ratioTable->firstWhere('unit_id', $row['unit_id']);
            return [
                'unit' => $row['unit_code'] ?: $row['unit_name'],
                'siswa_norm' => round(($row['siswa'] / $maxSiswa) * 100),
                'sdm_norm' => round(($row['total_sdm'] / $maxSdm) * 100),
                'rombel_norm' => round(($row['rombel'] / $maxRombel) * 100),
                'growth_norm' => round((($rRow['growth_num'] ?? 0) / $maxGrowth) * 100),
            ];
        });

        $charts = [
            'perbandingan_siswa' => $chartUnits->map(fn ($r) => ['name' => $r['unit_code'] ?: $r['unit_name'], 'siswa' => $r['siswa'], 'siswa_baru' => $r['siswa_baru']]),
            'perbandingan_sdm' => $chartUnits->map(fn ($r) => ['name' => $r['unit_code'] ?: $r['unit_name'], 'guru' => $r['guru'], 'pegawai' => $r['pegawai']]),
            'rasio_siswa_guru' => $chartUnits->map(function ($r) use ($ratioTable) {
                $rt = $ratioTable->firstWhere('unit_id', $r['unit_id']);
                return ['name' => $r['unit_code'] ?: $r['unit_name'], 'rasio' => $rt['siswa_guru_num'] ?? 0];
            }),
            'perbandingan_mutasi' => $chartUnits->map(fn ($r) => ['name' => $r['unit_code'] ?: $r['unit_name'], 'masuk' => $r['mutasi_masuk'], 'keluar' => $r['mutasi_keluar']]),
            'radar_normalized' => $radarChart,
        ];

        $highestMutasiOut = $mainComparison->sortByDesc('mutasi_keluar')->first();

        $insights = [
            'rasio_tertinggi' => $topSiswaUnit ? "Unit dengan populasi siswa terbanyak adalah {$topSiswaUnit['unit_name']} ({$topSiswaUnit['siswa']} siswa)." : '-',
            'growth_tertinggi' => $topGrowthUnit ? "Pertumbuhan siswa baru tertinggi tercatat di {$topGrowthUnit['unit_name']} ({$topGrowthUnit['pertumbuhan_siswa']})." : '-',
            'mutasi_keluar_indicator' => $highestMutasiOut ? "Mutasi keluar tertinggi terjadi pada {$highestMutasiOut['unit_name']} ({$highestMutasiOut['mutasi_keluar']} mutasi keluar)." : '-',
        ];

        return [
            'report' => [
                'title' => 'Laporan Perbandingan Lintas Unit',
                'description' => 'Laporan perbandingan data utama antar-Unit Pendidikan di bawah yayasan.',
                'period' => $period,
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => $summary,
            'charts' => $charts,
            'main_comparison' => $mainComparison,
            'ratio_table' => $ratioTable,
            'comparison_total' => [
                'unit_name' => 'TOTAL KESELURUHAN',
                'guru' => $mainComparison->sum('guru'),
                'pegawai' => $mainComparison->sum('pegawai'),
                'total_sdm' => $totalSdm,
                'siswa' => $totalSiswa,
                'siswa_baru' => $mainComparison->sum('siswa_baru'),
                'mutasi_masuk' => $mainComparison->sum('mutasi_masuk'),
                'mutasi_keluar' => $mainComparison->sum('mutasi_keluar'),
                'lulus' => $totalLulus,
                'alumni' => $totalAlumni,
                'kelas' => $mainComparison->sum('kelas'),
                'rombel' => $totalRombel,
            ],
            'insights' => $insights,
        ];
    }

    private function resolvePeriod(array $filters): array
    {
        return [
            'type' => 'year',
            'label' => 'Periode Aktif ' . date('Y'),
            'start_date' => date('Y-01-01'),
            'end_date' => date('Y-12-31'),
        ];
    }
}
