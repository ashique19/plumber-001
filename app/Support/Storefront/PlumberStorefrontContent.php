<?php

namespace App\Support\Storefront;

use App\Models\BusinessSetting;
use App\Support\Themes\PlumberThemePreset;

final class PlumberStorefrontContent
{
    /**
     * @var array<string, array{desc_en: string, desc_ar: string, detail_en: string, detail_ar: string}>
     */
    private const SERVICE_COPY = [
        'leak-repair' => [
            'desc_en' => 'Diagnose active leaks under sinks, walls, or supply lines.',
            'desc_ar' => 'تشخيص التسريبات النشطة تحت الأحواض أو الجدران أو خطوط التغذية.',
            'detail_en' => 'Includes shut-off check, moisture tracing, and patch or gasket repair when parts are on the truck. New piping or full replacements are quoted before work begins.',
            'detail_ar' => 'يشمل فحص الإغلاق وتتبع الرطوبة وإصلاحًا مؤقتًا أو قطعًا متوفرة على الشاحنة. تُعرض أسعار الاستبدال الكامل قبل البدء.',
        ],
        'burst-pipe' => [
            'desc_en' => 'Emergency isolation for burst or cracked supply lines.',
            'desc_ar' => 'عزل طارئ للأنابيب المنفجرة أو المتشققة.',
            'detail_en' => 'Technician secures the water source, removes standing water risk, and performs a temporary repair until permanent parts are sourced.',
            'detail_ar' => 'يؤمّن الفني مصدر المياه ويقلل خطر التجمع ثم ينفّذ إصلاحًا مؤقتًا حتى تتوفر القطع الدائمة.',
        ],
        'drain-clear' => [
            'desc_en' => 'Clear slow or blocked kitchen and bathroom drains.',
            'desc_ar' => 'تسليك مجاري المطبخ والحمام البطيئة أو المسدودة.',
            'detail_en' => 'Cable or handheld tools for typical buildup; camera scope quoted separately if main line access is required.',
            'detail_ar' => 'أدوات يدوية أو كابل للانسدادات المعتادة؛ فحص الكاميرا يُسعّر بشكل منفصل عند الحاجة لخط رئيسي.',
        ],
        'fixture-service' => [
            'desc_en' => 'Tune-up for taps, mixers, showers, and shut-off valves.',
            'desc_ar' => 'صيانة للمحابس والخلاطات والاستحمام وصمامات الإغلاق.',
            'detail_en' => 'Cartridge swaps, seal replacement, and pressure checks. Fixture supply is itemised if new hardware is requested.',
            'detail_ar' => 'استبدال أقراص واختام وفحص ضغط. تُفصّل تكلفة التجهيزات الجديدة عند الطلب.',
        ],
        'water-heater' => [
            'desc_en' => 'Install or replace standard residential water heaters.',
            'desc_ar' => 'تركيب أو استبدال سخانات المياه السكنية القياسية.',
            'detail_en' => 'Labour for a typical wall or closet unit; electrical or gas connections follow municipal requirements and are confirmed on-site.',
            'detail_ar' => 'أجرة تركيب لوحدة قياسية؛ التوصيلات الكهربائية أو الغازية حسب اشتراطات البلدية وتُؤكد في الموقع.',
        ],
        'filtration' => [
            'desc_en' => 'Under-sink and point-of-use filtration installs.',
            'desc_ar' => 'تركيب أنظمة تنقية تحت الحوض ونقاط الاستخدام.',
            'detail_en' => 'Mounting, tubing, and leak test for carbon-block kits we carry; filter cartridges are itemised on the invoice.',
            'detail_ar' => 'تثبيت ووصلات واختبار تسرب لمجموعات الفلتر؛ تُفصّل أسعار قواطع الفلتر في الفاتورة.',
        ],
        'leak-detection' => [
            'desc_en' => 'Electronic leak detection with minimal opening where possible.',
            'desc_ar' => 'كشف تسربات إلكتروني مع تقليل التكسير حيث أمكن.',
            'detail_en' => 'Moisture meters and acoustic tools to locate hidden leaks in walls, floors, or tanks before invasive repairs are quoted.',
            'detail_ar' => 'أجهزة رطوبة وصوتية لتحديد التسربات الخفية في الجدران أو الأرضيات أو الخزانات قبل عرض أعمال التكسير.',
        ],
        'water-tank' => [
            'desc_en' => 'Rooftop and ground tank cleaning, repair, and install.',
            'desc_ar' => 'تنظيف وصيانة وتركيب خزانات المياه العلوية والأرضية.',
            'detail_en' => 'Includes float valve checks, inlet/outlet seals, and hygiene rinse for residential tanks—labour visit; tank hardware itemised if replaced.',
            'detail_ar' => 'يشمل فحص عوامة المياه وأختام التغذية والتفريغ وغسل الخزانات السكنية—زيارة عمل؛ تُفصّل قطع الخزان عند الاستبدال.',
        ],
        'water-pump' => [
            'desc_en' => 'Booster pump (dynamo) install, repair, and pressure tuning.',
            'desc_ar' => 'تركيب وصيانة مضخات رفع الضغط وضبط التدفق.',
            'detail_en' => 'Diagnose low pressure, coupling leaks, and controller faults; replacement pumps quoted after on-site sizing.',
            'detail_ar' => 'تشخيص ضعف الضغط وتسربات الوصلات وأعطال المنظم؛ تُعرض أسعار المضخات البديلة بعد القياس في الموقع.',
        ],
        'ppr-piping' => [
            'desc_en' => 'New water lines and PPR pipe replacement.',
            'desc_ar' => 'تمديد شبكات المياه واستبدال أنابيب PPR.',
            'detail_en' => 'Hot and cold branches for kitchens, bathrooms, and manifolds—press-fit or fusion joints per site conditions.',
            'detail_ar' => 'تمديدات المياه الساخنة والباردة للمطابخ والحمامات ولوحات التوزيع—وصلات حسب حالة الموقع.',
        ],
        'sanitary-fixtures' => [
            'desc_en' => 'Toilet, basin, mixer, and shattaf installation.',
            'desc_ar' => 'تركيب مراحيض وأحواض وخلاطات وشطافات.',
            'detail_en' => 'Remove old fixtures, reset flanges, and test for leaks; customer-supplied models welcomed with serials noted on the job sheet.',
            'detail_ar' => 'إزالة التجهيزات القديمة وإعادة تثبيت الفلانشات واختبار التسرب؛ نرحب بتجهيزات العميل مع تسجيلها في أوراق العمل.',
        ],
        'water-heater-maint' => [
            'desc_en' => 'Electric and storage heater maintenance.',
            'desc_ar' => 'صيانة السخانات الكهربائية والمركزية.',
            'detail_en' => 'Element checks, thermostat calibration, anode inspection, and safety valve tests—parts billed when elements or valves are replaced.',
            'detail_ar' => 'فحص السخان والثرموستات والأنود وصمام الأمان—تُفصّل القطع عند استبدال السخان أو الصمامات.',
        ],
        'sewer-jetting' => [
            'desc_en' => 'Sewer and main line jetting beyond basic drain clearing.',
            'desc_ar' => 'تسليك خطوط الصرف الرئيسية بضغط المياه.',
            'detail_en' => 'High-pressure jetting for stubborn blockages; camera inspection quoted separately if routing is unknown.',
            'detail_ar' => 'ضخ مائي للانسدادات العنيدة؛ فحص الكاميرا يُسعّر منفصلًا عند مجهولية المسار.',
        ],
        'preventive-maintenance' => [
            'desc_en' => 'Scheduled preventive maintenance visit.',
            'desc_ar' => 'زيارة صيانة وقائية مجدولة.',
            'detail_en' => 'Multi-point check of visible supply lines, shut-offs, traps, and heater safety—ideal for villas and small clinics.',
            'detail_ar' => 'فحص متعدد لخطوط التغذية وصمامات الإغلاق والمصائد وأمان السخان—مناسب للفلل والعيادات الصغيرة.',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function enrich(BusinessSetting $business, array $stored): array
    {
        $legal = data_get($business->settings, 'legal_entity', []);
        $nameEn = (string) ($legal['name_en'] ?? $business->name);
        $nameAr = (string) ($legal['name_ar'] ?? $business->name);
        $canonical = PlumberThemePreset::demoPlumberContent($nameEn, $nameAr);

        $merged = array_replace_recursive($canonical, $stored);

        $merged['service_categories'] = self::appendExtendedServices(self::mergeServiceCategories(
            $canonical['service_categories'] ?? [],
            $stored['service_categories'] ?? [],
        ));

        foreach (self::marketingBlocks() as $key => $value) {
            if (self::shouldUseStoredMarketingBlock($key, $stored)) {
                continue;
            }
            $merged[$key] = $value;
        }

        return BusinessContentMirror::overlay($business, $merged);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private static function shouldUseStoredMarketingBlock(string $key, array $stored): bool
    {
        if (! array_key_exists($key, $stored)) {
            return false;
        }

        $block = $stored[$key];
        if ($block === null || $block === []) {
            return false;
        }

        if ($key === 'service_area' && is_array($block) && ! empty($block['managed'])) {
            return true;
        }

        return false;
    }

    /**
     * Storefront-only marketing (not persisted in tenant settings or SQL seeds).
     *
     * @return array<string, mixed>
     */
    private static function marketingBlocks(): array
    {
        return [
            'how_it_works' => [
                [
                    'step' => 1,
                    'title_en' => 'Book dispatch',
                    'title_ar' => 'احجز الزيارة',
                    'body_en' => 'Call, WhatsApp, or email with your address and issue. We confirm a visit window and assign a licensed technician.',
                    'body_ar' => 'اتصل أو راسلنا برقمك وعنوانك ووصف المشكلة. نؤكد موعد الزيارة ونعيّن فنيًا مرخّصًا.',
                ],
                [
                    'step' => 2,
                    'title_en' => 'On-site visit & quote',
                    'title_ar' => 'زيارة ميدانية وعرض سعر',
                    'body_en' => 'Your technician diagnoses the fault, documents the area with photos, and quotes labour plus any parts before non-emergency work starts.',
                    'body_ar' => 'يشخّص الفني العطل ويوثق الموقع بالصور ويعرض أجرة العمل وأي قطع قبل بدء الأعمال غير الطارئة.',
                ],
                [
                    'step' => 3,
                    'title_en' => 'Itemised invoice',
                    'title_ar' => 'فاتورة مفصّلة',
                    'body_en' => 'Visit labour and parts used on the job appear as separate lines. VAT invoices are available for qualifying business customers.',
                    'body_ar' => 'تظهر أجرة الزيارة والقطع المستخدمة في الفاتورة كبنود منفصلة. فواتير ضريبية متاحة للمنشآت المؤهلة.',
                ],
            ],
            'faq' => [
                [
                    'q_en' => 'How fast can you reach me?',
                    'q_ar' => 'ما مدى سرعة الوصول؟',
                    'a_en' => 'Most Riyadh visits are scheduled within four hours during Sat–Thu 8:00 AM–9:00 PM and Friday 2:00 PM–9:00 PM. The emergency line runs 24/7 for active leaks and burst pipes.',
                    'a_ar' => 'معظم زيارات الرياض تُحدد خلال أربع ساعات خلال أوقات العمل. خط الطوارئ يعمل 24/7 للتسريبات والانفجارات النشطة.',
                ],
                [
                    'q_en' => 'What does the visit price include?',
                    'q_ar' => 'ماذا تتضمن أسعار الزيارة؟',
                    'a_en' => 'Listed visit fees cover diagnosis and labour for the described scope. Consumables, fixtures, and replacement hardware are itemised on the job invoice—not on this public list.',
                    'a_ar' => 'تغطي رسوم الزيارة التشخيص والعمل ضمن النطاق الموضح. المواد والتجهيزات تُفصّل في فاتورة العمل وليس في هذه القائمة العامة.',
                ],
                [
                    'q_en' => 'Are prices shown with VAT?',
                    'q_ar' => 'هل الأسعار المعروضة تشمل الضريبة؟',
                    'a_en' => 'Amounts on this demo site exclude 15% VAT. Your technician confirms the total before work; businesses may receive VAT-compliant invoices when applicable.',
                    'a_ar' => 'المبالغ على هذا العرض التجريبي غير شاملة لضريبة 15٪. يؤكد الفني الإجمالي قبل العمل؛ ويمكن إصدار فواتير ضريبية للمنشآت عند الاستحقاق.',
                ],
                [
                    'q_en' => 'Which areas do you serve?',
                    'q_ar' => 'ما نطاق الخدمة؟',
                    'a_en' => 'We dispatch across central, north, east, and west Riyadh districts from our Al Olaya hub. Farther sites may be booked by appointment—demo marketing coverage only.',
                    'a_ar' => 'نغطي وسط وشمال وشرق وغرب الرياض من مركز العليا. المواقع البعيدة بالموعد—تغطية تسويقية تجريبية فقط.',
                ],
            ],
            'service_area' => [
                'title_en' => 'Riyadh service area',
                'title_ar' => 'نطاق الخدمة في الرياض',
                'intro_en' => 'Same-day crews rotate across major districts. This demo list is illustrative—not a municipal license boundary.',
                'intro_ar' => 'فرق الطوارئ تنتشر في الأحياء الرئيسية. هذه القائمة توضيحية وليست حدود ترخيص بلدي.',
                'areas_en' => ['Al Olaya & King Fahd Road', 'Al Malaz & Al Batha', 'Al Nakheel & Diplomatic Quarter', 'Al Narjis & North Riyadh', 'Al Shifa & East Ring'],
                'areas_ar' => ['العليا وطريق الملك فهد', 'الملز والبطحاء', 'النخيل والحي الدبلوماسي', 'النرجس وشمال الرياض', 'الشفاء والدائرة الشرقية'],
            ],
            'about_story_en' => 'We combine dispatch scheduling with on-site documentation so home owners and light-commercial sites know what was found, what was fixed, and what was replaced. Every job note links labour lines to parts used—ready for your VAT invoice when you qualify.',
            'about_story_ar' => 'نجمع بين جدولة الزيارات والتوثيق الميداني حتى يعرف أصحاب المنازل والمنشآت الخفيفة ما تم تشخيصه وإصلاحه واستبداله. تربط ملاحظات كل زيارة العمل بالقطع المستخدمة—استعدادًا لفاتورتك الضريبية عند الاستحقاق.',
            'demo_quote_en' => '“Clear quotes before work, tidy finish, and an invoice that matched what we agreed.” — Demo marketing line only, not a verified customer.',
            'demo_quote_ar' => '«عروض أسعار واضحة قبل العمل، وتسليم منظم، وفاتورة مطابقة لما اتفقنا عليه.» — سطر تسويقي تجريبي وليس عميلًا موثّقًا.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $canonical
     * @param  list<array<string, mixed>>  $stored
     * @return list<array<string, mixed>>
     */
    private static function mergeServiceCategories(array $canonical, array $stored): array
    {
        $source = $stored !== [] ? $stored : $canonical;
        $richBySlug = self::SERVICE_COPY;

        foreach ($source as &$category) {
            foreach ($category['items'] ?? [] as &$item) {
                $slug = (string) ($item['slug'] ?? '');
                if ($slug === '' || ! isset($richBySlug[$slug])) {
                    continue;
                }
                $rich = $richBySlug[$slug];
                $item['desc_en'] = $rich['desc_en'];
                $item['desc_ar'] = $rich['desc_ar'];
                $item['detail_en'] = $rich['detail_en'];
                $item['detail_ar'] = $rich['detail_ar'];
            }
        }

        return $source;
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     * @return list<array<string, mixed>>
     */
    private static function appendExtendedServices(array $categories): array
    {
        $existingSlugs = [];
        foreach ($categories as $category) {
            foreach ($category['items'] ?? [] as $item) {
                $existingSlugs[(string) ($item['slug'] ?? '')] = true;
            }
        }

        $richBySlug = self::SERVICE_COPY;

        foreach (self::extendedServicePatches() as $patch) {
            $item = $patch['item'];
            $slug = (string) ($item['slug'] ?? '');
            if ($slug === '' || isset($existingSlugs[$slug])) {
                continue;
            }

            if (isset($richBySlug[$slug])) {
                $item = array_merge($item, $richBySlug[$slug]);
            }

            $categorySlug = (string) ($patch['category_slug'] ?? '');
            $categoryIndex = null;
            foreach ($categories as $index => $category) {
                if (($category['slug'] ?? '') === $categorySlug) {
                    $categoryIndex = $index;
                    break;
                }
            }

            if ($categoryIndex === null) {
                $categories[] = [
                    'slug' => $categorySlug,
                    'name_en' => (string) ($patch['category_name_en'] ?? 'Services'),
                    'name_ar' => (string) ($patch['category_name_ar'] ?? 'خدمات'),
                    'items' => [$item],
                ];
            } else {
                $categories[$categoryIndex]['items'][] = $item;
            }

            $existingSlugs[$slug] = true;
        }

        return $categories;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function extendedServicePatches(): array
    {
        return [
            [
                'category_slug' => 'emergency',
                'item' => [
                    'slug' => 'leak-detection',
                    'name_en' => 'Hidden leak detection',
                    'name_ar' => 'كشف تسربات بدون تكسير',
                    'price_sar' => 220.00,
                ],
            ],
            [
                'category_slug' => 'maintenance',
                'item' => [
                    'slug' => 'water-tank',
                    'name_en' => 'Water tank service',
                    'name_ar' => 'تنظيف وصيانة خزانات المياه',
                    'price_sar' => 260.00,
                ],
            ],
            [
                'category_slug' => 'maintenance',
                'item' => [
                    'slug' => 'water-pump',
                    'name_en' => 'Booster pump service',
                    'name_ar' => 'تركيب وصيانة مضخات المياه',
                    'price_sar' => 210.00,
                ],
            ],
            [
                'category_slug' => 'maintenance',
                'item' => [
                    'slug' => 'water-heater-maint',
                    'name_en' => 'Water heater maintenance',
                    'name_ar' => 'صيانة السخانات',
                    'price_sar' => 150.00,
                ],
            ],
            [
                'category_slug' => 'maintenance',
                'item' => [
                    'slug' => 'sewer-jetting',
                    'name_en' => 'Sewer line jetting',
                    'name_ar' => 'تسليك خطوط الصرف',
                    'price_sar' => 280.00,
                ],
            ],
            [
                'category_slug' => 'maintenance',
                'item' => [
                    'slug' => 'preventive-maintenance',
                    'name_en' => 'Preventive maintenance visit',
                    'name_ar' => 'زيارة صيانة وقائية',
                    'price_sar' => 175.00,
                ],
            ],
            [
                'category_slug' => 'install',
                'item' => [
                    'slug' => 'ppr-piping',
                    'name_en' => 'Water line / PPR install',
                    'name_ar' => 'تمديد شبكات المياه',
                    'price_sar' => 380.00,
                ],
            ],
            [
                'category_slug' => 'install',
                'item' => [
                    'slug' => 'sanitary-fixtures',
                    'name_en' => 'Sanitary fixture install',
                    'name_ar' => 'تركيب الأدوات الصحية',
                    'price_sar' => 165.00,
                ],
            ],
        ];
    }
}
