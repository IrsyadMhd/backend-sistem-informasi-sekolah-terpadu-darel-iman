<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTeaching;
use App\Models\Kelas;
use App\Models\Position;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    protected EmployeeRepositoryInterface $employeeRepository;

    public function __construct(EmployeeRepositoryInterface $employeeRepository)
    {
        $this->employeeRepository = $employeeRepository;
    }

    public function getDashboardStats(array $filters = [])
    {
        $query = Employee::query();

        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $query->where('unit_id', $filters['unit_id']);
        } elseif (array_key_exists('allowed_unit_ids', $filters) && is_array($filters['allowed_unit_ids'])) {
            $query->whereIn('unit_id', $filters['allowed_unit_ids']);
        }

        if (! empty($filters['exclude_restricted_roles'])) {
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
        }

        $totalPegawai = (clone $query)->count();
        $totalAktif = (clone $query)->where('status', 'Aktif')->count();

        $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $totalGuru = (clone $query)->where(function ($q) use ($like) {
            $q->whereHas('teacher')
              ->orWhereHas('teachings')
              ->orWhere('status_pegawai', $like, '%Guru%')
              ->orWhereHas('position', function ($p) use ($like) {
                  $p->where('name', $like, '%Guru%')
                    ->orWhere('name', $like, '%Pendidik%')
                    ->orWhere('name', $like, '%Wali Kelas%');
              });
        })->count();

        $totalTUOperator = (clone $query)->where(function ($q) use ($like) {
            $q->whereHas('position', function ($p) use ($like) {
                $p->where('name', $like, '%Tata Usaha%')
                  ->orWhere('name', $like, '%Operator%')
                  ->orWhere('name', $like, '%Staf%')
                  ->orWhere('name', $like, '%Bendahara%')
                  ->orWhere('name', $like, '%Keamanan%');
            });
        })->count();

        $byUnit = (clone $query)->selectRaw('unit_id, count(*) as count')
            ->with('unit')
            ->groupBy('unit_id')
            ->get();

        $byJabatan = (clone $query)->selectRaw('jabatan_id, count(*) as count')
            ->with('position')
            ->groupBy('jabatan_id')
            ->get();

        // ── Perhitungan KPI Kehadiran Pegawai (Ditarik dari Database) ──
        $attendanceQuery = \App\Models\Attendance::query();
        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $attendanceQuery->where('unit_pendidikan_id', $filters['unit_id']);
        }

        $totalPresensiCount = (clone $attendanceQuery)->count();
        $hadirCount = (clone $attendanceQuery)->whereIn('status', ['HADIR', 'present', 'HADIR_TEPAT_WAKTU'])->count();
        $terlambatCount = (clone $attendanceQuery)->where('status', 'TERLAMBAT')->count();
        $tidakMasukCount = (clone $attendanceQuery)->whereIn('status', ['IZIN', 'SAKIT', 'ALPHA', 'absent', 'alpa'])->count();

        if ($totalPresensiCount > 0) {
            $pctHadir = round(($hadirCount / $totalPresensiCount) * 100, 1);
            $pctTerlambat = round(($terlambatCount / $totalPresensiCount) * 100, 1);
            $pctTidakMasuk = round(($tidakMasukCount / $totalPresensiCount) * 100, 1);
        } else {
            $pctHadir = $totalPegawai > 0 ? round(($totalAktif / $totalPegawai) * 100, 1) : 100.0;
            $pctTerlambat = 0.0;
            $pctTidakMasuk = round(100 - $pctHadir, 1);
        }

        // ── Perhitungan KPI Jam Mengajar Guru (Ditarik dari Database) ──
        $scheduleQuery = \App\Models\ClassSchedule::query()->where('is_active', true);
        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $scheduleQuery->whereHas('kelas', function ($q) use ($filters) {
                $q->where('unit_pendidikan_id', $filters['unit_id']);
            });
        }

        $totalScheduleCount = (clone $scheduleQuery)->count();

        $topSubjectData = (clone $scheduleQuery)
            ->select('subject_id', \Illuminate\Support\Facades\DB::raw('count(*) as total_jam'))
            ->with('subject')
            ->groupBy('subject_id')
            ->orderByDesc('total_jam')
            ->first();

        $topSubjectName = $topSubjectData?->subject?->name ?? $topSubjectData?->subject?->nama ?? ($totalScheduleCount > 0 ? 'Mata Pelajaran Utama' : '-');
        $topSubjectHours = (int) ($topSubjectData?->total_jam ?? 0);

        $topTeacherData = (clone $scheduleQuery)
            ->select('employee_id', \Illuminate\Support\Facades\DB::raw('count(*) as total_jam'))
            ->with('employee')
            ->groupBy('employee_id')
            ->orderByDesc('total_jam')
            ->first();

        $topTeacherName = $topTeacherData?->employee?->nama_lengkap ?? '-';
        $topTeacherHours = (int) ($topTeacherData?->total_jam ?? 0);

        $avgHoursPerGuru = $totalGuru > 0 ? round($totalScheduleCount / $totalGuru, 1) : 0;
        $totalTendik = max(0, $totalPegawai - $totalGuru);

        return [
            'total_pegawai' => $totalPegawai,
            'total_employees' => $totalPegawai,
            'total_aktif' => $totalAktif,
            'total_active' => $totalAktif,
            'total_guru' => $totalGuru,
            'total_teachers' => $totalGuru,
            'total_tendik' => $totalTendik,
            'total_staff' => $totalTendik,
            'total_tu_operator' => $totalTUOperator,
            'by_unit' => $byUnit,
            'by_jabatan' => $byJabatan,
            'kpi_presensi_pegawai' => [
                'total_presensi' => $totalPresensiCount,
                'total_hadir' => $hadirCount,
                'total_terlambat' => $terlambatCount,
                'total_tidak_masuk' => $tidakMasukCount,
                'persentase_hadir' => $pctHadir,
                'persentase_terlambat' => $pctTerlambat,
                'persentase_tidak_masuk' => $pctTidakMasuk,
            ],
            'kpi_jam_mengajar_guru' => [
                'total_jam_pelajaran' => $totalScheduleCount,
                'mapel_terbanyak' => $topSubjectName,
                'jam_mapel_terbanyak' => $topSubjectHours,
                'guru_terbanyak' => $topTeacherName,
                'jam_guru_terbanyak' => $topTeacherHours,
                'rata_jam_per_guru' => $avgHoursPerGuru,
            ],
        ];
    }

    public function list(array $filters, int $perPage = 15)
    {
        return $this->employeeRepository->paginate($filters, $perPage);
    }

    public function getById(string $id)
    {
        return $this->employeeRepository->findById($id);
    }

    public function create(array $data)
    {
        if (empty($data['niy'])) {
            $data['niy'] = 'NIY-'.date('Ym').str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }

        return $this->employeeRepository->create($data);
    }

    public function update(string $id, array $data)
    {
        return $this->employeeRepository->update($id, $data);
    }

    public function delete(string $id)
    {
        return $this->employeeRepository->delete($id);
    }

    public function assignTeaching(string $employeeId, array $teachingsData)
    {
        return DB::transaction(function () use ($employeeId, $teachingsData) {
            EmployeeTeaching::where('employee_id', $employeeId)->delete();

            $created = [];
            $seen = [];
            foreach ($teachingsData as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $classroomId = $item['classroom_id'] ?? null;
                $subjectId = $item['subject_id'] ?? null;
                $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];

                // Extract string labels if present
                $mapelName = $item['mapel'] ?? $metadata['mapel'] ?? null;
                $kelasName = $item['kelas'] ?? $metadata['kelas'] ?? null;
                $tahunName = $item['tahun'] ?? $metadata['tahun'] ?? null;
                $semesterName = $item['semester'] ?? $metadata['semester'] ?? null;

                if ($mapelName) {
                    $metadata['mapel'] = $mapelName;
                }
                if ($kelasName) {
                    $metadata['kelas'] = $kelasName;
                }
                if ($tahunName) {
                    $metadata['tahun'] = $tahunName;
                }
                if ($semesterName) {
                    $metadata['semester'] = $semesterName;
                }

                // Resolve subject_id by name if null
                if (! $subjectId && $mapelName) {
                    $matchedSubject = Subject::where('name', $mapelName)
                        ->orWhere('nama_mapel', $mapelName)
                        ->first();
                    if ($matchedSubject) {
                        $subjectId = $matchedSubject->id;
                    }
                }

                // Resolve classroom_id by name if null
                if (! $classroomId && $kelasName) {
                    $matchedClass = SchoolClass::where('name', $kelasName)->first()
                        ?? Kelas::where('nama_kelas', $kelasName)->first();
                    if ($matchedClass) {
                        $classroomId = $matchedClass->id;
                    }
                }

                $dedupKey = ($classroomId ?? $kelasName ?? '') . '|'
                    . ($subjectId ?? $mapelName ?? '') . '|'
                    . ($item['academic_year_id'] ?? $tahunName ?? '') . '|'
                    . ($item['semester_id'] ?? $semesterName ?? '');

                if ($dedupKey !== '|||' && isset($seen[$dedupKey])) {
                    continue;
                }
                $seen[$dedupKey] = true;

                $created[] = EmployeeTeaching::create([
                    'employee_id' => $employeeId,
                    'classroom_id' => $classroomId,
                    'subject_id' => $subjectId,
                    'academic_year_id' => $item['academic_year_id'] ?? null,
                    'semester_id' => $item['semester_id'] ?? null,
                    'aktif' => $item['aktif'] ?? true,
                    'metadata' => ! empty($metadata) ? $metadata : null,
                ]);
            }

            return $created;
        });
    }

    public function getPositions()
    {
        return Position::where('is_active', true)->orderBy('name', 'asc')->get();
    }
}
