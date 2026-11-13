import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

/**
 * @param {typeof import('leaflet')} L
 */
export function configureLeafletDefaultIcon(L) {
    if (! L?.Icon?.Default) {
        return;
    }

    const defaults = {
        iconUrl: markerIcon,
        iconRetinaUrl: markerIcon2x,
        shadowUrl: markerShadow,
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        tooltipAnchor: [16, -28],
        shadowSize: [41, 41],
    };

    L.Icon.Default.mergeOptions(defaults);
  L.Icon.Default.imagePath = '';

    L.Icon.Default.prototype._detectIconPath = function () {
        return '';
    };

    L.Icon.Default.prototype._getIconUrl = function (name) {
        if (name === 'icon') {
            return this.options.iconUrl ?? defaults.iconUrl;
        }
        if (name === 'icon-2x') {
            return this.options.iconRetinaUrl ?? defaults.iconRetinaUrl;
        }
        if (name === 'shadow') {
            return this.options.shadowUrl ?? defaults.shadowUrl;
        }

        return '';
    };
}
