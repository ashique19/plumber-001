@props(['business', 'title'])
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
        <img src="{{ $hero }}" alt="" class="h-full w-full object-cover" width="1600" height="900" loading="eager" decoding="async">
        <div class="auth-shell__gradient auth-shell__gradient--plumber"></div>
    </div>

    <header class="auth-shell__header" role="banner">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <div class="flex min-h-11 min-w-0 items-center gap-2">
                @if ($logoUrl)
                    <img src="{{ asset($logoUrl) }}" alt="" class="h-9 w-9 rounded-lg object-contain ring-1 ring-white/30" width="36" height="36">
                @else
                    <x-plumber.logo-mark class="h-9 w-9 text-white" aria-hidden="true" />
                @endif
                <span class="truncate text-sm font-semibold text-white sm:text-base">{{ $displayName }}</span>
            </div>
            <x-app.locale-switch :current="$locale" />
        </div>
    </header>

    <x-toast-stack />
    <main class="auth-shell__main flex flex-1 flex-col items-center justify-center px-4 py-8 sm:px-6">
        <div class="auth-glass-card w-full max-w-md p-6 sm:p-8">
            {{ $slot }}
        </div>
    </main>

    <footer class="auth-shell__footer px-4 py-6 text-center text-xs text-white/75 sm:px-6" role="contentinfo">
        <p class="font-medium text-white">{{ $displayName }} · © {{ date('Y') }}</p>
    </footer>
</body>
</html>
