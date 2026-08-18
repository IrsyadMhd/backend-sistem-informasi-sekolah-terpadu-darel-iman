<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsModulAjarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_pendidikan_id' => $this->unit_pendidikan_id,
            'unit_pendidikan' => $this->whenLoaded('educationUnit', function () {
                return [
                    'id' => $this->educationUnit->id,
                    'nama' => $this->educationUnit->name ?? $this->educationUnit->nama_unit ?? null,
                    'kode' => $this->educationUnit->code ?? $this->educationUnit->kode_unit ?? null,
                ];
            }),
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'tahun_ajaran' => $this->whenLoaded('tahunAjaran', function () {
                return [
                    'id' => $this->tahunAjaran->id,
                    'tahun' => $this->tahunAjaran->tahun ?? $this->tahunAjaran->year ?? null,
                ];
            }),
            'semester_id' => $this->semester_id,
            'semester_detail' => $this->whenLoaded('semesterModel', function () {
                return [
                    'id' => $this->semesterModel->id,
                    'nama' => $this->semesterModel->nama ?? $this->semesterModel->name ?? null,
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
            'mata_pelajaran_id' => $this->mata_pelajaran_id,
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'kode_mapel' => $this->subject->kode_mapel ?? $this->subject->code ?? null,
                    'nama_mapel' => $this->subject->nama_mapel ?? $this->subject->name ?? null,
                    'name' => $this->subject->nama_mapel ?? $this->subject->name ?? null,
                ];
            }),
            'guru_id' => $this->guru_id,
            'guru' => $this->whenLoaded('guru', function () {
                return [
                    'id' => $this->guru->id,
                    'nama' => $this->guru->nama_lengkap ?? $this->guru->name ?? null,
                    'nama_lengkap' => $this->guru->nama_lengkap ?? $this->guru->name ?? null,
                    'name' => $this->guru->nama_lengkap ?? $this->guru->name ?? null,
                    'nip' => $this->guru->nip ?? $this->guru->niy ?? $this->guru->nik ?? null,
                ];
            }),
            'kelas_id' => $this->kelas_id,
            'kelas' => $this->whenLoaded('kelas', function () {
                return [
                    'id' => $this->kelas->id,
                    'nama_kelas' => $this->kelas->nama_kelas ?? $this->kelas->name ?? null,
                    'name' => $this->kelas->nama_kelas ?? $this->kelas->name ?? null,
                ];
            }),
            'rombel_id' => $this->rombel_id,
            'rombel' => $this->whenLoaded('rombel', function () {
                return [
                    'id' => $this->rombel->id,
                    'nama_rombel' => $this->rombel->nama_kelas ?? $this->rombel->name ?? null,
                ];
            }),
            'cp_id' => $this->cp_id,
            'capaian_pembelajaran' => $this->whenLoaded('capaianPembelajaran', function () {
                return [
                    'id' => $this->capaianPembelajaran->id,
                    'kode_cp' => $this->capaianPembelajaran->kode_cp,
                    'nama_cp' => $this->capaianPembelajaran->nama_cp,
                ];
            }),
            'tp_id' => $this->tp_id,
            'tujuan_pembelajaran_detail' => $this->whenLoaded('tujuanPembelajaran', function () {
                return [
                    'id' => $this->tujuanPembelajaran->id,
                    'kode_tp' => $this->tujuanPembelajaran->kode_tp,
                    'nama_tp' => $this->tujuanPembelajaran->nama_tp,
                ];
            }),
            'cps' => $this->whenLoaded('cps'),
            'tps' => $this->whenLoaded('tps'),
            'kode_modul' => $this->kode_modul,
            'judul_modul' => $this->judul_modul,
            'fase' => $this->fase,
            'semester' => $this->semester,
            'alokasi_waktu_jp' => $this->alokasi_waktu_jp,
            'alokasi_jam' => $this->alokasi_waktu_jp,
            'tujuan_pembelajaran' => $this->tujuan_pembelajaran,
            'profil_pelajar_pancasila' => $this->profil_pelajar_pancasila,
            'target_peserta_didik' => $this->target_peserta_didik,
            'model_pembelajaran' => $this->model_pembelajaran,
            'metode_pembelajaran' => $this->metode_pembelajaran,
            'media_pembelajaran' => $this->media_pembelajaran,
            'sumber_belajar' => $this->sumber_belajar,
            'kegiatan_pendahuluan' => $this->kegiatan_pendahuluan,
            'kegiatan_inti' => $this->kegiatan_inti,
            'kegiatan_penutup' => $this->kegiatan_penutup,
            'asesmen_awal' => $this->asesmen_awal,
            'asesmen_proses' => $this->asesmen_proses,
            'asesmen_akhir' => $this->asesmen_akhir,
            'rencana_penilaian' => $this->rencana_penilaian,
            'refleksi_guru' => $this->refleksi_guru,
            'lampiran' => $this->lampiran,
            'status' => ucfirst($this->status),
            'deskripsi' => $this->deskripsi,
            'versi' => $this->versi,
            'revisions' => $this->whenLoaded('revisions'),
            'materi_count' => $this->materi ? $this->materi->count() : 0,
            'penugasan_count' => $this->penugasan ? $this->penugasan->count() : 0,
            'kisi_kisi_count' => $this->kisiKisi ? $this->kisiKisi->count() : 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
