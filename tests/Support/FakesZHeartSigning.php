<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

trait FakesZHeartSigning
{
    protected function fakeZHeartSigning(): void
    {
        config([
            'zheart.base_url' => 'https://z-heart.test',
            'zheart.api_key' => 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789',
            'zheart.retry_times' => 0,
            'zheart.auto_submit_signed' => false,
        ]);

        Http::fake([
            'https://z-heart.test/api/v1/invoices/sign' => Http::response([
                'status' => 'ok',
                'data' => [
                    'signed_xml' => base64_encode('<Invoice/>'),
                    'invoice_hash' => 'aGFzaA==',
                    'qr' => 'cXI=',
                    'uuid' => '550e8400-e29b-41d4-a716-446655440099',
                    'icv' => 1,
                    'pih' => '',
                ],
                'request_id' => 'req-test',
            ], 200),
        ]);
    }
}
