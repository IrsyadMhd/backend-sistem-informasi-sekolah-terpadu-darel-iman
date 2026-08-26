<?php

namespace App\Http\Requests\PemantauanDashboard;

use Illuminate\Foundation\Http\FormRequest;

class SimpanPengumumanSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('judul_pengumuman')) {
            $judul = trim((string) $this->judul_pengumuman);
            $judul = preg_replace('/\s+/', ' ', $judul);
            $updates['judul_pengumuman'] = $judul;
        }
        if ($this->has('isi_pengumuman')) {
            $isi = trim((string) $this->isi_pengumuman);
            $updates['isi_pengumuman'] = $isi;
        }
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        return [
            'judul_pengumuman' => ['required', 'string', 'max:200'],
            'isi_pengumuman' => ['required', 'string'],
            'target_peran' => ['nullable', 'array'],
            'target_peran.*' => ['string', 'max:80'],
            'mulai_tampil' => ['required', 'date'],
            'selesai_tampil' => ['nullable', 'date', 'after_or_equal:mulai_tampil'],
            'prioritas' => ['nullable', 'integer', 'min:1', 'max:10'],
            'status_aktif' => ['required', 'boolean'],
            'data_tambahan' => ['nullable', 'array'],
        ];
    }
}
