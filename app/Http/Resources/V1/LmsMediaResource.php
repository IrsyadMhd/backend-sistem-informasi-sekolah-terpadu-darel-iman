<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fileUrl = null;
        if ($this->path_file) {
            $fileUrl = str_starts_with($this->path_file, 'http')
                ? $this->path_file
                : asset('storage/'.$this->path_file);
        }

        return [
            'id' => $this->id,
            'materi_id' => $this->materi_id,
            'materi' => $this->whenLoaded('materi', function () {
                return [
                    'id' => $this->materi->id,
                    'judul' => $this->materi->judul,
                    'modul_ajar_id' => $this->materi->modul_ajar_id,
                    'modul_ajar' => $this->materi->relationLoaded('modulAjar') && $this->materi->modulAjar ? [
                        'id' => $this->materi->modulAjar->id,
                        'judul_modul' => $this->materi->modulAjar->judul_modul,
                    ] : null,
                ];
            }),
            'nama_file' => $this->nama_file,
            'tipe_file' => strtolower($this->tipe_file ?? 'link'),
            'path_file' => $this->path_file,
            'file_url' => $fileUrl,
            'url_eksternal' => $this->url_eksternal,
            'ukuran_bytes' => $this->ukuran_bytes ? (int) $this->ukuran_bytes : null,
            'ukuran_formatted' => $this->formatSizeUnits($this->ukuran_bytes),
            'durasi_detik' => $this->durasi_detik ? (int) $this->durasi_detik : null,
            'durasi_formatted' => $this->formatDuration($this->durasi_detik),
            'deskripsi' => $this->deskripsi,
            'urutan' => (int) ($this->urutan ?? 1),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function formatSizeUnits(?int $bytes): ?string
    {
        if (! $bytes) {
            return null;
        }
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    private function formatDuration(?int $seconds): ?string
    {
        if (! $seconds) {
            return null;
        }
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}
