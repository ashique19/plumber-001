import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { configureLeafletDefaultIcon } from './leaflet-default-icon.js';

configureLeafletDefaultIcon(L);

function buildPinIcon() {
    return L.divIcon({
        className: 'cafe-map-pin-wrap',
        html: '<span class="cafe-map-pin" aria-hidden="true"></span>',
        iconSize: [28, 36],
        iconAnchor: [14, 34],
        popupAnchor: [0, -30],
    });
}

function popupHtml(marker, labels, isRtl) {
    const dir = isRtl ? 'rtl' : 'ltr';
    const directions = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(`${marker.lat},${marker.lng}`)}`;
    const name = escapeHtml(marker.name);
    const address = escapeHtml(marker.address);

    return `<div class="cafe-map-popup" dir="${dir}">
        <p class="cafe-map-popup__title">${name}</p>
        <p class="cafe-map-popup__address">${address}</p>
        <a class="cafe-map-popup__link" href="${directions}" target="_blank" rel="noopener noreferrer">${escapeHtml(labels.directions)}</a>
    </div>`;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function setInteraction(map, enabled) {
    const method = enabled ? 'enable' : 'disable';
    map.dragging[method]();
    map.touchZoom[method]();
    map.doubleClickZoom[method]();
    map.scrollWheelZoom[method]();
    map.boxZoom[method]();
    map.keyboard[method]();
    if (enabled) {
        map.tap?.enable?.();
    } else {
        map.tap?.disable?.();
    }
}

/**
 * @param {HTMLElement} root
 */
export function mountBranchMap(root) {
    const host = root.querySelector('[data-cafe-map-host]');
    const unlock = root.querySelector('[data-cafe-map-unlock]');
    if (! host) {
        return;
    }

    let markers = [];
    try {
        markers = JSON.parse(root.dataset.markers || '[]');
    } catch {
        return;
    }
    if (markers.length === 0) {
        return;
    }

    const isRtl = root.dataset.mapRtl === '1';
    let labels = { unlock: 'Tap to explore map', directions: 'Directions' };
    try {
        labels = { ...labels, ...JSON.parse(root.dataset.mapLabels || '{}') };
    } catch {
        //
    }

    const map = L.map(host, {
        zoomControl: true,
        scrollWheelZoom: false,
        attributionControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a>',
    }).addTo(map);

    const icon = buildPinIcon();
    const latLngs = [];
    markers.forEach((marker) => {
        const point = L.latLng(marker.lat, marker.lng);
        latLngs.push(point);
        L.marker(point, { icon })
            .addTo(map)
            .bindPopup(popupHtml(marker, labels, isRtl), { className: 'cafe-map-popup-shell', maxWidth: 280 });
    });

    if (latLngs.length === 1) {
        map.setView(latLngs[0], 14);
    } else {
        map.fitBounds(L.latLngBounds(latLngs), { padding: [36, 36], maxZoom: 13 });
    }

    setInteraction(map, false);

    const isCoarseTouch = window.matchMedia('(pointer: coarse)').matches;

    if (isCoarseTouch && unlock) {
        unlock.addEventListener('click', () => {
            root.classList.remove('cafe-branch-map--locked');
            unlock.setAttribute('hidden', '');
            setInteraction(map, true);
            map.invalidateSize();
        });
    } else {
        root.classList.remove('cafe-branch-map--locked');
        unlock?.remove();
        setInteraction(map, true);
        map.scrollWheelZoom.disable();
        host.addEventListener(
            'wheel',
            (event) => {
                if (event.ctrlKey || event.metaKey) {
                    map.scrollWheelZoom.enable();
                }
            },
            { passive: true },
        );
        host.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());
    }

    requestAnimationFrame(() => map.invalidateSize());
}
