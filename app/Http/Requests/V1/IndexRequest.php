<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'unit_id' => ['nullable', 'string', 'max:100'],
            'unit_pendidikan_id' => ['nullable', 'string', 'max:100'],
            'kelas_id' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
