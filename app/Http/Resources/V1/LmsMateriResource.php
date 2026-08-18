<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsMateriResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'modul_ajar_id' => $this->modul_ajar_id,
            'modul_ajar' => $this->whenLoaded('modulAjar', function () {
                return [
                    'id' => $this->modulAjar->id,
                    'kode_modul' => $this->modulAjar->kode_modul ?? null,
                    'judul_modul' => $this->modulAjar->judul_modul ?? null,
                    'fase' => $this->modulAjar->fase ?? null,
                    'semester' => $this->modulAjar->semester ?? null,
                ];
            }),
            'mata_pelajaran_id' => $this->mata_pelajaran_id,
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'kode_mapel' => $this->subject->kode_mapel ?? null,
                    'nama_mapel' => $this->subject->nama_mapel ?? null,
                ];
            }),
            'guru_id' => $this->guru_id,
            'guru' => $this->whenLoaded('guru', function () {
                return [
                    'id' => $this->guru->id,
                    'nama_lengkap' => $this->guru->nama_lengkap ?? $this->guru->name ?? null,
                ];
            }),
            'judul' => $this->judul,
            'tipe' => $this->tipe ?? $this->tipe_materi ?? 'teks',
            'isi' => $this->isi ?? $this->konten ?? null,
            'file' => $this->file ? asset('storage/'.$this->file) : null,
            'file_raw' => $this->file,
            'video' => $this->video,
            'link' => $this->link,
            'urutan' => (int) ($this->urutan ?? 1),
            'status' => $this->status ?? ($this->is_published ? 'aktif' : 'draft'),
            'catatan' => $this->catatan,
            'media' => $this->whenLoaded('media'),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
