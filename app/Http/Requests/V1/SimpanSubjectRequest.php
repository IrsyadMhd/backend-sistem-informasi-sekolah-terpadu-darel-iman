<?php

namespace App\Http\Requests\V1;

use App\Models\MasterKurikulum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Class SimpanSubjectRequest
 * Validasi request saat membuat data Master Mata Pelajaran baru.
 */
class SimpanSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Synchronize alias fields
        $kode = $this->input('kode_mapel') ?? $this->input('code');
        $nama = $this->input('nama_mapel') ?? $this->input('name');

        $this->merge([
            'kode_mapel' => $kode,
            'nama_mapel' => $nama,
            'code' => $kode,
            'name' => $nama,
        ]);
    }

    public function rules(): array
    {
        $kurikulumId = $this->input('kurikulum_id');

        return [
            'unit_pendidikan_id' => ['required', 'uuid', 'exists:education_units,id'],
            'kurikulum_id' => ['required', 'uuid', 'exists:master_kurikulum,id'],
            'kode_mapel' => [
                'required',
                'string',
                'max:50',
                Rule::unique('subjects', 'kode_mapel')->where(function ($query) use ($kurikulumId) {
                    return $query->where('kurikulum_id', $kurikulumId)->whereNull('deleted_at');
                }),
            ],
            'nama_mapel' => ['required', 'string', 'max:150'],
            'nama_singkat' => ['nullable', 'string', 'max:50'],
            'kelompok_mapel' => ['required', 'string', 'max:50'],
            'kategori' => ['required', 'string', 'max:50'],
            'jenjang' => ['required', 'string', 'max:20'],
            'tingkat_kelas' => ['nullable', 'string', 'max:50'],
            'jam_pelajaran' => ['required', 'integer', 'min:1', 'max:40'],
            'guru_pengampu_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'kkm' => ['required', 'numeric', 'min:0', 'max:100'],
            'bobot_pengetahuan' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bobot_keterampilan' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bobot_sikap' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bobot_nilai' => ['nullable', 'array'],
            'warna' => ['nullable', 'string', 'max:20'],
            'ikon' => ['nullable', 'string', 'max:50'],
            'urutan_tampil' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'boolean'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['uuid', 'exists:employees,id'],
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => ['uuid', 'exists:tbl_kelas,id'],
            'rombel_ids' => ['nullable', 'array'],
            'rombel_ids.*' => ['uuid', 'exists:tbl_kelas,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unitId = $this->input('unit_pendidikan_id');
            $kurikulumId = $this->input('kurikulum_id');

            if ($unitId && $kurikulumId && ! MasterKurikulum::query()
                ->whereKey($kurikulumId)
                ->where('unit_pendidikan_id', $unitId)
                ->exists()) {
                $validator->errors()->add('kurikulum_id', 'Kurikulum harus berasal dari unit pendidikan yang dipilih.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'unit_pendidikan_id.required' => 'Unit Pendidikan wajib dipilih.',
            'kurikulum_id.required' => 'Kurikulum wajib dipilih.',
            'kode_mapel.required' => 'Kode mata pelajaran wajib diisi.',
            'kode_mapel.unique' => 'Kode mata pelajaran sudah terdaftar pada kurikulum yang sama.',
            'nama_mapel.required' => 'Nama mata pelajaran wajib diisi.',
            'jam_pelajaran.required' => 'Jam pelajaran wajib diisi.',
            'kkm.required' => 'KKM wajib diisi.',
        ];
    }
}
