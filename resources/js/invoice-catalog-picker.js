/**
 * Searchable catalog product picker for invoice line items (sheet on mobile, panel on desktop).
 */
export function registerInvoiceCatalogPicker(Alpine) {
    Alpine.data('invoiceCatalogPicker', (config) => ({
        lineIndex: config.lineIndex,
        products: config.products ?? [],
        open: false,
        query: '',

        get filtered() {
            const q = this.query.trim().toLowerCase();
            if (q === '') {
                return this.products;
            }

            return this.products.filter((p) => (p.search ?? '').includes(q));
        },

        toggle() {
            this.open = ! this.open;
            if (! this.open) {
                this.query = '';
            }
        },

        close() {
            this.open = false;
            this.query = '';
        },

        pick(productId) {
            if (typeof this.$wire?.applyCatalogProduct === 'function') {
                this.$wire.applyCatalogProduct(this.lineIndex, productId);
            }
            this.close();
        },
    }));
}