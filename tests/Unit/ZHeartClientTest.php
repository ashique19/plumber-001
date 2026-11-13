<?php

use App\Services\Invoicing\ZHeart\ZHeartApiException;
use App\Services\Invoicing\ZHeart\ZHeartClient;
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
            'data' => null,
            'request_id' => 'req-401',
        ], 401),
    ]);

    expect(fn () => app(ZHeartClient::class)->sign(minimalZHeartInvoicePayload()))
        ->toThrow(ZHeartApiException::class);
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
