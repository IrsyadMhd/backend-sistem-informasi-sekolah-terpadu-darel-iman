<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\JenisUnitPendidikan;
use App\Models\Kelas;
use App\Models\PengumumanSekolah;
use App\Models\Position;
use App\Models\RekapPrestasiSiswa;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FoundationDashboardService
{
    /**
     * Get aggregate overview data for Foundation Dashboard.
     */
    /**
     * Invalidate dashboard cache for a specific unit scope.
     * Call this after data changes (student create/update, attendance input, etc.)
     */
    public static function invalidateCache(?string $unitId = null): void
    {
        $month = now()->format('Y-m');
        $unitKey = $unitId ?? 'all';
        Cache::forget("foundation_dashboard:{$unitKey}:{$month}");
        // Also clear the 'all' key if a specific unit was changed
        if ($unitId) {
            Cache::forget("foundation_dashboard:all:{$month}");
        }
    }

    public function getDashboardOverview(array $filters = []): array
    {
        $unitKey   = $filters['unit_id'] ?? 'all';
        $month     = now()->format('Y-m');
        $cacheKey  = "foundation_dashboard:{$unitKey}:{$month}";
        $cacheTtl  = 300; // 5 minutes — safe for KPI dashboards

        return Cache::remember($cacheKey, $cacheTtl, fn () => $this->computeDashboardOverview($filters));
    }

    private function computeDashboardOverview(array $filters = []): array
    {
        $unitQuery = EducationUnit::query();
        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $unitQuery->where('id', $filters['unit_id']);
        }

        $activeUnits = $unitQuery->get();
        $unitIds = $activeUnits->pluck('id')->toArray();

        // 1. KPI Aggregation
        $startOfMonth = now()->startOfMonth();

        // Unit Pendidikan & Growth
        $totalUnits = EducationUnit::count();
        $totalActiveUnits = EducationUnit::where('is_active', true)->count();
        $growthUnit = EducationUnit::where('is_active', true)->where('created_at', '>=', $startOfMonth)->count();

        // Pegawai & Growth
        $employeeQuery = Employee::query();
        if (! empty($unitIds)) {
            $employeeQuery->whereIn('unit_id', $unitIds);
        }
        $totalPegawai = (clone $employeeQuery)->count();
        $totalPegawaiAktif = (clone $employeeQuery)->where(function ($q) {
            $q->where('status', 'aktif')->orWhere('status', 'Active')->orWhereNull('status');
        })->count();
        $growthPegawai = (clone $employeeQuery)->where(function ($q) {
            $q->where('status', 'aktif')->orWhere('status', 'Active')->orWhereNull('status');
        })->where('created_at', '>=', $startOfMonth)->count();

        // Guru vs Tendik & Growth
        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $guruQuery = (clone $employeeQuery)->where(function ($q) {
            $q->whereIn(DB::raw('LOWER(status)'), ['aktif', 'active'])->orWhereNull('status');
        })->where(function ($q) use ($like) {
            $q->whereHas('teacher')
              ->orWhereHas('teachings')
              ->orWhereHas('position', function ($p) use ($like) {
                  $p->where('name', $like, '%Guru%')
                    ->orWhere('name', $like, '%Pendidik%')
                    ->orWhereIn('level_jabatan', [8, 9]);
              });
        });
        $totalGuru = (clone $guruQuery)->count();
        $growthGuru = (clone $guruQuery)->where('created_at', '>=', $startOfMonth)->count();

        $totalTendik = max(0, $totalPegawai - $totalGuru);

        // Siswa & Growth
        $studentQuery = Student::query();
        if (! empty($unitIds)) {
            $studentQuery->whereIn('unit_id', $unitIds);
        }
        $totalSiswaAktif = (clone $studentQuery)->where('is_active', true)->count();
        $growthSiswa = (clone $studentQuery)->where('is_active', true)->where('created_at', '>=', $startOfMonth)->count();
        $totalLakiLaki = (clone $studentQuery)->where('is_active', true)->where('gender', 'male')->count();
        $totalPerempuan = (clone $studentQuery)->where('is_active', true)->where('gender', 'female')->count();

        // Siswa Baru tahun berjalan
        $activeYear = AcademicYear::where('is_active', true)->first();
        $currentYearNum = $activeYear ? (int) substr($activeYear->name, 0, 4) : (int) date('Y');

        $siswaBaru = (clone $studentQuery)->where(function ($q) use ($currentYearNum) {
            $q->where('tahun_masuk', $currentYearNum)
              ->orWhere('metadata->status_pendaftaran', 'baru')
              ->orWhere('metadata->is_new_student', true);
        })->count();

        // Mutasi
        $mutasiMasuk = (clone $studentQuery)->where('metadata->mutasi_type', 'masuk')->count();
        $mutasiKeluar = (clone $studentQuery)->where('metadata->mutasi_type', 'keluar')->count();
        $siswaBerhenti = (clone $studentQuery)->where('metadata->status_siswa', 'berhenti')->count();

        // Kelulusan & Alumni
        $siswaLulus = (clone $studentQuery)->where('is_active', false)->where('metadata->status_siswa', 'lulus')->count();
        $menungguAlumni = (clone $studentQuery)->where('is_active', false)->whereNull('metadata->is_alumni')->count();
        $alumniQuery = (clone $studentQuery)->where(function ($q) {
            $q->where('is_active', false)->orWhere('metadata->is_alumni', true)->orWhere('metadata->status_siswa', 'alumni');
        });
        $totalAlumni = (clone $alumniQuery)->count();
        $growthAlumni = (clone $alumniQuery)->where(function ($q) use ($startOfMonth) {
            $q->where('created_at', '>=', $startOfMonth)->orWhere('updated_at', '>=', $startOfMonth);
        })->count();

        // Orang Tua & Growth (scope ke unit terpilih bila filter aktif)
        $totalOrtu = \App\Models\ParentModel::count();
        $growthOrtu = \App\Models\ParentModel::where('created_at', '>=', $startOfMonth)->count();
        if ($totalOrtu === 0) {
            $totalOrtu = \App\Models\User::whereHas('roles', fn ($r) => $r->whereIn('name', ['Orang Tua', 'Orangtua', 'Wali Murid']))->count();
            $growthOrtu = \App\Models\User::whereHas('roles', fn ($r) => $r->whereIn('name', ['Orang Tua', 'Orangtua', 'Wali Murid']))
                ->where('created_at', '>=', $startOfMonth)->count();
        }
        if (! empty($unitIds)) {
            $totalOrtu = \App\Models\ParentModel::whereHas('students', fn ($q) => $q->whereIn('unit_id', $unitIds))->count();
            $growthOrtu = \App\Models\ParentModel::whereHas('students', fn ($q) => $q->whereIn('unit_id', $unitIds))
                ->where('created_at', '>=', $startOfMonth)->count();
        }

        // Kelas & Growth
        $kelasQuery = Kelas::query();
        if ($activeYear) {
            $kelasQuery->where(function ($q) use ($activeYear) {
                $q->where('tahun_ajaran_id', $activeYear->id)->orWhereNull('tahun_ajaran_id');
            });
        }
        $totalKelas = (clone $kelasQuery)->count();
        $growthKelas = (clone $kelasQuery)->where('created_at', '>=', $startOfMonth)->count();

        // Rombel & Growth
        $activeSemester = Semester::where('is_active', true)->first();
        $rombelQuery = Kelas::query();
        if ($activeYear) {
            $rombelQuery->where(function ($q) use ($activeYear) {
                $q->where('tahun_ajaran_id', $activeYear->id)->orWhereNull('tahun_ajaran_id');
            });
        }
        if ($activeSemester) {
            $rombelQuery->where(function ($q) use ($activeSemester) {
                $q->where('semester_id', $activeSemester->id)->orWhereNull('semester_id');
            });
        }
        $totalRombel = (clone $rombelQuery)->count();
        if ($totalRombel === 0) {
            $totalRombel = $totalKelas;
        }
        $growthRombel = (clone $rombelQuery)->where('created_at', '>=', $startOfMonth)->count();

        // Informasi Baru
        $totalPengumuman = PengumumanSekolah::where('status_aktif', true)->count();

        // 2. Chart Data: SDM Distribution per Unit
        $sdmDistribution = EducationUnit::query()
            ->when(! empty($filters['unit_id']) && $filters['unit_id'] !== 'all', fn ($query) => $query->whereKey($filters['unit_id']))
            ->withCount([
            'employees as total_pegawai',
            'employees as total_guru' => function ($q) {
                $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
                $q->where(function ($sq) use ($like) {
                    $sq->whereHas('teacher')
                      ->orWhereHas('teachings')
                      ->orWhereHas('position', function ($p) use ($like) {
                          $p->where('name', $like, '%Guru%')
                            ->orWhere('name', $like, '%Pendidik%')
                            ->orWhereIn('level_jabatan', [8, 9]);
                      });
                });
            },
        ])->get()->map(function ($unit) {
            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'pegawai' => $unit->total_pegawai ?? 0,
                'guru' => $unit->total_guru ?? 0,
                'tendik' => max(0, ($unit->total_pegawai ?? 0) - ($unit->total_guru ?? 0)),
            ];
        });

        // 3. Chart Data: Pergerakan siswa berdasarkan catatan yang benar-benar tersimpan.
        $studentMovement = [];
        if (DB::getDriverName() === 'pgsql') {
            $studentMovement = (clone $studentQuery)
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->selectRaw("to_char(date_trunc('month', created_at), 'Mon') as month")
                ->selectRaw("count(*) filter (where metadata->>'is_new_student' = 'true' or metadata->>'status_pendaftaran' = 'baru') as siswa_baru")
                ->selectRaw("count(*) filter (where metadata->>'mutasi_type' = 'masuk') as masuk")
                ->selectRaw("count(*) filter (where metadata->>'mutasi_type' = 'keluar') as keluar")
                ->selectRaw("count(*) filter (where is_active = false and metadata->>'status_siswa' = 'lulus') as lulus")
                ->groupByRaw("date_trunc('month', created_at)")
                ->orderByRaw("date_trunc('month', created_at)")
                ->get()
                ->map(fn ($item) => [
                    'month' => $item->month,
                    'siswa_baru' => (int) $item->siswa_baru,
                    'masuk' => (int) $item->masuk,
                    'keluar' => (int) $item->keluar,
                    'lulus' => (int) $item->lulus,
                ])
                ->values()
                ->all();
        }

        // 4. Prestasi Siswa Distribution (Dynamic from DB)
        //    `rekap_prestasi_siswas` tidak punya kolom `kategori`; kategori
        //    kanonik adalah `jenis_prestasi` (akademik / non_akademik).
        $totalPrestasi = \App\Models\RekapPrestasiSiswa::count();
        $akademikLike = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $prestasiDistribution = [
            ['name' => 'Akademik', 'value' => \App\Models\RekapPrestasiSiswa::where('jenis_prestasi', $akademikLike, '%akademik%')->count(), 'color' => '#10B981'],
            ['name' => 'Tahfiz', 'value' => 0, 'color' => '#0284C7'],
            ['name' => 'Olahraga', 'value' => 0, 'color' => '#F59E0B'],
            ['name' => 'Seni', 'value' => 0, 'color' => '#8B5CF6'],
            ['name' => 'Lainnya', 'value' => \App\Models\RekapPrestasiSiswa::where('jenis_prestasi', 'not '.$akademikLike, '%akademik%')->count(), 'color' => '#EF4444'],
        ];
        $sumPrestasiValues = array_sum(array_column($prestasiDistribution, 'value'));
        if ($sumPrestasiValues > 0) {
            foreach ($prestasiDistribution as &$pItem) {
                $pItem['percent'] = round(($pItem['value'] / $sumPrestasiValues) * 100) . '%';
            }
        } else {
            foreach ($prestasiDistribution as &$pItem) {
                $pItem['percent'] = '0%';
            }
        }
        unset($pItem);

        // 5. Attendance & Academic Monitoring
        //    `attendance_scan_logs` memakai `result_status` (bukan `status`)
        //    dan hanya merekam pemindai siswa (tidak ada `role_type`).
        $todayScans = \App\Models\AttendanceScanLog::whereDate('scanned_at', now())->count();
        $todayLateScans = \App\Models\AttendanceScanLog::whereDate('scanned_at', now())->where('result_status', 'late')->count();
        $todayAbsentScans = \App\Models\AttendanceScanLog::whereDate('scanned_at', now())->whereIn('result_status', ['absent', 'alpa'])->count();

        $teacherAttendancePct = 100; // scan_logs tidak mencatat kehadiran guru
        $studentAttendancePct = $totalSiswaAktif > 0 ? min(100, round(($totalSiswaAktif - $todayAbsentScans) / $totalSiswaAktif * 100)) : 100;

        $monitoringAkademik = [
            'kehadiran_guru' => $teacherAttendancePct,
            'kehadiran_siswa' => $studentAttendancePct,
            'input_nilai' => \App\Models\StudentGrade::count() > 0 ? 100 : 0,
            'input_tahfiz' => \App\Models\TahfizhDailyLog::count() > 0 ? 100 : 0,
            'input_mutabaah' => \App\Models\MutabaahDailyHeader::count() > 0 ? 100 : 0,
            'terlambat_hari_ini' => $todayLateScans,
            'tidak_hadir_hari_ini' => $todayAbsentScans,
        ];

        // 6. Monitoring Ibadah — single aggregate query per category (avoid duplicate COUNTs)
        $monitoringIbadah = $this->computeMonitoringIbadah();

        // 7. Unit Rankings by active student count
        $unitSummaries = $this->getUnitSummaries($filters);
        $unitRankings = collect($unitSummaries)->sortByDesc('siswa_aktif_count')->values()->map(function ($u, $idx) {
            return [
                'rank' => $idx + 1,
                'name' => $u['name'],
                'score' => $u['siswa_aktif_count'] > 0 ? 100 : 0,
            ];
        })->toArray();

        // 8. Agenda Yayasan & Recent Activities
        $recentInformation = PengumumanSekolah::where('status_aktif', true)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'judul' => $item->judul_pengumuman,
                    'isi' => $item->isi_pengumuman,
                    'tanggal' => $item->created_at->format('d M Y'),
                    'jam' => $item->created_at->format('H:i'),
                    'prioritas' => $item->prioritas,
                ];
            });

        $recentActivities = \App\Models\AttendanceScanLog::with(['student'])
            ->latest()
            ->take(6)
            ->get()
            ->map(function ($log) {
                $name = $log->student->nama_lengkap ?? 'User';
                return [
                    'id' => $log->id,
                    'title' => "{$name} scanned presensi",
                    'subtitle' => "Status: {$log->result_status}",
                    'time' => $log->scanned_at ? \Carbon\Carbon::parse($log->scanned_at)->format('H:i') . ' WIB' : 'Baru saja',
                ];
            });

        return [
            'kpis' => [
                'total_unit' => $totalActiveUnits,
                'growth_unit' => $growthUnit,
                'total_unit_aktif' => $totalActiveUnits,
                'total_pegawai' => $totalPegawai,
                'growth_pegawai' => $growthPegawai,
                'total_pegawai_aktif' => $totalPegawaiAktif,
                'total_guru' => $totalGuru,
                'growth_guru' => $growthGuru,
                'total_tendik' => $totalTendik,
                'total_siswa_aktif' => $totalSiswaAktif,
                'growth_siswa' => $growthSiswa,
                'total_ortu' => $totalOrtu,
                'growth_ortu' => $growthOrtu,
                'total_kelas' => $totalKelas,
                'growth_kelas' => $growthKelas,
                'total_rombel' => $totalRombel,
                'growth_rombel' => $growthRombel,
                'siswa_laki_laki' => $totalLakiLaki,
                'siswa_perempuan' => $totalPerempuan,
                'siswa_baru' => $siswaBaru,
                'mutasi_masuk' => $mutasiMasuk,
                'mutasi_keluar' => $mutasiKeluar,
                'siswa_berhenti' => $siswaBerhenti,
                'siswa_lulus' => $siswaLulus,
                'menunggu_alumni' => $menungguAlumni,
                'total_alumni' => $totalAlumni,
                'growth_alumni' => $growthAlumni,
                'informasi_baru' => $totalPengumuman,
                'total_prestasi' => $totalPrestasi,
                'guru' => ['total' => $totalGuru, 'growth' => $growthGuru],
                'pegawai' => ['total' => $totalPegawai, 'growth' => $growthPegawai],
                'siswa' => ['total' => $totalSiswaAktif, 'growth' => $growthSiswa],
                'orang_tua' => ['total' => $totalOrtu, 'growth' => $growthOrtu],
                'alumni' => ['total' => $totalAlumni, 'growth' => $growthAlumni],
                'kelas' => ['total' => $totalKelas, 'growth' => $growthKelas],
                'rombel' => ['total' => $totalRombel, 'growth' => $growthRombel],
                'unit_pendidikan' => ['total' => $totalActiveUnits, 'growth' => $growthUnit],
            ],
            'charts' => [
                'sdm_distribution' => $sdmDistribution,
                'student_movement' => $studentMovement,
                'prestasi_distribution' => $prestasiDistribution,
                'tahfizh_target_progress' => [],
                'attendance_trend' => [],
            ],
            'monitoring_akademik' => $monitoringAkademik,
            'monitoring_ibadah' => $monitoringIbadah,
            'unit_rankings' => $unitRankings,
            'agenda_yayasan' => $recentInformation,
            'recent_activities' => $recentActivities,
            'unit_summaries' => $unitSummaries,
            'recent_information' => $recentInformation,
            'active_academic_year' => $activeYear,
            'active_semester' => Semester::where('is_active', true)->first(),
        ];
    }

    /**
     * Compute monitoring ibadah percentages using single aggregate queries per category.
     * Eliminates the duplicate COUNT pattern: count(completed)/count(total) called twice each.
     */
    private function computeMonitoringIbadah(): array
    {
        $categories = ['Shalat', 'Tilawah', 'Murajaah'];
        $result     = [];

        // Kategori mutabaah diambil via relasi detail -> agenda_item -> mutabaah_categories.
        // `mutabaah_daily_details` tidak punya kolom `category_name`/`is_completed`;
        // kelengkapan = status_value IN ('good','less').
        $rows = DB::table('mutabaah_daily_details as d')
            ->join('mutabaah_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')
            ->join('mutabaah_categories as c', 'c.id', '=', 'a.category_id')
            ->whereIn('c.name', $categories)
            ->selectRaw("c.name as category_name,
                COUNT(*) as total,
                SUM(CASE WHEN d.status_value IN ('good','less') THEN 1 ELSE 0 END) as completed")
            ->groupBy('c.name')
            ->get()
            ->keyBy('category_name');

        foreach ($categories as $cat) {
            $row                 = $rows->get($cat);
            $total               = (int) ($row->total ?? 0);
            $completed           = (int) ($row->completed ?? 0);
            $result[strtolower($cat)] = $total > 0 ? round($completed / $total * 100) : 0;
        }

        // Mutabaah header verified ratio
        $mRow = DB::table('mutabaah_daily_headers')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = \'verified\' THEN 1 ELSE 0 END) as verified')
            ->first();
        $mTotal = (int) ($mRow->total ?? 0);
        $result['mutabaah'] = $mTotal > 0 ? round(((int) ($mRow->verified ?? 0)) / $mTotal * 100) : 0;

        return $result;
    }

    /**
     * Get aggregate statistics per education unit.
     *
     * N+1 FIX (Session 16): Replaced per-unit loop queries with batch withCount()
     * and a single keyed student/employee aggregate query.
     * Before: ~8 queries × N units (e.g., 40 queries for 5 units)
     * After:  ~5 queries total (withCount + batch aggregates)
     */
    public function getUnitSummaries(array $filters = []): array
    {
        $units = EducationUnit::query()
            ->with(['jenisUnit'])
            ->withCount([
                'employees as pegawai_count',
                'employees as guru_count' => function ($q) {
                    $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
                    $q->where(function ($sq) use ($like) {
                        $sq->whereHas('teacher')
                          ->orWhereHas('teachings')
                          ->orWhereHas('position', fn ($p) => $p->where('name', $like, '%Guru%')
                              ->orWhere('name', $like, '%Pendidik%')
                              ->orWhereIn('level_jabatan', [8, 9]));
                    });
                },
                'students as siswa_aktif_count' => fn ($q) => $q->where('is_active', true),
                'classes as kelas_count',
            ])
            ->when(! empty($filters['unit_id']) && $filters['unit_id'] !== 'all', fn ($q) => $q->whereKey($filters['unit_id']))
            ->when(! empty($filters['jenis_unit_id']), fn ($q) => $q->where('jenis_unit_id', $filters['jenis_unit_id']))
            ->when(isset($filters['status']) && $filters['status'] !== 'all', fn ($q) => $q->where('is_active', $filters['status'] === 'aktif'))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $driver = DB::getDriverName();
                if ($driver === 'pgsql') {
                    $q->where('name', 'ilike', '%' . $filters['search'] . '%');
                } else {
                    $q->where('name', 'like', '%' . $filters['search'] . '%');
                }
            })
            ->get();

        if ($units->isEmpty()) {
            return [];
        }

        $unitIds = $units->pluck('id')->toArray();

        // Batch query: kepala sekolah per unit (one query instead of N)
        $kepalaMap = Employee::with('position')
            ->whereIn('unit_id', $unitIds)
            ->whereHas('position', fn ($p) => $p->where('name', 'like', '%Kepala%'))
            ->get(['id', 'unit_id', 'nama_lengkap'])
            ->keyBy('unit_id');

        // Batch query: student counts per unit
        $currentYear = (int) date('Y');
        $studentStats = DB::table('students')
            ->whereIn('unit_id', $unitIds)
            ->whereNull('deleted_at')
            ->selectRaw("
                unit_id,
                SUM(CASE WHEN tahun_masuk = {$currentYear} THEN 1 ELSE 0 END) as siswa_baru_count
            ")
            ->groupBy('unit_id')
            ->get()
            ->keyBy('unit_id');

        return $units->map(function ($unit) use ($kepalaMap, $studentStats) {
            $kepala         = $kepalaMap->get($unit->id);
            $stats          = $studentStats->get($unit->id);
            $pegawaiCount   = (int) ($unit->pegawai_count ?? 0);
            $guruCount      = (int) ($unit->guru_count ?? 0);
            $kelasCount     = (int) ($unit->kelas_count ?? 0);

            return [
                'id'              => $unit->id,
                'name'            => $unit->name,
                'code'            => $unit->code,
                'jenis_unit'      => $unit->jenisUnit->nama_jenis ?? $unit->level ?? 'Umum',
                'level'           => $unit->level ?? '-',
                'location'        => $unit->description ?? 'Padang',
                'is_active'       => (bool) $unit->is_active,
                'kepala_sekolah'  => $kepala?->nama_lengkap ?? 'Belum Ditentukan',
                'pegawai_count'   => $pegawaiCount,
                'guru_count'      => $guruCount,
                'tendik_count'    => max(0, $pegawaiCount - $guruCount),
                'siswa_aktif_count' => (int) ($unit->siswa_aktif_count ?? 0),
                'kelas_count'     => $kelasCount,
                'rombel_count'    => $kelasCount > 0 ? $kelasCount : 1,
                'siswa_baru_count' => (int) ($stats->siswa_baru_count ?? 0),
                'mutasi_masuk'    => 0, // Computed separately when needed (avoid extra query in list)
                'mutasi_keluar'   => 0,
                'lulus_count'     => 0,
                'alumni_count'    => 0,
            ];
        })->toArray();
    }

    /**
     * Get detail of single unit with comprehensive statistics.
     */
    public function getUnitDetail(string $id): array
    {
        $unit = EducationUnit::with(['jenisUnit'])->findOrFail($id);

        $pegawaiList = Employee::with(['position', 'division', 'teacherBridge', 'teachings'])->where('unit_id', $id)->get();
        $guruCount = $pegawaiList->filter(function ($e) {
            $j = $e->position->name ?? '';
            return $e->teacherBridge !== null
                || $e->teachings->isNotEmpty()
                || str_contains(strtolower($j), 'guru')
                || str_contains(strtolower($j), 'pendidik')
                || in_array($e->position?->level_jabatan, [8, 9]);
        })->count();
        $pegawaiCount = $pegawaiList->count();
        $siswaCount = Student::where('unit_id', $id)->where('is_active', true)->count();
        $kelasCount = Kelas::where('unit_pendidikan_id', $id)->count();
        $rombelCount = $kelasCount > 0 ? $kelasCount : 1;

        $kepalaSekolah = Employee::where('unit_id', $id)
            ->whereHas('position', function ($p) {
                $p->where('name', 'like', '%Kepala%');
            })->first();

        $activeYear = AcademicYear::where('is_active', true)->first();
        $activeSemester = Semester::where('is_active', true)->first();

        return [
            'id' => $unit->id,
            'kode' => $unit->code,
            'code' => $unit->code,
            'nama' => $unit->name,
            'name' => $unit->name,
            'jenis_unit' => $unit->jenisUnit->nama_jenis ?? $unit->level ?? 'Umum',
            'level' => $unit->level ?? '-',
            'status' => $unit->is_active ? 'aktif' : 'nonaktif',
            'is_active' => (bool) $unit->is_active,
            'description' => $unit->description,
            'location' => $unit->description ?? 'Padang',
            'kepala_sekolah' => [
                'nama' => $kepalaSekolah ? $kepalaSekolah->nama_lengkap : 'Belum Ditentukan',
                'niy' => $kepalaSekolah ? ($kepalaSekolah->niy ?? $kepalaSekolah->nik) : '-',
                'no_hp' => $kepalaSekolah ? ($kepalaSekolah->no_hp ?? '-') : '-',
                'email' => $kepalaSekolah ? ($kepalaSekolah->email ?? '-') : '-',
            ],
            'statistik' => [
                'guru' => $guruCount,
                'pegawai' => $pegawaiCount,
                'siswa' => $siswaCount,
                'kelas' => $kelasCount,
                'rombel' => $rombelCount,
            ],
            'academic' => [
                'tahun_ajaran' => $activeYear ? $activeYear->name : date('Y') . '/' . (date('Y') + 1),
                'semester' => $activeSemester ? ucfirst($activeSemester->name ?? 'Ganjil') : 'Ganjil',
            ],
            'unit' => $unit,
        ];
    }

    /**
     * Get organizational structure for a given unit (or all units).
     */
    public function getUnitStructure(?string $unitId = null): array
    {
        $query = Position::with([
            'unitSekolah',
            'atasanLangsung',
            'atasanPegawai',
            'employees' => function ($q) use ($unitId) {
                $q->where(function ($sq) {
                    $sq->where('status', 'aktif')->orWhere('status', 'Aktif')->orWhereNull('status');
                });
                if (! empty($unitId) && $unitId !== 'all') {
                    $q->where('unit_id', $unitId);
                }
            },
        ])->where('is_active', true);

        if (! empty($unitId) && $unitId !== 'all') {
            $query->where(function ($q) use ($unitId) {
                $q->where('unit_sekolah_id', $unitId)
                  ->orWhereNull('unit_sekolah_id')
                  ->orWhere('satuan_kerja', 'Pengurus');
            });
        }

        $positions = $query->orderBy('urutan')->orderBy('level_jabatan')->get();

        $selectedUnit = null;
        if (! empty($unitId) && $unitId !== 'all') {
            $unit = EducationUnit::find($unitId);
            if ($unit) {
                $selectedUnit = [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'code' => $unit->code,
                ];
            }
        }

        $structuredLevels = [];
        $levelGroups = $positions->groupBy('level_jabatan');

        foreach ($levelGroups as $levelNum => $posList) {
            $levelName = Position::LEVEL_JABATAN_MAP[$levelNum] ?? "Level {$levelNum}";
            $items = $posList->map(function ($pos) {
                $assignedEmployees = $pos->employees->map(function ($emp) {
                    return [
                        'id' => $emp->id,
                        'nama_lengkap' => $emp->nama_lengkap,
                        'niy' => $emp->niy ?? $emp->nik ?? '-',
                        'foto' => $emp->foto ?? $emp->photo ?? null,
                        'email' => $emp->email ?? '-',
                        'no_hp' => $emp->no_hp ?? '-',
                        'status' => $emp->status ?? 'Aktif',
                        'unit_name' => $emp->unit?->name ?? '-',
                    ];
                });

                return [
                    'id' => $pos->id,
                    'code' => $pos->code,
                    'name' => $pos->name,
                    'satuan_kerja' => $pos->satuan_kerja,
                    'level_jabatan' => $pos->level_jabatan,
                    'level_label' => Position::LEVEL_JABATAN_MAP[$pos->level_jabatan] ?? "Level {$pos->level_jabatan}",
                    'atasan_nama' => $pos->atasanPegawai?->nama_lengkap ?? $pos->atasanLangsung?->name ?? 'Pengurus Yayasan',
                    'warna' => $pos->warna ?? '#3B82F6',
                    'ikon' => $pos->ikon ?? 'UserCheck',
                    'unit' => $pos->unitSekolah ? ['id' => $pos->unitSekolah->id, 'name' => $pos->unitSekolah->name] : null,
                    'employees' => $assignedEmployees->values(),
                    'total_employees' => $assignedEmployees->count(),
                ];
            });

            $structuredLevels[] = [
                'level' => (int) $levelNum,
                'title' => $levelName,
                'positions' => $items->values(),
            ];
        }

        usort($structuredLevels, fn ($a, $b) => $a['level'] <=> $b['level']);

        return [
            'unit' => $selectedUnit,
            'total_positions' => $positions->count(),
            'levels' => $structuredLevels,
        ];
    }
}
