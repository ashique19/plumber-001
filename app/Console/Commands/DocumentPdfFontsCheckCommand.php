<?php

namespace App\Console\Commands;

use App\Support\Invoicing\DocumentPdfFonts;
use Illuminate\Console\Command;

class DocumentPdfFontsCheckCommand extends Command
{
    protected $signature = 'document-pdf:fonts-check';

    protected $description = 'Verify Dompdf invoice fonts are installed under storage/fonts';

    public function handle(): int
    {
        try {
            DocumentPdfFonts::assertReady();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Dompdf invoice fonts OK: '.DocumentPdfFonts::storageDir());

        return self::SUCCESS;
    }
}