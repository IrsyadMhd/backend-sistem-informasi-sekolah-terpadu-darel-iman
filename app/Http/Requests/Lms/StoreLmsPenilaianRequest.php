<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;

class StoreLmsPenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'kelas_id' => ['required', 'uuid', 'exists:tbl_kelas,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'score_assignment' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'score_quiz' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'score_midterm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'score_final' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bobot_tugas' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bobot_uh' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bobot_uts' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bobot_uas' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nilai_kkm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa wajib dipilih.',
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'semester_id.required' => 'Semester wajib dipilih.',
            'kelas_id.required' => 'Kelas wajib dipilih.',
        ];
    }
}
