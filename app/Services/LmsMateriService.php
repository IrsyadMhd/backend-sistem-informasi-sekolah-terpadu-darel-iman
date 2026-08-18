<?php

namespace App\Services;

use App\Models\LmsMateri;
use App\Models\LmsModulAjar;
use App\Repositories\Contracts\LmsMateriRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LmsMateriService
{
    public function __construct(
        protected LmsMateriRepositoryInterface $materiRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        return $this->materiRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?LmsMateri
    {
        return $this->materiRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data, ?UploadedFile $file = null): LmsMateri
    {
        if ($file) {
            $path = $file->store('materi_files', 'public');
            $data['file'] = $path;
        }

        if (empty($data['tipe'])) {
            $data['tipe'] = 'teks';
        }

        if (empty($data['status'])) {
            $data['status'] = 'aktif';
        }

        if (! isset($data['urutan']) || $data['urutan'] === null) {
            $maxUrutan = LmsMateri::where('modul_ajar_id', $data['modul_ajar_id'])->max('urutan') ?? 0;
            $data['urutan'] = $maxUrutan + 1;
        }

        // Set inherited metadata from Modul Ajar if available
        $modul = LmsModulAjar::find($data['modul_ajar_id']);
        if ($modul) {
            if (empty($data['mata_pelajaran_id'])) {
                $data['mata_pelajaran_id'] = $modul->mata_pelajaran_id;
            }
            if (empty($data['guru_id'])) {
                $data['guru_id'] = $modul->guru_id;
            }
        }

        $materi = $this->materiRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Materi Pembelajaran Baru', [
            'materi_id' => $materi->id,
            'modul_ajar_id' => $materi->modul_ajar_id,
            'judul' => $materi->judul,
            'tipe' => $materi->tipe,
            'user_id' => auth()->id(),
        ]);

        return $materi;
    }

    public function ubah(string $id, array $data, ?UploadedFile $file = null): ?LmsMateri
    {
        $existing = $this->materiRepository->findById($id);
        if (! $existing) {
            return null;
        }

        if ($file) {
            if ($existing->file && Storage::disk('public')->exists($existing->file)) {
                Storage::disk('public')->delete($existing->file);
            }
            $path = $file->store('materi_files', 'public');
            $data['file'] = $path;
        }

        $updated = $this->materiRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Materi Pembelajaran', [
            'materi_id' => $id,
            'judul_sebelum' => $existing->judul,
            'judul_sesudah' => $updated->judul ?? $existing->judul,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $materi = $this->materiRepository->findById($id);
        if (! $materi) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus (Soft Delete) Materi Pembelajaran', [
            'materi_id' => $id,
            'judul' => $materi->judul,
            'user_id' => auth()->id(),
        ]);

        return $this->materiRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Materi Pembelajaran', [
            'materi_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->materiRepository->restore($id);
    }

    public function statistik(): array
    {
        return $this->materiRepository->getStats();
    }

    public function opsi(): array
    {
        $modulAjars = LmsModulAjar::with('subject')
            ->select('id', 'kode_modul', 'judul_modul', 'mata_pelajaran_id', 'fase')
            ->get();

        return [
            'modul_ajar' => $modulAjars,
            'tipe_options' => [
                ['id' => 'teks', 'nama' => 'Teks / Ringkasan'],
                ['id' => 'dokumen', 'nama' => 'Dokumen / PDF / Office'],
                ['id' => 'video', 'nama' => 'Video Pembelajaran'],
                ['id' => 'link', 'nama' => 'Tautan / Link Eksternal'],
                ['id' => 'presentasi', 'nama' => 'Slide Presentasi'],
            ],
            'status_options' => [
                ['id' => 'aktif', 'nama' => 'Aktif'],
                ['id' => 'draft', 'nama' => 'Draft'],
                ['id' => 'nonaktif', 'nama' => 'Nonaktif'],
            ],
        ];
    }
}
