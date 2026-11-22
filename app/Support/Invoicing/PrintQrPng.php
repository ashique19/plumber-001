<?php

namespace App\Support\Invoicing;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Throwable;

final class PrintQrPng
{
    public static function dataUri(?string $tlv): ?string
    {
        if (! filled($tlv)) {
            return null;
        }

        try {
            $result = Builder::create()
                ->writer(new PngWriter)
                ->data($tlv)
                ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
                ->size(360)
                ->margin(4)
                ->build();

            return $result->getDataUri();
        } catch (Throwable) {
            return null;
        }
    }
}
