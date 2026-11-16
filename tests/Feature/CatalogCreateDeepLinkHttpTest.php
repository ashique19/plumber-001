<?php

use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
});

it('renders product sheet on full page load with create=product deep link', function () {
    $html = $this->get(route('admin.catalog', ['tab' => 'products', 'create' => 'product']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('id="catalog-product-form"')
        ->and($html)->toContain('x-modal-backdrop')
        ->and($html)->toContain('data-testid="catalog-product-modal"');

    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
    expect($matches)->not->toBeEmpty();

    $snapshot = json_decode(html_entity_decode($matches[1]), true);
    expect(data_get($snapshot, 'data.showProductForm'))->toBeTrue();
});