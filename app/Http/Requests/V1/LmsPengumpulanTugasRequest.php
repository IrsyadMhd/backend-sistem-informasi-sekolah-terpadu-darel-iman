<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class LmsPengumpulanTugasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'penugasan_id' => ['nullable', 'string', 'exists:lms_penugasan,id'],
            'siswa_id' => ['nullable', 'string', 'exists:students,id'],

            // Standard columns
            'jawaban_teks' => ['nullable', 'string'],
            'file_path' => ['nullable', 'string', 'max:500'],
            'url_link' => ['nullable', 'string', 'max:1000'],
            'nilai_guru' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catatan_guru' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:belum,dikumpulkan,terlambat,dinilai,revisi'],

            // Aliases requested in user prompt
            'file' => ['nullable', 'string', 'max:500'],
            'link' => ['nullable', 'string', 'max:1000'],
            'catatan' => ['nullable', 'string'],
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
