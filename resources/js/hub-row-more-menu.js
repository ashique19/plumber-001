/** @param {typeof import('alpinejs').Alpine} Alpine */
export function registerHubRowMoreMenu(Alpine) {
    Alpine.store('hubRowMoreMenu', {
        openId: null,
        requestOpen(id) {
            if (this.openId && this.openId !== id) {
                window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: this.openId }));
            }
            this.openId = id;
        },
        requestClose(id) {
            if (this.openId === id) {
                this.openId = null;
            }
            window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: id }));
        },
        closeAll() {
            const previous = this.openId;
            this.openId = null;
            if (previous) {
                window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: previous }));
            }
        },
    });

    bindGlobalHubRowMoreDismiss(Alpine);

    Alpine.data('hubRowMoreMenu', (menuId) => ({
        menuId,
        isOpen: false,
        panelStyle: {},
        placement: 'bottom',

        init() {
            /** @type {(id: string | null) => void} */
            const syncOpen = (id) => {
                const nextOpen = id === this.menuId;
                if (nextOpen === this.isOpen) {
                    return;
                }
                this.isOpen = nextOpen;
                if (nextOpen) {
                    this.$nextTick(() => {
                        if (! this.isTriggerVisible()) {
                            this.$store.hubRowMoreMenu.requestClose(this.menuId);

                            return;
                        }
                        this.schedulePosition();
                    });
                }
            };

            syncOpen(this.$store.hubRowMoreMenu.openId);
            this.$watch('$store.hubRowMoreMenu.openId', syncOpen);

            this.onCloseEvent = (event) => {
                if (event.detail === this.menuId) {
                    this.isOpen = false;
                }
            };
            window.addEventListener('hub-row-more-menu-close', this.onCloseEvent);
        },

        destroy() {
            window.removeEventListener('hub-row-more-menu-close', this.onCloseEvent);
        },

        toggle() {
            if (this.isOpen) {
                this.close();

                return;
            }
            this.$store.hubRowMoreMenu.requestOpen(this.menuId);
        },

        close() {
            this.$store.hubRowMoreMenu.requestClose(this.menuId);
        },

        onMenuItemActivate(event) {
            if (! event.target.closest('a,button,[role="menuitem"]')) {
                return;
            }
            this.close();
        },

        schedulePosition() {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => this.positionPanel());
            });
        },

        isTriggerVisible() {
            const trigger = this.$refs.trigger;
            if (! trigger) {
                return false;
            }
            const style = window.getComputedStyle(trigger);
            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                return false;
            }
            const rect = trigger.getBoundingClientRect();

            return rect.width > 0 && rect.height > 0;
        },

        positionPanel() {
            const trigger = this.$refs.trigger;
            const panel = this.$refs.panel;
            if (! trigger || ! panel || ! this.isTriggerVisible()) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const margin = 8;
            const gap = 4;
            const viewportH = window.innerHeight;
            const viewportW = window.innerWidth;
            const panelWidth = Math.max(panel.offsetWidth || 176, 176);
            const panelHeight = panel.offsetHeight || panel.scrollHeight;

            let top = rect.bottom + gap;
            let placement = 'bottom';
            const spaceBelow = viewportH - rect.bottom - margin;
            const spaceAbove = rect.top - margin;

            if (panelHeight > spaceBelow && spaceAbove > spaceBelow) {
                top = Math.max(margin, rect.top - panelHeight - gap);
                placement = 'top';
            }

            const rtl = document.documentElement.getAttribute('dir') === 'rtl';
            let left;
            if (rtl) {
                left = Math.min(Math.max(margin, rect.left), viewportW - panelWidth - margin);
            } else {
                left = Math.max(margin, Math.min(rect.right - panelWidth, viewportW - panelWidth - margin));
            }

            const maxHeight = placement === 'bottom'
                ? Math.max(120, viewportH - top - margin)
                : Math.max(120, rect.top - margin);

            this.placement = placement;
            this.panelStyle = {
                position: 'fixed',
                top: `${Math.round(top)}px`,
                left: `${Math.round(left)}px`,
                minWidth: '11rem',
                maxHeight: `${Math.round(maxHeight)}px`,
                zIndex: '10050',
            };
        },
    }));
}

/** @param {typeof import('alpinejs').Alpine} Alpine */
function bindGlobalHubRowMoreDismiss(Alpine) {
    if (bindGlobalHubRowMoreDismiss.bound) {
        return;
    }
    bindGlobalHubRowMoreDismiss.bound = true;

    const closeAll = () => {
        Alpine.store('hubRowMoreMenu')?.closeAll();
    };

    window.addEventListener('scroll', closeAll, true);
    window.addEventListener('resize', closeAll);
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
}

/** @param {typeof import('../../vendor/livewire/livewire/dist/livewire.esm').Livewire} Livewire */
export function wireHubRowMoreMenuLivewire(Livewire) {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            requestAnimationFrame(() => {
                window.Alpine?.store('hubRowMoreMenu')?.closeAll();
            });
        });
    });

    document.addEventListener('livewire:navigated', () => {
        window.Alpine?.store('hubRowMoreMenu')?.closeAll();
    });
}