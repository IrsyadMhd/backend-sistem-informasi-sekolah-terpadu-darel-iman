<?php

namespace App\Services;

use App\Models\LmsModulAjar;
use App\Models\LmsReferensi;
use App\Repositories\Contracts\LmsReferensiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LmsReferensiService
{
    public function __construct(
        protected LmsReferensiRepositoryInterface $referensiRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->referensiRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsReferensi
    {
        return $this->referensiRepository->findById($id);
    }

    public function simpan(array $data, ?UploadedFile $file = null): LmsReferensi
    {
        if ($file) {
            $path = $file->store('referensi_files', 'public');
            $data['file'] = $path;
        }

        $referensi = $this->referensiRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Referensi Pembelajaran Baru', [
            'referensi_id' => $referensi->id,
            'modul_ajar_id' => $referensi->modul_ajar_id,
            'judul' => $referensi->judul,
            'penulis' => $referensi->penulis,
            'user_id' => auth()->id(),
        ]);

        return $referensi;
    }

    public function ubah(string $id, array $data, ?UploadedFile $file = null): ?LmsReferensi
    {
        $existing = $this->referensiRepository->findById($id);
        if (! $existing) {
            return null;
        }

        if ($file) {
            if ($existing->file && Storage::disk('public')->exists($existing->file)) {
                Storage::disk('public')->delete($existing->file);
            }
            $path = $file->store('referensi_files', 'public');
            $data['file'] = $path;
        }

        $updated = $this->referensiRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Referensi Pembelajaran', [
            'referensi_id' => $id,
            'judul_sebelum' => $existing->judul,
            'judul_sesudah' => $updated->judul ?? $existing->judul,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $referensi = $this->referensiRepository->findById($id);
        if (! $referensi) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus Referensi Pembelajaran', [
            'referensi_id' => $id,
            'judul' => $referensi->judul,
            'user_id' => auth()->id(),
        ]);

        return $this->referensiRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        return $this->referensiRepository->restore($id);
    }

    public function statistik(): array
    {
        return $this->referensiRepository->getStats();
    }

    public function opsi(): array
    {
        $modulAjarList = LmsModulAjar::select('id', 'judul_modul', 'kode_modul', 'mata_pelajaran_id')
            ->with(['subject:id,name,code'])
            ->orderBy('created_at', 'desc')
            ->get();

        $referensiUmumList = LmsReferensi::whereNull('modul_ajar_id')
            ->where('status', 'aktif')
            ->orderBy('judul', 'asc')
            ->get(['id', 'judul', 'penulis', 'penerbit', 'tahun', 'url', 'file']);

        return [
            'modul_ajar_options' => $modulAjarList,
            'referensi_umum_options' => $referensiUmumList,
            'status_options' => [
                ['id' => 'aktif', 'nama' => 'Aktif'],
                ['id' => 'non-aktif', 'nama' => 'Non-Aktif'],
            ],
        ];
    }
}
