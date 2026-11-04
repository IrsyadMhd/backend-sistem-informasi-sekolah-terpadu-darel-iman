<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\KurasiSoal;
use App\Models\Subject;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KurasiSoalController extends Controller
{
    public function __construct(protected AccessScopeService $accessScopeService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = KurasiSoal::with(['educationUnit', 'subject', 'penyusunEmployee', 'kuratorUser']);

        // Scope unit check
        if ($user && ! $this->accessScopeService->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScopeService->accessibleEducationUnits($user)->pluck('id')->all();
            $query->whereIn('education_unit_id', $allowedUnitIds);
        }

        if ($request->filled('unit_id') && $request->unit_id !== 'all') {
            $query->where('education_unit_id', $request->unit_id);
        }

        if ($request->filled('klasifikasi') && $request->klasifikasi !== 'all') {
            $query->where('klasifikasi', $request->klasifikasi);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where(function ($q) use ($search, $like) {
                $q->where('judul', $like, "%{$search}%")
                  ->orWhere('kode_kurasi', $like, "%{$search}%")
                  ->orWhere('nama_mapel', $like, "%{$search}%")
                  ->orWhere('nama_penyusun', $like, "%{$search}%")
                  ->orWhereHas('subject', function ($sq) use ($search, $like) {
                      $sq->where('name', $like, "%{$search}%")
                         ->orWhere('kode_mapel', $like, "%{$search}%");
                  })
                  ->orWhereHas('penyusunEmployee', function ($eq) use ($search, $like) {
                      $eq->where('nama_lengkap', $like, "%{$search}%")
                         ->orWhere('niy', $like, "%{$search}%");
                  })
                  ->orWhereHas('educationUnit', function ($uq) use ($search, $like) {
                      $uq->where('name', $like, "%{$search}%");
                  });
            });
        }

        $items = $query->orderByDesc('created_at')->get();

        $formatted = $items->map(function ($item) {
            $mapelName = $item->nama_mapel ?: ($item->subject?->name ?? 'Mata Pelajaran');
            $penyusunName = $item->nama_penyusun ?: ($item->penyusunEmployee?->nama_lengkap ?? 'Tim Guru Pengajar');
            $unitName = $item->educationUnit?->name ?? 'Unit Pendidikan';

            return [
                'id' => $item->id,
                'kode_kurasi' => $item->kode_kurasi,
                'judul' => $item->judul,
                'education_unit_id' => $item->education_unit_id,
                'unit_asal' => $unitName,
                'unit_code' => $item->educationUnit?->code ?? '-',
                'subject_id' => $item->subject_id,
                'mapel' => $mapelName,
                'penyusun_employee_id' => $item->penyusun_employee_id,
                'penyusun' => $penyusunName,
                'jenjang' => $item->jenjang,
                'klasifikasi' => $item->klasifikasi,
                'tingkat_kesukaran' => $item->tingkat_kesukaran,
                'status' => $item->status,
                'tanggal_reviu' => $item->tanggal_reviu ? $item->tanggal_reviu->format('Y-m-d') : null,
                'kurator_user_id' => $item->kurator_user_id,
                'nama_kurator' => $item->nama_kurator ?: ($item->kuratorUser?->name ?? 'Divisi Pendidikan'),
                'catatan_kurator' => $item->catatan_kurator ?? '',
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
        $subjects = Subject::select('id', 'name', 'kode_mapel')->orderBy('name')->get();
        $teachers = Employee::whereNotNull('unit_id')
            ->select('id', 'nama_lengkap', 'niy', 'nik', 'unit_id')
            ->with(['unit:id,name,code'])
            ->orderBy('nama_lengkap')
            ->limit(200)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->nama_lengkap,
                    'niy' => $t->niy ?? $t->nik ?? '-',
                    'unit_id' => $t->unit_id,
                    'unit_name' => $t->unit?->name ?? '-',
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'units' => $units,
                'subjects' => $subjects,
                'teachers' => $teachers,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'education_unit_id' => 'required|uuid|exists:education_units,id',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'nama_mapel' => 'nullable|string|max:150',
            'penyusun_employee_id' => 'nullable|uuid|exists:employees,id',
            'nama_penyusun' => 'nullable|string|max:150',
            'jenjang' => 'required|string|max:50',
            'klasifikasi' => 'required|string|in:HOTS,MOTS,LOTS',
            'tingkat_kesukaran' => 'required|string|max:50',
            'status' => 'required|string|in:disetujui,perlu_revisi,menunggu_reviu',
            'catatan_kurator' => 'nullable|string',
            'tanggal_reviu' => 'nullable|date',
        ]);

        $user = $request->user();

        // Resolve subject name
        $namaMapel = $validated['nama_mapel'] ?? null;
        if (! $namaMapel && ! empty($validated['subject_id'])) {
            $subj = Subject::find($validated['subject_id']);
            $namaMapel = $subj?->name;
        }

        // Resolve teacher name
        $namaPenyusun = $validated['nama_penyusun'] ?? null;
        if (! $namaPenyusun && ! empty($validated['penyusun_employee_id'])) {
            $emp = Employee::find($validated['penyusun_employee_id']);
            $namaPenyusun = $emp?->nama_lengkap;
        }

        // Generate unique code
        $randomSeq = strtoupper(Str::random(4));
        $year = date('Y');
        $kodeKurasi = "KRS-{$year}-{$randomSeq}";

        $kurasi = KurasiSoal::create([
            'kode_kurasi' => $kodeKurasi,
            'judul' => $validated['judul'],
            'education_unit_id' => $validated['education_unit_id'],
            'subject_id' => $validated['subject_id'] ?? null,
            'penyusun_employee_id' => $validated['penyusun_employee_id'] ?? null,
            'nama_penyusun' => $namaPenyusun ?: 'Tim MGMP Pendidik',
            'nama_mapel' => $namaMapel ?: 'Mata Pelajaran',
            'jenjang' => $validated['jenjang'],
            'klasifikasi' => $validated['klasifikasi'],
            'tingkat_kesukaran' => $validated['tingkat_kesukaran'],
            'status' => $validated['status'],
            'tanggal_reviu' => $validated['tanggal_reviu'] ?? now()->toDateString(),
            'kurator_user_id' => $user?->id,
            'nama_kurator' => $user?->name ?? 'Divisi Pendidikan Yayasan',
            'catatan_kurator' => $validated['catatan_kurator'] ?? null,
        ]);

        $kurasi->load(['educationUnit', 'subject', 'penyusunEmployee', 'kuratorUser']);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket kurasi bank soal berhasil disimpan di database.',
            'data' => $kurasi,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $kurasi = KurasiSoal::with(['educationUnit', 'subject', 'penyusunEmployee', 'kuratorUser'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $kurasi,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $kurasi = KurasiSoal::findOrFail($id);

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'education_unit_id' => 'required|uuid|exists:education_units,id',
            'subject_id' => 'nullable|uuid|exists:subjects,id',
            'nama_mapel' => 'nullable|string|max:150',
            'penyusun_employee_id' => 'nullable|uuid|exists:employees,id',
            'nama_penyusun' => 'nullable|string|max:150',
            'jenjang' => 'required|string|max:50',
            'klasifikasi' => 'required|string|in:HOTS,MOTS,LOTS',
            'tingkat_kesukaran' => 'required|string|max:50',
            'status' => 'required|string|in:disetujui,perlu_revisi,menunggu_reviu',
            'catatan_kurator' => 'nullable|string',
            'tanggal_reviu' => 'nullable|date',
        ]);

        $user = $request->user();

        $namaMapel = $validated['nama_mapel'] ?? null;
        if (! $namaMapel && ! empty($validated['subject_id'])) {
            $subj = Subject::find($validated['subject_id']);
            $namaMapel = $subj?->name;
        }

        $namaPenyusun = $validated['nama_penyusun'] ?? null;
        if (! $namaPenyusun && ! empty($validated['penyusun_employee_id'])) {
            $emp = Employee::find($validated['penyusun_employee_id']);
            $namaPenyusun = $emp?->nama_lengkap;
        }

        $kurasi->update([
            'judul' => $validated['judul'],
            'education_unit_id' => $validated['education_unit_id'],
            'subject_id' => $validated['subject_id'] ?? $kurasi->subject_id,
            'penyusun_employee_id' => $validated['penyusun_employee_id'] ?? $kurasi->penyusun_employee_id,
            'nama_penyusun' => $namaPenyusun ?: $kurasi->nama_penyusun,
            'nama_mapel' => $namaMapel ?: $kurasi->nama_mapel,
            'jenjang' => $validated['jenjang'],
            'klasifikasi' => $validated['klasifikasi'],
            'tingkat_kesukaran' => $validated['tingkat_kesukaran'],
            'status' => $validated['status'],
            'tanggal_reviu' => $validated['tanggal_reviu'] ?? now()->toDateString(),
            'kurator_user_id' => $user?->id ?? $kurasi->kurator_user_id,
            'nama_kurator' => $user?->name ?? $kurasi->nama_kurator,
            'catatan_kurator' => $validated['catatan_kurator'] ?? $kurasi->catatan_kurator,
        ]);

        $kurasi->load(['educationUnit', 'subject', 'penyusunEmployee', 'kuratorUser']);

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil kurasi bank soal berhasil diperbarui.',
            'data' => $kurasi,
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $kurasi = KurasiSoal::findOrFail($id);
        $user = $request->user();

        $validated = $request->validate([
            'catatan_kurator' => 'nullable|string',
        ]);

        $catatan = $validated['catatan_kurator'] ?? 'Disetujui standar baku yayasan oleh Divisi Pendidikan.';

        $kurasi->update([
            'status' => 'disetujui',
            'tanggal_reviu' => now()->toDateString(),
            'kurator_user_id' => $user?->id,
            'nama_kurator' => $user?->name ?? 'Divisi Pendidikan Yayasan',
            'catatan_kurator' => $catatan,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Paket soal berhasil disetujui standar baku yayasan.',
            'data' => $kurasi,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $kurasi = KurasiSoal::findOrFail($id);
        $kurasi->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Paket kurasi bank soal berhasil dihapus dari database.',
        ]);
    }
}
