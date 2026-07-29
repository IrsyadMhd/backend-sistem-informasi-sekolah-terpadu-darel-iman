<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsAktivitasBelajarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'modul_ajar_id' => $this->modul_ajar_id,
            'modul_ajar' => $this->whenLoaded('modulAjar', function () {
                return [
                    'id' => $this->modulAjar->id,
                    'judul_modul' => $this->modulAjar->judul_modul,
                    'kode_modul' => $this->modulAjar->kode_modul,
                ];
            }),
            'nama_aktivitas' => $this->nama_aktivitas,
            'jenis_aktivitas' => $this->jenis_aktivitas,
            'instruksi' => $this->instruksi,
            'waktu' => $this->waktu,
            'urutan' => $this->urutan,
            'status' => $this->status,
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
