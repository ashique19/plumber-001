<?php

use App\Support\Invoicing\PrintScreenBackUrl;
use Illuminate\Http\Request;

it('builds encoded hub return query without all tab values', function () {
  $encoded = PrintScreenBackUrl::encodeHubState([
      'lifecycle' => 'awaiting_clearance',
      'zatcaTab' => 'all',
      'typeFilter' => 'standard',
  ], PrintScreenBackUrl::INVOICE_HUB_KEYS);

  expect($encoded)->toBe('lifecycle=awaiting_clearance&typeFilter=standard');
});

it('resolves invoice back url from return param', function () {
  $request = Request::create('/admin/invoices/1/print', 'GET', [
      'return' => 'lifecycle=awaiting_clearance',
  ]);

  $url = PrintScreenBackUrl::invoiceHub($request);

  expect($url)->toBe(route('admin.invoices', ['lifecycle' => 'awaiting_clearance']));
});