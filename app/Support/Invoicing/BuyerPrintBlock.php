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

        $nameHtml = self::formatNameCell($customer);
        if ($nameHtml !== '') {
            $rows[] = ['en' => 'Name', 'ar' => 'الاسم', 'html' => $nameHtml];
        }

        $addressLine = trim((string) ($customer['address_line'] ?? $customer['address'] ?? ''));
        if ($addressLine !== '') {
            $rows[] = ['en' => 'Address', 'ar' => 'العنوان', 'value' => $addressLine];
        }

        $city = trim((string) ($customer['city'] ?? ''));
        if ($city !== '') {
            $rows[] = ['en' => 'City', 'ar' => 'المدينة', 'value' => $city];
        }

        $postBox = trim((string) ($customer['post_box'] ?? ''));
        if ($postBox !== '') {
            $rows[] = ['en' => 'Post Box', 'ar' => 'صندوق بريد', 'value' => $postBox];
        }

        $vat = preg_replace('/\D/', '', (string) ($customer['vat_number'] ?? '')) ?? '';
        $vatRegistered = (bool) ($customer['vat_registered'] ?? ($vat !== ''));
        $altType = strtoupper(trim((string) ($customer['alt_id_type'] ?? '')));
        $altNumber = trim((string) ($customer['alt_id_number'] ?? ''));
        $cr = trim((string) ($customer['cr_number'] ?? ''));

        if ($vatRegistered && $vat !== '') {
            $rows[] = [
                'en' => 'VAT Number',
                'ar' => 'رقم تسجيل ضريبة القيمة المضافة',
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
    private static function formatNameCell(array $customer): string
    {
        $nameAr = trim((string) ($customer['name_ar'] ?? ''));
        $nameEn = trim((string) ($customer['name_en'] ?? $customer['name'] ?? ''));

        if ($nameAr === '' && $nameEn === '') {
            return '';
        }

        if ($nameAr !== '' && $nameEn !== '' && $nameAr !== $nameEn) {
            return '<span class="invoice-a4__buyer-name-ar" dir="rtl">'.$nameAr.'</span>'
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