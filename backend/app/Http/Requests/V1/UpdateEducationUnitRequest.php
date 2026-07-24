<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEducationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        $educationUnitId = (string) $this->route('education_unit');

        return [
            'code' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('education_units', 'code')->ignore($educationUnitId, 'id'),
            ],
            'name' => ['required', 'string', 'max:120'],
            'level' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
