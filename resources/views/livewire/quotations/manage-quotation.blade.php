<div
    class="document-create-page"
    x-on:document-open-preview.window="window.open($event.detail.url, '_blank')"
>
    <x-documents.create-header :title="$pageTitle" :back-url="route('admin.quotations')" />

    <form
        id="quotation-save-form"
        wire:submit="save"
        @document-line-added.window="$nextTick(() => {
            const lineId = $event.detail?.lineId ?? null;
            const i = $event.detail?.lineIndex;
            const el = lineId
                ? document.querySelector('[data-document-line-id=' + JSON.stringify(lineId) + ']')
                : (typeof i === 'number'
                    ? document.querySelector(`[data-document-line-index='${i}']`)
                    : document.querySelector('.invoice-document-lines__row:last-child'));
            el?.classList.add('invoice-document-lines__row--flash');
            el && setTimeout(() => el.classList.remove('invoice-document-lines__row--flash'), 1600);
            el?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            $el.closest('.document-create-page')?.querySelector('.invoice-add-line-search input[data-admin-search-input]')?.focus({ preventScroll: true });
        })"
        @document-line-merged.window="$nextTick(() => {
            const lineId = $event.detail?.lineId ?? null;
            const i = $event.detail?.lineIndex;
            const targets = lineId
                ? document.querySelectorAll('[data-document-line-id=' + JSON.stringify(lineId) + ']')
                : (typeof i === 'number'
                    ? document.querySelectorAll(`[data-document-line-index='${i}']`)
                    : []);
            targets.forEach((row) => {
                row.classList.add('invoice-document-lines__row--flash');
                setTimeout(() => row.classList.remove('invoice-document-lines__row--flash'), 1600);
            });
            const el = targets[0];
            const stepper = el?.querySelector('.document-qty-stepper');
            stepper?.querySelector('button')?.focus({ preventScroll: true });
            stepper?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        })"
        x-on:document-validation-failed.window="$nextTick(() => document.getElementById('document-validation-summary')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
    >
        <x-invoices.document-validation-summary />

        <div class="document-create-grid">
            <div class="document-create-main space-y-4">
                <div class="document-create-card">
                    <div class="document-create-card__body">
                        <div class="document-meta-grid document-meta-grid--quotation">
                            <div class="document-readonly-field">
                                <span class="document-readonly-field__label">{{ __('quotations.field_document_number') }}</span>
                                <div class="document-readonly-field__value" title="{{ $documentNumberLabel }}">
                                    <x-app.icon name="lock" class="h-4 w-4 shrink-0 text-[var(--app-muted)]" aria-hidden="true" />
                                    <span class="truncate">{{ $documentNumberLabel }}</span>
                                </div>
                            </div>
                            <x-field.date :label="__('quotations.quotation_date')" name="supplyDate" wire:model.live="supplyDate" required />
                            <x-field.date :label="__('quotations.validity_until')" name="validityUntil" wire:model.live="validityUntil" required />
                        </div>
                    </div>
                </div>

                <x-invoices.document-customer-card
                    :customer-strip="$customerStrip"
                    :results="$customerSearchResults"
                    :can-create="$canCreateCustomers"
                    :search="$customerSearch"
                    :selected-customer-id="$selectedCustomerId"
                    :compact="! $isStandard"
                    :show-walk-in-hint="! $isStandard"
                    :show-walk-in-contact-fields="! $isStandard && $selectedCustomerId === null"
                />

                <div class="document-create-card">
                    <div class="document-create-card__head">
                        <x-app.icon name="package" class="h-5 w-5" aria-hidden="true" />
                        <span>{{ __('invoices.add_products') }}</span>
                    </div>
                    <div class="document-create-card__body">
                        <x-invoices.add-line-product-search
                            :results="$addLineProductSearchResults"
                            :can-create-product="$canCreateProducts"
                            :search="$addLineProductSearch"
                        />
                        <x-invoices.document-product-lines-create :lines="$lines" :stock-hint="$stockHint" />
                    </div>
                </div>

                <div class="document-terms-grid">
                    <x-field.textarea :label="__('quotations.terms_and_conditions')" name="termsAndConditions" wire:model.live="termsAndConditions" rows="6" />
                    <x-field.textarea :label="__('invoices.document_notes')" name="documentNotes" wire:model.live="documentNotes" rows="6" :placeholder="__('quotations.notes_placeholder')" />
                </div>

                <x-invoices.document-more-options :is-standard="$isStandard" :lines="$lines" />
            </div>

            <aside class="document-create-sidebar space-y-4">
                <x-documents.summary-card :heading="__('quotations.summary')" :summary-totals="$summaryTotals" />
                <x-quotations.document-info-card
                    :validity-days="$validityDays"
                    :status-label="__('quotations.status_draft')"
                />
            </aside>
        </div>

        <x-invoices.document-sticky-action-bar
            :total="$summaryTotals['grand_total_inc_vat']"
            :submit-label="__('quotations.create_submit')"
            wire-target="save"
            form="quotation-save-form"
        />

        <x-documents.create-footer
            :cancel-url="route('admin.quotations')"
            :primary-label="__('quotations.create_submit')"
            primary-target="save"
            form-id="quotation-save-form"
            draft-target="saveDraft"
            preview-target="previewDocument"
            :preview-label="__('quotations.preview_document')"
            :show-preview="true"
        />
    </form>

    <x-invoices.quick-customer-modal :show="$showQuickCustomerModal" :standard-buyer="$isStandard" />
    <x-invoices.quick-product-modal :show="$showQuickProductModal" :categories="$productCategories" />
</div>