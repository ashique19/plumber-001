<?php

namespace App\Livewire\Pos;

use App\Support\StaffBranchContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class Home extends Component
{
    public function mount(): void
    {
        $user = Auth::guard('staff')->user();
        if ($user === null) {
            return;
        }

        if (! StaffBranchContext::selectorVisible($user)) {
            $branch = StaffBranchContext::resolve($user);
            if ($branch !== null) {
                StaffBranchContext::remember($branch);
                $this->redirect(route('admin.pos.terminal', $branch), navigate: true);
            }
        }
    }

    public function render(): View
    {
        $user = Auth::guard('staff')->user();
        $branches = StaffBranchContext::accessibleBranches($user);

        return view('livewire.pos.home', [
            'branches' => $branches,
            'showBranchPicker' => StaffBranchContext::selectorVisible($user),
        ]);
    }
}
