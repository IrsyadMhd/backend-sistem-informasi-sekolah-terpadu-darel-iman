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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                  $p->whereIn('level_jabatan', [8])
                    ->orWhere('name', $like, '%Guru%')
                    ->orWhere('name', $like, '%Pendidik%')
                    ->orWhere('name', $like, '%Wali Kelas%');
              })
              ->orWhereHas('user.roles', function ($r) {
                  $r->whereIn('name', ['Guru', 'guru', 'Guru Mata Pelajaran', 'Wali Kelas']);
              });
        })->count();

        $totalTUOperator = (clone $query)->where(function ($q) use ($like) {
            $q->whereHas('position', function ($p) use ($like) {
                $p->whereIn('level_jabatan', [6, 7, 10])
                  ->orWhere('name', $like, '%Tata Usaha%')
                  ->orWhere('name', $like, '%Operator%')
                  ->orWhere('name', $like, '%Staf%')
                  ->orWhere('name', $like, '%Bendahara%')
                  ->orWhere('name', $like, '%Keamanan%');
            })
            ->orWhereHas('user.roles', function ($r) {
                $r->whereIn('name', ['Tata Usaha', 'tata_usaha', 'TU', 'Operator', 'operator']);
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

        // ── Perhitungan Top 3 Pegawai Terbaik & Top 3 Guru Terbaik ──
        $allCandidates = (clone $query)
            ->with(['unit:id,name,code', 'position:id,name,code', 'teacher', 'teachings', 'user.roles'])
            ->get();

        $guruCandidates = $allCandidates->filter(function ($emp) {
            $metaRole = $emp->metadata['role'] ?? '';
            $posName = $emp->position?->name ?? '';
            $statusPeg = $emp->status_pegawai ?? '';
            $fullName = $emp->nama_lengkap ?? '';
            $userRoles = $emp->user?->roles?->pluck('name')->all() ?? [];

            return $emp->teacher !== null ||
                   ($emp->teachings && $emp->teachings->isNotEmpty()) ||
                   in_array('Guru', $userRoles) ||
                   in_array('Wali Kelas', $userRoles) ||
                   preg_match('/\b(guru|pendidik|wali kelas|ustadz|ustadzah|pengajar)\b/i', "{$metaRole} {$posName} {$statusPeg} {$fullName}");
        });

        $nonGuruCandidates = $allCandidates->filter(function ($emp) use ($guruCandidates) {
            return ! $guruCandidates->contains('id', $emp->id);
        });

        // 1. Top 3 Pegawai (Non-Guru / Tendik) berdasarkan Indeks Disiplin & Presensi (Mulai dari 0)
        $rankedPegawai = $nonGuruCandidates->map(function ($emp) {
            $empAttendance = \App\Models\Attendance::where('employee_id', $emp->id)->get();
            $attCount = $empAttendance->count();
            if ($attCount > 0) {
                $hadir = $empAttendance->whereIn('status', ['HADIR', 'present', 'HADIR_TEPAT_WAKTU', 'hadir'])->count();
                $terlambat = $empAttendance->where('status', 'TERLAMBAT')->count();
                $alpa = $empAttendance->whereIn('status', ['ALPHA', 'alpa', 'absent'])->count();
                $basePct = round(($hadir / $attCount) * 100, 1);
                $score = max(0, min(100, round($basePct - ($terlambat * 0.5) - ($alpa * 2.0), 1)));
                $hasAttendance = true;
                $predikat = $score >= 95 ? 'Pegawai Teladan' : ($score >= 80 ? 'Disiplin Sangat Baik' : 'Cukup Disiplin');
                $label = "{$score}% Indeks Disiplin ({$hadir}/{$attCount} Hadir)";
            } else {
                // MURNI DIMULAI DARI 0: Belum ada catatan presensi riil di database
                $score = 0.0;
                $hasAttendance = false;
                $predikat = 'Belum Ada Presensi';
                $label = '0% Indeks Disiplin (0 Kehadiran)';
            }

            return [
                'id' => $emp->id,
                'nama_lengkap' => $emp->nama_lengkap,
                'gelar_depan' => $emp->gelar_depan && $emp->gelar_depan !== '-' ? $emp->gelar_depan : null,
                'gelar_belakang' => $emp->gelar_belakang && $emp->gelar_belakang !== '-' ? $emp->gelar_belakang : null,
                'niy' => $emp->niy,
                'foto' => $emp->foto,
                'jabatan_name' => $emp->position?->name ?? $emp->jabatan ?? data_get($emp->metadata, 'role', 'Staff Pegawai'),
                'unit_name' => $emp->unit?->name ?? 'Yayasan',
                'jenis_kelamin' => $emp->jenis_kelamin,
                'status' => $emp->status,
                'indeks_skor' => $score,
                'indeks_label' => $label,
                'predikat' => $predikat,
                'has_attendance' => $hasAttendance,
            ];
        })->filter(function ($item) {
            // HANYA pegawai yang telah memiliki capaian presensi (> 0) yang masuk nominasi Top 3
            return $item['indeks_skor'] > 0;
        })->sort(function ($a, $b) {
            if ($a['indeks_skor'] !== $b['indeks_skor']) {
                return $b['indeks_skor'] <=> $a['indeks_skor'];
            }
            return strcasecmp($a['nama_lengkap'], $b['nama_lengkap']);
        })->values()->take(3);

        // 2. Top 3 Guru berdasarkan Beban Jam Mengajar & Keaktifan Pembelajaran (Mulai dari 0)
        $rankedGuru = $guruCandidates->map(function ($guru) {
            $scheduleHours = \App\Models\ClassSchedule::where('employee_id', $guru->id)->where('is_active', true)->count() * 2;
            $teachingHours = \Illuminate\Support\Facades\DB::table('employee_teachings')->where('employee_id', $guru->id)->count() * 4;
            $totalJp = max($scheduleHours, $teachingHours);

            if ($totalJp > 0) {
                $capaianPct = min(100, round(($totalJp / 24) * 100, 1));
                $hasSchedule = true;
                $predikat = 'Guru Teladan';
                $label = "{$totalJp} JP/Minggu · {$capaianPct}% Capaian KBM";
            } else {
                // MURNI DIMULAI DARI 0: Belum ada jadwal mengajar aktif
                $totalJp = 0;
                $capaianPct = 0.0;
                $hasSchedule = false;
                $predikat = 'Belum Ada Jadwal';
                $label = '0 JP/Minggu · 0% Capaian KBM';
            }

            return [
                'id' => $guru->id,
                'nama_lengkap' => $guru->nama_lengkap,
                'gelar_depan' => $guru->gelar_depan && $guru->gelar_depan !== '-' ? $guru->gelar_depan : null,
                'gelar_belakang' => $guru->gelar_belakang && $guru->gelar_belakang !== '-' ? $guru->gelar_belakang : null,
                'niy' => $guru->niy,
                'foto' => $guru->foto,
                'jabatan_name' => $guru->position?->name ?? data_get($guru->metadata, 'role', 'Guru Mata Pelajaran'),
                'unit_name' => $guru->unit?->name ?? 'SIT',
                'jenis_kelamin' => $guru->jenis_kelamin,
                'status' => $guru->status,
                'jam_mengajar' => $totalJp,
                'indeks_skor' => $capaianPct,
                'indeks_label' => $label,
                'predikat' => $predikat,
                'has_schedule' => $hasSchedule,
            ];
        })->filter(function ($item) {
            // HANYA guru yang telah memiliki jadwal KBM & jam mengajar aktif (> 0) yang masuk nominasi Top 3
            return $item['jam_mengajar'] > 0 && $item['indeks_skor'] > 0;
        })->sort(function ($a, $b) {
            if ($a['jam_mengajar'] !== $b['jam_mengajar']) {
                return $b['jam_mengajar'] <=> $a['jam_mengajar'];
            }
            return strcasecmp($a['nama_lengkap'], $b['nama_lengkap']);
        })->values()->take(3);

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
            'top_3_pegawai' => $rankedPegawai,
            'top_3_guru' => $rankedGuru,
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
            $data['niy'] = \App\Services\CodeGeneratorService::generate('employee_niy', $data['unit_id'] ?? null);
        }

        $this->handlePhotoData($data);

        $employee = $this->employeeRepository->create($data);

        if (! empty($employee->user_id) && ! empty($employee->foto)) {
            $this->syncUserAvatar($employee->user_id, $employee->foto);
        }

        return $employee;
    }

    public function update(string $id, array $data)
    {
        $existing = $this->employeeRepository->findById($id);
        $this->handlePhotoData($data, $existing?->foto);

        $employee = $this->employeeRepository->update($id, $data);

        $userId = $employee->user_id ?? $existing?->user_id;
        if (! empty($userId) && array_key_exists('foto', $data)) {
            $this->syncUserAvatar($userId, $employee->foto);
        }

        return $employee;
    }

    protected function syncUserAvatar(string $userId, ?string $photoPath): void
    {
        $user = \App\Models\User::find($userId);
        if ($user) {
            $meta = is_array($user->metadata) ? $user->metadata : [];
            $meta['avatar'] = $photoPath;
            $user->update(['metadata' => $meta]);
        }
    }

    protected function handlePhotoData(array &$data, ?string $existingPath = null): void
    {
        if (! array_key_exists('foto', $data)) {
            return;
        }

        $rawPhoto = $data['foto'];

        if (empty($rawPhoto)) {
            if ($existingPath && ! str_starts_with($existingPath, 'data:') && ! str_starts_with($existingPath, 'http')) {
                Storage::disk('public')->delete($existingPath);
            }
            $data['foto'] = null;
            return;
        }

        if (is_string($rawPhoto) && str_starts_with($rawPhoto, 'data:image/')) {
            if ($existingPath && ! str_starts_with($existingPath, 'data:') && ! str_starts_with($existingPath, 'http')) {
                Storage::disk('public')->delete($existingPath);
            }

            $extension = 'jpg';
            if (preg_match('#^data:image/(\w+);base64,#i', $rawPhoto, $matches)) {
                $extCandidate = strtolower($matches[1]);
                $extension = $extCandidate === 'jpeg' ? 'jpg' : $extCandidate;
            }

            $base64Content = substr($rawPhoto, strpos($rawPhoto, ',') + 1);
            $decoded = base64_decode($base64Content, true);

            if ($decoded !== false) {
                $path = 'avatars/emp_' . Str::uuid()->toString() . '.' . $extension;
                Storage::disk('public')->put($path, $decoded);
                $data['foto'] = $path;
            }
        }
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
