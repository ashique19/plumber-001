<?php

namespace App\Support\Invoicing;

use App\Models\Customer;
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
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('profile->name_ar', 'like', $like);

                if ($digits !== '') {
                    $q->orWhere('vat_number', 'like', '%'.$digits.'%')
                        ->orWhere('profile->cr_number', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  object{
     *     buyerName: string,
     *     buyerPhone: string,
     *     buyerEmail: string,
     *     buyerVat: string,
     *     buyerCr: string,
     *     buyerNameOptional: string|null,
     *     buyerCountry: string,
     *     addrBuilding: string,
     *     addrStreet: string,
     *     addrDistrict: string,
     *     addrCity: string,
     *     addrPostal: string,
     *     addrAdditional: string,
     *     selectedCustomerId?: int|null,
     * }  $host
     */
    public static function applyCustomerToBuyer(object $host, Customer $customer): void
    {
        $profile = is_array($customer->profile) ? $customer->profile : [];
        $na = (array) data_get($profile, 'national_address', []);
        $vat = trim((string) ($customer->vat_number ?? data_get($profile, 'vat_number', '')));

        $host->buyerName = $customer->name;
        $host->buyerPhone = (string) ($customer->phone ?? '');
        $host->buyerEmail = (string) ($customer->email ?? '');
        $host->buyerVat = $vat;
        $host->buyerCr = (string) data_get($profile, 'cr_number', '');
        $host->buyerNameOptional = $customer->name;
        $host->buyerCountry = strtoupper((string) data_get($profile, 'country', 'SA')) ?: 'SA';
        $host->addrBuilding = (string) ($na['building'] ?? '');
        $host->addrStreet = (string) ($na['street'] ?? '');
        $host->addrDistrict = (string) ($na['district'] ?? '');
        $host->addrCity = (string) ($na['city'] ?? 'Riyadh');
        $host->addrPostal = (string) ($na['postal_code'] ?? $na['postal'] ?? '');
        if (property_exists($host, 'addrPostBox')) {
            $host->addrPostBox = (string) ($customer->post_box ?? $na['post_box'] ?? '');
        }
        $host->addrAdditional = (string) ($na['additional'] ?? '');

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

        $cr = trim((string) data_get($customer->profile, 'cr_number', ''));
        if ($cr !== '') {
            $segments[] = __('invoices.customer_meta_cr', ['value' => $cr]);
        }

        return implode(' · ', $segments);
    }
}