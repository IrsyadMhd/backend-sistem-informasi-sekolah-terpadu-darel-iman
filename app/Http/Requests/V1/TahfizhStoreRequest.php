<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class TahfizhStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'class_id' => ['required', 'uuid', 'exists:classes,id'],
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'record_date' => ['nullable', 'date'],
            'surah_name' => ['required', 'string', 'max:120'],
            'ayah_start' => ['required', 'integer', 'min:1'],
            'ayah_end' => ['required', 'integer', 'gte:ayah_start'],
            'line_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
