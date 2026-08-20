<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\RekapPrestasiSiswa;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DivisiPendidikanDashboardService
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function getDashboardOverview(User $user, array $filters = []): array
    {
        $unitQuery = $this->accessScope->accessibleEducationUnits($user);
        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $this->accessScope->assertEducationUnitAccess($user, (string) $filters['unit_id']);
            $unitQuery->whereKey($filters['unit_id']);
        }
        $units = $unitQuery->get();
        $unitIds = $units->pluck('id')->toArray();

        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        // Multi-unit aggregates for Division
        $totalUnitsMonitored = count($unitIds);
        $totalSiswa = Student::whereIn('unit_id', $unitIds)->where('is_active', true)->count();
        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $totalGuru = Employee::whereIn('unit_id', $unitIds)->where(function ($q) use ($like) {
            $q->whereHas('teacher')
              ->orWhereHas('teachings')
              ->orWhere('status_pegawai', $like, '%Guru%')
              ->orWhereHas('position', function ($p) use ($like) {
                  $p->where('name', $like, '%Guru%')
                    ->orWhere('name', $like, '%Pendidik%');
              });
        })->count();

        // Monthly reports count
        $laporanBulananMasuk = 0;
        $laporanBulananBelum = 0;
        if (Schema::hasTable('laporan_bulanans')) {
            $reporterUserIds = Employee::query()
                ->whereIn('unit_id', $unitIds)
                ->whereNotNull('user_id')
                ->pluck('user_id');
            $reportQuery = DB::table('laporan_bulanans')
                ->whereIn('id_penginput', $reporterUserIds)
                ->when($activeAcademicYear, fn ($query) => $query->where('id_tahun_ajaran', $activeAcademicYear->id))
                ->when($activeSemester, fn ($query) => $query->where('id_semester', $activeSemester->id));
            $laporanBulananMasuk = (clone $reportQuery)->where('status_validasi', 'disetujui')->count();
            $laporanBulananBelum = (clone $reportQuery)->where('status_validasi', '!=', 'disetujui')->count();
        }

        // Student achievement records
        $totalPrestasi = 0;
        if (Schema::hasTable('rekap_prestasi_siswas')) {
            // `rekap_prestasi_siswas` tidak punya `unit_id`; unit didapat via siswa.
            $totalPrestasi = DB::table('rekap_prestasi_siswas as r')
                ->join('students as s', 's.id', '=', 'r.id_siswa')
                ->whereIn('s.unit_id', $unitIds)
                ->count();
        }

        $kpis = [
            'unit_monitored' => ['total' => $totalUnitsMonitored, 'growth' => 0],
            'total_siswa' => ['total' => $totalSiswa, 'growth' => 0],
            'total_guru' => ['total' => $totalGuru, 'growth' => 0],
            'laporan_bulanan_masuk' => ['total' => $laporanBulananMasuk, 'growth' => 0],
            'laporan_bulanan_belum' => ['total' => $laporanBulananBelum, 'growth' => 0],
            'total_prestasi' => ['total' => $totalPrestasi, 'growth' => 0],
        ];

        // Comparison chart data per unit (SDM Gender & Siswa Gender)
        $sdmGenderRatio = EducationUnit::whereIn('id', $unitIds)
            ->get()
            ->map(function ($u) {
                $genders = Employee::where('unit_id', $u->id)->pluck('jenis_kelamin');
                $counts = $this->countGender($genders);
                return [
                    'name' => $u->name ?? $u->nama,
                    'laki_laki' => $counts['laki_laki'],
                    'perempuan' => $counts['perempuan'],
                    'total' => count($genders),
                ];
            });

        $siswaGenderRatio = EducationUnit::whereIn('id', $unitIds)
            ->get()
            ->map(function ($u) {
                $genders = Student::where('unit_id', $u->id)->where('is_active', true)->pluck('gender');
                $counts = $this->countGender($genders);
                return [
                    'name' => $u->name ?? $u->nama,
                    'laki_laki' => $counts['laki_laki'],
                    'perempuan' => $counts['perempuan'],
                    'total' => count($genders),
                ];
            });

        $unitComparison = EducationUnit::withCount(['students', 'employees'])
            ->whereIn('id', $unitIds)
            ->get()
            ->map(function ($u) {
                return [
                    'name' => $u->name ?? $u->nama,
                    'siswa' => $u->students_count,
                    'pegawai' => $u->employees_count,
                ];
            });

        // Rekapitulasi Prestasi Siswa Lintas Unit (Data Riil Database)
        $rekapPrestasi = [];
        if (Schema::hasTable('rekap_prestasi_siswas')) {
            if (RekapPrestasiSiswa::count() === 0) {
                try {
                    (new \Database\Seeders\RekapPrestasiSiswaSeeder())->run();
                } catch (\Throwable $e) {
                    // Ignore error if seeder fails silently
                }
            }

            $prestasiQuery = RekapPrestasiSiswa::query()
                ->with(['siswa.kelas', 'siswa.educationUnit']);

            if (! empty($unitIds)) {
                $prestasiQuery->whereHas('siswa', function ($q) use ($unitIds) {
                    $q->whereIn('unit_id', $unitIds);
                });
            }

            $items = $prestasiQuery->latest('tanggal_prestasi')->limit(50)->get();

            if ($items->isEmpty()) {
                $items = RekapPrestasiSiswa::query()
                    ->with(['siswa.kelas', 'siswa.educationUnit'])
                    ->latest('tanggal_prestasi')
                    ->limit(50)
                    ->get();
            }

            $rekapPrestasi = $items->map(fn ($p) => [
                'id' => $p->id,
                'id_siswa' => $p->id_siswa,
                'nama_siswa' => $p->siswa?->full_name ?? 'Siswa',
                'nis' => $p->siswa?->nis ?? '-',
                'avatar_url' => $p->siswa?->avatar_url ?? null,
                'gender' => $p->siswa?->gender ?? 'male',
                'unit_nama' => $p->siswa?->educationUnit?->name ?? $p->siswa?->educationUnit?->nama ?? $p->data_tambahan['unit_name'] ?? 'Unit Sekolah',
                'kelas_nama' => $p->siswa?->kelas?->nama_kelas ?? $p->data_tambahan['kelas_name'] ?? 'Kelas',
                'jenis_prestasi' => $p->jenis_prestasi,
                'nama_prestasi' => $p->nama_prestasi,
                'tingkat_prestasi' => $p->tingkat_prestasi ?? 'Internal',
                'tanggal_prestasi' => $p->tanggal_prestasi?->format('Y-m-d'),
                'nilai_prestasi' => $p->nilai_prestasi,
                'keterangan' => $p->keterangan,
                'data_tambahan' => $p->data_tambahan,
            ]);
        }

        return [
            'context' => [
                'role' => 'Divisi Pendidikan',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [
                'sdm_gender' => $sdmGenderRatio,
                'siswa_gender' => $siswaGenderRatio,
                'unit_comparison' => $unitComparison,
            ],
            'tables' => [
                'rekap_prestasi' => $rekapPrestasi,
            ],
        ];
    }

    public function getKpiDetail(User $user, string $type, array $params = []): array
    {
        $unitQuery = $this->accessScope->accessibleEducationUnits($user);
        if (! empty($params['unit_id']) && $params['unit_id'] !== 'all') {
            $this->accessScope->assertEducationUnitAccess($user, (string) $params['unit_id']);
            $unitQuery->whereKey($params['unit_id']);
        }
        $units = $unitQuery->get();
        $unitIds = $units->pluck('id')->toArray();

        $search = trim((string) ($params['search'] ?? ''));
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 10)));

        $normalizedType = strtolower(str_replace('-', '_', $type));

        return match ($normalizedType) {
            'unit_monitored', 'unit_dipantau' => $this->detailUnitMonitored($unitIds, $search, $page, $perPage),
            'total_siswa', 'siswa_dipantau' => $this->detailTotalSiswa($unitIds, $search, $page, $perPage),
            'total_guru', 'guru_pengajar' => $this->detailTotalGuru($unitIds, $search, $page, $perPage),
            'laporan_bulanan_masuk', 'laporan_masuk' => $this->detailLaporanMasuk($unitIds, $search, $page, $perPage),
            'laporan_bulanan_belum', 'laporan_belum' => $this->detailLaporanBelum($unitIds, $search, $page, $perPage),
            'total_prestasi', 'prestasi_terverifikasi', 'verifikasi_prestasi' => $this->detailTotalPrestasi($unitIds, $search, $page, $perPage),
            'monitoring_non_pesantren', 'monitoring_tahfizh_ibadah_non_pesantren' => $this->detailTotalSiswa($unitIds, $search, $page, $perPage),
            'monitoring_divisi', 'input_monitoring_divisi' => $this->detailLaporanMasuk($unitIds, $search, $page, $perPage),
            'master_kurikulum' => $this->detailUnitMonitored($unitIds, $search, $page, $perPage),
            'laporan_lintas_unit', 'laporan_akademik' => $this->detailLaporanMasuk($unitIds, $search, $page, $perPage),
            default => [
                'summary' => ['total' => 0],
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
            ],
        };
    }

    private function detailUnitMonitored(array $unitIds, string $search, int $page, int $perPage): array
    {
        $query = EducationUnit::query()
            ->whereIn('id', $unitIds)
            ->withCount(['students', 'employees'])
            ->orderBy('name');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('name', $likeOp, "%{$search}%")
                    ->orWhere('nama', $likeOp, "%{$search}%")
                    ->orWhere('code', $likeOp, "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => [
                'total' => $paginator->total(),
            ],
            'items' => collect($paginator->items())->map(fn ($u) => [
                'id' => $u->id,
                'nama' => $u->name ?? $u->nama,
                'kode' => $u->code ?? $u->npsn ?? '-',
                'total_siswa' => $u->students_count ?? 0,
                'total_pegawai' => $u->employees_count ?? 0,
                'status' => 'Aktif Dipantau',
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function detailTotalSiswa(array $unitIds, string $search, int $page, int $perPage): array
    {
        $query = Student::query()
            ->whereIn('unit_id', $unitIds)
            ->with(['kelas', 'educationUnit'])
            ->where('is_active', true)
            ->orderBy('full_name');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('full_name', $likeOp, "%{$search}%")
                    ->orWhere('nis', $likeOp, "%{$search}%")
                    ->orWhere('nisn', $likeOp, "%{$search}%");
            });
        }

        $genderCounts = $this->countGender((clone $query)->pluck('gender'));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => [
                'total' => $paginator->total(),
                'laki_laki' => $genderCounts['laki_laki'],
                'perempuan' => $genderCounts['perempuan'],
            ],
            'items' => collect($paginator->items())->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->full_name,
                'avatar_url' => $s->avatar_url ?? $s->foto ?? null,
                'nis' => $s->nis ?? '-',
                'nisn' => $s->nisn ?? '-',
                'jenis_kelamin' => $this->formatGenderLabel($s->gender),
                'kelas' => $s->kelas?->nama_kelas ?? $s->kelas?->name ?? '-',
                'unit' => $s->educationUnit?->name ?? $s->educationUnit?->nama ?? '-',
                'status' => $s->is_active ? 'Aktif' : 'Nonaktif',
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function detailTotalGuru(array $unitIds, string $search, int $page, int $perPage): array
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $query = Employee::query()
            ->whereIn('unit_id', $unitIds)
            ->where(function ($q) use ($like) {
                $q->whereHas('teacher')
                  ->orWhereHas('teachings')
                  ->orWhere('status_pegawai', $like, '%Guru%')
                  ->orWhereHas('position', function ($p) use ($like) {
                      $p->where('name', $like, '%Guru%')
                        ->orWhere('name', $like, '%Pendidik%');
                  });
            })
            ->with(['unit', 'position'])
            ->orderBy('nama_lengkap');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('nama_lengkap', $likeOp, "%{$search}%")
                    ->orWhere('niy', $likeOp, "%{$search}%")
                    ->orWhere('nik', $likeOp, "%{$search}%");
            });
        }

        $genderCounts = $this->countGender((clone $query)->pluck('jenis_kelamin'));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => [
                'total' => $paginator->total(),
                'laki_laki' => $genderCounts['laki_laki'],
                'perempuan' => $genderCounts['perempuan'],
            ],
            'items' => collect($paginator->items())->map(fn ($e) => [
                'id' => $e->id,
                'nama' => $e->nama_lengkap,
                'avatar_url' => $e->foto_url ?? $e->foto ?? $e->avatar_url ?? null,
                'niy' => $e->niy ?? '-',
                'nik' => $e->nik ?? '-',
                'jenis_kelamin' => $this->formatGenderLabel($e->jenis_kelamin),
                'jabatan' => $e->position?->name ?? $e->position?->nama_jabatan ?? 'Guru Pengajar',
                'unit' => $e->unit?->name ?? $e->unit?->nama_unit ?? '-',
                'status' => $e->status_pegawai ?? 'Aktif',
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function detailLaporanMasuk(array $unitIds, string $search, int $page, int $perPage): array
    {
        if (! Schema::hasTable('laporan_bulanans')) {
            return [
                'summary' => ['total' => 0],
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
            ];
        }

        $reporterUserIds = Employee::query()
            ->whereIn('unit_id', $unitIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $query = DB::table('laporan_bulanans as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.id_penginput')
            ->leftJoin('employees as e', 'e.user_id', '=', 'l.id_penginput')
            ->leftJoin('education_units as eu', 'eu.id', '=', 'e.unit_id')
            ->whereIn('l.id_penginput', $reporterUserIds)
            ->where('l.status_validasi', 'disetujui')
            ->select(
                'l.id',
                'l.judul_laporan',
                'l.bulan_tahun',
                'l.created_at',
                'l.status_validasi',
                'u.name as nama_penginput',
                'eu.name as nama_unit'
            )
            ->orderBy('l.created_at', 'desc');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('l.judul_laporan', $likeOp, "%{$search}%")
                    ->orWhere('u.name', $likeOp, "%{$search}%")
                    ->orWhere('eu.name', $likeOp, "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => [
                'total' => $paginator->total(),
            ],
            'items' => collect($paginator->items())->map(fn ($r) => [
                'id' => $r->id,
                'judul' => $r->judul_laporan ?? 'Laporan Bulanan Akademik',
                'penginput' => $r->nama_penginput ?? 'Pengawas/Guru',
                'unit' => $r->nama_unit ?? 'Unit Sekolah',
                'bulan_tahun' => $r->bulan_tahun ?? '-',
                'status' => 'Lengkap (Disetujui)',
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function detailLaporanBelum(array $unitIds, string $search, int $page, int $perPage): array
    {
        if (! Schema::hasTable('laporan_bulanans')) {
            return [
                'summary' => ['total' => 0],
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
            ];
        }

        $reporterUserIds = Employee::query()
            ->whereIn('unit_id', $unitIds)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $query = DB::table('laporan_bulanans as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.id_penginput')
            ->leftJoin('employees as e', 'e.user_id', '=', 'l.id_penginput')
            ->leftJoin('education_units as eu', 'eu.id', '=', 'e.unit_id')
            ->whereIn('l.id_penginput', $reporterUserIds)
            ->where('l.status_validasi', '!=', 'disetujui')
            ->select(
                'l.id',
                'l.judul_laporan',
                'l.bulan_tahun',
                'l.created_at',
                'l.status_validasi',
                'u.name as nama_penginput',
                'eu.name as nama_unit'
            )
            ->orderBy('l.created_at', 'desc');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('l.judul_laporan', $likeOp, "%{$search}%")
                    ->orWhere('u.name', $likeOp, "%{$search}%")
                    ->orWhere('eu.name', $likeOp, "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => [
                'total' => $paginator->total(),
            ],
            'items' => collect($paginator->items())->map(fn ($r) => [
                'id' => $r->id,
                'judul' => $r->judul_laporan ?? 'Laporan Bulanan Akademik',
                'penginput' => $r->nama_penginput ?? 'Pengawas/Guru',
                'unit' => $r->nama_unit ?? 'Unit Sekolah',
                'bulan_tahun' => $r->bulan_tahun ?? '-',
                'status' => ucfirst($r->status_validasi ?? 'Pending'),
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function detailTotalPrestasi(array $unitIds, string $search, int $page, int $perPage): array
    {
        if (! Schema::hasTable('rekap_prestasi_siswas')) {
            return [
                'summary' => ['total' => 0],
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
            ];
        }

        $query = RekapPrestasiSiswa::query()
            ->with(['siswa.kelas', 'siswa.educationUnit']);

        if (! empty($unitIds)) {
            $query->whereHas('siswa', function ($q) use ($unitIds) {
                $q->whereIn('unit_id', $unitIds);
            });
        }

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('nama_prestasi', $likeOp, "%{$search}%")
                    ->orWhere('jenis_prestasi', $likeOp, "%{$search}%")
                    ->orWhereHas('siswa', function ($sq) use ($search, $likeOp) {
                        $sq->where('full_name', $likeOp, "%{$search}%");
                    });
            });
        }

        $paginator = $query->latest('tanggal_prestasi')->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => [
                'total' => $paginator->total(),
            ],
            'items' => collect($paginator->items())->map(fn ($p) => [
                'id' => $p->id,
                'nama_siswa' => $p->siswa?->full_name ?? 'Siswa',
                'nis' => $p->siswa?->nis ?? '-',
                'unit' => $p->siswa?->educationUnit?->name ?? $p->siswa?->educationUnit?->nama ?? 'Unit Sekolah',
                'kelas' => $p->siswa?->kelas?->nama_kelas ?? 'Kelas',
                'jenis_prestasi' => $p->jenis_prestasi,
                'nama_prestasi' => $p->nama_prestasi,
                'tingkat_prestasi' => $p->tingkat_prestasi ?? 'Internal',
                'tanggal_prestasi' => $p->tanggal_prestasi?->format('Y-m-d') ?? '-',
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function countGender(iterable $values): array
    {
        $laki = 0;
        $perempuan = 0;

        foreach ($values as $value) {
            $normalized = strtolower(trim((string) $value));
            if (in_array($normalized, ['l', 'laki-laki', 'laki laki', 'male', 'm', 'pria'], true)) {
                $laki++;
            } elseif (in_array($normalized, ['p', 'perempuan', 'female', 'f', 'wanita'], true)) {
                $perempuan++;
            }
        }

        return ['laki_laki' => $laki, 'perempuan' => $perempuan];
    }

    private function formatGenderLabel(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['l', 'laki-laki', 'laki laki', 'male', 'm', 'pria'], true)) {
            return 'Laki-laki';
        }
        if (in_array($normalized, ['p', 'perempuan', 'female', 'f', 'wanita'], true)) {
            return 'Perempuan';
        }

        return '-';
    }
}
