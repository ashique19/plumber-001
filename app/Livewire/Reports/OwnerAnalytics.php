<?php

namespace App\Livewire\Reports;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Services\Pos\PosAnalyticsService;
use App\Support\AppShellNav;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\ResolvesBusinessContext;

#[Layout('layouts.pos')]
class OwnerAnalytics extends Component
{
    use ResolvesBusinessContext;

    #[Url(as: 'branch')]
    public ?int $branchId = null;

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url]
    public string $granularity = 'day';

    #[Url]
    public string $preset = '7d';

    public function mount(): void
    {
        $this->resolveBusiness();

        if ($this->dateFrom === '' || $this->dateTo === '') {
            $this->applyPreset($this->preset);
        }
    }

    public function updatedPreset(string $value): void
    {
        $this->applyPreset($value);
    }

    public function applyPreset(string $preset): void
    {
        $tz = config('app.timezone');
        $to = Carbon::now($tz)->endOfDay();
        $from = match ($preset) {
            '30d' => $to->copy()->subDays(29)->startOfDay(),
            '90d' => $to->copy()->subDays(89)->startOfDay(),
            'month' => $to->copy()->startOfMonth(),
            default => $to->copy()->subDays(6)->startOfDay(),
        };

        $this->preset = $preset;
        $this->dateFrom = $from->toDateString();
        $this->dateTo = $to->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(PosAnalyticsService $analytics): array
    {
        $tz = config('app.timezone');
        $from = Carbon::parse($this->dateFrom, $tz)->startOfDay();
        $to = Carbon::parse($this->dateTo, $tz)->endOfDay();
        $branch = $this->resolveBranch();

        $granularity = in_array($this->granularity, ['day', 'week', 'month'], true)
            ? $this->granularity
            : 'day';

        return $analytics->report($this->tenant, $branch, $from, $to, $granularity);
    }

    private function resolveBranch(): ?Branch
    {
        if ($this->branchId === null) {
            return null;
        }

        return Branch::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->whereKey($this->branchId)
            ->first();
    }

    public function render(PosAnalyticsService $analytics): View
    {
        $report = $this->buildReport($analytics);
        $branches = AppShellNav::branchesForSwitcher($this->tenant);

        $colors = $this->tenant->themeColors();

        return view('livewire.reports.owner-analytics', [
            'report' => $report,
            'branches' => $branches,
            'chartTheme' => [
                'primary' => $colors['caramel'] ?? '#C68B59',
                'accent' => $colors['accent'] ?? '#D4A574',
                'espresso' => $colors['espresso'] ?? '#2C1810',
                'latte' => $colors['latte'] ?? '#E8D4C4',
                'mocha' => $colors['mocha'] ?? '#5C3D2E',
            ],
            'isRtl' => config('locales.supported.'.app()->getLocale().'.dir', 'ltr') === 'rtl',
        ]);
    }
}
