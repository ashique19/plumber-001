@props(['branch' => null])

@php
    use App\Models\Branch;
    use App\Models\BusinessSetting;
    use App\Support\Admin\AdminLocaleFont;
    use App\Support\Admin\AdminSidebarMenu;
    use App\Support\AppShellNav;
    use App\Support\BusinessSettingDisplayName;
    use App\Support\StaffBranchContext;
    use App\Support\StaffLandingRoute;

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
    $staffUser = auth('staff')->user();
    $branches = $staffUser ? StaffBranchContext::accessibleBranches($staffUser) : [];
    $showBranchSwitcher = $staffUser && StaffBranchContext::selectorVisible($staffUser);
    $posHomeUrl = $staffUser ? StaffBranchContext::posHomeUrl($staffUser) : route('admin.pos.home');
    $adminSidebar = (bool) config('ui.admin_sidebar', true);
    $adminMenu = ($business && $staffUser && $adminSidebar)
        ? AdminSidebarMenu::build($staffUser, $business, request())
        : null;
    $branchParam = $branch ?? ($staffUser ? StaffBranchContext::resolve($staffUser) : null);
    $brandHomeUrl = ($staffUser && $business)
        ? StaffLandingRoute::forUser($business, $staffUser)
        : route('admin.pos.home');
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($business?->id ?? 1);
    $localeSwitchClass = 'shrink-0';
    $contentUsesNoTabbar = ! $adminSidebar;
    $documentTitle = $pageTitle ?? __('pos.title');
    $topbarTitle = $documentTitle;
    if ($adminSidebar && $adminMenu) {
        $activeNavItem = collect($adminMenu['groups'])
            ->flatMap(fn (array $group) => $group['items'] ?? [])
            ->first(fn (array $item) => (bool) ($item['active'] ?? false));
        if (filled($pageTitle ?? null)) {
            $topbarTitle = $pageTitle;
        } elseif ($activeNavItem !== null) {
            $topbarTitle = $activeNavItem['label'];
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}" class="scroll-smooth admin-app"
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
    <title>{{ $documentTitle }} — {{ $displayName }}</title>
    @if ($adminSidebar)
        <link
            rel="preload"
            href="{{ asset(AdminLocaleFont::preloadPath($locale)) }}"
            as="font"
            type="font/woff2"
            crossorigin
        />
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    @livewireStyles
</head>
<body
    class="app-shell antialiased"
    data-mobile-tabbar="0"
    @if ($business && $adminSidebar)
        x-data="{ navDrawerOpen: false, get darkTheme() { return $store.adminTheme?.dark ?? false } }"
        @keydown.escape.window="navDrawerOpen = false"
    @else
        x-data="{}"
    @endif
>
    <div class="livewire-loading-bar" aria-hidden="true"></div>
    <x-toast-stack @class(['has-app-tab-bar' => $business && $adminSidebar]) />

    @if($business)
        @if ($adminSidebar && $adminMenu)
        <aside class="app-sidebar app-sidebar--admin" aria-label="{{ __('admin_nav.aria') }}">
            <x-admin.sidebar-nav :menu="$adminMenu" variant="desktop" />
        </aside>
        @endif

        <div class="app-main @if($adminSidebar && $adminMenu) app-main--with-sidebar @endif">
            @if ($adminSidebar && $adminMenu)
                <div
                    x-show="navDrawerOpen"
                    x-cloak
                    class="app-nav-drawer-backdrop"
                    @click="navDrawerOpen = false"
                    aria-hidden="true"
                ></div>
                <div
                    x-show="navDrawerOpen"
                    x-cloak
                    class="app-nav-drawer"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ __('admin_nav.aria') }}"
                    @click.outside="navDrawerOpen = false"
                >
                    <button
                        type="button"
                        class="app-nav-drawer__close tap-action-muted min-h-11 min-w-11"
                        @click="navDrawerOpen = false"
                        aria-label="{{ __('admin_nav.close_menu') }}"
                    >
                        <x-app.icon name="x" class="h-5 w-5" />
                    </button>
                    <x-admin.sidebar-nav :menu="$adminMenu" variant="drawer" />
                </div>
            @endif
            <header class="app-topbar">
                <div class="app-topbar__inner">
                    @if ($adminSidebar && $adminMenu)
                        <button
                            type="button"
                            class="app-topbar__menu-btn shrink-0"
                            @click="navDrawerOpen = true"
                            aria-label="{{ __('admin_nav.open_menu') }}"
                        >
                            <x-app.icon name="menu" class="h-6 w-6" />
                        </button>
                    @endif
                    @if ($adminSidebar && $adminMenu)
                        <p class="app-topbar__page-title min-w-0 flex-1 truncate text-start text-sm font-semibold leading-snug text-[var(--app-text)] sm:text-base" dir="auto">
                            {{ $topbarTitle }}
                        </p>
                    @else
                        <a href="{{ $brandHomeUrl }}" class="app-topbar__brand app-brand-home min-w-0 flex-1">
                            @if ($logoUrl)
                                <img src="{{ asset($logoUrl) }}" alt="{{ __($logoAltKey, ['name' => $displayName]) }}" class="app-topbar__logo shrink-0 rounded-lg object-contain" width="32" height="32">
                            @else
                                <x-plumber.logo-mark class="app-topbar__logo shrink-0 text-[var(--app-accent)]" :name="$displayName" />
                            @endif
                            <div class="app-topbar__brand-text min-w-0">
                                <p class="app-topbar__brand-name">{{ $displayName }}</p>
                                @if($branch)
                                    <p class="app-topbar__brand-subtitle">{{ $branch->name }}</p>
                                @elseif($branchParam && ! $showBranchSwitcher)
                                    <p class="app-topbar__brand-subtitle">{{ $branchParam->name }}</p>
                                @elseif($showBranchSwitcher)
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
                        </a>
                    @endif
                    <x-app.locale-switch :current="$locale" :class="$localeSwitchClass" responsive />
                    @if ($adminSidebar)
                        <button
                            type="button"
                            class="admin-theme-toggle shrink-0"
                            @click="$store.adminTheme.toggle()"
                            :aria-pressed="($store.adminTheme.dark).toString()"
                            title="{{ __('admin_nav.theme_toggle') }}"
                        >
                            <x-app.icon name="moon" class="h-5 w-5" x-show="! $store.adminTheme.dark" />
                            <x-app.icon name="sun" class="h-5 w-5" x-show="$store.adminTheme.dark" x-cloak />
                        </button>
                    @endif
                    <form method="POST" action="{{ route('admin.logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="app-topbar__logout app-topbar__logout--compact">
                            <x-app.icon name="logout" class="h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                            <span class="text-xs sm:text-sm">{{ __('pos.logout') }}</span>
                        </button>
                    </form>
                </div>
            </header>

            <main @class(['app-content', 'app-content--no-tabbar' => $contentUsesNoTabbar])>
                {{ $slot }}
            </main>

        </div>
    @else
        <main class="app-content">{{ $slot }}</main>
    @endif

    <x-toast-prime-script />
    @livewireScriptConfig
</body>
</html>
