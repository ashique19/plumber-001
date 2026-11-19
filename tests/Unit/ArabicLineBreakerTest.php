<?php

use App\Support\Invoicing\ArabicLineBreaker;

it('keeps short arabic on one line', function () {
    $text = 'تسليك وتصوير تلفزيوني';
    expect(ArabicLineBreaker::breakLines($text, 40))->toBe([$text]);
});

it('breaks long arabic on spaces within budget', function () {
    $text = 'تسليك وتصوير تلفزيوني لتدفق الصرف الصحي مع عدة مواد كيميائية وضمان جودة العمل';
    $lines = ArabicLineBreaker::breakLines($text, 28);
    expect(count($lines))->toBeGreaterThan(1);
    foreach ($lines as $line) {
        expect(mb_strlen($line))->toBeLessThanOrEqual(28);
    }
    expect(implode(' ', $lines))->toBe($text);
});

it('hard chunks a single long arabic token', function () {
    $word = str_repeat('ا', 40);
    $lines = ArabicLineBreaker::breakLines($word, 12);
    expect($lines)->toHaveCount(4);
});

it('pulls a word from the previous line when the last line would be a lone word', function () {
    expect(ArabicLineBreaker::balanceOrphanLastLine(['أحد عشر ريال و خمسة', 'فقط']))
        ->toBe(['أحد عشر ريال و', 'خمسة فقط']);

    $text = 'ثمانية و عشرون ريال و خمسة و سبعون هللة فقط';
    $lines = ArabicLineBreaker::breakLines($text, 28);
    if (count($lines) > 1) {
        $lastWords = preg_split('/\s+/u', (string) end($lines)) ?: [];
        expect(count($lastWords))->toBeGreaterThan(1);
    }
});

it('fits typical total amount words into two lines for pdf blocks', function () {
    $text = 'ثمانية و عشرون ريال و خمسة و سبعون هللة فقط';
    $result = ArabicLineBreaker::breakForPdfBlock($text, 58, 2);

    expect(count($result['lines']))->toBeLessThanOrEqual(2)
        ->and(implode(' ', $result['lines']))->toBe($text);
});

it('moves a trailing و to the next line', function () {
    expect(ArabicLineBreaker::avoidTrailingWa(['ثمانية و عشرون ريال و خمسة و', 'سبعون هللة فقط']))
        ->toBe(['ثمانية و عشرون ريال و خمسة', 'و سبعون هللة فقط']);
});