<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DormitoryPermit;
use App\Models\ParentModel;
use App\Models\Student;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DormitoryPermitController extends Controller
{
    public function __construct(
        protected AccessScopeService $accessScope,
    ) {}

    /**
     * Assert that the authenticated user has access to this permit's unit.
     */
    protected function assertPermitAccess(DormitoryPermit $permit): void
    {
        $user = request()->user();
        if (! $user || $this->accessScope->hasGlobalScope($user)) {
            return;
        }

        $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
        abort_unless(
            $accessibleUnitIds->contains($permit->unit_pendidikan_id),
            403,
            'Akses ditolak: Izin santri berada di luar unit pendidikan Anda.'
        );
    }

    /**
     * List all dormitory permits (Musyrif / Staff View)
     */
    public function index(Request $request): JsonResponse
    {
        $query = DormitoryPermit::query()->with([
            'student:id,full_name,nis,nisn,class_id,kelas_id',
            'student.kelas:id,nama_kelas,kode_kelas',
            'educationUnit:id,name,code',
            'musyrif:id,nama_lengkap',
        ]);

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            $query->whereIn('unit_pendidikan_id', $accessibleUnitIds);
        }

        if ($request->filled('unit_id')) {
            $requestedUnitId = $request->query('unit_id');
            if ($user && ! $this->accessScope->hasGlobalScope($user)) {
                $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
                abort_unless($accessibleUnitIds->contains($requestedUnitId), 403, 'Akses unit ditolak.');
            }
            $query->where('unit_pendidikan_id', $requestedUnitId);
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_departure_at', $request->query('date'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('permit_number', 'ilike', $search)
                    ->orWhere('guardian_name', 'ilike', $search)
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('full_name', 'ilike', $search)
                            ->orWhere('nis', 'ilike', $search)
                            ->orWhere('nisn', 'ilike', $search);
                    });
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $permits = $query->orderByDesc('created_at')->paginate($perPage);

        $base = DormitoryPermit::query();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            $base->whereIn('unit_pendidikan_id', $accessibleUnitIds);
        }
        if ($request->filled('unit_id')) {
            $base->where('unit_pendidikan_id', $request->query('unit_id'));
        }

        $stats = [
            'total_permits' => (clone $base)->count(),
            'currently_out' => (clone $base)->where('status', 'keluar')->count(),
            'returned_ontime' => (clone $base)->where('return_status', 'tepat_waktu')->count(),
            'returned_late' => (clone $base)->where('return_status', 'terlambat')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Data perizinan santri asrama berhasil diambil.',
            'data' => $permits->items(),
            'meta' => [
                'current_page' => $permits->currentPage(),
                'last_page' => $permits->lastPage(),
                'per_page' => $permits->perPage(),
                'total' => $permits->total(),
            ],
            'statistik' => $stats,
        ]);
    }

    /**
     * Create a new permit for a student
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'permit_type' => 'required|string|in:pesiar_mingguan,kunjungan_ortu,pulang_berkala,izin_khusus',
            'destination' => 'nullable|string|max:255',
            'scheduled_departure_at' => 'required|date',
            'scheduled_return_at' => 'required|date|after:scheduled_departure_at',
            'guardian_name' => 'required|string|max:150',
            'guardian_phone' => 'required|string|max:30',
            'guardian_relation' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $studentUnitId = $student->unit_id ?: $student->kelas?->unit_pendidikan_id;

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            abort_unless(
                $accessibleUnitIds->contains($studentUnitId),
                403,
                'Akses ditolak: Santri berada di luar unit pendidikan Anda.'
            );
        }

        $permitNumber = 'BP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $permit = DormitoryPermit::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'unit_pendidikan_id' => $studentUnitId,
            'permit_number' => $permitNumber,
            'permit_type' => $validated['permit_type'],
            'destination' => $validated['destination'] ?? 'Rumah Orang Tua',
            'scheduled_departure_at' => $validated['scheduled_departure_at'],
            'scheduled_return_at' => $validated['scheduled_return_at'],
            'guardian_name' => $validated['guardian_name'],
            'guardian_phone' => $validated['guardian_phone'],
            'guardian_relation' => $validated['guardian_relation'] ?? 'Orang Tua',
            'status' => 'disetujui',
            'return_status' => 'belum_kembali',
            'notes' => $validated['notes'] ?? null,
            'approved_by_musyrif_id' => $request->user()?->employee?->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Izin kepulangan/pesiar {$student->full_name} ({$permit->permit_number}) berhasil diterbitkan.",
            'data' => $permit->load(['student', 'educationUnit']),
        ], 201);
    }

    /**
     * Tap Gate Out (Santri meninggalkan asrama)
     */
    public function checkout(Request $request, DormitoryPermit $permit): JsonResponse
    {
        $this->assertPermitAccess($permit);

        if ($permit->status === 'keluar') {
            return response()->json([
                'status' => 'error',
                'message' => 'Santri sudah berstatus keluar asrama.',
            ], 422);
        }

        $now = now();
        $permit->update([
            'status' => 'keluar',
            'actual_departure_at' => $now,
            'checked_out_by' => $request->user()?->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Santri {$permit->student?->full_name} berhasil check-out keluar gerbang pada pukul " . $now->format('H:i') . ' WIB.',
            'data' => $permit->fresh(['student']),
        ]);
    }

    /**
     * Return Check-in (Absensi santri kembali ke pondok pesantren)
     */
    public function returnCheckin(Request $request, DormitoryPermit $permit): JsonResponse
    {
        $this->assertPermitAccess($permit);

        if ($permit->status === 'kembali') {
            return response()->json([
                'status' => 'error',
                'message' => 'Santri sudah tercatat kembali ke asrama sebelumnya.',
            ], 422);
        }

        $now = now();
        $scheduledReturn = Carbon::parse($permit->scheduled_return_at);
        $isLate = $now->greaterThan($scheduledReturn);
        $lateMinutes = $isLate ? (int) round(abs($scheduledReturn->diffInMinutes($now))) : 0;

        $permit->update([
            'status' => 'kembali',
            'actual_return_at' => $now,
            'return_status' => $isLate ? 'terlambat' : 'tepat_waktu',
            'late_minutes' => $lateMinutes,
            'checked_in_by' => $request->user()?->id,
        ]);

        $statusMsg = $isLate
            ? "Santri kembali TERLAMBAT {$lateMinutes} menit (Batas: " . $scheduledReturn->format('H:i') . ', Aktual: ' . $now->format('H:i') . ').'
            : "Santri kembali TEPAT WAKTU pada pukul " . $now->format('H:i') . ' WIB.';

        return response()->json([
            'status' => 'success',
            'message' => "Absensi kepulangan {$permit->student?->full_name} berhasil dicatat. {$statusMsg}",
            'data' => $permit->fresh(['student', 'educationUnit']),
        ]);
    }

    /**
     * Parent Portal endpoint (Untuk orang tua melihat izin kepulangan santri)
     */
    public function portalIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $childId = $request->query('child_id');

        $query = DormitoryPermit::query()->with([
            'student:id,full_name,nis',
            'educationUnit:id,name',
        ]);

        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            $childIds = $parent->students->concat($parent->studentsPivot)->pluck('id');
            if ($childId) {
                abort_unless($childIds->contains($childId), 403, 'Akses ditolak: Santri bukan anak Anda.');
                $query->where('student_id', $childId);
            } else {
                $query->whereIn('student_id', $childIds);
            }
        } elseif ($childId) {
            $query->where('student_id', $childId);
        } else {
            $student = Student::where('user_id', $user->id)->first();
            if ($student) {
                $query->where('student_id', $student->id);
            }
        }

        $permits = $query->orderByDesc('scheduled_departure_at')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $permits->items(),
            'meta' => [
                'total' => $permits->total(),
            ],
        ]);
    }
}
