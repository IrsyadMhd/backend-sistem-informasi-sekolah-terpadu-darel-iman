<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsPengumpulanTugasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'penugasan_id' => $this->penugasan_id,
            'siswa_id' => $this->siswa_id,

            // Relasi Penugasan
            'penugasan' => $this->relationLoaded('penugasan') && $this->penugasan ? [
                'id' => $this->penugasan->id,
                'judul' => $this->penugasan->judul_tugas,
                'judul_tugas' => $this->penugasan->judul_tugas,
                'subject' => $this->penugasan->subject ? ($this->penugasan->subject->nama_mapel ?? $this->penugasan->subject->name ?? null) : null,
                'kelas' => $this->penugasan->kelas ? ($this->penugasan->kelas->nama_kelas ?? $this->penugasan->kelas->kode_kelas ?? null) : null,
                'deadline' => $this->penugasan->deadline ? $this->penugasan->deadline->format('Y-m-d H:i') : null,
            ] : null,

            // Relasi Siswa
            'siswa' => $this->relationLoaded('siswa') && $this->siswa ? [
                'id' => $this->siswa->id,
                'nama' => $this->siswa->name ?? $this->siswa->nama_lengkap ?? $this->siswa->full_name ?? 'Siswa',
                'nisn' => $this->siswa->nisn ?? $this->siswa->student_id_number ?? null,
                'nis' => $this->siswa->nis ?? null,
            ] : null,

            // Standard Database Columns
            'jawaban_teks' => $this->jawaban_teks,
            'file_path' => $this->file_path,
            'url_link' => $this->url_link,
            'status' => $this->status ?? 'belum',
            'waktu_kumpul' => $this->waktu_kumpul ? $this->waktu_kumpul->format('Y-m-d H:i') : null,
            'waktu_kumpul_formatted' => $this->waktu_kumpul ? $this->waktu_kumpul->diffForHumans() : null,
            'nilai_guru' => $this->nilai_guru !== null ? (float) $this->nilai_guru : null,
            'catatan_guru' => $this->catatan_guru,
            'waktu_dinilai' => $this->waktu_dinilai ? $this->waktu_dinilai->format('Y-m-d H:i') : null,

            // Relasi Penilai (Guru)
            'penilai' => $this->relationLoaded('penilai') && $this->penilai ? [
                'id' => $this->penilai->id,
                'nama' => $this->penilai->name ?? $this->penilai->full_name ?? 'Guru',
            ] : null,

            // Requested Field Aliases
            'file' => $this->file_path,
            'link' => $this->url_link,
            'catatan' => $this->catatan_guru ?? $this->jawaban_teks,
            'nilai' => $this->nilai_guru !== null ? (float) $this->nilai_guru : null,

            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
