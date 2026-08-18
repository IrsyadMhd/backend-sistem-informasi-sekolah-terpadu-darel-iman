<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SimpanJenisUnitPendidikanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_jenis' => ['required', 'string', 'max:20', 'unique:master_jenis_unit_pendidikan,kode_jenis'],
            'nama_jenis' => ['required', 'string', 'max:150', 'unique:master_jenis_unit_pendidikan,nama_jenis'],
            'singkatan' => ['nullable', 'string', 'max:50'],
            'jenjang' => ['required', 'string', 'in:PAUD,TK,SD,MI,SMP,MTs,SMA,MA,Pondok Pesantren,Mahad'],
            'urutan' => ['required', 'numeric', 'integer', 'min:1'],
            'warna_badge' => ['nullable', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode_jenis.required' => 'Kode wajib diisi',
            'kode_jenis.unique' => 'Kode harus unik',
            'kode_jenis.max' => 'Kode maksimal 20 karakter',
            'nama_jenis.required' => 'Nama wajib diisi',
            'nama_jenis.unique' => 'Nama tidak boleh sama',
            'nama_jenis.max' => 'Nama maksimal 150 karakter',
            'jenjang.required' => 'Jenjang pendidikan wajib dipilih',
            'jenjang.in' => 'Jenjang pendidikan tidak valid',
            'urutan.required' => 'Urutan harus angka',
            'urutan.numeric' => 'Urutan harus angka',
            'urutan.integer' => 'Urutan harus angka',
        ];
    }
}
