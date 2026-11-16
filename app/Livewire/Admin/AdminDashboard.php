<?php

namespace App\Livewire\Admin;

use App\Business\BusinessContext;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Support\Admin\DashboardQuickActionTiles;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class AdminDashboard extends Component
{
    use ResolvesBusinessContext;

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        $this->authorize('reports.view');
    }

    public function render(): View
    {
        $user = auth('staff')->user();

        return view('livewire.admin.admin-dashboard', [
            'actionTiles' => DashboardQuickActionTiles::forUser($user, $this->business),
        ]);
    }
}