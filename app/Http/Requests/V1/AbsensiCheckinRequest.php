<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AbsensiCheckinRequest extends FormRequest
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
            'attendance_date' => ['nullable', 'date'],
            'attendance_method' => ['required', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
