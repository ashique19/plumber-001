@props(['current', 'responsive' => false])
@php
    use App\Support\EnabledLocales;

    $query = request()->query();
    $locales = EnabledLocales::codes();
    $currentLabelKey = 'invoicing.locale_'.$current;
    $currentLabel = __($currentLabelKey);
    if ($currentLabel === $currentLabelKey) {
        $currentLabel = config("locales.supported.{$current}.name", strtoupper($current));
    }
@endphp
@if ($responsive)
    <nav
        {{ $attributes->class(['app-locale-switch app-locale-switch--pills hidden lg:flex']) }}
        style="border-color: var(--app-border); background: var(--app-surface);"
        aria-label="{{ __('plumber.locale_switch') }}"
    >
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
                   'flex min-h-9 min-w-9 items-center justify-center rounded-full px-2 text-[0.65rem] font-semibold transition sm:text-xs',
                   'bg-[var(--app-text)] text-[var(--app-bg)]' => $current === $code,
               ])
               @if ($current === $code) aria-current="true" @endif>
                {{ $label }}
            </a>
        @endforeach
    </nav>
    <details class="app-locale-switch-menu lg:hidden">
        <summary class="app-locale-switch-menu__trigger" aria-label="{{ __('plumber.locale_switch') }}">
            <span class="app-locale-switch-menu__value">{{ $currentLabel }}</span>
            <x-app.icon name="chevron" class="h-4 w-4 shrink-0 rotate-90 opacity-70" />
        </summary>
        <ul class="app-locale-switch-menu__list" role="list">
            @foreach ($locales as $code)
                @php
                    $labelKey = 'invoicing.locale_'.$code;
                    $label = __($labelKey);
                    if ($label === $labelKey) {
                        $label = config("locales.supported.{$code}.name", strtoupper($code));
                    }
                @endphp
                <li>
                    <a href="{{ request()->url() . '?' . http_build_query(array_merge($query, ['locale' => $code])) }}"
                       hreflang="{{ $code }}"
                       @class(['app-locale-switch-menu__option', 'is-active' => $current === $code])
                       @if ($current === $code) aria-current="true" @endif>
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@else
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
@endif