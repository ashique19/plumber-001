<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    \App\Business\BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->products = Product::query()->where('is_active', true)->orderBy('id')->limit(5)->get();
    expect($this->products)->not->toBeEmpty();
});

it('assigns stable line ids and recalculates totals when adding lines', function () {
    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard']);

    foreach ($this->products->take(1) as $product) {
        $component->call('addLineFromProduct', $product->id);
    }

    $lines = $component->get('lines');
    expect($lines)->toHaveCount(1)
        ->and($lines[0]['line_id'])->not->toBeEmpty();

    $totalOne = $component->instance()->previewTotals()['total_inc_vat'];
    expect($totalOne)->toBeGreaterThan(0);

    foreach ($this->products->slice(1, 1) as $product) {
        $component->call('addLineFromProduct', $product->id);
    }

    $ids = collect($component->get('lines'))->pluck('line_id');
    expect($ids)->toHaveCount(2)->unique()->count()->toBe(2);

    foreach ($this->products->slice(2) as $product) {
        $component->call('addLineFromProduct', $product->id);
    }

    expect(collect($component->get('lines')))->toHaveCount(5);

    $totalFive = $component->instance()->previewTotals()['total_inc_vat'];
    expect($totalFive)->toBeGreaterThan($totalOne);

    $component->call('removeLine', 2);
    $remaining = collect($component->get('lines'));
    expect($remaining)->toHaveCount(4)
        ->and($remaining->pluck('line_id')->unique()->count())->toBe(4);

    $html = $component->html();
    expect($html)->toContain('data-document-line-id="'.$remaining->first()['line_id'].'"')
        ->and($html)->toContain('doc-line-card-'.$remaining->first()['line_id'])
        ->and($html)->toContain('doc-line-row-'.$remaining->first()['line_id']);
});

it('renders valid table structure with line id wire keys in the blade', function () {
    $blade = file_get_contents(resource_path('views/components/invoices/document-product-lines-create.blade.php'));

    expect($blade)
        ->toContain('wire:key="doc-line-card-{{ $lineId }}"')
        ->toContain('wire:key="doc-line-row-{{ $lineId }}"')
        ->toContain('data-document-line-id="{{ $lineId }}"')
        ->not->toContain('doc-line-card-{{ $index }}');
});