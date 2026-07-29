<?php

namespace App\Services;

use App\Models\LmsModulAjar;
use App\Repositories\Contracts\LmsModulAjarRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LmsModulAjarService
{
    public function __construct(
        protected LmsModulAjarRepositoryInterface $modulAjarRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->modulAjarRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsModulAjar
    {
        return $this->modulAjarRepository->findById($id);
    }

    public function simpan(array $data): LmsModulAjar
    {
        if (empty($data['kode_modul'])) {
            $data['kode_modul'] = 'MA-' . strtoupper(Str::random(6));
        }

        if (empty($data['status'])) {
            $data['status'] = 'Draft';
        }

        if (empty($data['versi'])) {
            $data['versi'] = '1.0';
        }

        Log::info('Membuat Modul Ajar baru', ['kode_modul' => $data['kode_modul'], 'judul' => $data['judul_modul']]);

        return $this->modulAjarRepository->create($data);
    }

    public function ubah(string $id, array $data): ?LmsModulAjar
    {
        $existing = $this->modulAjarRepository->findById($id);
        if (!$existing) {
            return null;
        }

        // Handle Auto Version Increment if version parameter increment requested
        if (!empty($data['naikkan_versi']) && $data['naikkan_versi']) {
            $currentVer = (float) ($existing->versi ?? '1.0');
            $data['versi'] = number_format($currentVer + 0.1, 1, '.', '');
        }

        Log::info('Memperbarui Modul Ajar', ['id' => $id, 'versi' => $data['versi'] ?? $existing->versi]);

        return $this->modulAjarRepository->update($id, $data);
    }

    public function hapus(string $id): bool
    {
        Log::info('Menghapus (soft delete) Modul Ajar', ['id' => $id]);
        return $this->modulAjarRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('Memulihkan Modul Ajar', ['id' => $id]);
        return $this->modulAjarRepository->restore($id);
    }

    public function publikasikan(string $id): ?LmsModulAjar
    {
        Log::info('Mempublikasikan Modul Ajar', ['id' => $id]);
        return $this->modulAjarRepository->update($id, [
            'status' => 'Publish',
            'catatan_revisi' => 'Publikasi Modul Ajar'
        ]);
    }

    public function duplikasi(string $id): ?LmsModulAjar
    {
        $existing = $this->modulAjarRepository->findById($id);
        if (!$existing) {
            return null;
        }

        $newData = $existing->toArray();
        unset(
            $newData['id'],
            $newData['created_at'],
            $newData['updated_at'],
            $newData['deleted_at'],
            $newData['created_by'],
            $newData['updated_by'],
            $newData['deleted_by'],
            $newData['revisions'],
            $newData['materi'],
            $newData['penugasan'],
            $newData['kisi_kisi']
        );

        $newData['judul_modul'] = $existing->judul_modul . ' (Salinan)';
        $newData['kode_modul'] = 'MA-' . strtoupper(Str::random(6));
        $newData['status'] = 'Draft';
        $newData['versi'] = '1.0';

        if ($existing->cps && count($existing->cps) > 0) {
            $newData['cp_ids'] = $existing->cps->pluck('id')->toArray();
        }
        if ($existing->tps && count($existing->tps) > 0) {
            $newData['tp_ids'] = $existing->tps->pluck('id')->toArray();
        }

        Log::info('Menduplikasi Modul Ajar', ['original_id' => $id, 'new_title' => $newData['judul_modul']]);

        return $this->modulAjarRepository->create($newData);
    }

    public function dapatkanStatistik(): array
    {
        return $this->modulAjarRepository->getStats();
    }
}
