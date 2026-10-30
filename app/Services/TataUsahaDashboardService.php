<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TataUsahaDashboardService
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function getDashboardOverview($user, array $filters = []): array
    {
        $unitIds = $this->resolveUnitIds($user, $filters);
        $currentUnit = ! empty($unitIds) ? EducationUnit::query()->whereIn('id', $unitIds)->first() : null;

        $academicPeriodService = app(\App\Services\AcademicPeriodService::class);
        $activeAcademicYear = $academicPeriodService->getActiveYear();
        $activeSemester = $academicPeriodService->getActiveSemester($activeAcademicYear?->id);

        $studentQuery = $this->scopedStudentQuery($unitIds, $user);
        $employeeQuery = $this->scopedEmployeeQuery($unitIds, $user);

        $activeStudentQuery = (clone $studentQuery)->where('is_active', true);
        $totalSiswa = (clone $activeStudentQuery)->count();
        $totalPegawai = (clone $employeeQuery)->count();

        $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $totalGuru = (clone $employeeQuery)->where(function ($q) use ($like) {
            $q->whereHas('teacher')
              ->orWhereHas('teachings')
              ->orWhere('status_pegawai', $like, '%Guru%')
              ->orWhereHas('position', function ($p) use ($like) {
                  $p->whereIn('level_jabatan', [8])
                    ->orWhere('name', $like, '%Guru%')
                    ->orWhere('name', $like, '%Pendidik%');
              })
              ->orWhereHas('user.roles', function ($r) {
                  $r->whereIn('name', ['Guru', 'guru', 'Guru Mata Pelajaran', 'Wali Kelas']);
              });
        })->count();
        $totalTendik = max(0, $totalPegawai - $totalGuru);

        $siswaGender = $this->countGender((clone $activeStudentQuery)->pluck('gender'));
        $pegawaiGender = $this->countGender((clone $employeeQuery)->pluck('jenis_kelamin'));

        $siswaIncomplete = (clone $studentQuery)->where(function ($q) {
            $q->whereNull('nisn')->orWhereNull('birth_date')->orWhereNull('parent_id');
        })->count();

        $pegawaiIncomplete = (clone $employeeQuery)->where(function ($q) {
            $q->whereNull('niy')->orWhereNull('nik');
        })->count();

        $today = now()->toDateString();
        $absensiStats = $this->getTodayAttendanceStats($unitIds, $activeStudentQuery, $employeeQuery, $today, $filters, $user);

        // Hitungan permohonan surat / mutasi / perizinan
        $pengajuanMutasi = class_exists(StudentMutation::class)
            ? StudentMutation::query()->when(! empty($unitIds), fn ($q) => $q->whereIn('education_unit_id', $unitIds))->whereIn('status', ['diajukan', 'pending', 'proses'])->count()
            : 0;
        $pengajuanIzinAsrama = class_exists(DormitoryPermit::class)
            ? DormitoryPermit::query()->whereIn('status', ['menunggu', 'pending'])->count()
            : 0;
        $totalPengajuanSurat = $pengajuanMutasi + $pengajuanIzinAsrama;

        // Pembayaran hari ini
        $pembayaranHariIni = class_exists(\App\Models\BillPayment::class)
            ? \App\Models\BillPayment::query()->whereDate('paid_at', $today)->count()
            : 0;

        // Tugas menunggu verifikasi atau aksi berkas
        $tugasMenunggu = ($siswaIncomplete > 0 ? min($siswaIncomplete, 5) : 0)
            + ($pegawaiIncomplete > 0 ? 1 : 0)
            + $totalPengajuanSurat;

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
            'total_guru' => [
                'total' => $totalGuru,
                'growth' => 0,
            ],
            'total_tendik' => [
                'total' => $totalTendik,
                'growth' => 0,
            ],
            'pengajuan_surat' => [
                'total' => $totalPengajuanSurat,
                'growth' => 0,
            ],
            'pembayaran_hari_ini' => [
                'total' => $pembayaranHariIni,
                'growth' => 0,
            ],
            'tugas_menunggu' => [
                'total' => $tugasMenunggu,
                'growth' => 0,
            ],
            'siswa_incomplete' => ['total' => $siswaIncomplete, 'growth' => 0],
            'pegawai_incomplete' => ['total' => $pegawaiIncomplete, 'growth' => 0],
            'absensi_hari_ini' => [
                'total' => $absensiStats['total_verified'],
                'siswa_hadir' => $absensiStats['siswa_hadir'],
                'siswa_izin_sakit' => $absensiStats['siswa_izin_sakit'] ?? 0,
                'siswa_belum_absen' => $absensiStats['siswa_belum_absen'],
                'pegawai_hadir' => $absensiStats['pegawai_hadir'],
                'guru_hadir' => $absensiStats['guru_hadir'],
                'attendance_date' => $absensiStats['attendance_date'] ?? $today,
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
                ['name' => 'Izin/Sakit', 'value' => $absensiStats['siswa_izin_sakit'] ?? 0, 'color' => '#0ea5e9'],
                ['name' => 'Belum Absen', 'value' => $absensiStats['siswa_belum_absen'], 'color' => '#f59e0b'],
                ['name' => 'Guru Hadir', 'value' => $absensiStats['guru_hadir'], 'color' => '#6366f1'],
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

        $recentInformation = class_exists(\App\Models\PengumumanSekolah::class)
            ? \App\Models\PengumumanSekolah::where('status_aktif', true)->latest()->limit(6)->get()
            : collect([]);

        return [
            'context' => [
                'role' => 'Tata Usaha',
                'unit' => $currentUnit ? [
                    'id' => $currentUnit->id,
                    'nama' => $currentUnit->nama_unit ?? $currentUnit->name,
                    'kode' => $currentUnit->kode_unit ?? $currentUnit->code ?? null,
                ] : null,
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
            'recent_information' => $recentInformation,
            'tables' => [
                'announcements' => $recentInformation,
            ],
        ];
    }

    public function getKpiDetail($user, string $type, array $filters = []): array
    {
        $unitIds = $this->resolveUnitIds($user, $filters);
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = min(max((int) ($filters['per_page'] ?? 50), 1), 200);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $tab = (string) ($filters['tab'] ?? 'all');
        $kelasId = ! empty($filters['kelas_id']) && $filters['kelas_id'] !== 'all' ? (string) $filters['kelas_id'] : null;
        $parentSearch = trim((string) ($filters['parent_search'] ?? $filters['nama_orang_tua'] ?? ''));

        if ($type === 'siswa_hadir') {
            return $this->detailAbsensiHariIni($unitIds, $search, $page, $perPage, 'siswa_hadir', $filters, $user);
        }
        if ($type === 'siswa_belum_absen' || $type === 'belum_absen') {
            return $this->detailAbsensiHariIni($unitIds, $search, $page, $perPage, 'siswa_belum_absen', $filters, $user);
        }
        if ($type === 'guru_hadir' || $type === 'pegawai_hadir') {
            return $this->detailAbsensiHariIni($unitIds, $search, $page, $perPage, 'pegawai', $filters, $user);
        }

        return match ($type) {
            'total_siswa' => $this->detailTotalSiswa($unitIds, $search, $page, $perPage, $kelasId, $parentSearch, $user),
            'total_pegawai' => $this->detailTotalPegawai($unitIds, $search, $page, $perPage, $user),
            'absensi_hari_ini' => $this->detailAbsensiHariIni($unitIds, $search, $page, $perPage, $tab, $filters, $user),
            'siswa_incomplete' => $this->detailSiswaIncomplete($unitIds, $search, $page, $perPage, $user),
            'pegawai_incomplete' => $this->detailPegawaiIncomplete($unitIds, $search, $page, $perPage, $user),
            'pengajuan_surat' => $this->detailPengajuanSurat($unitIds, $search, $page, $perPage, $user),
            'pembayaran_hari_ini' => $this->detailPembayaranHariIni($unitIds, $search, $page, $perPage, $user),
            'tugas_menunggu' => $this->detailTugasMenunggu($unitIds, $search, $page, $perPage, $user),
            'jadwal_kbm', 'jadwal' => $this->detailJadwalKbm($unitIds, $search, $page, $perPage, $user),
            'laporan_nilai', 'nilai' => $this->detailLaporanNilai($unitIds, $search, $page, $perPage, $user),
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

    private function scopedStudentQuery(array $unitIds, $user = null)
    {
        $query = Student::query();
        $isGlobal = $user && $this->accessScope->hasGlobalScope($user);

        if (! empty($unitIds)) {
            $query->where(function ($q) use ($unitIds) {
                $q->whereIn('unit_id', $unitIds)
                    ->orWhereHas('kelas', fn ($k) => $k->whereIn('unit_pendidikan_id', $unitIds));
            });
        } elseif (! $isGlobal) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function scopedEmployeeQuery(array $unitIds, $user = null)
    {
        $query = Employee::query();
        $isGlobal = $user && $this->accessScope->hasGlobalScope($user);

        if (! empty($unitIds)) {
            $query->whereIn('unit_id', $unitIds);
        } elseif (! $isGlobal) {
            $query->whereRaw('1 = 0');
        }

        $query->whereDoesntHave('position', function ($q) {
            $q->whereIn('level_jabatan', [1, 2, 7])
                ->orWhere('satuan_kerja', 'Pengurus')
                ->orWhere('satuan_kerja', 'Bidang Pendidikan')
                ->orWhereRaw('LOWER(name) LIKE ?', ['%yayasan%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%pembina%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%pengawas%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%bidang pendidikan%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%divisi pendidikan%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%operator%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%superadmin%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%super admin%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%administrator%']);
        });
        $query->whereDoesntHave('user.roles', function ($q) {
            $q->whereIn('name', [
                'Super Admin',
                'super_admin',
                'superadmin',
                'Admin',
                'admin',
                'administrator',
                'Operator',
                'operator',
                'operator_sekolah',
                'Pengurus Yayasan',
                'pengurus_yayasan',
                'Yayasan',
                'yayasan',
                'Ketua Yayasan',
                'ketua_yayasan',
                'Sekretaris Yayasan',
                'sekretaris_yayasan',
                'Bendahara Yayasan',
                'bendahara_yayasan',
                'Divisi Pendidikan',
                'divisi_pendidikan',
                'Kepala Bidang Pendidikan',
                'kepala_bidang_pendidikan',
            ]);
        });
        $query->whereDoesntHave('role', function ($q) {
            $q->whereIn('name', [
                'Super Admin',
                'super_admin',
                'superadmin',
                'Admin',
                'admin',
                'administrator',
                'Operator',
                'operator',
                'operator_sekolah',
                'Pengurus Yayasan',
                'pengurus_yayasan',
                'Yayasan',
                'yayasan',
                'Ketua Yayasan',
                'ketua_yayasan',
                'Sekretaris Yayasan',
                'sekretaris_yayasan',
                'Bendahara Yayasan',
                'bendahara_yayasan',
                'Divisi Pendidikan',
                'divisi_pendidikan',
                'Kepala Bidang Pendidikan',
                'kepala_bidang_pendidikan',
            ]);
        });
        $query->where(function ($q) {
            $q->whereNull('nama_lengkap')
              ->orWhere(function ($sq) {
                  $sq->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%yayasan%'])
                     ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%divisi pendidikan%'])
                     ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%bidang pendidikan%'])
                     ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%superadmin%'])
                     ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%super admin%'])
                     ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%operator%']);
              });
        });

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

    private function getTodayAttendanceStats(array $unitIds, $activeStudentQuery, $employeeQuery, string $today, array $filters = [], $user = null): array
    {
        $isNotGlobal = $user && ! $this->accessScope->hasGlobalScope($user);
        if ($isNotGlobal && empty($unitIds)) {
            return [
                'total_verified' => 0,
                'siswa_hadir' => 0,
                'siswa_belum_absen' => 0,
                'pegawai_hadir' => 0,
                'guru_hadir' => 0,
                'attendance_date' => $today,
            ];
        }

        if (! Schema::hasTable('attendances')) {
            $activeStudents = (clone $activeStudentQuery)->count();

            return [
                'total_verified' => 0,
                'siswa_hadir' => 0,
                'siswa_belum_absen' => $activeStudents,
                'pegawai_hadir' => 0,
                'guru_hadir' => 0,
                'attendance_date' => $today,
            ];
        }

        $studentIds = (clone $activeStudentQuery)->pluck('id')->all();
        $employeeIds = (clone $employeeQuery)->pluck('id')->all();

        // Resolve target attendance date
        $targetDate = ! empty($filters['date']) ? $filters['date'] : (! empty($filters['attendance_date']) ? $filters['attendance_date'] : null);
        if (! $targetDate) {
            $hasToday = Attendance::query()
                ->whereDate('attendance_date', $today)
                ->when(! empty($studentIds), fn ($q) => $q->whereIn('student_id', $studentIds))
                ->exists();

            if ($hasToday) {
                $targetDate = $today;
            } else {
                $latestDate = Attendance::query()
                    ->when(! empty($studentIds), fn ($q) => $q->whereIn('student_id', $studentIds))
                    ->max('attendance_date');
                $targetDate = $latestDate ? Carbon::parse($latestDate)->toDateString() : $today;
            }
        }

        $presentStatuses = [
            'HADIR', 'Hadir', 'hadir',
            'TERLAMBAT', 'Terlambat', 'terlambat',
            'HADIR_DALAM_TOLERANSI', 'Hadir Dalam Toleransi', 'hadir_dalam_toleransi',
            'present', 'late', 'Present', 'Late',
        ];
        $permitStatuses = ['IZIN', 'Izin', 'izin', 'SAKIT', 'Sakit', 'sakit', 'permit', 'sick', 'Permit', 'Sick'];

        $siswaHadirQuery = Attendance::query()
            ->whereDate('attendance_date', $targetDate)
            ->whereNotNull('student_id')
            ->whereIn('status', $presentStatuses);

        if (! empty($studentIds)) {
            $siswaHadirQuery->whereIn('student_id', $studentIds);
        } elseif (! empty($unitIds)) {
            $siswaHadirQuery->whereIn('unit_pendidikan_id', $unitIds);
        }

        $siswaHadir = $siswaHadirQuery->distinct('student_id')->count('student_id');

        $siswaIzinSakitQuery = Attendance::query()
            ->whereDate('attendance_date', $targetDate)
            ->whereNotNull('student_id')
            ->whereIn('status', $permitStatuses);

        if (! empty($studentIds)) {
            $siswaIzinSakitQuery->whereIn('student_id', $studentIds);
        } elseif (! empty($unitIds)) {
            $siswaIzinSakitQuery->whereIn('unit_pendidikan_id', $unitIds);
        }

        $siswaIzinSakit = $siswaIzinSakitQuery->distinct('student_id')->count('student_id');

        $pegawaiHadirQuery = Attendance::query()
            ->whereDate('attendance_date', $targetDate)
            ->whereNotNull('employee_id')
            ->whereIn('status', $presentStatuses);

        if (! empty($employeeIds)) {
            $pegawaiHadirQuery->whereIn('employee_id', $employeeIds);
        } elseif (! empty($unitIds)) {
            $pegawaiHadirQuery->whereIn('unit_pendidikan_id', $unitIds);
        }

        $pegawaiHadir = $pegawaiHadirQuery->distinct('employee_id')->count('employee_id');
        $activeStudents = count($studentIds);

        return [
            'total_verified' => $siswaHadir + $pegawaiHadir + $siswaIzinSakit,
            'siswa_hadir' => $siswaHadir,
            'siswa_izin_sakit' => $siswaIzinSakit,
            'siswa_belum_absen' => max($activeStudents - $siswaHadir - $siswaIzinSakit, 0),
            'pegawai_hadir' => $pegawaiHadir,
            'guru_hadir' => $pegawaiHadir,
            'attendance_date' => $targetDate,
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

    private function detailTotalSiswa(
        array $unitIds,
        string $search,
        int $page,
        int $perPage,
        ?string $kelasId = null,
        string $parentSearch = '',
        $user = null
    ): array {
        $query = $this->scopedStudentQuery($unitIds, $user)
            ->with(['kelas', 'schoolClass', 'educationUnit', 'parent', 'parentsPivot'])
            ->where('is_active', true)
            ->orderBy('full_name');

        if (! empty($kelasId) && $kelasId !== 'all') {
            $query->where(function ($q) use ($kelasId) {
                $q->where('kelas_id', $kelasId)
                    ->orWhere('class_id', $kelasId);
            });
        }

        $this->applyStudentSearch($query, $search);

        if ($parentSearch !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($parentSearch, $likeOp) {
                $q->where('metadata->nama_ayah', $likeOp, "%{$parentSearch}%")
                    ->orWhere('metadata->nama_ibu', $likeOp, "%{$parentSearch}%")
                    ->orWhere('metadata->nama_wali', $likeOp, "%{$parentSearch}%")
                    ->orWhere('metadata->orang_tua->nama_ayah', $likeOp, "%{$parentSearch}%")
                    ->orWhere('metadata->orang_tua->nama_ibu', $likeOp, "%{$parentSearch}%")
                    ->orWhere('metadata->orang_tua->nama_wali', $likeOp, "%{$parentSearch}%")
                    ->orWhereHas('parent', fn ($pq) => $pq->where('full_name', $likeOp, "%{$parentSearch}%"))
                    ->orWhereHas('parentsPivot', fn ($pq) => $pq->where('full_name', $likeOp, "%{$parentSearch}%"));
            });
        }

        $genderCounts = $this->countGender((clone $query)->pluck('gender'));
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Fetch available classes for the scoped unit(s)
        $classesQuery = Kelas::query();
        if (! empty($unitIds)) {
            $classesQuery->whereIn('unit_pendidikan_id', $unitIds);
        } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $classesQuery->whereRaw('1 = 0');
        }

        $availableClasses = $classesQuery
            ->where(function ($q) {
                $q->whereNull('status')->orWhereIn('status', ['Aktif', 'aktif', 'active', '1']);
            })
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get(['id', 'nama_kelas', 'tingkat'])
            ->map(fn ($k) => [
                'id' => (string) $k->id,
                'nama_kelas' => $k->nama_kelas,
                'tingkat' => $k->tingkat,
            ]);

        if ($availableClasses->isEmpty() && class_exists(SchoolClass::class)) {
            $availableClasses = SchoolClass::query()
                ->when(! empty($unitIds), fn ($q) => $q->whereIn('education_unit_id', $unitIds))
                ->when($user && ! $this->accessScope->hasGlobalScope($user) && empty($unitIds), fn ($q) => $q->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(['id', 'name', 'level'])
                ->map(fn ($c) => [
                    'id' => (string) $c->id,
                    'nama_kelas' => $c->name,
                    'tingkat' => $c->level,
                ]);
        }

        // Fetch available parent names for the scoped unit and selected class
        $parentStudentsQuery = $this->scopedStudentQuery($unitIds, $user)->where('is_active', true);
        if (! empty($kelasId) && $kelasId !== 'all') {
            $parentStudentsQuery->where(function ($q) use ($kelasId) {
                $q->where('kelas_id', $kelasId)->orWhere('class_id', $kelasId);
            });
        }

        $parentModelNames = ParentModel::query()
            ->whereHas('students', function ($sq) use ($unitIds, $kelasId, $user) {
                $sq->where('is_active', true);
                if (! empty($unitIds)) {
                    $sq->where(function ($sub) use ($unitIds) {
                        $sub->whereIn('unit_id', $unitIds)
                            ->orWhereHas('kelas', fn ($k) => $k->whereIn('unit_pendidikan_id', $unitIds));
                    });
                } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
                    $sq->whereRaw('1 = 0');
                }
                if (! empty($kelasId) && $kelasId !== 'all') {
                    $sq->where(function ($q) use ($kelasId) {
                        $q->where('kelas_id', $kelasId)->orWhere('class_id', $kelasId);
                    });
                }
            })
            ->pluck('full_name');

        $metaAyah = (clone $parentStudentsQuery)
            ->whereNotNull('metadata->nama_ayah')
            ->distinct()
            ->selectRaw("metadata->>'nama_ayah' as extracted_name")
            ->pluck('extracted_name');

        $metaOrtuAyah = (clone $parentStudentsQuery)
            ->whereNotNull('metadata->orang_tua->nama_ayah')
            ->distinct()
            ->selectRaw("metadata->'orang_tua'->>'nama_ayah' as extracted_name")
            ->pluck('extracted_name');

        $metaIbu = (clone $parentStudentsQuery)
            ->whereNotNull('metadata->nama_ibu')
            ->distinct()
            ->selectRaw("metadata->>'nama_ibu' as extracted_name")
            ->pluck('extracted_name');

        $metaWali = (clone $parentStudentsQuery)
            ->whereNotNull('metadata->nama_wali')
            ->distinct()
            ->selectRaw("metadata->>'nama_wali' as extracted_name")
            ->pluck('extracted_name');

        $availableParents = $parentModelNames
            ->concat($metaAyah)
            ->concat($metaOrtuAyah)
            ->concat($metaIbu)
            ->concat($metaWali)
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($n) => $n !== '' && $n !== '-' && ! str_starts_with($n, '{'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'summary' => [
                'total' => $paginator->total(),
                'laki_laki' => $genderCounts['laki_laki'],
                'perempuan' => $genderCounts['perempuan'],
            ],
            'available_classes' => $availableClasses->values()->all(),
            'available_parents' => $availableParents,
            'items' => collect($paginator->items())->map(function ($s) {
                $meta = is_array($s->metadata) ? $s->metadata : [];
                $ortuMeta = is_array($meta['orang_tua'] ?? null) ? $meta['orang_tua'] : [];

                $namaAyah = $meta['nama_ayah'] ?? $ortuMeta['nama_ayah'] ?? null;
                $namaIbu = $meta['nama_ibu'] ?? $ortuMeta['nama_ibu'] ?? null;
                $namaWali = $meta['nama_wali'] ?? $ortuMeta['nama_wali'] ?? null;
                $parentPhone = $meta['no_hp'] ?? $ortuMeta['no_hp'] ?? $meta['no_hp_ayah'] ?? $ortuMeta['no_hp_ayah'] ?? $s->parent?->phone ?? null;
                $parentName = $s->parent?->full_name ?? $namaAyah ?? $namaIbu ?? $namaWali ?? '-';
                $pekerjaanAyah = $meta['pekerjaan_ayah'] ?? $ortuMeta['pekerjaan_ayah'] ?? $s->parent?->occupation ?? '-';
                $pekerjaanIbu = $meta['pekerjaan_ibu'] ?? $ortuMeta['pekerjaan_ibu'] ?? '-';
                $pekerjaanWali = $meta['pekerjaan_wali'] ?? $ortuMeta['pekerjaan_wali'] ?? '-';
                $alamatOrtu = $s->parent?->address ?? $meta['alamat_orang_tua'] ?? $s->address ?? '-';

                $kelasLabel = $s->kelas?->nama_kelas ?? $s->schoolClass?->name ?? '-';

                return [
                    'id' => $s->id,
                    'nama' => $s->full_name,
                    'nis' => $s->nis,
                    'nisn' => $s->nisn,
                    'jenis_kelamin' => $this->formatGenderLabel($s->gender),
                    'tempat_lahir' => $s->birth_place ?? '-',
                    'tanggal_lahir' => optional($s->birth_date)->format('d-m-Y') ?? '-',
                    'alamat' => $s->address ?? '-',
                    'kelas_id' => (string) ($s->kelas_id ?? $s->class_id ?? ''),
                    'kelas' => $kelasLabel,
                    'unit' => $s->educationUnit?->nama_unit ?? $s->educationUnit?->name ?? '-',
                    'status' => $s->is_active ? 'Aktif' : 'Nonaktif',
                    'orang_tua' => [
                        'nama' => $parentName,
                        'nama_ayah' => $namaAyah ?: ($s->parent?->full_name ?? '-'),
                        'nama_ibu' => $namaIbu ?: '-',
                        'nama_wali' => $namaWali ?: '-',
                        'no_hp' => $parentPhone ?: '-',
                        'pekerjaan_ayah' => $pekerjaanAyah,
                        'pekerjaan_ibu' => $pekerjaanIbu,
                        'pekerjaan_wali' => $pekerjaanWali,
                        'alamat' => $alamatOrtu,
                    ],
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

    private function detailTotalPegawai(array $unitIds, string $search, int $page, int $perPage, $user = null): array
    {
        $query = $this->scopedEmployeeQuery($unitIds, $user)
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
                'no_hp' => $e->no_hp ?? '-',
                'email' => $e->email ?? '-',
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function detailAbsensiHariIni(
        array $unitIds,
        string $search,
        int $page,
        int $perPage,
        string $tab,
        array $filters = [],
        $user = null
    ): array {
        $today = now()->toDateString();
        $studentQuery = $this->scopedStudentQuery($unitIds, $user)->where('is_active', true);
        $employeeQuery = $this->scopedEmployeeQuery($unitIds, $user);

        $stats = $this->getTodayAttendanceStats($unitIds, $studentQuery, $employeeQuery, $today, $filters, $user);
        $targetDate = $stats['attendance_date'] ?? $today;

        if (! Schema::hasTable('attendances')) {
            return [
                'summary' => $stats,
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
                'tab' => $tab,
            ];
        }

        $presentStatuses = [
            'HADIR', 'Hadir', 'hadir',
            'TERLAMBAT', 'Terlambat', 'terlambat',
            'HADIR_DALAM_TOLERANSI', 'Hadir Dalam Toleransi', 'hadir_dalam_toleransi',
            'present', 'late', 'Present', 'Late',
        ];

        if ($tab === 'pegawai' || $tab === 'guru' || $tab === 'guru_hadir') {
            $query = Attendance::query()
                ->whereDate('attendance_date', $targetDate)
                ->whereNotNull('employee_id')
                ->whereIn('employee_id', (clone $employeeQuery)->select('id'))
                ->whereIn('status', $presentStatuses)
                ->with(['employee.position', 'employee.unit'])
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
                    'nik' => $a->employee?->nik ?? '-',
                    'jenis_kelamin' => $this->formatGenderLabel($a->employee?->jenis_kelamin),
                    'jabatan' => $a->employee?->position?->name ?? 'Guru / Staf',
                    'unit' => $a->employee?->unit?->nama_unit ?? $a->employee?->unit?->name ?? '-',
                    'status' => $a->status_label ?? $a->status ?? 'HADIR',
                    'jam_masuk' => optional($a->check_in_time)->format('H:i') ?? '-',
                    'no_hp' => $a->employee?->no_hp ?? '-',
                    'email' => $a->employee?->email ?? '-',
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

        if ($tab === 'siswa_belum_absen' || $tab === 'belum_absen') {
            $hadirIds = Attendance::query()
                ->whereDate('attendance_date', $targetDate)
                ->whereNotNull('student_id')
                ->whereIn('student_id', (clone $studentQuery)->select('id'))
                ->whereIn('status', $presentStatuses)
                ->pluck('student_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            $query = (clone $studentQuery)
                ->with(['kelas', 'educationUnit', 'parent', 'parentsPivot'])
                ->when(! empty($hadirIds), fn ($q) => $q->whereNotIn('id', $hadirIds))
                ->orderBy('full_name');

            $this->applyStudentSearch($query, $search);
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'summary' => $stats,
                'items' => collect($paginator->items())->map(function ($s) {
                    $parent = $s->parent ?? $s->parentsPivot?->first();
                    $parentName = $parent?->full_name
                        ?? $s->metadata['nama_ayah']
                        ?? $s->metadata['nama_ibu']
                        ?? $s->metadata['nama_wali']
                        ?? $s->metadata['orang_tua']['nama_ayah']
                        ?? null;
                    $parentPhone = $parent?->phone
                        ?? $s->metadata['telepon_ortu']
                        ?? $s->metadata['no_hp_ayah']
                        ?? $s->metadata['no_hp_ibu']
                        ?? $s->metadata['orang_tua']['no_hp']
                        ?? '-';

                    return [
                        'id' => $s->id,
                        'nama' => $s->full_name,
                        'nis' => $s->nis,
                        'nisn' => $s->nisn,
                        'jenis_kelamin' => $this->formatGenderLabel($s->gender),
                        'kelas' => $s->kelas?->nama_kelas ?? $s->kelas?->name ?? '-',
                        'unit' => $s->educationUnit?->nama_unit ?? $s->educationUnit?->name ?? '-',
                        'status' => 'Belum Absen',
                        'orang_tua' => $parentName ? [
                            'nama' => $parentName,
                            'no_hp' => $parentPhone,
                        ] : null,
                    ];
                })->values(),
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
        $query = Attendance::query()
            ->whereDate('attendance_date', $targetDate)
            ->whereNotNull('student_id')
            ->whereIn('student_id', (clone $studentQuery)->select('id'))
            ->whereIn('status', $presentStatuses)
            ->with(['student.kelas', 'student.educationUnit', 'student.parent', 'student.parentsPivot'])
            ->orderByDesc('check_in_time');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->whereHas('student', fn ($q) => $q->where('full_name', $likeOp, "%{$search}%"));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => $stats,
            'items' => collect($paginator->items())->map(function ($a) {
                $student = $a->student;
                $parent = $student?->parent ?? $student?->parentsPivot?->first();
                $parentName = $parent?->full_name
                    ?? $student?->metadata['nama_ayah']
                    ?? $student?->metadata['nama_ibu']
                    ?? $student?->metadata['nama_wali']
                    ?? $student?->metadata['orang_tua']['nama_ayah']
                    ?? null;
                $parentPhone = $parent?->phone
                    ?? $student?->metadata['telepon_ortu']
                    ?? $student?->metadata['no_hp_ayah']
                    ?? $student?->metadata['no_hp_ibu']
                    ?? $student?->metadata['orang_tua']['no_hp']
                    ?? '-';

                return [
                    'id' => $a->id,
                    'nama' => $student?->full_name ?? '-',
                    'nis' => $student?->nis ?? '-',
                    'nisn' => $student?->nisn ?? '-',
                    'jenis_kelamin' => $this->formatGenderLabel($student?->gender),
                    'kelas' => $student?->kelas?->nama_kelas ?? $student?->kelas?->name ?? '-',
                    'unit' => $student?->educationUnit?->nama_unit ?? $student?->educationUnit?->name ?? '-',
                    'status' => $a->status_label ?? $a->status ?? 'HADIR',
                    'jam_masuk' => optional($a->check_in_time)->format('H:i') ?? '-',
                    'orang_tua' => $parentName ? [
                        'nama' => $parentName,
                        'no_hp' => $parentPhone,
                    ] : null,
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'tab' => $tab,
        ];
    }

    private function detailSiswaIncomplete(array $unitIds, string $search, int $page, int $perPage, $user = null): array
    {
        $query = $this->scopedStudentQuery($unitIds, $user)
            ->with(['kelas', 'educationUnit', 'parent'])
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
                    'unit' => $s->educationUnit?->nama_unit ?? $s->educationUnit?->name ?? '-',
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

    private function detailPegawaiIncomplete(array $unitIds, string $search, int $page, int $perPage, $user = null): array
    {
        $query = $this->scopedEmployeeQuery($unitIds, $user)
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

    private function detailPengajuanSurat(array $unitIds, string $search, int $page, int $perPage, $user = null): array
    {
        $query = \App\Models\StudentAttendancePermission::query()
            ->with(['student.kelas', 'student.educationUnit'])
            ->whereHas('student', function ($sq) use ($unitIds, $user) {
                if (! empty($unitIds)) {
                    $sq->where(function ($sub) use ($unitIds) {
                        $sub->whereIn('unit_id', $unitIds)
                            ->orWhereHas('kelas', fn ($k) => $k->whereIn('unit_pendidikan_id', $unitIds));
                    });
                } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
                    $sq->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('created_at');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('reason', $likeOp, "%{$search}%")
                  ->orWhere('type', $likeOp, "%{$search}%")
                  ->orWhere('status', $likeOp, "%{$search}%")
                  ->orWhereHas('student', fn ($sq) => $sq->where('full_name', $likeOp, "%{$search}%"));
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => [
                'total' => $paginator->total(),
                'menunggu' => (clone $query)->whereIn('status', ['PENDING', 'pending', 'menunggu'])->count(),
                'disetujui' => (clone $query)->whereIn('status', ['APPROVED', 'approved', 'disetujui'])->count(),
            ],
            'items' => collect($paginator->items())->map(function ($p) {
                return [
                    'id' => $p->id,
                    'nama' => $p->student?->full_name ?? 'Santri',
                    'nis' => $p->student?->nis ?? '-',
                    'nisn' => $p->student?->nisn ?? '-',
                    'kelas' => $p->student?->kelas?->nama_kelas ?? $p->student?->kelas?->name ?? '-',
                    'unit' => $p->student?->educationUnit?->nama_unit ?? $p->student?->educationUnit?->name ?? '-',
                    'tipe_surat' => $p->type ?? 'Surat Permohonan Izin / Dispensasi',
                    'alasan' => $p->reason ?? $p->notes ?? 'Pengajuan persuratan santri',
                    'tanggal' => optional($p->created_at)->format('d M Y') ?? '-',
                    'status' => strtoupper($p->status ?? 'MENUNGGU'),
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

    private function detailPembayaranHariIni(array $unitIds, string $search, int $page, int $perPage, $user = null): array
    {
        $today = Carbon::today()->toDateString();
        $query = \App\Models\BillPayment::query()
            ->with(['bill.student.kelas', 'bill.student.educationUnit', 'bill.feeCategory'])
            ->where(function ($q) use ($today) {
                $q->whereDate('paid_at', $today)
                  ->orWhereDate('created_at', $today);
            })
            ->whereHas('bill.student', function ($sq) use ($unitIds, $user) {
                if (! empty($unitIds)) {
                    $sq->where(function ($sub) use ($unitIds) {
                        $sub->whereIn('unit_id', $unitIds)
                            ->orWhereHas('kelas', fn ($k) => $k->whereIn('unit_pendidikan_id', $unitIds));
                    });
                } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
                    $sq->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('created_at');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('invoice_number', $likeOp, "%{$search}%")
                  ->orWhere('payment_method', $likeOp, "%{$search}%")
                  ->orWhereHas('bill.student', fn ($sq) => $sq->where('full_name', $likeOp, "%{$search}%"));
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $totalNominal = (float) (clone $query)->sum('paid_amount');

        return [
            'summary' => [
                'total' => $paginator->total(),
                'total_nominal' => 'Rp ' . number_format($totalNominal, 0, ',', '.'),
            ],
            'items' => collect($paginator->items())->map(function ($p) {
                $student = $p->bill?->student;
                return [
                    'id' => $p->id,
                    'nama' => $student?->full_name ?? '-',
                    'nis' => $student?->nis ?? '-',
                    'kelas' => $student?->kelas?->nama_kelas ?? $student?->kelas?->name ?? '-',
                    'unit' => $student?->educationUnit?->nama_unit ?? $student?->educationUnit?->name ?? '-',
                    'invoice' => $p->invoice_number ?? '-',
                    'pos_tagihan' => $p->bill?->feeCategory?->name ?? $p->bill?->title ?? 'SPP / Pendidikan',
                    'nominal' => 'Rp ' . number_format((float) ($p->paid_amount ?? 0), 0, ',', '.'),
                    'metode' => strtoupper($p->payment_method ?? 'TUNAI / KAS'),
                    'status' => strtoupper($p->status ?? 'LUNAS'),
                    'jam' => optional($p->paid_at ?? $p->created_at)->format('H:i') ?? '-',
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

    private function detailTugasMenunggu(array $unitIds, string $search, int $page, int $perPage, $user = null): array
    {
        $incompleteStudents = $this->detailSiswaIncomplete($unitIds, $search, 1, 100, $user);
        $incompleteEmployees = $this->detailPegawaiIncomplete($unitIds, $search, 1, 100, $user);
        $pendingPermits = $this->detailPengajuanSurat($unitIds, $search, 1, 100, $user);

        $tasks = collect([]);

        foreach ($incompleteStudents['items'] as $s) {
            $tasks->push([
                'id' => 'siswa_' . $s['id'],
                'kategori' => 'Kelengkapan Siswa',
                'nama' => $s['nama'],
                'subjudul' => 'Kelas: ' . $s['kelas'] . ' · NIS: ' . $s['nis'],
                'keterangan' => 'Perlu dilengkapi: ' . $s['keterangan'],
                'tipe' => 'siswa',
                'priority' => 'Tinggi',
            ]);
        }

        foreach ($incompleteEmployees['items'] as $e) {
            $tasks->push([
                'id' => 'pegawai_' . $e['id'],
                'kategori' => 'Kelengkapan Pegawai',
                'nama' => $e['nama'],
                'subjudul' => 'Jabatan: ' . $e['jabatan'] . ' · Unit: ' . $e['unit'],
                'keterangan' => 'Perlu dilengkapi: ' . $e['keterangan'],
                'tipe' => 'pegawai',
                'priority' => 'Sedang',
            ]);
        }

        foreach ($pendingPermits['items'] as $p) {
            if ($p['status'] === 'MENUNGGU' || $p['status'] === 'PENDING') {
                $tasks->push([
                    'id' => 'surat_' . $p['id'],
                    'kategori' => 'Verifikasi Surat',
                    'nama' => $p['nama'],
                    'subjudul' => $p['tipe_surat'] . ' · ' . $p['kelas'],
                    'keterangan' => 'Alasan: ' . $p['alasan'],
                    'tipe' => 'surat',
                    'priority' => 'Tinggi',
                ]);
            }
        }

        $totalTasks = $tasks->count();
        $slicedTasks = $tasks->forPage($page, $perPage)->values();

        return [
            'summary' => [
                'total' => $totalTasks,
                'siswa' => count($incompleteStudents['items']),
                'pegawai' => count($incompleteEmployees['items']),
            ],
            'items' => $slicedTasks,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) ceil($totalTasks / $perPage),
                'per_page' => $perPage,
                'total' => $totalTasks,
            ],
        ];
    }

    private function detailJadwalKbm(array $unitIds, string $search, int $page, int $perPage, $user = null): array
    {
        $query = ClassSchedule::query()
            ->with(['kelas.unitPendidikan', 'teacher', 'employee', 'subject'])
            ->where('is_active', true)
            ->whereHas('kelas', function ($sq) use ($unitIds, $user) {
                if (! empty($unitIds)) {
                    $sq->whereIn('unit_pendidikan_id', $unitIds);
                } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
                    $sq->whereRaw('1 = 0');
                }
            })
            ->orderBy('day_of_week')
            ->orderBy('time_start');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('activity_name', $likeOp, "%{$search}%")
                  ->orWhereHas('subject', fn ($sq) => $sq->where('name', $likeOp, "%{$search}%"))
                  ->orWhereHas('teacher', fn ($sq) => $sq->where('full_name', $likeOp, "%{$search}%"))
                  ->orWhereHas('employee', fn ($sq) => $sq->where('nama_lengkap', $likeOp, "%{$search}%"))
                  ->orWhereHas('kelas', fn ($sq) => $sq->where('nama_kelas', $likeOp, "%{$search}%"));
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $todayIso = Carbon::now()->dayOfWeekIso;
        $totalHariIni = (clone $query)->where('day_of_week', $todayIso)->count();

        return [
            'summary' => [
                'total' => $paginator->total(),
                'hari_ini' => $totalHariIni,
            ],
            'items' => collect($paginator->items())->map(function ($s) {
                $teacherName = $s->teacher?->full_name
                    ?? $s->employee?->nama_lengkap
                    ?? $s->employee?->name
                    ?? 'Guru Pengampu';

                $timeStart = $s->time_start ? substr($s->time_start, 0, 5) : '-';
                $timeEnd = $s->time_end ? substr($s->time_end, 0, 5) : '-';

                return [
                    'id' => $s->id,
                    'hari' => $s->nama_hari ?? "Hari {$s->day_of_week}",
                    'jam' => "{$timeStart} - {$timeEnd}",
                    'mapel' => $s->subject?->name ?? $s->subject?->nama_mapel ?? $s->activity_name ?? 'Pelajaran Umum',
                    'guru' => $teacherName,
                    'kelas' => $s->kelas?->nama_kelas ?? $s->kelas?->name ?? '-',
                    'ruang' => $s->kelas?->ruangan ?? 'Ruang Kelas',
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

    private function detailLaporanNilai(array $unitIds, string $search, int $page, int $perPage, $user = null): array
    {
        $query = StudentGrade::query()
            ->with(['student.kelas', 'student.educationUnit', 'subject', 'kelas'])
            ->whereHas('student', function ($sq) use ($unitIds, $user) {
                if (! empty($unitIds)) {
                    $sq->where(function ($sub) use ($unitIds) {
                        $sub->whereIn('unit_id', $unitIds)
                            ->orWhereHas('kelas', fn ($k) => $k->whereIn('unit_pendidikan_id', $unitIds));
                    });
                } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
                    $sq->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('created_at');

        if ($search !== '') {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->whereHas('student', fn ($sq) => $sq->where('full_name', $likeOp, "%{$search}%")->orWhere('nis', $likeOp, "%{$search}%"))
                  ->orWhereHas('subject', fn ($sq) => $sq->where('name', $likeOp, "%{$search}%"))
                  ->orWhere('grade_letter', $likeOp, "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $total = $paginator->total();
        $tuntas = (clone $query)->where('is_passed', true)->count();

        return [
            'summary' => [
                'total' => $total,
                'tuntas' => $tuntas,
                'belum_tuntas' => max(0, $total - $tuntas),
            ],
            'items' => collect($paginator->items())->map(function ($g) {
                return [
                    'id' => $g->id,
                    'nama' => $g->student?->full_name ?? $g->student?->name ?? '-',
                    'nis' => $g->student?->nis ?? '-',
                    'kelas' => $g->kelas?->nama_kelas ?? $g->student?->kelas?->nama_kelas ?? '-',
                    'unit' => $g->student?->educationUnit?->name ?? $g->student?->educationUnit?->nama_unit ?? '-',
                    'mapel' => $g->subject?->name ?? $g->subject?->nama_mapel ?? 'Mata Pelajaran',
                    'nilai_akhir' => $g->final_score !== null ? (string) $g->final_score : '-',
                    'predikat' => $g->grade_letter ?? '-',
                    'status' => $g->is_passed ? 'TUNTAS' : 'REMIDIAL',
                    'nilai_tugas' => $g->score_assignment !== null ? (string) $g->score_assignment : '-',
                    'nilai_uts' => $g->score_midterm !== null ? (string) $g->score_midterm : '-',
                    'nilai_uas' => $g->score_final !== null ? (string) $g->score_final : '-',
                    'catatan' => $g->notes ?? 'Hasil evaluasi belajar reguler.',
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
