/** @param {typeof import('alpinejs').Alpine} Alpine */
export function registerHubRowMoreMenu(Alpine) {
    Alpine.store('hubRowMoreMenu', {
        openId: null,
        /** @type {Record<string, HTMLElement>} */
        triggers: {},
        /** @type {Record<string, DOMRect>} */
        anchorRects: {},
        scrollGuardUntil: 0,
        requestOpen(id, trigger, rect) {
            if (this.openId && this.openId !== id) {
                window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: this.openId }));
            }
            this.openId = id;
            this.scrollGuardUntil = Date.now() + 450;
            if (trigger instanceof HTMLElement) {
                this.triggers[id] = trigger;
            }
            if (rect && typeof rect.width === 'number' && typeof rect.right === 'number') {
                this.anchorRects[id] = rect;
            }
        },
        requestClose(id) {
            if (this.openId === id) {
                this.openId = null;
            }
            delete this.triggers[id];
            delete this.anchorRects[id];
            window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: id }));
        },
        closeAll() {
            const previous = this.openId;
            this.openId = null;
            this.triggers = {};
            this.anchorRects = {};
            window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: previous }));
        },
        triggerFor(id) {
            const el = this.triggers[id];
            if (el instanceof HTMLElement && isVisibleTrigger(el)) {
                return el;
            }

            return null;
        },
        anchorFor(id) {
            const rect = this.anchorRects[id];
            if (rect && rect.width > 0 && rect.height > 0) {
                return rect;
            }

            return null;
        },
    });

    bindGlobalHubRowMoreDismiss(Alpine);

    Alpine.data('hubRowMoreMenu', (menuId) => ({
        menuId,
        isOpen: false,
        panelReady: false,
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
                    this.panelReady = false;
                    this.applyInitialPosition();
                    this.$nextTick(() => this.refineVerticalPlacement());
                } else {
                    this.panelReady = false;
                    this.panelStyle = {};
                }
            };

            syncOpen(this.$store.hubRowMoreMenu.openId);
            this.$watch('$store.hubRowMoreMenu.openId', syncOpen);

            this.onCloseEvent = (event) => {
                if (event.detail === this.menuId) {
                    this.isOpen = false;
                    this.panelReady = false;
                    this.panelStyle = {};
                }
            };
            window.addEventListener('hub-row-more-menu-close', this.onCloseEvent);
        },

        destroy() {
            window.removeEventListener('hub-row-more-menu-close', this.onCloseEvent);
        },

        /**
         * @param {MouseEvent} event
         */
        toggle(event) {
            const clicked = event?.currentTarget;
            if (this.isOpen) {
                this.close();

                return;
            }
            if (!(clicked instanceof HTMLElement) || ! isVisibleTrigger(clicked)) {
                return;
            }
            const rect = clicked.getBoundingClientRect();
            this.$store.hubRowMoreMenu.requestOpen(this.menuId, clicked, rect);
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

        triggerRect() {
            const stored = this.$store.hubRowMoreMenu.anchorFor(this.menuId);
            if (stored) {
                return stored;
            }

            const trigger = this.$store.hubRowMoreMenu.triggerFor(this.menuId) ?? this.$refs.trigger;
            if (trigger instanceof HTMLElement && isVisibleTrigger(trigger)) {
                return trigger.getBoundingClientRect();
            }

            return null;
        },

        applyInitialPosition() {
            const rect = this.triggerRect();
            if (! rect || rect.width <= 0 || rect.height <= 0) {
                this.$store.hubRowMoreMenu.requestClose(this.menuId);

                return;
            }

            const gap = 4;
            const margin = 8;
            const rtl = document.documentElement.getAttribute('dir') === 'rtl';
            const viewportH = window.innerHeight;

            /** @type {Record<string, string | number>} */
            const style = {
                position: 'fixed',
                top: `${Math.round(rect.bottom + gap)}px`,
                minWidth: '11rem',
                maxHeight: `${Math.round(Math.max(120, viewportH - rect.bottom - gap - margin))}px`,
                zIndex: '10050',
            };

            if (rtl) {
                style.left = `${Math.round(rect.left)}px`;
                style.right = 'auto';
            } else {
                style.right = `${Math.round(window.innerWidth - rect.right)}px`;
                style.left = 'auto';
            }

            this.placement = 'bottom';
            this.panelStyle = style;
            this.panelReady = true;
        },

        refineVerticalPlacement() {
            const rect = this.triggerRect();
            const panel = this.$refs.panel;
            if (! rect || ! panel || ! this.isOpen) {
                return;
            }

            const gap = 4;
            const margin = 8;
            const viewportH = window.innerHeight;
            const panelHeight = panel.offsetHeight;
            if (panelHeight <= 0) {
                return;
            }

            const belowFits = rect.bottom + gap + panelHeight <= viewportH - margin;
            if (belowFits) {
                return;
            }

            const aboveTop = Math.max(margin, rect.top - gap - panelHeight);
            const aboveFits = aboveTop >= margin;
            if (! aboveFits) {
                return;
            }

            this.placement = 'top';
            this.panelStyle = {
                ...this.panelStyle,
                top: `${Math.round(aboveTop)}px`,
                maxHeight: `${Math.round(Math.max(120, rect.top - margin))}px`,
            };
        },
    }));
}

/**
 * @param {Element | null | undefined} el
 */
function isVisibleTrigger(el) {
    if (!(el instanceof HTMLElement)) {
        return false;
    }
    const style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
        return false;
    }
    const rect = el.getBoundingClientRect();

    return rect.width > 0 && rect.height > 0;
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

    const onScroll = (event) => {
        const store = Alpine.store('hubRowMoreMenu');
        if (! store?.openId) {
            return;
        }
        if (Date.now() < store.scrollGuardUntil) {
            return;
        }
        if (! event.isTrusted) {
            return;
        }

        const target = event.target;
        const scrollRoot = target === document ? document.documentElement : target;
        if (!(scrollRoot instanceof Element)) {
            return;
        }

        const hubScroll = scrollRoot.closest?.('.hub-doc-table-scroll, .admin-data-table-scroll');
        const isPageScroll = scrollRoot === document.documentElement || scrollRoot === document.body;

        if (! isPageScroll && ! hubScroll) {
            return;
        }

        closeAll();
    };

    document.addEventListener('scroll', onScroll, true);
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