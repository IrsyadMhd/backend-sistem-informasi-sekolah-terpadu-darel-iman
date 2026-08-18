<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UbahReferensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->has('modul_ajar_id') && ($this->modul_ajar_id === '' || strtolower((string) $this->modul_ajar_id) === 'umum' || strtolower((string) $this->modul_ajar_id) === 'null')) {
            $this->merge([
                'modul_ajar_id' => null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'modul_ajar_id' => ['nullable', 'uuid', 'exists:lms_modul_ajar,id'],
            'judul' => ['sometimes', 'required', 'string', 'max:255'],
            'penulis' => ['nullable', 'string', 'max:255'],
            'penerbit' => ['nullable', 'string', 'max:255'],
            'tahun' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'url' => [
                'nullable',
                'string',
                'max:1000',
                'url',
                function ($attribute, $value, $fail) {
                    if ($value && preg_match('/^(javascript|data|vbscript):/i', trim($value))) {
                        $fail('URL referensi berisi skema protokol yang tidak aman.');
                    }
                },
            ],
            'file' => ['nullable', 'file', 'max:20480'],
            'status' => ['nullable', 'string', 'in:aktif,non-aktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'modul_ajar_id.exists' => 'Modul Ajar yang dipilih tidak valid.',
            'judul.required' => 'Judul referensi wajib diisi.',
            'judul.max' => 'Judul referensi maksimal 255 karakter.',
            'tahun.integer' => 'Tahun terbit harus berupa angka.',
            'tahun.min' => 'Tahun terbit minimal tahun 1900.',
            'tahun.max' => 'Tahun terbit maksimal tahun 2100.',
            'url.url' => 'Format URL referensi tidak valid.',
            'file.max' => 'Ukuran file referensi maksimal adalah 20MB.',
            'status.in' => 'Status harus berupa aktif atau non-aktif.',
        ];
    }
}
