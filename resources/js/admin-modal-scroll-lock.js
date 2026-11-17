/** @typedef {{ bodyOverflow: string, bodyPaddingInlineEnd: string, bodyPaddingInlineStart: string, htmlOverflow: string, htmlPaddingInlineEnd: string, htmlPaddingInlineStart: string }} SavedScrollStyles */

const state = {
    count: 0,
    /** @type {SavedScrollStyles | null} */
    saved: null,
};

function scrollbarWidth() {
    return Math.max(0, window.innerWidth - document.documentElement.clientWidth);
}

function isRtl() {
    return document.documentElement.getAttribute('dir') === 'rtl';
}

function applyLock() {
    const body = document.body;
    const html = document.documentElement;
    const gap = scrollbarWidth();

    state.saved = {
        bodyOverflow: body.style.overflow,
        bodyPaddingInlineEnd: body.style.paddingInlineEnd,
        bodyPaddingInlineStart: body.style.paddingInlineStart,
        htmlOverflow: html.style.overflow,
        htmlPaddingInlineEnd: html.style.paddingInlineEnd,
        htmlPaddingInlineStart: html.style.paddingInlineStart,
    };

    body.style.overflow = 'hidden';
    html.style.overflow = 'hidden';

    if (gap > 0) {
        if (isRtl()) {
            body.style.paddingInlineStart = `${gap}px`;
            html.style.paddingInlineStart = `${gap}px`;
        } else {
            body.style.paddingInlineEnd = `${gap}px`;
            html.style.paddingInlineEnd = `${gap}px`;
        }
    }
}

function restoreLock() {
    if (! state.saved) {
        return;
    }

    const body = document.body;
    const html = document.documentElement;
    const saved = state.saved;

    body.style.overflow = saved.bodyOverflow;
    body.style.paddingInlineEnd = saved.bodyPaddingInlineEnd;
    body.style.paddingInlineStart = saved.bodyPaddingInlineStart;
    html.style.overflow = saved.htmlOverflow;
    html.style.paddingInlineEnd = saved.htmlPaddingInlineEnd;
    html.style.paddingInlineStart = saved.htmlPaddingInlineStart;

    state.saved = null;
}

/** @returns {() => void} */
export function acquireAdminModalScrollLock() {
    if (state.count === 0) {
        applyLock();
    }

    state.count += 1;

    let released = false;

    return () => {
        if (released) {
            return;
        }

        released = true;
        state.count = Math.max(0, state.count - 1);

        if (state.count === 0) {
            restoreLock();
        }
    };
}

export function resetAdminModalScrollLock() {
    state.count = 0;
    restoreLock();
}

/** @param {import('alpinejs').Alpine} Alpine */
export function registerAdminModalScroll(Alpine) {
    Alpine.data('adminModalBackdrop', () => ({
        /** @type {null | (() => void)} */
        release: null,
        init() {
            this.release = acquireAdminModalScrollLock();
            window.dispatchEvent(new CustomEvent('toast-clear'));
        },
        destroy() {
            this.release?.();
            this.release = null;
        },
    }));
}

/** @param {typeof import('../../vendor/livewire/livewire/dist/livewire.esm').Livewire} Livewire */
export function wireAdminModalScrollLivewire(Livewire) {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            requestAnimationFrame(() => {
                if (! document.querySelector('.x-modal-backdrop')) {
                    resetAdminModalScrollLock();
                }
            });
        });
    });

    document.addEventListener('livewire:navigated', () => {
        resetAdminModalScrollLock();
        window.Alpine?.store('hubRowMoreMenu')?.closeAll();
    });
}