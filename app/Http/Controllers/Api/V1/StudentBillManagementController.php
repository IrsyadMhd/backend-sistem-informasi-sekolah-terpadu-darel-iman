<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\BillPayment;
use App\Models\FeeCategory;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\StudentBill;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentBillManagementController extends Controller
{
    /**
     * Menampilkan daftar tagihan siswa dengan filter dan pencarian.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentBill::query()
            ->with([
                'student' => fn ($q) => $q->select('id', 'nis', 'nisn', 'full_name', 'kelas_id', 'unit_id')
                    ->with(['kelas:id,nama_kelas,unit_pendidikan_id', 'kelas.unitPendidikan:id,name']),
                'feeCategory:id,code,name,default_amount',
                'academicYear:id,name,is_active',
                'payments',
            ]);

        // Filter Unit Pendidikan
        if ($request->filled('unit_id')) {
            $unitId = $request->input('unit_id');
            $query->whereHas('student', function ($q) use ($unitId) {
                $q->where('unit_id', $unitId)
                    ->orWhereHas('kelas', fn ($kq) => $kq->where('unit_pendidikan_id', $unitId));
            });
        }

        // Filter Kelas / Rombel
        if ($request->filled('class_id') || $request->filled('kelas_id')) {
            $classId = $request->input('class_id') ?: $request->input('kelas_id');
            $query->whereHas('student', fn ($q) => $q->where('kelas_id', $classId));
        }

        // Filter Tahun Ajaran
        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->input('academic_year_id'));
        }

        // Filter Kategori Biaya
        if ($request->filled('fee_category_id')) {
            $query->where('fee_category_id', $request->input('fee_category_id'));
        }

        // Filter Status
        if ($request->filled('status')) {
            $status = strtolower($request->input('status'));
            if ($status === 'overdue') {
                $query->where('status', 'unpaid')->where('due_date', '<', Carbon::today());
            } elseif ($status === 'unpaid') {
                $query->where('status', 'unpaid');
            } elseif ($status === 'paid') {
                $query->where('status', 'paid');
            }
        }

        // Pencarian Nama Siswa, NIS, atau Judul Tagihan
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('full_name', 'like', "%{$search}%")
                            ->orWhere('nis', 'like', "%{$search}%")
                            ->orWhere('nisn', 'like', "%{$search}%");
                    });
            });
        }

        $bills = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $bills,
        ]);
    }

    /**
     * Ringkasan KPI Keuangan Siswa.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = StudentBill::query();

        if ($request->filled('unit_id')) {
            $unitId = $request->input('unit_id');
            $query->whereHas('student', function ($q) use ($unitId) {
                $q->where('unit_id', $unitId)
                    ->orWhereHas('kelas', fn ($kq) => $kq->where('unit_pendidikan_id', $unitId));
            });
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->input('academic_year_id'));
        }

        $totalBills = (clone $query)->count();
        $totalNominal = (clone $query)->sum('amount');
        $paidNominal = (clone $query)->where('status', 'paid')->sum('amount');
        $unpaidNominal = (clone $query)->where('status', 'unpaid')->sum('amount');
        $overdueCount = (clone $query)->where('status', 'unpaid')->where('due_date', '<', Carbon::today())->count();

        $collectionRate = $totalNominal > 0 ? round(($paidNominal / $totalNominal) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_bills' => $totalBills,
                'total_nominal' => (float) $totalNominal,
                'paid_nominal' => (float) $paidNominal,
                'unpaid_nominal' => (float) $unpaidNominal,
                'overdue_count' => $overdueCount,
                'collection_rate' => $collectionRate,
            ],
        ]);
    }

    /**
     * Membuat tagihan baru (mendukung single siswa atau bulk per rombel).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'fee_category_id' => 'required|uuid|exists:fee_categories,id',
            'academic_year_id' => 'nullable|uuid|exists:academic_years,id',
            'mode' => 'required|in:single,bulk_class',
            'student_id' => 'required_if:mode,single|nullable|uuid|exists:students,id',
            'class_id' => 'required_if:mode,bulk_class|nullable|uuid|exists:tbl_kelas,id',
        ]);

        $academicYearId = $validated['academic_year_id'] ?? AcademicYear::query()->where('is_active', true)->value('id');

        if ($validated['mode'] === 'single') {
            $bill = StudentBill::create([
                'student_id' => $validated['student_id'],
                'fee_category_id' => $validated['fee_category_id'],
                'academic_year_id' => $academicYearId,
                'title' => $validated['title'],
                'amount' => $validated['amount'],
                'due_date' => $validated['due_date'],
                'status' => 'unpaid',
                'metadata' => [
                    'created_by' => $request->user()->name ?? 'Petugas Keuangan',
                    'created_by_id' => $request->user()->id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tagihan siswa berhasil diterbitkan.',
                'data' => $bill,
            ], 201);
        }

        // Mode Bulk per Rombel / Kelas
        $students = Student::query()->where('kelas_id', $validated['class_id'])->get();
        if ($students->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada siswa terdaftar di kelas yang dipilih.',
            ], 422);
        }

        $createdCount = 0;
        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                StudentBill::create([
                    'student_id' => $student->id,
                    'fee_category_id' => $validated['fee_category_id'],
                    'academic_year_id' => $academicYearId,
                    'title' => $validated['title'],
                    'amount' => $validated['amount'],
                    'due_date' => $validated['due_date'],
                    'status' => 'unpaid',
                    'metadata' => [
                        'batch_class_id' => $validated['class_id'],
                        'created_by' => $request->user()->name ?? 'Petugas Keuangan',
                        'created_by_id' => $request->user()->id,
                    ],
                ]);
                $createdCount++;
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menerbitkan {$createdCount} tagihan untuk rombel kelas.",
                'data' => ['created_count' => $createdCount],
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menerbitkan tagihan massal: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Menampilkan rincian satu tagihan beserta histori pelunasan.
     */
    public function show(string $id): JsonResponse
    {
        $bill = StudentBill::query()
            ->with(['student.kelas.unitPendidikan', 'feeCategory', 'academicYear', 'payments'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $bill,
        ]);
    }

    /**
     * Memperbarui informasi tagihan.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $bill = StudentBill::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'due_date' => 'sometimes|date',
            'fee_category_id' => 'sometimes|uuid|exists:fee_categories,id',
            'status' => 'sometimes|in:paid,unpaid',
        ]);

        $bill->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tagihan siswa berhasil diperbarui.',
            'data' => $bill,
        ]);
    }

    /**
     * Menghapus tagihan (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $bill = StudentBill::findOrFail($id);
        $bill->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tagihan berhasil dihapus.',
        ]);
    }

    /**
     * Mencatat pembayaran kasir / transfer manual untuk sebuah tagihan.
     */
    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $bill = StudentBill::findOrFail($id);

        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:TUNAI_KASIR,TRANSFER_BSI,QRIS,TRANSFER_BANK',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $paidDate = $validated['paid_at'] ? Carbon::parse($validated['paid_at']) : Carbon::now();
        $invoiceNumber = 'INV/' . $paidDate->format('Ymd') . '/' . strtoupper(Str::random(6));

        $payment = BillPayment::create([
            'bill_id' => $bill->id,
            'invoice_number' => $invoiceNumber,
            'payment_method' => $validated['payment_method'],
            'paid_amount' => $validated['paid_amount'],
            'paid_at' => $paidDate,
            'metadata' => [
                'cashier_id' => $request->user()->id,
                'cashier_name' => $request->user()->name ?? 'Kasir Keuangan',
                'notes' => $validated['notes'] ?? null,
            ],
        ]);

        // Cek apakah total pembayaran sudah mencukupi tagihan
        $totalPaid = $bill->payments()->sum('paid_amount');
        if ($totalPaid >= (float) $bill->amount) {
            $bill->update(['status' => 'paid']);
        }

        return response()->json([
            'success' => true,
            'message' => "Pembayaran berhasil dicatat dengan nomor kuitansi {$invoiceNumber}.",
            'data' => [
                'payment' => $payment,
                'bill_status' => $bill->status,
                'total_paid' => $totalPaid,
            ],
        ]);
    }

    /**
     * Daftar Kategori Biaya untuk referensi dropdown.
     */
    public function feeCategories(): JsonResponse
    {
        $categories = FeeCategory::query()->orderBy('name')->get();
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
