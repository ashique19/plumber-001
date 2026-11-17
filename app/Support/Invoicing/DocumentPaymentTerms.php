<?php

namespace App\Support\Invoicing;

use Carbon\Carbon;
use Illuminate\Validation\Rule;

final class DocumentPaymentTerms
{
    public const CASH = 'cash';

    public const NET_7 = 'net_7';

    public const NET_15 = 'net_15';

    public const NET_30 = 'net_30';

    public const CUSTOM = 'custom';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return [
            self::CASH,
            self::NET_7,
            self::NET_15,
            self::NET_30,
            self::CUSTOM,
        ];
    }

    public static function normalizeCode(string $code, int $businessDefaultDays): string
    {
        $code = strtolower(trim($code));
        if ($code === 'credit') {
            return self::defaultCreditTermCode($businessDefaultDays);
        }

        return in_array($code, self::codes(), true) ? $code : self::CASH;
    }

    public static function defaultCreditTermCode(int $businessDefaultDays): string
    {
        return match (true) {
            $businessDefaultDays <= 7 => self::NET_7,
            $businessDefaultDays <= 15 => self::NET_15,
            default => self::NET_30,
        };
    }

    public static function isImmediate(string $code): bool
    {
        return self::normalizeStorageCode($code) === self::CASH;
    }

    public static function isCredit(string $code): bool
    {
        return ! self::isImmediate($code);
    }

    private static function normalizeStorageCode(string $code): string
    {
        $code = strtolower(trim($code));

        return $code === 'credit' ? self::NET_30 : $code;
    }

    public static function netDays(string $code, int $businessDefaultDays): ?int
    {
        return match (self::normalizeStorageCode($code)) {
            self::CASH => 0,
            self::NET_7 => 7,
            self::NET_15 => 15,
            self::NET_30 => 30,
            self::CUSTOM => null,
            default => max(0, $businessDefaultDays),
        };
    }

    public static function computeDueDate(
        string $code,
        string $supplyDate,
        string $currentDueDate,
        int $businessDefaultDays,
    ): string {
        if (self::isImmediate($code)) {
            return self::parseDateOrToday($supplyDate);
        }

        $days = self::netDays($code, $businessDefaultDays);
        if ($days === null) {
            $custom = trim($currentDueDate);

            return $custom !== '' ? self::parseDateOrToday($custom) : self::addDays($supplyDate, $businessDefaultDays);
        }

        return self::addDays($supplyDate, $days);
    }

    public static function inferTermFromDueDate(
        string $dueDate,
        string $supplyDate,
        int $businessDefaultDays,
    ): string {
        $due = self::parseDateOrToday($dueDate);
        $supply = self::parseDateOrToday($supplyDate);

        foreach ([self::NET_7, self::NET_15, self::NET_30] as $preset) {
            $days = self::netDays($preset, $businessDefaultDays);
            if ($days !== null && $due === self::addDays($supply, $days)) {
                return $preset;
            }
        }

        $defaultDue = self::addDays($supply, $businessDefaultDays);
        if ($due === $defaultDue) {
            return self::defaultCreditTermCode($businessDefaultDays);
        }

        return self::CUSTOM;
    }

    /**
     * @return array<string, mixed>
     */
    public static function issueValidationRules(
        string $paymentTerms,
        string $supplyDate,
        float $grandTotalIncVat,
        float $amountReceived,
        bool $isStandard,
    ): array {
        if (! $isStandard) {
            return [];
        }

        $immediate = self::isImmediate($paymentTerms);
        $received = max(0, $amountReceived);
        $supply = self::parseDateOrToday($supplyDate);

        $rules = [
            'paymentTerms' => ['required', Rule::in(self::codes())],
            'amountReceived' => [
                'nullable',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($grandTotalIncVat): void {
                    if ((float) $value > $grandTotalIncVat + 0.009) {
                        $fail(__('invoices.errors.amount_received_exceeds_total'));
                    }
                },
            ],
        ];

        if ($immediate) {
            $rules['dueDate'] = ['required', 'date', Rule::in([$supply])];
            $rules['paymentMethod'] = [
                'required',
                Rule::in(array_map(fn (\App\Enums\InvoiceArPaymentMethod $m) => $m->value, \App\Enums\InvoiceArPaymentMethod::cases())),
            ];
            if ($received + 0.009 < $grandTotalIncVat) {
                $rules['amountReceived'][] = function (string $attribute, mixed $value, \Closure $fail): void {
                    $fail(__('invoices.errors.cash_terms_full_payment'));
                };
            }
        } else {
            $rules['dueDate'] = ['required', 'date', 'after_or_equal:'.$supply];
            $rules['paymentMethod'] = $received > 0.009
                ? ['required', Rule::in(array_map(fn (\App\Enums\InvoiceArPaymentMethod $m) => $m->value, \App\Enums\InvoiceArPaymentMethod::cases()))]
                : ['nullable', Rule::in(array_map(fn (\App\Enums\InvoiceArPaymentMethod $m) => $m->value, \App\Enums\InvoiceArPaymentMethod::cases()))];
        }

        return $rules;
    }

    public static function label(string $code, ?string $locale = null): string
    {
        $key = match (self::normalizeStorageCode($code)) {
            self::CASH => 'invoices.payment_terms_cash',
            self::NET_7 => 'invoices.payment_terms_net_7',
            self::NET_15 => 'invoices.payment_terms_net_15',
            self::NET_30 => 'invoices.payment_terms_net_30',
            self::CUSTOM => 'invoices.payment_terms_custom',
            default => 'invoices.payment_terms_net_30',
        };

        return __($key, [], $locale);
    }

    private static function parseDateOrToday(string $value): string
    {
        $trim = trim($value);
        if ($trim === '') {
            return now('Asia/Riyadh')->toDateString();
        }

        try {
            return Carbon::parse($trim, 'Asia/Riyadh')->toDateString();
        } catch (\Throwable) {
            return now('Asia/Riyadh')->toDateString();
        }
    }

    private static function addDays(string $from, int $days): string
    {
        return Carbon::parse(self::parseDateOrToday($from), 'Asia/Riyadh')
            ->addDays(max(0, $days))
            ->toDateString();
    }
}