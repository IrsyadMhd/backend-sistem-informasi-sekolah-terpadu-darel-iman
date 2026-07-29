<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsUjianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kisi_kisi_id' => $this->kisi_kisi_id,
            'kelas_id' => $this->kelas_id,
            'semester_id' => $this->semester_id,
            'guru_id' => $this->guru_id,
            'judul_ujian' => $this->judul_ujian,
            'instruksi' => $this->instruksi,
            'waktu_mulai' => $this->waktu_mulai?->toIso8601String(),
            'waktu_selesai' => $this->waktu_selesai?->toIso8601String(),
            'durasi_menit' => (int) $this->durasi_menit,
            'acak_soal' => (bool) $this->acak_soal,
            'acak_jawaban' => (bool) $this->acak_jawaban,
            'tampilkan_nilai_langsung' => (bool) $this->tampilkan_nilai_langsung,
            'nilai_kkm' => (float) $this->nilai_kkm,
            'max_attempt' => (int) $this->max_attempt,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'draft' => 'Draft',
                'published' => 'Dipublikasikan',
                'berlangsung' => 'Sedang Berlangsung',
                'selesai' => 'Selesai',
                'dibatalkan' => 'Dibatalkan',
                default => ucfirst($this->status),
            },
            'total_peserta' => $this->sesi_count ?? $this->sesi?->count() ?? 0,

            'kisi_kisi' => $this->whenLoaded('kisiKisi', function () {
                return [
                    'id' => $this->kisiKisi->id,
                    'judul_kisi' => $this->kisiKisi->judul_kisi,
                    'jenis_ujian' => $this->kisiKisi->jenis_ujian,
                    'mata_pelajaran' => $this->kisiKisi->subject->name ?? null,
                    'jumlah_soal_target' => $this->kisiKisi->jumlah_soal,
                ];
            }),

            'kelas' => $this->whenLoaded('kelas', function () {
                return [
                    'id' => $this->kelas->id,
                    'nama_kelas' => $this->kelas->nama_kelas,
                ];
            }),

            'semester' => $this->whenLoaded('semester', function () {
                return [
                    'id' => $this->semester->id,
                    'nama_semester' => $this->semester->nama_semester,
                ];
            }),

            'guru' => $this->whenLoaded('guru', function () {
                return [
                    'id' => $this->guru->id,
                    'nama_lengkap' => $this->guru->nama_lengkap,
                ];
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
