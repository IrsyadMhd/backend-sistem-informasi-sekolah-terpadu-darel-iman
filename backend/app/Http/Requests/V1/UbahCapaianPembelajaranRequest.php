<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UbahCapaianPembelajaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_pendidikan_id' => ['nullable', 'uuid', 'exists:education_units,id'],
            'tahun_ajaran_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'kurikulum_id' => ['sometimes', 'required', 'uuid', 'exists:master_kurikulum,id'],
            'mata_pelajaran_id' => ['sometimes', 'required', 'uuid', 'exists:subjects,id'],
            'kode_cp' => ['sometimes', 'required', 'string', 'max:50'],
            'nama_cp' => ['sometimes', 'required', 'string', 'max:250'],
            'deskripsi' => ['nullable', 'string'],
            'fase' => ['nullable', 'string', 'max:20'],
            'kelas_target' => ['nullable', 'string', 'max:50'],
            'urutan' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'kurikulum_id.required' => 'Kurikulum wajib dipilih.',
            'kurikulum_id.exists' => 'Kurikulum yang dipilih tidak valid.',
            'mata_pelajaran_id.required' => 'Mata Pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists' => 'Mata Pelajaran yang dipilih tidak valid.',
            'kode_cp.required' => 'Kode CP wajib diisi.',
            'nama_cp.required' => 'Nama Capaian Pembelajaran wajib diisi.',
        ];
    }
}
