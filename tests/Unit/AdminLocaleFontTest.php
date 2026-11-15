<?php

use App\Support\Admin\AdminLocaleFont;

it('resolves preload paths for supported locales', function () {
    expect(AdminLocaleFont::preloadPath('en'))->toBe('/fonts/admin/en/plus-jakarta-sans-400.woff2')
        ->and(AdminLocaleFont::preloadPath('bn'))->toBe('/fonts/admin/bn/noto-sans-bengali-400.woff2')
        ->and(AdminLocaleFont::preloadPath('ar'))->toBe('/fonts/admin/ar/ibm-plex-sans-arabic-400.woff2');
});