<?php

namespace App\Services\Reports;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Division;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SdmReportService
{
    public function getReport(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        // Employee Base Query (removes heavy 'teachings.subject' & 'schedules.kelas' eager-loading)
        $employeeQuery = Employee::with(['unit.jenisUnit', 'position', 'division']);

        // Scope filters
        if (!empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $employeeQuery->where('unit_id', $filters['unit_id']);
        }
        if (!empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
            $employeeQuery->whereIn('unit_id', array_filter($filters['unit_ids']));
        }
        if (!empty($filters['jenis_unit_id']) && $filters['jenis_unit_id'] !== 'all') {
            $employeeQuery->whereHas('unit', function ($q) use ($filters) {
                $q->where('jenis_unit_id', $filters['jenis_unit_id']);
            });
        }
        if (!empty($filters['jabatan_id']) && $filters['jabatan_id'] !== 'all') {
            $employeeQuery->where('jabatan_id', $filters['jabatan_id']);
        }
        if (!empty($filters['divisi_id']) && $filters['divisi_id'] !== 'all') {
            $employeeQuery->where('division_id', $filters['divisi_id']);
        }
        if (!empty($filters['status_pegawai']) && $filters['status_pegawai'] !== 'all') {
            $employeeQuery->where('status_pegawai', $filters['status_pegawai']);
        }
        if (!empty($filters['status_aktif']) && $filters['status_aktif'] !== 'all') {
            if ($filters['status_aktif'] === 'aktif') {
                $employeeQuery->where(function ($q) {
                    $q->whereRaw('LOWER(status) = ?', ['aktif'])
                      ->orWhereRaw('LOWER(status) = ?', ['active'])
                      ->orWhere('status', '1')
                      ->orWhereNull('status');
                });
            } else {
                $employeeQuery->where(function ($q) {
                    $q->whereRaw('LOWER(status) != ?', ['aktif'])
                      ->whereRaw('LOWER(status) != ?', ['active'])
                      ->where('status', '!=', '1');
                });
            }
        }
        if (!empty($filters['jenis_kelamin']) && $filters['jenis_kelamin'] !== 'all') {
            $employeeQuery->where('jenis_kelamin', $filters['jenis_kelamin']);
        }
        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $teacherScope = function ($q) use ($like) {
            $q->whereHas('teacher')
              ->orWhereHas('teachings')
              ->orWhereHas('position', function ($p) use ($like) {
                  $p->where('name', $like, '%Guru%')
                    ->orWhere('name', $like, '%Pendidik%')
                    ->orWhereIn('level_jabatan', [8, 9]);
              });
        };

        if (!empty($filters['jenis_sdm']) && $filters['jenis_sdm'] !== 'all') {
            if ($filters['jenis_sdm'] === 'guru') {
                $employeeQuery->where($teacherScope);
            } elseif ($filters['jenis_sdm'] === 'non-guru') {
                $employeeQuery->whereNot($teacherScope);
            }
        }
        if (!empty($filters['search'])) {
            $search = (string) $filters['search'];
            $employeeQuery->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('niy', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        // Clone for aggregations
        $totalSdm = (clone $employeeQuery)->count();

        // Guru vs Pegawai Non-Guru
        $guruQuery = (clone $employeeQuery)->where($teacherScope);
        $totalGuru = $guruQuery->count();
        $totalNonGuru = max(0, $totalSdm - $totalGuru);

        // Status Aktif / Nonaktif (Case-insensitive for PostgreSQL)
        $aktifQuery = (clone $employeeQuery)->where(function ($q) {
            $q->whereRaw('LOWER(status) = ?', ['aktif'])
              ->orWhereRaw('LOWER(status) = ?', ['active'])
              ->orWhere('status', '1')
              ->orWhereNull('status');
        });
        $totalAktif = $aktifQuery->count();
        $totalNonaktif = max(0, $totalSdm - $totalAktif);

        // Kepegawaian Detail (Case-insensitive)
        $guruTetap = (clone $guruQuery)->where(function ($q) {
            $q->whereRaw('LOWER(status_pegawai) LIKE ?', ['%tetap%'])
              ->orWhereRaw('LOWER(status_pegawai) LIKE ?', ['%pns%']);
        })->count();
        $guruTidakTetap = max(0, $totalGuru - $guruTetap);

        $nonGuruQuery = (clone $employeeQuery)->whereNot($teacherScope);
        $pegawaiTetap = (clone $nonGuruQuery)->where(function ($q) {
            $q->whereRaw('LOWER(status_pegawai) LIKE ?', ['%tetap%'])
              ->orWhereRaw('LOWER(status_pegawai) LIKE ?', ['%pns%']);
        })->count();
        $pegawaiTidakTetap = max(0, $totalNonGuru - $pegawaiTetap);

        // Gender (Case-insensitive)
        $lakiLaki = (clone $employeeQuery)->where(function ($q) {
            $q->whereRaw('LOWER(jenis_kelamin) LIKE ?', ['l%'])
              ->orWhereRaw('LOWER(jenis_kelamin) LIKE ?', ['male%']);
        })->count();
        $perempuan = (clone $employeeQuery)->where(function ($q) {
            $q->whereRaw('LOWER(jenis_kelamin) LIKE ?', ['p%'])
              ->orWhereRaw('LOWER(jenis_kelamin) LIKE ?', ['female%'])
              ->orWhereRaw('LOWER(jenis_kelamin) LIKE ?', ['perempuan%']);
        })->count();

        // SDM Baru / Keluar within selected period
        $sdmBaru = 0;
        $sdmKeluar = 0;
        if (!empty($period['start_date']) && !empty($period['end_date'])) {
            $sdmBaru = (clone $employeeQuery)->whereBetween('tanggal_masuk', [$period['start_date'], $period['end_date']])->count();
            $sdmKeluar = (clone $employeeQuery)->whereBetween('tanggal_keluar', [$period['start_date'], $period['end_date']])->count();
        }

        // Summary KPI structure
        $summary = [
            'total_sdm' => $totalSdm,
            'total_guru' => $totalGuru,
            'total_non_guru' => $totalNonGuru,
            'sdm_aktif' => $totalAktif,
            'sdm_nonaktif' => $totalNonaktif,
            'guru_tetap' => $guruTetap,
            'guru_tidak_tetap' => $guruTidakTetap,
            'pegawai_tetap' => $pegawaiTetap,
            'pegawai_tidak_tetap' => $pegawaiTidakTetap,
            'laki_laki' => $lakiLaki,
            'perempuan' => $perempuan,
            'sdm_baru' => $sdmBaru,
            'sdm_keluar' => $sdmKeluar,
        ];

        // 2. Single-Query Pre-Aggregations for Unit Recaps & Charts (Eliminates N+1 loop)
        $units = EducationUnit::with(['jenisUnit'])->get();

        $statsByUnit = Employee::query()
            ->selectRaw('
                unit_id,
                COUNT(*) as total_sdm,
                SUM(CASE WHEN LOWER(status) IN ("aktif", "active", "1") OR status IS NULL THEN 1 ELSE 0 END) as sdm_aktif,
                SUM(CASE WHEN LOWER(jenis_kelamin) LIKE "l%" OR LOWER(jenis_kelamin) LIKE "male%" THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN (SELECT 1 FROM teachers WHERE teachers.employee_id = employees.id LIMIT 1) IS NOT NULL
                          OR (SELECT 1 FROM employee_teachings WHERE employee_teachings.employee_id = employees.id LIMIT 1) IS NOT NULL
                          OR (SELECT 1 FROM positions WHERE positions.id = employees.jabatan_id AND (positions.name ' . $like . ' "%Guru%" OR positions.name ' . $like . ' "%Pendidik%" OR positions.level_jabatan IN (8, 9)) LIMIT 1) IS NOT NULL
                    THEN 1 ELSE 0 END) as guru_count
            ')
            ->groupBy('unit_id')
            ->get()
            ->keyBy('unit_id');

        $chartUnitDist = $units->map(function ($u) use ($statsByUnit) {
            $st = $statsByUnit->get($u->id);
            $totalSub = (int) ($st->total_sdm ?? 0);
            $guru = (int) ($st->guru_count ?? 0);

            return [
                'name' => $u->code ?: $u->name,
                'full_name' => $u->name,
                'guru' => $guru,
                'non_guru' => max(0, $totalSub - $guru),
                'total' => $totalSub,
            ];
        });

        // Status kepegawaian chart via SQL Grouping
        $statusCounts = Employee::query()
            ->selectRaw('COALESCE(status_pegawai, "Lainnya") as name, COUNT(*) as value')
            ->groupBy('status_pegawai')
            ->get();

        // Gender chart
        $chartGender = [
            ['name' => 'Laki-Laki', 'value' => $lakiLaki],
            ['name' => 'Perempuan', 'value' => $perempuan],
        ];

        // Position distribution chart
        $chartPositions = Position::withCount('employees')->orderBy('employees_count', 'desc')->take(6)->get()->map(function ($pos) {
            return [
                'name' => $pos->name,
                'value' => $pos->employees_count,
            ];
        });

        $charts = [
            'unit_distribution' => $chartUnitDist,
            'status_kepegawaian' => $statusCounts,
            'gender' => $chartGender,
            'positions' => $chartPositions,
        ];

        // 3. Rekap Per Unit (Constructed from single pre-computed SQL aggregations)
        $unitRecaps = $units->map(function ($u) use ($statsByUnit) {
            $st = $statsByUnit->get($u->id);
            $totalSub = (int) ($st->total_sdm ?? 0);
            $gCount = (int) ($st->guru_count ?? 0);
            $aCount = (int) ($st->sdm_aktif ?? 0);
            $lCount = (int) ($st->male_count ?? 0);

            return [
                'unit_id' => $u->id,
                'unit_code' => $u->code,
                'unit_name' => $u->name,
                'guru' => $gCount,
                'non_guru' => max(0, $totalSub - $gCount),
                'total_sdm' => $totalSub,
                'aktif' => $aCount,
                'nonaktif' => max(0, $totalSub - $aCount),
                'laki_laki' => $lCount,
                'perempuan' => max(0, $totalSub - $lCount),
                'percentage' => $totalSdm > 0 ? round(($totalSub / $totalSdm) * 100, 1) : 0,
            ];
        });

        // Calculate total recap row
        $recapTotal = [
            'unit_name' => 'TOTAL KESELURUHAN',
            'guru' => $unitRecaps->sum('guru'),
            'non_guru' => $unitRecaps->sum('non_guru'),
            'total_sdm' => $unitRecaps->sum('total_sdm'),
            'aktif' => $unitRecaps->sum('aktif'),
            'nonaktif' => $unitRecaps->sum('nonaktif'),
            'laki_laki' => $unitRecaps->sum('laki_laki'),
            'perempuan' => $unitRecaps->sum('perempuan'),
            'percentage' => 100,
        ];

        // 4. Paginated Detailed Data
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);
        $sortBy = $filters['sort_by'] ?? 'nama_lengkap';
        $sortDir = strtolower($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['nama_lengkap', 'niy', 'created_at', 'tanggal_masuk', 'status'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'nama_lengkap';
        }

        $paginated = (clone $employeeQuery)
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage, ['*'], 'page', $page);

        $formattedDetails = collect($paginated->items())->map(function ($emp) use ($period) {
            $posName = $emp->position->name ?? '';
            $isGuru = $emp->teacher !== null
                || $emp->teachings->isNotEmpty()
                || str_contains(strtolower($posName), 'guru')
                || str_contains(strtolower($posName), 'pendidik')
                || in_array($emp->position?->level_jabatan, [8, 9]);

            $isBaru = false;
            if (!empty($period['start_date']) && !empty($period['end_date']) && $emp->tanggal_masuk) {
                $isBaru = $emp->tanggal_masuk->between($period['start_date'], $period['end_date']);
            }

            return [
                'id' => $emp->id,
                'niy' => $emp->niy ?? $emp->nik ?? '-',
                'nama' => $emp->nama_lengkap,
                'jenis_sdm' => $isGuru ? 'Guru' : 'Pegawai Non-Guru',
                'is_guru' => $isGuru,
                'is_baru' => $isBaru,
                'unit' => $emp->unit->name ?? '-',
                'unit_code' => $emp->unit->code ?? '-',
                'jabatan' => $emp->position->name ?? '-',
                'divisi_mapel' => $isGuru ? ($emp->teachings->pluck('subject.name')->implode(', ') ?: ($emp->division->name ?? '-')) : ($emp->division->name ?? '-'),
                'status_kepegawaian' => $emp->status_pegawai ?? 'Tetap',
                'jenis_kelamin' => $emp->jenis_kelamin ?? 'L',
                'tanggal_masuk' => $emp->tanggal_masuk ? $emp->tanggal_masuk->format('d M Y') : '-',
                'status' => ucfirst($emp->status ?? 'aktif'),
            ];
        });

        // 5. Rule-based Calculated Insights
        $topUnit = $unitRecaps->sortByDesc('total_sdm')->first();
        $topGuruUnit = $unitRecaps->sortByDesc('guru')->first();

        $insights = [
            'unit_terbanyak' => $topUnit ? "{$topUnit['unit_name']} ({$topUnit['total_sdm']} SDM)" : 'Belum ada data',
            'guru_terbanyak' => $topGuruUnit ? "{$topGuruUnit['unit_name']} ({$topGuruUnit['guru']} Guru)" : 'Belum ada data',
            'rasio_gender' => $perempuan > 0 ? '1 : ' . round($lakiLaki / max(1, $perempuan), 2) : '100% Laki-Laki',
        ];

        return [
            'report' => [
                'title' => 'Laporan Sumber Daya Manusia',
                'description' => 'Laporan tenaga pendidik dan tenaga kependidikan pada seluruh Unit Pendidikan.',
                'period' => $period,
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => $summary,
            'charts' => $charts,
            'unit_recaps' => $unitRecaps,
            'unit_recaps_total' => $recapTotal,
            'details' => $formattedDetails,
            'insights' => $insights,
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
        $emp = Employee::with(['unit', 'position', 'division', 'teacherBridge', 'teachings.subject', 'schedules.kelas'])->findOrFail($id);
        $posName = $emp->position->name ?? '';
        $isGuru = $emp->teacherBridge !== null
            || $emp->teachings->isNotEmpty()
            || str_contains(strtolower($posName), 'guru')
            || str_contains(strtolower($posName), 'pendidik')
            || in_array($emp->position?->level_jabatan, [8, 9]);

        $masaKerja = '-';
        if ($emp->tanggal_masuk) {
            $years = Carbon::now()->diffInYears($emp->tanggal_masuk);
            $months = Carbon::now()->diffInMonths($emp->tanggal_masuk) % 12;
            $masaKerja = "{$years} Tahun {$months} Bulan";
        }

        $teachingClasses = $emp->schedules->pluck('kelas.nama_kelas')->unique()->filter()->implode(', ');
        $isWaliKelas = \App\Models\Kelas::where('wali_kelas_id', $emp->id)->exists();

        return [
            'id' => $emp->id,
            'foto' => $emp->foto,
            'niy' => $emp->niy ?? '-',
            'nik' => $emp->nik ?? '-',
            'nama' => $emp->nama_lengkap,
            'jenis_kelamin' => in_array(strtolower($emp->jenis_kelamin ?? 'l'), ['l', 'laki-laki', 'male']) ? 'Laki-Laki' : 'Perempuan',
            'unit' => $emp->unit->name ?? '-',
            'jabatan' => $emp->position->name ?? '-',
            'divisi' => $emp->division->name ?? '-',
            'status_kepegawaian' => $emp->status_pegawai ?? 'Tetap',
            'tanggal_masuk' => $emp->tanggal_masuk ? $emp->tanggal_masuk->format('d F Y') : '-',
            'masa_kerja' => $masaKerja,
            'no_hp' => $emp->no_hp ?? '-',
            'email' => $emp->email ?? '-',
            'status' => ucfirst($emp->status ?? 'aktif'),
            'is_guru' => $isGuru,
            'mata_pelajaran' => $emp->teachings->pluck('subject.name')->implode(', ') ?: '-',
            'kelas_diajar' => $teachingClasses ?: '-',
            'status_wali_kelas' => $isWaliKelas ? 'Wali Kelas' : 'Bukan Wali Kelas',
            'beban_mengajar' => $emp->schedules->count() . ' Sesi / Jam',
        ];
    }

    private function resolvePeriod(array $filters): array
    {
        $periodType = $filters['period'] ?? 'year';
        $startDate = $filters['tanggal_mulai'] ?? null;
        $endDate = $filters['tanggal_selesai'] ?? null;

        if ($periodType === 'custom' && $startDate && $endDate) {
            return [
                'type' => 'custom',
                'label' => Carbon::parse($startDate)->format('d M Y') . ' - ' . Carbon::parse($endDate)->format('d M Y'),
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        }

        $now = Carbon::now();
        switch ($periodType) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $label = 'Hari Ini (' . $now->format('d M Y') . ')';
                break;
            case 'week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $label = 'Minggu Ini';
                break;
            case 'month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $label = 'Bulan Ini (' . $now->format('F Y') . ')';
                break;
            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                $label = 'Bulan Lalu (' . $now->copy()->subMonth()->format('F Y') . ')';
                break;
            default:
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                $label = 'Tahun Ini (' . $now->format('Y') . ')';
                break;
        }

        return [
            'type' => $periodType,
            'label' => $label,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }
}
