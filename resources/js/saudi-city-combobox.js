/** @param {import('alpinejs').Alpine} Alpine */
export function registerSaudiCityCombobox(Alpine) {
    Alpine.data('saudiCityCombobox', (config = {}) => ({
        options: Array.isArray(config.options) ? config.options : [],
        otherKey: config.otherKey ?? 'other',
        wireProp: config.wireProp ?? '',
        wirePropAr: config.wirePropAr ?? null,
        panelOpen: false,
        query: '',
        isOther: false,

        init() {
            this.syncFromWire();

            if (typeof Livewire !== 'undefined') {
                Livewire.hook('commit', ({ succeed }) => {
                    succeed(() => {
                        requestAnimationFrame(() => this.syncFromWire());
                    });
                });
            }
        },

        wire() {
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

        syncFromWire() {
            const wire = this.wire();
            if (! wire || ! this.wireProp) {
                return;
            }

            const stored = String(wire.get(this.wireProp) ?? '').trim();
            if (stored === '') {
                this.isOther = false;
                this.query = '';

                return;
            }

            const match = this.options.find(
                (option) => option.key !== this.otherKey && option.english?.toLowerCase() === stored.toLowerCase(),
            );

            if (match) {
                this.isOther = false;
                this.query = match.label;
            } else {
                this.isOther = true;
                this.query = '';
            }
        },

        get filteredOptions() {
            const needle = this.query.trim().toLowerCase();

            return this.options.filter((option) => {
                if (option.key === this.otherKey) {
                    return true;
                }

                if (needle === '') {
                    return true;
                }

                return (
                    String(option.label ?? '').toLowerCase().includes(needle)
                    || String(option.english ?? '').toLowerCase().includes(needle)
                );
            });
        },

        openPanel() {
            this.panelOpen = true;
        },

        closePanel() {
            this.panelOpen = false;
        },

        select(option) {
            const wire = this.wire();
            if (! wire) {
                return;
            }

            if (option.key === this.otherKey) {
                this.isOther = true;
                this.query = '';
                this.panelOpen = false;
                wire.set(this.wireProp, '');

                return;
            }

            this.isOther = false;
            this.query = option.label;
            this.panelOpen = false;
            wire.set(this.wireProp, option.english);
            if (this.wirePropAr && option.ar) {
                wire.set(this.wirePropAr, option.ar);
            }
        },

        onOtherInput(event) {
            const wire = this.wire();
            if (! wire) {
                return;
            }

            const value = String(event.target?.value ?? '').trim();
            wire.set(this.wireProp, value);
        },
    }));
}