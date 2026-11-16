/**
 * Search typeahead shell (invoice/quotation/purchase). Quick-add “+” uses explicit
 * Livewire calls so the first click after a full page load is not lost to hydration.
 */
export function registerAdminTypeaheadControl(Alpine) {
    Alpine.data('adminTypeaheadControl', (config = {}) => ({
        panelOpen: Boolean(config.resultsOpen),
        addAction: config.addAction ?? null,

        init() {
            this.$watch('panelOpen', (open) => {
                if (! open) {
                    return;
                }
            });
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

            const call = () => {
                const wire = this.resolveWire();
                if (! wire) {
                    return false;
                }

                const setMatch = typeof action === 'string'
                    ? action.match(/^\$set\('([^']+)',\s*(true|false|null|\d+|'[^']*')\)$/)
                    : null;
                if (setMatch && typeof wire.set === 'function') {
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

                    wire.set(prop, value);

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
            };

            if (call()) {
                return;
            }

            queueMicrotask(() => {
                if (call()) {
                    return;
                }

                const onReady = () => {
                    call();
                };

                if (typeof Livewire !== 'undefined') {
                    document.addEventListener('livewire:init', onReady, { once: true });
                    document.addEventListener('livewire:navigated', onReady, { once: true });
                }

                requestAnimationFrame(() => call());
            });
        },
    }));
}