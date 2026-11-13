@php
    use App\Support\EnabledLocales;

    $locale = app()->getLocale();
    $query = request()->query();
    $baseUrl = url()->route('foundation.home');
    $locales = EnabledLocales::codesWithLangFile('invoicing');
@endphp
<header class="fl-header">
    <div class="fl-header__inner">
        <a href="{{ $baseUrl }}" class="fl-wordmark">
            <span class="fl-wordmark__mark" aria-hidden="true">
                <svg class="h-full w-full" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="5" width="16" height="3" rx="1" fill="currentColor" opacity="0.9"/>
                    <rect x="4" y="10.5" width="12" height="3" rx="1" fill="currentColor" opacity="0.75"/>
                    <rect x="4" y="16" width="8" height="3" rx="1" fill="currentColor" opacity="0.6"/>
                </svg>
            </span>
            <span class="fl-wordmark__text">{{ __('invoicing.landing_header_wordmark') }}</span>
        </a>
        <nav class="fl-locale shrink-0" aria-label="{{ __('invoicing.locale_switch') }}">
            @foreach ($locales as $code)
                @php
                    $href = $code === 'en' ? $baseUrl : $baseUrl.'?'.http_build_query(array_merge($query, ['locale' => $code]));
                    $active = $locale === $code;
                @endphp
                <a href="{{ $href }}"
                   @class(['fl-locale__link', 'fl-locale__link--active' => $active])
                   @if ($active) aria-current="true" @endif>
                    {{ __('invoicing.locale_'.$code) }}
                </a>
            @endforeach
        </nav>
    </div>
</header>
