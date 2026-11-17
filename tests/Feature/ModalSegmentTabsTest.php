<?php

use Illuminate\Support\Facades\Blade;

it('renders modal segment tabs only when two or more tabs are visible', function (int $tabCount, bool $expectsBar) {
    $html = Blade::render(<<<'BLADE'
        <x-admin.modal-segment-tabs :tab-count="$tabCount" test-id="spec-tabs">
            <button type="button" class="app-segment-tab">Tab A</button>
            <button type="button" class="app-segment-tab">Tab B</button>
        </x-admin.modal-segment-tabs>
    BLADE, ['tabCount' => $tabCount]);

    if ($expectsBar) {
        expect($html)->toContain('data-testid="spec-tabs"')
            ->and($html)->toContain('app-segment-tab');
    } else {
        expect($html)->not->toContain('data-testid="spec-tabs"')
            ->and($html)->not->toContain('app-section-tabs');
    }
})->with([
    'no tabs' => [0, false],
    'single tab' => [1, false],
    'two tabs' => [2, true],
]);