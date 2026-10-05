<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScope
    ) {}

    /**
     * Get paginated list of attendance records with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::with(['student', 'employee', 'schoolClass', 'educationUnit']);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $unitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->filter()->values();
            if ($request->filled('unit_pendidikan_id')) {
                $requestedUnit = (string) $request->query('unit_pendidikan_id');
                $this->accessScope->assertEducationUnitAccess($user, $requestedUnit);
                $query->where('unit_pendidikan_id', $requestedUnit);
            } elseif ($unitIds->isNotEmpty()) {
                $query->where(function ($q) use ($unitIds) {
                    $q->whereIn('unit_pendidikan_id', $unitIds)
                        ->orWhereHas('student', fn ($sq) => $sq->whereIn('unit_id', $unitIds))
                        ->orWhereHas('schoolClass', fn ($cq) => $cq->whereIn('unit_pendidikan_id', $unitIds));
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($request->filled('unit_pendidikan_id')) {
            $query->where('unit_pendidikan_id', (string) $request->query('unit_pendidikan_id'));
        }

        // Security Guard: Pegawai Attendance Filtering
        // Hanya role di atas TU yang boleh melihat rekap kehadiran seluruh pegawai.
        // Role TU dan di bawahnya HANYA boleh melihat kehadiran miliknya sendiri.
        if ($request->query('tipe_presensi') === Attendance::TIPE_PEGAWAI || $request->filled('employee_id')) {
            if ($user && ! $this->accessScope->isAboveTu($user)) {
                $userEmpId = Employee::where('user_id', $user->id)->value('id');
                $query->where(function ($q) use ($userEmpId, $user) {
                    if ($userEmpId) {
                        $q->where('employee_id', $userEmpId);
                    }
                    if ($user->id) {
                        $q->orWhere('employee_id', $user->id);
                    }
                });
            } elseif ($request->filled('employee_id')) {
                $query->where('employee_id', $request->query('employee_id'));
            }
        }

        if ($request->filled('tipe_presensi')) {
            $query->where('tipe_presensi', $request->query('tipe_presensi'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        $this->applyPeriodFilter($query, $request);

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($sq) use ($search) {
                    $sq->where('nama_lengkap', 'like', $search)->orWhere('nisn', 'like', $search);
                })->orWhereHas('employee', function ($eq) use ($search) {
                    $eq->where('nama_lengkap', 'like', $search)->orWhere('nip', 'like', $search);
                })->orWhere('keterangan', 'like', $search);
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $data = $query->orderByDesc('attendance_date')->orderByDesc('check_in_time')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar presensi berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * Statistics overview of attendance for dashboard cards.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::query();

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $unitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->filter()->values();
            if ($request->filled('unit_pendidikan_id')) {
                $requestedUnit = (string) $request->query('unit_pendidikan_id');
                $this->accessScope->assertEducationUnitAccess($user, $requestedUnit);
                $query->where('unit_pendidikan_id', $requestedUnit);
            } elseif ($unitIds->isNotEmpty()) {
                $userEmployeeId = Employee::where('user_id', $user->id)->value('id');
                $query->where(function ($q) use ($unitIds, $userEmployeeId, $user) {
                    $q->whereIn('unit_pendidikan_id', $unitIds)
                        ->orWhereHas('student', fn ($sq) => $sq->whereIn('unit_id', $unitIds))
                        ->orWhereHas('schoolClass', fn ($cq) => $cq->whereIn('unit_pendidikan_id', $unitIds))
                        ->orWhereHas('employee', fn ($eq) => $eq->whereIn('unit_id', $unitIds));
                    if ($userEmployeeId) {
                        $q->orWhere('employee_id', $userEmployeeId);
                    }
                    if ($user?->id) {
                        $q->orWhere('employee_id', $user->id);
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($request->filled('unit_pendidikan_id')) {
            $query->where('unit_pendidikan_id', (string) $request->query('unit_pendidikan_id'));
        }

        // Security Guard: Pegawai Attendance Stats
        if ($request->query('tipe_presensi') === Attendance::TIPE_PEGAWAI || $request->filled('employee_id')) {
            if ($user && ! $this->accessScope->isAboveTu($user)) {
                $userEmpId = Employee::where('user_id', $user->id)->value('id');
                $query->where(function ($q) use ($userEmpId, $user) {
                    if ($userEmpId) {
                        $q->where('employee_id', $userEmpId);
                    }
                    if ($user->id) {
                        $q->orWhere('employee_id', $user->id);
                    }
                });
            } elseif ($request->filled('employee_id')) {
                $empId = (string) $request->query('employee_id');
                $query->where(function ($q) use ($empId) {
                    $q->where('employee_id', $empId);
                    $empUserId = Employee::where('id', $empId)->value('user_id');
                    if ($empUserId) {
                        $q->orWhere('employee_id', $empUserId);
                    }
                });
            }
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', (string) $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (string) $request->query('class_id'));
        }

        if ($request->filled('tipe_presensi')) {
            $query->where('tipe_presensi', (string) $request->query('tipe_presensi'));
        }

        $date = $this->applyPeriodFilter($query, $request);

        $total     = (clone $query)->count();
        $hadir     = (clone $query)->whereIn('status', Attendance::STATUSES_HADIR)->count();
        $dinasLuar = (clone $query)->whereIn('status', Attendance::STATUSES_DINAS)->count();
        $terlambat = (clone $query)->where('status', Attendance::STATUS_TERLAMBAT)->count();
        $sakit     = (clone $query)->where('status', Attendance::STATUS_SAKIT)->count();
        $izin      = (clone $query)->where('status', Attendance::STATUS_IZIN)->count();
        $alpha     = (clone $query)->whereIn('status', Attendance::STATUSES_ALPHA)->count();

        $persentaseHadir = $total > 0 ? round((($hadir + $dinasLuar + $terlambat) / $total) * 100, 1) : 100;

        return response()->json([
            'status' => 'success',
            'data' => [
                'tanggal' => $date,
                'total_presensi' => $total,
                'hadir' => $hadir,
                'dinas_luar' => $dinasLuar,
                'terlambat' => $terlambat,
                'sakit' => $sakit,
                'izin' => $izin,
                'alpha' => $alpha,
                'alpa' => $alpha,
                'persentase_hadir' => $persentaseHadir,
            ],
        ]);
    }

    /**
     * Check-in / Absen Masuk.
     */
    public function absenMasuk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipe_presensi' => 'nullable|string|in:Siswa,Pegawai',
            'student_id' => 'nullable|string',
            'employee_id' => 'nullable|string',
            'class_id' => 'nullable|string',
            'unit_pendidikan_id' => 'nullable|string',
            'academic_year_id' => 'nullable|string',
            'semester_id' => 'nullable|string',
            'attendance_date' => 'nullable|date',
            'status' => 'nullable|string|in:HADIR,TERLAMBAT,SAKIT,IZIN,ALPHA,DINAS_LUAR,dinas_luar,present,absent,sakit,izin,alpha',
            'attendance_method' => 'nullable|string',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'keterangan' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,heic,webp|max:10240',
            'metadata' => 'nullable|array',
        ]);

        $tanggal = $validated['attendance_date'] ?? now()->toDateString();
        $bulan = (int) now()->month;

        $user = $request->user();
        $employeeId = $validated['employee_id'] ?? null;
        if (! $employeeId && $user) {
            $employeeId = Employee::where('user_id', $user->id)->value('id');
        }

        // Resolusi unit pendidikan jika belum ada
        $unitId = $validated['unit_pendidikan_id'] ?? null;
        if (! $unitId && $employeeId) {
            $unitId = Employee::where('id', $employeeId)->value('unit_id');
        }
        if (! $unitId && $user) {
            $unitId = $user->metadata['education_unit_id'] ?? null;
        }

        // Resolusi tahun ajaran & semester jika belum ada (wajib terisi karena constraint NOT NULL pada PostgreSQL)
        $academicYearId = $validated['academic_year_id'] ?? null;
        $semesterId = $validated['semester_id'] ?? null;

        if (! $academicYearId || ! $semesterId) {
            $activeAy = AcademicYear::where('is_active', true)->first();
            $activeSem = $activeAy
                ? Semester::where('academic_year_id', $activeAy->id)
                    ->where('is_active', true)
                    ->orderBy('sequence', 'desc')
                    ->first()
                : null;

            $academicYearId = $academicYearId ?: ($activeAy?->id ?? ($user?->metadata['academic_year_id'] ?? null));
            $semesterId = $semesterId ?: ($activeSem?->id ?? ($user?->metadata['semester_id'] ?? null));
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                Attendance::STORAGE_PATH,
                Attendance::STORAGE_DISK
            );
        }

        $existing = Attendance::where('attendance_date', $tanggal)
            ->where(function ($q) use ($validated, $employeeId, $user) {
                if (! empty($validated['student_id'])) {
                    $q->where('student_id', $validated['student_id']);
                } elseif (! empty($employeeId)) {
                    $q->where('employee_id', $employeeId);
                    if ($user) {
                        $q->orWhere('employee_id', $user->id);
                    }
                }
            })
            ->first();

        if ($existing) {
            $existing->update([
                'check_in_time' => $existing->check_in_time ?? now(),
                'unit_pendidikan_id' => $existing->unit_pendidikan_id ?? $unitId,
                'academic_year_id' => $existing->academic_year_id ?? $academicYearId,
                'semester_id' => $existing->semester_id ?? $semesterId,
                'status' => strtoupper($validated['status'] ?? $existing->status ?? Attendance::STATUS_HADIR),
                'attendance_method' => $validated['attendance_method'] ?? $existing->attendance_method,
                'location' => $validated['location'] ?? $existing->location,
                'latitude' => $validated['latitude'] ?? $existing->latitude,
                'longitude' => $validated['longitude'] ?? $existing->longitude,
                'attachment_path' => $attachmentPath ?? $existing->attachment_path,
                'keterangan' => $validated['keterangan'] ?? $existing->keterangan,
                'metadata' => $validated['metadata'] ?? $existing->metadata,
            ]);
            $record = $existing;
        } else {
            $record = Attendance::create([
                'id'                => (string) Str::uuid(),
                'student_id'        => $validated['student_id'] ?? null,
                'employee_id'       => $employeeId,
                'attendance_date'   => $tanggal,
                'tipe_presensi'     => $validated['tipe_presensi']
                    ?? ($employeeId ? Attendance::TIPE_PEGAWAI : Attendance::TIPE_SISWA),
                'class_id'          => $validated['class_id'] ?? null,
                'unit_pendidikan_id' => $unitId,
                'academic_year_id'  => $academicYearId,
                'semester_id'       => $semesterId,
                'month'             => $bulan,
                'check_in_time'     => now(),
                'status'            => strtoupper($validated['status'] ?? Attendance::STATUS_HADIR),
                'attendance_method' => $validated['attendance_method'] ?? Attendance::METHOD_MANUAL,
                'location'          => $validated['location'] ?? Attendance::DEFAULT_LOCATION,
                'latitude'          => $validated['latitude'] ?? null,
                'longitude'         => $validated['longitude'] ?? null,
                'attachment_path'   => $attachmentPath,
                'keterangan'        => $validated['keterangan'] ?? Attendance::DEFAULT_KETERANGAN_MASUK,
                'metadata'          => $validated['metadata'] ?? null,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi masuk berhasil dicatat.',
            'data' => $record,
        ], 201);
    }

    /**
     * Check-out / Absen Pulang.
     */
    public function absenPulang(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attendance_id' => 'nullable|string',
            'student_id' => 'nullable|string',
            'employee_id' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        $user = $request->user();
        $employeeId = $validated['employee_id'] ?? null;
        if (! $employeeId && $user) {
            $employeeId = \App\Models\Employee::where('user_id', $user->id)->value('id');
        }

        $query = Attendance::query();
        if (! empty($validated['attendance_id'])) {
            $query->where('id', $validated['attendance_id']);
        } elseif (! empty($validated['student_id'])) {
            $query->where('student_id', $validated['student_id'])->whereDate('attendance_date', now()->toDateString());
        } elseif (! empty($employeeId)) {
            $query->where(function ($q) use ($employeeId, $user) {
                $q->where('employee_id', $employeeId);
                if ($user) {
                    $q->orWhere('employee_id', $user->id);
                }
            })->whereDate('attendance_date', now()->toDateString());
        } else {
            return response()->json(['status' => 'error', 'message' => 'Parameter presensi tidak valid.'], 400);
        }

        $attendance = $query->first();

        // Fallback: Jika belum ketemu di hari ini, periksa apakah ada presensi pegawai terbuka (check_out_time masih null)
        if (! $attendance && ! empty($employeeId)) {
            $attendance = Attendance::where(function ($q) use ($employeeId, $user) {
                $q->where('employee_id', $employeeId);
                if ($user) {
                    $q->orWhere('employee_id', $user->id);
                }
            })->whereNull('check_out_time')->orderByDesc('attendance_date')->first();
        }

        if (! $attendance) {
            return response()->json(['status' => 'error', 'message' => 'Data presensi hari ini tidak ditemukan.'], 404);
        }

        $attendance->update([
            'check_out_time' => now(),
            'location' => $validated['location'] ?? $attendance->location,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi pulang berhasil dicatat.',
            'data' => $attendance,
        ]);
    }

    /**
     * Rekapitulasi Kehadiran.
     */
    public function rekapKehadiran(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::with(['student', 'employee', 'schoolClass']);

        // ── Security guard: jika client minta data pegawai tertentu,
        //    non-AboveTu hanya boleh melihat miliknya sendiri ──
        if ($request->filled('employee_id') || $request->query('tipe_presensi') === Attendance::TIPE_PEGAWAI) {
            if ($user && ! $this->accessScope->isAboveTu($user)) {
                $userEmpId = Employee::where('user_id', $user->id)->value('id');
                $query->where(function ($q) use ($userEmpId, $user) {
                    if ($userEmpId) {
                        $q->where('employee_id', $userEmpId);
                    }
                    if ($user->id) {
                        $q->orWhere('employee_id', $user->id);
                    }
                });
            } elseif ($request->filled('employee_id')) {
                // AboveTu boleh melihat employee spesifik
                $empId = (string) $request->query('employee_id');
                $query->where(function ($q) use ($empId) {
                    $q->where('employee_id', $empId);
                    $empUserId = Employee::where('id', $empId)->value('user_id');
                    if ($empUserId) {
                        $q->orWhere('employee_id', $empUserId);
                    }
                });
            }
        } elseif ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $unitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->filter()->values();
            if ($request->filled('unit_pendidikan_id')) {
                $requestedUnit = (string) $request->query('unit_pendidikan_id');
                $this->accessScope->assertEducationUnitAccess($user, $requestedUnit);
                $query->where('unit_pendidikan_id', $requestedUnit);
            } elseif ($unitIds->isNotEmpty()) {
                $userEmployeeId = \App\Models\Employee::where('user_id', $user->id)->value('id');
                $query->where(function ($q) use ($unitIds, $userEmployeeId, $user) {
                    $q->whereIn('unit_pendidikan_id', $unitIds)
                        ->orWhereHas('student', fn ($sq) => $sq->whereIn('unit_id', $unitIds))
                        ->orWhereHas('schoolClass', fn ($cq) => $cq->whereIn('unit_pendidikan_id', $unitIds))
                        ->orWhereHas('employee', fn ($eq) => $eq->whereIn('unit_id', $unitIds));
                    if ($userEmployeeId) {
                        $q->orWhere('employee_id', $userEmployeeId);
                    }
                    if ($user?->id) {
                        $q->orWhere('employee_id', $user->id);
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($request->filled('unit_pendidikan_id')) {
            $query->where('unit_pendidikan_id', (string) $request->query('unit_pendidikan_id'));
        }

        // Filter tipe_presensi (Pegawai / Siswa)
        if ($request->filled('tipe_presensi')) {
            $query->where('tipe_presensi', (string) $request->query('tipe_presensi'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', (string) $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (string) $request->query('class_id'));
        }

        // Period filter
        $this->applyPeriodFilter($query, $request);

        // start_date / end_date manual (jika tidak pakai period)
        if (! $request->filled('period') && $request->filled('start_date')) {
            $query->whereDate('attendance_date', '>=', (string) $request->query('start_date'));
        }
        if (! $request->filled('period') && $request->filled('end_date')) {
            $query->whereDate('attendance_date', '<=', (string) $request->query('end_date'));
        }

        $perPage = (int) $request->query('per_page', 100);
        $records = $query->orderByDesc('attendance_date')->limit($perPage)->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Rekapitulasi kehadiran berhasil diambil.',
            'records' => $records,
            'data' => $records,
        ]);
    }

    /**
     * Detail presensi.
     */
    public function show(string $id): JsonResponse
    {
        $attendance = Attendance::with(['student', 'employee', 'schoolClass', 'educationUnit'])->find($id);

        if (! $attendance) {
            return response()->json(['status' => 'error', 'message' => 'Presensi tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $attendance]);
    }

    /**
     * Manual Input / Create Presensi.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->absenMasuk($request);
    }

    /**
     * Update presensi.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $attendance = Attendance::find($id);

        if (! $attendance) {
            return response()->json(['status' => 'error', 'message' => 'Presensi tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'status' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'check_in_time' => 'nullable|date',
            'check_out_time' => 'nullable|date',
        ]);

        $attendance->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi berhasil diperbarui.',
            'data' => $attendance,
        ]);
    }

    /**
     * Delete presensi.
     */
    public function destroy(string $id): JsonResponse
    {
        $attendance = Attendance::find($id);

        if (! $attendance) {
            return response()->json(['status' => 'error', 'message' => 'Presensi tidak ditemukan.'], 404);
        }

        $attendance->delete();

        return response()->json(['status' => 'success', 'message' => 'Presensi berhasil dihapus.']);
    }

    /**
     * Apakah user memiliki role di atas TU?
     * Didelegasikan ke AccessScopeService agar definisi role tidak tersebar.
     *
     * @deprecated Gunakan $this->accessScope->isAboveTu($user) secara langsung.
     */
    private function isAboveTuUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->accessScope->isAboveTu($user);
    }

    private function applyPeriodFilter($query, Request $request): string
    {
        $period = (string) $request->query('period');
        $dateLabel = now()->toDateString();

        if ($period === 'daily' || $period === 'harian') {
            $date = (string) ($request->query('date') ?? now()->toDateString());
            $query->whereDate('attendance_date', $date);
            $dateLabel = $date;
        } elseif ($period === 'weekly' || $period === 'mingguan') {
            $start = now()->startOfWeek()->toDateString();
            $end = now()->endOfWeek()->toDateString();
            $query->whereBetween('attendance_date', [$start, $end]);
            $dateLabel = "$start s/d $end";
        } elseif ($period === 'monthly' || $period === 'bulanan') {
            $month = (int) ($request->query('month') ?? now()->month);
            $query->where('month', $month);
            if ($request->filled('year')) {
                $query->whereYear('attendance_date', (int) $request->query('year'));
            } else {
                $query->whereYear('attendance_date', now()->year);
            }
            $dateLabel = 'Bulan ' . $month;
        } elseif ($period === 'semester') {
            $semId = $request->query('semester_id') ?? Semester::where('is_active', true)->value('id');
            if ($semId) {
                $query->where('semester_id', $semId);
                $semName = Semester::where('id', $semId)->value('name');
                $dateLabel = $semName ? "Semester $semName" : 'Semester Aktif';
            }
        } elseif ($period === 'yearly' || $period === 'tahunan') {
            $ayId = $request->query('academic_year_id') ?? AcademicYear::where('is_active', true)->value('id');
            if ($ayId) {
                $query->where('academic_year_id', $ayId);
                $ayName = AcademicYear::where('id', $ayId)->value('name');
                $dateLabel = $ayName ? "Tahun Ajaran $ayName" : 'Tahun Ajaran Aktif';
            }
        } else {
            if ($request->filled('date')) {
                $date = (string) $request->query('date');
                $query->whereDate('attendance_date', $date);
                $dateLabel = $date;
            } elseif ($request->filled('start_date') || $request->filled('end_date')) {
                if ($request->filled('start_date')) {
                    $query->whereDate('attendance_date', '>=', (string) $request->query('start_date'));
                }
                if ($request->filled('end_date')) {
                    $query->whereDate('attendance_date', '<=', (string) $request->query('end_date'));
                }
                $dateLabel = ($request->query('start_date') ?? '') . ' s/d ' . ($request->query('end_date') ?? now()->toDateString());
            } elseif (! $request->filled('employee_id') && ! $request->filled('student_id')) {
                $date = now()->toDateString();
                $query->whereDate('attendance_date', $date);
                $dateLabel = $date;
            }
        }

        if ($request->filled('month') && ! in_array($period, ['monthly', 'bulanan'], true)) {
            $query->where('month', (int) $request->query('month'));
        }

        if ($request->filled('academic_year_id') && ! in_array($period, ['yearly', 'tahunan'], true)) {
            $query->where('academic_year_id', (string) $request->query('academic_year_id'));
        }

        if ($request->filled('semester_id') && $period !== 'semester') {
            $query->where('semester_id', (string) $request->query('semester_id'));
        }

        return $dateLabel;
    }
}
