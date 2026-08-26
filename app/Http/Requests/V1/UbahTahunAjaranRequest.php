<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahTahunAjaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $name = trim((string) $this->name);
            $name = preg_replace('/\s+/', ' ', $name);
            $this->merge(['name' => $name]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('tahun_ajaran');

        return [
            'name' => [
                'required',
                'string',
                'max:32',
                Rule::unique('academic_years', 'name')->ignore($id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama tahun ajaran wajib diisi.',
            'name.unique' => 'Nama tahun ajaran ini sudah terdaftar pada data lain.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after' => 'Tanggal selesai harus setelah tanggal mulai.',
        ];
    }
}
