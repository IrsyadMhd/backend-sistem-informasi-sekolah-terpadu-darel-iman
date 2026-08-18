<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TujuanPembelajaranResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cp_id' => $this->cp_id,
            'kode_tp' => $this->kode_tp,
            'nama_tp' => $this->nama_tp,
            'deskripsi' => $this->deskripsi,
            'deskripsi_tp' => $this->deskripsi_tp,
            'alokasi_waktu_jp' => $this->alokasi_waktu_jp ?? 2,
            'urutan' => $this->urutan,
            'status' => (bool) $this->status,
            'capaian_pembelajaran' => $this->whenLoaded('capaianPembelajaran', function () {
                return [
                    'id' => $this->capaianPembelajaran->id,
                    'kode_cp' => $this->capaianPembelajaran->kode_cp,
                    'nama_cp' => $this->capaianPembelajaran->nama_cp,
                    'deskripsi' => $this->capaianPembelajaran->deskripsi,
                    'fase' => $this->capaianPembelajaran->fase,
                    'kelas_target' => $this->capaianPembelajaran->kelas_target,
                    'mata_pelajaran' => $this->capaianPembelajaran->subject ? [
                        'id' => $this->capaianPembelajaran->subject->id,
                        'code' => $this->capaianPembelajaran->subject->code,
                        'name' => $this->capaianPembelajaran->subject->name,
                    ] : null,
                    'kurikulum' => $this->capaianPembelajaran->kurikulum ? [
                        'id' => $this->capaianPembelajaran->kurikulum->id,
                        'nama' => $this->capaianPembelajaran->kurikulum->nama,
                        'kode' => $this->capaianPembelajaran->kurikulum->kode,
                    ] : null,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
