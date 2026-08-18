<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsUjianSesiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ujian_id' => $this->ujian_id,
            'siswa_id' => $this->siswa_id,
            'waktu_mulai' => $this->waktu_mulai?->toIso8601String(),
            'waktu_selesai' => $this->waktu_selesai?->toIso8601String(),
            'durasi_aktual_detik' => (int) $this->durasi_aktual_detik,
            'jumlah_benar' => (int) $this->jumlah_benar,
            'jumlah_salah' => (int) $this->jumlah_salah,
            'jumlah_kosong' => (int) $this->jumlah_kosong,
            'nilai_raw' => (float) $this->nilai_raw,
            'nilai_final' => (float) $this->nilai_final,
            'status' => $this->status,
            'ip_address' => $this->ip_address,

            'siswa' => $this->whenLoaded('siswa', function () {
                return [
                    'id' => $this->siswa->id,
                    'full_name' => $this->siswa->full_name,
                    'nis' => $this->siswa->nisn ?? $this->siswa->nis ?? '',
                ];
            }),

            'ujian' => $this->whenLoaded('ujian', function () {
                return [
                    'id' => $this->ujian->id,
                    'judul_ujian' => $this->ujian->judul_ujian,
                    'nilai_kkm' => (float) $this->ujian->nilai_kkm,
                ];
            }),

            'jawaban' => $this->whenLoaded('jawaban', function () {
                return $this->jawaban->map(function ($j) {
                    return [
                        'id' => $j->id,
                        'soal_id' => $j->soal_id,
                        'jawaban_dipilih' => $j->jawaban_dipilih,
                        'jawaban_esai' => $j->jawaban_esai,
                        'is_correct' => $j->is_correct,
                        'poin_didapat' => (float) $j->poin_didapat,
                        'catatan_guru' => $j->catatan_guru,
                    ];
                });
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
