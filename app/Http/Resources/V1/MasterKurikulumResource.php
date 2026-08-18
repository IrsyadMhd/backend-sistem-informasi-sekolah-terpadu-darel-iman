<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterKurikulumResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_kurikulum' => $this->kode_kurikulum,
            'nama_kurikulum' => $this->nama_kurikulum,
            'jenis_kurikulum' => $this->jenis_kurikulum,
            'unit_pendidikan_id' => $this->unit_pendidikan_id,
            'unit_pendidikan_nama' => $this->unitPendidikan?->name,
            'jenjang' => $this->jenjang,
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'tahun_ajaran_nama' => $this->tahunAjaran?->name,
            'semester_id' => $this->semester_id,
            'semester_nama' => $this->semester?->name,
            'tanggal_mulai' => $this->tanggal_mulai ? $this->tanggal_mulai->format('Y-m-d') : null,
            'tanggal_selesai' => $this->tanggal_selesai ? $this->tanggal_selesai->format('Y-m-d') : null,
            'status' => (bool) $this->status,
            'deskripsi' => $this->deskripsi,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'updated_by' => $this->updated_by,
            'updater_name' => $this->updater?->name,
            'deleted_by' => $this->deleted_by,
            'deleter_name' => $this->deleter?->name,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'deleted_at' => $this->deleted_at ? $this->deleted_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
