<?php

namespace App\Services;

use App\Models\LmsMateri;
use App\Models\LmsMedia;
use App\Repositories\Contracts\LmsMediaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LmsMediaService
{
    public function __construct(
        protected LmsMediaRepositoryInterface $mediaRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        return $this->mediaRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsMedia
    {
        return $this->mediaRepository->findById($id);
    }

    public function simpan(array $data, ?UploadedFile $file = null): LmsMedia
    {
        if ($file) {
            $path = $file->store('media_files', 'public');
            $data['path_file'] = $path;
            if (empty($data['ukuran_bytes'])) {
                $data['ukuran_bytes'] = $file->getSize();
            }
        }

        if (empty($data['urutan'])) {
            $maxUrutan = LmsMedia::where('materi_id', $data['materi_id'])->max('urutan') ?? 0;
            $data['urutan'] = $maxUrutan + 1;
        }

        $media = $this->mediaRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Media Pembelajaran Baru', [
            'media_id' => $media->id,
            'materi_id' => $media->materi_id,
            'nama_file' => $media->nama_file,
            'tipe_file' => $media->tipe_file,
            'user_id' => auth()->id(),
        ]);

        return $media;
    }

    public function ubah(string $id, array $data, ?UploadedFile $file = null): ?LmsMedia
    {
        $existing = $this->mediaRepository->findById($id);
        if (! $existing) {
            return null;
        }

        if ($file) {
            if ($existing->path_file && Storage::disk('public')->exists($existing->path_file)) {
                Storage::disk('public')->delete($existing->path_file);
            }
            $path = $file->store('media_files', 'public');
            $data['path_file'] = $path;
            if (empty($data['ukuran_bytes'])) {
                $data['ukuran_bytes'] = $file->getSize();
            }
        }

        $updated = $this->mediaRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Media Pembelajaran', [
            'media_id' => $id,
            'nama_sebelum' => $existing->nama_file,
            'nama_sesudah' => $updated->nama_file ?? $existing->nama_file,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $media = $this->mediaRepository->findById($id);
        if (! $media) {
            return false;
        }

        if ($media->path_file && Storage::disk('public')->exists($media->path_file)) {
            Storage::disk('public')->delete($media->path_file);
        }

        Log::info('[AUDIT LOG] Menghapus Media Pembelajaran', [
            'media_id' => $id,
            'nama_file' => $media->nama_file,
            'user_id' => auth()->id(),
        ]);

        return $this->mediaRepository->delete($id);
    }

    public function reorder(array $orders): bool
    {
        return $this->mediaRepository->reorder($orders);
    }

    public function statistik(): array
    {
        return $this->mediaRepository->getStats();
    }

    public function opsi(): array
    {
        $materis = LmsMateri::select('id', 'judul', 'modul_ajar_id')
            ->with(['modulAjar:id,judul_modul,kode_modul'])
            ->get();

        return [
            'materi_options' => $materis,
            'tipe_options' => [
                ['id' => 'pdf', 'nama' => 'Dokumen PDF'],
                ['id' => 'video', 'nama' => 'Video Pembelajaran (MP4/WebM/YouTube)'],
                ['id' => 'audio', 'nama' => 'Rekaman Audio (MP3/WAV)'],
                ['id' => 'ppt', 'nama' => 'Slide PPT / Presentation'],
                ['id' => 'word', 'nama' => 'Dokumen Word (DOCX)'],
                ['id' => 'image', 'nama' => 'Gambar / Infografis (PNG/JPG)'],
                ['id' => 'link', 'nama' => 'Tautan / Link Eksternal'],
            ],
        ];
    }
}
