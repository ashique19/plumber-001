<?php

use App\Business\BusinessContext;
use App\Livewire\Admin\BusinessSettings;
use App\Livewire\Admin\StaffProfile;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Livewire\Expenses\ExpensesHub;
use App\Livewire\Payments\PaymentsDuesHub;
use App\Livewire\Pos\OrderHistory;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    BusinessContext::set($this->business);
});

/**
 * Livewire components whose views wire blur/change to validateLiveField must expose that method
 * (ValidatesFieldsLive or an explicit wrapper).
 */
it('ensures livewire components with live field validation expose validateLiveField', function () {
    $viewRoot = resource_path('views/livewire');
    $missing = [];

    foreach (File::allFiles($viewRoot) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relative = Str::after($file->getPathname(), $viewRoot.DIRECTORY_SEPARATOR);
        $blade = $file->getContents();

        $needsLive = false;
        foreach (preg_split('/\R/', $blade) ?: [] as $line) {
            if (! preg_match('/<x-(?:field|form)\.\w+/i', $line)) {
                continue;
            }
            if (str_contains($line, 'liveValidate="false"') || str_contains($line, ':liveValidate="false"')) {
                continue;
            }
            if (preg_match('/type="search"/i', $line) || preg_match("/type='search'/i", $line)) {
                continue;
            }
            $needsLive = true;
            break;
        }

        if (! $needsLive) {
            continue;
        }

        $class = 'App\\Livewire\\'.str_replace(['/', '.blade.php'], ['\\', ''], $relative);
        if (! class_exists($class)) {
            continue;
        }

        $usesTrait = in_array(ValidatesFieldsLive::class, class_uses_recursive($class), true);
        $hasMethod = method_exists($class, 'validateLiveField');
        if (! $usesTrait && ! $hasMethod) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBe([]);
});

it('calls validateLiveField on the staff profile without error', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(StaffProfile::class)
        ->call('validateLiveField', 'name')
        ->assertHasNoErrors();
});

it('calls validateLiveField on expense and payments forms without error', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(ExpensesHub::class)
        ->set('showForm', true)
        ->call('validateLiveField', 'vendor')
        ->assertHasNoErrors();

    Livewire::actingAs($this->owner, 'staff')
        ->test(PaymentsDuesHub::class)
        ->call('validateLiveField', 'paidOn')
        ->assertHasNoErrors();
});

it('calls validateLiveField on order history refund reason without error', function () {
    $order = seedDemoPaidPosOrder();

    Livewire::actingAs($this->owner, 'staff')
        ->test(OrderHistory::class, ['branch' => $this->branch])
        ->call('openRefundModal', $order->id)
        ->set('refundReason', 'Sample refund reason')
        ->call('validateLiveField', 'refundReason')
        ->assertHasNoErrors();
});

it('business settings validateLiveField accepts blur on contact email', function () {
    ['business' => $business, 'cashier' => $cashier] = posBusinessSetup();
    BusinessContext::set($business);
    $cashier->syncRoles(['owner']);

    Livewire::actingAs($cashier, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'contact')
        ->call('validateLiveField', 'email')
        ->assertHasNoErrors();
});