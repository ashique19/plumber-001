<?php

namespace App\Support\Invoicing;

final class BuyerPrintBlock
{
    /**
     * @param  array<string, mixed>|null  $customer
     * @return list<array{en: string, ar: string, value?: string, html?: string}>
     */
    public static function rows(?array $customer): array
    {
        if ($customer === null || $customer === []) {
            return [];
        }

        $rows = [];

        $nameHtml = self::formatCustomerCell($customer);
        if ($nameHtml !== '') {
            $rows[] = ['en' => 'Customer', 'ar' => 'العميل', 'html' => $nameHtml];
        }

        $address = self::formatAddressLine($customer);
        if ($address !== '') {
            $rows[] = ['en' => 'Address', 'ar' => 'العنوان', 'value' => $address];
        }

        $email = trim((string) ($customer['email'] ?? ''));
        if ($email !== '') {
            $rows[] = ['en' => 'Email', 'ar' => 'البريد الإلكتروني', 'value' => $email];
        }

        $phone = BuyerPhonePrint::format($customer['phone'] ?? null);
        if ($phone !== null && $phone !== '') {
            $rows[] = ['en' => 'Phone', 'ar' => 'الهاتف', 'value' => $phone];
        }

        $vat = preg_replace('/\D/', '', (string) ($customer['vat_number'] ?? '')) ?? '';
        $vatRegistered = (bool) ($customer['vat_registered'] ?? ($vat !== ''));
        $altType = strtoupper(trim((string) ($customer['alt_id_type'] ?? '')));
        $altNumber = trim((string) ($customer['alt_id_number'] ?? ''));
        $cr = trim((string) ($customer['cr_number'] ?? ''));

        if ($vatRegistered && $vat !== '') {
            $rows[] = [
                'en' => 'VAT number',
                'ar' => 'رقم التسجيل الضريبي',
                'value' => $vat,
            ];
        } elseif ($altType !== '' && $altNumber !== '') {
            $rows[] = [
                'en' => self::altIdLabelEn($altType),
                'ar' => self::altIdLabelAr($altType),
                'value' => $altNumber,
            ];
        } elseif ($cr !== '') {
            $rows[] = [
                'en' => self::altIdLabelEn('CRN'),
                'ar' => self::altIdLabelAr('CRN'),
                'value' => $cr,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private static function formatAddressLine(array $customer): string
    {
        $prepared = trim((string) ($customer['address_print'] ?? ''));
        if ($prepared !== '') {
            return $prepared;
        }

        $addr = (array) ($customer['address_parts'] ?? []);
        $parts = array_values(array_filter([
            trim((string) ($addr['building'] ?? '')),
            trim((string) ($addr['street'] ?? '')),
            trim((string) ($addr['district'] ?? '')),
            trim((string) ($addr['city'] ?? $customer['city'] ?? '')),
            trim((string) ($addr['postal_code'] ?? $addr['postal'] ?? $customer['postal_code'] ?? '')),
            CountryPrintName::english(
                (string) ($customer['country_code'] ?? $addr['country'] ?? 'SA'),
            ),
        ], fn (string $part): bool => $part !== ''));

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return trim((string) ($customer['address'] ?? $customer['address_line'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private static function formatCustomerCell(array $customer): string
    {
        $nameAr = trim((string) ($customer['name_ar'] ?? ''));
        $nameEn = trim((string) ($customer['name_en'] ?? $customer['name'] ?? ''));

        if ($nameAr === '' && $nameEn === '') {
            return '';
        }

        if ($nameAr !== '' && $nameEn !== '' && $nameAr !== $nameEn) {
            return '<span class="invoice-a4__buyer-name-ar invoice-print-ar" dir="rtl">'.$nameAr.'</span>'
                .'<span class="invoice-a4__buyer-name-en">'.$nameEn.'</span>';
        }

        return $nameAr !== '' ? $nameAr : $nameEn;
    }

    private static function altIdLabelEn(string $type): string
    {
        $type = strtoupper($type);

        return match ($type) {
            'CRN' => 'CR Number',
            'NAT' => 'National ID Number',
            'IQA' => 'Iqama Number',
            'PAS' => 'Passport Number',
            'GCC' => 'GCC ID Number',
            default => $type !== '' ? $type.' Number' : 'ID Number',
        };
    }

    private static function altIdLabelAr(string $type): string
    {
        $type = strtoupper($type);

        return match ($type) {
            'CRN' => 'رقم السجل التجاري',
            'NAT' => 'رقم الهوية الوطنية',
            'IQA' => 'رقم الإقامة',
            'PAS' => 'رقم جواز السفر',
            'GCC' => 'رقم هوية مجلس التعاون',
            default => $type !== '' ? 'رقم '.$type : 'رقم الهوية',
        };
    }
}