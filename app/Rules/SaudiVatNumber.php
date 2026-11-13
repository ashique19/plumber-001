<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SaudiVatNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $vat = preg_replace('/\D/', '', (string) $value) ?? '';

        if (! preg_match('/^3\d{13}3$/', $vat)) {
            $fail(__('business.fields.validation.vat_format'));
        }
    }
}
