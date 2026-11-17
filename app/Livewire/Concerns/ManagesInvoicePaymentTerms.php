<?php

namespace App\Livewire\Concerns;

use App\Support\Invoicing\DocumentPaymentTerms;

trait ManagesInvoicePaymentTerms
{
    protected function defaultBusinessPaymentTermDays(): int
    {
        return max(0, (int) data_get($this->business->settings, 'invoicing.payment_terms_days', 30));
    }

    protected function effectiveSupplyDate(): string
    {
        $supply = trim((string) ($this->supplyDate ?? ''));

        return $supply !== '' ? $supply : now('Asia/Riyadh')->format('Y-m-d');
    }

    public function bootstrapDocumentPaymentTerms(): void
    {
        $defaultDays = $this->defaultBusinessPaymentTermDays();
        $this->paymentTerms = DocumentPaymentTerms::normalizeCode($this->paymentTerms, $defaultDays);

        if (DocumentPaymentTerms::isImmediate($this->paymentTerms)) {
            $this->dueDate = $this->effectiveSupplyDate();
            $this->syncAmountReceivedToGrandTotal();
        } else {
            $this->dueDate = DocumentPaymentTerms::computeDueDate(
                $this->paymentTerms,
                $this->effectiveSupplyDate(),
                $this->dueDate,
                $defaultDays,
            );
            if (trim((string) $this->amountReceived) === '') {
                $this->amountReceived = number_format(0, 2, '.', '');
            }
        }
    }

    public function updatedPaymentTerms(): void
    {
        $this->applyPaymentTermsState();
    }

    public function updatedSupplyDate(): void
    {
        $defaultDays = $this->defaultBusinessPaymentTermDays();
        if (DocumentPaymentTerms::isImmediate($this->paymentTerms)) {
            $this->dueDate = $this->effectiveSupplyDate();
        } else {
            $this->dueDate = DocumentPaymentTerms::computeDueDate(
                $this->paymentTerms,
                $this->effectiveSupplyDate(),
                $this->dueDate,
                $defaultDays,
            );
        }
    }

    public function updatedDueDate(): void
    {
        if (DocumentPaymentTerms::isImmediate($this->paymentTerms)) {
            $this->dueDate = $this->effectiveSupplyDate();

            return;
        }

        $this->paymentTerms = DocumentPaymentTerms::inferTermFromDueDate(
            $this->dueDate,
            $this->effectiveSupplyDate(),
            $this->defaultBusinessPaymentTermDays(),
        );
    }

    public function updatedAmountReceived(): void
    {
        $grand = (float) ($this->previewTotals()['total_inc_vat'] ?? 0);
        $received = max(0, min($grand, (float) $this->amountReceived));
        $this->amountReceived = number_format($received, 2, '.', '');

        if (
            DocumentPaymentTerms::isImmediate($this->paymentTerms)
            && $grand > 0.009
            && $received > 0.009
            && $received + 0.009 < $grand
        ) {
            $this->paymentTerms = DocumentPaymentTerms::defaultCreditTermCode($this->defaultBusinessPaymentTermDays());
            $this->applyPaymentTermsState(keepReceived: true);
            $this->toastInfo(__('invoices.payment_terms_auto_credit'));
        }
    }

    protected function applyPaymentTermsState(bool $keepReceived = false): void
    {
        $defaultDays = $this->defaultBusinessPaymentTermDays();

        if (DocumentPaymentTerms::isImmediate($this->paymentTerms)) {
            $this->dueDate = $this->effectiveSupplyDate();
            if (! $keepReceived) {
                $this->syncAmountReceivedToGrandTotal();
            }

            return;
        }

        $this->dueDate = DocumentPaymentTerms::computeDueDate(
            $this->paymentTerms,
            $this->effectiveSupplyDate(),
            $this->dueDate,
            $defaultDays,
        );

        if (! $keepReceived) {
            $this->amountReceived = number_format(0, 2, '.', '');
        }
    }

    protected function syncPaymentAmountsAfterTotalsChange(): void
    {
        if (DocumentPaymentTerms::isImmediate($this->paymentTerms)) {
            $this->syncAmountReceivedToGrandTotal();
        }

        $grand = (float) ($this->previewTotals()['total_inc_vat'] ?? 0);
        $received = max(0, (float) ($this->amountReceived ?? 0));
        if ($received > $grand) {
            $this->amountReceived = number_format($grand, 2, '.', '');
        }
    }

    public function paymentTermsIsImmediate(): bool
    {
        return DocumentPaymentTerms::isImmediate($this->paymentTerms);
    }

    public function paymentMethodRequiredOnForm(): bool
    {
        if (DocumentPaymentTerms::isImmediate($this->paymentTerms)) {
            return true;
        }

        return max(0, (float) ($this->amountReceived ?? 0)) > 0.009;
    }
}