<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UbahTujuanPembelajaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cp_id' => ['sometimes', 'required', 'uuid', 'exists:lms_capaian_pembelajaran,id'],
            'kode_tp' => ['nullable', 'string', 'max:50'],
            'nama_tp' => ['nullable', 'string', 'max:250'],
            'deskripsi_tp' => ['nullable', 'string'],
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
            'cp_id.exists' => 'Capaian Pembelajaran (CP) yang dipilih tidak valid.',
        ];
    }
}
