import { attachGlobalToastHelpers, toastStackFactory } from './toast-stack-factory.js';

export function registerToastStack(Alpine, copy = {}) {
    const merged = { ...window.__toastCopy, ...copy };

    Alpine.data('toastStack', (initial = []) => toastStackFactory(initial, merged));
    attachGlobalToastHelpers(copy);
}
