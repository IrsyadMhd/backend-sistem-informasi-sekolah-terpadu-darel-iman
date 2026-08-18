<?php

namespace App\Http\Requests\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class UbahMasterKurikulumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kurikulumId = $this->route('kurikulum') ?? $this->route('id');

        return [
            'kode_kurikulum' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('master_kurikulum', 'kode_kurikulum')->ignore($kurikulumId, 'id'),
            ],
            'nama_kurikulum' => ['sometimes', 'required', 'string', 'max:150'],
            'jenis_kurikulum' => ['sometimes', 'required', 'string', 'in:Nasional,Merdeka,SIT,Lokal,Pesantren,Lainnya'],
            'unit_pendidikan_id' => ['sometimes', 'required', 'uuid', 'exists:education_units,id'],
            'jenjang' => ['sometimes', 'required', 'string', 'max:20'],
            'tahun_ajaran_id' => ['sometimes', 'required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'tanggal_mulai' => ['sometimes', 'required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'status' => ['nullable', 'boolean'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $semesterId = $this->input('semester_id');
            $academicYearId = $this->input('tahun_ajaran_id');

            if ($semesterId && $academicYearId && ! \App\Models\Semester::query()
                ->whereKey($semesterId)
                ->where('academic_year_id', $academicYearId)
                ->exists()) {
                $validator->errors()->add('semester_id', 'Semester harus berasal dari tahun ajaran yang dipilih.');
            }
        });
    }

    /**
     * Pesan kesalahan kustom untuk aturan validasi.
     */
    public function messages(): array
    {
        return [
            'kode_kurikulum.required' => 'Kode kurikulum wajib diisi.',
            'kode_kurikulum.unique' => 'Kode kurikulum sudah digunakan. Silakan gunakan kode lain.',
            'nama_kurikulum.required' => 'Nama kurikulum wajib diisi.',
            'jenis_kurikulum.required' => 'Jenis kurikulum wajib dipilih.',
            'unit_pendidikan_id.required' => 'Unit Pendidikan wajib dipilih.',
            'tahun_ajaran_id.required' => 'Tahun Ajaran wajib dipilih.',
            'tanggal_mulai.required' => 'Tanggal mulai kurikulum wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus tanggal yang sama atau setelah tanggal mulai.',
        ];
    }
}
