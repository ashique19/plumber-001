<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? __('errors.title') }}</title>
    @vite(['resources/css/app.css', 'resources/css/error-pages.css'])
</head>
<body class="error-page">
    <main class="error-card" role="alert">
        <p class="error-code">{{ $code ?? '—' }}</p>
        <h1 class="error-heading">{{ $heading ?? __('errors.generic_heading') }}</h1>
        <p class="error-lead">{{ $message ?? __('errors.generic_lead') }}</p>
        <a href="{{ url('/') }}" class="error-home">{{ __('errors.back_home') }}</a>
    </main>
</body>
</html>
