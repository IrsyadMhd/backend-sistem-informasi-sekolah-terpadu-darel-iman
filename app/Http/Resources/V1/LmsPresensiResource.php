<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsPresensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $jadwal = $this->whenLoaded('jadwalPelajaran');
        $siswa = $this->whenLoaded('siswa');

        return [
            'id' => $this->id,
            'jadwal_pelajaran_id' => $this->jadwal_pelajaran_id,
            'siswa_id' => $this->siswa_id,
            'tanggal' => $this->tanggal ? $this->tanggal->format('Y-m-d') : null,
            'status_hadir' => strtolower($this->status_hadir),
            'status_label' => $this->status_label,
            'status_badge_color' => $this->status_badge_color,
            'keterangan' => $this->keterangan,
            'pertemuan_ke' => $this->pertemuan_ke,
            'waktu_presensi' => $this->waktu_presensi ? $this->waktu_presensi->toISOString() : null,

            'jadwal' => $this->when($this->relationLoaded('jadwalPelajaran'), function () use ($jadwal) {
                if (! $jadwal) {
                    return null;
                }
                $kelasObj = $jadwal->relationLoaded('kelas') && $jadwal->kelas
                    ? $jadwal->kelas
                    : ($jadwal->relationLoaded('schoolClass') ? $jadwal->schoolClass : null);
                $teacherObj = $jadwal->relationLoaded('employee') && $jadwal->employee
                    ? $jadwal->employee
                    : ($jadwal->relationLoaded('teacher') ? $jadwal->teacher : null);

                return [
                    'id' => $jadwal->id,
                    'day_of_week' => $jadwal->day_of_week,
                    'day_name' => $jadwal->nama_hari,
                    'time_start' => $jadwal->time_start ? substr($jadwal->time_start, 0, 5) : null,
                    'time_end' => $jadwal->time_end ? substr($jadwal->time_end, 0, 5) : null,
                    'subject' => $jadwal->relationLoaded('subject') && $jadwal->subject ? [
                        'id' => $jadwal->subject->id,
                        'code' => $jadwal->subject->code ?? '',
                        'name' => $jadwal->subject->name ?? '',
                    ] : null,
                    'kelas' => $kelasObj ? [
                        'id' => $kelasObj->id,
                        'nama_kelas' => $kelasObj->nama_kelas ?? $kelasObj->name ?? '',
                        'kode_kelas' => $kelasObj->kode_kelas ?? '',
                    ] : null,
                    'teacher' => $teacherObj ? [
                        'id' => $teacherObj->id,
                        'full_name' => $teacherObj->full_name ?? $teacherObj->name ?? '',
                        'nip' => $teacherObj->nip ?? '',
                    ] : null,
                ];
            }),

            'siswa' => $this->when($this->relationLoaded('siswa'), function () use ($siswa) {
                if (! $siswa) {
                    return null;
                }

                return [
                    'id' => $siswa->id,
                    'full_name' => $siswa->full_name,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                    'class_id' => $siswa->class_id,
                ];
            }),

            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
