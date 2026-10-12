<?php

namespace App\Rules;

use App\Models\BusinessOption;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ActiveBusinessOption implements ValidationRule
{
    public function __construct(
        protected string $group,
        protected bool $allowEmptyIfNoOptions = true
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        // Cek apakah ada opsi yang terdaftar di database untuk grup ini
        $totalOptionsInGroup = BusinessOption::byGroup($this->group)->count();

        if ($totalOptionsInGroup === 0 && $this->allowEmptyIfNoOptions) {
            return;
        }

        // Cek apakah nilai cocok dengan nama atau kode opsi yang AKTIF
        $isActive = BusinessOption::byGroup($this->group)
            ->active()
            ->where(function ($q) use ($value) {
                $q->where('name', $value)
                    ->orWhere('code', $value);
            })
            ->exists();

        if (! $isActive) {
            $fail("Pilihan '{$value}' untuk {$attribute} tidak valid atau telah dinonaktifkan.");
        }
    }
}
