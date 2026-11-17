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

<footer class="document-create-footer" data-testid="document-create-footer">
    <div class="document-create-footer__inner">
        <a href="{{ $cancelUrl }}" class="document-create-footer__cancel tap-action-ghost min-h-11 px-4 text-sm font-semibold" wire:navigate>{{ __('invoices.cancel') }}</a>
        <div class="document-create-footer__actions">
            @if ($showDraft)
                <x-ui.livewire-action type="button" wire-click="{{ $draftTarget }}" :target="$draftTarget" :loading="$draftTarget" class="document-create-footer__secondary tap-action-outline min-h-11 w-full px-4 text-sm font-semibold sm:w-auto">
                    {{ __('invoices.save_draft') }}
                </x-ui.livewire-action>
            @endif
            @if ($showPreview)
                <x-ui.livewire-action type="button" wire-click="{{ $previewTarget }}" :target="$previewTarget" :loading="$previewTarget" class="document-create-footer__secondary tap-action-outline min-h-11 w-full px-4 text-sm font-semibold sm:w-auto">
                    {{ $previewLabel }}
                </x-ui.livewire-action>
            @endif
            <x-ui.livewire-action
                :type="$primaryFormSubmit ? 'submit' : 'button'"
                :form="$primaryFormSubmit ? $formId : null"
                :wire-click="$primaryWireClick"
                :target="$primaryTarget"
                :loading="$primaryTarget"
                class="tap-action-primary document-create-footer__primary document-primary-submit min-h-11 w-full px-5 text-sm font-semibold sm:w-auto"
            >
                {{ $primaryLabel }}
            </x-ui.livewire-action>
        </div>
    </div>
</footer>