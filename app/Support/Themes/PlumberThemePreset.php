<?php

namespace App\Support\Themes;

use App\Models\BusinessSetting;

class PlumberThemePreset
{
    public static function settings(string $nameEn, string $nameAr, string $locale = 'en'): array
    {
        $base = BusinessSetting::defaultSettings();

        $base['default_locale'] = $locale;
        $base['theme'] = [
            'layout_key' => 'plumber',
            'logo_url' => null,
            'colors' => [
                'navy' => '#0B1F3A',
                'steel' => '#2D4A6F',
                'sky' => '#E8F1FA',
                'ice' => '#F4F8FC',
                'safety' => '#E87722',
                'accent' => '#1B8A8A',
                'slate' => '#475569',
            ],
            'font' => 'Instrument Sans',
            'font_ar' => 'Tajawal',
        ];

        $base['invoicing']['default_document_type'] = 'simplified';

        $base['legal_entity'] = [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'vat_number' => '311122393500004',
            'cr_number' => '1010987654',
            'address' => 'Al Olaya Street, Building 12, Riyadh 12211, Kingdom of Saudi Arabia',
            'address_ar' => 'شارع العليا، مبنى ١٢، الرياض ١٢٢١١، المملكة العربية السعودية',
        ];

        $base['content']['plumber'] = self::demoPlumberContent($nameEn, $nameAr);

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public static function demoPlumberContent(string $nameEn, string $nameAr): array
    {
        $contactSlug = str_replace('-', '', \Illuminate\Support\Str::slug($nameEn));

        return [
            'tagline_en' => 'Licensed plumbing & water systems across Riyadh',
            'tagline_ar' => 'سباكة مرخّصة وأنظمة مياه في الرياض',
            'about_en' => "{$nameEn} provides residential and light commercial plumbing — leaks, heaters, drain clearing, and preventative maintenance. Our technicians arrive on time with clear quotes before work begins.",
            'about_ar' => "تقدّم {$nameAr} خدمات سباكة للمنازل والمنشآت الصغيرة — تسريبات، سخانات، تسليك مجاري وصيانة وقائية. فنّيونا يلتزمون بالمواعيد مع عرض سعر واضح قبل البدء.",
            'hours' => [
                ['label_en' => 'Sat – Thu', 'label_ar' => 'السبت – الخميس', 'value_en' => '8:00 AM – 9:00 PM', 'value_ar' => '٨:٠٠ ص – ٩:٠٠ م'],
                ['label_en' => 'Friday', 'label_ar' => 'الجمعة', 'value_en' => '2:00 PM – 9:00 PM', 'value_ar' => '٢:٠٠ م – ٩:٠٠ م'],
                ['label_en' => 'Emergency line', 'label_ar' => 'طوارئ', 'value_en' => '24/7 dispatch', 'value_ar' => 'استقبال على مدار الساعة'],
            ],
            'location' => [
                'map_placeholder' => true,
                'lat' => 24.7136,
                'lng' => 46.6753,
            ],
            'contact' => [
                'phone' => '+966 11 200 4472',
                'whatsapp' => '+966500000447',
                'email' => "service@{$contactSlug}.sa",
            ],
            'trust_points' => [
                ['icon' => 'shield', 'title_en' => 'Licensed technicians', 'title_ar' => 'فنيون مرخّصون', 'body_en' => 'Municipal-compliant workmanship with photo documentation.', 'body_ar' => 'تنفيذ متوافق مع الأنظمة مع توثيق بالصور.'],
                ['icon' => 'clock', 'title_en' => 'Same-day visits', 'title_ar' => 'زيارات في نفس اليوم', 'body_en' => 'Most jobs scheduled within 4 hours in Riyadh.', 'body_ar' => 'معظم الطلبات خلال ٤ ساعات داخل الرياض.'],
                ['icon' => 'receipt', 'title_en' => 'Clear SAR pricing', 'title_ar' => 'أسعار واضحة بالريال', 'body_en' => 'Itemised quotes; VAT invoices available for businesses.', 'body_ar' => 'عروض أسعار مفصّلة وفواتير ضريبية للمنشآت.'],
            ],
            'materials_note_en' => 'Parts and small materials used on your visit are itemised on the job invoice—not listed as a public hardware catalog.',
            'materials_note_ar' => 'القطع والمواد الصغيرة المستخدمة في الزيارة تُفصَّل في فاتورة العمل—وليست متجرًا عامًا للتجهيزات.',
            'featured_services' => [
                ['key' => 'leak-repair', 'badge_en' => 'Popular', 'badge_ar' => 'الأكثر طلباً'],
                ['key' => 'drain-clear', 'badge_en' => 'Fast', 'badge_ar' => 'سريع'],
                ['key' => 'water-heater', 'badge_en' => 'Install', 'badge_ar' => 'تركيب'],
            ],
            'service_categories' => [
                [
                    'slug' => 'emergency',
                    'name_en' => 'Emergency',
                    'name_ar' => 'طوارئ',
                    'items' => [
                        ['slug' => 'leak-repair', 'name_en' => 'Leak repair visit', 'name_ar' => 'زيارة إصلاح تسرب', 'desc_en' => 'Diagnose and patch active leaks', 'desc_ar' => 'تشخيص ومعالجة التسربات النشطة', 'price_sar' => 180.00],
                        ['slug' => 'burst-pipe', 'name_en' => 'Burst pipe isolation', 'name_ar' => 'عزل أنبوب منفجر', 'desc_en' => 'Shut-off and temporary repair', 'desc_ar' => 'إغلاق المياه وإصلاح مؤقت', 'price_sar' => 250.00],
                    ],
                ],
                [
                    'slug' => 'maintenance',
                    'name_en' => 'Maintenance',
                    'name_ar' => 'صيانة',
                    'items' => [
                        ['slug' => 'drain-clear', 'name_en' => 'Drain clearing', 'name_ar' => 'تسليك مجاري', 'desc_en' => 'Kitchen or bathroom line', 'desc_ar' => 'مطبخ أو حمّام', 'price_sar' => 140.00],
                        ['slug' => 'fixture-service', 'name_en' => 'Fixture tune-up', 'name_ar' => 'صيانة تجهيزات', 'desc_en' => 'Taps, mixers, seals', 'desc_ar' => 'محابس ومفاصل وأختام', 'price_sar' => 95.00],
                    ],
                ],
                [
                    'slug' => 'install',
                    'name_en' => 'Installation',
                    'name_ar' => 'تركيب',
                    'items' => [
                        ['slug' => 'water-heater', 'name_en' => 'Water heater install', 'name_ar' => 'تركيب سخان مياه', 'desc_en' => 'Supply and labour, standard unit', 'desc_ar' => 'توريد وتركيب وحدة قياسية', 'price_sar' => 420.00],
                        ['slug' => 'filtration', 'name_en' => 'Filtration system', 'name_ar' => 'نظام تنقية', 'desc_en' => 'Under-sink carbon block', 'desc_ar' => 'فلتر تحت الحوض', 'price_sar' => 320.00],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function findServiceItem(array $content, string $key): ?array
    {
        foreach ($content['service_categories'] ?? [] as $category) {
            foreach ($category['items'] ?? [] as $item) {
                if (($item['slug'] ?? '') === $key) {
                    return $item + ['category' => $category];
                }
            }
        }

        return null;
    }
}
