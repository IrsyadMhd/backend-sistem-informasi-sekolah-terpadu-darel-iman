<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\PelatihanGuru;
use App\Models\PelatihanGuruPeserta;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PelatihanGuruController extends Controller
{
    public function __construct(protected AccessScopeService $accessScopeService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PelatihanGuru::with(['targetUnit', 'penanggungJawab'])
            ->withCount('peserta');

        if ($user && ! $this->accessScopeService->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScopeService->accessibleEducationUnits($user)->pluck('id')->all();
            $query->where(function ($q) use ($allowedUnitIds) {
                $q->whereNull('target_unit_id')
                  ->orWhereIn('target_unit_id', $allowedUnitIds);
            });
        }

        if ($request->filled('target_unit_id') && $request->target_unit_id !== 'all') {
            $query->where('target_unit_id', $request->target_unit_id);
        }

        if ($request->filled('kategori') && $request->kategori !== 'all') {
            $query->where('kategori', $request->kategori);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where(function ($q) use ($search, $like) {
                $q->where('nama', $like, "%{$search}%")
                  ->orWhere('kode_pelatihan', $like, "%{$search}%")
                  ->orWhere('narasumber', $like, "%{$search}%")
                  ->orWhere('lokasi', $like, "%{$search}%")
                  ->orWhere('kategori', $like, "%{$search}%");
            });
        }

        $items = $query->orderByDesc('tanggal_mulai')->orderByDesc('created_at')->get();

        $formatted = $items->map(function ($item) {
            $startDate = $item->tanggal_mulai ? $item->tanggal_mulai->format('d M Y') : '-';
            $endDate = $item->tanggal_selesai ? $item->tanggal_selesai->format('d M Y') : null;
            $displayDate = $endDate && $endDate !== $startDate ? "{$startDate} - {$endDate}" : $startDate;

            $participantCount = $item->peserta_count > 0 ? $item->peserta_count : (int) $item->jumlah_terdaftar;

            return [
                'id' => $item->id,
                'kode_pelatihan' => $item->kode_pelatihan,
                'nama' => $item->nama,
                'kategori' => $item->kategori,
                'narasumber' => $item->narasumber ?: 'Tim Instruktur Yayasan',
                'jam_pelatihan' => (int) $item->jam_pelatihan,
                'tanggal_mulai' => $item->tanggal_mulai ? $item->tanggal_mulai->format('Y-m-d') : null,
                'tanggal_selesai' => $item->tanggal_selesai ? $item->tanggal_selesai->format('Y-m-d') : null,
                'tanggal' => $displayDate,
                'lokasi' => $item->lokasi,
                'kuota' => (int) $item->kuota,
                'terdaftar' => $participantCount,
                'status' => $item->status,
                'deskripsi' => $item->deskripsi ?? '',
                'penyelenggara' => $item->penyelenggara ?? 'Divisi Pendidikan',
                'target_unit_id' => $item->target_unit_id,
                'target_unit_name' => $item->targetUnit?->name ?? 'Seluruh Unit Sekolah',
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

        $categories = [
            'Pedagogik & Asesmen',
            'Kepribadian & Konseling BK',
            'Bahasa & Keislaman',
            'Teknologi & Media Belajar',
            'Kepemimpinan & Manajemen Sekolah',
        ];

        $teachers = Employee::whereNotNull('unit_id')
            ->select('id', 'nama_lengkap', 'niy', 'nik', 'unit_id')
            ->with(['unit:id,name,code'])
            ->orderBy('nama_lengkap')
            ->limit(200)
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
                'categories' => $categories,
                'teachers' => $teachers,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'required|string|max:100',
            'narasumber' => 'nullable|string|max:150',
            'jam_pelatihan' => 'required|numeric|min:1|max:200',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'lokasi' => 'required|string|max:150',
            'kuota' => 'required|numeric|min:1',
            'status' => 'required|string|in:akan_datang,berjalan,selesai,dibatalkan',
            'deskripsi' => 'nullable|string',
            'target_unit_id' => 'nullable|uuid|exists:education_units,id',
        ]);

        $user = $request->user();

        $randomSeq = strtoupper(Str::random(4));
        $kodePelatihan = 'TRN-' . date('Y') . '-' . $randomSeq;

        $pelatihan = PelatihanGuru::create([
            'kode_pelatihan' => $kodePelatihan,
            'nama' => $validated['nama'],
            'kategori' => $validated['kategori'],
            'narasumber' => $validated['narasumber'] ?? 'Tim Instruktur Yayasan',
            'jam_pelatihan' => (int) $validated['jam_pelatihan'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'] ?? null,
            'lokasi' => $validated['lokasi'],
            'kuota' => (int) $validated['kuota'],
            'jumlah_terdaftar' => 0,
            'status' => $validated['status'],
            'deskripsi' => $validated['deskripsi'] ?? null,
            'target_unit_id' => $validated['target_unit_id'] ?? null,
            'penanggung_jawab_user_id' => $user?->id,
        ]);

        $pelatihan->load('targetUnit');

        return response()->json([
            'status' => 'success',
            'message' => 'Program workshop pelatihan guru berhasil dibuat.',
            'data' => $pelatihan,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $pelatihan = PelatihanGuru::with(['targetUnit', 'penanggungJawab', 'peserta.employee.unit'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $pelatihan,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $pelatihan = PelatihanGuru::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'required|string|max:100',
            'narasumber' => 'nullable|string|max:150',
            'jam_pelatihan' => 'required|numeric|min:1|max:200',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'lokasi' => 'required|string|max:150',
            'kuota' => 'required|numeric|min:1',
            'status' => 'required|string|in:akan_datang,berjalan,selesai,dibatalkan',
            'deskripsi' => 'nullable|string',
            'target_unit_id' => 'nullable|uuid|exists:education_units,id',
        ]);

        $pelatihan->update([
            'nama' => $validated['nama'],
            'kategori' => $validated['kategori'],
            'narasumber' => $validated['narasumber'] ?? $pelatihan->narasumber,
            'jam_pelatihan' => (int) $validated['jam_pelatihan'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'] ?? null,
            'lokasi' => $validated['lokasi'],
            'kuota' => (int) $validated['kuota'],
            'status' => $validated['status'],
            'deskripsi' => $validated['deskripsi'] ?? $pelatihan->deskripsi,
            'target_unit_id' => $validated['target_unit_id'] ?? null,
        ]);

        $pelatihan->load('targetUnit');

        return response()->json([
            'status' => 'success',
            'message' => 'Data program pelatihan berhasil diperbarui.',
            'data' => $pelatihan,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $pelatihan = PelatihanGuru::findOrFail($id);
        $pelatihan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Program pelatihan berhasil dihapus.',
        ]);
    }
}
