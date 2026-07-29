<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsPenugasanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pengumpulanCollection = $this->whenLoaded('pengumpulan');
        $totalPengumpulan = $this->relationLoaded('pengumpulan') ? $this->pengumpulan->count() : ($this->pengumpulan_count ?? 0);
        $totalDinilai = $this->relationLoaded('pengumpulan') ? $this->pengumpulan->whereNotNull('nilai_guru')->count() : 0;

        return [
            'id' => $this->id,
            // User requested fields & Database fields
            'judul' => $this->judul_tugas,
            'judul_tugas' => $this->judul_tugas,
            'deskripsi' => $this->deskripsi,
            'instruksi' => $this->instruksi,
            'tipe' => $this->tipe_tugas ?? 'individu',
            'tipe_tugas' => $this->tipe_tugas ?? 'individu',
            'jenis_tugas' => $this->jenis_tugas ?? 'tugas',
            'tanggal_mulai' => $this->tanggal_mulai ? $this->tanggal_mulai->format('Y-m-d H:i') : null,
            'tanggal_selesai' => $this->deadline ? $this->deadline->format('Y-m-d H:i') : null,
            'deadline' => $this->deadline ? $this->deadline->format('Y-m-d H:i') : null,
            'nilai_maksimal' => (float) ($this->nilai_maksimal ?? 100),
            'bobot_persen' => (float) ($this->bobot_persen ?? 0),
            'lampiran' => $this->file_lampiran,
            'file_lampiran' => $this->file_lampiran,
            'status' => $this->is_published ? 'dipublikasikan' : 'draft',
            'is_published' => (bool) $this->is_published,
            'izin_kumpul_terlambat' => (bool) $this->izin_kumpul_terlambat,

            // Relasi IDs
            'modul_ajar_id' => $this->modul_ajar_id,
            'mata_pelajaran_id' => $this->mata_pelajaran_id,
            'kelas_id' => $this->kelas_id,
            'guru_id' => $this->guru_id,
            'semester_id' => $this->semester_id,
            'tahun_ajaran_id' => $this->tahun_ajaran_id,

            // Loaded Relations
            'modul_ajar' => $this->relationLoaded('modulAjar') && $this->modulAjar ? [
                'id' => $this->modulAjar->id,
                'judul' => $this->modulAjar->judul_modul ?? $this->modulAjar->judul ?? 'Modul Ajar',
                'judul_modul' => $this->modulAjar->judul_modul ?? $this->modulAjar->judul ?? 'Modul Ajar',
                'kode_modul' => $this->modulAjar->kode_modul ?? null,
            ] : null,

            'guru' => $this->relationLoaded('guru') && $this->guru ? [
                'id' => $this->guru->id,
                'nama' => $this->guru->nama_lengkap ?? $this->guru->name ?? $this->guru->full_name ?? 'Guru',
                'nama_lengkap' => $this->guru->nama_lengkap ?? $this->guru->name ?? $this->guru->full_name ?? 'Guru',
                'nip' => $this->guru->niy ?? $this->guru->nik ?? $this->guru->nip ?? null,
            ] : null,

            'kelas' => $this->relationLoaded('kelas') && $this->kelas ? [
                'id' => $this->kelas->id,
                'nama_kelas' => $this->kelas->nama_kelas ?? $this->kelas->name ?? 'Kelas',
                'tingkat' => $this->kelas->tingkat ?? null,
            ] : null,

            'subject' => $this->relationLoaded('subject') && $this->subject ? [
                'id' => $this->subject->id,
                'nama_mapel' => $this->subject->nama_mapel ?? $this->subject->name ?? 'Mata Pelajaran',
                'kode_mapel' => $this->subject->kode_mapel ?? $this->subject->code ?? null,
            ] : null,

            'semester' => $this->relationLoaded('semester') && $this->semester ? [
                'id' => $this->semester->id,
                'nama_semester' => $this->semester->name ?? $this->semester->semester_name ?? 'Semester',
            ] : null,

            'tahun_ajaran' => $this->relationLoaded('tahunAjaran') && $this->tahunAjaran ? [
                'id' => $this->tahunAjaran->id,
                'tahun_ajaran' => $this->tahunAjaran->year_name ?? $this->tahunAjaran->name ?? 'Tahun Ajaran',
            ] : null,

            'pembuat' => $this->relationLoaded('creator') && $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null,

            // Submissions summary & list
            'total_pengumpulan' => $totalPengumpulan,
            'total_dinilai' => $totalDinilai,
            'pengumpulan' => LmsPengumpulanTugasResource::collection($this->whenLoaded('pengumpulan')),

            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'created_at_formatted' => $this->created_at ? $this->created_at->diffForHumans() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
