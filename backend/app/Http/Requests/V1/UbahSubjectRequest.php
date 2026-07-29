<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class UbahSubjectRequest
 * Validasi request saat memperbarui data Master Mata Pelajaran.
 */
class UbahSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $kode = $this->input('kode_mapel') ?? $this->input('code');
        $nama = $this->input('nama_mapel') ?? $this->input('name');

        if ($kode) {
            $this->merge(['kode_mapel' => $kode, 'code' => $kode]);
        }
        if ($nama) {
            $this->merge(['nama_mapel' => $nama, 'name' => $nama]);
        }
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject');
        $kurikulumId = $this->input('kurikulum_id');

        return [
            'unit_pendidikan_id' => ['sometimes', 'required', 'uuid', 'exists:education_units,id'],
            'kurikulum_id' => ['sometimes', 'required', 'uuid', 'exists:master_kurikulum,id'],
            'kode_mapel' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('subjects', 'kode_mapel')
                    ->where(function ($query) use ($kurikulumId) {
                        if ($kurikulumId) {
                            $query->where('kurikulum_id', $kurikulumId);
                        }
                        return $query->whereNull('deleted_at');
                    })
                    ->ignore($subjectId),
            ],
            'nama_mapel' => ['sometimes', 'required', 'string', 'max:150'],
            'nama_singkat' => ['nullable', 'string', 'max:50'],
            'kelompok_mapel' => ['sometimes', 'required', 'string', 'max:50'],
            'kategori' => ['sometimes', 'required', 'string', 'max:50'],
            'jenjang' => ['sometimes', 'required', 'string', 'max:20'],
            'tingkat_kelas' => ['nullable', 'string', 'max:50'],
            'jam_pelajaran' => ['sometimes', 'required', 'integer', 'min:1', 'max:40'],
            'guru_pengampu_id' => ['nullable', 'uuid'],
            'kkm' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'bobot_pengetahuan' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bobot_keterampilan' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bobot_sikap' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bobot_nilai' => ['nullable', 'array'],
            'warna' => ['nullable', 'string', 'max:20'],
            'ikon' => ['nullable', 'string', 'max:50'],
            'urutan_tampil' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', 'boolean'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['uuid'],
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => ['uuid'],
            'rombel_ids' => ['nullable', 'array'],
            'rombel_ids.*' => ['uuid'],
        ];
    }
}
