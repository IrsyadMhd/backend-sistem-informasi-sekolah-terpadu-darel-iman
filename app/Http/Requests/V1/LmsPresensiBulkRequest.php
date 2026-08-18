<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class LmsPresensiBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jadwal_pelajaran_id' => ['required', 'uuid', 'exists:class_schedules,id'],
            'tanggal' => ['required', 'date'],
            'pertemuan_ke' => ['nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.siswa_id' => ['required', 'uuid', 'exists:students,id'],
            'items.*.status_hadir' => ['required', 'string', 'in:hadir,izin,sakit,alpa,terlambat'],
            'items.*.keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'jadwal_pelajaran_id.required' => 'Jadwal Pelajaran wajib dipilih.',
            'tanggal.required' => 'Tanggal presensi wajib diisi.',
            'items.required' => 'Daftar presensi siswa tidak boleh kosong.',
            'items.*.siswa_id.required' => 'ID Siswa wajib ada.',
            'items.*.status_hadir.required' => 'Status presensi wajib diisi.',
        ];
    }
}
