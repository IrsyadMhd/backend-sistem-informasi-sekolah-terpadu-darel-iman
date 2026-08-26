<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLmsUjianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('judul_ujian')) {
            $judul = trim((string) $this->judul_ujian);
            $judul = preg_replace('/\s+/', ' ', $judul);
            $this->merge(['judul_ujian' => $judul]);
        }
    }

    public function rules(): array
    {
        return [
            'kisi_kisi_id' => ['sometimes', 'required', 'uuid', 'exists:lms_kisi_kisi,id'],
            'kelas_id' => ['sometimes', 'required', 'uuid', 'exists:tbl_kelas,id'],
            'semester_id' => ['sometimes', 'required', 'uuid', 'exists:semesters,id'],
            'guru_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'judul_ujian' => ['sometimes', 'required', 'string', 'max:200'],
            'instruksi' => ['nullable', 'string'],
            'waktu_mulai' => ['nullable', 'date'],
            'waktu_selesai' => ['nullable', 'date'],
            'durasi_menit' => ['sometimes', 'required', 'integer', 'min:1', 'max:360'],
            'acak_soal' => ['nullable', 'boolean'],
            'acak_jawaban' => ['nullable', 'boolean'],
            'tampilkan_nilai_langsung' => ['nullable', 'boolean'],
            'nilai_kkm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_attempt' => ['nullable', 'integer', 'min:1', 'max:10'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'berlangsung', 'selesai', 'dibatalkan'])],
        ];
    }

    public function messages(): array
    {
        return [
            'judul_ujian.required' => 'Judul Ujian wajib diisi.',
            'durasi_menit.required' => 'Durasi waktu ujian wajib ditentukan.',
        ];
    }
}
