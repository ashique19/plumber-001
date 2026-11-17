<?php

use App\Support\Admin\AdminPrintScreen;

it('appends embed query flag to print urls', function () {
    $url = AdminPrintScreen::withEmbed('https://example.test/admin/invoices/1/print?layout=a4');

    expect($url)->toContain('embed=1');
});