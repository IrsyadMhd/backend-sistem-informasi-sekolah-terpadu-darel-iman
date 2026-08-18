<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SimpanMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'materi_id' => ['required', 'uuid', 'exists:lms_materi,id'],
            'nama_file' => ['required', 'string', 'max:255'],
            'tipe_file' => ['required', 'string', 'in:pdf,video,audio,ppt,word,image,link'],
            'file' => ['nullable', 'file', 'mimes:pdf,mp4,webm,mp3,wav,ppt,pptx,doc,docx,jpg,jpeg,png,webp', 'max:51200'], // max 50MB
            'url_eksternal' => ['nullable', 'string', 'max:1000', 'url'],
            'ukuran_bytes' => ['nullable', 'integer', 'min:0'],
            'durasi_detik' => ['nullable', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
            'urutan' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'materi_id.required' => 'Materi Pembelajaran wajib dipilih.',
            'materi_id.exists' => 'Materi Pembelajaran tidak valid atau tidak ditemukan.',
            'nama_file.required' => 'Nama file/media wajib diisi.',
            'tipe_file.required' => 'Tipe media wajib dipilih.',
            'tipe_file.in' => 'Tipe media harus salah satu dari: pdf, video, audio, ppt, word, image, link.',
            'file.mimes' => 'Format file media tidak didukung.',
            'file.max' => 'Ukuran file maksimal adalah 50MB.',
            'url_eksternal.url' => 'Format URL eksternal tidak valid.',
        ];
    }
}
