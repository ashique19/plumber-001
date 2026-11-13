<?php

namespace App\Livewire\Ops;

use App\Models\Branch;
use App\Services\Pos\PosBranchInsights;
use App\Services\Pos\PosEndOfDayService;
use App\Support\ChartScale;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class BranchDashboard extends Component
{
    public int $branchId;

    public function mount(Branch $branch): void
    {
        $this->branchId = $branch->id;
    }

    public function render(PosEndOfDayService $eod, PosBranchInsights $insights): View
    {
        $branch = Branch::query()->findOrFail($this->branchId);

        $today = $insights->todaySnapshot($branch);
        $hourlyTotals = array_column($today['hourly'], 'total');

        return view('livewire.ops.branch-dashboard', [
            'branch' => $branch,
            'summary' => $eod->summarize($branch),
            'today' => $today,
            'maxHour' => ChartScale::floorMax(1, $hourlyTotals),
        ]);
    }
}
