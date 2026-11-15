@php
    $class = 'app-nav-link admin-sidebar-nav__item';
    if ($nested) {
        $class .= ' admin-sidebar-nav__item--nested';
    }
    if ($item['active']) {
        $class .= ' app-nav-link--active';
    }
    if (! $item['enabled']) {
        $class .= ' admin-sidebar-nav__item--disabled';
    }
@endphp

@if ($item['enabled'] && $item['href'])
    <a href="{{ $item['href'] }}" class="{{ $class }}">{{ $item['label'] }}</a>
@else
    <span class="{{ $class }}" aria-disabled="true">{{ $item['label'] }}</span>
@endif