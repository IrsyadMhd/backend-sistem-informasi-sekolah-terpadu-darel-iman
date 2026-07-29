<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;

class CalculateLmsPenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'uuid', 'exists:tbl_kelas,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'bobot_tugas' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bobot_uh' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bobot_uts' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bobot_uas' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nilai_kkm' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'kelas_id.required' => 'Kelas sasaran wajib dipilih.',
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'semester_id.required' => 'Semester wajib dipilih.',
        ];
    }
}
