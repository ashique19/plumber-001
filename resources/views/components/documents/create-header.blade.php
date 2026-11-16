@props([
    'title',
    'backUrl',
])

<header class="document-create-header">
    <a href="{{ $backUrl }}" class="document-create-header__back tap-action-muted" wire:navigate aria-label="{{ __('quotations.back') }}">
        <x-app.icon name="arrow-left" class="h-5 w-5 rtl:rotate-180" />
    </a>
    <h1 class="app-page-title m-0">{{ $title }}</h1>
</header>