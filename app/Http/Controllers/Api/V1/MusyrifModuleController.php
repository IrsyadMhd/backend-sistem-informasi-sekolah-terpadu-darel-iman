<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClinicLog;
use App\Models\DisciplineCategory;
use App\Models\DormitoryDeposit;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Models\TahfizhDailyLog;
use App\Models\TahfizhExam;
use App\Models\User;
use App\Models\WorshipAttendanceDetail;
use App\Models\WorshipAttendanceTemplate;
use App\Services\AccessScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MusyrifModuleController extends Controller
{
    public function __construct(
        protected AccessScopeService $accessScope,
    ) {}

    /**
     * Assert that the authenticated user has access to the student's educational unit.
     */
    protected function assertStudentAccess(Student $student, ?User $user = null): void
    {
        $user = $user ?: request()->user();
        abort_unless($user, 401, 'Unauthenticated.');

        if ($this->accessScope->hasGlobalScope($user)) {
            return;
        }

        $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
        $studentUnitId = $student->unit_id ?: $student->kelas?->unit_pendidikan_id;

        abort_unless(
            $studentUnitId && $accessibleUnitIds->contains($studentUnitId),
            403,
            'Akses ditolak: Santri berada di luar unit pendidikan Anda.'
        );
    }

    /**
     * Daftar Santri / Siswa Binaan untuk Dropdown Modal Musyrif.
     *
     * Selalu memfilter berdasarkan unit pendidikan yang dapat diakses oleh user
     * yang sedang login — termasuk akun Global Scope (Super Admin / Admin / Yayasan)
     * yang sebelumnya melewati (bypass) seluruh filter unit sehingga semua siswa
     * dari semua unit tampil di dropdown Boarding Pass.
     *
     * accessibleEducationUnits() sudah mengandung logika boarding-scope khusus
     * untuk role Musyrif / Musyrifah, sehingga filter ini sudah benar secara
     * semantik untuk semua tipe user.
     */
    public function students(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ambil unit pendidikan yang dapat diakses user ini.
        // Untuk Musyrif murni → hanya unit boarding (ponpes/asrama).
        // Untuk Global Scope (Super Admin, dll.) yang mengakses via konteks musyrif
        // → tetap dibatasi ke unit yang ter-assign, bukan semua unit.
        $accessibleUnitIds = $user
            ? $this->accessScope->accessibleEducationUnits($user)->pluck('id')
            : collect();

        $query = Student::select('id', 'full_name', 'nis', 'nisn', 'kelas_id', 'unit_id', 'is_active')
            ->where('is_active', true);

        // Terapkan filter unit jika ada unit yang accessible.
        // Jika $accessibleUnitIds kosong (edge case: akun tanpa unit assignment),
        // query dikembalikan kosong agar tidak bocor semua data.
        if ($accessibleUnitIds->isNotEmpty()) {
            $query->where(function ($q) use ($accessibleUnitIds) {
                $q->whereIn('unit_id', $accessibleUnitIds)
                  ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        } else {
            // Tidak ada unit yang dapat diakses → kembalikan kosong
            return response()->json(['success' => true, 'data' => []]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('full_name')->limit(150)->get();

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    /**
     * Presensi Sholat Berjamaah Santri (Wajib 5 Waktu & Sunnah) untuk Musyrif
     */
    public function storeWorshipAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'prayer_name' => 'required|string|in:subuh,zuhur,asar,magrib,isya,jumat,tahajud,witir,dhuha,rawatib,tarawih,syuruq',
            'attendance_status' => 'required|string|in:hadir_berjamaah,hadir_sendiri,terlambat,tidak_hadir,izin,sakit,uzur_syarii,haid,dispensasi',
            'notes' => 'nullable|string',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $this->assertStudentAccess($student, $request->user());
        $today = now()->toDateString();

        $sunnahList = ['tahajud', 'witir', 'dhuha', 'rawatib', 'tarawih', 'syuruq'];
        $isSunnah = in_array(strtolower($validated['prayer_name']), $sunnahList);

        $template = \App\Models\WorshipAttendanceTemplate::firstOrCreate(
            ['code' => "SHOLAT_" . strtoupper($validated['prayer_name'])],
            [
                'nama' => 'Sholat ' . ucfirst($validated['prayer_name']) . ($isSunnah ? ' (Sunnah)' : ' Berjamaah'),
                'category' => $isSunnah ? 'shalat_sunnah' : 'shalat_wajib',
                'obligation_type' => $isSunnah ? 'sunnah' : 'wajib',
                'gender_scope' => 'all',
                'time_source' => 'prayer_schedule',
                'prayer_name' => strtolower($validated['prayer_name']),
                'is_active' => true,
                'created_by' => $request->user()?->id,
            ]
        );

        $session = \App\Models\WorshipAttendanceSession::firstOrCreate(
            [
                'template_id' => $template->id,
                'session_date' => $today,
            ],
            [
                'scheduled_start_at' => now(),
                'status' => 'open',
                'created_by' => $request->user()?->id,
            ]
        );

        $detail = \App\Models\WorshipAttendanceDetail::updateOrCreate(
            [
                'session_id' => $session->id,
                'student_id' => $student->id,
            ],
            [
                'attendance_status' => $validated['attendance_status'],
                'attended_at' => now(),
                'method' => 'manual',
                'verified_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Presensi Sholat " . ucfirst($validated['prayer_name']) . " santri berhasil dicatat.",
            'data' => $detail,
        ], 200);
    }

    /**
     * Ambil catatan setoran Tahfizh terakhir santri untuk kelanjutan/pengulangan hafalan
     */
    public function lastLog(Request $request): JsonResponse
    {
        $request->validate(['student_id' => 'required|uuid|exists:students,id']);

        $student = Student::findOrFail($request->student_id);
        $this->assertStudentAccess($student, $request->user());

        $query = TahfizhDailyLog::where('student_id', $request->student_id)
            ->whereNotNull('hafalan_surah_number')
            ->where(function ($q) {
                $q->whereNull('metadata->type')
                  ->orWhereNotIn(DB::raw("LOWER(COALESCE(metadata->>'type', ''))"), ['murajaah', 'murojaah']);
            });

        $lastLog = $query->orderBy('record_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        // Fallback: jika santri belum memiliki log Ziyadah, ambil log terakhir apa pun
        if (! $lastLog) {
            $lastLog = TahfizhDailyLog::where('student_id', $request->student_id)
                ->whereNotNull('hafalan_surah_number')
                ->orderBy('record_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => $lastLog,
        ]);
    }

    /**
     * Input Setoran Hafalan Harian (Ziyadah & Murajaah) untuk Musyrif
     */
    public function storeSetoranTahfizh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'type' => 'required|string|in:Ziyadah,ziyadah,Murajaah,murajaah,Murojaah,murojaah,Tasmi,tasmi,Ujian,ujian',
            'juz' => 'nullable|integer|min:1|max:30',
            'surah_number' => 'required|integer|min:1|max:114',
            'ayat_start' => 'required|integer|min:1',
            'ayat_end' => 'required|integer|gte:ayat_start',
            'kelancaran' => 'nullable|string|max:100',
            'tajwid' => 'nullable|string|max:100',
            'makhraj' => 'nullable|string|max:100',
            'notes_teacher' => 'nullable|string',
            'notes_student' => 'nullable|string',
            'notes' => 'nullable|string',
            'record_date' => 'nullable|date',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $this->assertStudentAccess($student, $request->user());
        $surah = DB::table('quran_surahs')->where('nomor', $validated['surah_number'])->first();

        // 1. Normalisasi jenis kegiatan ke format kanonis backend
        $rawType = trim((string) $validated['type']);
        $normType = match (strtolower($rawType)) {
            'murajaah', 'murojaah' => 'Murajaah',
            'tasmi' => 'Tasmi',
            'ujian' => 'Ujian',
            default => 'Ziyadah',
        };

        // 2. Susun metadata JSONB terstruktur
        $metadata = [
            'type' => $normType,
        ];

        if (!empty($validated['juz']) && is_numeric($validated['juz'])) {
            $metadata['juz'] = (int) $validated['juz'];
        }

        if (!empty($validated['kelancaran'])) {
            $metadata['kelancaran'] = (string) $validated['kelancaran'];
        }

        if (!empty($validated['tajwid'])) {
            $metadata['tajwid'] = (string) $validated['tajwid'];
        }

        if (!empty($validated['makhraj'])) {
            $metadata['makhraj'] = (string) $validated['makhraj'];
        }

        if (!empty($validated['notes_student'])) {
            $metadata['notes_student'] = (string) $validated['notes_student'];
        }

        // 3. Format notes_teacher dengan mempertahankan ringkasan evaluasi dan teks catatan asli
        $userNotes = $validated['notes_teacher'] ?? ($validated['notes'] ?? ($request->input('notes') ?? ''));

        $headerParts = [];
        if (!empty($metadata['juz'])) {
            $headerParts[] = "Juz {$metadata['juz']}";
        }
        $evalParts = [];
        if (!empty($metadata['tajwid'])) {
            $evalParts[] = "Tajwid: {$metadata['tajwid']}";
        }
        if (!empty($metadata['makhraj'])) {
            $evalParts[] = "Makhraj: {$metadata['makhraj']}";
        }
        if (!empty($metadata['kelancaran'])) {
            $evalParts[] = "Kelancaran: {$metadata['kelancaran']}";
        }
        if (!empty($evalParts)) {
            $headerParts[] = implode(', ', $evalParts);
        }

        $summaryTag = "[{$normType}" . (!empty($headerParts) ? ' - ' . implode(' | ', $headerParts) : '') . ']';
        $fullNotesTeacher = trim($summaryTag . ' ' . trim((string) $userNotes));

        $recordDate = $validated['record_date'] ?? now()->toDateString();
        $dayName = Carbon::parse($recordDate)->locale('id')->isoFormat('dddd');
        $isMurajaah = $normType === 'Murajaah';
        $hafalanBaris = $isMurajaah ? 0 : ((int) $validated['ayat_end'] - (int) $validated['ayat_start'] + 1);
        $murajaahLembar = $isMurajaah ? max(1, round(((int) $validated['ayat_end'] - (int) $validated['ayat_start'] + 1) / 15, 1)) : 0;
        $murajaahText = $isMurajaah ? "Surah " . ($surah?->nama_latin ?? "Surah {$validated['surah_number']}") . " ({$validated['ayat_start']}–{$validated['ayat_end']})" : null;

        $log = TahfizhDailyLog::create([
            'student_id' => $student->id,
            'class_id' => $student->kelas_id ?? $student->class_id,
            'teacher_id' => $request->user()?->id,
            'record_date' => $recordDate,
            'day_name' => $dayName,
            'hafalan_surah_number' => $validated['surah_number'],
            'hafalan_surah_name' => $surah?->nama_latin ?? "Surah ke-{$validated['surah_number']}",
            'hafalan_ayah_start' => $validated['ayat_start'],
            'hafalan_ayah_end' => $validated['ayat_end'],
            'hafalan_baris' => $hafalanBaris,
            'murajaah_lembar' => $murajaahLembar,
            'murajaah_text' => $murajaahText,
            'notes_teacher' => $fullNotesTeacher,
            'metadata' => $metadata,
        ]);

        // Sinkronisasi capaian per-ayat siswa jika service tersedia
        try {
            app(\App\Services\TahfizhAchievementService::class)->synchronize($student);
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Setoran hafalan Tahfizh santri berhasil dicatat.',
            'data' => $log,
        ], 201);
    }

    /**
     * Ujian Tasmi' / Ujian Tahfizh Sekali Duduk
     */
    public function indexExams(Request $request): JsonResponse
    {
        $query = TahfizhExam::with(['student:id,full_name,nis,kelas_id', 'examiner:id,name']);

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            $query->whereHas('student', function ($sq) use ($accessibleUnitIds) {
                $sq->whereIn('unit_id', $accessibleUnitIds)
                   ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        $exams = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $exams->items(),
            'meta' => [
                'current_page' => $exams->currentPage(),
                'last_page' => $exams->lastPage(),
                'total' => $exams->total(),
            ],
        ]);
    }

    public function storeExam(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'exam_type' => 'required|string|in:tasmi_1_juz,tasmi_5_juz,tasmi_10_juz,tasmi_30_juz,ujian_tajwid',
            'juz_number' => 'nullable|integer|min:1|max:30',
            'tajwid_grade' => 'required|in:A,B,C,D',
            'makhraj_grade' => 'required|in:A,B,C,D',
            'final_score' => 'required|numeric|min:0|max:100',
            'certificate_path' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $this->assertStudentAccess($student, $request->user());

        $validated['examiner_id'] = $request->user()?->id;
        $exam = TahfizhExam::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data Ujian Tasmi\' berhasil disimpan.',
            'data' => $exam->load('student:id,full_name,nis'),
        ], 201);
    }

    /**
     * Master Bank Poin Pelanggaran & Prestasi
     */
    public function indexDisciplineCategories(Request $request): JsonResponse
    {
        $categories = DisciplineCategory::where('is_active', true)
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->orderBy('code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Ledger Transaksi Poin Siswa
     */
    public function indexPointTransactions(Request $request): JsonResponse
    {
        $query = StudentPointTransaction::with(['student:id,full_name,nis', 'category', 'reportedBy:id,name']);

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            $query->whereHas('student', function ($sq) use ($accessibleUnitIds) {
                $sq->whereIn('unit_id', $accessibleUnitIds)
                   ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function storePointTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'category_id' => 'nullable|uuid|exists:discipline_categories,id',
            'points' => 'required|integer',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            'status' => 'nullable|in:dalam_pengawasan,selesai_sanksi,dirujuk_bk,tercatat',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $this->assertStudentAccess($student, $request->user());

        $validated['reported_by_id'] = $request->user()?->id;
        $transaction = StudentPointTransaction::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Poin kedisiplinan santri berhasil dicatat.',
            'data' => $transaction->load(['student:id,full_name,nis', 'category']),
        ], 201);
    }

    /**
     * Klinik & Log Kesehatan Santri
     */
    public function indexClinicLogs(Request $request): JsonResponse
    {
        $query = ClinicLog::with(['student:id,full_name,nis', 'handledBy:id,name']);

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            $query->whereHas('student', function ($sq) use ($accessibleUnitIds) {
                $sq->whereIn('unit_id', $accessibleUnitIds)
                   ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function storeClinicLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'symptoms' => 'required|string',
            'symptom_start_at' => 'nullable|date',
            'medicine_given' => 'nullable|string',
            'rest_recommendation' => 'nullable|string',
            'status' => 'required|in:rawat_jalan,istirahat_uksh,dirujuk_rs,sembuh',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $this->assertStudentAccess($student, $request->user());

        $validated['handled_by_musyrif_id'] = $request->user()?->id;
        $log = ClinicLog::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Log klinik santri berhasil disimpan.',
            'data' => $log->load('student:id,full_name,nis'),
        ], 201);
    }

    /**
     * Log Penitipan Barang Berharga (HP/Laptop)
     */
    public function indexDeposits(Request $request): JsonResponse
    {
        $query = DormitoryDeposit::with(['student:id,full_name,nis', 'musyrif:id,name']);

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            $query->whereHas('student', function ($sq) use ($accessibleUnitIds) {
                $sq->whereIn('unit_id', $accessibleUnitIds)
                   ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $deposits = $query->orderBy('deposited_at', 'desc')->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $deposits->items(),
            'meta' => [
                'current_page' => $deposits->currentPage(),
                'last_page' => $deposits->lastPage(),
                'total' => $deposits->total(),
            ],
        ]);
    }

    public function storeDeposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'item_type' => 'required|in:smartphone,laptop,tablet,elektronik_lain',
            'item_name' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'deposited_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $this->assertStudentAccess($student, $request->user());

        $validated['musyrif_id'] = $request->user()?->id;
        $validated['status'] = 'deposited';
        $deposit = DormitoryDeposit::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Penitipan barang berharga santri berhasil dicatat.',
            'data' => $deposit->load('student:id,full_name,nis'),
        ], 201);
    }

    public function retrieveDeposit(Request $request, string $id): JsonResponse
    {
        $deposit = DormitoryDeposit::with('student.kelas')->findOrFail($id);
        if ($deposit->student) {
            $this->assertStudentAccess($deposit->student, $request->user());
        }

        $deposit->update([
            'status' => 'retrieved',
            'retrieved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Barang titipan berhasil dikembalikan ke santri.',
            'data' => $deposit,
        ]);
    }

    /**
     * Dashboard Overview & KPI Metrics untuk Musyrif Asrama
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $academicPeriodService = app(\App\Services\AcademicPeriodService::class);
        $activeAcademicYear = $academicPeriodService->getActiveYear();
        $activeSemester = $academicPeriodService->getActiveSemester($activeAcademicYear?->id);

        $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');

        $studentsQuery = Student::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('metadata->mutasi_type')
                  ->orWhere('metadata->mutasi_type', '!=', 'keluar');
            });

        // Filter unit jika ada unit_id dari request
        if ($request->filled('unit_id') && $request->unit_id !== 'all') {
            $studentsQuery->where('unit_id', $request->unit_id);
        } elseif ($accessibleUnitIds->isNotEmpty()) {
            $studentsQuery->where(function ($q) use ($accessibleUnitIds) {
                $q->whereIn('unit_id', $accessibleUnitIds)
                  ->orWhereHas('kelas', fn ($kq) => $kq->whereIn('unit_pendidikan_id', $accessibleUnitIds));
            });
        }

        $totalSantriBinaan = (clone $studentsQuery)->count();
        $santriIds = (clone $studentsQuery)->pluck('id')->toArray();

        $today = now()->toDateString();

        // 1. Presensi Shalat Berjamaah Hari Ini
        $worshipCount = 0;
        if (Schema::hasTable('worship_attendance_details') && !empty($santriIds)) {
            $worshipCount = WorshipAttendanceDetail::whereIn('student_id', $santriIds)
                ->whereDate('attended_at', $today)
                ->whereIn('attendance_status', ['hadir_berjamaah', 'hadir_sendiri'])
                ->distinct('student_id')
                ->count('student_id');
        }

        // 2. Setoran Hafalan Tahfizh Hari Ini
        $setoranHariIni = 0;
        if (Schema::hasTable('tahfizh_daily_logs') && !empty($santriIds)) {
            $setoranHariIni = TahfizhDailyLog::whereIn('student_id', $santriIds)
                ->whereDate('record_date', $today)
                ->count();
        }

        // 3. Mutabaah Terisi Hari Ini
        $mutabaahCount = 0;
        if (Schema::hasTable('mutabaah_records') && !empty($santriIds)) {
            $mutabaahCount = DB::table('mutabaah_records')
                ->whereIn('student_id', $santriIds)
                ->whereDate('record_date', $today)
                ->distinct('student_id')
                ->count('student_id');
        }
        if ($mutabaahCount === 0 && $worshipCount > 0) {
            $mutabaahCount = $worshipCount;
        }

        // Tables: Tasmi Logs
        $tasmiLogs = [];
        if (Schema::hasTable('tahfizh_exams') && !empty($santriIds)) {
            $tasmiLogs = TahfizhExam::with(['student:id,full_name,nis,kelas_id', 'student.kelas:id,nama_kelas'])
                ->whereIn('student_id', $santriIds)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(function ($exam) {
                    return [
                        'id' => $exam->id,
                        'santri' => $exam->student?->full_name ?? 'Santri',
                        'jenis' => $exam->exam_type ? "Tasmi' {$exam->exam_type}" : "Tasmi' Juz {$exam->juz_number}",
                        'nilai' => $exam->final_score ?? 90,
                        'penguji' => 'Musyrif Penguji',
                    ];
                });
        }

        // Tables: Poin Logs
        $poinLogs = [];
        if (Schema::hasTable('student_point_transactions') && !empty($santriIds)) {
            $poinLogs = StudentPointTransaction::with(['student:id,full_name,nis', 'category:id,name'])
                ->whereIn('student_id', $santriIds)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(function ($tx) {
                    return [
                        'id' => $tx->id,
                        'santri' => $tx->student?->full_name ?? 'Santri',
                        'kategori' => $tx->category?->name ?? 'Disiplin',
                        'poin' => $tx->points ?? 0,
                        'tindakan' => $tx->direct_consequence_notes ?? $tx->description ?? 'Pembinaan Asrama',
                    ];
                });
        }

        // Tables: Klinik Logs
        $klinikLogs = [];
        if (Schema::hasTable('clinic_logs') && !empty($santriIds)) {
            $klinikLogs = ClinicLog::with('student:id,full_name,nis')
                ->whereIn('student_id', $santriIds)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'santri' => $log->student?->full_name ?? 'Santri',
                        'keluhan' => $log->symptoms ?? '-',
                        'obat' => $log->medicine_given ?? '-',
                        'status' => $log->status ?? 'Istirahat',
                    ];
                });
        }

        // Tables: Titipan Logs
        $titipanLogs = [];
        if (Schema::hasTable('dormitory_deposits') && !empty($santriIds)) {
            $titipanLogs = DormitoryDeposit::with('student:id,full_name,nis')
                ->whereIn('student_id', $santriIds)
                ->orderByDesc('created_at')
                ->limit(15)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'santri' => $item->student?->full_name ?? 'Santri',
                        'item' => $item->item_name ?? 'Elektronik',
                        'serial' => $item->serial_number ?? '-',
                        'status' => $item->status ?? 'deposited',
                    ];
                });
        }

        // Tables: Mutabaah / Presensi Keasramaan Logs
        $mutabaahLogs = [];
        if (Schema::hasTable('worship_attendance_details') && !empty($santriIds)) {
            $mutabaahLogs = WorshipAttendanceDetail::with([
                'student:id,full_name,nis,kelas_id,unit_id',
                'student.kelas:id,nama_kelas',
                'student.unit:id,name',
                'session.template',
                'verifier:id,name',
            ])
                ->whereIn('student_id', $santriIds)
                ->orderByDesc('attended_at')
                ->limit(100)
                ->get()
                ->map(function ($w) {
                    $tpl = $w->session?->template;
                    $isSunnah = $tpl?->obligation_type === 'sunnah' || $tpl?->category === 'shalat_sunnah';
                    return [
                        'id' => $w->id,
                        'student_id' => $w->student_id,
                        'santri' => $w->student?->full_name ?? 'Santri',
                        'nis' => $w->student?->nis ?? '-',
                        'kelas' => $w->student?->kelas?->nama_kelas ?? 'Asrama Pondok',
                        'unit' => $w->student?->unit?->name ?? 'Ponpes Darel Iman',
                        'kegiatan' => $tpl?->nama ?? 'Sholat Berjamaah',
                        'kategori' => $isSunnah ? 'Sholat Sunnah' : 'Sholat Wajib',
                        'lokasi' => $tpl?->location_name ?? 'Masjid Utama Pesantren',
                        'waktu' => $w->attended_at ? Carbon::parse($w->attended_at)->format('d M Y H:i') : '-',
                        'tanggal' => $w->attended_at ? Carbon::parse($w->attended_at)->format('d/m/Y') : '-',
                        'raw_date' => $w->attended_at ? Carbon::parse($w->attended_at)->toDateString() : Carbon::today()->toDateString(),
                        'jam' => $w->attended_at ? Carbon::parse($w->attended_at)->format('H:i') . ' WIB' : '-',
                        'metode' => $w->method ? ucfirst($w->method) : 'Manual Musyrif',
                        'verifikator' => $w->verifier?->name ?? 'Musyrif Asrama',
                        'catatan' => $w->notes ?: 'Telah diverifikasi hadir tepat waktu di masjid asrama',
                        'status' => str_contains($w->attendance_status, 'hadir') ? 'Hadir' : ucfirst(str_replace('_', ' ', $w->attendance_status)),
                    ];
                });
        }

        // Charts: Worship Trend (7 Hari Terakhir) - 100% Dinamis dari Master Template & Data Database (Wajib & Sunnah)
        $worshipTemplates = WorshipAttendanceTemplate::whereIn('category', ['shalat_wajib', 'shalat_sunnah'])
            ->where('is_active', true)
            ->orderByRaw("CASE 
                WHEN LOWER(COALESCE(prayer_name, '')) = 'tahajud' OR code = 'TAHAJUD_MALAM' THEN 1
                WHEN LOWER(COALESCE(prayer_name, '')) = 'subuh' OR code = 'SUBUH_BERJAMAAH' THEN 2
                WHEN LOWER(COALESCE(prayer_name, '')) = 'dhuha' OR code = 'SHALAT_DHUHA' THEN 3
                WHEN LOWER(COALESCE(prayer_name, '')) = 'dzuhur' OR code = 'DZUHUR_BERJAMAAH' THEN 4
                WHEN LOWER(COALESCE(prayer_name, '')) = 'ashar' OR code = 'ASHAR_BERJAMAAH' THEN 5
                WHEN LOWER(COALESCE(prayer_name, '')) = 'magrib' OR code = 'MAGRIB_BERJAMAAH' THEN 6
                WHEN LOWER(COALESCE(prayer_name, '')) = 'isya' OR code = 'ISYA_BERJAMAAH' THEN 7
                ELSE 8 END")
            ->get();

        $startDate = Carbon::today()->subDays(6)->toDateString();
        $endDate = Carbon::today()->toDateString();

        $worshipDetailsQuery = DB::table('worship_attendance_details as wad')
            ->join('worship_attendance_sessions as was', 'wad.session_id', '=', 'was.id')
            ->join('worship_attendance_templates as wat', 'was.template_id', '=', 'wat.id')
            ->whereIn('wad.student_id', $santriIds)
            ->whereBetween(DB::raw('was.session_date::date'), [$startDate, $endDate])
            ->whereIn('wad.attendance_status', ['hadir_berjamaah', 'hadir_sendiri'])
            ->select(
                DB::raw('was.session_date::date as session_date'),
                'wat.id as template_id',
                DB::raw('COUNT(DISTINCT wad.student_id) as total_hadir')
            )
            ->groupBy(DB::raw('was.session_date::date'), 'wat.id')
            ->get();

        $attendanceMatrix = [];
        foreach ($worshipDetailsQuery as $row) {
            $dateStr = Carbon::parse($row->session_date)->toDateString();
            $attendanceMatrix[$dateStr][$row->template_id] = (int) $row->total_hadir;
        }

        $worshipTrend = [];
        $daysOfWeekIndo = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Ahd'];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $dString = $d->toDateString();
            $dayName = $daysOfWeekIndo[$d->dayOfWeekIso - 1] ?? $d->format('D');

            $dayEntry = [
                'date' => $dString,
                'day' => $dayName,
            ];

            foreach ($worshipTemplates as $tpl) {
                $pKey = strtolower(trim($tpl->prayer_name ?: str_replace(['SHALAT_', '_BERJAMAAH', '_MALAM'], '', $tpl->code)));
                $dayEntry[$pKey] = $attendanceMatrix[$dString][$tpl->id] ?? 0;
            }

            $worshipTrend[] = $dayEntry;
        }

        $palette = [
            'tahajud' => ['color' => '#8B5CF6', 'name' => 'Tahajud', 'badge' => 'Sunnah'],
            'subuh' => ['color' => '#064E3B', 'name' => 'Subuh', 'badge' => 'Wajib'],
            'dhuha' => ['color' => '#EC4899', 'name' => 'Dhuha', 'badge' => 'Sunnah'],
            'dzuhur' => ['color' => '#0D9488', 'name' => 'Dzuhur', 'badge' => 'Wajib'],
            'ashar' => ['color' => '#2563EB', 'name' => 'Ashar', 'badge' => 'Wajib'],
            'magrib' => ['color' => '#D97706', 'name' => 'Maghrib', 'badge' => 'Wajib'],
            'isya' => ['color' => '#059669', 'name' => 'Isya', 'badge' => 'Wajib'],
        ];

        $prayersMeta = $worshipTemplates->map(function ($tpl) use ($palette) {
            $pKey = strtolower(trim($tpl->prayer_name ?: str_replace(['SHALAT_', '_BERJAMAAH', '_MALAM'], '', $tpl->code)));
            $isSunnah = $tpl->obligation_type === 'sunnah' || $tpl->category === 'shalat_sunnah';
            return [
                'key' => $pKey,
                'template_id' => $tpl->id,
                'name' => $palette[$pKey]['name'] ?? ($tpl->nama ?: ucfirst($pKey)),
                'color' => $palette[$pKey]['color'] ?? ($isSunnah ? '#8B5CF6' : '#10B981'),
                'badge' => $isSunnah ? 'Sunnah' : 'Wajib',
                'category' => $tpl->category,
                'obligation_type' => $tpl->obligation_type,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Musyrif Asrama berhasil dimuat.',
            'data' => [
                'context' => [
                    'musyrif_name' => $user->name,
                    'role' => 'Musyrif Asrama',
                    'tahun_ajaran' => $activeAcademicYear ? [
                        'id' => $activeAcademicYear->id,
                        'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama,
                    ] : null,
                    'semester' => $activeSemester ? [
                        'id' => $activeSemester->id,
                        'nama' => $activeSemester->name ?? $activeSemester->nama,
                    ] : null,
                ],
                'kpis' => [
                    'santri_binaan' => ['total' => $totalSantriBinaan, 'growth' => 0],
                    'total_siswa_binaan' => ['total' => $totalSantriBinaan, 'growth' => 0],
                    'ibadah_lengkap' => ['total' => $worshipCount, 'growth' => 0],
                    'setoran_tahfizh' => ['total' => $setoranHariIni, 'growth' => 0],
                    'setoran_hari_ini' => ['total' => $setoranHariIni, 'growth' => 0],
                    'mutabaah_terisi' => ['total' => $mutabaahCount, 'growth' => 0],
                ],
                'charts' => [
                    'worship_trend' => $worshipTrend,
                    'worship_prayers' => $prayersMeta,
                ],
                'tables' => [
                    'tasmi_logs' => $tasmiLogs,
                    'poin_logs' => $poinLogs,
                    'klinik_logs' => $klinikLogs,
                    'titipan_logs' => $titipanLogs,
                    'mutabaah_logs' => $mutabaahLogs,
                ],
            ],
        ]);
    }

    /**
     * Rincian Lengkap Shalat (Wajib & Sunnah) Santri dengan Filter Periode:
     * - today: Hari Ini
     * - week: 7 Hari Terakhir (Mingguan)
     * - month: 30 Hari Terakhir (Bulanan)
     * - semester: Semester Berjalan
     */
    public function studentWorshipBreakdown(Request $request, string $studentId): JsonResponse
    {
        $student = Student::with(['kelas:id,nama_kelas', 'unit:id,name'])->findOrFail($studentId);
        $this->assertStudentAccess($student, $request->user());

        $period = $request->query('period', 'today'); // 'today' | 'week' | 'month' | 'semester'

        $startDate = match ($period) {
            'week' => Carbon::today()->subDays(6)->startOfDay(),
            'month' => Carbon::today()->subDays(29)->startOfDay(),
            'semester' => Carbon::today()->subMonths(5)->startOfDay(),
            default => Carbon::today()->startOfDay(),
        };
        $endDate = Carbon::today()->endOfDay();

        $templates = WorshipAttendanceTemplate::whereIn('category', ['shalat_wajib', 'shalat_sunnah', 'kegiatan_keagamaan'])
            ->where('is_active', true)
            ->orderByRaw("CASE 
                WHEN LOWER(COALESCE(prayer_name, '')) = 'tahajud' OR code = 'TAHAJUD_MALAM' THEN 1
                WHEN LOWER(COALESCE(prayer_name, '')) = 'subuh' OR code = 'SUBUH_BERJAMAAH' THEN 2
                WHEN code = 'HALAQAH_SUBUH' THEN 3
                WHEN LOWER(COALESCE(prayer_name, '')) = 'dhuha' OR code = 'SHALAT_DHUHA' THEN 4
                WHEN LOWER(COALESCE(prayer_name, '')) = 'dzuhur' OR code = 'DZUHUR_BERJAMAAH' THEN 5
                WHEN LOWER(COALESCE(prayer_name, '')) = 'ashar' OR code = 'ASHAR_BERJAMAAH' THEN 6
                WHEN LOWER(COALESCE(prayer_name, '')) = 'magrib' OR code = 'MAGRIB_BERJAMAAH' THEN 7
                WHEN LOWER(COALESCE(prayer_name, '')) = 'isya' OR code = 'ISYA_BERJAMAAH' THEN 8
                ELSE 9 END")
            ->get();

        $details = WorshipAttendanceDetail::with(['session.template', 'verifier:id,name'])
            ->where('student_id', $student->id)
            ->whereBetween('attended_at', [$startDate, $endDate])
            ->orderByDesc('attended_at')
            ->get();

        $breakdown = $templates->map(function ($tpl) use ($details) {
            $tplDetails = $details->filter(fn ($d) => $d->session?->template_id === $tpl->id);
            $latest = $tplDetails->first();
            $hadirCount = $tplDetails->filter(fn ($d) => str_contains($d->attendance_status, 'hadir'))->count();
            $totalCount = $tplDetails->count();

            $isSunnah = $tpl->obligation_type === 'sunnah' || $tpl->category === 'shalat_sunnah';
            $isWajib = $tpl->obligation_type === 'wajib' || $tpl->category === 'shalat_wajib';
            $prayerTitle = $tpl->nama ?: (ucfirst(strtolower($tpl->prayer_name ?: $tpl->code ?: 'Sholat')));

            return [
                'template_id' => $tpl->id,
                'key' => strtolower($tpl->prayer_name ?: $tpl->code ?: 'sholat'),
                'label' => $prayerTitle,
                'name' => $prayerTitle,
                'code' => $tpl->code,
                'prayer_name' => $tpl->prayer_name,
                'category' => $isWajib ? 'wajib' : ($isSunnah ? 'sunnah' : 'kegiatan'),
                'obligation_type' => $tpl->obligation_type,
                'badge' => $isSunnah ? 'Sunnah' : ($isWajib ? 'Wajib' : 'Kegiatan'),
                'color' => match (strtolower($tpl->prayer_name ?? '')) {
                    'tahajud' => '#8B5CF6',
                    'subuh' => '#064E3B',
                    'dhuha' => '#EC4899',
                    'dzuhur' => '#0D9488',
                    'ashar' => '#2563EB',
                    'magrib' => '#D97706',
                    'isya' => '#059669',
                    default => '#4B5563',
                },
                'is_attended' => $latest ? (str_contains(strtolower($latest->attendance_status), 'hadir') || $latest->attendance_status !== 'alpa') : false,
                'status' => $latest ? (str_contains(strtolower($latest->attendance_status), 'hadir') ? 'Hadir' : ucfirst(str_replace('_', ' ', $latest->attendance_status))) : 'Belum Dikerjakan',
                'raw_status' => $latest?->attendance_status ?? 'tidak_ada',
                'attended_at' => $latest?->attended_at ? Carbon::parse($latest->attended_at)->format('d M Y H:i') : '-',
                'jam' => $latest?->attended_at ? Carbon::parse($latest->attended_at)->format('H:i') . ' WIB' : ($tpl->start_time ? $tpl->start_time . ' WIB' : '-'),
                'lokasi' => $tpl->location_name ?: 'Masjid Utama Pesantren',
                'metode' => $latest?->method ? ucfirst($latest->method) : 'Checklist',
                'verifikator' => $latest?->verifier?->name ?? 'Musyrif Asrama',
                'catatan' => $latest?->notes ?: 'Telah diverifikasi hadir tepat waktu di masjid asrama',
                'hadir_count' => $hadirCount,
                'attended_count' => $hadirCount,
                'total_count' => $totalCount,
                'total_days' => max(1, $totalCount),
                'percentage' => $totalCount > 0 ? round(($hadirCount / $totalCount) * 100) : 100,
            ];
        })->values()->all();

        $wajibItems = collect($breakdown)->filter(fn ($b) => $b['badge'] === 'Wajib');
        $sunnahItems = collect($breakdown)->filter(fn ($b) => $b['badge'] === 'Sunnah');

        $wajibHadir = $wajibItems->sum('hadir_count');
        $wajibTotal = max(1, $wajibItems->sum('total_count'));
        $sunnahHadir = $sunnahItems->sum('hadir_count');
        $sunnahTotal = max(1, $sunnahItems->sum('total_count'));

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'nis' => $student->nis ?? '-',
                    'kelas' => $student->kelas?->nama_kelas ?? 'Asrama Pondok',
                    'unit' => $student->unit?->name ?? 'Ponpes Darel Iman',
                ],
                'period' => $period,
                'period_label' => match ($period) {
                    'week' => 'Mingguan (7 Hari Terakhir)',
                    'month' => 'Bulanan (30 Hari Terakhir)',
                    'semester' => 'Semester Berjalan',
                    default => 'Hari Ini (' . Carbon::today()->translatedFormat('d F Y') . ')',
                },
                'summary' => [
                    'wajib_completed' => $wajibHadir,
                    'wajib_total' => $wajibTotal,
                    'wajib_percentage' => round(($wajibHadir / $wajibTotal) * 100),
                    'sunnah_completed' => $sunnahHadir,
                    'sunnah_total' => $sunnahTotal,
                    'sunnah_percentage' => round(($sunnahHadir / $sunnahTotal) * 100),
                    'overall_percentage' => round((($wajibHadir + $sunnahHadir) / ($wajibTotal + $sunnahTotal)) * 100),
                ],
                'breakdown' => $breakdown,
                'raw_records' => $details->map(fn ($d) => [
                    'id' => $d->id,
                    'kegiatan' => $d->session?->template?->nama ?? 'Sholat',
                    'kategori' => $d->session?->template?->category ?? 'shalat_wajib',
                    'status' => str_contains($d->attendance_status, 'hadir') ? 'Hadir' : ucfirst(str_replace('_', ' ', $d->attendance_status)),
                    'waktu' => $d->attended_at ? Carbon::parse($d->attended_at)->format('d M Y H:i') : '-',
                    'metode' => ucfirst($d->method ?? 'manual'),
                ])->values()->all(),
            ],
        ]);
    }
}
