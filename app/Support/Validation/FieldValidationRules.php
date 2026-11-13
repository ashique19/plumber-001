<?php

namespace App\Support\Validation;

use App\Rules\SaudiVatNumber;
use Closure;
/**
 * Shared server rules reused for submit and live validateOnly() checks.
 */
final class FieldValidationRules
{
    /**
     * @return list<mixed>
     */
    public static function vatRequired(): array
    {
        return ['required', 'string', new SaudiVatNumber];
    }

    /**
     * @return list<mixed>
     */
    public static function vatOptional(): array
    {
        return ['nullable', 'string', new SaudiVatNumber];
    }

    /**
     * @return list<mixed>
     */
    public static function postalCodeRequired(): array
    {
        return ['required', 'string', 'regex:/^\d{5}$/'];
    }

    /**
     * @return list<mixed>
     */
    public static function emailOptional(): array
    {
        return ['nullable', 'email', 'max:120'];
    }

    /**
     * @return list<mixed>
     */
    public static function emailRequired(): array
    {
        return ['required', 'email', 'max:255'];
    }

    /**
     * @return list<mixed>
     */
    public static function saudiMobileRequired(): array
    {
        return ['required', 'string', 'max:20', 'regex:/^(?:\+966|966|0)?5\d{8}$/'];
    }

    /**
     * @return list<mixed>
     */
    public static function saudiMobileOptional(): array
    {
        return ['nullable', 'string', 'max:20', 'regex:/^(?:\+966|966|0)?5\d{8}$/'];
    }

    /**
     * @return list<mixed>
     */
    public static function urlOptional(): array
    {
        return ['nullable', 'url', 'max:2048'];
    }

    /**
     * @return list<mixed>
     */
    public static function otpOnSubmit(): array
    {
        return ['required', 'string', 'min:4', 'max:32', 'regex:/^[A-Za-z0-9]+$/'];
    }

    /**
     * @return list<mixed>
     */
    public static function otpOptionalLive(): array
    {
        return ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9]*$/'];
    }

    /**
     * @return list<mixed>
     */
    public static function crNumberOptional(): array
    {
        return ['nullable', 'string', 'max:32', 'regex:/^\d{0,10}$/'];
    }

    /**
     * @return list<mixed>
     */
    public static function slug(): array
    {
        return ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
    }

    /**
     * @return list<mixed>
     */
    public static function positiveDecimal(): array
    {
        return ['required', 'numeric', 'min:0.01'];
    }

    /**
     * @return list<mixed>
     */
    public static function nonZeroInteger(): array
    {
        return ['required', 'integer', 'not_in:0'];
    }

}
