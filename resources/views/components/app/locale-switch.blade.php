@props(['current'])
@php
    use App\Support\EnabledLocales;

    $query = request()->query();
    $locales = EnabledLocales::codes();
@endphp
<nav {{ $attributes->merge(['class' => 'flex flex-wrap gap-1 rounded-full border p-0.5 text-[0.65rem] font-semibold sm:text-xs']) }} style="border-color: var(--app-border); background: var(--app-surface);" aria-label="{{ __('plumber.locale_switch') }}">
    @foreach ($locales as $code)
        @php
            $labelKey = 'invoicing.locale_'.$code;
            $label = __($labelKey);
            if ($label === $labelKey) {
                $label = config("locales.supported.{$code}.name", strtoupper($code));
            }
        @endphp
        <a href="{{ request()->url() . '?' . http_build_query(array_merge($query, ['locale' => $code])) }}"
           hreflang="{{ $code }}"
           @class([
               'flex min-h-9 min-w-9 items-center justify-center rounded-full px-2 transition',
               'bg-[var(--app-text)] text-[var(--app-bg)]' => $current === $code,
           ])
           @if ($current === $code) aria-current="true" @endif>
            {{ $label }}
        </a>
    @endforeach
</nav>
