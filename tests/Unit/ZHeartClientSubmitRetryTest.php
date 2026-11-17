<?php

use App\Services\Invoicing\ZHeart\ZHeartClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test_key',
        'zheart.retry_times' => 5,
    ]);
});

it('does not retry POST report or clear submissions', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/report' => Http::sequence()
            ->push(['status' => 'error', 'message' => 'fail'], 503)
            ->push(['status' => 'ok', 'data' => ['signed_xml' => base64_encode('<x/>')]], 200),
    ]);

    try {
        app(ZHeartClient::class)->report(['uuid' => 'test-uuid']);
    } catch (\Throwable) {
        // expected after single attempt
    }

    Http::assertSentCount(1);
});