import { registerToastStack } from './toast-stack.js';

function parseJsonMaybe(text) {
    if (! text || typeof text !== 'string') {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
}

function summarizeValidationErrors(errors) {
    if (! errors || typeof errors !== 'object') {
        return '';
    }

    const messages = Object.values(errors)
        .flat()
        .filter((m) => typeof m === 'string');

    const unique = [...new Set(messages)];

    return unique.slice(0, 3).join('; ');
}

function toastFromHttpStatus(status, bodyText, copy) {
    if (status === 419) {
        return {
            type: 'warning',
            title: copy.session_expired_title,
            message: copy.session_expired_body,
            persistent: true,
        };
    }

    if (status === 403) {
        return {
            type: 'error',
            title: copy.forbidden_title,
            message: copy.forbidden_body,
            persistent: true,
        };
    }

    if (status === 404) {
        return {
            type: 'error',
            title: copy.not_found_title,
            message: copy.not_found_body,
            persistent: true,
        };
    }

    if (status === 422) {
        const json = parseJsonMaybe(bodyText);
        const fields = summarizeValidationErrors(json?.errors ?? json?.message);
        const message = fields
            ? copy.validation_body.replace(':fields', fields)
            : copy.validation_body.replace(':fields', copy.unexpected_body);

        return {
            type: 'warning',
            title: copy.validation_title,
            message,
            persistent: true,
        };
    }

    if (status >= 500) {
        return {
            type: 'error',
            title: copy.server_error_title,
            message: copy.server_error_body,
            persistent: true,
        };
    }

    return {
        type: 'error',
        title: copy.unexpected_title,
        message: copy.unexpected_body,
        persistent: true,
    };
}

export function wireLivewireRequestFeedback(copy) {
    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, content, preventDefault }) => {
                preventDefault();

                if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                    window.showToast?.({
                        type: 'error',
                        title: copy.offline_title,
                        message: copy.offline_body,
                        persistent: true,
                    });

                    return;
                }

                const toast = toastFromHttpStatus(status, content, copy);
                window.showToast?.(toast);

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const payload = JSON.stringify({
                    status,
                    snippet: typeof content === 'string' ? content.slice(0, 500) : '',
                    url: window.location.href,
                });

                if (token) {
                    fetch('/telemetry/livewire-client-failure', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: payload,
                        keepalive: true,
                    }).catch(() => {});
                }
            });
        });

        Livewire.hook('message.failed', () => {
            // Network / transport failure (offline, timeout, etc.)
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                window.showToast?.({
                    type: 'error',
                    title: copy.offline_title,
                    message: copy.offline_body,
                    persistent: true,
                });
            }
        });

        Livewire.hook('message.processed', (message, component) => {
            const errors = component?.errors;
            if (! errors || typeof errors !== 'object') {
                return;
            }

            const keys = Object.keys(errors);
            if (keys.length === 0) {
                return;
            }

            if (keys.length === 1 && keys[0] === 'checkout') {
                const msg = Array.isArray(errors.checkout) ? errors.checkout[0] : errors.checkout;
                if (msg) {
                    window.showToast?.({
                        type: 'error',
                        message: msg,
                        persistent: true,
                    });
                    component.clearValidation('checkout');

                    return;
                }
            }

            const fields = summarizeValidationErrors(errors);
            if (! fields) {
                return;
            }

            window.showToast?.({
                type: 'warning',
                title: copy.validation_title,
                message: copy.validation_body.replace(':fields', fields),
                persistent: true,
            });
        });
    });
}
