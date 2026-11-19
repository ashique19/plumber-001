<?php

it('measures and positions hub row more menu after teleport refs are ready', function () {
    $source = (string) file_get_contents(resource_path('js/hub-row-more-menu.js'));

    expect($source)
        ->toContain('applyInitialPosition')
        ->toContain('refineVerticalPlacement')
        ->toContain('style.right')
        ->toContain('currentTarget')
        ->toContain('scrollGuardUntil')
        ->not->toContain('measurePanel');
});