<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Support\Invoicing\DocumentCreateUiSupport;

trait ManagesDocumentBuyerContact
{
    public bool $buyerContactOverride = false;

    public function updatedBuyerContactOverride(bool $value): void
    {
        if ($value || $this->selectedCustomerId === null) {
            return;
        }

        $customer = Customer::query()->find($this->selectedCustomerId);
        if ($customer === null) {
            return;
        }

        $this->buyerPhone = trim((string) ($customer->phone ?? ''));
        $this->buyerEmail = trim((string) ($customer->email ?? ''));
    }

    protected function applyBuyerContactOverrideFromState(?int $customerId): void
    {
        $this->buyerContactOverride = DocumentCreateUiSupport::shouldInferBuyerContactOverride(
            $customerId,
            $this->buyerPhone,
            $this->buyerEmail,
        );
    }
}