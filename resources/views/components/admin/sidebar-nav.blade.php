@props(['menu', 'variant' => 'desktop'])

@php
    /** @var array{businessName: string, groups: list<array<string, mixed>>} $menu */
@endphp

<div class="admin-sidebar-nav flex h-full flex-col" data-variant="{{ $variant }}">
    <div class="admin-sidebar-nav__header flex items-center gap-2 border-b px-3 py-4" style="border-color: var(--app-border)">
        <x-plumber.logo-mark class="h-9 w-9 shrink-0 text-[var(--app-accent)]" :name="$menu['businessName']" />
        <span class="admin-sidebar-nav__brand-name" title="{{ $menu['businessName'] }}">{{ $menu['businessName'] }}</span>
    </div>
    <nav class="admin-sidebar-nav__scroll flex flex-1 flex-col gap-0.5 overflow-y-auto p-2" aria-label="{{ __('admin_nav.aria') }}">
        @foreach ($menu['groups'] as $block)
            @if ($block['type'] === 'link')
                @php $item = $block['items'][0]; @endphp
                @include('components.admin.sidebar-nav-item', ['item' => $item, 'nested' => false])
            @else
                <div
                    class="admin-sidebar-nav__group"
                    x-data="{ open: {{ $block['expanded'] ? 'true' : 'false' }} }"
                >
                    <button
                        type="button"
                        class="admin-sidebar-nav__group-toggle app-nav-link w-full justify-between"
                        @click="open = !open"
                        :aria-expanded="open"
                    >
                        <span class="truncate">{{ $block['label'] }}</span>
                        <x-app.icon name="chevron" class="h-4 w-4 shrink-0 transition-transform" x-bind:class="open ? 'rotate-90 rtl:-rotate-90' : '-rotate-90 rtl:rotate-90'" />
                    </button>
                    <div class="admin-sidebar-nav__children space-y-0.5 ps-2" x-show="open" x-cloak>
                        @foreach ($block['items'] as $item)
                            @include('components.admin.sidebar-nav-item', ['item' => $item, 'nested' => true])
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
</div>