<?php

namespace App\Livewire\Pos;

use App\Models\Branch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class Home extends Component
{
    public function render(): View
    {
        $user = Auth::guard('staff')->user();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when($user->branch_id, fn ($q) => $q->where('id', $user->branch_id))
            ->orderBy('name')
            ->get();

        return view('livewire.pos.home', ['branches' => $branches]);
    }
}
