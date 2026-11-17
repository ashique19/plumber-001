<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\Product;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('resolves quick modal translation keys in en, bn, and ar', function () {
    $pattern = '/__\(\s*(?:\@[\'"][^\'"]+[\'"]\s*)?[\'"]([^\'"]+)[\'"]/';

    $files = [
        resource_path('views/components/invoices/quick-customer-modal.blade.php'),
        resource_path('views/components/invoices/quick-product-modal.blade.php'),
        resource_path('views/components/purchases/quick-supplier-modal.blade.php'),
        resource_path('views/components/modal/footer.blade.php'),
        resource_path('views/components/modal/validation-summary.blade.php'),
        resource_path('views/components/ui/livewire-action.blade.php'),
    ];

    $keys = collect($files)
        ->flatMap(function (string $path) use ($pattern) {
            $contents = file_get_contents($path);

            preg_match_all($pattern, $contents, $matches);

            return $matches[1] ?? [];
        })
        ->merge([
            'loading.saving',
            'invoices.customer_created_selected',
            'invoices.product_created_applied',
            'invoices.customer_profile_updated',
            'suppliers.created',
        ])
        ->unique()
        ->filter(fn (string $key) => str_contains($key, '.') && ! str_ends_with($key, '.'))
        ->values();

    $loadLocale = function (string $locale, string $filename): array {
        $enPath = lang_path('en/'.$filename);
        if (! is_file($enPath)) {
            return [];
        }

        /** @var array<string, mixed> $en */
        $en = require $enPath;
        $localePath = lang_path($locale.'/'.$filename);

        if (! is_file($localePath)) {
            return $en;
        }

        /** @var array<string, mixed> $patch */
        $patch = require $localePath;

        return array_replace_recursive($en, $patch);
    };

    $missing = [];

    foreach ($keys as $dotKey) {
        [$file, $rest] = explode('.', $dotKey, 2);

        foreach (['en', 'bn', 'ar'] as $locale) {
            $merged = $loadLocale($locale, $file.'.php');

            if (! Arr::has($merged, $rest)) {
                $missing[] = "{$locale}: {$dotKey}";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('renders quick customer modal save button with localized loading label in bn', function () {
    app()->setLocale('bn');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard', 'locale' => 'bn'])
        ->set('showQuickCustomerModal', true)
        ->html();

    expect($html)
        ->toContain(__('catalog.save_and_select', locale: 'bn'))
        ->toContain(__('loading.saving', locale: 'bn'))
        ->toContain('wire:target="saveQuickCustomer"')
        ->not->toContain('loading.saving')
        ->not->toContain('customers.saving');
});

it('does not leak raw translation keys after successful quick customer save in bn', function () {
    app()->setLocale('bn');

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified', 'locale' => 'bn'])
        ->call('addLineFromProduct', $this->product->id)
        ->set('customerQuickName', 'Quick Buyer')
        ->set('customerQuickVatRegistered', true)
        ->set('customerQuickVat', '300000000000003')
        ->set('customerQuickPhone', '0599000111')
        ->call('saveQuickCustomer')
        ->assertHasNoErrors()
        ->assertSet('showQuickCustomerModal', false);

    expect(Lang::get('invoices.customer_created_selected', locale: 'bn'))
        ->not->toBe('invoices.customer_created_selected');
});