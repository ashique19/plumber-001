<?php

namespace App\Livewire\Pos;

use App\Models\Branch;
use App\Services\Pos\PosEndOfDayService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class EndOfDay extends Component
{
    public int $branchId;

    public string $date;

    public function mount(Branch $branch): void
    {
        $user = Auth::guard('staff')->user();
        if ($user->branch_id !== null && (int) $user->branch_id !== (int) $branch->id) {
            abort(403);
        }

        $this->branchId = $branch->id;
        $this->date = today()->toDateString();
    }

    public function render(PosEndOfDayService $eod): View
    {
        $branch = Branch::query()->findOrFail($this->branchId);
        $summary = $eod->summarize($branch, Carbon::parse($this->date));

        return view('livewire.pos.end-of-day', [
            'branch' => $branch,
            'summary' => $summary,
        ]);
    }
}
