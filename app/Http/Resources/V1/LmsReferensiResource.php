<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsReferensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fileUrl = null;
        if ($this->file) {
            $fileUrl = str_starts_with($this->file, 'http')
                ? $this->file
                : asset('storage/'.$this->file);
        }

        return [
            'id' => $this->id,
            'modul_ajar_id' => $this->modul_ajar_id,
            'modul_ajar' => $this->whenLoaded('modulAjar', function () {
                return [
                    'id' => $this->modulAjar->id,
                    'judul_modul' => $this->modulAjar->judul_modul,
                    'kode_modul' => $this->modulAjar->kode_modul,
                    'subject' => $this->modulAjar->relationLoaded('subject') && $this->modulAjar->subject ? [
                        'id' => $this->modulAjar->subject->id,
                        'name' => $this->modulAjar->subject->name ?? $this->modulAjar->subject->nama,
                    ] : null,
                ];
            }),
            'judul' => $this->judul,
            'penulis' => $this->penulis,
            'penerbit' => $this->penerbit,
            'tahun' => $this->tahun ? (int) $this->tahun : null,
            'url' => $this->url,
            'file' => $this->file,
            'file_url' => $fileUrl,
            'status' => $this->status ?? 'aktif',
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
