import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { configureLeafletDefaultIcon } from './leaflet-default-icon.js';

configureLeafletDefaultIcon(L);

const RIYADH = { lat: 24.7136, lng: 46.6753 };

function googleMapsUrl(lat, lng) {
    return `https://www.google.com/maps?q=${Number(lat).toFixed(6)},${Number(lng).toFixed(6)}`;
}

function parseCoord(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const n = Number(value);

    return Number.isFinite(n) ? n : null;
}

function hostIsVisible(host) {
    if (! host) {
        return false;
    }
    const rect = host.getBoundingClientRect();

    return rect.width > 0 && rect.height > 0;
}

/**
 * @param {import('alpinejs').Alpine} Alpine
 */
export function registerAdminLocationPicker(Alpine) {
    Alpine.data('adminLocationPicker', (config) => ({
        lat: config.lat,
        lng: config.lng,
        mapsUrl: config.mapsUrl,
        advancedOpen: config.advancedOpen,
        search: '',
        searchOpen: false,
        searchResults: [],
        searchLoading: false,
        labels: config.labels,
        attribution: config.attribution,
        nominatimUserAgent: config.nominatimUserAgent,
        defaultLat: config.defaultLat ?? RIYADH.lat,
        defaultLng: config.defaultLng ?? RIYADH.lng,
        _map: null,
        _marker: null,
        _searchTimer: null,
        _searchAbort: null,
        _observer: null,
        _visibilityHandler: null,

        init() {
            this._visibilityHandler = (event) => {
                const section = event.detail?.section ?? event.detail;
                if (section === 'contact' || section === 'branches' || section === 'team') {
                    this.$nextTick(() => this.refreshMap(true));
                }
            };

            window.addEventListener('business-tab-shown', this._visibilityHandler);
            window.addEventListener('business-map-refresh', this._visibilityHandler);

            this.$nextTick(() => {
                const host = this.$refs.mapHost;
                if (host && typeof IntersectionObserver !== 'undefined') {
                    this._observer = new IntersectionObserver(
                        (entries) => {
                            entries.forEach((entry) => {
                                if (entry.isIntersecting) {
                                    this.refreshMap(true);
                                }
                            });
                        },
                        { threshold: 0.05 },
                    );
                    this._observer.observe(host);
                }

                this.refreshMap(true);
            });

            this.$watch('lat', () => this.syncMarkerFromModel());
            this.$watch('lng', () => this.syncMarkerFromModel());
        },

        destroy() {
            this.teardownMap();
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
            if (this._visibilityHandler) {
                window.removeEventListener('business-tab-shown', this._visibilityHandler);
                window.removeEventListener('business-map-refresh', this._visibilityHandler);
            }
        },

        teardownMap() {
            if (this._map) {
                this._map.remove();
                this._map = null;
                this._marker = null;
            }
        },

        refreshMap(forceBoot = false) {
            const host = this.$refs.mapHost;
            if (! host) {
                return;
            }

            if (! hostIsVisible(host)) {
                if (forceBoot && this._map) {
                    requestAnimationFrame(() => this._map?.invalidateSize(true));
                }

                return;
            }

            if (! this._map || forceBoot) {
                if (this._map) {
                    this.teardownMap();
                }
                this.bootMap();
            } else {
                requestAnimationFrame(() => {
                    this._map?.invalidateSize(true);
                });
            }
        },

        bootMap() {
            const host = this.$refs.mapHost;
            if (! host || this._map || ! hostIsVisible(host)) {
                return;
            }

            const lat = parseCoord(this.lat);
            const lng = parseCoord(this.lng);
            const hasPin = lat !== null && lng !== null;

            this._map = L.map(host, {
                scrollWheelZoom: true,
                attributionControl: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: this.attribution,
            }).addTo(this._map);

            if (hasPin) {
                this.placeMarker(lat, lng, false);
                this._map.setView([lat, lng], 15);
            } else {
                this._map.setView([this.defaultLat, this.defaultLng], 11);
            }

            this._map.on('click', (event) => {
                this.placeMarker(event.latlng.lat, event.latlng.lng, true);
            });

            requestAnimationFrame(() => {
                this._map?.invalidateSize(true);
            });
            window.setTimeout(() => this._map?.invalidateSize(true), 120);
        },

        placeMarker(lat, lng, pushModel) {
            if (! this._map) {
                return;
            }
            const point = L.latLng(lat, lng);
            if (! this._marker) {
                this._marker = L.marker(point, { draggable: true }).addTo(this._map);
                this._marker.on('dragend', () => {
                    const position = this._marker.getLatLng();
                    this.applyCoords(position.lat, position.lng);
                });
            } else {
                this._marker.setLatLng(point);
            }

            if (pushModel) {
                this.applyCoords(lat, lng);
            }
        },

        syncMarkerFromModel() {
            if (! this._map) {
                return;
            }
            const lat = parseCoord(this.lat);
            const lng = parseCoord(this.lng);
            if (lat !== null && lng !== null) {
                this.placeMarker(lat, lng, false);
                if (this._map.getZoom() < 10) {
                    this._map.setView([lat, lng], 15);
                }
            }
        },

        applyCoords(lat, lng) {
            const latR = Math.round(lat * 1_000_000) / 1_000_000;
            const lngR = Math.round(lng * 1_000_000) / 1_000_000;
            this.lat = String(latR);
            this.lng = String(lngR);
            this.mapsUrl = googleMapsUrl(latR, lngR);
        },

        useMyLocation() {
            if (! navigator.geolocation) {
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude } = position.coords;
                    this.refreshMap(true);
                    this.placeMarker(latitude, longitude, true);
                    this._map?.setView([latitude, longitude], 16);
                },
                () => {},
                { enableHighAccuracy: true, timeout: 15_000, maximumAge: 60_000 },
            );
        },

        onSearchInput() {
            clearTimeout(this._searchTimer);
            const query = this.search.trim();
            if (query.length < 3) {
                this.searchResults = [];
                this.searchOpen = false;

                return;
            }
            this._searchTimer = setTimeout(() => this.runSearch(query), 300);
        },

        async runSearch(query) {
            if (this._searchAbort) {
                this._searchAbort.abort();
            }
            this._searchAbort = new AbortController();
            const { signal } = this._searchAbort;

            this.searchLoading = true;
            try {
                const url = new URL('https://nominatim.openstreetmap.org/search');
                url.searchParams.set('format', 'json');
                url.searchParams.set('limit', '5');
                url.searchParams.set('countrycodes', 'sa');
                url.searchParams.set('q', query);
                const response = await fetch(url, {
                    signal,
                    headers: {
                        Accept: 'application/json',
                        'User-Agent': this.nominatimUserAgent,
                    },
                });
                const data = await response.json();
                this.searchResults = Array.isArray(data) ? data : [];
                this.searchOpen = this.searchResults.length > 0;
            } catch (err) {
                if (err?.name === 'AbortError') {
                    return;
                }
                this.searchResults = [];
                this.searchOpen = false;
            } finally {
                if (! signal.aborted) {
                    this.searchLoading = false;
                }
            }
        },

        pickSearchResult(row) {
            const lat = parseFloat(row.lat);
            const lng = parseFloat(row.lon);
            if (! Number.isFinite(lat) || ! Number.isFinite(lng)) {
                return;
            }
            this.refreshMap(true);
            this.placeMarker(lat, lng, true);
            this._map?.setView([lat, lng], 16);
            this.search = row.display_name;
            this.searchOpen = false;
        },
    }));
}

export function wireAdminLocationPickerLifecycle() {
    const refreshAll = () => {
        window.dispatchEvent(new CustomEvent('business-map-refresh', { detail: { section: 'contact' } }));
    };

    document.addEventListener('livewire:navigated', refreshAll);

    if (typeof Livewire !== 'undefined') {
        Livewire.hook('morph.added', ({ el }) => {
            if (el?.querySelector?.('[x-data*="adminLocationPicker"]') || el?.getAttribute?.('x-data')?.includes('adminLocationPicker')) {
                if (window.Alpine?.initTree) {
                    window.Alpine.initTree(el);
                }
                refreshAll();
            }
        });

        Livewire.hook('commit', ({ component, succeed }) => {
            succeed(() => {
                requestAnimationFrame(refreshAll);
            });
        });
    }
}
