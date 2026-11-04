<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MunaqasyahTahfizh;
use App\Models\Student;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MunaqasyahTahfizhController extends Controller
{
    public function __construct(protected AccessScopeService $accessScopeService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = MunaqasyahTahfizh::with(['student', 'educationUnit', 'pengujiEmployee', 'kuratorUser']);

        if ($user && ! $this->accessScopeService->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScopeService->accessibleEducationUnits($user)->pluck('id')->all();
            $query->whereIn('education_unit_id', $allowedUnitIds);
        }

        if ($request->filled('unit_id') && $request->unit_id !== 'all') {
            $query->where('education_unit_id', $request->unit_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('juz') && $request->juz !== 'all') {
            $juz = strtolower($request->juz);
            $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where('juz_diuji', $like, "%{$juz}%");
        }

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where(function ($q) use ($search, $like) {
                $q->where('nama_santri', $like, "%{$search}%")
                  ->orWhere('nis', $like, "%{$search}%")
                  ->orWhere('nomor_syahadah', $like, "%{$search}%")
                  ->orWhere('nomor_munaqasyah', $like, "%{$search}%")
                  ->orWhere('nama_penguji', $like, "%{$search}%")
                  ->orWhere('juz_diuji', $like, "%{$search}%")
                  ->orWhereHas('educationUnit', function ($uq) use ($search, $like) {
                      $uq->where('name', $like, "%{$search}%");
                  });
            });
        }

        $items = $query->orderByDesc('tanggal_ujian')->orderByDesc('created_at')->get();

        $formatted = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'nomor_munaqasyah' => $item->nomor_munaqasyah,
                'nomor_syahadah' => $item->nomor_syahadah ?: 'PENDING',
                'student_id' => $item->student_id,
                'nama_siswa' => $item->nama_santri ?: ($item->student?->nama_lengkap ?? 'Santri'),
                'nis' => $item->nis ?: ($item->student?->nisn ?? $item->student?->nis ?? '-'),
                'unit_id' => $item->education_unit_id,
                'unit_name' => $item->educationUnit?->name ?? 'Unit Pendidikan',
                'unit_code' => $item->educationUnit?->code ?? '-',
                'kelas' => $item->nama_kelas ?: '-',
                'juz_diuji' => $item->juz_diuji,
                'tanggal_ujian' => $item->tanggal_ujian ? $item->tanggal_ujian->format('Y-m-d') : null,
                'penguji_employee_id' => $item->penguji_employee_id,
                'penguji' => $item->nama_penguji ?: ($item->pengujiEmployee?->nama_lengkap ?? 'Dewan Penguji Tahfizh'),
                'nilai_tajwid' => (int) $item->nilai_tajwid,
                'nilai_fashahah' => (int) $item->nilai_fashahah,
                'nilai_kelancaran' => (int) $item->nilai_kelancaran,
                'nilai_akhir' => (int) $item->nilai_akhir,
                'predikat' => $item->predikat,
                'status' => $item->status,
                'catatan_dewan_penguji' => $item->catatan_dewan_penguji ?? '',
                'created_at' => $item->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    public function options(): JsonResponse
    {
        $units = EducationUnit::select('id', 'name', 'code')->orderBy('name')->get();

        $students = Student::select('id', 'full_name', 'nis', 'nisn', 'unit_id', 'kelas_id')
            ->with(['educationUnit:id,name,code', 'kelas:id,nama_kelas'])
            ->orderBy('full_name')
            ->limit(300)
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->full_name,
                    'nis' => $s->nis ?: ($s->nisn ?: '-'),
                    'unit_id' => $s->unit_id,
                    'unit_name' => $s->educationUnit?->name ?? '-',
                    'kelas' => $s->kelas?->nama_kelas ?? '-',
                ];
            });

        $examiners = Employee::whereNotNull('unit_id')
            ->select('id', 'nama_lengkap', 'niy', 'nik', 'unit_id')
            ->with(['unit:id,name,code'])
            ->orderBy('nama_lengkap')
            ->limit(150)
            ->get()
            ->map(function ($e) {
                return [
                    'id' => $e->id,
                    'name' => $e->nama_lengkap,
                    'niy' => $e->niy ?? $e->nik ?? '-',
                    'unit_id' => $e->unit_id,
                    'unit_name' => $e->unit?->name ?? '-',
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'units' => $units,
                'students' => $students,
                'examiners' => $examiners,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'nullable|uuid|exists:students,id',
            'nama_siswa' => 'required|string|max:150',
            'nis' => 'nullable|string|max:50',
            'education_unit_id' => 'required|uuid|exists:education_units,id',
            'kelas' => 'nullable|string|max:100',
            'juz_diuji' => 'required|string|max:100',
            'tanggal_ujian' => 'required|date',
            'penguji_employee_id' => 'nullable|uuid|exists:employees,id',
            'penguji' => 'nullable|string|max:150',
            'nilai_tajwid' => 'nullable|numeric|min:0|max:100',
            'nilai_fashahah' => 'nullable|numeric|min:0|max:100',
            'nilai_kelancaran' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|string|in:lulus,terjadwal,mengulang',
            'catatan_dewan_penguji' => 'nullable|string',
        ]);

        $user = $request->user();

        $tajwid = (int) ($validated['nilai_tajwid'] ?? 0);
        $fashahah = (int) ($validated['nilai_fashahah'] ?? 0);
        $kelancaran = (int) ($validated['nilai_kelancaran'] ?? 0);

        $nilaiAkhir = 0;
        $predikat = 'Menunggu Ujian';

        if ($validated['status'] === 'lulus') {
            $nilaiAkhir = (int) round(($tajwid + $fashahah + $kelancaran) / 3);
            if ($nilaiAkhir >= 92) $predikat = 'Mumtaz (Istimewa)';
            elseif ($nilaiAkhir >= 85) $predikat = 'Jayyid Jiddan (Sangat Baik)';
            else $predikat = 'Jayyid (Cukup/Baik)';
        } elseif ($validated['status'] === 'mengulang') {
            $nilaiAkhir = (int) round(($tajwid + $fashahah + $kelancaran) / 3);
            $predikat = 'Mengulang / Perlu Tahsin';
        }

        $randomSeq = strtoupper(Str::random(4));
        $nomorMunaqasyah = 'MNQ-' . date('Y') . '-' . $randomSeq;

        $nomorSyahadah = null;
        if ($validated['status'] === 'lulus') {
            $romanMonths = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'];
            $curMonth = $romanMonths[(int) date('n')] ?? 'XI';
            $curYear = date('Y');
            $nomorSyahadah = "SYH/DIF/{$curYear}/{$curMonth}/" . strtoupper(Str::random(5));
        }

        $munaqasyah = MunaqasyahTahfizh::create([
            'nomor_munaqasyah' => $nomorMunaqasyah,
            'nomor_syahadah' => $nomorSyahadah,
            'student_id' => $validated['student_id'] ?? null,
            'nama_santri' => $validated['nama_siswa'],
            'nis' => $validated['nis'] ?? null,
            'education_unit_id' => $validated['education_unit_id'],
            'nama_kelas' => $validated['kelas'] ?? null,
            'juz_diuji' => $validated['juz_diuji'],
            'tanggal_ujian' => $validated['tanggal_ujian'],
            'penguji_employee_id' => $validated['penguji_employee_id'] ?? null,
            'nama_penguji' => $validated['penguji'] ?? 'Dewan Penguji Tahfizh Yayasan',
            'nilai_tajwid' => $tajwid,
            'nilai_fashahah' => $fashahah,
            'nilai_kelancaran' => $kelancaran,
            'nilai_akhir' => $nilaiAkhir,
            'predikat' => $predikat,
            'status' => $validated['status'],
            'catatan_dewan_penguji' => $validated['catatan_dewan_penguji'] ?? null,
            'kurator_user_id' => $user?->id,
        ]);

        $munaqasyah->load(['student', 'educationUnit', 'pengujiEmployee', 'kuratorUser']);

        return response()->json([
            'status' => 'success',
            'message' => 'Data munaqasyah akbar tahfizh berhasil disimpan ke database.',
            'data' => $munaqasyah,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $munaqasyah = MunaqasyahTahfizh::with(['student', 'educationUnit', 'pengujiEmployee', 'kuratorUser'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $munaqasyah,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $munaqasyah = MunaqasyahTahfizh::findOrFail($id);

        $validated = $request->validate([
            'student_id' => 'nullable|uuid|exists:students,id',
            'nama_siswa' => 'required|string|max:150',
            'nis' => 'nullable|string|max:50',
            'education_unit_id' => 'required|uuid|exists:education_units,id',
            'kelas' => 'nullable|string|max:100',
            'juz_diuji' => 'required|string|max:100',
            'tanggal_ujian' => 'required|date',
            'penguji_employee_id' => 'nullable|uuid|exists:employees,id',
            'penguji' => 'nullable|string|max:150',
            'nilai_tajwid' => 'nullable|numeric|min:0|max:100',
            'nilai_fashahah' => 'nullable|numeric|min:0|max:100',
            'nilai_kelancaran' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|string|in:lulus,terjadwal,mengulang',
            'catatan_dewan_penguji' => 'nullable|string',
        ]);

        $tajwid = (int) ($validated['nilai_tajwid'] ?? 0);
        $fashahah = (int) ($validated['nilai_fashahah'] ?? 0);
        $kelancaran = (int) ($validated['nilai_kelancaran'] ?? 0);

        $nilaiAkhir = 0;
        $predikat = 'Menunggu Ujian';

        if ($validated['status'] === 'lulus') {
            $nilaiAkhir = (int) round(($tajwid + $fashahah + $kelancaran) / 3);
            if ($nilaiAkhir >= 92) $predikat = 'Mumtaz (Istimewa)';
            elseif ($nilaiAkhir >= 85) $predikat = 'Jayyid Jiddan (Sangat Baik)';
            else $predikat = 'Jayyid (Cukup/Baik)';
        } elseif ($validated['status'] === 'mengulang') {
            $nilaiAkhir = (int) round(($tajwid + $fashahah + $kelancaran) / 3);
            $predikat = 'Mengulang / Perlu Tahsin';
        }

        $nomorSyahadah = $munaqasyah->nomor_syahadah;
        if ($validated['status'] === 'lulus' && (empty($nomorSyahadah) || $nomorSyahadah === 'PENDING')) {
            $romanMonths = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'];
            $curMonth = $romanMonths[(int) date('n')] ?? 'XI';
            $curYear = date('Y');
            $nomorSyahadah = "SYH/DIF/{$curYear}/{$curMonth}/" . strtoupper(Str::random(5));
        }

        $munaqasyah->update([
            'nomor_syahadah' => $nomorSyahadah,
            'student_id' => $validated['student_id'] ?? $munaqasyah->student_id,
            'nama_santri' => $validated['nama_siswa'],
            'nis' => $validated['nis'] ?? $munaqasyah->nis,
            'education_unit_id' => $validated['education_unit_id'],
            'nama_kelas' => $validated['kelas'] ?? $munaqasyah->nama_kelas,
            'juz_diuji' => $validated['juz_diuji'],
            'tanggal_ujian' => $validated['tanggal_ujian'],
            'penguji_employee_id' => $validated['penguji_employee_id'] ?? $munaqasyah->penguji_employee_id,
            'nama_penguji' => $validated['penguji'] ?? $munaqasyah->nama_penguji,
            'nilai_tajwid' => $tajwid,
            'nilai_fashahah' => $fashahah,
            'nilai_kelancaran' => $kelancaran,
            'nilai_akhir' => $nilaiAkhir,
            'predikat' => $predikat,
            'status' => $validated['status'],
            'catatan_dewan_penguji' => $validated['catatan_dewan_penguji'] ?? $munaqasyah->catatan_dewan_penguji,
        ]);

        $munaqasyah->load(['student', 'educationUnit', 'pengujiEmployee', 'kuratorUser']);

        return response()->json([
            'status' => 'success',
            'message' => 'Data munaqasyah berhasil diperbarui.',
            'data' => $munaqasyah,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $munaqasyah = MunaqasyahTahfizh::findOrFail($id);
        $munaqasyah->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data munaqasyah berhasil dihapus.',
        ]);
    }
}
