<?php

namespace App\Support\Foundation;

final class FoundationSeo
{
    /**
     * @return array<string, mixed>
     */
    public static function forRequest(string $locale): array
    {
        $title = __('invoicing.landing_meta_title', [], $locale);
        $description = __('invoicing.landing_meta_description', [], $locale);
        $canonical = url()->route('foundation.home');
        if ($locale !== 'en') {
            $canonical .= '?locale='.$locale;
        }
        $ogImage = asset('images/templates/cafe-storefront.webp');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index,follow',
            'og' => [
                'type' => 'website',
                'title' => $title,
                'description' => $description,
                'url' => $canonical,
                'image' => $ogImage,
            ],
        ];
    }
}
