<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class KelasResource
 * Mengubah objek Model Kelas ke format JSON API terstruktur.
 */
class KelasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'yayasan_id' => $this->yayasan_id,
            'unit_pendidikan_id' => $this->unit_pendidikan_id,
            'unit_pendidikan' => $this->whenLoaded('unitPendidikan', function () {
                return [
                    'id' => $this->unitPendidikan->id,
                    'name' => $this->unitPendidikan->name,
                    'code' => $this->unitPendidikan->code,
                    'level' => $this->unitPendidikan->level,
                ];
            }),
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'tahun_ajaran' => $this->whenLoaded('tahunAjaran', function () {
                return [
                    'id' => $this->tahunAjaran->id,
                    'name' => $this->tahunAjaran->name,
                    'is_active' => $this->tahunAjaran->is_active,
                ];
            }),
            'semester_id' => $this->semester_id,
            'semester' => $this->whenLoaded('semester', function () {
                return [
                    'id' => $this->semester->id,
                    'name' => $this->semester->name,
                    'sequence' => $this->semester->sequence,
                    'is_active' => $this->semester->is_active,
                ];
            }),
            'jenjang' => $this->jenjang,
            'tingkat' => $this->tingkat,
            'kode_kelas' => $this->kode_kelas,
            'nama_kelas' => $this->nama_kelas,
            'wali_kelas_id' => $this->wali_kelas_id,
            'wali_kelas' => $this->whenLoaded('waliKelas', function () {
                return $this->waliKelas ? [
                    'id' => $this->waliKelas->id,
                    'niy' => $this->waliKelas->niy,
                    'nama_lengkap' => $this->waliKelas->nama_lengkap,
                    'gelar_depan' => $this->waliKelas->gelar_depan,
                    'gelar_belakang' => $this->waliKelas->gelar_belakang,
                    'nama_tampil' => trim(($this->waliKelas->gelar_depan ? $this->waliKelas->gelar_depan.' ' : '').$this->waliKelas->nama_lengkap.($this->waliKelas->gelar_belakang ? ', '.$this->waliKelas->gelar_belakang : '')),
                    'no_hp' => $this->waliKelas->no_hp,
                    'email' => $this->waliKelas->email,
                    'foto' => $this->waliKelas->foto,
                ] : null;
            }),
            'kapasitas' => (int) $this->kapasitas,
            'jumlah_siswa' => $this->relationLoaded('siswa') ? $this->siswa->count() : ($this->siswa_count ?? 0),
            'ruangan' => $this->ruangan,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'pembuat' => $this->whenLoaded('pembuat', function () {
                return $this->pembuat ? $this->pembuat->name : null;
            }),
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? $this->deleted_at->toIso8601String() : null,
        ];
    }
}
