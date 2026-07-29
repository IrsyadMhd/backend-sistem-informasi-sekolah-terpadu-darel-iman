<?php

namespace App\Services;

use App\Models\CapaianPembelajaran;
use App\Repositories\Contracts\CapaianPembelajaranRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CapaianPembelajaranService
{
    public function __construct(
        protected CapaianPembelajaranRepositoryInterface $cpRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        return $this->cpRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function dapatkanDropdown(array $filters = []): Collection
    {
        return $this->cpRepository->getDropdownOptions($filters);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?CapaianPembelajaran
    {
        return $this->cpRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data): CapaianPembelajaran
    {
        if (!isset($data['status'])) {
            $data['status'] = true;
        }

        if (empty($data['urutan'])) {
            $maxUrutan = CapaianPembelajaran::where('mata_pelajaran_id', $data['mata_pelajaran_id'] ?? null)->max('urutan') ?? 0;
            $data['urutan'] = $maxUrutan + 1;
        }

        Log::info('[AUDIT LOG] Membuat Capaian Pembelajaran Baru', [
            'kode_cp' => $data['kode_cp'] ?? null,
            'nama_cp' => $data['nama_cp'] ?? null,
            'user_id' => auth()->id(),
        ]);

        return $this->cpRepository->create($data);
    }

    public function ubah(string $id, array $data): ?CapaianPembelajaran
    {
        $existing = $this->cpRepository->findById($id);
        if (!$existing) {
            return null;
        }

        Log::info('[AUDIT LOG] Memperbarui Capaian Pembelajaran', [
            'id' => $id,
            'kode_cp' => $existing->kode_cp,
            'user_id' => auth()->id(),
        ]);

        return $this->cpRepository->update($id, $data);
    }

    public function hapus(string $id): bool
    {
        Log::info('[AUDIT LOG] Menghapus (Soft Delete) Capaian Pembelajaran', [
            'id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->cpRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Capaian Pembelajaran', [
            'id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->cpRepository->restore($id);
    }

    public function statistik(): array
    {
        return [
            'total_cp' => CapaianPembelajaran::count(),
            'total_cp_aktif' => CapaianPembelajaran::where('status', true)->count(),
            'total_cp_nonaktif' => CapaianPembelajaran::where('status', false)->count(),
        ];
    }
}
