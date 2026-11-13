@php
    use App\Support\SessionToastCollector;

    $initialToasts = SessionToastCollector::fromRequest(request());
    $toastCopy = [
        'dismiss' => __('toast.dismiss'),
        'validation_title' => __('toast.validation_title'),
        'validation_body' => __('toast.validation_body'),
        'session_expired_title' => __('toast.session_expired_title'),
        'session_expired_body' => __('toast.session_expired_body'),
        'forbidden_title' => __('toast.forbidden_title'),
        'forbidden_body' => __('toast.forbidden_body'),
        'not_found_title' => __('toast.not_found_title'),
        'not_found_body' => __('toast.not_found_body'),
        'server_error_title' => __('toast.server_error_title'),
        'server_error_body' => __('toast.server_error_body'),
        'offline_title' => __('toast.offline_title'),
        'offline_body' => __('toast.offline_body'),
        'unexpected_title' => __('toast.unexpected_title'),
        'unexpected_body' => __('toast.unexpected_body'),
    ];
@endphp
<script>
    window.__toastCopy = @json($toastCopy);
</script>
<div
    id="toast-stack"
    {{ $attributes->class(['toast-stack']) }}
    x-data="toastStack(@js($initialToasts))"
    x-cloak
    aria-relevant="additions"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            :class="typeClass(toast.type)"
            role="status"
            :aria-live="liveRegion(toast.type)"
            class="toast-item"
        >
            <div class="toast-item__body">
                <p class="toast-item__title" x-show="toast.title" x-text="toast.title"></p>
                <p class="toast-item__message" x-text="toast.message"></p>
            </div>
            <button
                type="button"
                class="toast-item__dismiss touch-target"
                @click="dismiss(toast.id)"
                :aria-label="copy.dismiss"
            >
                <span aria-hidden="true">×</span>
            </button>
        </div>
    </template>
</div>
