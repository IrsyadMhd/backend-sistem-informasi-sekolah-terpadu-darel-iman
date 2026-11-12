<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DisciplineCategory;
use App\Models\DisciplineThresholdRule;
use App\Models\EducationUnit;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\StudentPointTransaction;
use App\Models\StudentWarningLetter;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DisciplineManagementController extends Controller
{
    public function __construct(
        protected AccessScopeService $accessScope
    ) {}

    /**
     * Helper to verify if user has global administrative role (Super Admin / Admin)
     */
    protected function isAdminUser(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($user->can('discipline.point.manage')) {
            return true;
        }

        $roles = collect($user->roles ?? [])->map(fn ($r) => is_string($r) ? $r : ($r->name ?? ''))->toArray();
        $adminRoles = ['Super Admin', 'Superadmin', 'super_admin', 'Admin', 'admin', 'Tata Usaha', 'tu', 'TU', 'tata_usaha', 'Operator', 'operator'];

        return count(array_intersect($adminRoles, $roles)) > 0;
    }

    /**
     * Master Kategori Pelanggaran (Buku Saku Peraturan Pondok & Sekolah)
     */
    public function indexCategories(Request $request): JsonResponse
    {
        $query = DisciplineCategory::query()
            ->when($request->filled('cluster'), fn ($q) => $q->where('cluster', $request->query('cluster')))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->query('level')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('target_unit_type'), fn ($q) => $q->whereIn('target_unit_type', [$request->query('target_unit_type'), 'all']))
            ->when($request->has('is_permanent'), fn ($q) => $q->where('is_permanent', filter_var($request->query('is_permanent'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . $request->query('search') . '%';
                $q->where(function ($sub) use ($s) {
                    $sub->where('name', 'like', $s)
                        ->orWhere('code', 'like', $s)
                        ->orWhere('rule_name', 'like', $s)
                        ->orWhere('violation_detail', 'like', $s);
                });
            })
            ->orderBy('cluster')
            ->orderBy('pasal_number')
            ->orderBy('code');

        if ($request->boolean('all', false)) {
            return response()->json([
                'success' => true,
                'data' => $query->get(),
            ]);
        }

        $perPage = $request->integer('per_page', 25);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Tambah Master Kategori Pelanggaran (Super Admin / Admin)
     */
    public function storeCategory(Request $request): JsonResponse
    {
        if (! $this->isAdminUser($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Administrator atau Tata Usaha yang memiliki hak mengelola master tata tertib dan poin.',
            ], 403);
        }

        $validated = $request->validate([
            'cluster' => 'required|in:peraturan_umum,kelas_halaqoh,asrama,masjid',
            'pasal_number' => 'nullable|string|max:50',
            'rule_name' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'violation_detail' => 'nullable|string',
            'level' => 'required|in:ringan,sedang,berat',
            'code' => 'required|string|max:50|unique:discipline_categories,code',
            'point_weight' => 'required|integer|min:1|max:500',
            'direct_consequence' => 'nullable|string|max:255',
            'is_permanent' => 'nullable|boolean',
            'target_unit_type' => 'nullable|in:all,boarding,fullday',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['type'] = 'violation';
        $category = DisciplineCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori pelanggaran tata tertib berhasil ditambahkan.',
            'data' => $category,
        ], 201);
    }

    /**
     * Update Master Kategori Pelanggaran (Super Admin / Admin)
     */
    public function updateCategory(Request $request, string $id): JsonResponse
    {
        if (! $this->isAdminUser($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Administrator atau Tata Usaha yang memiliki hak mengelola master tata tertib dan poin.',
            ], 403);
        }

        $category = DisciplineCategory::findOrFail($id);

        $validated = $request->validate([
            'cluster' => 'sometimes|required|in:peraturan_umum,kelas_halaqoh,asrama,masjid',
            'pasal_number' => 'nullable|string|max:50',
            'rule_name' => 'nullable|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'violation_detail' => 'nullable|string',
            'level' => 'sometimes|required|in:ringan,sedang,berat',
            'code' => 'sometimes|required|string|max:50|unique:discipline_categories,code,' . $id,
            'point_weight' => 'sometimes|required|integer|min:1|max:500',
            'direct_consequence' => 'nullable|string|max:255',
            'is_permanent' => 'nullable|boolean',
            'target_unit_type' => 'nullable|in:all,boarding,fullday',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kategori pelanggaran tata tertib berhasil diperbarui.',
            'data' => $category,
        ]);
    }

    /**
     * Hapus Master Kategori Pelanggaran (Super Admin / Admin)
     */
    public function destroyCategory(Request $request, string $id): JsonResponse
    {
        if (! $this->isAdminUser($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Super Admin atau Admin yang memiliki hak menghapus data master.',
            ], 403);
        }

        $category = DisciplineCategory::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori pelanggaran tata tertib berhasil dihapus.',
        ]);
    }

    /**
     * Master Ambang Batas Poin Sanksi (Tabel Point 1 - 250)
     */
    public function indexThresholds(): JsonResponse
    {
        $thresholds = DisciplineThresholdRule::orderBy('stage_order')->get();

        return response()->json([
            'success' => true,
            'data' => $thresholds,
        ]);
    }

    /**
     * Update Ambang Batas Sanksi (Admin)
     */
    public function updateThreshold(Request $request, string $id): JsonResponse
    {
        if (! $this->isAdminUser($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Super Admin atau Admin yang berhak memperbarui aturan sanksi.',
            ], 403);
        }

        $threshold = DisciplineThresholdRule::findOrFail($id);
        $validated = $request->validate([
            'sanction_name' => 'sometimes|required|string|max:255',
            'sanction_details' => 'nullable|string',
            'notes' => 'nullable|string',
            'requires_parent_call' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $threshold->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Aturan sanksi ambang batas berhasil diperbarui.',
            'data' => $threshold,
        ]);
    }

    /**
     * Validasi Hak Akses Input Poin Siswa (Boarding vs Fullday)
     */
    protected function validateRecorderPermission(Request $request, Student $student): array
    {
        $user = $request->user();
        if (! $user) {
            return ['allowed' => false, 'message' => 'Pengguna tidak terautentikasi.', 'role' => 'Guest'];
        }

        // 1. Super Admin, Admin, dan Divisi Pendidikan memiliki izin universal
        $roles = collect($user->roles ?? [])->map(fn ($r) => is_string($r) ? $r : ($r->name ?? ''))->toArray();
        $isSuperAdmin = count(array_intersect(['Super Admin', 'Superadmin', 'super_admin'], $roles)) > 0;
        $isAdmin = count(array_intersect(['Admin', 'admin'], $roles)) > 0;
        $isDivisi = count(array_intersect([
            'Divisi Pendidikan', 'divisi_pendidikan', 'divisi-pendidikan',
            'Kepala Bidang Pendidikan', 'kepala_bidang_pendidikan',
            'Divisi Kesiswaan', 'Divisi Kurikulum',
        ], $roles)) > 0;

        if ($isSuperAdmin) {
            return ['allowed' => true, 'role' => 'Super Admin'];
        }
        if ($isAdmin) {
            return ['allowed' => true, 'role' => 'Admin'];
        }
        if ($isDivisi) {
            return ['allowed' => true, 'role' => 'Divisi Pendidikan'];
        }

        // Dapatkan unit pendidikan siswa
        $unit = $student->educationUnit;
        $unitName = strtoupper(($unit?->name ?? '') . ' ' . ($unit?->code ?? '') . ' ' . ($unit?->level ?? ''));
        $isBoarding = ($unit?->program_type === 'boarding') || Str::contains($unitName, ['PONPES', 'MAHAD', 'PESANTREN', 'ASRAMA']);

        $isMusyrif = count(array_intersect(['Musyrif', 'musyrif', 'Musyrifah', 'musyrifah', 'Musyrif / Musyrifah', 'Musyrif Asrama', 'Pembina Asrama'], $roles)) > 0;
        $isWaliKelas = count(array_intersect(['Wali Kelas', 'wali_kelas', 'wali-kelas'], $roles)) > 0;
        $isWakaKesantrian = count(array_intersect(['Waka Kesantrian', 'waka_kesantrian', 'Wakil Kesantrian', 'Kepala Asrama', 'Pengasuhan'], $roles)) > 0;
        $isKepsek = count(array_intersect(['Kepala Sekolah', 'kepala_sekolah', 'KepalaSekolah', 'kepsek'], $roles)) > 0;
        $isGuru = count(array_intersect(['Guru', 'Guru Mata Pelajaran', 'Guru PAI', 'Guru Tahfizh', 'Guru BK', 'Teacher', 'teacher'], $roles)) > 0;

        if ($isBoarding) {
            // Unit Boarding: Musyrif, Wali Kelas, Wakil Kesantrian, atau Kepala Sekolah
            if ($isMusyrif) {
                return ['allowed' => true, 'role' => 'Musyrif'];
            }
            if ($isWakaKesantrian) {
                return ['allowed' => true, 'role' => 'Waka Kesantrian'];
            }
            if ($isWaliKelas) {
                return ['allowed' => true, 'role' => 'Wali Kelas'];
            }
            if ($isKepsek) {
                return ['allowed' => true, 'role' => 'Kepala Sekolah'];
            }

            return [
                'allowed' => false,
                'message' => 'Pada unit Boarding (Pesantren), pengisian poin kelakuan/pelanggaran hanya diizinkan untuk Musyrif, Wali Kelas, atau Wakil Kesantrian.',
                'role' => $roles[0] ?? 'Pegawai',
            ];
        } else {
            // Unit Fullday: Seluruh lini guru (Wali Kelas, Guru Mapel), Kepala Sekolah, dan Divisi Pendidikan
            if ($isWaliKelas) {
                return ['allowed' => true, 'role' => 'Wali Kelas'];
            }
            if ($isGuru) {
                return ['allowed' => true, 'role' => 'Guru Mata Pelajaran'];
            }
            if ($isKepsek) {
                return ['allowed' => true, 'role' => 'Kepala Sekolah'];
            }

            return [
                'allowed' => false,
                'message' => 'Pada unit Fullday, pengisian poin kelakuan diizinkan untuk Seluruh Lini Guru (Wali Kelas, Mapel), Kepala Sekolah, dan Divisi Pendidikan.',
                'role' => $roles[0] ?? 'Pegawai',
            ];
        }
    }

    /**
     * Input Transaksi Poin Pelanggaran Siswa/Santri
     */
    public function storeViolation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'category_id' => 'required|uuid|exists:discipline_categories,id',
            'points' => 'nullable|integer|min:1|max:500',
            'transaction_date' => 'required|date',
            'incident_location' => 'nullable|string|max:100',
            'description' => 'required|string|max:3000',
            'direct_consequence_notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:tercatat,tindak_lanjut,selesai_pembinaan,dirujuk_bk',
        ]);

        $student = Student::with(['educationUnit', 'kelas'])->findOrFail($validated['student_id']);

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $studentUnitId = $student->unit_id ?: $student->kelas?->unit_pendidikan_id;
            abort_unless($studentUnitId, 403, 'Siswa/santri berada di luar cakupan unit akun.');
            $this->accessScope->assertEducationUnitAccess($user, (string) $studentUnitId);
        }

        // Validasi Otorisasi Hak Input Berdasarkan Tipe Unit (Boarding vs Fullday)
        $perm = $this->validateRecorderPermission($request, $student);
        if (! $perm['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $perm['message'],
            ], 403);
        }

        $category = DisciplineCategory::findOrFail($validated['category_id']);
        $points = $validated['points'] ? (int) $validated['points'] : (int) $category->point_weight;

        $activeYear = AcademicYear::where('is_active', true)->first();
        $activeSemester = Semester::where('is_active', true)->first();

        DB::beginTransaction();
        try {
            // 1. Simpan Transaksi Poin
            $transaction = StudentPointTransaction::create([
                'student_id' => $student->id,
                'academic_year_id' => $activeYear?->id,
                'semester_id' => $activeSemester?->id,
                'category_id' => $category->id,
                'reported_by_id' => $request->user()?->id,
                'recorder_role' => $perm['role'],
                'points' => $points,
                'transaction_date' => $validated['transaction_date'],
                'incident_location' => $validated['incident_location'] ?? ($category->cluster === 'asrama' ? 'asrama' : ($category->cluster === 'masjid' ? 'masjid' : 'kelas')),
                'description' => $validated['description'],
                'direct_consequence_notes' => $validated['direct_consequence_notes'] ?? $category->direct_consequence,
                'status' => $validated['status'] ?? 'tercatat',
            ]);

            // 2. Sinkronkan ke Catatan Siswa (StudentNote) agar tampak di Buku Penghubung Orang Tua & Mobile App
            $priority = match ($category->level) {
                'berat' => 'urgent',
                'sedang' => 'high',
                default => 'medium',
            };

            StudentNote::create([
                'student_id' => $student->id,
                'teacher_id' => $request->user()?->employee?->id ?? null,
                'education_unit_id' => $student->unit_id,
                'academic_year_id' => $activeYear?->id,
                'semester_id' => $activeSemester?->id,
                'date' => $validated['transaction_date'],
                'category' => 'Kedisiplinan',
                'title' => $category->name . " (+{$points} Poin)",
                'content' => "Pelanggaran {$category->rule_name} (Kluster: " . strtoupper(str_replace('_', ' ', $category->cluster)) . "). " .
                    $validated['description'] . 
                    ($category->direct_consequence ? " | Tindakan Langsung: {$category->direct_consequence}" : ""),
                'follow_up' => $validated['direct_consequence_notes'] ?? $category->direct_consequence,
                'priority' => $priority,
                'is_private' => false,
            ]);

            // 3. Hitung Poin Kumulatif Berjalan
            $cumulativePoints = StudentPointTransaction::where('student_id', $student->id)
                ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
                ->sum('points');

            // 4. Cek Ambang Batas Sanksi (Threshold)
            $matchedStage = DisciplineThresholdRule::where('is_active', true)
                ->where('min_points', '<=', $cumulativePoints)
                ->where(function ($q) use ($cumulativePoints) {
                    $q->whereNull('max_points')
                      ->orWhere('max_points', '>=', $cumulativePoints);
                })
                ->orderByDesc('stage_order')
                ->first();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Poin kelakuan/pelanggaran santri berhasil dicatat.',
                'data' => [
                    'transaction' => $transaction->load(['student:id,full_name,nis', 'category']),
                    'cumulative_points' => $cumulativePoints,
                    'current_stage' => $matchedStage,
                    'needs_warning_letter' => $matchedStage && $matchedStage->letter_type !== null,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat poin pelanggaran: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ledger Riwayat Transaksi Pelanggaran & Poin Siswa (Filter Komprehensif)
     */
    public function indexViolations(Request $request): JsonResponse
    {
        $query = StudentPointTransaction::with([
            'student:id,full_name,nis,kelas_id,class_id,unit_id,gender,avatar_url',
            'student.educationUnit:id,name,code,level,program_type',
            'student.kelas:id,nama_kelas',
            'category',
            'reportedBy:id,name',
            'academicYear:id,name',
        ]);

        // Filter Scope Organisasi
        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            $query->whereHas('student', function ($sq) use ($accessibleUnitIds) {
                $sq->whereIn('unit_id', $accessibleUnitIds)
                   ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        }

        // Filter Pencarian & Kriteria
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }
        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $unitId = $request->query('unit_id');
            $query->whereHas('student', function ($sq) use ($unitId) {
                $sq->where('unit_id', $unitId)
                   ->orWhereHas('kelas', fn ($kq) => $kq->where('unit_pendidikan_id', $unitId));
            });
        }
        if ($request->filled('class_id') && $request->query('class_id') !== 'all') {
            $classId = $request->query('class_id');
            $query->whereHas('student', fn ($sq) => $sq->where('kelas_id', $classId)->orWhere('class_id', $classId));
        }
        if ($request->filled('cluster')) {
            $query->whereHas('category', fn ($cq) => $cq->where('cluster', $request->query('cluster')));
        }
        if ($request->filled('level')) {
            $query->whereHas('category', fn ($cq) => $cq->where('level', $request->query('level')));
        }
        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->query('end_date'));
        }
        if ($request->filled('search')) {
            $s = '%' . $request->query('search') . '%';
            $query->where(function ($sub) use ($s) {
                $sub->whereHas('student', fn ($sq) => $sq->where('full_name', 'like', $s)->orWhere('nis', 'like', $s))
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', $s)->orWhere('code', 'like', $s))
                    ->orWhere('description', 'like', $s);
            });
        }

        $sortBy = $request->query('sort_by', 'date_desc');
        if ($sortBy === 'points_desc') {
            $query->orderByDesc('points')->latest('created_at');
        } elseif ($sortBy === 'points_asc') {
            $query->orderBy('points', 'asc')->latest('created_at');
        } elseif ($sortBy === 'date_asc') {
            $query->orderBy('transaction_date', 'asc')->oldest('created_at');
        } else {
            $query->orderByDesc('transaction_date')->latest('created_at');
        }

        $paginated = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Ringkasan & Lembar Skor Kedisiplinan Per Santri / Siswa (Speedometer 0 - 250 Poin)
     */
    public function studentSummary(string $studentId): JsonResponse
    {
        $student = Student::with([
            'educationUnit:id,name,code,level,program_type',
            'kelas:id,nama_kelas',
            'parentModel:id,name,phone',
        ])->findOrFail($studentId);

        $activeYear = AcademicYear::where('is_active', true)->first();

        // 1. Total Poin Kumulatif
        $transactions = StudentPointTransaction::with(['category', 'reportedBy:id,name'])
            ->where('student_id', $studentId)
            ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->orderByDesc('transaction_date')
            ->get();

        $cumulativePoints = $transactions->sum('points');
        $permanentPoints = $transactions->filter(fn ($t) => (bool) ($t->category?->is_permanent))->sum('points');

        // 2. Breakdown per Kluster
        $breakdownByCluster = [
            'peraturan_umum' => $transactions->filter(fn ($t) => $t->category?->cluster === 'peraturan_umum')->sum('points'),
            'kelas_halaqoh' => $transactions->filter(fn ($t) => $t->category?->cluster === 'kelas_halaqoh')->sum('points'),
            'asrama' => $transactions->filter(fn ($t) => $t->category?->cluster === 'asrama')->sum('points'),
            'masjid' => $transactions->filter(fn ($t) => $t->category?->cluster === 'masjid')->sum('points'),
        ];

        // 3. Breakdown per Tingkat (Ringan / Sedang / Berat)
        $breakdownByLevel = [
            'ringan' => $transactions->filter(fn ($t) => $t->category?->level === 'ringan')->count(),
            'sedang' => $transactions->filter(fn ($t) => $t->category?->level === 'sedang')->count(),
            'berat' => $transactions->filter(fn ($t) => $t->category?->level === 'berat')->count(),
        ];

        // 4. Ambang Batas Saat Ini
        $currentStage = DisciplineThresholdRule::where('is_active', true)
            ->where('min_points', '<=', $cumulativePoints)
            ->where(function ($q) use ($cumulativePoints) {
                $q->whereNull('max_points')
                  ->orWhere('max_points', '>=', $cumulativePoints);
            })
            ->orderByDesc('stage_order')
            ->first();

        // 5. Surat Peringatan yang Sudah Diterbitkan
        $warningLetters = StudentWarningLetter::where('student_id', $studentId)
            ->with('issuedBy:id,name')
            ->orderByDesc('issue_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $student,
                'cumulative_points' => $cumulativePoints,
                'permanent_points' => $permanentPoints,
                'max_standard_points' => 250,
                'risk_percentage' => min(100, round(($cumulativePoints / 250) * 100, 1)),
                'current_stage' => $currentStage,
                'breakdown_by_cluster' => $breakdownByCluster,
                'breakdown_by_level' => $breakdownByLevel,
                'total_violations_count' => $transactions->count(),
                'recent_transactions' => $transactions->take(10),
                'warning_letters' => $warningLetters,
            ],
        ]);
    }

    /**
     * Terbitkan Surat Peringatan / Surat Teguran (ST, SP1, SP2, Keluar)
     */
    public function storeWarningLetter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'letter_type' => 'required|in:st,sp1,sp2,keluar',
            'letter_number' => 'nullable|string|max:100',
            'parent_summon_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'signed_by_role' => 'nullable|string|max:100',
        ]);

        $student = Student::with('educationUnit')->findOrFail($validated['student_id']);
        $activeYear = AcademicYear::where('is_active', true)->first();
        $activeSemester = Semester::where('is_active', true)->first();

        // Hitung akumulasi poin saat ini
        $cumulativePoints = StudentPointTransaction::where('student_id', $student->id)
            ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->sum('points');

        // Nomor Surat Otomatis jika kosong
        $letterCode = strtoupper($validated['letter_type']);
        $prefix = match ($validated['letter_type']) {
            'st' => 'ST',
            'sp1' => 'SP.1',
            'sp2' => 'SP.2',
            'keluar' => 'SK.KELUAR',
        };
        $unitCode = $student->educationUnit?->code ?? 'PONDOK';
        $romanMonth = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'][date('n')];
        $autoNumber = $validated['letter_number'] ?: sprintf('%s/%s/%s/%s/%03d', $prefix, $unitCode, $romanMonth, date('Y'), StudentWarningLetter::count() + 1);

        $letter = StudentWarningLetter::create([
            'student_id' => $student->id,
            'academic_year_id' => $activeYear?->id,
            'semester_id' => $activeSemester?->id,
            'letter_number' => $autoNumber,
            'letter_type' => $validated['letter_type'],
            'cumulative_points' => $cumulativePoints,
            'issue_date' => now()->toDateString(),
            'parent_summon_date' => $validated['parent_summon_date'] ?? null,
            'parent_summon_status' => 'menunggu_kehadiran',
            'notes' => $validated['notes'],
            'issued_by_id' => $request->user()?->id,
            'signed_by_role' => $validated['signed_by_role'] ?? 'Waka Kesantrian / Kepala Sekolah',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Surat {$letterCode} resmi berhasil diterbitkan dengan nomor {$autoNumber}.",
            'data' => $letter->load(['student:id,full_name,nis', 'issuedBy:id,name']),
        ], 201);
    }

    /**
     * Catat Respon & Kehadiran Pemanggilan Orang Tua
     */
    public function updateParentSummon(Request $request, string $id): JsonResponse
    {
        $letter = StudentWarningLetter::findOrFail($id);

        $validated = $request->validate([
            'parent_summon_status' => 'required|in:menunggu_kehadiran,hadir,tidak_hadir,selesai_konseling',
            'parent_response_notes' => 'required|string',
        ]);

        $letter->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Status pemanggilan dan dokumentasi respon orang tua berhasil disimpan.',
            'data' => $letter,
        ]);
    }

    /**
     * Statistik & Ringkasan KPI Dashboard Kedisiplinan
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasGlobalScope = ! $user || $this->accessScope->hasGlobalScope($user);
        $accessibleUnitIds = $hasGlobalScope
            ? collect()
            : $this->accessScope->accessibleEducationUnits($user)->pluck('id');

        if (! $hasGlobalScope && $accessibleUnitIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'violations_this_month' => 0,
                    'active_sp_count' => 0,
                    'permanent_violations_count' => 0,
                    'resolved_count' => 0,
                    'cluster_distribution' => [],
                ],
            ]);
        }

        $applyUnitScope = function ($q) use ($hasGlobalScope, $accessibleUnitIds) {
            if ($hasGlobalScope) {
                return;
            }
            $q->whereHas('student', function ($sq) use ($accessibleUnitIds) {
                $sq->whereIn('unit_id', $accessibleUnitIds)
                    ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        };

        // 1. Total Pelanggaran Bulan Ini
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $violationsThisMonthQuery = StudentPointTransaction::whereDate('transaction_date', '>=', $startOfMonth);
        $applyUnitScope($violationsThisMonthQuery);
        $violationsThisMonth = $violationsThisMonthQuery->count();

        // 2. Santri Status SP1 / SP2 Aktif
        $activeSpQuery = StudentWarningLetter::whereIn('letter_type', ['sp1', 'sp2'])
            ->where('parent_summon_status', '!=', 'selesai_konseling');
        $applyUnitScope($activeSpQuery);
        $activeSpCount = $activeSpQuery->distinct('student_id')->count('student_id');

        // 3. Kasus Berat / Poin Permanen
        $permanentQuery = StudentPointTransaction::whereHas('category', fn ($q) => $q->where('is_permanent', true));
        $applyUnitScope($permanentQuery);
        $permanentViolationsCount = $permanentQuery->count();

        // 4. Pembinaan Selesai
        $resolvedQuery = StudentPointTransaction::where('status', 'selesai_pembinaan');
        $applyUnitScope($resolvedQuery);
        $resolvedCount = $resolvedQuery->count();

        // 5. Distribusi Kluster
        $clusterQuery = DB::table('student_point_transactions')
            ->join('discipline_categories', 'student_point_transactions.category_id', '=', 'discipline_categories.id')
            ->select('discipline_categories.cluster', DB::raw('count(*) as count'), DB::raw('sum(student_point_transactions.points) as total_points'))
            ->groupBy('discipline_categories.cluster');

        if (! $hasGlobalScope) {
            $scopedStudentSubquery = Student::query()
                ->select('id')
                ->where(function ($sq) use ($accessibleUnitIds) {
                    $sq->whereIn('unit_id', $accessibleUnitIds)
                        ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
                });
            $clusterQuery->whereIn('student_point_transactions.student_id', $scopedStudentSubquery);
        }

        $clusterDistribution = $clusterQuery->get();

        return response()->json([
            'success' => true,
            'data' => [
                'violations_this_month' => $violationsThisMonth,
                'active_sp_count' => $activeSpCount,
                'permanent_violations_count' => $permanentViolationsCount,
                'resolved_count' => $resolvedCount,
                'cluster_distribution' => $clusterDistribution,
            ],
        ]);
    }

    /**
     * Overview Kedisiplinan & Early Warning untuk Kepala Sekolah (Level Satuan Pendidikan)
     */
    public function principalOverview(Request $request): JsonResponse
    {
        $user = $request->user();
        $unitId = $request->query('unit_id');
        if (! $unitId || in_array(strtolower((string) $unitId), ['all', 'semua'], true)) {
            $accessible = $this->accessScope->getAccessibleUnitIds($user);
            $unitId = $user?->employee?->education_unit_id ?: ($accessible[0] ?? null);
            if (! $unitId) {
                $firstUnit = EducationUnit::first();
                $unitId = $firstUnit?->id;
            }
        }

        $unit = EducationUnit::find($unitId);
        $activeYear = AcademicYear::where('is_active', true)->first();

        // 1. Total Siswa di Unit
        $totalStudents = Student::where('unit_id', $unitId)->count();

        // 2. Akumulasi Poin Pelanggaran Berjalan per Siswa
        $pointsGrouped = DB::table('student_point_transactions')
            ->join('students', 'student_point_transactions.student_id', '=', 'students.id')
            ->where('students.unit_id', $unitId)
            ->when($activeYear, fn ($q) => $q->where('student_point_transactions.academic_year_id', $activeYear->id))
            ->groupBy('students.id')
            ->select('students.id', DB::raw('SUM(student_point_transactions.points) as total_points'))
            ->get();

        $studentsWithViolationsCount = $pointsGrouped->count();
        $cleanStudentsCount = max(0, $totalStudents - $studentsWithViolationsCount);
        $lowRiskCount = $pointsGrouped->whereBetween('total_points', [1, 30])->count();
        $mediumRiskCount = $pointsGrouped->whereBetween('total_points', [31, 99])->count();
        $highRiskCount = $pointsGrouped->where('total_points', '>=', 100)->count();

        // 3. Early Warning Radar: Siswa Kritis & Rawan (Poin >= 31 atau top poin di unit)
        $earlyWarningStudentIds = $pointsGrouped->where('total_points', '>=', 31)->pluck('id');
        $earlyWarningStudents = Student::with([
                'kelas:id,nama_kelas',
                'educationUnit:id,name',
                'pointTransactions' => fn ($q) => $q->with('category:id,name,rule_name,level,cluster')->latest()->limit(3),
            ])
            ->whereIn('id', $earlyWarningStudentIds)
            ->get()
            ->map(function ($st) use ($pointsGrouped) {
                $pts = (int) ($pointsGrouped->firstWhere('id', $st->id)->total_points ?? 0);
                $stage = DisciplineThresholdRule::where('is_active', true)
                    ->where('min_points', '<=', $pts)
                    ->where(function ($q) use ($pts) {
                        $q->whereNull('max_points')->orWhere('max_points', '>=', $pts);
                    })
                    ->orderByDesc('stage_order')
                    ->first();

                return [
                    'id' => $st->id,
                    'full_name' => $st->full_name,
                    'nis' => $st->nis,
                    'kelas' => $st->kelas?->nama_kelas ?? '-',
                    'total_points' => $pts,
                    'stage_name' => $stage?->sanction_name ?? 'Tertib',
                    'letter_type' => $stage?->letter_type,
                    'recent_violations' => $st->pointTransactions,
                ];
            })
            ->sortByDesc('total_points')
            ->values()
            ->take(15);

        // 4. Surat Peringatan di Unit ini
        $warningLetters = StudentWarningLetter::whereHas('student', fn ($q) => $q->where('unit_id', $unitId))
            ->with(['student:id,full_name,nis,kelas_id', 'student.kelas:id,nama_kelas', 'issuedBy:id,name'])
            ->orderByDesc('issue_date')
            ->limit(10)
            ->get();

        // 5. Kasus per Rombel / Kelas
        $violationsByClass = DB::table('student_point_transactions')
            ->join('students', 'student_point_transactions.student_id', '=', 'students.id')
            ->join('classes', 'students.kelas_id', '=', 'classes.id')
            ->where('students.unit_id', $unitId)
            ->when($activeYear, fn ($q) => $q->where('student_point_transactions.academic_year_id', $activeYear->id))
            ->groupBy('classes.id', 'classes.nama_kelas')
            ->select('classes.nama_kelas as class_name', DB::raw('count(*) as count'), DB::raw('sum(student_point_transactions.points) as total_points'))
            ->orderByDesc('total_points')
            ->limit(8)
            ->get();

        // 6. Kasus per Kluster
        $violationsByCluster = DB::table('student_point_transactions')
            ->join('students', 'student_point_transactions.student_id', '=', 'students.id')
            ->join('discipline_categories', 'student_point_transactions.category_id', '=', 'discipline_categories.id')
            ->where('students.unit_id', $unitId)
            ->when($activeYear, fn ($q) => $q->where('student_point_transactions.academic_year_id', $activeYear->id))
            ->groupBy('discipline_categories.cluster')
            ->select('discipline_categories.cluster', DB::raw('count(*) as count'), DB::raw('sum(student_point_transactions.points) as total_points'))
            ->get();

        // 7. Total Poin Pelanggaran
        $totalViolationPoints = (int) DB::table('student_point_transactions')
            ->join('students', 'student_point_transactions.student_id', '=', 'students.id')
            ->where('students.unit_id', $unitId)
            ->where('student_point_transactions.points', '>', 0)
            ->when($activeYear, fn ($q) => $q->where('student_point_transactions.academic_year_id', $activeYear->id))
            ->sum('student_point_transactions.points');

        return response()->json([
            'success' => true,
            'data' => [
                'unit' => $unit,
                'kpi' => [
                    'total_students' => $totalStudents,
                    'clean_students' => $cleanStudentsCount,
                    'compliance_rate' => $totalStudents > 0 ? round(($cleanStudentsCount / $totalStudents) * 100, 1) : 100,
                    'low_risk_count' => $lowRiskCount,
                    'medium_risk_count' => $mediumRiskCount,
                    'high_risk_count' => $highRiskCount,
                    'total_sp_count' => $warningLetters->count(),
                    'total_violation_points' => $totalViolationPoints,
                ],
                'early_warning_students' => $earlyWarningStudents,
                'warning_letters' => $warningLetters,
                'violations_by_class' => $violationsByClass,
                'violations_by_cluster' => $violationsByCluster,
            ],
        ]);
    }

    /**
     * Overview Supervisi Komparatif Kedisiplinan Lintas Unit untuk Divisi Pendidikan
     */
    public function divisiOverview(Request $request): JsonResponse
    {
        $activeYear = AcademicYear::where('is_active', true)->first();
        $unitParam = $request->query('unit_id');
        $selectedUnitId = ($unitParam && $unitParam !== 'all') ? (string) $unitParam : null;

        $classParam = $request->query('class_id');
        $selectedClassId = ($classParam && $classParam !== 'all') ? (string) $classParam : null;

        $sortBy = $request->query('sort_by', 'points_desc');

        $units = EducationUnit::orderBy('level')->orderBy('name')->get();
        $allStudentsCount = Student::when($selectedUnitId, fn ($q) => $q->where('unit_id', $selectedUnitId))
            ->when($selectedClassId, fn ($q) => $q->where('kelas_id', $selectedClassId))
            ->count();

        // Points grouped by student
        $pointsByStudent = DB::table('student_point_transactions')
            ->join('students', 'student_point_transactions.student_id', '=', 'students.id')
            ->where('student_point_transactions.status', '!=', 'dibatalkan')
            ->when($activeYear, fn ($q) => $q->where('student_point_transactions.academic_year_id', $activeYear->id))
            ->when($selectedUnitId, fn ($q) => $q->where('students.unit_id', $selectedUnitId))
            ->when($selectedClassId, fn ($q) => $q->where('students.kelas_id', $selectedClassId))
            ->groupBy('students.id', 'students.unit_id')
            ->select('students.id', 'students.unit_id', DB::raw('SUM(student_point_transactions.points) as total_points'))
            ->get();

        $unitBenchmarks = $units->map(function ($u) use ($pointsByStudent, $selectedUnitId) {
            $uStudentsCount = Student::where('unit_id', $u->id)->count();
            $uPoints = $pointsByStudent->where('unit_id', $u->id);
            $uViolationsCount = $uPoints->count();
            $uCleanCount = max(0, $uStudentsCount - $uViolationsCount);
            $uComplianceRate = $uStudentsCount > 0 ? round(($uCleanCount / $uStudentsCount) * 100, 1) : 100;

            $uLowRisk = $uPoints->whereBetween('total_points', [1, 30])->count();
            $uMediumRisk = $uPoints->whereBetween('total_points', [31, 99])->count();
            $uHighRisk = $uPoints->where('total_points', '>=', 100)->count();

            $totalPointsSum = (int) $uPoints->sum('total_points');
            $spCount = StudentWarningLetter::whereHas('student', fn ($q) => $q->where('unit_id', $u->id))->count();

            return [
                'unit_id' => $u->id,
                'unit_name' => $u->name,
                'level' => $u->level,
                'program_type' => $u->program_type ?? 'fullday',
                'is_selected' => $selectedUnitId === $u->id,
                'total_students' => $uStudentsCount,
                'clean_students' => $uCleanCount,
                'compliance_rate' => $uComplianceRate,
                'low_risk_count' => $uLowRisk,
                'medium_risk_count' => $uMediumRisk,
                'high_risk_count' => $uHighRisk,
                'total_points' => $totalPointsSum,
                'sp_count' => $spCount,
            ];
        });

        // Audit Kepatuhan: Siswa >= 100 poin yang belum ada SP
        $highRiskStudentIds = $pointsByStudent->where('total_points', '>=', 100)->pluck('id');
        $studentsWithSpIds = StudentWarningLetter::pluck('student_id')->unique();
        $unissuedSpStudentIds = $highRiskStudentIds->diff($studentsWithSpIds);

        $complianceAuditStudents = Student::with(['educationUnit:id,name', 'kelas:id,nama_kelas'])
            ->whereIn('id', $unissuedSpStudentIds)
            ->when($selectedUnitId, fn ($q) => $q->where('unit_id', $selectedUnitId))
            ->when($selectedClassId, fn ($q) => $q->where('kelas_id', $selectedClassId))
            ->get()
            ->map(function ($st) use ($pointsByStudent) {
                return [
                    'id' => $st->id,
                    'full_name' => $st->full_name,
                    'nis' => $st->nis,
                    'unit_name' => $st->educationUnit?->name,
                    'kelas' => $st->kelas?->nama_kelas,
                    'total_points' => (int) ($pointsByStudent->firstWhere('id', $st->id)->total_points ?? 0),
                    'audit_status' => 'Perlu Tindak Lanjut Penerbitan ST/SP',
                ];
            });

        // Rincian Siswa/Santri & Ranking Poin (Tertinggi / Terendah)
        $studentRankingsQuery = DB::table('students')
            ->leftJoin('student_point_transactions', function ($join) use ($activeYear) {
                $join->on('students.id', '=', 'student_point_transactions.student_id')
                     ->where('student_point_transactions.status', '!=', 'dibatalkan');
                if ($activeYear) {
                    $join->where('student_point_transactions.academic_year_id', $activeYear->id);
                }
            })
            ->leftJoin('education_units', 'students.unit_id', '=', 'education_units.id')
            ->leftJoin('tbl_kelas', 'students.kelas_id', '=', 'tbl_kelas.id')
            ->when($selectedUnitId, fn ($q) => $q->where('students.unit_id', $selectedUnitId))
            ->when($selectedClassId, fn ($q) => $q->where('students.kelas_id', $selectedClassId))
            ->select(
                'students.id',
                'students.full_name',
                'students.nis',
                'students.gender',
                'students.unit_id',
                'education_units.name as unit_name',
                'education_units.program_type as program_type',
                'tbl_kelas.nama_kelas as kelas_name',
                DB::raw('COALESCE(SUM(student_point_transactions.points), 0) as total_points'),
                DB::raw('COUNT(student_point_transactions.id) as violations_count'),
                DB::raw('MAX(student_point_transactions.transaction_date) as last_violation_date')
            )
            ->groupBy(
                'students.id',
                'students.full_name',
                'students.nis',
                'students.gender',
                'students.unit_id',
                'education_units.name',
                'education_units.program_type',
                'tbl_kelas.nama_kelas'
            );

        if ($sortBy === 'points_asc') {
            $studentRankingsQuery->orderBy('total_points', 'asc')->orderBy('students.full_name', 'asc');
        } else {
            $studentRankingsQuery->orderByDesc('total_points')->orderBy('students.full_name', 'asc');
        }

        $studentRankingsRaw = $studentRankingsQuery->limit(60)->get();
        $studentIdsInRankings = $studentRankingsRaw->pluck('id')->filter();

        $warningLettersMap = StudentWarningLetter::whereIn('student_id', $studentIdsInRankings)
            ->orderByDesc('issue_date')
            ->get()
            ->groupBy('student_id');

        $studentRankings = $studentRankingsRaw->map(function ($st) use ($warningLettersMap) {
            $pts = (int) $st->total_points;
            $riskStatus = 'Aman (0 Poin)';
            $riskBadgeColor = 'success';
            if ($pts >= 250) {
                $riskStatus = 'Sanksi Kritis (Dikeluarkan)';
                $riskBadgeColor = 'danger';
            } elseif ($pts >= 200) {
                $riskStatus = 'SP 2 (200 - 249 Poin)';
                $riskBadgeColor = 'danger';
            } elseif ($pts >= 150) {
                $riskStatus = 'SP 1 (150 - 199 Poin)';
                $riskBadgeColor = 'danger';
            } elseif ($pts >= 100) {
                $riskStatus = 'Surat Teguran (100 - 149 Poin)';
                $riskBadgeColor = 'warning';
            } elseif ($pts >= 31) {
                $riskStatus = 'Sanksi Sedang (31 - 99 Poin)';
                $riskBadgeColor = 'warning';
            } elseif ($pts >= 1) {
                $riskStatus = 'Sanksi Ringan (1 - 30 Poin)';
                $riskBadgeColor = 'primary';
            }

            $spLetters = $warningLettersMap->get($st->id, collect());
            $latestSp = $spLetters->first();

            return [
                'id' => $st->id,
                'full_name' => $st->full_name,
                'nis' => $st->nis ?: '-',
                'gender' => $st->gender ?? 'male',
                'unit_id' => $st->unit_id,
                'unit_name' => $st->unit_name ?: 'Unit Sekolah',
                'program_type' => $st->program_type ?? 'fullday',
                'kelas_name' => $st->kelas_name ?: 'Belum Ditentukan',
                'total_points' => $pts,
                'violations_count' => (int) $st->violations_count,
                'last_violation_date' => $st->last_violation_date,
                'risk_status' => $riskStatus,
                'risk_badge_color' => $riskBadgeColor,
                'has_sp' => $latestSp ? true : false,
                'latest_sp_type' => $latestSp ? strtoupper($latestSp->letter_type) : null,
                'latest_sp_date' => $latestSp ? $latestSp->issue_date : null,
            ];
        });

        // Kluster Se-Yayasan
        $clusterDistribution = DB::table('student_point_transactions')
            ->join('discipline_categories', 'student_point_transactions.category_id', '=', 'discipline_categories.id')
            ->where('student_point_transactions.status', '!=', 'dibatalkan')
            ->when($activeYear, fn ($q) => $q->where('student_point_transactions.academic_year_id', $activeYear->id))
            ->when($selectedUnitId, function ($q) use ($selectedUnitId) {
                $q->join('students', 'student_point_transactions.student_id', '=', 'students.id')
                  ->where('students.unit_id', $selectedUnitId);
            })
            ->groupBy('discipline_categories.cluster')
            ->select('discipline_categories.cluster', DB::raw('count(*) as count'), DB::raw('sum(student_point_transactions.points) as total_points'))
            ->get();

        $totalCleanAll = $selectedUnitId
            ? ($unitBenchmarks->firstWhere('unit_id', $selectedUnitId)['clean_students'] ?? 0)
            : $unitBenchmarks->sum('clean_students');
        $overallCompliance = $allStudentsCount > 0 ? round(($totalCleanAll / $allStudentsCount) * 100, 1) : 100;

        $highestRiskUnit = $unitBenchmarks->sortByDesc('total_points')->first();
        $selectedUnitModel = $selectedUnitId ? $units->firstWhere('id', $selectedUnitId) : null;
        $selectedClassModel = $selectedClassId ? DB::table('tbl_kelas')->where('id', $selectedClassId)->first() : null;

        $unitClassScope = $selectedUnitModel ? $selectedUnitModel->name : 'Seluruh Satuan Pendidikan (Lintas Unit)';
        if ($selectedClassModel) {
            $unitClassScope .= ' — Kelas ' . ($selectedClassModel->nama_kelas ?? $selectedClassModel->kode_kelas);
        }

        $executiveSummary = [
            'report_title' => 'Laporan Eksekutif Supervisi Ketertiban & Mutu Adab Santri',
            'audience' => 'Pengurus Yayasan & Badan Pembina Pesantren',
            'period' => $activeYear?->name ?? 'Tahun Ajaran 2026/2027',
            'generated_at' => now()->translatedFormat('d F Y, H:i') . ' WIB',
            'selected_unit' => $unitClassScope,
            'selected_class' => $selectedClassModel ? ($selectedClassModel->nama_kelas ?? $selectedClassModel->kode_kelas) : 'Semua Kelas / Rombel',
            'total_students_monitored' => $allStudentsCount,
            'clean_students' => $totalCleanAll,
            'overall_compliance_rate' => $overallCompliance,
            'high_risk_students_count' => $pointsByStudent->where('total_points', '>=', 100)->count(),
            'total_sp_issued' => StudentWarningLetter::when($selectedUnitId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('unit_id', $selectedUnitId)))->count(),
            'unissued_sp_count' => $unissuedSpStudentIds->count(),
            'highest_risk_unit' => $highestRiskUnit ? [
                'unit_name' => $highestRiskUnit['unit_name'],
                'total_points' => $highestRiskUnit['total_points'],
                'compliance_rate' => $highestRiskUnit['compliance_rate'],
            ] : null,
            'recommendations' => [
                'Percepatan penerbitan Surat Peringatan (SP) dan pemanggilan orang tua bagi santri dengan akumulasi poin >= 100.',
                'Penguatan koordinasi terpadu antara Kepala Sekolah, Wali Asrama/Musyrif, dan Guru BK dalam pembinaan adab berkala.',
                'Apresiasi dan penghargaan adab (bintang kebaikan) bagi unit dan kelas dengan tingkat kepatuhan di atas 95%.',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'macro_kpi' => [
                    'total_students' => $allStudentsCount,
                    'clean_students' => $totalCleanAll,
                    'overall_compliance_rate' => $overallCompliance,
                    'total_units' => $units->count(),
                    'total_high_risk_students' => $pointsByStudent->where('total_points', '>=', 100)->count(),
                    'total_warning_letters' => StudentWarningLetter::when($selectedUnitId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('unit_id', $selectedUnitId)))->count(),
                    'unissued_sp_count' => $unissuedSpStudentIds->count(),
                ],
                'selected_unit_id' => $selectedUnitId,
                'selected_class_id' => $selectedClassId,
                'sort_by' => $sortBy,
                'unit_benchmarks' => $unitBenchmarks,
                'student_rankings' => $studentRankings,
                'compliance_audit' => $complianceAuditStudents,
                'cluster_distribution' => $clusterDistribution,
                'executive_summary' => $executiveSummary,
            ],
        ]);
    }

    /**
     * Overview Eksekutif Kedisiplinan & Watchlist Kasus Kritis untuk Pengurus Yayasan
     */
    public function foundationOverview(Request $request): JsonResponse
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        // 1. Kasus Kritis / Berat untuk Pengurus Yayasan:
        // Poin >= 100 (Ambang ST/SP) ATAU pelanggaran berkategori is_permanent (Narkoba, Asusila, Miras, dll)
        $criticalTransactions = StudentPointTransaction::with([
                'student:id,full_name,nis,unit_id,kelas_id',
                'student.educationUnit:id,name',
                'student.kelas:id,nama_kelas',
                'category:id,name,rule_name,level,is_permanent,cluster,direct_consequence',
                'reportedBy:id,name',
            ])
            ->where(function ($q) {
                $q->whereHas('category', fn ($cq) => $cq->where('is_permanent', true)->orWhere('level', 'berat'))
                  ->orWhere('points', '>=', 50);
            })
            ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->orderByDesc('transaction_date')
            ->limit(20)
            ->get();

        // SP2 & Keluar
        $severeWarningLetters = StudentWarningLetter::with([
                'student:id,full_name,nis,unit_id,kelas_id',
                'student.educationUnit:id,name',
                'student.kelas:id,nama_kelas',
                'issuedBy:id,name',
            ])
            ->whereIn('letter_type', ['sp2', 'keluar', 'sp1', 'st'])
            ->orderByDesc('issue_date')
            ->limit(15)
            ->get();

        // Indeks Kepatuhan Makro
        $totalStudents = Student::count();
        $violatingStudentsCount = StudentPointTransaction::distinct('student_id')
            ->when($activeYear, fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->count('student_id');

        $cleanStudents = max(0, $totalStudents - $violatingStudentsCount);
        $macroComplianceRate = $totalStudents > 0 ? round(($cleanStudents / $totalStudents) * 100, 1) : 100;

        // Peringkat Kepatuhan Unit (Top to Bottom)
        $unitRanking = EducationUnit::all()->map(function ($u) {
            $stCount = Student::where('unit_id', $u->id)->count();
            $violationCount = StudentPointTransaction::whereHas('student', fn ($sq) => $sq->where('unit_id', $u->id))->count();
            $cleanCount = max(0, $stCount - $violationCount);
            return [
                'id' => $u->id,
                'name' => $u->name,
                'total_students' => $stCount,
                'compliance_rate' => $stCount > 0 ? round(($cleanCount / $stCount) * 100, 1) : 100,
                'severe_cases_count' => StudentPointTransaction::whereHas('student', fn ($sq) => $sq->where('unit_id', $u->id))
                    ->whereHas('category', fn ($cq) => $cq->where('level', 'berat'))->count(),
            ];
        })->sortByDesc('compliance_rate')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'scorecard' => [
                    'total_students' => $totalStudents,
                    'clean_students' => $cleanStudents,
                    'compliance_rate' => $macroComplianceRate,
                    'total_severe_sp' => $severeWarningLetters->count(),
                    'total_permanent_violations' => StudentPointTransaction::whereHas('category', fn ($q) => $q->where('is_permanent', true))->count(),
                ],
                'critical_watchlist' => $criticalTransactions,
                'severe_warning_letters' => $severeWarningLetters,
                'unit_ranking' => $unitRanking,
            ],
        ]);
    }

    /**
     * Cari Santri / Siswa Dinamis untuk Pencatatan Pelanggaran Tata Tertib
     */
    public function searchStudents(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $unitId = $request->query('unit_id');
        $kelasId = $request->query('kelas_id');
        $programType = $request->query('program_type');
        $user = $request->user();

        $query = Student::query()
            ->with([
                'educationUnit:id,name,level,program_type',
                'kelas:id,nama_kelas,tingkat',
            ]);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            if ($accessibleUnitIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }
            $query->where(function ($sq) use ($accessibleUnitIds) {
                $sq->whereIn('unit_id', $accessibleUnitIds)
                    ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        }

        $query
            ->when($unitId && ! in_array(strtolower((string) $unitId), ['all', 'semua'], true), fn ($q) => $q->where('unit_id', $unitId))
            ->when($kelasId && ! in_array(strtolower((string) $kelasId), ['all', 'semua'], true), fn ($q) => $q->where('kelas_id', $kelasId))
            ->when($programType && ! in_array(strtolower((string) $programType), ['all', 'semua'], true), function ($q) use ($programType) {
                $q->whereHas('educationUnit', function ($uq) use ($programType) {
                    if ($programType === 'boarding') {
                        $uq->where('program_type', 'boarding')
                            ->orWhere('name', 'like', '%PONPES%')
                            ->orWhere('name', 'like', '%MAHAD%')
                            ->orWhere('name', 'like', '%ASRAMA%');
                    } else {
                        $uq->where('program_type', '!=', 'boarding')
                            ->where('name', 'not like', '%PONPES%')
                            ->where('name', 'not like', '%MAHAD%')
                            ->where('name', 'not like', '%ASRAMA%');
                    }
                });
            })
            ->when($search, function ($q) use ($search) {
                $s = '%' . trim($search) . '%';
                $q->where(function ($sub) use ($s) {
                    $sub->where('full_name', 'like', $s)
                        ->orWhere('nis', 'like', $s)
                        ->orWhere('nisn', 'like', $s);
                });
            })
            ->orderBy('full_name')
            ->limit(50);

        $students = $query->get()->map(function ($st) {
            $unitName = $st->educationUnit->name ?? 'Unit Sekolah';
            $isBoarding = ($st->educationUnit->program_type ?? '') === 'boarding'
                || Str::contains(strtoupper($unitName), ['PONPES', 'MAHAD', 'PESANTREN', 'ASRAMA']);

            $activePoints = (int) StudentPointTransaction::where('student_id', $st->id)
                ->where('status', '!=', 'dibatalkan')
                ->sum('points');

            $riskStatus = 'Aman (0 Poin)';
            $riskBadgeColor = 'success';
            if ($activePoints >= 250) {
                $riskStatus = 'Sanksi Kritis (Dikeluarkan)';
                $riskBadgeColor = 'danger';
            } elseif ($activePoints >= 200) {
                $riskStatus = 'SP 2 (200 - 249 Poin)';
                $riskBadgeColor = 'danger';
            } elseif ($activePoints >= 150) {
                $riskStatus = 'SP 1 (150 - 199 Poin)';
                $riskBadgeColor = 'danger';
            } elseif ($activePoints >= 100) {
                $riskStatus = 'Surat Teguran (100 - 149 Poin)';
                $riskBadgeColor = 'warning';
            } elseif ($activePoints >= 31) {
                $riskStatus = 'Sanksi Sedang (31 - 99 Poin)';
                $riskBadgeColor = 'warning';
            } elseif ($activePoints >= 1) {
                $riskStatus = 'Sanksi Ringan (1 - 30 Poin)';
                $riskBadgeColor = 'primary';
            }

            return [
                'id' => $st->id,
                'name' => $st->full_name ?? $st->nama_lengkap ?? $st->name ?? 'Santri/Siswa',
                'full_name' => $st->full_name ?? $st->nama_lengkap ?? $st->name ?? 'Santri/Siswa',
                'nis' => $st->nis ?: '-',
                'nisn' => $st->nisn ?: '-',
                'gender' => $st->gender ?? 'male',
                'unit_id' => $st->unit_id,
                'unit_name' => $unitName,
                'education_unit' => $st->educationUnit ? [
                    'id' => $st->educationUnit->id,
                    'name' => $st->educationUnit->name,
                    'program_type' => $st->educationUnit->program_type ?? ($isBoarding ? 'boarding' : 'fullday'),
                ] : null,
                'educationUnit' => $st->educationUnit ? [
                    'id' => $st->educationUnit->id,
                    'name' => $st->educationUnit->name,
                    'program_type' => $st->educationUnit->program_type ?? ($isBoarding ? 'boarding' : 'fullday'),
                ] : null,
                'is_boarding' => $isBoarding,
                'kelas_id' => $st->kelas_id,
                'kelas_name' => $st->kelas->nama_kelas ?? 'Kelas Belum Ditentukan',
                'kelas' => $st->kelas ? [
                    'id' => $st->kelas->id,
                    'nama_kelas' => $st->kelas->nama_kelas,
                ] : null,
                'total_points' => $activePoints,
                'risk_status' => $riskStatus,
                'risk_badge_color' => $riskBadgeColor,
                'avatar_url' => $st->avatar_url ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }
}
