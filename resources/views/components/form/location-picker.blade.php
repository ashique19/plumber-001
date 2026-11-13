@props([
    'latWire' => 'lat',
    'lngWire' => 'lng',
    'mapsUrlWire' => 'mapsUrl',
    'defaultLat' => 24.7136,
    'defaultLng' => 46.6753,
])

@php
    $pickerLabels = [
        'search' => __('business.map.search_placeholder'),
        'use_location' => __('business.map.use_my_location'),
        'searching' => __('business.map.searching'),
        'coords' => __('business.map.coords_hint'),
    ];
    $userAgent = config('app.name', 'TaskenLab').'/1.0 ('.parse_url(config('app.url', 'https://localhost'), PHP_URL_HOST).')';
@endphp

<div {{ $attributes->class(['admin-location-picker']) }}>
    <div
        wire:ignore
        class="admin-location-picker__map-wrap"
        style="position: relative; z-index: 0; isolation: isolate"
        x-data="adminLocationPicker({
            lat: @entangle($latWire).live,
            lng: @entangle($lngWire).live,
            mapsUrl: @entangle($mapsUrlWire).live,
            advancedOpen: false,
            defaultLat: {{ $defaultLat }},
            defaultLng: {{ $defaultLng }},
            labels: @js($pickerLabels),
            attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\' rel=\'noopener noreferrer\'>OpenStreetMap</a> &middot; {{ __('business.map.nominatim_credit') }}',
            nominatimUserAgent: @js($userAgent),
        })"
    >
        <div class="admin-location-picker__toolbar">
            <div class="admin-location-picker__search">
                <input
                    type="search"
                    class="admin-location-picker__search-input"
                    x-model="search"
                    @input="onSearchInput()"
                    :placeholder="labels.search"
                    autocomplete="off"
                />
                <div
                    x-show="searchOpen && searchResults.length > 0"
                    x-cloak
                    class="admin-location-picker__results"
                    @click.outside="searchOpen = false"
                >
                    <template x-for="row in searchResults" :key="row.place_id ?? row.osm_id ?? row.display_name">
                        <button type="button" class="admin-location-picker__result" x-text="row.display_name" @click="pickSearchResult(row)"></button>
                    </template>
                </div>
                <p x-show="searchLoading" class="admin-location-picker__search-hint text-xs text-[var(--app-muted)]" x-cloak x-text="labels.searching"></p>
            </div>
            <button
                type="button"
                class="admin-location-picker__geo tap-action-muted inline-flex min-h-11 shrink-0 items-center gap-1.5 px-3 text-sm font-semibold"
                @click="useMyLocation()"
                :aria-label="labels.use_location"
            >
                <x-app.icon name="external" class="h-4 w-4 shrink-0 opacity-80" aria-hidden="true" />
                <span class="hidden sm:inline" x-text="labels.use_location"></span>
            </button>
        </div>

        <div x-ref="mapHost" class="admin-location-picker__map" role="application" aria-label="{{ __('business.map.region_label') }}"></div>

        <p class="admin-location-picker__coords" x-show="lat && lng" x-cloak>
            <span x-text="labels.coords"></span>
            <span x-text="`${lat}, ${lng}`"></span>
        </p>

        <p class="admin-location-picker__footer">{!! __('business.map.attribution_html') !!}</p>
    </div>

    {{ $advanced ?? '' }}
</div>
