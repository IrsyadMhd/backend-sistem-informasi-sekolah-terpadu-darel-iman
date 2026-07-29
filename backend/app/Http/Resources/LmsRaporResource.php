<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsRaporResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'siswa_id' => $this->siswa_id,
            'kelas_id' => $this->kelas_id,
            'semester_id' => $this->semester_id,
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'guru_wali_id' => $this->guru_wali_id,

            // Relasi
            'siswa' => [
                'id' => $this->siswa?->id,
                'name' => $this->siswa?->name,
                'nisn' => $this->siswa?->nisn,
                'nis' => $this->siswa?->nis,
            ],
            'kelas' => [
                'id' => $this->kelas?->id,
                'nama_kelas' => $this->kelas?->nama_kelas,
                'tingkat' => $this->kelas?->tingkat,
            ],
            'semester' => [
                'id' => $this->semester?->id,
                'nama' => $this->semester?->nama,
            ],
            'tahun_ajaran' => [
                'id' => $this->tahunAjaran?->id,
                'year' => $this->tahunAjaran?->year,
            ],
            'wali_kelas' => [
                'id' => $this->waliKelas?->id,
                'name' => $this->waliKelas?->name,
                'nip' => $this->waliKelas?->nip,
            ],

            // Nilai Akademik
            'total_nilai' => (float) $this->total_nilai,
            'rata_rata' => (float) $this->rata_rata,
            'peringkat_kelas' => $this->peringkat_kelas,
            'total_siswa_kelas' => $this->total_siswa_kelas,
            'total_mapel' => (int) $this->total_mapel,
            'mapel_lulus' => (int) $this->mapel_lulus,
            'mapel_tidak_lulus' => (int) $this->mapel_tidak_lulus,

            // Presensi
            'total_hari_efektif' => (int) $this->total_hari_efektif,
            'total_hadir' => (int) $this->total_hadir,
            'total_izin' => (int) $this->total_izin,
            'total_sakit' => (int) $this->total_sakit,
            'total_alpha' => (int) $this->total_alpha,

            // Catatan & Status
            'catatan_wali_kelas' => $this->catatan_wali_kelas,
            'catatan_kepala_sekolah' => $this->catatan_kepala_sekolah,
            'status_rapor' => $this->status_rapor,
            'tanggal_terbit' => $this->tanggal_terbit ? $this->tanggal_terbit->format('Y-m-d') : null,
            'sudah_dilihat_ortu' => (bool) $this->sudah_dilihat_ortu,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
