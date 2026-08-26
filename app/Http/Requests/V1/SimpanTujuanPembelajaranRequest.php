<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SimpanTujuanPembelajaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('nama_tp')) {
            $nama = trim((string) $this->nama_tp);
            $nama = preg_replace('/\s+/', ' ', $nama);
            $updates['nama_tp'] = $nama;
        }
        if ($this->has('kode_tp')) {
            $kode = trim((string) $this->kode_tp);
            $kode = preg_replace('/\s+/', ' ', $kode);
            $updates['kode_tp'] = $kode !== '' ? strtoupper($kode) : null;
        }
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        return [
            'cp_id' => ['required', 'uuid', 'exists:lms_capaian_pembelajaran,id'],
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
