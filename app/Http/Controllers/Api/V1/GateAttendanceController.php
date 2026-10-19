<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Services\AccessScopeService;
use App\Services\GateAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GateAttendanceController extends Controller
{
    public function __construct(
        private GateAttendanceService $gateService,
        private AccessScopeService $accessScope,
    ) {}

    /**
     * Get paginated gate check-in / check-out logs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['student', 'educationUnit', 'schoolClass']);

        $this->assertRequestedUnitScope($request);
        $effectiveUnitId = $this->resolveUserUnitScope($request);
        if ($effectiveUnitId) {
            $query->where('unit_pendidikan_id', $effectiveUnitId);
        }
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [$request->query('date_from'), $request->query('date_to')]);
        } elseif ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->query('date'));
        } else {
            $query->whereDate('attendance_date', today());
        }
        if ($request->filled('status')) {
            $statusVal = strtoupper($request->query('status'));
            if ($statusVal === 'HADIR') {
                $query->whereIn('status', ['HADIR', 'HADIR_DALAM_TOLERANSI', 'TEPAT_WAKTU']);
            } elseif ($statusVal === 'ALPHA' || $statusVal === 'ALPA') {
                $query->whereIn('status', ['ALPHA', 'ALPA']);
            } elseif ($statusVal === 'IZIN') {
                $query->whereIn('status', ['IZIN', 'SAKIT']);
            } else {
                $query->where('status', $statusVal);
            }
        }
        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', $search)
                    ->orWhere('nisn', 'like', $search)
                    ->orWhere('nis', 'like', $search);
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $data = $query->orderByDesc('check_in_time')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Data absensi gerbang berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * Gate Check-in scan endpoint (QR, RFID, Face, Manual).
     */
    public function scanCheckIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'nullable|string',
            'qr_token' => 'nullable|string|max:2048',
            'card_number' => 'nullable|string',
            'nisn' => 'nullable|string',
            'nis' => 'nullable|string',
            'unit_id' => 'nullable|uuid',
            'attendance_date' => 'nullable|date',
            'check_in_time' => 'nullable|string',
            'attendance_method' => 'nullable|string|in:QRCODE,RFID,FACE,FINGERPRINT,MANUAL,TAP_LOKASI,TAP_GPS,qr,rfid,face,fingerprint,manual,tap_lokasi,tap_gps',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'photo_snapshot' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $this->assertStudentScope($request, $validated);

        $result = $this->gateService->recordCheckIn($validated, $request->user()?->id);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Gate Check-out scan endpoint.
     */
    public function scanCheckOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'nullable|string',
            'qr_token' => 'nullable|string|max:2048',
            'card_number' => 'nullable|string',
            'nisn' => 'nullable|string',
            'nis' => 'nullable|string',
            'unit_id' => 'nullable|uuid',
            'attendance_date' => 'nullable|date',
            'check_out_time' => 'nullable|string',
            'attendance_method' => 'nullable|string|in:QRCODE,RFID,FACE,FINGERPRINT,MANUAL,TAP_LOKASI,TAP_GPS,qr,rfid,face,fingerprint,manual,tap_lokasi,tap_gps',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
            'is_early' => 'nullable|boolean',
            'pickup_person' => 'nullable|string',
            'pickup_relation' => 'nullable|string',
            'pickup_verification' => 'nullable|string',
            'photo_snapshot' => 'nullable|string',
            'approved_by' => 'nullable|string',
        ]);

        $this->assertStudentScope($request, $validated);

        $result = $this->gateService->recordCheckOut($validated, $request->user()?->id);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Overview stats for gate check-in/out terminal.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->assertRequestedUnitScope($request);
        $unitId = $this->resolveUserUnitScope($request);

        $query = Attendance::query();
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [$request->query('date_from'), $request->query('date_to')]);
            $dateDisplay = $request->query('date_from').' s/d '.$request->query('date_to');
        } elseif ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->query('date'));
            $dateDisplay = $request->query('date');
        } else {
            $query->whereDate('attendance_date', today());
            $dateDisplay = today()->toDateString();
        }

        if ($unitId) {
            $query->where(function ($q) use ($unitId) {
                $q->where('unit_pendidikan_id', $unitId)
                    ->orWhereHas('student', fn ($sq) => $sq->where('unit_id', $unitId));
            });
        }
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        $totalScanned = (clone $query)->count();
        $hadir = (clone $query)->whereIn('status', ['HADIR', 'HADIR_DALAM_TOLERANSI', 'TEPAT_WAKTU'])->count();
        $terlambat = (clone $query)->where('status', 'TERLAMBAT')->count();
        $izin = (clone $query)->where('status', 'IZIN')->count();
        $sakit = (clone $query)->where('status', 'SAKIT')->count();
        $belumHadir = (clone $query)->where('status', 'BELUM_HADIR')->count();
        $alpha = (clone $query)->whereIn('status', ['ALPHA', 'ALPA'])->count();
        $checkedOut = (clone $query)->whereNotNull('check_out_time')->count();
        $classifiedCount = $hadir + $terlambat + $izin + $sakit + $belumHadir + $alpha;
        $lainnya = max(0, $totalScanned - $classifiedCount);
        $persentaseKehadiran = $totalScanned > 0 ? round(($hadir / $totalScanned) * 100, 1) : 0;

        // Calculate total active students in unit
        $studentQuery = Student::query()->where('is_active', true);
        if ($unitId) {
            $studentQuery->where(function ($query) use ($unitId) {
                $query->where('unit_id', $unitId)
                    ->orWhereHas('kelas', fn ($class) => $class->where('unit_pendidikan_id', $unitId));
            });
        }
        if ($request->filled('class_id')) {
            $studentQuery->where('kelas_id', $request->query('class_id'));
        }
        $totalStudents = $studentQuery->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'tanggal' => $dateDisplay,
                'total_siswa' => $totalStudents,
                'total_scanned' => $totalScanned,
                'total_data' => $totalScanned,
                'hadir' => $hadir,
                'tepat_waktu' => $hadir,
                'terlambat' => $terlambat,
                'izin' => $izin,
                'sakit' => $sakit,
                'belum_hadir' => $belumHadir,
                'alpha' => $alpha,
                'alpa' => $alpha,
                'lainnya' => $lainnya,
                'tidak_terklasifikasi' => $lainnya,
                'sudah_pulang' => $checkedOut,
                'persentase_kehadiran' => $persentaseKehadiran,
            ],
        ]);
    }

    /**
     * Get gate attendance schedule settings (jam masuk & jam pulang).
     */
    public function getScheduleConfig(Request $request): JsonResponse
    {
        $this->assertRequestedUnitScope($request);
        $unitId = $this->resolveUserUnitScope($request);
        $config = $this->gateService->getScheduleConfig($unitId);

        return response()->json([
            'status' => 'success',
            'data' => $config,
        ]);
    }

    /**
     * Get all education units' gate attendance schedule settings.
     */
    public function getAllScheduleConfigs(Request $request): JsonResponse
    {
        $all = $this->gateService->getAllUnitsScheduleConfig();
        $unitId = $this->resolveUserUnitScope($request);

        if ($unitId !== null) {
            $all['units'] = collect($all['units'])
                ->where('unit_id', $unitId)
                ->values()
                ->all();
        }

        return response()->json([
            'status' => 'success',
            'data' => $all,
        ]);
    }

    /**
     * Save gate attendance schedule settings.
     */
    public function saveScheduleConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'nullable|string',
            'jam_masuk' => 'required|string',
            'toleransi_menit' => 'required|integer|min:0|max:120',
            'jam_pulang' => 'required|string',
            'jam_cutoff_alpha' => 'nullable|string',
        ]);

        $unitId = $this->resolveUserUnitScope($request) ?: ($validated['unit_id'] ?? null);
        if ($unitId && $request->user()) {
            $this->accessScope->assertEducationUnitAccess($request->user(), $unitId);
        }
        $result = $this->gateService->saveScheduleConfig($unitId, $validated);

        return response()->json($result);
    }

    /**
     * Resolve effective unit scope based on user role.
     * Superadmin, Admin, Yayasan, Divisi Pendidikan have multi-unit access.
     * Pegawai, Guru, etc. are restricted to their assigned education unit.
     */
    private function resolveUserUnitScope(Request $request): ?string
    {
        $user = $request->user();
        if (! $user) {
            return $request->query('unit_id');
        }

        if ($this->accessScope->hasGlobalScope($user)) {
            return $request->query('unit_id');
        }

        $unitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->filter()->values();
        if ($request->filled('unit_id')) {
            $this->accessScope->assertEducationUnitAccess($user, (string) $request->query('unit_id'));

            return (string) $request->query('unit_id');
        }

        return $unitIds->first() ?: ($user->education_unit_id ?? $user->unit_id ?? $user->employee?->unit_id ?? '__none__');
    }

    private function assertStudentScope(Request $request, array $data): void
    {
        $student = $this->gateService->resolveStudent($data);

        if (! $student || ! $request->user()) {
            return;
        }

        $user = $request->user();
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Superadmin', 'super_admin'])) {
            if (! empty($data['unit_id'])) {
                abort_unless(
                    $this->accessScope->accessibleEducationUnits($user)->whereKey($data['unit_id'])->exists(),
                    403,
                    'Unit terminal tidak berada dalam cakupan akun.'
                );
            }
        } elseif (! empty($data['unit_id'])) {
            abort_unless(
                $this->accessScope->accessibleEducationUnits($user)->whereKey($data['unit_id'])->exists(),
                403,
                'Unit terminal tidak berada dalam cakupan akun.'
            );
        }

        $unitId = $student->unit_id ?? $student->education_unit_id ?? $student->kelas?->unit_pendidikan_id;
        if (! empty($data['unit_id'])) {
            abort_unless((string) $unitId === (string) $data['unit_id'], 422, 'Siswa berada di luar unit terminal yang dipilih.');
        }

        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Superadmin', 'super_admin'])) {
            return;
        }

        // Jika user adalah Siswa atau Orang Tua, verifikasi kepemilikan siswa
        if ($user->hasAnyRole(['Siswa', 'siswa', 'student'])) {
            $myStudentId = $user->student?->id ?? $user->student_id;
            if ($myStudentId || $student->user_id) {
                abort_unless((string) $student->user_id === (string) $user->id || (string) $student->id === (string) $myStudentId, 403, 'Akses presensi siswa tidak valid.');
                return;
            }
        }

        if ($user->hasAnyRole(['Orang Tua', 'orang_tua', 'orang-tua', 'Orangtua', 'Wali Murid', 'parent'])) {
            $parent = \App\Models\ParentModel::where('user_id', $user->id)->first();
            if ($parent) {
                $isChild = \App\Models\Student::where('id', $student->id)
                    ->where(fn ($q) => $q->where('parent_id', $parent->id)->orWhereHas('parentsPivot', fn ($p) => $p->whereKey($parent->id)))
                    ->exists();
                abort_unless($isChild, 403, 'Siswa bukan anak dari akun orang tua ini.');
                return;
            }
        }

        abort_unless(
            $unitId && $this->accessScope->accessibleEducationUnits($user)->whereKey($unitId)->exists(),
            403,
            'Siswa berada di luar cakupan unit akun.'
        );
    }

    private function assertRequestedUnitScope(Request $request): void
    {
        if ($request->filled('unit_id') && $request->user()) {
            $this->accessScope->assertEducationUnitAccess($request->user(), (string) $request->query('unit_id'));
        }
    }

    private function normalizeRole(string $role): string
    {
        return strtolower((string) preg_replace('/[\s_-]+/', '', $role));
    }
}
