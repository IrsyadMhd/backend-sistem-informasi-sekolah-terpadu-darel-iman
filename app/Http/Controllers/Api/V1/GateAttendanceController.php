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
        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->query('date'));
        } else {
            $query->whereDate('attendance_date', today());
        }
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
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
            'attendance_method' => 'nullable|string|in:QRCODE,RFID,FACE,FINGERPRINT,MANUAL,qr,rfid,face,fingerprint,manual',
            'location' => 'nullable|string',
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
            'attendance_method' => 'nullable|string|in:QRCODE,RFID,FACE,FINGERPRINT,MANUAL,qr,rfid,face,fingerprint,manual',
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
        $date = $request->query('date', today()->toDateString());
        $this->assertRequestedUnitScope($request);
        $unitId = $this->resolveUserUnitScope($request);

        $query = Attendance::whereDate('attendance_date', $date);
        if ($unitId) {
            $query->where('unit_pendidikan_id', $unitId);
        }

        $totalScanned = (clone $query)->count();
        $hadir = (clone $query)->whereIn('status', ['HADIR', 'HADIR_DALAM_TOLERANSI'])->count();
        $terlambat = (clone $query)->where('status', 'TERLAMBAT')->count();
        $izin = (clone $query)->where('status', 'IZIN')->count();
        $sakit = (clone $query)->where('status', 'SAKIT')->count();
        $belumHadir = (clone $query)->where('status', 'BELUM_HADIR')->count();
        $alpha = (clone $query)->where('status', 'ALPHA')->count();
        $checkedOut = (clone $query)->whereNotNull('check_out_time')->count();

        // Calculate total active students in unit
        $studentQuery = Student::query()->where('is_active', true);
        if ($unitId) {
            $studentQuery->where(function ($query) use ($unitId) {
                $query->where('unit_id', $unitId)
                    ->orWhereHas('kelas', fn ($class) => $class->where('unit_pendidikan_id', $unitId));
            });
        }
        $totalStudents = $studentQuery->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'tanggal' => $date,
                'total_siswa' => $totalStudents,
                'total_scanned' => $totalScanned,
                'hadir' => $hadir,
                'terlambat' => $terlambat,
                'izin' => $izin,
                'sakit' => $sakit,
                'belum_hadir' => $belumHadir,
                'alpha' => $alpha,
                'sudah_pulang' => $checkedOut,
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

        $roles = $user->getRoleNames()->map(fn (string $role) => $this->normalizeRole($role));
        $isMultiUnit = $roles->intersect([
            'superadmin', 'super_admin', 'admin', 'yayasan', 'ketuayayasan',
            'pengurusayayasan', 'sekretarisyayasan', 'bendaharayayasan',
            'kepalabidangpendidikan', 'divisipendidikan', 'divisikurikulum',
            'divisikesiswaan', 'divisibahasa', 'divisiprogramkhusus',
        ])->isNotEmpty();

        if ($isMultiUnit) {
            return $request->query('unit_id');
        }

        // Single unit restriction for pegawai, guru, etc.
        $userUnitId = $user->education_unit_id ?? $user->unit_id ?? $user->employee?->unit_id;

        return $userUnitId ?: '__none__';
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
