<?php

namespace App\Support\Invoicing;

final class ClassicPartyPrintBlock
{
    /**
     * @param  array<string, mixed>  $t
     * @return list<array{en: string, ar: string, value?: string, html?: string}>
     */
    public static function sellerRows(array $t): array
    {
        $rows = [];

        $nameEn = trim((string) ($t['seller_en'] ?? ''));
        $nameAr = trim((string) ($t['seller_ar'] ?? ''));
        if ($nameEn !== '' || $nameAr !== '') {
            $rows[] = self::nameRow($nameEn, $nameAr);
        }

        if (filled($t['address_en'] ?? null)) {
            $rows[] = ['en' => 'Address', 'ar' => 'العنوان', 'value' => (string) $t['address_en']];
        } elseif (filled($t['address_ar_html'] ?? null)) {
            $rows[] = ['en' => 'Address', 'ar' => 'العنوان', 'html' => (string) $t['address_ar_html']];
        } elseif (filled($t['address_ar'] ?? null)) {
            $rows[] = ['en' => 'Address', 'ar' => 'العنوان', 'value' => (string) $t['address_ar']];
        }

        $na = (array) ($t['national_address'] ?? []);
        $city = trim((string) ($na['city'] ?? ''));
        if ($city !== '') {
            $rows[] = ['en' => 'City', 'ar' => 'المدينة', 'value' => $city];
        }

        $postal = trim((string) ($na['postal_code'] ?? $na['postal'] ?? ''));
        if ($postal !== '') {
            $rows[] = ['en' => 'Postal Code', 'ar' => 'الرمز البريدي', 'value' => $postal];
        }

        $trn = preg_replace('/\D/', '', (string) ($t['trn'] ?? '')) ?? '';
        if ($trn !== '') {
            $rows[] = ['en' => 'Vat Number', 'ar' => 'رقم تسجيل قيمة الضريبة المضافة', 'value' => $trn];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|null  $customer
     * @return list<array{en: string, ar: string, value?: string, html?: string}>
     */
    public static function buyerRows(?array $customer): array
    {
        if ($customer === null || $customer === []) {
            return [];
        }

        $rows = [];
        $nameHtml = self::formatNameCell($customer);
        if ($nameHtml !== '') {
            $rows[] = ['en' => 'Name', 'ar' => 'الاسم', 'html' => $nameHtml];
        }

        $address = trim((string) ($customer['address_print'] ?? $customer['address'] ?? ''));
        if ($address !== '') {
            $rows[] = ['en' => 'Address', 'ar' => 'العنوان', 'value' => $address];
        }

        $city = trim((string) ($customer['city'] ?? ''));
        if ($city !== '') {
            $rows[] = ['en' => 'City', 'ar' => 'المدينة', 'value' => $city];
        }

        $postal = trim((string) ($customer['postal_code'] ?? ''));
        if ($postal !== '') {
            $rows[] = ['en' => 'Postal Code', 'ar' => 'الرمز البريدي', 'value' => $postal];
        }

        $postBox = trim((string) ($customer['post_box'] ?? ''));
        if ($postBox !== '') {
            $rows[] = ['en' => 'Post Box', 'ar' => 'صندوق بريد', 'value' => $postBox];
        }

        $vat = preg_replace('/\D/', '', (string) ($customer['vat_number'] ?? '')) ?? '';
        if ($vat !== '') {
            $rows[] = ['en' => 'Vat Number', 'ar' => 'رقم تسجيل قيمة الضريبة المضافة', 'value' => $vat];
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
            return '<span class="classic-party__name-ar invoice-print-ar" dir="rtl">'.$nameAr.'</span>'
                .'<span class="classic-party__name-en">'.$nameEn.'</span>';
        }

        return $nameAr !== '' ? $nameAr : $nameEn;
    }

    private static function nameRow(string $nameEn, string $nameAr): array
    {
        if ($nameAr !== '' && $nameEn !== '' && $nameAr !== $nameEn) {
            return [
                'en' => 'Name',
                'ar' => 'الاسم',
                'html' => '<span class="classic-party__name-en">'.$nameEn.'</span>'
                    .'<span class="classic-party__name-ar invoice-print-ar" dir="rtl">'.$nameAr.'</span>',
            ];
        }

        $single = $nameAr !== '' ? $nameAr : $nameEn;

        return ['en' => 'Name', 'ar' => 'الاسم', 'value' => $single];
    }
}
