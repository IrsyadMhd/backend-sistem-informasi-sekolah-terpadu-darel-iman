<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TataUsahaDashboardService
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function getDashboardOverview($user, array $filters = []): array
    {
        $unitIds = $this->resolveUnitIds($user, $filters);

        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        $studentQuery = $this->scopedStudentQuery($unitIds);
        $employeeQuery = $this->scopedEmployeeQuery($unitIds);

        $activeStudentQuery = (clone $studentQuery)->where('is_active', true);
        $totalSiswa = (clone $activeStudentQuery)->count();
        $totalPegawai = (clone $employeeQuery)->count();

        $siswaGender = $this->countGender((clone $activeStudentQuery)->pluck('gender'));
        $pegawaiGender = $this->countGender((clone $employeeQuery)->pluck('jenis_kelamin'));

        $siswaIncomplete = (clone $studentQuery)->where(function ($q) {
            $q->whereNull('nisn')->orWhereNull('birth_date')->orWhereNull('parent_id');
        })->count();

        $pegawaiIncomplete = (clone $employeeQuery)->where(function ($q) {
            $q->whereNull('niy')->orWhereNull('nik');
        })->count();

        $today = now()->toDateString();
        $absensiStats = $this->getTodayAttendanceStats($unitIds, $activeStudentQuery, $employeeQuery, $today);

        $kpis = [
            'total_siswa' => [
                'total' => $totalSiswa,
                'laki_laki' => $siswaGender['laki_laki'],
                'perempuan' => $siswaGender['perempuan'],
                'growth' => 0,
            ],
            'total_pegawai' => [
                'total' => $totalPegawai,
                'laki_laki' => $pegawaiGender['laki_laki'],
                'perempuan' => $pegawaiGender['perempuan'],
                'growth' => 0,
            ],
            'siswa_incomplete' => ['total' => $siswaIncomplete, 'growth' => 0],
            'pegawai_incomplete' => ['total' => $pegawaiIncomplete, 'growth' => 0],
            'absensi_hari_ini' => [
                'total' => $absensiStats['total_verified'],
                'siswa_hadir' => $absensiStats['siswa_hadir'],
                'siswa_belum_absen' => $absensiStats['siswa_belum_absen'],
                'pegawai_hadir' => $absensiStats['pegawai_hadir'],
                'growth' => 0,
            ],
        ];

        $siswaLengkap = max($totalSiswa - $siswaIncomplete, 0);
        $pegawaiLengkap = max($totalPegawai - $pegawaiIncomplete, 0);

        $charts = [
            'siswa_gender' => [
                ['name' => 'Laki-laki', 'value' => $siswaGender['laki_laki'], 'color' => '#0ea5e9'],
                ['name' => 'Perempuan', 'value' => $siswaGender['perempuan'], 'color' => '#f43f5e'],
            ],
            'pegawai_gender' => [
                ['name' => 'Laki-laki', 'value' => $pegawaiGender['laki_laki'], 'color' => '#0ea5e9'],
                ['name' => 'Perempuan', 'value' => $pegawaiGender['perempuan'], 'color' => '#f43f5e'],
            ],
            'absensi_hari_ini' => [
                ['name' => 'Siswa Hadir', 'value' => $absensiStats['siswa_hadir'], 'color' => '#0E5C44'],
                ['name' => 'Siswa Belum Absen', 'value' => $absensiStats['siswa_belum_absen'], 'color' => '#f59e0b'],
                ['name' => 'Pegawai Hadir', 'value' => $absensiStats['pegawai_hadir'], 'color' => '#6366f1'],
            ],
            'siswa_kelengkapan' => [
                ['name' => 'Data Lengkap', 'value' => $siswaLengkap, 'color' => '#0E5C44'],
                ['name' => 'Belum Lengkap', 'value' => $siswaIncomplete, 'color' => '#f59e0b'],
            ],
            'pegawai_kelengkapan' => [
                ['name' => 'Data Lengkap', 'value' => $pegawaiLengkap, 'color' => '#0E5C44'],
                ['name' => 'Belum Lengkap', 'value' => $pegawaiIncomplete, 'color' => '#f43f5e'],
            ],
        ];

        return [
            'context' => [
                'role' => 'Tata Usaha',
                'tahun_ajaran' => $activeAcademicYear ? [
                    'id' => $activeAcademicYear->id,
                    'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama,
                ] : null,
                'semester' => $activeSemester ? [
                    'id' => $activeSemester->id,
                    'nama' => $activeSemester->name ?? $activeSemester->nama,
                ] : null,
            ],
            'kpis' => $kpis,
            'charts' => $charts,
            'tables' => [],
        ];
    }

    public function getKpiDetail($user, string $type, array $filters = []): array
    {
        $unitIds = $this->resolveUnitIds($user, $filters);
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = min(max((int) ($filters['per_page'] ?? 50), 1), 200);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $tab = (string) ($filters['tab'] ?? 'all');

        return match ($type) {
            'total_siswa' => $this->detailTotalSiswa($unitIds, $search, $page, $perPage),
            'total_pegawai' => $this->detailTotalPegawai($unitIds, $search, $page, $perPage),
            'absensi_hari_ini' => $this->detailAbsensiHariIni($unitIds, $search, $page, $perPage, $tab),
            'siswa_incomplete' => $this->detailSiswaIncomplete($unitIds, $search, $page, $perPage),
            'pegawai_incomplete' => $this->detailPegawaiIncomplete($unitIds, $search, $page, $perPage),
            default => abort(404, 'Detail KPI tidak ditemukan.'),
        };
    }

    private function resolveUnitIds($user, array $filters): array
    {
        $unitQuery = $this->accessScope->accessibleEducationUnits($user);
        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $this->accessScope->assertEducationUnitAccess($user, (string) $filters['unit_id']);
            $unitQuery->whereKey($filters['unit_id']);
        }

        return $unitQuery->pluck('id')->filter()->values()->all();
    }

    private function scopedStudentQuery(array $unitIds)
    {
        $query = Student::query();
        if (! empty($unitIds)) {
            $query->whereIn('unit_id', $unitIds);
        }

        return $query;
    }

    private function scopedEmployeeQuery(array $unitIds)
    {
        $query = Employee::query();
        if (! empty($unitIds)) {
            $query->whereIn('unit_id', $unitIds);
        }

        return $query;
    }

    private function countGender($values): array
    {
        $laki = 0;
        $perempuan = 0;

        foreach ($values as $value) {
            if ($this->isMale($value)) {
                $laki++;
            } elseif ($this->isFemale($value)) {
                $perempuan++;
            }
        }

        return ['laki_laki' => $laki, 'perempuan' => $perempuan];
    }

    private function isMale(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['l', 'laki-laki', 'laki laki', 'male', 'm', 'pria'], true);
    }

    private function isFemale(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['p', 'perempuan', 'female', 'f', 'wanita'], true);
    }

    private function formatGenderLabel(mixed $value): string
    {
        if ($this->isMale($value)) {
            return 'Laki-laki';
        }
        if ($this->isFemale($value)) {
            return 'Perempuan';
        }

        return '-';
    }

    private function getTodayAttendanceStats(array $unitIds, $activeStudentQuery, $employeeQuery, string $today): array
    {
        if (! Schema::hasTable('attendances')) {
            $activeStudents = (clone $activeStudentQuery)->count();

            return [
                'total_verified' => 0,
                'siswa_hadir' => 0,
                'siswa_belum_absen' => $activeStudents,
                'pegawai_hadir' => 0,
            ];
        }

        $studentIds = (clone $activeStudentQuery)->pluck('id');
        $employeeIds = (clone $employeeQuery)->pluck('id');

        $attendanceQuery = Attendance::query()->whereDate('attendance_date', $today);

        if (! empty($unitIds)) {
            $attendanceQuery->whereIn('unit_pendidikan_id', $unitIds);
        }

        $siswaHadirIds = (clone $attendanceQuery)
            ->whereNotNull('student_id')
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->unique();

        $pegawaiHadir = (clone $attendanceQuery)
            ->whereNotNull('employee_id')
            ->whereIn('employee_id', $employeeIds)
            ->distinct('employee_id')
            ->count('employee_id');

        $siswaHadir = $siswaHadirIds->count();
        $activeStudents = $studentIds->count();

        return [
            'total_verified' => $siswaHadir + $pegawaiHadir,
            'siswa_hadir' => $siswaHadir,
            'siswa_belum_absen' => max($activeStudents - $siswaHadir, 0),
            'pegawai_hadir' => $pegawaiHadir,
        ];
    }

    private function applyStudentSearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $query->where(function ($q) use ($search, $likeOp) {
            $q->where('full_name', $likeOp, "%{$search}%")
                ->orWhere('nis', $likeOp, "%{$search}%")
                ->orWhere('nisn', $likeOp, "%{$search}%");
        });
    }

    private function applyEmployeeSearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $query->where(function ($q) use ($search, $likeOp) {
            $q->where('nama_lengkap', $likeOp, "%{$search}%")
                ->orWhere('niy', $likeOp, "%{$search}%")
                ->orWhere('nik', $likeOp, "%{$search}%");
        });
    }

    private function detailTotalSiswa(array $unitIds, string $search, int $page, int $perPage): array
    {
        $query = $this->scopedStudentQuery($unitIds)
            ->with(['kelas', 'educationUnit'])
            ->where('is_active', true)
            ->orderBy('full_name');

        $this->applyStudentSearch($query, $search);

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
                'nis' => $s->nis,
                'nisn' => $s->nisn,
                'jenis_kelamin' => $this->formatGenderLabel($s->gender),
                'kelas' => $s->kelas?->nama_kelas ?? $s->kelas?->name ?? '-',
                'unit' => $s->educationUnit?->nama_unit ?? $s->educationUnit?->name ?? '-',
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

    private function detailTotalPegawai(array $unitIds, string $search, int $page, int $perPage): array
    {
        $query = $this->scopedEmployeeQuery($unitIds)
            ->with(['unit', 'position'])
            ->orderBy('nama_lengkap');

        $this->applyEmployeeSearch($query, $search);

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
                'niy' => $e->niy,
                'nik' => $e->nik,
                'jenis_kelamin' => $this->formatGenderLabel($e->jenis_kelamin),
                'jabatan' => $e->position?->name ?? $e->position?->nama_jabatan ?? '-',
                'unit' => $e->unit?->nama_unit ?? $e->unit?->name ?? '-',
                'status' => $e->status_pegawai ?? $e->status ?? '-',
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function detailAbsensiHariIni(array $unitIds, string $search, int $page, int $perPage, string $tab): array
    {
        $today = now()->toDateString();
        $studentQuery = $this->scopedStudentQuery($unitIds)->where('is_active', true);
        $employeeQuery = $this->scopedEmployeeQuery($unitIds);

        $stats = $this->getTodayAttendanceStats($unitIds, $studentQuery, $employeeQuery, $today);

        if (! Schema::hasTable('attendances')) {
            return [
                'summary' => $stats,
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
                'tab' => $tab,
            ];
        }

        $attendanceBase = Attendance::query()
            ->whereDate('attendance_date', $today);

        if (! empty($unitIds)) {
            $attendanceBase->whereIn('unit_pendidikan_id', $unitIds);
        }

        if ($tab === 'pegawai') {
            $query = (clone $attendanceBase)
                ->whereNotNull('employee_id')
                ->with(['employee.position'])
                ->orderByDesc('check_in_time');

            if ($search !== '') {
                $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->whereHas('employee', fn ($q) => $q->where('nama_lengkap', $likeOp, "%{$search}%"));
            }

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'summary' => $stats,
                'items' => collect($paginator->items())->map(fn ($a) => [
                    'id' => $a->id,
                    'nama' => $a->employee?->nama_lengkap ?? '-',
                    'niy' => $a->employee?->niy ?? '-',
                    'jabatan' => $a->employee?->position?->name ?? '-',
                    'status' => $a->status_label ?? $a->status ?? '-',
                    'jam_masuk' => optional($a->check_in_time)->format('H:i') ?? '-',
                ])->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'tab' => $tab,
            ];
        }

        if ($tab === 'siswa_belum_absen') {
            $hadirIds = (clone $attendanceBase)
                ->whereNotNull('student_id')
                ->pluck('student_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            $query = (clone $studentQuery)
                ->with(['kelas'])
                ->when(! empty($hadirIds), fn ($q) => $q->whereNotIn('id', $hadirIds))
                ->orderBy('full_name');

            $this->applyStudentSearch($query, $search);
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'summary' => $stats,
                'items' => collect($paginator->items())->map(fn ($s) => [
                    'id' => $s->id,
                    'nama' => $s->full_name,
                    'nis' => $s->nis,
                    'nisn' => $s->nisn,
                    'jenis_kelamin' => $this->formatGenderLabel($s->gender),
                    'kelas' => $s->kelas?->nama_kelas ?? $s->kelas?->name ?? '-',
                    'status' => 'Belum Absen',
                ])->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'tab' => $tab,
            ];
        }

        // Default: siswa hadir
        $query = (clone $attendanceBase)
            ->whereNotNull('student_id')
            ->with(['student.kelas'])
            ->orderByDesc('check_in_time');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->whereHas('student', fn ($q) => $q->where('full_name', $likeOp, "%{$search}%"));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => $stats,
            'items' => collect($paginator->items())->map(fn ($a) => [
                'id' => $a->id,
                'nama' => $a->student?->full_name ?? '-',
                'nis' => $a->student?->nis ?? '-',
                'nisn' => $a->student?->nisn ?? '-',
                'jenis_kelamin' => $this->formatGenderLabel($a->student?->gender),
                'kelas' => $a->student?->kelas?->nama_kelas ?? $a->student?->kelas?->name ?? '-',
                'status' => $a->status_label ?? $a->status ?? '-',
                'jam_masuk' => optional($a->check_in_time)->format('H:i') ?? '-',
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'tab' => $tab,
        ];
    }

    private function detailSiswaIncomplete(array $unitIds, string $search, int $page, int $perPage): array
    {
        $query = $this->scopedStudentQuery($unitIds)
            ->with(['kelas', 'parent'])
            ->where(function ($q) {
                $q->whereNull('nisn')->orWhereNull('birth_date')->orWhereNull('parent_id');
            })
            ->orderBy('full_name');

        $this->applyStudentSearch($query, $search);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => ['total' => $paginator->total()],
            'items' => collect($paginator->items())->map(function ($s) {
                $missing = array_values(array_filter([
                    empty($s->nisn) ? 'NISN' : null,
                    empty($s->birth_date) ? 'Tgl Lahir' : null,
                    empty($s->parent_id) ? 'Wali Murid' : null,
                ]));

                return [
                    'id' => $s->id,
                    'nama' => $s->full_name,
                    'nis' => $s->nis,
                    'nisn' => $s->nisn ?? '-',
                    'kelas' => $s->kelas?->nama_kelas ?? $s->kelas?->name ?? '-',
                    'keterangan' => implode(', ', $missing),
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function detailPegawaiIncomplete(array $unitIds, string $search, int $page, int $perPage): array
    {
        $query = $this->scopedEmployeeQuery($unitIds)
            ->with(['unit', 'position'])
            ->where(function ($q) {
                $q->whereNull('niy')->orWhereNull('nik');
            })
            ->orderBy('nama_lengkap');

        $this->applyEmployeeSearch($query, $search);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => ['total' => $paginator->total()],
            'items' => collect($paginator->items())->map(function ($e) {
                $missing = array_values(array_filter([
                    empty($e->niy) ? 'NIY' : null,
                    empty($e->nik) ? 'NIK' : null,
                ]));

                return [
                    'id' => $e->id,
                    'nama' => $e->nama_lengkap,
                    'niy' => $e->niy ?? '-',
                    'nik' => $e->nik ?? '-',
                    'jabatan' => $e->position?->name ?? $e->position?->nama_jabatan ?? '-',
                    'unit' => $e->unit?->nama_unit ?? $e->unit?->name ?? '-',
                    'keterangan' => implode(', ', $missing),
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
