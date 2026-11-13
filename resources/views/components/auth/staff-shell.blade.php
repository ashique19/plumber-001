@props(['business', 'title', 'showBrand' => true])
@php
    $locale = app()->getLocale();
    $dir = config("locales.supported.{$locale}.dir", 'ltr');
    $displayName = \App\Support\BusinessSettingDisplayName::for($business, $locale);
    $colors = $business->themeColors();
    $logoUrl = data_get($business->settings, 'theme.logo_url');
    $hero = asset('images/plumber/hero.webp');
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
    <title>{{ $title }} — {{ $displayName }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-shell min-h-dvh font-sans text-plumber-navy antialiased plumber-auth">
    <div class="auth-shell__bg" aria-hidden="true">
        <img src="{{ $hero }}" alt="" class="h-full w-full object-cover" width="1600" height="900" loading="eager" decoding="async" fetchpriority="high">
        <div class="auth-shell__gradient auth-shell__gradient--plumber"></div>
    </div>

    <div class="auth-shell__viewport">
        <header class="auth-shell__header" role="banner">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <p class="sr-only">{{ $displayName }}</p>
                <x-app.locale-switch :current="$locale" class="auth-locale-switch ms-auto" />
            </div>
        </header>

        <x-toast-stack />

        <main class="auth-shell__main" id="main-content">
            <div class="auth-glass-card p-5 sm:p-8">
                @if ($showBrand)
                    <div class="auth-card-brand mb-5 sm:mb-6">
                        @if ($logoUrl)
                            <img src="{{ asset($logoUrl) }}" alt="" class="auth-card-brand__logo" width="56" height="56">
                        @else
                            <x-plumber.logo-mark class="auth-card-brand__logo h-14 w-14 text-plumber-navy" aria-hidden="true" />
                        @endif
                        <p class="auth-card-brand__name">{{ $displayName }}</p>
                    </div>
                @endif
                {{ $slot }}
            </div>
        </main>

        <footer class="auth-shell__footer px-4 py-4 text-center text-xs text-white sm:px-6 sm:py-6" role="contentinfo">
            <p class="font-medium text-white drop-shadow-sm">© {{ date('Y') }} {{ $displayName }}</p>
        </footer>
    </div>
</body>
</html>
