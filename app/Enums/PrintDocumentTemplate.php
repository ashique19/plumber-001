<?php

namespace App\Enums;

enum PrintDocumentTemplate: string
{
    case Modern = 'modern';
    case Classic = 'classic';

    public function label(): string
    {
        return match ($this) {
            self::Modern => __('business.print.template_modern'),
            self::Classic => __('business.print.template_classic'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}