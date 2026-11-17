@props([
    'isStandard' => false,
    'lines' => [],
])

<div class="invoice-document-more border-t border-[var(--app-border)] pt-4" x-data="{ moreOpen: false }">
    <button
        type="button"
        class="tap-action-muted invoice-document-more__toggle flex min-h-12 w-full items-center justify-between gap-2 text-sm font-semibold"
        @click="moreOpen = !moreOpen"
        :aria-expanded="moreOpen.toString()"
    >
        <span>{{ __('invoices.more_options') }}</span>
        <span class="inline-flex rotate-90 transition-transform" :class="moreOpen ? 'rotate-[270deg]' : ''" aria-hidden="true">
            <x-app.icon name="chevron" class="h-5 w-5" />
        </span>
    </button>

    <div x-show="moreOpen" x-cloak class="invoice-document-more__panel admin-form-grid space-y-4 pt-3">
        @if ($isStandard)
            <x-field.date :label="__('invoices.due_date')" name="dueDate" wire:model.live="dueDate" />
        @endif

        <x-field.date :label="__('invoices.supply_date')" name="supplyDate" wire:model.live="supplyDate" />
        <x-field.textarea :label="__('invoices.document_notes')" name="documentNotes" wire:model.live="documentNotes" rows="2" />

        <div class="admin-form-grid admin-form-grid--2 gap-3">
            <x-field.input :label="__('invoices.buyer_phone')" name="buyerPhone" wire:model.blur="buyerPhone" inputmode="tel" autocomplete="tel" icon="phone" />
            <x-field.input type="email" :label="__('invoices.buyer_email')" name="buyerEmail" wire:model.blur="buyerEmail" autocomplete="email" icon="mail" />
        </div>

        <div class="admin-form-grid admin-form-grid--3 gap-3">
            <x-field.money step="0.01" min="0" :label="__('invoices.invoice_discount_ex_vat')" name="invoiceDiscountExVat" wire:model.live="invoiceDiscountExVat" />
            <x-field.input type="number" step="0.01" min="0" max="100" inputmode="decimal" :label="__('invoices.retention_percent')" name="retentionPercent" wire:model.live="retentionPercent" />
            <x-field.money step="0.01" min="0" :label="__('invoices.retention_fixed')" name="retentionFixed" wire:model.live="retentionFixed" />
        </div>

        @unless ($isStandard)
            @foreach ($lines ?? [] as $index => $line)
                @continue(! filled($line['product_id'] ?? null))
                <div class="hidden sm:block" wire:key="line-vat-more-{{ $index }}">
                    <x-field.input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        inputmode="decimal"
                        :label="__('invoices.line_vat').' · '.$line['name']"
                        name="lines.{{ $index }}.vat_rate"
                        wire:model.live="lines.{{ $index }}.vat_rate"
                    />
                </div>
            @endforeach
        @endunless
    </div>
</div>