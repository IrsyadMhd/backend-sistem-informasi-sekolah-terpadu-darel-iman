<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsBankSoalResource extends JsonResource
{
    /**
     * Kunci jawaban & pembahasan hanya boleh dilihat pengguna internal
     * (guru/operator/admin). Siswa, Orang Tua, dan Alumni tidak boleh
     * menerima kunci/pembahasan lewat endpoint mana pun.
     */
    private function canSeeAnswerKey(Request $request): bool
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return ! $user->hasAnyRole(['Siswa', 'Orang Tua', 'Alumni']);
    }

    public function toArray(Request $request): array
    {
        $showKey = $this->canSeeAnswerKey($request);

        $pasanganMenjodohkan = null;
        if ($showKey && $this->tipe_soal === 'menjodohkan' && ! empty($this->kunci_jawaban)) {
            $decoded = json_decode($this->kunci_jawaban, true);
            if (is_array($decoded)) {
                $pasanganMenjodohkan = $decoded;
            }
        }

        return [
            'id' => $this->id,
            'kisi_kisi_id' => $this->kisi_kisi_id,
            'mata_pelajaran_id' => $this->mata_pelajaran_id,
            'kode_soal' => $this->kode_soal,
            'pertanyaan' => $this->pertanyaan,
            'tipe_soal' => $this->tipe_soal,
            'tipe_soal_label' => match ($this->tipe_soal) {
                'pg' => 'Pilihan Ganda',
                'esai' => 'Essay / Esai',
                'benar_salah' => 'Benar / Salah',
                'menjodohkan' => 'Menjodohkan',
                default => strtoupper($this->tipe_soal),
            },
            'opsi_a' => $this->opsi_a,
            'opsi_b' => $this->opsi_b,
            'opsi_c' => $this->opsi_c,
            'opsi_d' => $this->opsi_d,
            'opsi_e' => $this->opsi_e,
            'kunci_jawaban' => $showKey ? $this->kunci_jawaban : null,
            'pasangan_menjodohkan' => $pasanganMenjodohkan,
            'pembahasan' => $showKey ? $this->pembahasan : null,
            'poin' => (float) $this->poin,
            'tingkat_kesulitan' => $this->tingkat_kesulitan,
            'tingkat_kesulitan_label' => ucfirst($this->tingkat_kesulitan),
            'indikator' => $this->indikator,
            'gambar_path' => $this->gambar_path,
            'status' => (bool) $this->status,

            'kisi_kisi' => $this->whenLoaded('kisiKisi', function () {
                return [
                    'id' => $this->kisiKisi->id,
                    'judul_kisi' => $this->kisiKisi->judul_kisi,
                    'jenis_ujian' => $this->kisiKisi->jenis_ujian,
                    'mata_pelajaran' => $this->kisiKisi->subject->name ?? null,
                    'kelas' => $this->kisiKisi->kelas->nama_kelas ?? null,
                    'guru' => $this->kisiKisi->guru->nama_lengkap ?? null,
                ];
            }),

            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                    'code' => $this->subject->code,
                ];
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
