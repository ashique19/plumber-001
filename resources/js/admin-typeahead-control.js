/**
 * Search typeahead shell (invoice/quotation/purchase). Quick-add “+” uses explicit
 * Livewire calls so the first click after a full page load is not lost to hydration.
 */

function parseSetQuickAddAction(action) {
    if (typeof action !== 'string') {
        return null;
    }

    const setMatch = action.match(/^\$set\('([^']+)',\s*(true|false|null|\d+|'[^']*')\)$/);
    if (! setMatch) {
        return null;
    }

    const [, prop, raw] = setMatch;
    const value = raw === 'true'
        ? true
        : raw === 'false'
            ? false
            : raw === 'null'
                ? null
                : raw.startsWith("'")
                    ? raw.slice(1, -1)
                    : Number(raw);

    return { prop, value };
}

function runQuickAddAction(action, hostEl) {
    if (! action || ! hostEl) {
        return false;
    }

    const wireId = hostEl.closest('[wire\\:id]')?.getAttribute('wire:id');
    if (! wireId || typeof Livewire === 'undefined') {
        return false;
    }

    const wire = Livewire.find(wireId);
    if (! wire) {
        return false;
    }

    const setPayload = parseSetQuickAddAction(action);
    if (setPayload && typeof wire.set === 'function') {
        wire.set(setPayload.prop, setPayload.value);

        return true;
    }

    if (typeof wire.call === 'function') {
        wire.call(action);

        return true;
    }

    if (typeof wire[action] === 'function') {
        wire[action]();

        return true;
    }

    return false;
}

function scheduleQuickAddRetries(action, hostEl) {
    const attempt = () => runQuickAddAction(action, hostEl);

    if (attempt()) {
        return;
    }

    queueMicrotask(() => {
        if (attempt()) {
            return;
        }

        const onReady = () => attempt();

        document.addEventListener('livewire:init', onReady, { once: true });
        document.addEventListener('livewire:navigated', onReady, { once: true });

        requestAnimationFrame(attempt);
        window.setTimeout(attempt, 0);
        window.setTimeout(attempt, 48);
    });
}

/** Capture-phase click so the first tap is not lost to Alpine panel toggles or morph. */
export function wireAdminQuickAddDelegation() {
    if (document.documentElement.dataset.adminQuickAddDelegation === '1') {
        return;
    }

    document.documentElement.dataset.adminQuickAddDelegation = '1';

    document.addEventListener(
        'click',
        (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const button = target.closest('button[data-admin-quick-add], button[data-admin-livewire-call]');
            if (! button) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            const action = button.getAttribute('data-admin-quick-add')
                ?? button.getAttribute('data-admin-livewire-call');
            scheduleQuickAddRetries(action, button);
        },
        true,
    );
}

const createQueryDeepLinks = [
    {
        matchPath: (path) => path.includes('/admin/catalog'),
        create: 'product',
        livewireCall: 'startProduct',
        modalReadySelector: '#catalog-product-form',
        pageRootSelector: '.catalog-admin-page',
    },
    {
        matchPath: (path) => path.includes('/admin/customers'),
        create: 'customer',
        livewireCall: 'startCustomer',
        modalReadySelector: 'input[name="customerName"]',
        pageRootSelector: '.customer-hub',
    },
];

function wireRootForPage(rootSelector) {
    const root = document.querySelector(rootSelector);

    return root?.closest('[wire\\:id]') ?? null;
}

function tryOpenCreateDeepLink() {
    if (typeof Livewire === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    const path = url.pathname;

    for (const link of createQueryDeepLinks) {
        if (! link.matchPath(path)) {
            continue;
        }

        if (url.searchParams.get('create') !== link.create) {
            continue;
        }

        if (document.querySelector(link.modalReadySelector)) {
            continue;
        }

        const host = wireRootForPage(link.pageRootSelector);
        if (! host) {
            continue;
        }

        scheduleQuickAddRetries(link.livewireCall, host);
    }
}

/** Ensure ?create= deep links open after Livewire hydration when mount could not. */
export function wireAdminCreateQueryDeepLinks() {
    const run = () => {
        requestAnimationFrame(() => tryOpenCreateDeepLink());
        window.setTimeout(() => tryOpenCreateDeepLink(), 48);
    };

    document.addEventListener('livewire:initialized', run, { once: true });
    document.addEventListener('livewire:navigated', run);
}

export function registerAdminTypeaheadControl(Alpine) {
    Alpine.data('adminTypeaheadControl', (config = {}) => ({
        panelOpen: Boolean(config.resultsOpen),
        addAction: config.addAction ?? null,

        init() {
            const syncFromDom = () => {
                const openFlag = this.$el.getAttribute('data-typeahead-results-open');
                if (openFlag === '0') {
                    this.panelOpen = false;
                }
            };

            syncFromDom();

            if (typeof Livewire !== 'undefined') {
                Livewire.hook('commit', ({ succeed }) => {
                    succeed(() => {
                        requestAnimationFrame(() => syncFromDom());
                    });
                });
            }
        },

        openPanel() {
            this.panelOpen = true;
        },

        closePanel() {
            this.panelOpen = false;
        },

        resolveWire() {
            if (this.$wire) {
                return this.$wire;
            }

            const host = this.$el.closest('[wire\\:id]');
            const wireId = host?.getAttribute('wire:id');
            if (wireId && typeof Livewire !== 'undefined') {
                return Livewire.find(wireId);
            }

            return null;
        },

        invokeQuickAdd() {
            const action = this.addAction;
            if (! action) {
                return;
            }

            scheduleQuickAddRetries(action, this.$el);
        },
    }));
}