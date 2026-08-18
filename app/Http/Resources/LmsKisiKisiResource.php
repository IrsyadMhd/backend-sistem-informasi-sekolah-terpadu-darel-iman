<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsKisiKisiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'judul_kisi' => $this->judul_kisi,
            'jenis_ujian' => $this->jenis_ujian,
            'jumlah_soal' => (int) $this->jumlah_soal,
            'alokasi_waktu_menit' => (int) $this->alokasi_waktu_menit,
            'kompetensi_dasar' => $this->kompetensi_dasar,
            'level_kognitif' => $this->level_kognitif,
            'distribusi_bobot' => $this->distribusi_bobot ?? [
                'pg' => 60,
                'isian' => 20,
                'esai' => 20,
            ],
            'status' => (bool) $this->status,

            'mata_pelajaran_id' => $this->mata_pelajaran_id,
            'mata_pelajaran' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                    'code' => $this->subject->code ?? null,
                ];
            }),

            'cp_id' => $this->cp_id,
            'cp' => $this->whenLoaded('cp', function () {
                return [
                    'id' => $this->cp->id,
                    'kode_cp' => $this->cp->kode_cp ?? null,
                    'nama_cp' => $this->cp->nama_cp ?? null,
                    'fase' => $this->cp->fase ?? null,
                ];
            }),

            'tp_id' => $this->tp_id,
            'tp' => $this->whenLoaded('tp', function () {
                return [
                    'id' => $this->tp->id,
                    'kode_tp' => $this->tp->kode_tp ?? null,
                    'nama_tp' => $this->tp->nama_tp ?? null,
                    'deskripsi' => $this->tp->deskripsi ?? null,
                ];
            }),

            'kurikulum_id' => $this->kurikulum_id,
            'kurikulum' => $this->whenLoaded('kurikulum', function () {
                return [
                    'id' => $this->kurikulum->id,
                    'nama_kurikulum' => $this->kurikulum->nama_kurikulum ?? null,
                    'kode_kurikulum' => $this->kurikulum->kode_kurikulum ?? null,
                ];
            }),

            'kelas_id' => $this->kelas_id,
            'kelas' => $this->whenLoaded('kelas', function () {
                return [
                    'id' => $this->kelas->id,
                    'nama_kelas' => $this->kelas->nama_kelas ?? null,
                    'tingkat' => $this->kelas->tingkat ?? null,
                ];
            }),

            'semester_id' => $this->semester_id,
            'semester' => $this->whenLoaded('semester', function () {
                return [
                    'id' => $this->semester->id,
                    'nama_semester' => $this->semester->nama_semester ?? null,
                ];
            }),

            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'tahun_ajaran' => $this->whenLoaded('tahunAjaran', function () {
                return [
                    'id' => $this->tahunAjaran->id,
                    'name' => $this->tahunAjaran->name ?? null,
                ];
            }),

            'guru_id' => $this->guru_id,
            'guru' => $this->whenLoaded('guru', function () {
                return [
                    'id' => $this->guru->id,
                    'name' => $this->guru->name ?? null,
                    'nip' => $this->guru->nip ?? null,
                ];
            }),

            'bank_soal_count' => $this->whenCounted('bankSoal', $this->bank_soal_count ?? 0),
            'ujian_count' => $this->whenCounted('ujian', $this->ujian_count ?? 0),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
