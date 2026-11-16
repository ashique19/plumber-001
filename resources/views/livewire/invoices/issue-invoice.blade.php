<div
    class="document-create-page"
    x-on:document-open-preview.window="window.open($event.detail.url, '_blank')"
>
    <x-documents.create-header :title="__('invoices.create_heading')" :back-url="route('admin.invoices')" />

    <form
        id="invoice-issue-form"
        wire:submit="issue"
        @document-line-added.window="$nextTick(() => document.querySelector('.invoice-document-lines__row:last-child')?.classList.add('invoice-document-lines__row--flash'))"
        x-on:document-validation-failed.window="$nextTick(() => document.getElementById('document-validation-summary')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
    >
        <x-invoices.document-validation-summary />

        <div class="document-create-grid">
            <div class="document-create-main space-y-4">
                <div class="document-create-card">
                    <div class="document-create-card__body">
                        <div class="document-meta-grid">
                            <h2 class="sr-only">{{ __('invoices.document_meta') }}</h2>
                            <div class="document-readonly-field">
                                <span class="text-xs font-semibold text-[var(--app-muted)]">{{ __('invoices.field_document_number') }}</span>
                                <div class="document-readonly-field__value">
                                    <x-app.icon name="lock" class="h-4 w-4 text-[var(--app-muted)]" aria-hidden="true" />
                                    <span>{{ $documentNumberLabel }}</span>
                                </div>
                            </div>
                            <x-field.date :label="__('invoices.invoice_date')" name="supplyDate" wire:model.live="supplyDate" required />
                            <x-field.select :label="__('invoices.invoice_type')" name="mode" wire:model.live="mode" required>
                                <option value="standard">{{ __('invoices.type.standard_tax') }}</option>
                                <option value="simplified">{{ __('invoices.type.simplified_tax') }}</option>
                            </x-field.select>
                            <x-field.select :label="__('invoices.payment_terms_label')" name="paymentTerms" wire:model.live="paymentTerms">
                                <option value="cash">{{ __('invoices.payment_terms_cash') }}</option>
                                <option value="credit">{{ __('invoices.payment_terms_credit') }}</option>
                            </x-field.select>
                            <x-field.date
                                :label="__('invoices.due_date')"
                                name="dueDate"
                                wire:model.live="dueDate"
                                :disabled="$paymentTerms === 'cash'"
                                :required="$isStandard && $paymentTerms === 'credit'"
                            />
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

                <x-invoices.document-more-options :is-standard="$isStandard" :lines="$lines" />

                <x-invoices.zatca-not-ready-notice :show="$zatcaNotReady" />
            </div>

            <aside class="document-create-sidebar space-y-4">
                <x-documents.summary-card :heading="__('invoices.invoice_summary')" :summary-totals="$summaryTotals" />
                <x-invoices.document-payment-details
                    :payment-method-options="$paymentMethodOptions"
                    :due-amount="$paymentDueAmount"
                />
            </aside>
        </div>

        <x-invoices.document-sticky-action-bar
            :total="$summaryTotals['grand_total_inc_vat']"
            :submit-label="__('invoices.issue_submit')"
            wire-target="issue"
            form="invoice-issue-form"
            wire-click="issue"
        />

        <x-documents.create-footer
            :cancel-url="route('admin.invoices')"
            :primary-label="__('invoices.issue_submit')"
            primary-target="issue"
            primary-wire-click="issue"
            form-id="invoice-issue-form"
            draft-target="saveDraft"
            preview-target="previewDocument"
        />
    </form>

    <x-invoices.quick-customer-modal
        :show="$showQuickCustomerModal"
        :standard-buyer="$isStandard || $customerQuickEditMode"
        :edit-mode="$customerQuickEditMode"
    />
    <x-invoices.standard-buyer-completion-modal
        :show="$showStandardBuyerCompletionModal"
        :missing-keys="$standardBuyerMissingKeys"
        :customer-name="$buyerName"
    />
    <x-invoices.quick-product-modal :show="$showQuickProductModal" :categories="$productCategories" />
</div>