<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class LmsDiskusiKomentarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'string', 'exists:lms_diskusi_komentar,id'],
            'peran_pengirim' => ['nullable', 'string', 'in:Guru,Siswa,Admin'],
            'konten' => ['required', 'string'],
            'is_solution' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'konten.required' => 'Komentar tidak boleh kosong.',
            'parent_id.exists' => 'Komentar yang dibalas tidak ditemukan.',
        ];
    }
}
