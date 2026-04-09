<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidBrazilianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 10 || strlen($digits) > 11) {
            $fail('Informe um telefone válido com DDD (10 ou 11 dígitos).');

            return;
        }

        if (! preg_match('/^[1-9]\d{9,10}$/', $digits)) {
            $fail('Informe um telefone válido com DDD.');
        }
    }
}
