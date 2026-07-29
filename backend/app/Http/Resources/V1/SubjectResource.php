<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class SubjectResource
 * Format JSON transformer untuk data Master Mata Pelajaran (Enhanced).
 */
class SubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_pendidikan_id' => $this->unit_pendidikan_id,
            'unit_pendidikan' => $this->whenLoaded('unitPendidikan', function () {
                return [
                    'id' => $this->unitPendidikan->id,
                    'name' => $this->unitPendidikan->name,
                    'code' => $this->unitPendidikan->code,
                ];
            }),
            'kurikulum_id' => $this->kurikulum_id,
            'kurikulum' => $this->whenLoaded('kurikulum', function () {
                return [
                    'id' => $this->kurikulum->id,
                    'kode_kurikulum' => $this->kurikulum->kode_kurikulum,
                    'nama_kurikulum' => $this->kurikulum->nama_kurikulum,
                    'jenis_kurikulum' => $this->kurikulum->jenis_kurikulum,
                ];
            }),
            'kode_mapel' => $this->kode_mapel ?? $this->code,
            'nama_mapel' => $this->nama_mapel ?? $this->name,
            'nama_singkat' => $this->nama_singkat ?? $this->kode_mapel ?? $this->code,
            'code' => $this->code ?? $this->kode_mapel,
            'name' => $this->name ?? $this->nama_mapel,
            'kelompok_mapel' => $this->kelompok_mapel ?? 'Kelompok A',
            'kategori' => $this->kategori ?? 'Wajib',
            'jenjang' => $this->jenjang ?? 'SD',
            'tingkat_kelas' => $this->tingkat_kelas ?? 'All',
            'jam_pelajaran' => $this->jam_pelajaran ?? 2,
            'guru_pengampu_id' => $this->guru_pengampu_id,
            'guru_pengampu' => $this->whenLoaded('guruPengampu', function () {
                return [
                    'id' => $this->guruPengampu->id,
                    'name' => $this->guruPengampu->name,
                    'nip' => $this->guruPengampu->nip ?? '-',
                ];
            }),
            'kkm' => (float) ($this->kkm ?? 75.00),
            'bobot_pengetahuan' => (int) ($this->bobot_pengetahuan ?? 40),
            'bobot_keterampilan' => (int) ($this->bobot_keterampilan ?? 40),
            'bobot_sikap' => (int) ($this->bobot_sikap ?? 20),
            'bobot_nilai' => $this->bobot_nilai ?? [
                'pengetahuan' => $this->bobot_pengetahuan ?? 40,
                'keterampilan' => $this->bobot_keterampilan ?? 40,
                'sikap' => $this->bobot_sikap ?? 20,
            ],
            'warna' => $this->warna ?? '#0E5C44',
            'ikon' => $this->ikon ?? 'BookOpen',
            'urutan_tampil' => (int) ($this->urutan_tampil ?? 1),
            'status' => (bool) ($this->status ?? true),
            'deskripsi' => $this->deskripsi ?? $this->description,
            'description' => $this->description ?? $this->deskripsi,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'pembuat' => $this->whenLoaded('creator', fn () => $this->creator->name),
            'pengubah' => $this->whenLoaded('updater', fn () => $this->updater->name),
            'penghapus' => $this->whenLoaded('deleter', fn () => $this->deleter->name),
            'is_deleted' => $this->trashed(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
