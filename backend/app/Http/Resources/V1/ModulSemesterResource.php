<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModulSemesterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_modul' => $this->kode_modul,
            'nama_modul' => $this->nama_modul,
            'jenjang' => $this->jenjang,
            'kurikulum' => $this->kurikulum,
            'status' => $this->status,

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
                ];
            }),

            'unit_pendidikan_id' => $this->unit_pendidikan_id,
            'unit_pendidikan' => $this->whenLoaded('unitPendidikan', function () {
                return [
                    'id' => $this->unitPendidikan->id,
                    'name' => $this->unitPendidikan->name,
                    'code' => $this->unitPendidikan->code,
                    'level' => $this->unitPendidikan->level,
                ];
            }),

            'kelas_id' => $this->kelas_id,
            'kelas' => $this->whenLoaded('kelas', function () {
                return [
                    'id' => $this->kelas->id,
                    'nama_kelas' => $this->kelas->nama_kelas,
                    'kode_kelas' => $this->kelas->kode_kelas,
                    'tingkat' => $this->kelas->tingkat,
                ];
            }),

            'mata_pelajaran_id' => $this->mata_pelajaran_id,
            'mata_pelajaran' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                    'code' => $this->subject->code,
                ];
            }),

            'guru_id' => $this->guru_id,
            'guru' => $this->whenLoaded('guru', function () {
                return [
                    'id' => $this->guru->id,
                    'nama_lengkap' => $this->guru->nama_lengkap,
                    'niy' => $this->guru->niy,
                ];
            }),

            // Pembelajaran
            'atp' => $this->atp,
            'cp' => $this->cp,
            'tujuan_pembelajaran' => $this->tujuan_pembelajaran,
            'alokasi_jam' => $this->alokasi_jam,
            'jumlah_pertemuan' => $this->jumlah_pertemuan,
            'metode_pembelajaran' => $this->metode_pembelajaran,
            'model_pembelajaran' => $this->model_pembelajaran,
            'media_pembelajaran' => $this->media_pembelajaran,
            'sumber_belajar' => $this->sumber_belajar,

            // Target
            'target_nilai_minimum' => $this->target_nilai_minimum,
            'target_kehadiran' => $this->target_kehadiran,
            'target_hafalan' => $this->target_hafalan,
            'target_proyek' => $this->target_proyek,

            // Pengaturan
            'berlaku_mulai' => $this->berlaku_mulai ? $this->berlaku_mulai->format('Y-m-d') : null,
            'berlaku_sampai' => $this->berlaku_sampai ? $this->berlaku_sampai->format('Y-m-d') : null,
            'ditampilkan_di_portal_ortu' => (bool) $this->ditampilkan_di_portal_ortu,
            'ditampilkan_di_aplikasi_siswa' => (bool) $this->ditampilkan_di_aplikasi_siswa,
            'arsip_otomatis' => (bool) $this->arsip_otomatis,

            // Bobot Penilaian
            'bobot_tugas' => (float) $this->bobot_tugas,
            'bobot_quiz' => (float) $this->bobot_quiz,
            'bobot_projek' => (float) $this->bobot_projek,
            'bobot_uts' => (float) $this->bobot_uts,
            'bobot_uas' => (float) $this->bobot_uas,
            'total_bobot' => (float) ($this->bobot_tugas + $this->bobot_quiz + $this->bobot_projek + $this->bobot_uts + $this->bobot_uas),

            // Detail Materi
            'details' => $this->whenLoaded('details', function () {
                return $this->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'minggu' => $detail->minggu,
                        'materi' => $detail->materi,
                        'atp' => $detail->atp,
                        'cp' => $detail->cp,
                        'jp' => $detail->jp,
                        'keterangan' => $detail->keterangan,
                    ];
                });
            }),

            'created_at' => $this->created_at ? $this->created_at->isoFormat('D MMMM Y, HH:mm') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->isoFormat('D MMMM Y, HH:mm') : null,
        ];
    }
}
