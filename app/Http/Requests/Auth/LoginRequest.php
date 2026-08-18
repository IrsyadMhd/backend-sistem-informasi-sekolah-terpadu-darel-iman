<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['nullable', 'string', 'required_without:email'],
            // `email` tetap diterima sebagai nama field legacy untuk client lama.
            'email' => ['nullable', 'string', 'required_without:identifier'],
            'password' => ['required', 'string', 'min:8'],
            'device_name' => ['nullable', 'string', 'max:150'],
        ];
    }
}
