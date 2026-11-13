<?php

namespace App\Support\Invoicing;

use App\Enums\ProductItemType;

final class InvoiceLineGrouper
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{uses_groups: bool, sections: list<array{key: string, title: string, title_ar: string, lines: list<array<string, mixed>>}>}
     */
    public static function sectionsForDisplay(array $lines, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $services = [];
        $parts = [];
        $other = [];

        foreach ($lines as $index => $line) {
            $line['_index'] = $index;
            $type = ProductItemType::tryFrom((string) ($line['item_type'] ?? ProductItemType::Item->value))
                ?? ProductItemType::Item;

            match ($type) {
                ProductItemType::Service => $services[] = $line,
                ProductItemType::Part => $parts[] = $line,
                default => $other[] = $line,
            };
        }

        $usesGroups = $services !== [] && $parts !== [];

        if (! $usesGroups) {
            $indexed = [];
            foreach ($lines as $index => $line) {
                $line['_index'] = $index;
                $indexed[] = $line;
            }

            return [
                'uses_groups' => false,
                'sections' => [
                    [
                        'key' => 'all',
                        'title' => '',
                        'title_ar' => '',
                        'lines' => $indexed,
                    ],
                ],
            ];
        }

        $sections = [];

        if ($services !== []) {
            $sections[] = [
                'key' => 'services',
                'title' => __('pos.line_group_services'),
                'title_ar' => __('pos.line_group_services_ar'),
                'lines' => $services,
            ];
        }

        if ($parts !== []) {
            $sections[] = [
                'key' => 'parts',
                'title' => __('pos.line_group_parts'),
                'title_ar' => __('pos.line_group_parts_ar'),
                'lines' => $parts,
            ];
        }

        if ($other !== []) {
            $sections[] = [
                'key' => 'other',
                'title' => __('pos.line_group_other'),
                'title_ar' => __('pos.line_group_other_ar'),
                'lines' => $other,
            ];
        }

        return [
            'uses_groups' => true,
            'sections' => $sections,
        ];
    }

    public static function sectionHeading(array $section, string $locale): string
    {
        return $locale === 'ar' && ($section['title_ar'] ?? '') !== ''
            ? (string) $section['title_ar']
            : (string) ($section['title'] ?? '');
    }
}
