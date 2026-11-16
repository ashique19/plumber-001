<div class="admin-dashboard pb-24 lg:pb-8" data-testid="admin-dashboard">
    <header class="mb-3">
        <h1 class="app-page-title">{{ __('dashboard.title') }}</h1>
        <p class="app-page-lead">{{ __('dashboard.subtitle') }}</p>
    </header>

    @if (count($actionTiles) > 0)
        <section
            class="dashboard-action-board app-card mb-4 overflow-hidden border border-[var(--app-border)] p-0 shadow-sm"
            aria-label="{{ __('dashboard.quick_actions') }}"
        >
            <header class="dashboard-action-board__head">
                <h2 class="dashboard-action-board__title">{{ __('dashboard.overview') }}</h2>
            </header>
            <div class="dashboard-action-board__grid">
                @foreach ($actionTiles as $tile)
                    <x-admin.dashboard-action-tile
                        :emoji="$tile['emoji']"
                        :label="$tile['label']"
                        :subtitle="$tile['subtitle']"
                        :href="$tile['href']"
                        :enabled="$tile['enabled']"
                    />
                @endforeach
            </div>
        </section>
    @endif
</div>