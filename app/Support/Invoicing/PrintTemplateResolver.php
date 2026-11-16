<?php

namespace App\Support\Invoicing;

use App\Enums\PrintDocumentTemplate;
use App\Models\BusinessSetting;
use Illuminate\Http\Request;

final class PrintTemplateResolver
{
    public static function forInvoice(BusinessSetting $business, ?Request $request = null): PrintDocumentTemplate
    {
        $override = self::queryOverride($request);
        if ($override !== null) {
            return $override;
        }

        $raw = (string) data_get($business->settings, 'invoicing.invoice_print_template', 'modern');

        return self::parse($raw);
    }

    public static function forQuotation(BusinessSetting $business, ?Request $request = null): PrintDocumentTemplate
    {
        $override = self::queryOverride($request);
        if ($override !== null) {
            return $override;
        }

        $raw = (string) data_get($business->settings, 'invoicing.quotation_print_template', 'modern');

        return self::parse($raw);
    }

    private static function queryOverride(?Request $request): ?PrintDocumentTemplate
    {
        if ($request === null) {
            return null;
        }

        $raw = trim((string) $request->query('template', ''));
        if ($raw === '') {
            return null;
        }

        return self::parse($raw);
    }

    private static function parse(string $raw): PrintDocumentTemplate
    {
        return PrintDocumentTemplate::tryFrom($raw) ?? PrintDocumentTemplate::Modern;
    }
}