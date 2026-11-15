@props([
    'stickyMobile' => true,
])

<div @class([
    'form-actions flex flex-wrap items-center gap-2',
    'form-actions--sticky-mobile' => $stickyMobile,
]) {{ $attributes }}>
    {{ $slot }}
</div>