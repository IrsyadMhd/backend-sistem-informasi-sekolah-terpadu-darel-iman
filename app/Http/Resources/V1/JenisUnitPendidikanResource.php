<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JenisUnitPendidikanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'kode_jenis' => $this->kode_jenis,
            'nama_jenis' => $this->nama_jenis,
            'singkatan' => $this->singkatan,
            'jenjang' => $this->jenjang,
            'warna_badge' => $this->warna_badge ?? '#10B981',
            'icon' => $this->icon ?? 'School',
            'urutan' => (int) $this->urutan,
            'keterangan' => $this->keterangan,
            'status' => (bool) $this->status,
            'status_label' => $this->status ? 'Aktif' : 'Tidak Aktif',
            'created_by' => $this->created_by,
            'created_by_name' => $this->creator?->name ?? 'Sistem',
            'updated_by' => $this->updated_by,
            'updated_by_name' => $this->updater?->name ?? null,
            'deleted_by' => $this->deleted_by,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'deleted_at' => $this->deleted_at ? $this->deleted_at->format('Y-m-d H:i:s') : null,
            'is_deleted' => $this->trashed(),
        ];
    }
}
