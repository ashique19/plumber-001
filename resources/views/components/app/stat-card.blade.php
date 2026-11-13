@props(['label', 'value', 'icon' => 'chart'])
<div {{ $attributes->merge(['class' => 'app-stat-card']) }}>
    <div class="flex items-start justify-between gap-2">
        <p class="app-stat-label">{{ $label }}</p>
        <x-app.icon :name="$icon" class="h-5 w-5 text-[var(--app-accent)]" />
    </div>
    <p class="app-stat-value mt-2">{{ $value }}</p>
</div>
