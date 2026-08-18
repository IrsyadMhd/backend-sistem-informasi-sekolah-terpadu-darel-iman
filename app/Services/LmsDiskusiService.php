<?php

namespace App\Services;

use App\Models\LmsDiskusi;
use App\Models\LmsDiskusiKomentar;
use App\Models\LmsModulAjar;
use App\Repositories\Contracts\LmsDiskusiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsDiskusiService
{
    public function __construct(
        protected LmsDiskusiRepositoryInterface $diskusiRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->diskusiRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsDiskusi
    {
        return $this->diskusiRepository->findById($id);
    }

    public function dapatkanBerdasarkanModulAjar(string $modulAjarId): Collection
    {
        return $this->diskusiRepository->getByModulAjarId($modulAjarId);
    }

    public function simpan(array $data): LmsDiskusi
    {
        return $this->diskusiRepository->create($data);
    }

    public function ubah(string $id, array $data): ?LmsDiskusi
    {
        return $this->diskusiRepository->update($id, $data);
    }

    public function hapus(string $id): bool
    {
        return $this->diskusiRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        return $this->diskusiRepository->restore($id);
    }

    public function togglePin(string $id): ?LmsDiskusi
    {
        return $this->diskusiRepository->togglePin($id);
    }

    public function toggleClose(string $id): ?LmsDiskusi
    {
        return $this->diskusiRepository->toggleClose($id);
    }

    public function tambahKomentar(string $diskusiId, array $data): LmsDiskusiKomentar
    {
        return $this->diskusiRepository->addComment($diskusiId, $data);
    }

    public function hapusKomentar(string $komentarId): bool
    {
        return $this->diskusiRepository->deleteComment($komentarId);
    }

    public function dapatkanStatistik(): array
    {
        return $this->diskusiRepository->getStats();
    }

    public function dapatkanOpsiModulAjar(?string $guruId = null): array
    {
        return LmsModulAjar::query()
            ->select('id', 'judul_modul', 'kode_modul')
            ->when($guruId, fn ($query) => $query->where('guru_id', $guruId))
            ->orderBy('judul_modul', 'asc')
            ->get()
            ->map(fn ($item) => [
                'value' => $item->id,
                'label' => $item->kode_modul ? "[{$item->kode_modul}] {$item->judul_modul}" : $item->judul_modul,
                'judul' => $item->judul_modul,
                'judul_modul' => $item->judul_modul,
            ])
            ->toArray();
    }
}
