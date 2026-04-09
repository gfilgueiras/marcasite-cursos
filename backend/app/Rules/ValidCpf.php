<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) !== 11) {
            $fail('O CPF deve conter 11 dígitos.');

            return;
        }

        if (preg_match('/^(\d)\1{10}$/', $digits)) {
            $fail('CPF inválido.');

            return;
        }

        for ($i = 0, $sum = 0; $i < 9; $i++) {
            $sum += (int) $digits[$i] * (10 - $i);
        }
        $rest = ($sum * 10) % 11;
        $d1 = $rest === 10 ? 0 : $rest;
        if ($d1 !== (int) $digits[9]) {
            $fail('CPF inválido.');

            return;
        }

        for ($i = 0, $sum = 0; $i < 10; $i++) {
            $sum += (int) $digits[$i] * (11 - $i);
        }
        $rest = ($sum * 10) % 11;
        $d2 = $rest === 10 ? 0 : $rest;
        if ($d2 !== (int) $digits[10]) {
            $fail('CPF inválido.');
        }
    }
}
