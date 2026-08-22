<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UbahMateriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modul_ajar_id' => 'sometimes|required|uuid|exists:lms_modul_ajar,id',
            'judul' => 'sometimes|required|string|max:255',
            'tipe' => 'nullable|string|max:50',
            'isi' => 'nullable|string',
            'konten' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,jpg,png|max:20480',
            'file_url' => 'nullable|string|max:500',
            'video' => 'nullable|string|max:500',
            'link' => 'nullable|string|max:500',
            'urutan' => 'nullable|integer|min:1',
            'status' => 'nullable|string|max:30',
            'ringkasan' => 'nullable|string',
            'catatan' => 'nullable|string',
            'bobot' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'modul_ajar_id.required' => 'Modul Ajar wajib dipilih.',
            'modul_ajar_id.exists' => 'Modul Ajar tidak ditemukan.',
            'judul.required' => 'Judul materi wajib diisi.',
            'judul.max' => 'Judul materi maksimal 255 karakter.',
            'file.mimes' => 'Format file dokumen tidak didukung.',
            'file.max' => 'Ukuran file maksimal 20 MB.',
        ];
    }
}
