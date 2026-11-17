<div class="admin-dashboard pb-24 lg:pb-8" data-testid="admin-dashboard">
    @if (count($actionTiles) > 0)
        <section class="dashboard-action-grid mb-4" aria-label="{{ __('dashboard.quick_actions') }}">
            @foreach ($actionTiles as $tile)
                <x-admin.dashboard-action-tile
                    :emoji="$tile['emoji']"
                    :label="$tile['label']"
                    :subtitle="$tile['subtitle']"
                    :href="$tile['href']"
                    :enabled="$tile['enabled']"
                />
            @endforeach
        </section>
    @endif
</div>