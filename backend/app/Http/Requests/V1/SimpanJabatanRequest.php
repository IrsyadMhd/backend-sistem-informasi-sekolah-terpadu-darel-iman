<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SimpanJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_jabatan' => 'nullable|string|max:50|unique:positions,code',
            'nama_jabatan' => 'required|string|max:255',
            'unit_sekolah_id' => 'nullable|uuid|exists:education_units,id',
            'level_jabatan' => 'required|integer|between:1,14',
            'atasan_langsung_id' => 'nullable|uuid|exists:positions,id',
            'role_sistem_id' => 'nullable|integer|exists:roles,id',
            'urutan' => 'nullable|integer|min:0',
            'warna' => 'nullable|string|max:30',
            'ikon' => 'nullable|string|max:50',
            'deskripsi' => 'nullable|string',
            'status' => 'nullable|string|in:Aktif,Nonaktif',
            'is_active' => 'nullable|boolean',
            'tampil_struktur' => 'nullable|boolean',
            'boleh_login' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_jabatan.required' => 'Nama jabatan wajib diisi.',
            'kode_jabatan.unique' => 'Kode jabatan sudah digunakan.',
            'level_jabatan.required' => 'Level jabatan wajib dipilih.',
            'level_jabatan.between' => 'Level jabatan harus berada antara level 1 hingga 14.',
            'unit_sekolah_id.exists' => 'Unit sekolah tidak valid.',
            'atasan_langsung_id.exists' => 'Jabatan atasan langsung tidak ditemukan.',
            'role_sistem_id.exists' => 'Role sistem tidak ditemukan.',
        ];
    }
}
