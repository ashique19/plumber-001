<div class="pb-24 lg:pb-8">
    <header class="mb-4">
        <h1 class="app-page-title">{{ $pageTitle }}</h1>
        <p class="app-page-lead">{{ __('invoices.issue_page_lead') }}</p>
    </header>

    <form wire:submit="issue" class="app-card space-y-4 p-4">
        @if ($isStandard)
            <fieldset class="space-y-3">
                <legend class="app-section-heading">{{ __('invoices.buyer_section') }}</legend>
                <x-form.input :label="__('invoices.buyer_name')" name="buyerName" wire:model.blur="buyerName" required />
                <p class="text-xs text-[var(--app-muted)]">{{ __('invoices.buyer_id_hint') }}</p>
                <x-form.input :label="__('business.fields.vat_number')" name="buyerVat" wire:model.blur="buyerVat" />
                <x-form.input :label="__('business.fields.cr_number')" name="buyerCr" wire:model.blur="buyerCr" />
                <x-form.input :label="__('business.fields.fields.building')" name="addrBuilding" wire:model.blur="addrBuilding" required />
                <x-form.input :label="__('business.fields.fields.street')" name="addrStreet" wire:model.blur="addrStreet" required />
                <x-form.input :label="__('business.fields.fields.district')" name="addrDistrict" wire:model.blur="addrDistrict" />
                <x-form.input :label="__('business.fields.fields.city')" name="addrCity" wire:model.blur="addrCity" required />
                <x-form.input :label="__('business.fields.fields.postal')" name="addrPostal" wire:model.blur="addrPostal" required />
                <x-form.input :label="__('business.fields.fields.additional')" name="addrAdditional" wire:model.blur="addrAdditional" />
            </fieldset>
        @else
            <x-form.input :label="__('invoices.buyer_optional')" name="buyerNameOptional" wire:model.blur="buyerNameOptional" />
        @endif

        <fieldset class="space-y-3">
            <legend class="app-section-heading">{{ __('invoices.lines_section') }}</legend>
            @foreach ($lines as $index => $line)
                <div class="app-inset-panel" wire:key="line-{{ $index }}">
                    <x-form.input :label="__('invoices.line_name')" name="lines.{{ $index }}.name" wire:model.blur="lines.{{ $index }}.name" />
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        <x-form.input type="number" step="0.01" :label="__('invoices.line_qty')" name="lines.{{ $index }}.quantity" wire:model.blur="lines.{{ $index }}.quantity" />
                        <x-form.input type="number" step="0.01" :label="__('invoices.line_price_ex_vat')" name="lines.{{ $index }}.unit_price" wire:model.blur="lines.{{ $index }}.unit_price" />
                        <x-form.input type="number" step="0.01" :label="__('invoices.line_vat')" name="lines.{{ $index }}.vat_rate" wire:model.blur="lines.{{ $index }}.vat_rate" />
                    </div>
                    @if (count($lines) > 1)
                        <button type="button" class="tap-action-muted mt-2 min-h-10 text-xs" wire:click="removeLine({{ $index }})">{{ __('invoices.remove_line') }}</button>
                    @endif
                </div>
            @endforeach
            <button type="button" class="tap-action-muted min-h-11 w-full sm:w-auto" wire:click="addLine">{{ __('invoices.add_line') }}</button>
        </fieldset>

        <x-ui.livewire-action type="submit" target="issue" loading="issue" class="tap-action-primary min-h-11 w-full sm:w-auto">
            {{ __('invoices.issue_submit') }}
        </x-ui.livewire-action>
    </form>
</div>
