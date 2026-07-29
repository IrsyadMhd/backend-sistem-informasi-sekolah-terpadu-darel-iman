<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTujuanPembelajaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cp_id' => ['sometimes', 'required', 'uuid', 'exists:lms_capaian_pembelajaran,id'],
            'kode_tp' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nama_tp' => ['sometimes', 'nullable', 'string', 'max:250'],
            'deskripsi_tp' => ['sometimes', 'required', 'string'],
            'deskripsi' => ['sometimes', 'nullable', 'string'],
            'alokasi_waktu_jp' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'urutan' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
