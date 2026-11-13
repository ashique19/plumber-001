<?php

namespace App\Support\Validation;

use App\Rules\SaudiVatNumber;
use App\Support\Storefront\StorefrontConfig;
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
    public static function csidToken(): array
    {
        return ['required', 'string', 'min:8', 'max:4096'];
    }

    /**
     * @return list<mixed>
     */
    public static function csidTokenOptionalLive(): array
    {
        return ['nullable', 'string', 'min:8', 'max:4096'];
    }

    /**
     * @return list<mixed>
     */
    public static function csidSecret(): array
    {
        return ['required', 'string', 'min:4', 'max:512'];
    }

    /**
     * @return list<mixed>
     */
    public static function csidSecretOptionalLive(): array
    {
        return ['nullable', 'string', 'min:4', 'max:512'];
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

    /**
     * @return list<mixed>
     */
    public static function ga4MeasurementId(): array
    {
        return [
            'nullable',
            'string',
            'max:32',
            static::closureRule(function (string $value): bool {
                if ($value === '') {
                    return true;
                }

                return StorefrontConfig::isValidGa4Id(StorefrontConfig::normalizeGaInput($value));
            }, 'storefront.errors.ga_invalid'),
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function googleSiteVerification(): array
    {
        return [
            'nullable',
            'string',
            'max:120',
            static::closureRule(
                fn (string $value) => StorefrontConfig::isValidGoogleSiteVerification(trim($value)),
                'storefront.errors.google_verify_invalid',
            ),
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function bingSiteVerification(): array
    {
        return [
            'nullable',
            'string',
            'max:120',
            static::closureRule(
                fn (string $value) => StorefrontConfig::isValidBingSiteVerification(trim($value)),
                'storefront.errors.bing_verify_invalid',
            ),
        ];
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private static function closureRule(Closure $check, string $messageKey): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($check, $messageKey): void {
            if (! is_string($value)) {
                return;
            }
            if (! $check($value)) {
                $fail(__($messageKey));
            }
        };
    }
}
