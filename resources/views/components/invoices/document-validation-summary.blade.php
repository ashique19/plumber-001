@php
    $messages = collect($errors->all())->unique()->values();
@endphp

@if ($messages->isNotEmpty())
    <div
        id="document-validation-summary"
        class="invoice-document-validation app-card border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-950 dark:border-rose-500/40 dark:bg-rose-950/30 dark:text-rose-100"
        role="alert"
        aria-live="polite"
    >
        <p class="mb-2 font-semibold">{{ __('invoices.form_validation_summary') }}</p>
        <ul class="list-inside list-disc space-y-1">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif