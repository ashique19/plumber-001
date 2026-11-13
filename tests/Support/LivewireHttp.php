<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;

final class LivewireHttp
{
    /**
     * @return array{0: string, 1: string} snapshot JSON and component id
     */
    public static function firstSnapshotFromHtml(string $html): array
    {
        if (preg_match('/wire:snapshot="([^"]+)"/', $html, $matches) !== 1) {
            throw new \RuntimeException('No wire:snapshot found in response HTML.');
        }

        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);

        $decoded = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        $id = $decoded['memo']['id'] ?? throw new \RuntimeException('Livewire snapshot missing component id.');

        return [$snapshot, $id];
    }

    /**
     * @param  array<int, array{method: string, params?: array<int, mixed>, path?: string}>  $calls
     */
    public static function postUpdate(object $test, TestResponse $page, array $calls, ?string $referer = null): TestResponse
    {
        [$snapshot] = self::firstSnapshotFromHtml($page->getContent());

        $payload = [
            '_token' => csrf_token(),
            'components' => [
                [
                    'snapshot' => $snapshot,
                    'updates' => [],
                    'calls' => array_map(fn (array $call) => [
                        'path' => $call['path'] ?? '',
                        'method' => $call['method'],
                        'params' => $call['params'] ?? [],
                    ], $calls),
                ],
            ],
        ];

        $headers = $referer !== null ? ['Referer' => $referer] : [];

        return $test->post('/livewire/update', $payload, $headers);
    }
}
