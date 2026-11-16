import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registerAdminLocationPicker, wireAdminLocationPickerLifecycle } from './admin-location-picker.js';
import { registerAdminLogoUpload } from './admin-logo-upload.js';
import { registerCatalogProductImageUpload } from './product-image-upload.js';
import { registerPosQtyStepper } from './pos-qty-stepper.js';
import { registerInvoiceCatalogPicker } from './invoice-catalog-picker.js';
import { registerAdminModalScroll, wireAdminModalScrollLivewire } from './admin-modal-scroll-lock.js';
import { registerAdminTypeaheadControl, wireAdminQuickAddDelegation } from './admin-typeahead-control.js';
import { registerAdminTheme } from './admin-theme.js';
import { registerToastStack } from './toast-stack.js';
import { wireLivewireRequestFeedback } from './livewire-feedback.js';

window.Alpine = Alpine;

/** @type {() => number} */
window.__livewireInFlight = () => 0;

const toastCopy = window.__toastCopy ?? {};

registerToastStack(Alpine, toastCopy);
registerAdminLocationPicker(Alpine);
registerAdminLogoUpload(Alpine);
registerCatalogProductImageUpload(Alpine);
registerPosQtyStepper(Alpine);
registerInvoiceCatalogPicker(Alpine);
registerAdminTheme(Alpine);
registerAdminModalScroll(Alpine);
registerAdminTypeaheadControl(Alpine);
wireAdminQuickAddDelegation();

document.addEventListener('livewire:init', () => {
    wireAdminModalScrollLivewire(Livewire);
    wireAdminLocationPickerLifecycle();
    wireLivewireRequestFeedback(window.__toastCopy ?? {});

    const bar = document.querySelector('.livewire-loading-bar');
    if (! bar) {
        return;
    }

    let requests = 0;

    /** @type {() => number} */
    window.__livewireInFlight = () => requests;

    Livewire.hook('request', ({ succeed, fail }) => {
        requests += 1;
        bar.classList.add('scale-x-[0.45]');

        const done = () => {
            requests = Math.max(0, requests - 1);
            if (requests === 0) {
                bar.classList.remove('scale-x-[0.45]', 'scale-x-[0.92]');
            }
        };

        succeed(done);
        fail(done);
    });
});

const hasBundledLivewire = typeof window.livewireScriptConfig !== 'undefined';

if (hasBundledLivewire) {
  Livewire.start();
} else {
    const bootStandaloneAlpine = () => {
        if (document.getElementById('toast-stack')) {
            Alpine.start();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootStandaloneAlpine);
    } else {
        bootStandaloneAlpine();
    }
}
