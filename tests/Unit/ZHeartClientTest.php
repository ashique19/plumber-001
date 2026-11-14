<?php

use App\Services\Invoicing\ZHeart\ZHeartApiException;
use App\Services\Invoicing\ZHeart\ZHeartClient;
use App\Support\Invoicing\ZHeartApiUserMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789',
        'zheart.retry_times' => 0,
    ]);
});

it('calls sign and maps success artifacts', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/sign' => Http::response([
            'status' => 'ok',
            'message' => 'Invoice signed.',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'invoice_hash' => 'aGFzaA==',
                'qr' => 'cXI=',
                'uuid' => '550e8400-e29b-41d4-a716-446655440099',
                'icv' => 3,
                'pih' => 'cGlo',
            ],
            'request_id' => 'req-sign-1',
        ], 200, ['X-Request-Id' => 'req-sign-1']),
    ]);

    $client = app(ZHeartClient::class);
    $result = $client->sign(minimalZHeartInvoicePayload());

    expect($result['data']['icv'])->toBe(3)
        ->and($result['request_id'])->toBe('req-sign-1');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789')
            && ! str_contains(json_encode($request->data()), 'zh_test1234');
    });
});

it('maps 401 to a safe user-facing exception', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/sign' => Http::response([
            'status' => 'error',
            'message' => 'Invalid API key.',
            'data' => ['error' => 'invalid_api_key'],
            'request_id' => 'req-401',
        ], 401),
    ]);

    try {
        app(ZHeartClient::class)->sign(minimalZHeartInvoicePayload());
        expect(false)->toBeTrue('expected exception');
    } catch (ZHeartApiException $e) {
        expect($e->getMessage())->toContain('Invalid API key.')
            ->and($e->getMessage())->toContain('invalid_api_key');
    }
});

it('maps 403 client_suspended via data.error', function () {
    $message = ZHeartApiUserMessage::fromResponse(403, [
        'message' => 'Client blocked.',
        'data' => ['error' => 'client_suspended'],
    ], 'req-403');

    expect($message)->toContain('Client blocked.');
});

it('does not retry report on 503 even when retry_times is high', function () {
    config(['zheart.retry_times' => 3]);

    Http::fake([
        'https://z-heart.test/api/v1/invoices/report' => Http::response([
            'status' => 'error',
            'message' => 'Down',
            'data' => ['error' => 'server_error'],
        ], 503),
    ]);

    expect(fn () => app(ZHeartClient::class)->report(minimalZHeartInvoicePayload()))
        ->toThrow(ZHeartApiException::class);

    Http::assertSentCount(1);
});


it('health checks the remote service', function () {
    Http::fake([
        'https://z-heart.test/api/v1/health' => Http::response(['status' => 'ok'], 200),
    ]);

    expect(app(ZHeartClient::class)->health())->toBeTrue();
});

function minimalZHeartInvoicePayload(string $type = 'simplified'): array
{
    return [
        'uuid' => '550e8400-e29b-41d4-a716-446655440001',
        'issue_date' => '2026-01-15',
        'issue_time' => '12:00:00',
        'currency' => 'SAR',
        'type' => $type,
        'lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15]],
        'totals' => ['tax_exclusive' => 100, 'tax' => 15, 'tax_inclusive' => 115],
    ];
}
