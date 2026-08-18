<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsDiskusiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'modul_ajar_id' => $this->modul_ajar_id,
            'modul_ajar' => $this->relationLoaded('modulAjar') && $this->modulAjar ? [
                'id' => $this->modulAjar->id,
                'judul' => $this->modulAjar->judul_modul ?? $this->modulAjar->judul ?? '',
                'judul_modul' => $this->modulAjar->judul_modul ?? $this->modulAjar->judul ?? '',
                'kode_modul' => $this->modulAjar->kode_modul ?? null,
            ] : null,
            'judul' => $this->judul,
            'deskripsi' => $this->deskripsi,
            'kategori' => $this->kategori ?? 'Umum',
            'tanggal_mulai' => $this->tanggal_mulai ? $this->tanggal_mulai->format('Y-m-d H:i') : null,
            'tanggal_tutup' => $this->tanggal_tutup ? $this->tanggal_tutup->format('Y-m-d H:i') : null,
            'is_pinned' => (bool) $this->is_pinned,
            'is_closed' => (bool) $this->is_closed,
            'status' => $this->status ?? 'aktif',
            'jumlah_komentar' => $this->semuaKomentar()->count(),
            'komentar' => LmsDiskusiKomentarResource::collection($this->whenLoaded('komentar')),
            'pembuat' => $this->relationLoaded('creator') && $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null,
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'created_at_formatted' => $this->created_at ? $this->created_at->diffForHumans() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
