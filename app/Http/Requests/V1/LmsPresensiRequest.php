<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class LmsPresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jadwal_pelajaran_id' => ['required', 'uuid', 'exists:class_schedules,id'],
            'siswa_id' => ['required', 'uuid', 'exists:students,id'],
            'tanggal' => ['required', 'date'],
            'status_hadir' => ['required', 'string', 'in:hadir,izin,sakit,alpa,terlambat'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'pertemuan_ke' => ['nullable', 'integer', 'min:1'],
            'waktu_presensi' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'jadwal_pelajaran_id.required' => 'Jadwal Pelajaran wajib dipilih.',
            'jadwal_pelajaran_id.exists' => 'Jadwal Pelajaran tidak valid.',
            'siswa_id.required' => 'Siswa wajib dipilih.',
            'siswa_id.exists' => 'Siswa tidak valid.',
            'tanggal.required' => 'Tanggal presensi wajib diisi.',
            'status_hadir.required' => 'Status kehadiran wajib diisi.',
            'status_hadir.in' => 'Status kehadiran harus salah satu dari: hadir, izin, sakit, alpa, terlambat.',
        ];
    }
}
