<?php

namespace App\Support\Invoicing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosOrder;

final class InvoiceCustomerIdentity
{
    /**
     * @return array{
     *     customer_id: int|null,
     *     name: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     key: string
     * }
     */
    public static function resolve(Invoice $invoice): array
    {
        $payload = $invoice->payload ?? [];
        $customerId = null;

        if ($invoice->source_type === PosOrder::class && $invoice->source_id !== null) {
            $customerId = PosOrder::query()
                ->whereKey($invoice->source_id)
                ->value('customer_id');
        }

        $phone = self::normalizePhone((string) ($payload['buyer_phone'] ?? ''));
        $email = self::normalizeEmail((string) ($payload['buyer_email'] ?? ''));
        $name = trim((string) ($payload['buyer_name'] ?? ''));

        if ($customerId === null && ($phone !== null || $email !== null)) {
            $customerId = Customer::query()
                ->when($phone !== null, fn ($q) => $q->where('phone', $phone))
                ->when($phone === null && $email !== null, fn ($q) => $q->where('email', $email))
                ->value('id');
        }

        if ($customerId !== null) {
            $customer = Customer::query()->find($customerId);
            if ($customer !== null) {
                $name = filled($customer->name) ? $customer->name : $name;
                $phone = filled($customer->phone) ? self::normalizePhone($customer->phone) : $phone;
                $email = filled($customer->email) ? self::normalizeEmail($customer->email) : $email;
            }
        }

        $key = $customerId !== null
          ? 'customer:'.$customerId
          : 'buyer:'.sha1(implode('|', [
              $name,
              $phone ?? '',
              $email ?? '',
          ]));

        return [
            'customer_id' => $customerId !== null ? (int) $customerId : null,
            'name' => $name !== '' ? $name : null,
            'phone' => $phone,
            'email' => $email,
            'key' => $key,
        ];
    }

    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        return $digits;
    }

    public static function normalizeEmail(string $email): ?string
    {
        $email = strtolower(trim($email));

        return $email !== '' ? $email : null;
    }

    public static function displayName(array $identity): string
    {
        if (filled($identity['name'] ?? null)) {
            return (string) $identity['name'];
        }

        if (filled($identity['phone'] ?? null)) {
            return (string) $identity['phone'];
        }

        if (filled($identity['email'] ?? null)) {
            return (string) $identity['email'];
        }

        return __('payments.unknown_customer');
    }
}
