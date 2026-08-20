<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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

        // Filters
        if ($request->filled('tipe_presensi')) {
            $query->where('tipe_presensi', $request->query('tipe_presensi'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->query('date'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('attendance_date', '>=', $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('attendance_date', '<=', $request->query('end_date'));
        }

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
        $date = $request->query('date', now()->toDateString());
        $query = Attendance::whereDate('attendance_date', $date);

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

        $total = (clone $query)->count();
        $hadir = (clone $query)->whereIn('status', ['HADIR', 'present'])->count();
        $terlambat = (clone $query)->where('status', 'TERLAMBAT')->count();
        $sakit = (clone $query)->where('status', 'SAKIT')->count();
        $izin = (clone $query)->where('status', 'IZIN')->count();
        $alpha = (clone $query)->whereIn('status', ['ALPHA', 'absent'])->count();

        $persentaseHadir = $total > 0 ? round((($hadir + $terlambat) / $total) * 100, 1) : 100;

        return response()->json([
            'status' => 'success',
            'data' => [
                'tanggal' => $date,
                'total_presensi' => $total,
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'sakit' => $sakit,
                'izin' => $izin,
                'alpha' => $alpha,
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
            'status' => 'nullable|string|in:HADIR,TERLAMBAT,SAKIT,IZIN,ALPHA,present,absent',
            'attendance_method' => 'nullable|string',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'keterangan' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $tanggal = $validated['attendance_date'] ?? now()->toDateString();
        $bulan = (int) now()->month;

        $record = Attendance::updateOrCreate(
            [
                'student_id' => $validated['student_id'] ?? null,
                'employee_id' => $validated['employee_id'] ?? null,
                'attendance_date' => $tanggal,
            ],
            [
                'id' => (string) Str::uuid(),
                'tipe_presensi' => $validated['tipe_presensi'] ?? ($validated['employee_id'] ? 'Pegawai' : 'Siswa'),
                'class_id' => $validated['class_id'] ?? null,
                'unit_pendidikan_id' => $validated['unit_pendidikan_id'] ?? null,
                'academic_year_id' => $validated['academic_year_id'] ?? null,
                'semester_id' => $validated['semester_id'] ?? null,
                'month' => $bulan,
                'check_in_time' => now(),
                'status' => strtoupper($validated['status'] ?? 'HADIR'),
                'attendance_method' => $validated['attendance_method'] ?? 'MANUAL',
                'location' => $validated['location'] ?? 'Sims Mobile App',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'keterangan' => $validated['keterangan'] ?? 'Absen masuk terdaftar.',
                'metadata' => $validated['metadata'] ?? null,
            ]
        );

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

        $query = Attendance::query();
        if (! empty($validated['attendance_id'])) {
            $query->where('id', $validated['attendance_id']);
        } elseif (! empty($validated['student_id'])) {
            $query->where('student_id', $validated['student_id'])->whereDate('attendance_date', now()->toDateString());
        } elseif (! empty($validated['employee_id'])) {
            $query->where('employee_id', $validated['employee_id'])->whereDate('attendance_date', now()->toDateString());
        } else {
            return response()->json(['status' => 'error', 'message' => 'Parameter presensi tidak valid.'], 400);
        }

        $attendance = $query->first();

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

        if ($request->filled('student_id')) {
            $query->where('student_id', (string) $request->query('student_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (string) $request->query('employee_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (string) $request->query('class_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('attendance_date', '>=', (string) $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('attendance_date', '<=', (string) $request->query('end_date'));
        }

        $records = $query->orderByDesc('attendance_date')->limit(100)->get();

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
}
