@props(['branch' => null])

@php
    use App\Models\Branch;
    use App\Models\BusinessSetting;
    use App\Support\AppShellNav;
    use App\Support\BusinessSettingDisplayName;

    $business = \App\Business\BusinessContext::get() ?? BusinessSetting::query()->find(1);
    $branch = $branch ?? request()->route('branch');
    if ($branch !== null && ! $branch instanceof Branch) {
        $branch = Branch::query()->withoutGlobalScopes()->find($branch);
    }
    $locale = app()->getLocale();
    $dir = config("locales.supported.{$locale}.dir", 'ltr');
    $active = AppShellNav::activeTab();
    $displayName = $business ? BusinessSettingDisplayName::for($business, $locale) : config('app.name');
    $logoUrl = $business ? data_get($business->settings, 'theme.logo_url') : null;
    $logoAltKey = 'plumber.logo_alt';
    $colors = $business?->themeColors() ?? [];
    $branches = $business ? AppShellNav::branchesForSwitcher($business) : [];
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($business?->id ?? 1);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}" class="scroll-smooth"
      style="
        --plumber-navy: {{ $colors['navy'] ?? '#0B1F3A' }};
        --plumber-steel: {{ $colors['steel'] ?? '#2D4A6F' }};
        --plumber-sky: {{ $colors['sky'] ?? '#E8F1FA' }};
        --plumber-ice: {{ $colors['ice'] ?? '#F4F8FC' }};
        --plumber-safety: {{ $colors['safety'] ?? '#E87722' }};
        --plumber-accent: {{ $colors['accent'] ?? '#1B8A8A' }};
        --plumber-slate: {{ $colors['slate'] ?? '#475569' }};
      ">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    @php
        $documentTitle = $pageTitle ?? __('pos.title');
    @endphp
    <title>{{ $documentTitle }} — {{ $displayName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    @livewireStyles
</head>
<body class="app-shell antialiased" x-data="{ sidebarCollapsed: false, moreOpen: false, userOpen: false }" @keydown.escape.window="moreOpen = false; userOpen = false">
    <div class="livewire-loading-bar" aria-hidden="true"></div>
    <x-toast-stack class="has-app-tab-bar" />

    @if($business)
        <aside class="app-sidebar" :class="sidebarCollapsed ? 'app-sidebar--collapsed' : ''" aria-label="{{ __('pos.title') }}">
            <div class="flex items-center gap-2 border-b px-3 py-4" style="border-color: var(--app-border)">
                @if ($logoUrl)
                    <img src="{{ asset($logoUrl) }}" alt="{{ __($logoAltKey, ['name' => $displayName]) }}" class="h-9 w-9 shrink-0 rounded-lg object-contain" width="36" height="36">
                @else
                    <x-plumber.logo-mark class="h-9 w-9 shrink-0 text-[var(--app-accent)]" :name="$displayName" />
                @endif
                <span class="app-sidebar__label truncate text-sm font-semibold">{{ $displayName }}</span>
            </div>
            <nav class="flex flex-1 flex-col gap-1 p-2">
                @php
                    $branchParam = $branch ?? ($branches[0] ?? null);
                @endphp
                @can('reports.view')
                    <a href="{{ route('admin.dashboard') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'dashboard'])>
                        <x-app.icon name="home" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('dashboard.nav') }}</span>
                    </a>
                @endcan
                <a href="{{ route('admin.pos.home') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'pos'])>
                    <x-app.icon name="chart" class="h-5 w-5 shrink-0" />
                    <span class="app-sidebar__label">{{ __('pos.nav_pos') }}</span>
                </a>
                @if($branchParam)
                    <a href="{{ route('admin.pos.history', [ $branchParam]) }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'orders'])>
                        <x-app.icon name="clipboard" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('pos.nav_orders') }}</span>
                    </a>
                    <a href="{{ route('admin.pos.eod', [ $branchParam]) }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'eod'])>
                        <x-app.icon name="clock" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('pos.nav_eod') }}</span>
                    </a>
                    <a href="{{ route('admin.pos.ops', [ $branchParam]) }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'ops'])>
                        <x-app.icon name="chart" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('pos.nav_ops') }}</span>
                    </a>
                @endif
                @can('business.manage')
                    <a href="{{ route('admin.business') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'business'])>
                        <x-app.icon name="paint" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('business.nav') }}</span>
                    </a>
                @endcan
                @can('staff.manage')
                    <a href="{{ route('admin.team') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'team'])>
                        <x-app.icon name="clipboard" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('operations.nav') }}</span>
                    </a>
                @endcan
                @can('inventory.view')
                    <a href="{{ route('admin.catalog') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'catalog'])>
                        <x-app.icon name="paint" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('catalog.nav') }}</span>
                    </a>
                    <a href="{{ route('admin.inventory') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'inventory'])>
                        <x-app.icon name="shield" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('inventory.nav') }}</span>
                    </a>
                @endcan
                @can('reports.view')
                    <a href="{{ route('admin.reports') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'analytics'])>
                        <x-app.icon name="chart" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('reports.nav') }}</span>
                    </a>
                    <a href="{{ route('admin.customers') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'customers'])>
                        <x-app.icon name="users" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('customers.nav') }}</span>
                    </a>
                @endcan
                @if (auth('staff')->user()?->can('invoices.issue') || auth('staff')->user()?->can('reports.view'))
                    <a href="{{ route('admin.invoices') }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'invoices'])>
                        <x-app.icon name="clipboard" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('invoices.nav') }}</span>
                    </a>
                    @can('invoices.issue')
                        @php $altInvoiceMode = $business->alternateInvoiceDocumentType()->value; @endphp
                        <a href="{{ route('admin.invoices.issue', [ $altInvoiceMode]) }}" @class(['app-nav-link', 'app-nav-link--active' => $active === 'invoices-issue'])>
                            <x-app.icon name="clipboard" class="h-5 w-5 shrink-0" />
                            <span class="app-sidebar__label">{{ __('invoices.nav_issue_'.$altInvoiceMode) }}</span>
                        </a>
                    @endcan
                @endif
            </nav>
            <div class="border-t p-2" style="border-color: var(--app-border)">
                <button type="button" class="app-nav-link w-full" @click="sidebarCollapsed = !sidebarCollapsed">
                    <x-app.icon name="menu" class="h-5 w-5 shrink-0" />
                    <span class="app-sidebar__label">{{ __('pos.collapse_sidebar') }}</span>
                </button>
                <form method="POST" action="{{ route('admin.logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="app-nav-link w-full">
                        <x-app.icon name="logout" class="h-5 w-5 shrink-0" />
                        <span class="app-sidebar__label">{{ __('pos.logout') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="app-main" :class="sidebarCollapsed ? 'app-main--sidebar-collapsed' : ''">
            <header class="app-topbar">
                <div class="mx-auto flex max-w-6xl items-center gap-2 px-3 py-2 sm:px-5 sm:py-3">
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        @if ($logoUrl)
                            <img src="{{ asset($logoUrl) }}" alt="{{ __($logoAltKey, ['name' => $displayName]) }}" class="h-8 w-8 rounded-lg object-contain lg:hidden" width="32" height="32">
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $displayName }}</p>
                            @if($branch)
                                <p class="truncate text-xs text-[var(--app-muted)]">{{ $branch->name }}</p>
                            @elseif(count($branches) > 1)
                                <details class="relative text-xs">
                                    <summary class="cursor-pointer list-none text-[var(--app-accent)]">{{ __('pos.switch_branch') }}</summary>
                                    <ul class="app-card app-card--flat absolute z-20 mt-1 min-w-[12rem] p-1 shadow-lg">
                                        @foreach($branches as $b)
                                            <li>
                                                <a class="app-nav-link" href="{{ route('admin.pos.terminal', [ $b]) }}">{{ $b->name }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>
                    </div>
                    <x-app.locale-switch :current="$locale" class="hidden sm:flex" />
                    <div class="relative">
                        <button type="button" class="tap-action-muted min-h-11 min-w-11 rounded-full px-3" @click="userOpen = !userOpen" :aria-expanded="userOpen">
                            <span class="sr-only">{{ __('pos.user_menu') }}</span>
                            <x-app.icon name="dots" />
                        </button>
                        <div x-show="userOpen" x-cloak @click.outside="userOpen = false" class="app-card app-card--flat absolute end-0 z-30 mt-1 min-w-[11rem] p-1 shadow-xl">
                            <x-app.locale-switch :current="$locale" class="flex p-2 sm:hidden" />
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="app-nav-link w-full">{{ __('pos.logout') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="app-content">
                {{ $slot }}
            </main>

            @if($branchParam)
                <nav class="app-tabbar" aria-label="{{ __('pos.mobile_nav') }}">
                    <a href="{{ route('admin.pos.terminal', [ $branchParam]) }}" class="app-tab @if($active === 'pos') app-tab--active @endif">
                        <x-app.icon name="cart" />
                        {{ __('pos.nav_pos') }}
                    </a>
                    <a href="{{ route('admin.pos.history', [ $branchParam]) }}" class="app-tab @if($active === 'orders') app-tab--active @endif">
                        <x-app.icon name="clipboard" />
                        {{ __('pos.nav_orders') }}
                    </a>
                    <a href="{{ route('admin.pos.eod', [ $branchParam]) }}" class="app-tab @if($active === 'eod') app-tab--active @endif">
                        <x-app.icon name="clock" />
                        {{ __('pos.nav_eod') }}
                    </a>
                    <a href="{{ route('admin.pos.ops', [ $branchParam]) }}" class="app-tab @if($active === 'ops') app-tab--active @endif">
                        <x-app.icon name="chart" />
                        {{ __('pos.nav_ops') }}
                    </a>
                    <button type="button" class="app-tab @if($active === 'more') app-tab--active @endif" @click="moreOpen = true">
                        <x-app.icon name="dots" />
                        {{ __('pos.nav_more') }}
                    </button>
                </nav>

                <div x-show="moreOpen" x-cloak class="app-sheet-backdrop" @click="moreOpen = false"></div>
                <div x-show="moreOpen" x-cloak class="app-sheet p-4 lg:hidden" role="dialog" aria-label="{{ __('pos.nav_more') }}">
                    <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-[var(--app-border)]"></div>
                    <ul class="space-y-1">
                        <li><a class="app-nav-link" href="{{ route('admin.pos.home') }}">{{ __('pos.nav_home') }}</a></li>
                        @can('business.manage')
                            <li><a class="app-nav-link" href="{{ route('admin.business') }}">{{ __('business.nav') }}</a></li>
                        @endcan
                        @can('staff.manage')
                            <li><a class="app-nav-link" href="{{ route('admin.team') }}">{{ __('operations.nav') }}</a></li>
                        @endcan
                        @can('inventory.view')
                            <li><a class="app-nav-link" href="{{ route('admin.catalog') }}">{{ __('catalog.nav') }}</a></li>
                            <li><a class="app-nav-link" href="{{ route('admin.inventory') }}">{{ __('inventory.nav') }}</a></li>
                        @endcan
                        @can('reports.view')
                            <li><a class="app-nav-link" href="{{ route('admin.dashboard') }}">{{ __('dashboard.nav') }}</a></li>
                            <li><a class="app-nav-link" href="{{ route('admin.reports') }}">{{ __('reports.nav') }}</a></li>
                            <li><a class="app-nav-link" href="{{ route('admin.customers') }}">{{ __('customers.nav') }}</a></li>
                        @endcan
                        @if (auth('staff')->user()?->can('invoices.issue') || auth('staff')->user()?->can('reports.view'))
                            <li><a class="app-nav-link" href="{{ route('admin.invoices') }}">{{ __('invoices.nav') }}</a></li>
                        @endif
                        @can('invoices.issue')
                            @php $altInvoiceMode = $business->alternateInvoiceDocumentType()->value; @endphp
                            <li><a class="app-nav-link" href="{{ route('admin.invoices.issue', [ $altInvoiceMode]) }}">{{ __('invoices.nav_issue_'.$altInvoiceMode) }}</a></li>
                        @endcan
                        <li>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="app-nav-link w-full">{{ __('pos.logout') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @else
                <nav class="app-tabbar" aria-label="{{ __('pos.mobile_nav') }}">
                    <a href="{{ route('admin.pos.home') }}" class="app-tab app-tab--active">
                        <x-app.icon name="home" />
                        {{ __('pos.nav_home') }}
                    </a>
                </nav>
            @endif
        </div>
    @else
        <main class="app-content">{{ $slot }}</main>
    @endif

    <x-toast-prime-script />
    @livewireScriptConfig
</body>
</html>
