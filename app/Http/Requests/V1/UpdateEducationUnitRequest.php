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
                Rule::unique('education_units', 'code')
                    ->ignore($educationUnitId, 'id')
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('education_units', 'name')
                    ->ignore($educationUnitId, 'id')
                    ->whereNull('deleted_at'),
            ],
            'level' => ['nullable', 'string', 'max:50'],
            'program_type' => ['nullable', 'string', 'in:fullday,boarding'],
            'jam_masuk' => ['nullable', 'string', 'max:10'],
            'jam_pulang' => ['nullable', 'string', 'max:10'],
            'jam_kegiatan' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meter' => ['nullable', 'integer', 'min:1', 'max:50000'],
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
