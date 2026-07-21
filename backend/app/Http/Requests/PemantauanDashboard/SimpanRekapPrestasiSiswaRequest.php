<?php

namespace App\Http\Requests\PemantauanDashboard;

use Illuminate\Foundation\Http\FormRequest;

class SimpanRekapPrestasiSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_siswa' => ['required', 'uuid', 'exists:students,id'],
            'jenis_prestasi' => ['required', 'string', 'in:akademik,non_akademik'],
            'nama_prestasi' => ['required', 'string', 'max:180'],
            'tingkat_prestasi' => ['nullable', 'string', 'max:80'],
            'tanggal_prestasi' => ['required', 'date'],
            'nilai_prestasi' => ['nullable', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'data_tambahan' => ['nullable', 'array'],
        ];
    }
}
