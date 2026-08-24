<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClinicLog;
use App\Models\DisciplineCategory;
use App\Models\DormitoryDeposit;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Models\TahfizhExam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MusyrifModuleController extends Controller
{
    /**
     * Daftar Santri / Siswa Binaan untuk Dropdown Modal Musyrif
     */
    public function students(Request $request): JsonResponse
    {
        $query = Student::select('id', 'full_name', 'nama_lengkap', 'nis', 'nisn', 'kelas_id', 'status')
            ->where(function ($q) {
                $q->where('status', 'aktif')
                  ->orWhereNull('status');
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nama_lengkap', 'like', "%{$search}%")
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
        $request->validate(['student_id' => 'required|uuid']);

        $lastLog = \App\Models\TahfizhDailyLog::where('student_id', $request->student_id)
            ->whereNotNull('hafalan_surah_number')
            ->orderBy('created_at', 'desc')
            ->first();

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
            'type' => 'required|string|in:Ziyadah,Murajaah,Tasmi,Ujian',
            'juz' => 'required|integer|min:1|max:30',
            'surah_number' => 'required|integer|min:1|max:114',
            'ayat_start' => 'required|integer|min:1',
            'ayat_end' => 'required|integer|gte:ayat_start',
            'kelancaran' => 'required|string',
            'tajwid' => 'required|string',
            'makhraj' => 'required|string',
            'notes_teacher' => 'nullable|string',
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $surah = DB::table('quran_surahs')->where('nomor', $validated['surah_number'])->first();

        $log = \App\Models\TahfizhDailyLog::create([
            'student_id' => $student->id,
            'class_id' => $student->kelas_id ?? $student->class_id,
            'teacher_id' => $request->user()?->id,
            'record_date' => now()->toDateString(),
            'day_name' => now()->locale('id')->isoFormat('dddd'),
            'hafalan_surah_number' => $validated['surah_number'],
            'hafalan_surah_name' => $surah?->nama_latin ?? "Surah ke-{$validated['surah_number']}",
            'hafalan_ayah_start' => $validated['ayat_start'],
            'hafalan_ayah_end' => $validated['ayat_end'],
            'notes_teacher' => "[{$validated['type']} - Juz {$validated['juz']} | Tajwid: {$validated['tajwid']}, Makhraj: {$validated['makhraj']}, Kelancaran: {$validated['kelancaran']}] " . ($validated['notes_teacher'] ?? ''),
        ]);

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

        $validated['musyrif_id'] = $request->user()?->id;
        $validated['status'] = 'deposited';
        $deposit = DormitoryDeposit::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Penitipan barang berharga santri berhasil dicatat.',
            'data' => $deposit->load('student:id,full_name,nis'),
        ], 201);
    }

    public function retrieveDeposit(string $id): JsonResponse
    {
        $deposit = DormitoryDeposit::findOrFail($id);
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
}
