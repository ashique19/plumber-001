@props([
    'cancelUrl',
    'primaryLabel',
    'primaryTarget',
    'primaryWireClick' => null,
    'formId',
    'showDraft' => true,
    'showPreview' => true,
    'draftTarget' => 'saveDraft',
    'previewTarget' => 'previewDocument',
    'previewLabel' => null,
    'primaryFormSubmit' => true,
])

@php
    $previewLabel = $previewLabel ?? __('invoices.preview_document');
@endphp

<footer class="document-create-footer">
    <div class="document-create-footer__inner">
        <a href="{{ $cancelUrl }}" class="tap-action-muted min-h-11 px-4 text-sm font-semibold" wire:navigate>{{ __('invoices.cancel') }}</a>
        <div class="document-create-footer__actions">
            @if ($showDraft)
                <x-ui.livewire-action type="button" wire-click="{{ $draftTarget }}" :target="$draftTarget" :loading="$draftTarget" class="tap-action-outline min-h-11 px-4 text-sm font-semibold">
                    {{ __('invoices.save_draft') }}
                </x-ui.livewire-action>
            @endif
            @if ($showPreview)
                <x-ui.livewire-action type="button" wire-click="{{ $previewTarget }}" :target="$previewTarget" :loading="$previewTarget" class="tap-action-outline min-h-11 px-4 text-sm font-semibold">
                    {{ $previewLabel }}
                </x-ui.livewire-action>
            @endif
            <x-ui.livewire-action
                :type="$primaryFormSubmit ? 'submit' : 'button'"
                :form="$primaryFormSubmit ? $formId : null"
                :wire-click="$primaryWireClick"
                :target="$primaryTarget"
                :loading="$primaryTarget"
                class="tap-action-primary document-create-footer__primary document-primary-submit min-h-11 px-5 text-sm font-semibold"
            >
                {{ $primaryLabel }}
            </x-ui.livewire-action>
        </div>
    </div>
</footer>