@props(['fields' => []])

@php
    $fieldList = is_array($fields) ? $fields : [$fields];
    $messages = collect($fieldList)
        ->filter(fn (string $field) => $errors->has($field))
        ->flatMap(fn (string $field) => $errors->get($field))
        ->unique()
        ->values();
@endphp

@if ($messages->isNotEmpty())
    <div {{ $attributes->class(['x-modal-form__error-summary']) }} role="alert" aria-live="polite">
        <p class="x-modal-form__error-summary-title">{{ __('catalog.modal_error_summary') }}</p>
        <ul class="x-modal-form__error-summary-list">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif