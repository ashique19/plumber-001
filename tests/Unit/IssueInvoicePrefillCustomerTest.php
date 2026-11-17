<?php

use App\Models\Customer;
use App\Support\Invoicing\IssueInvoicePrefill;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds issue-invoice prefill from a customer record', function () {
    $customer = Customer::query()->create([
        'name' => 'Buyer LLC',
        'phone' => '0501234567',
        'email' => 'buyer@example.test',
        'vat_number' => '300000000000003',
        'city' => 'Riyadh',
        'street_name' => 'King Fahd Rd',
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => [
            'national_address' => [
                'building' => '12',
                'postal_code' => '11564',
            ],
        ],
    ]);

    $prefill = IssueInvoicePrefill::fromCustomer($customer);

    expect($prefill['selectedCustomerId'])->toBe($customer->id)
        ->and($prefill['buyerPhone'])->toBe('0501234567')
        ->and($prefill['buyerName'])->toBe('Buyer LLC')
        ->and($prefill['buyerVat'])->toBe('300000000000003')
        ->and($prefill['addrCity'])->toBe('Riyadh');
});