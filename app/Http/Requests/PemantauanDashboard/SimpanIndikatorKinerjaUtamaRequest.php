<?php

namespace App\Http\Requests\PemantauanDashboard;

use Illuminate\Foundation\Http\FormRequest;

class SimpanIndikatorKinerjaUtamaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_indikator' => ['required', 'string', 'max:80'],
            'nama_indikator' => ['required', 'string', 'max:150'],
            'kategori_indikator' => ['required', 'string', 'max:80'],
            'nilai' => ['required', 'numeric'],
            'target_nilai' => ['nullable', 'numeric'],
            'satuan' => ['nullable', 'string', 'max:30'],
            'bulan_periode' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun_periode' => ['required', 'integer', 'min:2000', 'max:2100'],
            'warna_hex' => ['nullable', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0', 'max:100'],
            'data_tambahan' => ['nullable', 'array'],
        ];
    }
}
