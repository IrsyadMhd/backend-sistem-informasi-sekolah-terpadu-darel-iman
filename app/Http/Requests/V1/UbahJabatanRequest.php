<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UbahJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('kode_jabatan')) {
            $kode = trim((string) $this->kode_jabatan);
            $kode = preg_replace('/\s+/', ' ', $kode);
            $updates['kode_jabatan'] = $kode !== '' ? strtoupper($kode) : null;
        }
        if ($this->has('nama_jabatan')) {
            $nama = trim((string) $this->nama_jabatan);
            $nama = preg_replace('/\s+/', ' ', $nama);
            $updates['nama_jabatan'] = $nama;
        }
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        $id = $this->route('jabatan') ?? $this->route('id');

        return [
            'kode_jabatan' => 'nullable|string|max:50|unique:positions,code,'.$id,
            'nama_jabatan' => 'sometimes|required|string|max:255',
            'satuan_kerja' => 'sometimes|required|string|in:Pengurus,Bidang Pendidikan,Unit Pendidikan',
            'unit_sekolah_id' => 'nullable|uuid|exists:education_units,id',
            'level_jabatan' => 'sometimes|required|integer|between:1,10',
            'atasan_langsung_id' => 'nullable|uuid|exists:positions,id',
            'atasan_pegawai_id' => 'nullable|uuid|exists:employees,id',
            'role_sistem_id' => 'nullable|integer|exists:roles,id',
            'scope_akses' => 'sometimes|required|string|in:semua_unit,bidang_pendidikan,unit_sendiri,rombel_sendiri,kelas_mapel_sendiri,siswa_binaan',
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
            'level_jabatan.between' => 'Level jabatan harus berada antara level 1 hingga 10.',
            'unit_sekolah_id.exists' => 'Unit sekolah tidak valid.',
            'satuan_kerja.required' => 'Satuan kerja wajib dipilih.',
            'atasan_langsung_id.exists' => 'Jabatan atasan langsung tidak ditemukan.',
            'atasan_pegawai_id.exists' => 'Pegawai atasan langsung tidak ditemukan.',
            'role_sistem_id.exists' => 'Role sistem tidak ditemukan.',
        ];
    }
}
