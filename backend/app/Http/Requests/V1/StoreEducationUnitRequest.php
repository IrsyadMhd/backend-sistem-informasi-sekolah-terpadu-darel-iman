<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEducationUnitRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'jenis_unit_id' => ['nullable', 'string'],
            'code' => ['nullable', 'string', 'max:30', 'unique:education_units,code'],
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
