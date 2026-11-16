<?php

namespace App\Support\Invoicing;

use App\Models\Customer;
use App\Support\Customers\CustomerIdentityPersistence;
use App\Support\Customers\CustomerIdentityRules;
use App\Support\Customers\CustomerProfileFields;
use Illuminate\Support\Collection;

final class InvoicingCustomerLookup
{
    /**
     * @return Collection<int, Customer>
     */
    public static function search(string $term, int $limit = 15): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return Customer::query()
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get();
        }

        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term) ?? '';

        return Customer::query()
            ->where(function ($q) use ($like, $digits): void {
                $q->where('name', 'like', $like)
                    ->orWhere('legal_name_ar', 'like', $like)
                    ->orWhere('legal_name_en', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('alt_id_number', 'like', $like)
                    ->orWhere('profile->name_ar', 'like', $like);

                if ($digits !== '') {
                    $q->orWhere('vat_number', 'like', '%'.$digits.'%')
                        ->orWhere('alt_id_number', 'like', '%'.$digits.'%')
                        ->orWhere('profile->cr_number', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

  /**
   * @param  object  $host  Livewire host with buyer identity properties
   */
    public static function applyCustomerToBuyer(object $host, Customer $customer): void
    {
        CustomerIdentityPersistence::fillHostFromCustomer(
            $host,
            CustomerIdentityRules::buyerFields(),
            $customer,
        );

        $state = CustomerIdentityPersistence::stateFromCustomer($customer);
        if (property_exists($host, 'buyerCr')) {
            $cr = $state['alt_id_type'] === 'CRN' ? (string) $state['alt_id_number'] : CustomerProfileFields::crNumber($customer);
            $host->buyerCr = (string) ($cr ?? '');
        }

        if (property_exists($host, 'selectedCustomerId')) {
            $host->selectedCustomerId = $customer->id;
        }
    }

    public static function typeaheadMetaLine(Customer $customer): string
    {
        $segments = [];
        $phone = trim((string) ($customer->phone ?? ''));
        if ($phone !== '') {
            $segments[] = __('invoices.customer_meta_phone', ['value' => $phone]);
        }

        $vat = trim((string) ($customer->vat_number ?? ''));
        if ($vat !== '') {
            $segments[] = __('invoices.customer_meta_vat', ['value' => $vat]);
        }

        $cr = CustomerProfileFields::crNumber($customer);
        if ($cr !== null) {
            $segments[] = __('invoices.customer_meta_cr', ['value' => $cr]);
        }

        return implode(' · ', $segments);
    }
}