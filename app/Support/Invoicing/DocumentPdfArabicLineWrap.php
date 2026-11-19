<?php

namespace App\Support\Invoicing;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class DocumentPdfArabicLineWrap
{
    /**
     * CSS class => max characters per line at typical PDF font sizes.
     *
     * @var array<string, int>
     */
    private const CLASS_BUDGETS = [
        'invoice-a4__desc-ar' => 34,
        'invoice-a4__buyer-name-ar' => 38,
        'invoice-print-words-block__ar' => 58,
        'invoice-a4__footer-ar' => 40,
        'print-tpl-modern__footer-name-ar' => 40,
    ];

    public static function apply(string $html): string
    {
        if (! preg_match('/\p{Arabic}/u', $html)) {
            return $html;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        foreach (self::CLASS_BUDGETS as $class => $maxChars) {
            $query = '//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]';
            foreach ($xpath->query($query) ?: [] as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                if ($node->getElementsByTagName('*')->length > 0) {
                    continue;
                }

                $text = trim($node->textContent ?? '');
                if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
                    continue;
                }

                if ($class === 'invoice-print-words-block__ar') {
                    $wrapped = ArabicLineBreaker::breakForPdfBlock($text, $maxChars, 2);
                    $lines = $wrapped['lines'];
                    $compact = $wrapped['compact'];
                } else {
                    $lines = ArabicLineBreaker::breakLines($text, $maxChars);
                    $compact = false;
                }

                if (count($lines) <= 1) {
                    continue;
                }

                self::replaceWithPrelines($dom, $node, $lines, $compact);
            }
        }

        foreach ($xpath->query('//td[contains(@class,"col-desc")]//div[contains(@class,"invoice-print-ar") and not(contains(@class,"invoice-a4__desc-ar"))]') ?: [] as $node) {
            if (! $node instanceof DOMElement || $node->getElementsByTagName('*')->length > 0) {
                continue;
            }
            $text = trim($node->textContent ?? '');
            if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
                continue;
            }
            $lines = ArabicLineBreaker::breakLines($text, 34);
            if (count($lines) <= 1) {
                continue;
            }
            self::replaceWithPrelines($dom, $node, $lines, false);
        }

        $out = $dom->saveHTML();

        return is_string($out) ? $out : $html;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function replaceWithPrelines(DOMDocument $dom, DOMElement $node, array $lines, bool $compact): void
    {
        $existingClass = trim($node->getAttribute('class'));
        if ($compact) {
            $existingClass = trim($existingClass.' invoice-pdf-ar-lines--compact');
        }
        $dir = $node->getAttribute('dir');
        $lang = $node->getAttribute('lang');

        $container = strtolower($node->tagName) === 'p'
            ? $dom->createElement('div')
            : $node;

        if ($container !== $node) {
            $container->setAttribute('class', $existingClass);
            if ($dir !== '') {
                $container->setAttribute('dir', $dir);
            }
            if ($lang !== '') {
                $container->setAttribute('lang', $lang);
            }
        }

        while ($container->firstChild !== null) {
            $container->removeChild($container->firstChild);
        }

        foreach ($lines as $line) {
            $lineEl = $dom->createElement('div');
            $lineClass = trim($existingClass.' invoice-pdf-ar-preline');
            if ($compact) {
                $lineClass = trim($lineClass.' invoice-pdf-ar-preline--compact');
            }
            $lineEl->setAttribute('class', $lineClass);
            $lineEl->setAttribute('dir', 'rtl');
            $lineEl->setAttribute('lang', 'ar');
            $lineEl->appendChild($dom->createTextNode($line));
            $container->appendChild($lineEl);
        }

        if ($container !== $node) {
            $node->parentNode?->replaceChild($container, $node);
        }
    }
}