<?php

namespace App\Http\Requests\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEducationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && trim((string) $this->code) === '') {
            $this->merge(['code' => null]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $param = $this->route('education_unit');
        $educationUnitId = is_object($param) ? ($param->id ?? null) : $param;

        return [
            'jenis_unit_id' => ['nullable', 'string'],
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

    public function messages(): array
    {
        return [
            'name.required' => 'Nama Unit Pendidikan wajib diisi.',
            'name.max' => 'Nama Unit Pendidikan maksimal 120 karakter.',
            'code.unique' => 'Kode Unit Pendidikan sudah digunakan, gunakan kode lain.',
        ];
    }
}
