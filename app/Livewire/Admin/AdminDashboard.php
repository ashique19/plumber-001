<?php

namespace App\Livewire\Admin;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Services\Business\DashboardMonthlyInvoiceReportService;
use App\Services\Inventory\IngredientStockService;
use App\Services\Business\BusinessDashboardService;
use App\Support\Admin\DashboardQuickActionTiles;
use App\Support\ChartScale;
use App\Business\BusinessContext;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Livewire\Concerns\ResolvesBusinessContext;
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

    private function resolveBranch(): ?Branch
    {
        $user = auth('staff')->user();
        if ($user?->branch_id === null) {
            return null;
        }

        return Branch::query()
            ->withoutGlobalScopes()
            ->whereKey($user->branch_id)
            ->first();
    }

    public function render(
        BusinessDashboardService $dashboard,
        DashboardMonthlyInvoiceReportService $monthlyReports,
        IngredientStockService $stock,
    ): View {
        $branch = $this->resolveBranch();
        $snapshot = $dashboard->snapshot($this->business);
        $monthlyReport = $monthlyReports->report($this->business, $branch, app()->getLocale());
        $lowStock = $stock->lowStockForBusiness();
        $series = $snapshot['recent_sales_series'];
        $seriesTotals = array_map(fn (array $row) => (float) $row['total'], $series);
        $maxTotal = ChartScale::floorMax(1.0, $seriesTotals);

        $user = auth('staff')->user();

        return view('livewire.admin.admin-dashboard', [
            'monthlyReport' => $monthlyReport,
            'snapshot' => $snapshot,
            'series' => $series,
            'maxTotal' => $maxTotal,
            'lowStock' => $lowStock,
            'actionTiles' => DashboardQuickActionTiles::forUser($user, $this->business),
        ]);
    }
}
