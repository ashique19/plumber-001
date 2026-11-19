/** @param {typeof import('alpinejs').Alpine} Alpine */
export function registerHubRowMoreMenu(Alpine) {
    Alpine.store('hubRowMoreMenu', {
        openId: null,
        /** @type {Record<string, HTMLElement>} */
        triggers: {},
        requestOpen(id, trigger) {
            if (this.openId && this.openId !== id) {
                window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: this.openId }));
            }
            this.openId = id;
            if (trigger instanceof HTMLElement) {
                this.triggers[id] = trigger;
            }
        },
        requestClose(id) {
            if (this.openId === id) {
                this.openId = null;
            }
            delete this.triggers[id];
            window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: id }));
        },
        closeAll() {
            const previous = this.openId;
            this.openId = null;
            this.triggers = {};
            if (previous) {
                window.dispatchEvent(new CustomEvent('hub-row-more-menu-close', { detail: previous }));
            }
        },
        triggerFor(id) {
            const el = this.triggers[id];
            if (el instanceof HTMLElement && isVisibleTrigger(el)) {
                return el;
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
                    this.panelStyle = {};
                    this.$nextTick(() => {
                        const trigger = this.resolveTrigger();
                        if (! trigger || ! isVisibleTrigger(trigger)) {
                            this.$store.hubRowMoreMenu.requestClose(this.menuId);

                            return;
                        }
                        this.schedulePosition();
                    });
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
            this.$store.hubRowMoreMenu.requestOpen(this.menuId, clicked);
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

        resolveTrigger() {
            const fromStore = this.$store.hubRowMoreMenu.triggerFor(this.menuId);
            if (fromStore) {
                return fromStore;
            }

            const ref = this.$refs.trigger;
            if (ref instanceof HTMLElement && isVisibleTrigger(ref)) {
                return ref;
            }

            return null;
        },

        schedulePosition() {
            /** @param {number} attempt */
            const tryPosition = (attempt = 0) => {
                const trigger = this.resolveTrigger();
                const panel = this.$refs.panel;

                if (! trigger || ! panel) {
                    if (attempt < 12) {
                        this.$nextTick(() => {
                            requestAnimationFrame(() => tryPosition(attempt + 1));
                        });
                    }

                    return;
                }

                if (! isVisibleTrigger(trigger)) {
                    this.$store.hubRowMoreMenu.requestClose(this.menuId);

                    return;
                }

                const positioned = this.positionPanel(trigger, panel);
                if (! positioned && attempt < 12) {
                    requestAnimationFrame(() => tryPosition(attempt + 1));

                    return;
                }

                this.panelReady = positioned;
            };

            this.$nextTick(() => {
                requestAnimationFrame(() => tryPosition());
            });
        },

        measurePanel(panel) {
            if (panel.offsetWidth > 0 && panel.offsetHeight > 0) {
                return {
                    width: panel.offsetWidth,
                    height: panel.offsetHeight,
                };
            }

            const previous = {
                position: panel.style.position,
                visibility: panel.style.visibility,
                display: panel.style.display,
                left: panel.style.left,
                top: panel.style.top,
                width: panel.style.width,
            };

            panel.style.visibility = 'hidden';
            panel.style.display = 'block';
            panel.style.position = 'fixed';
            panel.style.left = '-10000px';
            panel.style.top = '0';
            panel.style.width = 'max-content';

            const width = Math.max(panel.offsetWidth, panel.scrollWidth, 176);
            const height = Math.max(panel.offsetHeight, panel.scrollHeight, 80);

            panel.style.position = previous.position;
            panel.style.visibility = previous.visibility;
            panel.style.display = previous.display;
            panel.style.left = previous.left;
            panel.style.top = previous.top;
            panel.style.width = previous.width;

            return { width, height };
        },

        /**
         * @param {HTMLElement} trigger
         * @param {HTMLElement} panel
         */
        positionPanel(trigger, panel) {
            if (! isVisibleTrigger(trigger)) {
                return false;
            }

            const rect = trigger.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0) {
                return false;
            }

            const { width: panelWidth, height: panelHeight } = this.measurePanel(panel);

            const margin = 8;
            const gap = 4;
            const viewportH = window.innerHeight;
            const viewportW = window.innerWidth;

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

            return true;
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