@props(['ariaLabel' => ''])

<div {{ $attributes->class(['hub-doc-table-wrap']) }} role="region" @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif>
    <div class="hub-doc-table-scroll">
        <table class="hub-doc-table w-full text-sm">
            {{ $slot }}
        </table>
    </div>
</div>