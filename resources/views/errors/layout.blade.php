@php
    $sessionLocale = null;
    try {
        $sessionLocale = request()->hasSession() ? request()->session()->get('locale') : null;
    } catch (\Throwable) {
        $sessionLocale = null;
    }
    $errorLocale = \App\Support\EnabledLocales::resolve(
        request()->query('locale')
            ?? $sessionLocale
            ?? \App\Support\LocaleCookie::resolveLocale()
            ?? app()->getLocale()
            ?? \App\Business\BusinessContext::get()?->settings['default_locale']
    );
    app()->setLocale($errorLocale);

    $errorBusiness = \App\Business\BusinessContext::get()
        ?? \App\Models\BusinessSetting::query()->find(1);
    $errorBusinessName = $errorBusiness
        ? \App\Support\BusinessSettingDisplayName::for($errorBusiness, $errorLocale)
        : (string) config('app.name');
    $errorHeading = $heading ?? __('errors.generic_heading');
    $errorDocumentTitle = $title ?? ($errorHeading.' | '.$errorBusinessName);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $errorLocale) }}" dir="{{ $errorLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $errorDocumentTitle }}</title>
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
