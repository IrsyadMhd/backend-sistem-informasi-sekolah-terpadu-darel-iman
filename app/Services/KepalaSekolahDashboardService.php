<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\PengumumanSekolah;
use App\Models\RekapPrestasiSiswa;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KepalaSekolahDashboardService
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function getDashboardData($user, array $filters = []): array
    {
        $unitQuery = $this->accessScope->accessibleEducationUnits($user);
        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $this->accessScope->assertEducationUnitAccess($user, (string) $filters['unit_id']);
            $unitQuery->whereKey($filters['unit_id']);
        }

        $emp = Employee::where('user_id', $user->id)->first();
        $userUnitId = ! empty($filters['unit_id']) && $filters['unit_id'] !== 'all'
            ? $filters['unit_id']
            : ($emp?->unit_id ?? data_get($user->metadata, 'education_unit_id') ?? data_get($user->metadata, 'unit_id'));

        if ($userUnitId) {
            $unit = $this->accessScope->accessibleEducationUnits($user)->whereKey($userUnitId)->first();
        } else {
            $unit = $unitQuery->first();
        }
        $targetUnitId = $unit ? $unit->id : null;

        // Context
        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        // 1. KPIs scoped to Principal's unit
        $studentQuery = Student::query()
            ->where(function ($q) {
                $q->whereNull('metadata->mutasi_type')
                  ->orWhere('metadata->mutasi_type', '!=', 'keluar');
            });
        if ($targetUnitId && $unit) {
            $studentQuery->where(function ($q) use ($targetUnitId) {
                $q->where('unit_id', $targetUnitId)
                  ->orWhereHas('kelas', function ($kq) use ($targetUnitId) {
                      $kq->where('unit_pendidikan_id', $targetUnitId);
                  });
            });
        }

        $employeeQuery = Employee::query();
        if ($targetUnitId && $unit) {
            $employeeQuery->where('unit_id', $targetUnitId);
        }

        $classQuery = Kelas::query();
        if ($targetUnitId && $unit) {
            $classQuery->where('unit_pendidikan_id', $targetUnitId);
        }

        $totalSiswa = (clone $studentQuery)->where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })->count();

        $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $totalGuru = (clone $employeeQuery)->where(function ($q) use ($like) {
            $q->whereHas('teacher')
              ->orWhereHas('teachings')
              ->orWhere('status_pegawai', $like, '%Guru%')
              ->orWhereHas('position', function ($p) use ($like) {
                  $p->where('name', $like, '%Guru%')
                    ->orWhere('name', $like, '%Pendidik%');
              });
        })->count();

        $totalPegawai = (clone $employeeQuery)->count();

        $totalKelas = (clone $classQuery)->count();

        $totalRombel = Schema::hasTable('rombels') && $targetUnitId
            ? DB::table('rombels')->where('unit_id', $targetUnitId)->count()
            : $totalKelas;
        if ($totalRombel === 0) {
            $totalRombel = $totalKelas;
        }

        // Attendance today
        $today = now()->toDateString();
        $hadirHariIni = 0;
        $terlambat = 0;
        $izin = 0;
        $sakit = 0;
        $alpha = 0;

        $hadirHariIni = 0;
        $terlambat = 0;
        $izin = 0;
        $sakit = 0;
        $alpha = 0;

        $studentIds = (clone $studentQuery)->pluck('id');

        $latestGate = Schema::hasTable('attendances') && $studentIds->isNotEmpty()
            ? DB::table('attendances')->whereIn('student_id', $studentIds)->max('attendance_date')
            : null;
        $latestLms = Schema::hasTable('lms_presensi') && $studentIds->isNotEmpty()
            ? DB::table('lms_presensi')->whereIn('siswa_id', $studentIds)->max('tanggal')
            : null;
        $latestActiveDate = max($latestGate, $latestLms);

        $attDateToUse = ! empty($filters['date']) ? $filters['date'] : (! empty($filters['attendance_date']) ? $filters['attendance_date'] : $today);

        if ($studentIds->isNotEmpty()) {
            $gateMap = Schema::hasTable('attendances')
                ? DB::table('attendances')
                    ->whereIn('student_id', $studentIds)
                    ->whereDate('attendance_date', $attDateToUse)
                    ->get()
                    ->keyBy('student_id')
                : collect();

            $lmsMap = Schema::hasTable('lms_presensi')
                ? DB::table('lms_presensi')
                    ->whereIn('siswa_id', $studentIds)
                    ->whereDate('tanggal', $attDateToUse)
                    ->get()
                    ->groupBy('siswa_id')
                : collect();

            foreach ($studentIds as $sId) {
                $gate = $gateMap->get($sId);
                $lmsList = $lmsMap->get($sId);

                $isGatePresent = $gate && in_array(strtolower($gate->status ?? ''), ['present', 'hadir', 'late', 'terlambat']);
                $isLessonPresent = false;
                $lmsStatus = null;
                if ($lmsList && $lmsList->isNotEmpty()) {
                    foreach ($lmsList as $lms) {
                        $s = strtolower($lms->status_hadir ?? '');
                        if (in_array($s, ['present', 'hadir', 'late', 'terlambat'])) {
                            $isLessonPresent = true;
                        } elseif (! $lmsStatus) {
                            $lmsStatus = $s;
                        }
                    }
                }

                // Status hijau jika siswa absen gerbang maupun absen di pelajaran
                if ($isGatePresent || $isLessonPresent) {
                    $hadirHariIni++;
                } elseif (($gate && in_array(strtolower($gate->status), ['permission', 'izin'])) || ($lmsStatus && in_array($lmsStatus, ['permission', 'izin']))) {
                    $izin++;
                } elseif (($gate && in_array(strtolower($gate->status), ['sick', 'sakit'])) || ($lmsStatus && in_array($lmsStatus, ['sick', 'sakit']))) {
                    $sakit++;
                } elseif (($gate && in_array(strtolower($gate->status), ['absent', 'alpha', 'alpa'])) || ($lmsStatus && in_array($lmsStatus, ['alpa', 'alpha']))) {
                    $alpha++;
                }
            }
        }

        // Tahfizh setoran hari ini
        $setoranTahfizhHariIni = 0;
        if (Schema::hasTable('tahfizh_records')) {
            $tQuery = DB::table('tahfizh_records')
                ->whereDate('record_date', $today);
            $studentIds = (clone $studentQuery)->pluck('id');
            if ($studentIds->isNotEmpty()) {
                $tQuery->whereIn('student_id', $studentIds);
            }
            $setoranTahfizhHariIni = $tQuery->count();
        }

        $totalPrestasi = 0;
        if (Schema::hasTable('rekap_prestasi_siswas')) {
            $totalPrestasiQuery = RekapPrestasiSiswa::query();
            if ($targetUnitId) {
                $totalPrestasiQuery->whereHas('siswa', fn ($sq) => $sq->where('unit_id', $targetUnitId));
            }
            $totalPrestasi = $totalPrestasiQuery->count();
        }

        $kpis = [
            'total_siswa' => ['total' => $totalSiswa, 'growth' => 0],
            'total_guru' => ['total' => $totalGuru, 'growth' => 0],
            'total_pegawai' => ['total' => $totalPegawai, 'growth' => 0],
            'total_kelas' => ['total' => $totalKelas, 'growth' => 0],
            'total_rombel' => ['total' => $totalRombel, 'growth' => 0],
            'siswa_hadir_hari_ini' => ['total' => $hadirHariIni, 'growth' => 0],
            'siswa_terlambat' => ['total' => $terlambat, 'growth' => 0],
            'siswa_izin' => ['total' => $izin, 'growth' => 0],
            'siswa_sakit' => ['total' => $sakit, 'growth' => 0],
            'siswa_alpha' => ['total' => $alpha, 'growth' => 0],
            'setoran_tahfizh_hari_ini' => ['total' => $setoranTahfizhHariIni, 'growth' => 0],
            'total_prestasi' => ['total' => $totalPrestasi, 'growth' => 0],
        ];

        // 2. Charts Data
        // Attendance Trend: Perbandingan Fullday (Senin - Jumat) vs Pondok Pesantren (Senin - Sabtu)
        $isPesantren = false;
        if ($unit) {
            $uCode = strtoupper($unit->code ?? '');
            $uName = strtolower($unit->name ?? '');
            $isPesantren = str_contains($uCode, 'PONPES') || str_contains($uCode, 'MAHAD') || str_contains($uName, 'pesantren') || str_contains($uName, 'mahad') || str_contains($uName, 'pondok');
        }

        $trendStudentIds = (clone $studentQuery)->pluck('id');
        $totalSiswaCount = $trendStudentIds->count();

        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];

        // Tentukan tanggal acuan minggu aktif dari tanggal presensi yang digunakan
        $refDate = Carbon::parse($attDateToUse);
        $monday = $refDate->copy()->startOfWeek(Carbon::MONDAY);

        $fulldayDates = collect();
        for ($i = 0; $i < 5; $i++) {
            $fulldayDates->push($monday->copy()->addDays($i));
        }

        $pesantrenDates = collect();
        for ($i = 0; $i < 6; $i++) {
            $pesantrenDates->push($monday->copy()->addDays($i));
        }

        $startWeek = $monday->toDateString();
        $endWeek = $monday->copy()->addDays(6)->toDateString();

        $gateTrend = (Schema::hasTable('attendances') && $trendStudentIds->isNotEmpty())
            ? DB::table('attendances')
                ->whereIn('student_id', $trendStudentIds)
                ->whereBetween('attendance_date', [$startWeek, $endWeek])
                ->get()
            : collect();

        $lmsTrend = (Schema::hasTable('lms_presensi') && $trendStudentIds->isNotEmpty())
            ? DB::table('lms_presensi')
                ->whereIn('siswa_id', $trendStudentIds)
                ->whereBetween('tanggal', [$startWeek, $endWeek])
                ->get()
            : collect();

        $buildTrend = function ($dateCollection) use ($gateTrend, $lmsTrend, $trendStudentIds, $dayNames, $totalSiswaCount) {
            return $dateCollection->map(function ($carbonDate) use ($gateTrend, $lmsTrend, $trendStudentIds, $dayNames, $totalSiswaCount) {
                $dStr = $carbonDate->toDateString();
                $dayNum = $carbonDate->dayOfWeekIso;
                $dayName = $dayNames[$dayNum] ?? 'Hari';

                $gateOnDay = $gateTrend->filter(fn ($x) => str_starts_with($x->attendance_date, $dStr))->keyBy('student_id');
                $lmsOnDay = $lmsTrend->filter(fn ($x) => str_starts_with($x->tanggal, $dStr))->groupBy('siswa_id');

                $hadir = 0;
                $terlambat = 0;
                $alpha = 0;

                foreach ($trendStudentIds as $sId) {
                    $g = $gateOnDay->get($sId);
                    $l = $lmsOnDay->get($sId);

                    $isPres = ($g && in_array(strtolower($g->status ?? ''), ['present', 'hadir', 'late', 'terlambat']))
                        || ($l && $l->contains(fn ($x) => in_array(strtolower($x->status_hadir ?? ''), ['present', 'hadir', 'late', 'terlambat'])));

                    if ($isPres) {
                        $hadir++;
                    } elseif (($g && in_array(strtolower($g->status ?? ''), ['absent', 'alpha', 'alpa'])) || ($l && $l->contains(fn ($x) => in_array(strtolower($x->status_hadir ?? ''), ['alpa', 'alpha'])))) {
                        $alpha++;
                    }
                }

                $rate = $totalSiswaCount > 0 ? round(($hadir / $totalSiswaCount) * 100, 1) : 0;

                return [
                    'date' => $dStr,
                    'day_name' => $dayName,
                    'label' => $dayName . ' (' . $carbonDate->format('d/m') . ')',
                    'hadir' => $hadir,
                    'terlambat' => $terlambat,
                    'alpha' => $alpha,
                    'rate' => $rate,
                ];
            })->values()->toArray();
        };

        $fulldayTrend = $buildTrend($fulldayDates);
        $pesantrenTrend = $buildTrend($pesantrenDates);
        $attendanceTrend = $isPesantren ? $pesantrenTrend : $fulldayTrend;

        // 3. Tables & Alerts
        if (PengumumanSekolah::count() === 0) {
            try {
                PengumumanSekolah::create([
                    'judul_pengumuman' => 'Edaran Pelaksanaan Evaluasi Pembelajaran & Mutabaah Yaumiyah',
                    'isi_pengumuman' => 'Diberitahukan kepada seluruh Kepala Sekolah, Wali Kelas, dan Guru Pengajar untuk melengkapi rekapitulasi nilai evaluasi pembelajaran serta mutabaah hafalan Al-Qur’an siswa.',
                    'target_peran' => ['Kepala Sekolah', 'Guru', 'Wali Kelas'],
                    'prioritas' => 'tinggi',
                    'status_aktif' => true,
                ]);
                PengumumanSekolah::create([
                    'judul_pengumuman' => 'Pengumuman Rapat Koordinasi Ortu & Syukuran Prestasi Santri',
                    'isi_pengumuman' => 'Undangan rapat koordinasi pengurus yayasan dan komite sekolah bersama orang tua murid dalam rangka penyerahan apresiasi kelulusan ujian Tahfizh Al-Qur’an.',
                    'target_peran' => ['Orang Tua', 'Kepala Sekolah'],
                    'prioritas' => 'sedang',
                    'status_aktif' => true,
                ]);
            } catch (\Throwable $e) {
                // Ignore silent seeder error
            }
        }

        $recentAnnouncements = PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($announcement) => [
                'id' => $announcement->id,
                'judul' => $announcement->judul_pengumuman,
                'isi' => $announcement->isi_pengumuman,
                'target' => is_array($announcement->target_peran)
                    ? implode(', ', $announcement->target_peran)
                    : ($announcement->target_peran ?: 'Semua Unit'),
                'prioritas' => $announcement->prioritas,
                'created_at' => $announcement->created_at,
                'gambar_url' => data_get($announcement->data_tambahan, 'gambar_url') ?? data_get($announcement->data_tambahan, 'image_url') ?? null,
                'kategori' => data_get($announcement->data_tambahan, 'kategori') ?? 'Informasi',
                'ringkasan' => data_get($announcement->data_tambahan, 'ringkasan') ?? null,
            ]);

        // 4. Rekapitulasi Prestasi Siswa (Data Riil dari Database)
        $rekapPrestasi = [];
        if (Schema::hasTable('rekap_prestasi_siswas')) {
            $prestasiQuery = RekapPrestasiSiswa::query()
                ->with(['siswa.kelas', 'siswa.educationUnit']);

            if (! empty($targetUnitId)) {
                $prestasiQuery->whereHas('siswa', function ($sq) use ($targetUnitId) {
                    $sq->where('unit_id', $targetUnitId);
                });
            }

            $items = $prestasiQuery->latest('tanggal_prestasi')->limit(30)->get();

            $unitFallback = $unit ? ($unit->name ?? $unit->nama) : '-';

            $rekapPrestasi = $items->map(fn ($p) => [
                'id' => $p->id,
                'id_siswa' => $p->id_siswa,
                'nama_siswa' => $p->siswa?->full_name ?? 'Siswa',
                'nis' => $p->siswa?->nis ?? '-',
                'avatar_url' => $p->siswa?->avatar_url ?? null,
                'gender' => $p->siswa?->gender ?? 'male',
                'unit_nama' => $p->siswa?->educationUnit?->name ?? $p->siswa?->educationUnit?->nama ?? $p->data_tambahan['unit_name'] ?? $unitFallback,
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

        $siteSetting = \App\Models\SiteSetting::current();
        $unitNama = $unit ? ($unit->name ?? $unit->nama) : ($siteSetting->school_name ?? '-');
        $unitKode = $unit ? ($unit->code ?? $unit->kode ?? null) : null;
        $unitNpsn = $unit?->metadata['npsn'] ?? $unit?->npsn ?? '-';
        $unitAkreditasi = $unit?->metadata['akreditasi'] ?? $unit?->akreditasi ?? '-';
        $unitAlamat = $unit?->metadata['alamat'] ?? $unit?->alamat ?? $unit?->description ?? ($siteSetting->footer_text ?? '-');
        $unitKepalaSekolah = $unit?->metadata['kepala_sekolah'] ?? $user?->name ?? '-';
        $unitKontak = $unit?->metadata['kontak'] ?? '-';
        
        // 5. Civitas Online & Log Keaktifan (Data Riil Database Unit)
        $onlineUsers = [];
        $onlineLogs = [];

        if (Schema::hasTable('employees')) {
            $empQuery = Employee::query();

            if (Schema::hasColumn('employees', 'status')) {
                $empQuery->where(function ($q) {
                    $q->whereIn('status', ['Aktif', 'aktif', 'active', 'Tetap'])
                      ->orWhereNull('status');
                });
            } elseif (Schema::hasColumn('employees', 'is_active')) {
                $empQuery->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                });
            }

            if (! empty($targetUnitId)) {
                $empQuery->where('unit_id', $targetUnitId);
            }

            $empList = $empQuery->with(['user', 'position', 'educationUnit'])->get();

            if ($empList->isNotEmpty()) {
                $userIds = $empList->pluck('user_id')->filter()->unique();
                $recentTokens = collect();
                if ($userIds->isNotEmpty() && Schema::hasTable('personal_access_tokens')) {
                    $recentTokens = DB::table('personal_access_tokens')
                        ->whereIn('tokenable_id', $userIds)
                        ->whereNotNull('last_used_at')
                        ->orderByDesc('last_used_at')
                        ->get()
                        ->groupBy('tokenable_id');
                }

                $recentLogins = collect();
                if ($userIds->isNotEmpty() && Schema::hasTable('login_events')) {
                    $recentLogins = DB::table('login_events')
                        ->where('status', 'success')
                        ->whereIn('user_id', $userIds)
                        ->orderByDesc('created_at')
                        ->get()
                        ->groupBy('user_id');
                }

                $now = now();
                $onlineThreshold = $now->copy()->subMinutes(15);

                $onlineUsers = $empList->map(function ($emp) use ($unitNama, $recentTokens, $recentLogins, $now, $onlineThreshold, $user) {
                    $uName = $emp->nama_lengkap ?? $emp->full_name ?? $emp->name ?? $emp->user?->name ?? 'Pegawai';
                    $posName = $emp->position?->name ?? $emp->status_pegawai ?? 'Pegawai / Guru';
                    $isFemale = ($emp->jenis_kelamin === 'P' || $emp->jenis_kelamin === 'female' || str_contains(strtolower($uName), 'ustadzah') || str_contains(strtolower($uName), 'siti') || str_contains(strtolower($uName), 'rahma') || str_contains(strtolower($uName), 'ibu'));

                    $uid = $emp->user_id;
                    $lastLoginTime = null;

                    if ($uid) {
                        if (isset($recentTokens[$uid])) {
                            $tok = $recentTokens[$uid]->first();
                            $tokTime = \Carbon\Carbon::parse($tok->last_used_at ?? $tok->created_at);
                            if (! $lastLoginTime || $tokTime->gt($lastLoginTime)) {
                                $lastLoginTime = $tokTime;
                            }
                        }
                        if (isset($recentLogins[$uid])) {
                            $le = $recentLogins[$uid]->first();
                            $leTime = \Carbon\Carbon::parse($le->created_at);
                            if (! $lastLoginTime || $leTime->gt($lastLoginTime)) {
                                $lastLoginTime = $leTime;
                            }
                        }
                    }

                    $isCurrentUser = ($uid && $user && $uid === $user->id);
                    $isOnline = $isCurrentUser || ($lastLoginTime && $lastLoginTime->gte($onlineThreshold));

                    if ($isOnline) {
                        $lastSeen = $isCurrentUser ? 'Aktif sekarang' : 'Login ' . $lastLoginTime->diffForHumans();
                        $activity = 'Sesi aktif di portal sistem';
                    } elseif ($lastLoginTime) {
                        $lastSeen = 'Offline (Login ' . $lastLoginTime->diffForHumans() . ')';
                        $activity = 'Terakhir login ' . $lastLoginTime->format('d M Y, H:i') . ' WIB';
                    } else {
                        $lastSeen = 'Offline';
                        $activity = 'Belum pernah login ke sistem';
                    }

                    $nipDisplay = $emp->niy ? 'NIY. ' . $emp->niy : ($emp->nik ? 'NIK. ' . $emp->nik : '—');

                    return [
                        'id' => 'emp-' . $emp->id,
                        'name' => $uName,
                        'nama' => $uName,
                        'role' => $posName,
                        'nip' => $nipDisplay,
                        'dept' => $emp->educationUnit?->name ?? $emp->unit?->name ?? $unitNama,
                        'activity' => $activity,
                        'lastSeen' => $lastSeen,
                        'last_login_at' => $lastLoginTime ? $lastLoginTime->toIso8601String() : null,
                        'is_online' => $isOnline,
                        'email' => $emp->email ?? $emp->user?->email ?? '—',
                        'phone' => $emp->no_hp ?? $emp->phone ?? '—',
                        'avatar_url' => $emp->photo_url ?? $emp->avatar_url ?? $emp->foto ?? $emp->user?->avatar_url ?? null,
                        'gender' => $isFemale ? 'female' : 'male',
                    ];
                })
                ->sort(function ($a, $b) {
                    if ($a['is_online'] !== $b['is_online']) {
                        return $b['is_online'] <=> $a['is_online'];
                    }
                    if ($a['last_login_at'] !== $b['last_login_at']) {
                        return strcmp($b['last_login_at'] ?? '', $a['last_login_at'] ?? '');
                    }
                    return strcmp($a['name'], $b['name']);
                })
                ->values()
                ->toArray();
            }
        }

        // Real Activity Logs from login_events
        if (Schema::hasTable('login_events')) {
            $logQuery = DB::table('login_events')
                ->join('users', 'login_events.user_id', '=', 'users.id')
                ->leftJoin('employees', 'employees.user_id', '=', 'users.id')
                ->leftJoin('positions', 'employees.jabatan_id', '=', 'positions.id')
                ->select(
                    'login_events.id',
                    'users.name as user_name',
                    'employees.nama_lengkap as emp_name',
                    'positions.name as role_name',
                    'login_events.portal_type',
                    'login_events.login_method',
                    'login_events.status',
                    'login_events.ip_address',
                    'login_events.created_at'
                )
                ->where('login_events.status', 'success');

            if (! empty($targetUnitId)) {
                $logQuery->where(function ($q) use ($targetUnitId) {
                    $q->where('employees.unit_id', $targetUnitId)
                      ->orWhere('login_events.education_unit_id', $targetUnitId);
                });
            }

            $rawLogs = $logQuery->latest('login_events.created_at')->limit(15)->get();

            if ($rawLogs->isEmpty()) {
                $rawLogs = DB::table('login_events')
                    ->join('users', 'login_events.user_id', '=', 'users.id')
                    ->leftJoin('employees', 'employees.user_id', '=', 'users.id')
                    ->leftJoin('positions', 'employees.jabatan_id', '=', 'positions.id')
                    ->select(
                        'login_events.id',
                        'users.name as user_name',
                        'employees.nama_lengkap as emp_name',
                        'positions.name as role_name',
                        'login_events.portal_type',
                        'login_events.login_method',
                        'login_events.status',
                        'login_events.ip_address',
                        'login_events.created_at'
                    )
                    ->where('login_events.status', 'success')
                    ->latest('login_events.created_at')
                    ->limit(15)
                    ->get();
            }

            $onlineLogs = $rawLogs->map(function ($log) {
                $uName = $log->emp_name ?: ($log->user_name ?: 'Pengguna');
                $posName = $log->role_name ?: 'Pengguna Sistem';
                $cTime = \Carbon\Carbon::parse($log->created_at);
                $portalLabel = ucfirst($log->portal_type ?? 'portal');
                return [
                    'id' => 'log-' . $log->id,
                    'user' => $uName,
                    'nama' => $uName,
                    'role' => $posName,
                    'type' => 'login',
                    'action' => 'Login sukses ke ' . $portalLabel . ($log->ip_address ? ' (' . $log->ip_address . ')' : ''),
                    'time' => $cTime->diffForHumans(),
                ];
            })->toArray();
        }

        // 6. Presensi Siswa (Gabungan Absen Gerbang & Absen Pelajaran)
        $studentAttendance = [];
        if (Schema::hasTable('students')) {
            $stListQuery = Student::query()
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->where(function ($q) {
                    $q->whereNull('metadata->mutasi_type')
                      ->orWhere('metadata->mutasi_type', '!=', 'keluar');
                });

            if (! empty($targetUnitId)) {
                $stListQuery->where('unit_id', $targetUnitId);
            }
            $stList = $stListQuery->with(['educationUnit', 'kelas', 'parent'])->get();

            if ($stList->isNotEmpty()) {
                $studentIds = $stList->pluck('id');

                $gateMap = Schema::hasTable('attendances')
                    ? DB::table('attendances')
                        ->whereIn('student_id', $studentIds)
                        ->whereDate('attendance_date', $attDateToUse)
                        ->get()
                        ->keyBy('student_id')
                    : collect();

                $lmsMap = Schema::hasTable('lms_presensi')
                    ? DB::table('lms_presensi')
                        ->whereIn('siswa_id', $studentIds)
                        ->whereDate('tanggal', $attDateToUse)
                        ->get()
                        ->groupBy('siswa_id')
                    : collect();

                $studentAttendance = $stList->map(function ($st) use ($unitNama, $gateMap, $lmsMap, $attDateToUse) {
                    $sName = $st->full_name ?? $st->name ?? 'Siswa';
                    $gate = $gateMap->get($st->id);
                    $lmsList = $lmsMap->get($st->id);

                    $isGatePresent = false;
                    $gateTime = null;
                    if ($gate) {
                        $gStatus = strtolower($gate->status ?? '');
                        if (in_array($gStatus, ['present', 'hadir', 'late', 'terlambat'])) {
                            $isGatePresent = true;
                            $gateTime = $gate->check_in_time ? \Carbon\Carbon::parse($gate->check_in_time)->format('H:i') : null;
                        }
                    }

                    $isLessonPresent = false;
                    $lessonTime = null;
                    $lmsStatus = null;
                    if ($lmsList && $lmsList->isNotEmpty()) {
                        foreach ($lmsList as $lms) {
                            $lStatus = strtolower($lms->status_hadir ?? '');
                            if (in_array($lStatus, ['present', 'hadir', 'late', 'terlambat'])) {
                                $isLessonPresent = true;
                                $t = $lms->waktu_presensi ?? $lms->arrival_time;
                                if ($t) {
                                    $fmt = \Carbon\Carbon::parse($t)->format('H:i');
                                    if (! $lessonTime || $fmt < $lessonTime) {
                                        $lessonTime = $fmt;
                                    }
                                }
                            } elseif (! $lmsStatus) {
                                $lmsStatus = $lStatus;
                            }
                        }
                    }

                    // Status jika belum/tidak ada presensi (bukan hijau!)
                    $isWeekend = \Carbon\Carbon::parse($attDateToUse)->isWeekend();
                    $status = $isWeekend ? 'Libur (Akhir Pekan)' : 'Belum Presensi';
                    $waktu = '—';
                    $keterangan = $isWeekend ? 'Tidak ada kegiatan KBM (Hari Libur)' : 'Belum ada catatan presensi hari ini';

                    if ($isGatePresent || $isLessonPresent) {
                        $status = 'Hadir'; // STATUS HIJAU HANYA JIKA ADA PRESENSI!
                        $timeVal = $gateTime ?? $lessonTime;
                        $waktu = $timeVal ? $timeVal . ' WIB' : 'Tercatat';
                        if ($isGatePresent && $isLessonPresent) {
                            $keterangan = 'Hadir Lengkap (Absen Gerbang & Pelajaran)';
                        } elseif ($isGatePresent) {
                            $keterangan = 'Hadir (Absen Gerbang)';
                        } else {
                            $keterangan = 'Hadir (Absen Pelajaran)';
                        }
                    } elseif ($gate && in_array(strtolower($gate->status), ['permission', 'izin'])) {
                        $status = 'Izin';
                        $keterangan = $gate->keterangan ?? 'Izin';
                    } elseif ($lmsStatus && in_array($lmsStatus, ['permission', 'izin'])) {
                        $status = 'Izin';
                        $keterangan = 'Izin pada jam pelajaran';
                    } elseif ($gate && in_array(strtolower($gate->status), ['sick', 'sakit'])) {
                        $status = 'Sakit';
                        $keterangan = $gate->keterangan ?? 'Sakit';
                    } elseif ($lmsStatus && in_array($lmsStatus, ['sick', 'sakit'])) {
                        $status = 'Sakit';
                        $keterangan = 'Sakit pada jam pelajaran';
                    } elseif (($gate && in_array(strtolower($gate->status), ['absent', 'alpha', 'alpa'])) || ($lmsStatus && in_array($lmsStatus, ['alpa', 'alpha']))) {
                        $status = 'Alpha';
                        $keterangan = 'Tidak hadir tanpa keterangan';
                    }

                    $parentName = $st->parent?->full_name 
                        ?? data_get($st->metadata, 'orang_tua.nama_ayah') 
                        ?? data_get($st->metadata, 'orang_tua.nama_ibu') 
                        ?? '—';

                    $parentPhone = $st->parent?->phone 
                        ?? data_get($st->metadata, 'orang_tua.no_hp') 
                        ?? '—';

                    return [
                        'id' => 'st-' . $st->id,
                        'nama' => $sName,
                        'nisn' => $st->nisn ?? $st->nis ?? '—',
                        'unit_name' => $st->educationUnit?->name ?? $unitNama,
                        'kelas' => $st->kelas?->nama_kelas ?? '—',
                        'status' => $status,
                        'waktu' => $waktu,
                        'nama_ortu' => $parentName,
                        'no_hp_ortu' => $parentPhone,
                        'keterangan' => $keterangan,
                        'avatar_url' => $st->photo_url ?? $st->avatar_url ?? $st->foto ?? null,
                        'gender' => $st->gender ?? 'male',
                    ];
                })
                ->sortBy(fn ($s) => match ($s['status']) {
                    'Hadir' => 0,
                    'Izin' => 1,
                    'Sakit' => 2,
                    'Alpha' => 3,
                    default => 4,
                })
                ->values()
                ->toArray();
            }
        }

        // 6b. Presensi Realtime Guru & Tendik (Hadir, Izin Dinas Luar, Sakit, Alfa)
        $employeeAttendanceList = [];
        $empHadirCount = 0;
        $empIzinDinasCount = 0;
        $empSakitCount = 0;
        $empAlfaCount = 0;

        $isWeekPeriod = ($filters['period'] ?? '') === 'this_week';
        $hasCustomRange = ! empty($filters['date_from']) && ! empty($filters['date_to']);
        $isRangeMode = $isWeekPeriod || $hasCustomRange;

        $rangeStart = $hasCustomRange 
            ? $filters['date_from'] 
            : ($isWeekPeriod ? now()->startOfWeek()->toDateString() : $attDateToUse);
        $rangeEnd = $hasCustomRange 
            ? $filters['date_to'] 
            : ($isWeekPeriod ? now()->toDateString() : $attDateToUse);

        if (Schema::hasTable('employees')) {
            $empListQuery = (clone $employeeQuery)
                ->with(['position', 'teacher', 'teachings'])
                ->orderBy('nama_lengkap');

            $allEmployees = $empListQuery->get();

            if ($allEmployees->isNotEmpty()) {
                $allEmpIds = $allEmployees->pluck('id');

                $empAttRecords = Schema::hasTable('attendances')
                    ? ($isRangeMode
                        ? DB::table('attendances')
                            ->whereIn('employee_id', $allEmpIds)
                            ->whereBetween('attendance_date', [$rangeStart, $rangeEnd])
                            ->orderByDesc('attendance_date')
                            ->get()
                            ->groupBy('employee_id')
                        : DB::table('attendances')
                            ->whereIn('employee_id', $allEmpIds)
                            ->whereDate('attendance_date', $attDateToUse)
                            ->get()
                            ->keyBy('employee_id'))
                    : collect();

                foreach ($allEmployees as $index => $emp) {
                    $empId = (string) $emp->id;
                    $empName = $emp->nama_lengkap ?? $emp->name ?? 'Pegawai';
                    $nip = $emp->nuptk ? 'NUPTK. ' . $emp->nuptk : ($emp->niy ? 'NIY. ' . $emp->niy : ($emp->nik ? 'NIK. ' . $emp->nik : 'ID: ' . substr($empId, 0, 8)));
                    
                    $isGuru = $emp->teacher || ($emp->teachings && $emp->teachings->isNotEmpty())
                        || stripos($emp->status_pegawai ?? '', 'guru') !== false
                        || stripos($emp->position?->name ?? '', 'guru') !== false
                        || stripos($emp->position?->name ?? '', 'pendidik') !== false;

                    $roleType = $isGuru ? 'Guru' : 'Tendik';
                    $positionName = $emp->position?->name ?? $emp->status_pegawai ?? ($isGuru ? 'Tenaga Pendidik' : 'Tenaga Kependidikan');

                    $status = 'Hadir';
                    $waktu = '—';
                    $keterangan = '—';
                    $lokasi = 'SIMS Madrasah';

                    if ($isRangeMode) {
                        $records = $empAttRecords->get($emp->id, collect());
                        $att = $records->first();
                    } else {
                        $att = $empAttRecords->get($emp->id);
                    }

                    if ($att) {
                        $rawStatus = strtoupper(trim($att->status ?? ''));
                        $rawKet = $att->keterangan ?? '';

                        if (in_array($rawStatus, ['HADIR', 'PRESENT', 'TERLAMBAT', 'LATE'])) {
                            $status = 'Hadir';
                            $timeFmt = $att->check_in_time ? Carbon::parse($att->check_in_time)->format('H:i') : null;
                            $waktu = $timeFmt ? $timeFmt . ' WIB' : 'Tercatat';
                            if ($isRangeMode) {
                                $recordsCount = $records->filter(fn($r) => in_array(strtoupper(trim($r->status ?? '')), ['HADIR', 'PRESENT', 'TERLAMBAT', 'LATE']))->count();
                                $keterangan = $rawKet ?: "Hadir {$recordsCount} hari dalam periode";
                            } else {
                                $keterangan = $rawKet ?: ($rawStatus === 'TERLAMBAT' ? 'Hadir Terlambat' : 'Hadir Tepat Waktu');
                            }
                            $lokasi = $att->location ?? 'Gerbang Utama';
                            $empHadirCount++;
                        } elseif (
                            in_array($rawStatus, ['DINAS_LUAR', 'IZIN_DINAS', 'DINAS']) 
                            || stripos($rawStatus, 'DINAS') !== false 
                            || stripos($rawKet, 'dinas') !== false
                        ) {
                            $status = 'Izin Dinas Luar';
                            $waktu = 'Surat Tugas';
                            $keterangan = $rawKet ?: 'Penugasan Luar / Dinas Pendidikan';
                            $lokasi = $att->location ?? 'Luar Kampus';
                            $empIzinDinasCount++;
                        } elseif (in_array($rawStatus, ['SAKIT', 'SICK'])) {
                            $status = 'Sakit';
                            $waktu = 'Surat Dokter';
                            $keterangan = $rawKet ?: 'Izin sakit / pemulihan kesehatan';
                            $lokasi = 'Kediaman';
                            $empSakitCount++;
                        } elseif (in_array($rawStatus, ['ALPHA', 'ALFA', 'ABSENT'])) {
                            $status = 'Alfa';
                            $waktu = 'Tidak Hadir';
                            $keterangan = $rawKet ?: 'Belum ada konfirmasi kehadiran';
                            $lokasi = '—';
                            $empAlfaCount++;
                        } else {
                            $status = 'Izin Dinas Luar';
                            $waktu = 'Izin Resmi';
                            $keterangan = $rawKet ?: 'Izin dispensasi kegiatan luar';
                            $lokasi = 'Luar Kampus';
                            $empIzinDinasCount++;
                        }
                    } else {
                        // Tidak ada presensi tercatat di database untuk pegawai ini pada tanggal/periode terpilih
                        $status = 'Alfa';
                        $waktu = '—';
                        $keterangan = $isRangeMode ? 'Belum ada catatan presensi pada periode ini' : 'Belum ada catatan presensi hari ini';
                        $lokasi = '—';
                        $empAlfaCount++;
                    }

                    $employeeAttendanceList[] = [
                        'id' => $empId,
                        'nama' => $empName,
                        'nip' => $nip,
                        'role_type' => $roleType,
                        'jabatan' => $positionName,
                        'status' => $status,
                        'waktu' => $waktu,
                        'keterangan' => $keterangan,
                        'lokasi' => $lokasi,
                        'no_hp' => $emp->no_hp ?? $emp->phone_number ?? $emp->telepon ?? null,
                        'avatar_url' => $emp->foto ?? $emp->avatar_url ?? null,
                        'gender' => $emp->jenis_kelamin ?? 'L',
                    ];
                }

                // Sort urutan prioritas: Hadir -> Izin Dinas Luar -> Sakit -> Alfa
                usort($employeeAttendanceList, function ($a, $b) {
                    $order = [
                        'Hadir' => 0,
                        'Izin Dinas Luar' => 1,
                        'Sakit' => 2,
                        'Alfa' => 3,
                    ];
                    return ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);
                });
            }
        }

        $empTotalCount = count($employeeAttendanceList);
        $dateLabel = $isRangeMode
            ? (Carbon::parse($rangeStart)->translatedFormat('d M') . ' - ' . Carbon::parse($rangeEnd)->translatedFormat('d M Y'))
            : Carbon::parse($attDateToUse)->translatedFormat('d M Y');

        $employeeAttendanceSummary = [
            'total' => $empTotalCount,
            'hadir' => $empHadirCount,
            'izin_dinas' => $empIzinDinasCount,
            'sakit' => $empSakitCount,
            'alfa' => $empAlfaCount,
            'persentase_hadir' => $empTotalCount > 0 ? round(($empHadirCount / $empTotalCount) * 100, 1) : 0,
            'date_label' => $dateLabel,
            'is_range' => $isRangeMode,
            'date' => $attDateToUse,
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
        ];

        // 7. Data Profil Pengurus Yayasan (Ketua, Sekretaris, Bendahara)
        $activeYearLabel = $activeAcademicYear ? ($activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama) : null;
        $startYear = $activeAcademicYear && $activeAcademicYear->start_date 
            ? Carbon::parse($activeAcademicYear->start_date)->year 
            : (int) date('Y');
        $startPeriodYear = (int) (floor($startYear / 5) * 5);
        $endPeriodYear = $startPeriodYear + 5;
        $periodeYayasan = "{$startPeriodYear} - {$endPeriodYear}";

        $officeDefinitions = [
            [
                'id' => 'pengurus-1',
                'jabatan' => 'Ketua Yayasan',
                'code' => 'JBT-001',
                'roles' => ['Ketua Yayasan', 'ketua_yayasan', 'Yayasan'],
                'periode' => $periodeYayasan,
                'gender' => 'male',
                'badge_variant' => 'emerald',
                'role_code' => 'KETUA',
            ],
            [
                'id' => 'pengurus-2',
                'jabatan' => 'Sekretaris Yayasan',
                'code' => 'JBT-002',
                'roles' => ['Sekretaris Yayasan', 'sekretaris_yayasan'],
                'periode' => $periodeYayasan,
                'gender' => 'male',
                'badge_variant' => 'blue',
                'role_code' => 'SEKRETARIS',
            ],
            [
                'id' => 'pengurus-3',
                'jabatan' => 'Bendahara Yayasan',
                'code' => 'JBT-015',
                'roles' => ['Bendahara Yayasan', 'bendahara_yayasan'],
                'periode' => $periodeYayasan,
                'gender' => 'male',
                'badge_variant' => 'purple',
                'role_code' => 'BENDAHARA',
            ],
        ];

        $now = now();
        $onlineThreshold = $now->copy()->subMinutes(15);

        $pengurusYayasan = [];
        foreach ($officeDefinitions as $item) {
            $emp = null;
            if (Schema::hasTable('employees')) {
                $emp = Employee::query()
                    ->whereHas('position', function ($q) use ($item) {
                        $q->where('code', $item['code'])->orWhere('name', 'LIKE', '%' . $item['jabatan'] . '%');
                    })
                    ->where('nama_lengkap', 'not like', '%Super Admin%')
                    ->first();
            }

            $userObj = null;
            if (! $emp && Schema::hasTable('users')) {
                $userObj = User::query()
                    ->whereHas('roles', function ($q) use ($item) {
                        $q->whereIn('name', $item['roles']);
                    })
                    ->where('name', 'not like', '%Super Admin%')
                    ->where('name', 'not like', '%Test%')
                    ->first()
                    ?? User::query()
                        ->whereHas('roles', function ($q) use ($item) {
                            $q->whereIn('name', $item['roles']);
                        })
                        ->first();
            }

            $uid = $emp?->user_id ?? $userObj?->id;
            $lastLoginTime = null;

            if ($uid) {
                if (Schema::hasTable('personal_access_tokens')) {
                    $tok = DB::table('personal_access_tokens')
                        ->where('tokenable_id', $uid)
                        ->whereNotNull('last_used_at')
                        ->orderByDesc('last_used_at')
                        ->first();
                    if ($tok) {
                        $tokTime = \Carbon\Carbon::parse($tok->last_used_at ?? $tok->created_at);
                        if (! $lastLoginTime || $tokTime->gt($lastLoginTime)) {
                            $lastLoginTime = $tokTime;
                        }
                    }
                }
                if (Schema::hasTable('login_events')) {
                    $le = DB::table('login_events')
                        ->where('user_id', $uid)
                        ->where('status', 'success')
                        ->orderByDesc('created_at')
                        ->first();
                    if ($le) {
                        $leTime = \Carbon\Carbon::parse($le->created_at);
                        if (! $lastLoginTime || $leTime->gt($lastLoginTime)) {
                            $lastLoginTime = $leTime;
                        }
                    }
                }
            }

            $isCurrentUser = ($uid && $user && $uid === $user->id);
            $isOnline = $isCurrentUser || ($lastLoginTime && $lastLoginTime->gte($onlineThreshold));

            if ($isOnline) {
                $lastSeen = $isCurrentUser ? 'Aktif sekarang' : 'Login ' . $lastLoginTime->diffForHumans();
            } elseif ($lastLoginTime) {
                $lastSeen = 'Offline (Login ' . $lastLoginTime->diffForHumans() . ')';
            } else {
                $lastSeen = 'Offline';
            }

            $nipDisplay = $emp?->niy ? 'NIY. ' . $emp->niy : ($emp?->nik ? 'NIK. ' . $emp->nik : '-');
            $namaPengurus = $emp?->nama_lengkap ?? $userObj?->name ?? $item['jabatan'];
            $emailPengurus = $emp?->email ?? $userObj?->email ?? '-';
            $phonePengurus = $emp?->no_hp ?? $userObj?->phone ?? '-';

            $pengurusYayasan[] = [
                'id' => $item['id'],
                'jabatan' => $item['jabatan'],
                'nama' => $namaPengurus,
                'nip' => $nipDisplay,
                'email' => $emailPengurus,
                'phone' => $phonePengurus,
                'periode' => $item['periode'],
                'status' => $emp?->status ?? 'Aktif',
                'is_online' => $isOnline,
                'last_seen' => $lastSeen,
                'last_login_at' => $lastLoginTime ? $lastLoginTime->toIso8601String() : null,
                'gender' => $item['gender'],
                'avatar_url' => $emp?->foto ?? $emp?->avatar_url ?? $userObj?->avatar_url ?? null,
                'badge_variant' => $item['badge_variant'],
                'role_code' => $item['role_code'],
            ];
        }

        // 8. Data Tabel Detail Riil untuk Drilldown KPI Kepala Sekolah
        $tableSiswa = [];
        if (Schema::hasTable('students')) {
            $tableSiswa = (clone $studentQuery)
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->where(function ($q) {
                    $q->whereNull('metadata->mutasi_type')
                      ->orWhere('metadata->mutasi_type', '!=', 'keluar');
                })
                ->with(['kelas'])
                ->orderBy('full_name')
                ->limit(100)
                ->get()
                ->map(function ($st) {
                    $name = $st->full_name ?? $st->name ?? 'Siswa';
                    $nisn = $st->nisn ? 'NISN. ' . $st->nisn : ($st->nis ? 'NIS. ' . $st->nis : 'NISN/NIS: -');
                    $kelas = $st->kelas?->nama_kelas ? 'Kelas ' . $st->kelas->nama_kelas : 'Belum Ada Kelas';
                    return [
                        'id' => (string) $st->id,
                        'name' => $name,
                        'id_num' => $nisn,
                        'extra' => $kelas,
                        'status' => $st->status ?? 'Aktif',
                    ];
                })
                ->values()
                ->all();
        }

        $tableGuru = [];
        if (Schema::hasTable('employees')) {
            $tableGuru = (clone $employeeQuery)
                ->where(function ($q) use ($like) {
                    $q->whereHas('teacher')
                      ->orWhereHas('teachings')
                      ->orWhere('status_pegawai', $like, '%Guru%')
                      ->orWhereHas('position', function ($p) use ($like) {
                          $p->where('name', $like, '%Guru%')
                            ->orWhere('name', $like, '%Pendidik%');
                      });
                })
                ->with(['position'])
                ->orderBy('nama_lengkap')
                ->limit(100)
                ->get()
                ->map(function ($emp) {
                    $name = $emp->nama_lengkap ?? $emp->name ?? 'Guru';
                    $nip = $emp->nuptk ? 'NUPTK. ' . $emp->nuptk : ($emp->niy ? 'NIY. ' . $emp->niy : ($emp->nik ? 'NIK. ' . $emp->nik : 'ID: -'));
                    $pos = $emp->position?->name ?? $emp->status_pegawai ?? 'Tenaga Pendidik';
                    return [
                        'id' => (string) $emp->id,
                        'name' => $name,
                        'id_num' => $nip,
                        'extra' => $pos,
                        'status' => $emp->status ?? 'Aktif',
                    ];
                })
                ->values()
                ->all();
        }

        $tablePegawai = [];
        if (Schema::hasTable('employees')) {
            $tablePegawai = (clone $employeeQuery)
                ->with(['position'])
                ->orderBy('nama_lengkap')
                ->limit(100)
                ->get()
                ->map(function ($emp) {
                    $name = $emp->nama_lengkap ?? $emp->name ?? 'Pegawai';
                    $nik = $emp->nik ? 'NIK. ' . $emp->nik : ($emp->niy ? 'NIY. ' . $emp->niy : ($emp->nuptk ? 'NUPTK. ' . $emp->nuptk : 'ID: -'));
                    $pos = $emp->position?->name ?? $emp->status_pegawai ?? 'Staf / Tenaga Kependidikan';
                    return [
                        'id' => (string) $emp->id,
                        'name' => $name,
                        'id_num' => $nik,
                        'extra' => $pos,
                        'status' => $emp->status ?? 'Aktif',
                    ];
                })
                ->values()
                ->all();
        }

        $tableKelas = [];
        if (Schema::hasTable('tbl_kelas')) {
            $tableKelas = (clone $classQuery)
                ->with(['waliKelas'])
                ->withCount(['siswa'])
                ->orderBy('nama_kelas')
                ->limit(100)
                ->get()
                ->map(function ($k) {
                    $wali = $k->waliKelas?->nama_lengkap ?? $k->waliKelas?->name ?? 'Belum Ditentukan';
                    $kapasitas = $k->kapasitas ? "Kapasitas: {$k->kapasitas} Siswa" : 'Kapasitas: -';
                    $siswaCount = $k->siswa_count ?? 0;
                    return [
                        'id' => (string) $k->id,
                        'name' => $k->nama_kelas ?? 'Kelas',
                        'id_num' => $kapasitas,
                        'extra' => 'Wali Kelas: ' . $wali,
                        'status' => $siswaCount > 0 ? "{$siswaCount} Terisi" : 'Aktif',
                    ];
                })
                ->values()
                ->all();
        }

        return [
            'context' => [
                'role' => 'Kepala Sekolah',
                'unit' => [
                    'id' => $unit ? $unit->id : null,
                    'nama' => $unitNama,
                    'code' => $unitKode,
                    'npsn' => $unitNpsn,
                    'akreditasi' => $unitAkreditasi,
                    'alamat' => $unitAlamat,
                    'kepala_sekolah' => $unitKepalaSekolah,
                    'kontak' => $unitKontak,
                ],
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'school_info' => [
                'nama' => $unitNama,
                'npsn' => $unitNpsn,
                'kode' => $unitKode,
                'akreditasi' => $unitAkreditasi,
                'alamat' => $unitAlamat,
                'kepala_sekolah' => $unitKepalaSekolah,
                'kontak' => $unitKontak,
                'status' => ($unit?->is_active ?? true) ? 'Aktif / Operasional' : 'Operasional',
            ],
            'online_users' => $onlineUsers,
            'online_logs' => $onlineLogs,
            'student_attendance' => $studentAttendance,
            'employee_attendance' => [
                'summary' => $employeeAttendanceSummary,
                'list' => $employeeAttendanceList,
            ],
            'attendance_meta' => [
                'date' => $attDateToUse,
                'date_label' => Carbon::parse($attDateToUse)->translatedFormat('d M Y'),
                'is_today' => $attDateToUse === $today,
                'is_weekend' => Carbon::parse($attDateToUse)->isWeekend(),
                'has_activity' => ($hadirHariIni + $terlambat + $izin + $sakit + $alpha) > 0,
                'latest_active_date' => $latestActiveDate,
                'latest_active_date_label' => $latestActiveDate ? Carbon::parse($latestActiveDate)->translatedFormat('d M Y') : null,
                'total_evaluated' => count($studentAttendance),
            ],
            'pengurus_yayasan' => $pengurusYayasan,
            'kpis' => array_merge($kpis, [
                'absensi_guru_tendik' => $employeeAttendanceSummary,
            ]),
            'charts' => [
                'attendance_trend' => $attendanceTrend,
                'fullday_trend' => $fulldayTrend,
                'pesantren_trend' => $pesantrenTrend,
                'unit_schedule_type' => $isPesantren ? 'pesantren' : 'fullday',
                'schedule_label' => $isPesantren ? 'Pondok Pesantren (Senin - Sabtu)' : 'Fullday School (Senin - Jumat)',
            ],
            'tables' => [
                'announcements' => $recentAnnouncements,
                'rekap_prestasi' => $rekapPrestasi,
                'total_siswa' => $tableSiswa,
                'total_guru' => $tableGuru,
                'total_pegawai' => $tablePegawai,
                'total_kelas' => $tableKelas,
            ],
            'students_list' => $tableSiswa,
            'teachers_list' => $tableGuru,
            'staff_list' => $tablePegawai,
            'rombel_list' => $tableKelas,
            'alerts' => [],
        ];
    }
}
