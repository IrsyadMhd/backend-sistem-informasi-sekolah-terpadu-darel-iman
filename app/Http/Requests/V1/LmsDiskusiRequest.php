<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class LmsDiskusiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('judul')) {
            $judul = trim((string) $this->judul);
            $judul = preg_replace('/\s+/', ' ', $judul);
            $this->merge(['judul' => $judul]);
        }
    }

    public function rules(): array
    {
        return [
            'modul_ajar_id' => ['nullable', 'string', 'exists:lms_modul_ajar,id'],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['nullable', 'string', 'max:50'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_tutup' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_closed' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:aktif,draft,nonaktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul diskusi wajib diisi.',
            'modul_ajar_id.exists' => 'Modul Ajar tidak valid.',
            'tanggal_tutup.after_or_equal' => 'Tanggal tutup harus setelah atau sama dengan tanggal mulai.',
        ];
    }
}
