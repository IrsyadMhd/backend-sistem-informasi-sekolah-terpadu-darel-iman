<?php

namespace App\Http\Requests\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SimpanMasterKurikulumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('nama_kurikulum')) {
            $nama = trim((string) $this->nama_kurikulum);
            $nama = preg_replace('/\s+/', ' ', $nama);
            $updates['nama_kurikulum'] = $nama;
        }
        if ($this->has('kode_kurikulum')) {
            $kode = trim((string) $this->kode_kurikulum);
            $kode = preg_replace('/\s+/', ' ', $kode);
            $updates['kode_kurikulum'] = $kode !== '' ? strtoupper($kode) : null;
        }
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kode_kurikulum' => ['required', 'string', 'max:30', 'unique:master_kurikulum,kode_kurikulum'],
            'nama_kurikulum' => ['required', 'string', 'max:150'],
            'jenis_kurikulum' => ['required', 'string', 'in:Nasional,Merdeka,SIT,Lokal,Pesantren,Lainnya'],
            'unit_pendidikan_id' => ['required', 'uuid', 'exists:education_units,id'],
            'jenjang' => ['required', 'string', 'max:20'],
            'tahun_ajaran_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'tanggal_mulai' => ['required', 'date'],
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
            'jenis_kurikulum.in' => 'Pilihan jenis kurikulum tidak valid.',
            'unit_pendidikan_id.required' => 'Unit Pendidikan wajib dipilih.',
            'unit_pendidikan_id.exists' => 'Unit Pendidikan yang dipilih tidak valid.',
            'tahun_ajaran_id.required' => 'Tahun Ajaran wajib dipilih.',
            'tahun_ajaran_id.exists' => 'Tahun Ajaran yang dipilih tidak valid.',
            'tanggal_mulai.required' => 'Tanggal mulai kurikulum wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus tanggal yang sama atau setelah tanggal mulai.',
        ];
    }
}
