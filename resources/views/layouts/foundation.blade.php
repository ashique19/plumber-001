<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $dir ?? (config('locales.supported.'.app()->getLocale().'.dir') ?? 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $seo = is_array($seo ?? null) ? $seo : [];
    @endphp
    @if ($seo !== [])
        <title>{{ $seo['title'] ?? config('app.name', 'Invoicing') }}</title>
        <meta name="description" content="{{ $seo['description'] ?? '' }}">
        <meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
        <link rel="canonical" href="{{ $seo['canonical'] ?? url()->current() }}">
        <meta property="og:type" content="{{ data_get($seo, 'og.type', 'website') }}">
        <meta property="og:title" content="{{ data_get($seo, 'og.title') }}">
        <meta property="og:description" content="{{ data_get($seo, 'og.description') }}">
        <meta property="og:url" content="{{ data_get($seo, 'og.url') }}">
        @if (filled(data_get($seo, 'og.image')))
            <meta property="og:image" content="{{ data_get($seo, 'og.image') }}">
        @endif
    @else
        <title>{{ config('app.name', 'Invoicing') }}</title>
    @endif
    @vite(['resources/css/foundation-landing.css'])
    @livewireStyles
</head>
<body class="fl-shell flex flex-col" data-foundation-landing>
    <x-foundation.site-header />
    <main class="flex-1">
        {{ $slot }}
    </main>
    <x-foundation.site-footer />
    @livewireScripts
</body>
</html>
