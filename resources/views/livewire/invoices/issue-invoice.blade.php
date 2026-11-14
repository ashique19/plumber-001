<div class="pb-24 lg:pb-8">
    <header class="mb-4">
        <h1 class="app-page-title">{{ $pageTitle }}</h1>
    </header>

    <section class="app-card mb-4 space-y-2 p-4">
        <p class="text-sm font-semibold">
            {{ $isStandard ? __('invoices.print_a4') : __('invoices.print_cash') }}
        </p>
        <p class="text-xs text-[var(--app-muted)]">
            {{ $isStandard ? __('invoices.layout_a4') : __('invoices.layout_cash') }}
        </p>
        <a
            class="tap-action-muted inline-flex min-h-10 items-center px-3 text-xs font-semibold"
            href="{{ route('admin.invoices.issue', ['mode' => $isStandard ? 'simplified' : 'standard']) }}"
        >
            {{ $isStandard ? __('invoices.issue_simplified_title') : __('invoices.issue_standard_title') }}
        </a>
    </section>

    <form
        wire:submit="issue"
        class="app-card space-y-4 p-4"
        @unless ($isStandard)
            x-data="{ moreOpen: false }"
        @endunless
    >
        @if ($isStandard)
            <fieldset class="space-y-3">
                <legend class="app-section-heading">{{ __('invoices.buyer_section') }}</legend>
                <x-form.input :label="__('invoices.buyer_name')" name="buyerName" wire:model.blur="buyerName" required />
                <p class="text-xs text-[var(--app-muted)]">{{ __('invoices.buyer_id_hint') }}</p>
                <x-form.input :label="__('business.fields.vat_number')" name="buyerVat" wire:model.blur="buyerVat" />
                <x-form.input :label="__('business.fields.cr_number')" name="buyerCr" wire:model.blur="buyerCr" />
                <x-form.input :label="__('business.fields.building')" name="addrBuilding" wire:model.blur="addrBuilding" required />
                <x-form.input :label="__('business.fields.street')" name="addrStreet" wire:model.blur="addrStreet" required />
                <x-form.input :label="__('business.fields.district')" name="addrDistrict" wire:model.blur="addrDistrict" />
                <x-form.input :label="__('business.fields.city')" name="addrCity" wire:model.blur="addrCity" required />
                <x-form.input :label="__('business.fields.postal')" name="addrPostal" wire:model.blur="addrPostal" required />
                <x-form.input :label="__('business.fields.additional')" name="addrAdditional" wire:model.blur="addrAdditional" />
                <div class="grid gap-2 sm:grid-cols-2">
                    <x-form.input :label="__('invoices.buyer_phone')" name="buyerPhone" wire:model.blur="buyerPhone" inputmode="tel" />
                    <x-form.input type="email" :label="__('invoices.buyer_email')" name="buyerEmail" wire:model.blur="buyerEmail" />
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <x-form.input type="date" :label="__('invoices.due_date')" name="dueDate" wire:model.live="dueDate" required />
                    <x-form.input :label="__('invoices.buyer_country')" name="buyerCountry" wire:model.blur="buyerCountry" maxlength="2" pattern="[A-Za-z]{2}" required />
                </div>
            </fieldset>

            <div class="grid gap-2 sm:grid-cols-3">
                <x-form.input type="number" step="0.01" min="0" :label="__('invoices.invoice_discount_ex_vat')" name="invoiceDiscountExVat" wire:model.live="invoiceDiscountExVat" />
                <x-form.input type="number" step="0.01" min="0" max="100" :label="__('invoices.retention_percent')" name="retentionPercent" wire:model.live="retentionPercent" />
                <x-form.input type="number" step="0.01" min="0" :label="__('invoices.retention_fixed')" name="retentionFixed" wire:model.live="retentionFixed" />
            </div>

            <fieldset class="space-y-3">
                <legend class="app-section-heading">{{ __('invoices.lines_section') }}</legend>
                <p class="text-xs text-[var(--app-muted)]">{{ __('invoices.manual_line_hint') }}</p>

                @foreach ($lines as $index => $line)
                    <div class="app-inset-panel" wire:key="line-{{ $index }}">
                        <x-invoices.catalog-product-picker :line-index="$index" :products="$catalogProducts" />

                        @if (filled($line['unit_label'] ?? null))
                            <p class="mb-2 text-xs font-medium text-[var(--app-muted)]">
                                {{ __('invoices.line_unit') }}: {{ $line['unit_label'] }}
                            </p>
                        @endif

                        <x-form.input :label="__('invoices.line_name')" name="lines.{{ $index }}.name" wire:model.live="lines.{{ $index }}.name" />
                        <x-form.input class="mt-2" :label="__('invoices.line_name_ar')" name="lines.{{ $index }}.name_ar" wire:model.live="lines.{{ $index }}.name_ar" dir="rtl" />
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <x-form.input type="number" step="0.001" min="0.001" :label="__('invoices.line_qty')" name="lines.{{ $index }}.quantity" wire:model.live="lines.{{ $index }}.quantity" />
                            <x-form.input type="number" step="0.01" :label="__('invoices.line_price_ex_vat')" name="lines.{{ $index }}.unit_price" wire:model.live="lines.{{ $index }}.unit_price" />
                            <x-form.input type="number" step="0.01" :label="__('invoices.line_vat')" name="lines.{{ $index }}.vat_rate" wire:model.live="lines.{{ $index }}.vat_rate" />
                        </div>
                        @if (count($lines) > 1)
                            <button type="button" class="tap-action-muted mt-2 min-h-10 text-xs" wire:click="removeLine({{ $index }})">{{ __('invoices.remove_line') }}</button>
                        @endif
                    </div>
                @endforeach
                <button type="button" class="tap-action-muted min-h-11 w-full sm:w-auto" wire:click="addLine">{{ __('invoices.add_line') }}</button>
            </fieldset>
        @else
            <fieldset class="issue-simplified-lines space-y-3">
                <legend class="sr-only">{{ __('invoices.lines_section') }}</legend>

                @foreach ($lines as $index => $line)
                    <div class="issue-simplified-line app-inset-panel space-y-3" wire:key="line-{{ $index }}">
                        <x-invoices.catalog-product-picker :line-index="$index" :products="$catalogProducts" compact />

                        <div class="grid grid-cols-2 gap-3">
                            <x-form.input
                                type="number"
                                step="0.001"
                                min="0.001"
                                :label="__('invoices.line_qty')"
                                name="lines.{{ $index }}.quantity"
                                wire:model.live="lines.{{ $index }}.quantity"
                                wrapper-class="issue-simplified-line__field"
                            />
                            <x-form.input
                                type="number"
                                step="0.01"
                                :label="__('invoices.line_price_ex_vat')"
                                name="lines.{{ $index }}.unit_price"
                                wire:model.live="lines.{{ $index }}.unit_price"
                                wrapper-class="issue-simplified-line__field"
                            />
                        </div>

                        @if (count($lines) > 1)
                            <button
                                type="button"
                                class="tap-action-muted min-h-12 w-full text-sm font-semibold"
                                wire:click="removeLine({{ $index }})"
                            >
                                {{ __('invoices.remove_line') }}
                            </button>
                        @endif
                    </div>
                @endforeach

                <button
                    type="button"
                    class="tap-action-muted issue-simplified-add-line min-h-12 w-full text-sm font-semibold"
                    wire:click="addLine"
                >
                    {{ __('invoices.add_line') }}
                </button>
            </fieldset>

            <section class="invoice-issue-preview-totals invoice-issue-preview-totals--compact rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4" aria-live="polite">
                <h2 class="mb-2 text-sm font-semibold">{{ __('invoices.preview_totals') }}</h2>
                <p class="text-2xl font-bold tabular-nums">
                    {{ number_format($previewTotals['total_inc_vat'], 2) }}
                    <span class="text-sm font-medium text-[var(--app-muted)]">{{ __('invoices.total_inc_vat') }}</span>
                </p>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--app-muted)]">{{ __('invoices.subtotal_ex_vat') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['subtotal_ex_vat'], 2) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-[var(--app-muted)]">{{ __('invoices.total_vat') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['total_vat'], 2) }}</dd>
                    </div>
                    @if (($previewTotals['retention_amount'] ?? 0) > 0)
                        <div class="flex justify-between gap-2 sm:col-span-2">
                            <dt class="text-[var(--app-muted)]">{{ __('invoices.retention_amount') }}</dt>
                            <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['retention_amount'], 2) }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <x-invoices.zatca-not-ready-notice :show="$zatcaNotReady" />

            <x-ui.livewire-action type="submit" target="issue" loading="issue" class="tap-action-primary issue-simplified-issue min-h-14 w-full text-base font-semibold">
                {{ __('invoices.issue_submit') }}
            </x-ui.livewire-action>

            <div class="issue-simplified-more border-t border-[var(--app-border)] pt-4" id="issue-simplified-more">
                <button
                    type="button"
                    class="tap-action-muted issue-simplified-more__toggle flex min-h-12 w-full items-center justify-between gap-2 text-sm font-semibold"
                    @click="moreOpen = !moreOpen"
                    :aria-expanded="moreOpen.toString()"
                >
                    <span>{{ __('invoices.more_options') }}</span>
                    <span class="inline-flex rotate-90 transition-transform" :class="moreOpen ? 'rotate-[270deg]' : ''" aria-hidden="true">
                        <x-app.icon name="chevron" class="h-5 w-5" />
                    </span>
                </button>

                <div x-show="moreOpen" x-cloak class="issue-simplified-more__panel space-y-4 pt-3">
                    <x-form.input :label="__('invoices.buyer_optional')" name="buyerNameOptional" wire:model.blur="buyerNameOptional" />
                    <x-form.input :label="__('invoices.buyer_phone')" name="buyerPhone" wire:model.blur="buyerPhone" />
                    <x-form.input :label="__('invoices.buyer_email')" name="buyerEmail" wire:model.blur="buyerEmail" />

                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-form.input type="number" step="0.01" min="0" :label="__('invoices.invoice_discount_ex_vat')" name="invoiceDiscountExVat" wire:model.live="invoiceDiscountExVat" />
                        <x-form.input type="number" step="0.01" min="0" max="100" :label="__('invoices.retention_percent')" name="retentionPercent" wire:model.live="retentionPercent" />
                        <x-form.input type="number" step="0.01" min="0" :label="__('invoices.retention_fixed')" name="retentionFixed" wire:model.live="retentionFixed" />
                    </div>

                    <p class="text-xs text-[var(--app-muted)]">{{ __('invoices.manual_line_hint') }}</p>

                    @foreach ($lines as $index => $line)
                        <div class="app-inset-panel space-y-2" wire:key="line-more-{{ $index }}">
                            @if (count($lines) > 1)
                                <p class="text-xs font-semibold text-[var(--app-muted)]">
                                    {{ __('invoices.lines_section') }} {{ $index + 1 }}
                                </p>
                            @endif

                            @if (filled($line['unit_label'] ?? null))
                                <p class="text-xs font-medium text-[var(--app-muted)]">
                                    {{ __('invoices.line_unit') }}: {{ $line['unit_label'] }}
                                </p>
                            @endif

                            <x-form.input :label="__('invoices.line_name')" name="lines.{{ $index }}.name" wire:model.live="lines.{{ $index }}.name" />
                            <x-form.input :label="__('invoices.line_name_ar')" name="lines.{{ $index }}.name_ar" wire:model.live="lines.{{ $index }}.name_ar" dir="rtl" />
                            <x-form.input type="number" step="0.01" :label="__('invoices.line_vat')" name="lines.{{ $index }}.vat_rate" wire:model.live="lines.{{ $index }}.vat_rate" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($isStandard)
            <section class="invoice-issue-preview-totals rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4" aria-live="polite">
                <h2 class="mb-3 text-sm font-semibold">{{ __('invoices.preview_totals') }}</h2>
                <dl class="grid gap-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-2 sm:block">
                        <dt class="text-[var(--app-muted)]">{{ __('invoices.subtotal_ex_vat') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['subtotal_ex_vat'], 2) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 sm:block">
                        <dt class="text-[var(--app-muted)]">{{ __('invoices.total_vat') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['total_vat'], 2) }}</dd>
                    </div>
                    <div class="flex justify-between gap-2 sm:col-span-2">
                        <dt class="text-[var(--app-muted)]">{{ __('invoices.total_inc_vat') }}</dt>
                        <dd class="text-base font-bold tabular-nums">{{ number_format($previewTotals['total_inc_vat'], 2) }}</dd>
                    </div>
                    @if (($previewTotals['retention_amount'] ?? 0) > 0)
                        <div class="flex justify-between gap-2 sm:col-span-2">
                            <dt class="text-[var(--app-muted)]">{{ __('invoices.retention_amount') }}</dt>
                            <dd class="font-semibold tabular-nums">{{ number_format($previewTotals['retention_amount'], 2) }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <x-invoices.zatca-not-ready-notice :show="$zatcaNotReady" />

            <x-ui.livewire-action type="submit" target="issue" loading="issue" class="tap-action-primary min-h-11 w-full sm:w-auto">
                {{ __('invoices.issue_submit') }}
            </x-ui.livewire-action>
        @endif
    </form>
</div>