<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTujuanPembelajaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cp_id' => ['required', 'uuid', 'exists:lms_capaian_pembelajaran,id'],
            'kode_tp' => ['nullable', 'string', 'max:50'],
            'nama_tp' => ['nullable', 'string', 'max:250'],
            'deskripsi_tp' => ['required', 'string'],
            'deskripsi' => ['nullable', 'string'],
            'alokasi_waktu_jp' => ['nullable', 'integer', 'min:1'],
            'urutan' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cp_id.required' => 'Capaian Pembelajaran (CP) wajib dipilih.',
            'cp_id.exists' => 'Capaian Pembelajaran yang dipilih tidak valid.',
            'deskripsi_tp.required' => 'Deskripsi Tujuan Pembelajaran (TP) wajib diisi.',
        ];
    }
}
