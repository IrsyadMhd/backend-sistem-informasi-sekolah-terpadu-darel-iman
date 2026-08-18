<?php

namespace App\Services;

use App\Models\LmsAktivitasBelajar;
use App\Models\LmsModulAjar;
use App\Repositories\Contracts\LmsAktivitasBelajarRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class LmsAktivitasBelajarService
{
    public function __construct(
        protected LmsAktivitasBelajarRepositoryInterface $aktivitasRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        return $this->aktivitasRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsAktivitasBelajar
    {
        return $this->aktivitasRepository->findById($id);
    }

    public function simpan(array $data): LmsAktivitasBelajar
    {
        $aktivitas = $this->aktivitasRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Aktivitas Belajar Baru', [
            'aktivitas_id' => $aktivitas->id,
            'modul_ajar_id' => $aktivitas->modul_ajar_id,
            'nama_aktivitas' => $aktivitas->nama_aktivitas,
            'jenis_aktivitas' => $aktivitas->jenis_aktivitas,
            'waktu' => $aktivitas->waktu,
            'user_id' => auth()->id(),
        ]);

        return $aktivitas;
    }

    public function ubah(string $id, array $data): ?LmsAktivitasBelajar
    {
        $existing = $this->aktivitasRepository->findById($id);
        if (! $existing) {
            return null;
        }

        $updated = $this->aktivitasRepository->update($id, $data);

        Log::info('[AUDIT LOG] Mengubah Aktivitas Belajar', [
            'aktivitas_id' => $id,
            'modul_ajar_id' => $updated->modul_ajar_id,
            'nama_aktivitas' => $updated->nama_aktivitas,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $existing = $this->aktivitasRepository->findById($id);
        if (! $existing) {
            return false;
        }

        $result = $this->aktivitasRepository->delete($id);

        Log::warning('[AUDIT LOG] Menghapus Aktivitas Belajar (Soft Delete)', [
            'aktivitas_id' => $id,
            'nama_aktivitas' => $existing->nama_aktivitas,
            'user_id' => auth()->id(),
        ]);

        return $result;
    }

    public function pulihkan(string $id): bool
    {
        return $this->aktivitasRepository->restore($id);
    }

    public function dapatkanStatistik(): array
    {
        return $this->aktivitasRepository->getStats();
    }

    public function dapatkanOpsiModulAjar(?string $guruId = null): array
    {
        return LmsModulAjar::query()
            ->select('id', 'judul_modul', 'kode_modul', 'fase')
            ->when($guruId, fn ($query) => $query->where('guru_id', $guruId))
            ->orderBy('judul_modul', 'asc')
            ->get()
            ->toArray();
    }
}
