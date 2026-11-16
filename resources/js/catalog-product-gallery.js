/** Persist catalog products grid vs list per staff user in localStorage. */
export function registerCatalogProductGallery(Alpine) {
    Alpine.data('catalogProductGallery', (staffUserId) => ({
        storageKey: `catalog.productsView.${staffUserId}`,

        init() {
            if (! staffUserId) {
                return;
            }

            const saved = window.localStorage.getItem(this.storageKey);
            if (saved === 'gallery' || saved === 'list') {
                this.$wire.set('productsView', saved);
            }

            this.$watch('$wire.productsView', (mode) => {
                if (mode === 'gallery' || mode === 'list') {
                    window.localStorage.setItem(this.storageKey, mode);
                }
            });
        },

        setView(mode) {
            if (mode !== 'gallery' && mode !== 'list') {
                return;
            }

            this.$wire.set('productsView', mode);
        },
    }));
}