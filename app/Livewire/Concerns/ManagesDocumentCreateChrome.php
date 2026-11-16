<?php

namespace App\Livewire\Concerns;

use App\Support\Invoicing\DocumentCreateUiSupport;
use App\Support\Invoicing\InvoiceLineMath;

trait ManagesDocumentCreateChrome
{
    public function adjustLineQuantity(int $index, float $delta): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $current = max(0.001, (float) ($this->lines[$index]['quantity'] ?? 1));
        $next = round(max(0.001, $current + $delta), 3);
        $this->lines[$index]['quantity'] = (string) $next;
    }

  /**
   * @return array<string, float>
   */
    public function documentSummaryTotals(): array
    {
        return DocumentCreateUiSupport::summaryRows($this->previewTotals());
    }

    public function paymentDueAmount(): float
    {
        $grand = (float) ($this->previewTotals()['total_inc_vat'] ?? 0);
        $received = max(0, (float) ($this->amountReceived ?? '0'));

        return round(max(0, $grand - $received), 2);
    }

    public function syncAmountReceivedToGrandTotal(): void
    {
        if (! property_exists($this, 'amountReceived')) {
            return;
        }

        $total = (float) ($this->previewTotals()['total_inc_vat'] ?? 0);
        $this->amountReceived = number_format($total, 2, '.', '');
    }

    public function clearSelectedCustomer(): void
    {
        $this->selectedCustomerId = null;
        $this->customerSearch = '';
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public function lineIncVatDisplay(array $line): float
    {
        return DocumentCreateUiSupport::lineAmounts($line)['line_inc_vat'];
    }
}