@props(['title', 'lead' => null, 'icon' => null])

<section {{ $attributes->merge(['class' => 'form-section app-card p-4 sm:p-5']) }}>
    <header class="form-section__head">
        @if($icon)
            <x-app.icon :name="$icon" class="h-5 w-5 text-[var(--app-accent)]" />
        @endif
        <div>
            <h2 class="form-section__title">{{ $title }}</h2>
            @if($lead)
                <p class="form-section__lead">{{ $lead }}</p>
            @endif
        </div>
    </header>
    <div class="form-section__body mt-4">
        {{ $slot }}
    </div>
</section>
