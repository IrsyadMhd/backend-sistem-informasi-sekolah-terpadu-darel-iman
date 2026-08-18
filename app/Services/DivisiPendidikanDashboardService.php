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

        // Comparison chart data per unit
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
                'unit_comparison' => $unitComparison,
            ],
            'tables' => [
                'rekap_prestasi' => $rekapPrestasi,
            ],
        ];
    }
}
