<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class LmsAktivitasBelajarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modul_ajar_id' => ['required', 'uuid', 'exists:lms_modul_ajar,id'],
            'nama_aktivitas' => ['required', 'string', 'max:255'],
            'jenis_aktivitas' => ['required', 'string', 'max:50'],
            'instruksi' => ['nullable', 'string'],
            'waktu' => ['required', 'integer', 'min:1', 'max:600'],
            'urutan' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:aktif,draft,nonaktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'modul_ajar_id.required' => 'Modul Ajar wajib dipilih.',
            'modul_ajar_id.exists' => 'Modul Ajar tidak ditemukan.',
            'nama_aktivitas.required' => 'Nama aktivitas wajib diisi.',
            'jenis_aktivitas.required' => 'Jenis aktivitas wajib diisi.',
            'waktu.required' => 'Alokasi waktu (menit) wajib diisi.',
            'waktu.integer' => 'Alokasi waktu harus berupa angka bulat menit.',
            'urutan.required' => 'Nomor urutan aktivitas wajib diisi.',
            'status.required' => 'Status aktivitas wajib dipilih.',
        ];
    }
}
