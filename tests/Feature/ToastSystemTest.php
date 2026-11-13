<?php

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoFancyCoffeeSeeder;
use Livewire\Component;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoFancyCoffeeSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@fancy-coffee.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);
});

it('includes toast stack in pos layout', function () {
    $layout = file_get_contents(resource_path('views/layouts/pos.blade.php'));
    $component = file_get_contents(resource_path('views/components/toast-stack.blade.php'));
    expect($layout)->toContain('x-toast-stack')
        ->and($component)->toContain('id="toast-stack"')
        ->and($component)->toContain('toastStack');
});

it('dispatches toast events from components', function () {
    $probe = new class extends Component
    {
        use DispatchesToasts;

        public function ping(): void
        {
            $this->toastSuccess('Saved for later');
        }

        public function render()
        {
            return '<div>probe</div>';
        }
    };

    Livewire::test($probe::class)
        ->call('ping')
        ->assertDispatched('toast', type: 'success', message: 'Saved for later');
});
