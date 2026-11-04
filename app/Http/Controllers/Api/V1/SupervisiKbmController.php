<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SupervisiKbm;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupervisiKbmController extends Controller
{
    public function __construct(protected AccessScopeService $accessScopeService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SupervisiKbm::with(['guru.unit', 'guru.position', 'unit']);

        // Filter unit scope
        if ($user && ! $this->accessScopeService->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScopeService->accessibleEducationUnits($user)->pluck('id')->all();
            $query->whereIn('unit_id', $allowedUnitIds);
        }

        if ($request->filled('unit_id') && $request->unit_id !== 'all') {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('guru_id')) {
            $query->where('guru_id', $request->guru_id);
        }

        if ($request->filled('search')) {
            $search = strtolower(trim($request->search));
            $like = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where(function ($q) use ($search, $like) {
                $q->where('mapel', $like, "%{$search}%")
                  ->orWhere('kelas', $like, "%{$search}%")
                  ->orWhere('supervisor', $like, "%{$search}%")
                  ->orWhereHas('guru', function ($gq) use ($search, $like) {
                      $gq->where('nama_lengkap', $like, "%{$search}%")
                         ->orWhere('niy', $like, "%{$search}%")
                         ->orWhere('nip', $like, "%{$search}%");
                  });
            });
        }

        $items = $query->orderByDesc('tanggal')->orderByDesc('created_at')->get();

        $formatted = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'guru_id' => $item->guru_id,
                'guru_name' => $item->guru?->nama_lengkap ?? 'Guru Pendidik',
                'niy' => $item->guru?->niy ?? $item->guru?->nip ?? '-',
                'unit_id' => $item->unit_id,
                'unit_name' => $item->unit?->name ?? $item->guru?->unit?->name ?? 'Unit Pendidikan',
                'mapel' => $item->mapel,
                'kelas' => $item->kelas ?? '-',
                'tanggal' => $item->tanggal ? $item->tanggal->format('Y-m-d') : null,
                'supervisor' => $item->supervisor ?? 'Divisi Pendidikan',
                'skor_total' => (int) $item->skor_total,
                'status' => $item->status,
                'rubrik' => $item->rubrik ?? [
                    'pendahuluan' => 0,
                    'penguasaan_materi' => 0,
                    'pengelolaan_kelas' => 0,
                    'integrasi_islam' => 0,
                    'media_pembelajaran' => 0,
                    'penutup_asesmen' => 0,
                ],
                'apresiasi' => $item->apresiasi ?? '',
                'rekomendasi' => $item->rekomendasi ?? '',
                'created_at' => $item->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guru_id' => 'required|uuid|exists:employees,id',
            'unit_id' => 'required|uuid|exists:education_units,id',
            'mapel' => 'required|string|max:150',
            'kelas' => 'nullable|string|max:100',
            'tanggal' => 'required|date',
            'supervisor' => 'nullable|string|max:150',
            'status' => 'required|string|in:selesai,terjadwal,pendampingan',
            'rubrik' => 'nullable|array',
            'apresiasi' => 'nullable|string',
            'rekomendasi' => 'nullable|string',
        ]);

        $rubrik = $validated['rubrik'] ?? [];
        $skorTotal = 0;
        if (! empty($rubrik) && $validated['status'] === 'selesai') {
            $sum = array_sum(array_map('intval', $rubrik));
            $skorTotal = (int) round(($sum / 24) * 100);
        }

        $supervisi = SupervisiKbm::create([
            'guru_id' => $validated['guru_id'],
            'unit_id' => $validated['unit_id'],
            'mapel' => $validated['mapel'],
            'kelas' => $validated['kelas'] ?? '-',
            'tanggal' => $validated['tanggal'],
            'supervisor' => $validated['supervisor'] ?? 'Divisi Pendidikan',
            'status' => $validated['status'],
            'skor_total' => $skorTotal,
            'rubrik' => $rubrik,
            'apresiasi' => $validated['apresiasi'] ?? null,
            'rekomendasi' => $validated['rekomendasi'] ?? null,
            'id_penginput' => $request->user()?->id,
        ]);

        $supervisi->load(['guru.unit', 'unit']);

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan supervisi KBM berhasil disimpan.',
            'data' => [
                'id' => $supervisi->id,
                'guru_id' => $supervisi->guru_id,
                'guru_name' => $supervisi->guru?->nama_lengkap ?? 'Guru Pendidik',
                'niy' => $supervisi->guru?->niy ?? $supervisi->guru?->nip ?? '-',
                'unit_id' => $supervisi->unit_id,
                'unit_name' => $supervisi->unit?->name ?? 'Unit Pendidikan',
                'mapel' => $supervisi->mapel,
                'kelas' => $supervisi->kelas,
                'tanggal' => $supervisi->tanggal ? $supervisi->tanggal->format('Y-m-d') : null,
                'supervisor' => $supervisi->supervisor,
                'skor_total' => $supervisi->skor_total,
                'status' => $supervisi->status,
                'rubrik' => $supervisi->rubrik,
                'apresiasi' => $supervisi->apresiasi,
                'rekomendasi' => $supervisi->rekomendasi,
            ],
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $supervisi = SupervisiKbm::with(['guru.unit', 'unit'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $supervisi->id,
                'guru_id' => $supervisi->guru_id,
                'guru_name' => $supervisi->guru?->nama_lengkap ?? 'Guru Pendidik',
                'niy' => $supervisi->guru?->niy ?? $supervisi->guru?->nip ?? '-',
                'unit_id' => $supervisi->unit_id,
                'unit_name' => $supervisi->unit?->name ?? 'Unit Pendidikan',
                'mapel' => $supervisi->mapel,
                'kelas' => $supervisi->kelas,
                'tanggal' => $supervisi->tanggal ? $supervisi->tanggal->format('Y-m-d') : null,
                'supervisor' => $supervisi->supervisor,
                'skor_total' => $supervisi->skor_total,
                'status' => $supervisi->status,
                'rubrik' => $supervisi->rubrik,
                'apresiasi' => $supervisi->apresiasi,
                'rekomendasi' => $supervisi->rekomendasi,
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $supervisi = SupervisiKbm::findOrFail($id);

        $validated = $request->validate([
            'guru_id' => 'sometimes|required|uuid|exists:employees,id',
            'unit_id' => 'sometimes|required|uuid|exists:education_units,id',
            'mapel' => 'sometimes|required|string|max:150',
            'kelas' => 'nullable|string|max:100',
            'tanggal' => 'sometimes|required|date',
            'supervisor' => 'nullable|string|max:150',
            'status' => 'sometimes|required|string|in:selesai,terjadwal,pendampingan',
            'rubrik' => 'nullable|array',
            'apresiasi' => 'nullable|string',
            'rekomendasi' => 'nullable|string',
        ]);

        if (isset($validated['rubrik'])) {
            $status = $validated['status'] ?? $supervisi->status;
            if ($status === 'selesai') {
                $sum = array_sum(array_map('intval', $validated['rubrik']));
                $validated['skor_total'] = (int) round(($sum / 24) * 100);
            } else {
                $validated['skor_total'] = 0;
            }
        }

        $supervisi->update($validated);
        $supervisi->load(['guru.unit', 'unit']);

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan supervisi KBM berhasil diperbarui.',
            'data' => [
                'id' => $supervisi->id,
                'guru_id' => $supervisi->guru_id,
                'guru_name' => $supervisi->guru?->nama_lengkap ?? 'Guru Pendidik',
                'niy' => $supervisi->guru?->niy ?? $supervisi->guru?->nip ?? '-',
                'unit_id' => $supervisi->unit_id,
                'unit_name' => $supervisi->unit?->name ?? 'Unit Pendidikan',
                'mapel' => $supervisi->mapel,
                'kelas' => $supervisi->kelas,
                'tanggal' => $supervisi->tanggal ? $supervisi->tanggal->format('Y-m-d') : null,
                'supervisor' => $supervisi->supervisor,
                'skor_total' => $supervisi->skor_total,
                'status' => $supervisi->status,
                'rubrik' => $supervisi->rubrik,
                'apresiasi' => $supervisi->apresiasi,
                'rekomendasi' => $supervisi->rekomendasi,
            ],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $supervisi = SupervisiKbm::findOrFail($id);
        $supervisi->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan supervisi KBM berhasil dihapus.',
        ]);
    }
}
