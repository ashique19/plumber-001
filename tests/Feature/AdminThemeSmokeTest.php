<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Support\AuthenticatedRouteCatalog;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    Branch::query()->update(['is_active' => true]);
    $this->owner->forceFill(['branch_id' => null])->save();

    $this->quotation = Quotation::query()->create([
        'branch_id' => $this->branch->id,
        'quotation_number' => 'QUO-SMOKE-1',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => ['lines' => []],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $this->invoice = Invoice::query()->create([
        'branch_id' => $this->branch->id,
        'document_number' => 'SIMP-SMOKE-1',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
        ],
    ]);
});

it('serves every admin GET route in en bn and ar without server errors or missing translation markers', function () {
    $this->actingAs($this->owner, 'staff');

    $routes = array_merge(
        AuthenticatedRouteCatalog::coreRoutes($this->branch),
        AuthenticatedRouteCatalog::extendedRoutes($this->branch, $this->quotation, $this->invoice),
    );

    foreach (['en', 'bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        foreach ($routes as [$routeName, $parameters, $expectedStatus]) {
            $url = route($routeName, $parameters);
            $response = $this->get($url);
            $body = (string) $response->getContent();

            expect($response->status())
                ->not->toBe(500, "{$locale}: GET {$url}")
                ->and($response->status())
                ->toBe($expectedStatus, "{$locale}: GET {$url}");

            expect($body)->not->toContain('Translation missing')
                ->and($body)->not->toMatch('/\{\{[^}]+\}\}/');
        }
    }
});

it('renders key livewire admin components', function () {
    $this->actingAs($this->owner, 'staff');

    Livewire::test(\App\Livewire\Admin\AdminDashboard::class)
        ->assertSee(__('dashboard.title'), false);

    Livewire::test(\App\Livewire\Invoices\IssueInvoice::class, ['mode' => 'simplified'])
        ->assertSee(__('invoices.customer_search'), false);

    Livewire::test(\App\Livewire\Invoices\IssueInvoice::class, ['mode' => 'standard'])
        ->assertOk();

    Livewire::test(\App\Livewire\Quotations\ManageQuotation::class)
        ->assertSee(__('invoices.search_catalog_products'), false);

    Livewire::test(\App\Livewire\Admin\BusinessSettings::class)
        ->assertOk();

    foreach (AuthenticatedRouteCatalog::livewireSmokeComponents() as $component) {
        expect(class_exists($component))->toBeTrue("Component {$component} exists")
            ->and(is_subclass_of($component, \Livewire\Component::class))->toBeTrue();
    }
});

it('registers admin layout theme hooks on invoice issue page', function () {
    $this->actingAs($this->owner, 'staff');

    $html = $this->get(route('admin.invoices.issue', ['mode' => 'simplified']))->assertOk()->getContent();

    expect($html)->toContain('admin-app')
        ->and($html)->toContain('admin-theme-toggle')
        ->and($html)->toContain('document-sticky-action-bar');
});

it('includes print stylesheet separately from admin shell', function () {
    expect(Route::has('admin.invoices.print'))->toBeTrue();
    $this->actingAs($this->owner, 'staff');

    $response = $this->get(route('admin.invoices.print', ['invoice' => $this->invoice]));
    $body = (string) $response->getContent();

    expect($response->status())->toBe(200)
        ->and($body)->not->toContain('admin-theme-toggle');
});