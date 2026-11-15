const STORAGE_KEY = 'plumber-admin-theme';

/** @param {import('alpinejs').Alpine} Alpine */
export function registerAdminTheme(Alpine) {
    const stored = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;
    const initialDark = stored === 'dark';

    if (initialDark) {
        document.documentElement.classList.add('admin-theme-dark');
    }

    Alpine.store('adminTheme', {
        dark: initialDark,
        toggle() {
            this.dark = ! this.dark;
            document.documentElement.classList.toggle('admin-theme-dark', this.dark);
            try {
                localStorage.setItem(STORAGE_KEY, this.dark ? 'dark' : 'light');
            } catch {
                // ignore
            }
        },
    });

    Alpine.directive('count-up', (el, { expression }) => {
        const target = Number(expression || el.textContent?.replace(/[^\d.-]/g, '') || 0);
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced || Number.isNaN(target)) {
            return;
        }

        const suffix = el.textContent?.replace(/[\d.,\s-]/g, '') ?? '';
        const duration = 400;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - (1 - progress) ** 3;
            const value = target * eased;
            el.textContent = `${value.toLocaleString(undefined, { maximumFractionDigits: 2 })}${suffix}`;
            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    });
}
