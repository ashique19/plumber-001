<?php

namespace Tests\Support;

/**
 * Seller block the faked Z-Heart sign endpoint returns. Null mimics an older Z-Heart
 * that did not return one. Placeholder values only.
 */
final class ZHeartFakeSeller
{
    /** @var array<string, mixed>|null */
    public static ?array $block = null;

    /** @return array<string, mixed> */
    public static function sample(): array
    {
        return [
            'name' => 'ZH Plumbing Placeholder LLC',
            'name_ar' => 'شركة زد هارت للسباكة التجريبية',
            'name_en' => 'ZH Plumbing Placeholder LLC',
            'vat_number' => '399999999900003',
            'id_scheme' => 'CRN',
            'id' => '1010010000',
            'cr_number' => '1010010000',
            'address' => [
                'building' => '7243',
                'street' => 'King Fahd Rd',
                'additional' => '1234',
                'district' => 'Al Olaya',
                'city' => 'Riyadh',
                'postal_code' => '12211',
                'country' => 'SA',
            ],
            'address_formatted' => '7243, King Fahd Rd, Al Olaya, Riyadh, 12211, Addl. 1234',
            'branch' => 'Main branch',
            'source' => 'z-heart',
        ];
    }
}
