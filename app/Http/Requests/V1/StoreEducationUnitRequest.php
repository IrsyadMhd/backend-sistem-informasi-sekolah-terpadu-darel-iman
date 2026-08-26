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
        $updates = [];
        if ($this->has('code')) {
            $code = trim((string) $this->code);
            $code = preg_replace('/\s+/', ' ', $code);
            $updates['code'] = $code !== '' ? $code : null;
        }
        if ($this->has('name')) {
            $name = trim((string) $this->name);
            $name = preg_replace('/\s+/', ' ', $name);
            $updates['name'] = $name;
        }
        if (! empty($updates)) {
            $this->merge($updates);
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
            'name' => ['required', 'string', 'max:120', 'unique:education_units,name'],
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
            'name.unique' => 'Nama Unit Pendidikan sudah digunakan, gunakan nama lain.',
            'code.unique' => 'Kode Unit Pendidikan sudah digunakan, gunakan kode lain.',
        ];
    }
}
