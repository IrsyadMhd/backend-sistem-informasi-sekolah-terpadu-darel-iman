<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DormitoryPermit;
use App\Models\ParentModel;
use App\Models\Student;
use App\Services\AccessScopeService;
use App\Services\CodeGeneratorService;
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

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = $request->query('status');
            if ($status === 'active') {
                $query->where(function ($q) {
                    $q->where('presence_status', 'di_luar')
                      ->orWhere('presence_status', 'berobat')
                      ->orWhere('presence_status', 'pulang')
                      ->orWhere('status', 'keluar');
                });
            } elseif ($status === 'medical') {
                $query->where(function ($q) {
                    $q->where('permit_type', 'sakit_berobat')
                      ->orWhere('presence_status', 'berobat')
                      ->orWhere('is_emergency', true);
                });
            } elseif ($status === 'late') {
                $query->where(function ($q) {
                    $q->where('return_status', 'terlambat')
                      ->orWhere('deadline_status', 'overdue')
                      ->orWhere('late_minutes', '>', 0);
                });
            } elseif ($status === 'approval') {
                $query->where(function ($q) {
                    $q->where('status', 'diajukan')
                      ->orWhere('extension_status', 'requested');
                });
            } elseif ($status === 'returned') {
                $query->where(function ($q) {
                    $q->where('status', 'kembali')
                      ->orWhere('presence_status', 'di_pondok');
                });
            }
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
            'currently_out' => (clone $base)->where(function ($q) {
                $q->where('presence_status', 'di_luar')
                  ->orWhere('presence_status', 'berobat')
                  ->orWhere('presence_status', 'pulang')
                  ->orWhere('status', 'keluar');
            })->count(),
            'returned_ontime' => (clone $base)->where('return_status', 'tepat_waktu')->count(),
            'returned_late' => (clone $base)->where('return_status', 'terlambat')->count(),
            'in_dormitory' => (clone $base)->where(function ($q) {
                $q->where('presence_status', 'di_pondok')
                  ->orWhere('status', 'kembali');
            })->count(),
            'medical_permits' => (clone $base)->where(function ($q) {
                $q->where('permit_type', 'sakit_berobat')
                  ->orWhere('presence_status', 'berobat')
                  ->orWhere('is_emergency', true);
            })->count(),
            'pending_approval' => (clone $base)->where(function ($q) {
                $q->where('status', 'diajukan')
                  ->orWhere('extension_status', 'requested');
            })->count(),
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
     * Create a new permit for a student (Musyrif / Staff View)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'permit_type' => 'required|string|in:pulang_terjadwal,sakit_berobat,pesiar_mingguan,kunjungan_ortu,pulang_berkala,izin_khusus',
            'destination' => 'nullable|string|max:255',
            'scheduled_departure_at' => 'required|date',
            'scheduled_return_at' => 'required|date|after:scheduled_departure_at',
            'guardian_name' => 'required|string|max:150',
            'guardian_phone' => 'required|string|max:30',
            'guardian_relation' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_emergency' => 'nullable|boolean',
            'medical_complaint' => 'nullable|string|max:500',
            'medical_destination' => 'nullable|string|max:255',
            'medical_escort_name' => 'nullable|string|max:150',
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

        $permitNumber = CodeGeneratorService::generate('permit_number', $studentUnitId);
        $isMedical = $validated['permit_type'] === 'sakit_berobat' || ! empty($validated['is_emergency']);

        $permit = DormitoryPermit::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'unit_pendidikan_id' => $studentUnitId,
            'permit_number' => $permitNumber,
            'permit_type' => $validated['permit_type'],
            'destination' => $validated['destination'] ?? ($isMedical ? 'Fasilitas Kesehatan / Rumah' : 'Rumah Orang Tua'),
            'scheduled_departure_at' => $validated['scheduled_departure_at'],
            'scheduled_return_at' => $validated['scheduled_return_at'],
            'original_scheduled_return_at' => $validated['scheduled_return_at'],
            'guardian_name' => $validated['guardian_name'],
            'guardian_phone' => $validated['guardian_phone'],
            'guardian_relation' => $validated['guardian_relation'] ?? 'Orang Tua',
            'status' => 'disetujui',
            'presence_status' => 'di_pondok',
            'deadline_status' => 'on_time',
            'return_status' => 'belum_kembali',
            'is_emergency' => (bool) ($validated['is_emergency'] ?? false),
            'medical_complaint' => $validated['medical_complaint'] ?? null,
            'medical_destination' => $validated['medical_destination'] ?? null,
            'medical_escort_name' => $validated['medical_escort_name'] ?? null,
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
        $isMedical = $permit->permit_type === 'sakit_berobat' || $permit->is_emergency;
        $isHome = in_array($permit->permit_type, ['pulang_terjadwal', 'pulang_berkala']);

        $presenceStatus = $isMedical ? 'berobat' : ($isHome ? 'pulang' : 'di_luar');

        $permit->update([
            'status' => 'keluar',
            'presence_status' => $presenceStatus,
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
     * Emergency Fast-Track Out (Pencatatan darurat langsung di gerbang tanpa tunda approval)
     */
    public function emergencyExit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'medical_complaint' => 'required|string|max:500',
            'medical_destination' => 'required|string|max:255',
            'medical_escort_name' => 'required|string|max:150',
            'guardian_phone' => 'nullable|string|max:30',
            'estimated_hours' => 'nullable|integer|min:1|max:72',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $studentUnitId = $student->unit_id ?: $student->kelas?->unit_pendidikan_id;
        $now = now();
        $returnAt = (clone $now)->addHours((int) ($validated['estimated_hours'] ?? 6));
        $permitNumber = CodeGeneratorService::generate('permit_number', $studentUnitId);

        $permit = DormitoryPermit::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'unit_pendidikan_id' => $studentUnitId,
            'permit_number' => $permitNumber,
            'permit_type' => 'sakit_berobat',
            'destination' => $validated['medical_destination'],
            'scheduled_departure_at' => $now,
            'actual_departure_at' => $now,
            'scheduled_return_at' => $returnAt,
            'original_scheduled_return_at' => $returnAt,
            'guardian_name' => $student->parent?->nama_lengkap ?? 'Orang Tua / Wali',
            'guardian_phone' => $validated['guardian_phone'] ?? $student->parent?->no_hp ?? '-',
            'guardian_relation' => 'Orang Tua',
            'status' => 'keluar',
            'presence_status' => 'berobat',
            'deadline_status' => 'on_time',
            'return_status' => 'belum_kembali',
            'is_emergency' => true,
            'medical_complaint' => $validated['medical_complaint'],
            'medical_destination' => $validated['medical_destination'],
            'medical_escort_name' => $validated['medical_escort_name'],
            'notes' => '[JALUR DARURAT] Rujukan langsung medis/klinik pesantren.',
            'approved_by_musyrif_id' => $request->user()?->employee?->id,
            'checked_out_by' => $request->user()?->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Jalur darurat aktif! Santri {$student->full_name} berhasil dicatat keluar menuju {$validated['medical_destination']}.",
            'data' => $permit->load(['student', 'educationUnit']),
        ], 201);
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
            'presence_status' => 'di_pondok',
            'actual_return_at' => $now,
            'return_status' => $isLate ? 'terlambat' : 'tepat_waktu',
            'deadline_status' => $isLate ? 'overdue' : 'on_time',
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
     * Review Extension (Pengurus menyetujui / menolak perpanjangan waktu santri)
     */
    public function reviewExtension(Request $request, DormitoryPermit $permit): JsonResponse
    {
        $this->assertPermitAccess($permit);

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'new_scheduled_return_at' => 'required_if:action,approve|nullable|date',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        if ($validated['action'] === 'approve') {
            $newReturn = Carbon::parse($validated['new_scheduled_return_at']);
            $permit->update([
                'scheduled_return_at' => $newReturn,
                'extension_status' => 'approved',
                'extension_count' => ($permit->extension_count ?? 0) + 1,
                'extension_approved_by' => $user?->employee?->id,
                'extension_approved_at' => now(),
                'deadline_status' => 'on_time',
                'notes' => trim(($permit->notes ? $permit->notes . "\n" : '') . '[Perpanjangan Disetujui] ' . ($validated['admin_notes'] ?? '')),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Perpanjangan izin disetujui. Batas waktu baru: " . $newReturn->format('d/m/Y H:i') . ' WIB.',
                'data' => $permit->fresh(['student', 'educationUnit']),
            ]);
        }

        $permit->update([
            'extension_status' => 'rejected',
            'notes' => trim(($permit->notes ? $permit->notes . "\n" : '') . '[Perpanjangan Ditolak] ' . ($validated['admin_notes'] ?? '')),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan perpanjangan izin telah ditolak.',
            'data' => $permit->fresh(['student', 'educationUnit']),
        ]);
    }

    /**
     * Parent / Student Apply Permit (Pengajuan Mandiri dari Mobile App)
     */
    public function portalApply(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'child_id' => 'required|uuid|exists:students,id',
            'permit_type' => 'required|string|in:pulang_terjadwal,sakit_berobat,pesiar_mingguan,izin_khusus',
            'scheduled_departure_at' => 'required|date|after_or_equal:today',
            'scheduled_return_at' => 'required|date|after:scheduled_departure_at',
            'guardian_name' => 'required|string|max:150',
            'guardian_phone' => 'required|string|max:30',
            'guardian_relation' => 'required|string|max:50',
            'destination' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'medical_complaint' => 'nullable|string|max:500',
            'medical_destination' => 'nullable|string|max:255',
            'medical_escort_name' => 'nullable|string|max:150',
        ]);

        $student = Student::findOrFail($validated['child_id']);
        $studentUnitId = $student->unit_id ?: $student->kelas?->unit_pendidikan_id;
        $permitNumber = CodeGeneratorService::generate('permit_number', $studentUnitId);
        $isMedical = $validated['permit_type'] === 'sakit_berobat';

        $permit = DormitoryPermit::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'unit_pendidikan_id' => $studentUnitId,
            'permit_number' => $permitNumber,
            'permit_type' => $validated['permit_type'],
            'destination' => $validated['destination'] ?? ($isMedical ? 'Fasilitas Kesehatan / Rumah' : 'Rumah Orang Tua'),
            'scheduled_departure_at' => $validated['scheduled_departure_at'],
            'scheduled_return_at' => $validated['scheduled_return_at'],
            'original_scheduled_return_at' => $validated['scheduled_return_at'],
            'guardian_name' => $validated['guardian_name'],
            'guardian_phone' => $validated['guardian_phone'],
            'guardian_relation' => $validated['guardian_relation'],
            'status' => 'diajukan',
            'presence_status' => 'di_pondok',
            'deadline_status' => 'on_time',
            'return_status' => 'belum_kembali',
            'medical_complaint' => $validated['medical_complaint'] ?? null,
            'medical_destination' => $validated['medical_destination'] ?? null,
            'medical_escort_name' => $validated['medical_escort_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'applied_by_user_id' => $user->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Pengajuan izin untuk {$student->full_name} berhasil dikirim dan menunggu persetujuan pengurus asrama.",
            'data' => $permit->load(['student', 'educationUnit']),
        ], 201);
    }

    /**
     * Parent / Student Request Extension (Pengajuan Perpanjangan Waktu dari Mobile App)
     */
    public function portalRequestExtension(Request $request, DormitoryPermit $permit): JsonResponse
    {
        $validated = $request->validate([
            'requested_return_at' => 'required|date|after:now',
            'reason' => 'required|string|max:500',
        ]);

        if ($permit->status === 'kembali') {
            return response()->json([
                'status' => 'error',
                'message' => 'Santri sudah kembali ke asrama, tidak dapat mengajukan perpanjangan.',
            ], 422);
        }

        $permit->update([
            'extension_status' => 'requested',
            'extension_requested_return_at' => $validated['requested_return_at'],
            'extension_reason' => $validated['reason'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permohonan perpanjangan izin berhasil dikirimkan ke pengurus asrama.',
            'data' => $permit->fresh(['student', 'educationUnit']),
        ]);
    }

    /**
     * Parent Portal endpoint (Untuk orang tua & santri melihat daftar izin asrama)
     */
    public function portalIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $childId = $request->query('child_id');

        $query = DormitoryPermit::query()->with([
            'student:id,full_name,nis,nisn',
            'educationUnit:id,name',
            'musyrif:id,nama_lengkap',
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

        $now = now();
        $permits = $query->orderByDesc('scheduled_departure_at')->paginate(15);

        // Update kalkulasi deadline_status secara dinamis saat di-load
        $items = collect($permits->items())->map(function ($p) use ($now) {
            if ($p->status === 'keluar' && $p->scheduled_return_at) {
                $target = Carbon::parse($p->scheduled_return_at);
                if ($now->greaterThan($target)) {
                    $p->deadline_status = 'overdue';
                } elseif ($now->diffInHours($target, false) <= 2) {
                    $p->deadline_status = 'near_deadline';
                } else {
                    $p->deadline_status = 'on_time';
                }
            }
            return $p;
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'total' => $permits->total(),
                'current_page' => $permits->currentPage(),
                'last_page' => $permits->lastPage(),
            ],
        ]);
    }
}
