<?php

use App\Support\Invoicing\SigningFailureSanitizer;

it('redacts pem and long base64 from signing failure messages', function () {
    $raw = 'Error: -----BEGIN PRIVATE KEY-----'.str_repeat('A', 120).'-----END PRIVATE KEY----- token MIID'.str_repeat('x', 90).'==';

    $sanitized = SigningFailureSanitizer::sanitizeMessage($raw);

    expect($sanitized)->not->toContain('BEGIN PRIVATE KEY')
        ->and($sanitized)->toContain('[redacted-pem]')
        ->and($sanitized)->toContain('[redacted-base64]');
});

it('builds user messages with translated base and sanitized technical suffix', function () {
    $message = SigningFailureSanitizer::userMessage(
        'Base message.',
        new RuntimeException('Call to a member function asText() on null')
    );

    expect($message)->toStartWith('Base message.')
        ->and($message)->toContain('RuntimeException')
        ->and($message)->toContain('asText() on null')
        ->and($message)->toMatch('/\([A-Za-z0-9_.-]+\.php:\d+\)/');
});
