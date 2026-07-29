<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLmsRaporRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kelas_id' => 'required|uuid|exists:tbl_kelas,id',
            'semester_id' => 'required|uuid|exists:semesters,id',
            'tahun_ajaran_id' => 'required|uuid|exists:academic_years,id',
        ];
    }
}
