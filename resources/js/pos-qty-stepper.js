/**
 * @param {import('alpinejs').Alpine} Alpine
 */
export function registerPosQtyStepper(Alpine) {
    Alpine.data('posQtyStepper', (lineIndex, initialQty) => ({
        lineIndex,
        displayQty: initialQty,
        editing: false,
        draft: initialQty,

        init() {
            const sync = (event) => {
                const detail = event.detail ?? {};
                if (detail.index === this.lineIndex) {
                    this.displayQty = detail.quantity;
                    this.draft = detail.quantity;
                    this.editing = false;
                }
            };

            window.addEventListener('pos-line-qty-updated', sync);
        },

        openEdit() {
            this.editing = true;
            this.draft = this.displayQty;
            this.$nextTick(() => {
                const input = this.$refs.qtyInput;
                if (! input) {
                    return;
                }
                input.focus();
                input.select();
            });
        },

        commitEdit() {
            const parsed = Number.parseInt(String(this.draft), 10);
            this.editing = false;
            if (! Number.isFinite(parsed)) {
                this.draft = this.displayQty;

                return;
            }

            this.$wire.applyLineQuantity(this.lineIndex, parsed);
        },

        onEscape() {
            this.editing = false;
            this.draft = this.displayQty;
        },
    }));
}
