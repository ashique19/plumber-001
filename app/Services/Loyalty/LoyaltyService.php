<?php

namespace App\Services\Loyalty;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\PosOrder;
use App\Support\Loyalty\LoyaltyConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LoyaltyService
{
    public function ensureReferralCode(Customer $customer): Customer
    {
        if ($customer->referral_code !== '') {
            return $customer;
        }

        $customer->forceFill([
            'referral_code' => strtoupper(Str::random(8)),
        ])->save();

        return $customer->fresh();
    }

    public function earnForPaidOrder(PosOrder $order): void
    {
        if ($order->customer_id === null) {
            return;
        }

        $customer = $order->customer()->firstOrFail();
        $config = LoyaltyConfig::for(\App\Business\BusinessContext::require());
        $points = (int) floor((float) $order->subtotal_ex_vat * $config->earnPointsPerSarExVat());

        if ($points <= 0) {
            return;
        }

        $this->record($customer, 'earn', $points, $order, ['reason' => 'pos_paid']);
        $this->maybeAwardReferrer($customer, $order, $config);
    }

    public function redeemOnOrder(PosOrder $order, Customer $customer, int $points): float
    {
        $config = LoyaltyConfig::for(\App\Business\BusinessContext::require());

        if ($points < $config->minimumRedeemPoints()) {
            throw new \InvalidArgumentException('Below minimum redemption points.');
        }

        if ($points > (int) $customer->points_balance) {
            throw new \InvalidArgumentException('Insufficient points.');
        }

        $discountExVat = $points * $config->redemptionSarExVatPerPoint();
        $maxDiscount = (float) $order->subtotal_ex_vat * ($config->maxRedeemPercentOfOrder() / 100);
        $discountExVat = min($discountExVat, $maxDiscount);

        if ($discountExVat <= 0) {
            throw new \InvalidArgumentException('Redemption value is zero.');
        }

        $this->record($customer, 'redeem', -$points, $order, [
            'discount_ex_vat' => $discountExVat,
        ]);

        return $discountExVat;
    }

    public function adjustPoints(Customer $customer, int $pointsDelta, string $reason): LoyaltyTransaction
    {
        $reason = trim($reason);
        if ($pointsDelta === 0) {
            throw new \InvalidArgumentException('Points adjustment cannot be zero.');
        }

        if ($reason === '') {
            throw new \InvalidArgumentException('Reason is required.');
        }

        $nextBalance = (int) $customer->points_balance + $pointsDelta;
        if ($nextBalance < 0) {
            throw new \InvalidArgumentException('Adjustment would make balance negative.');
        }

        return $this->record($customer, 'adjust', $pointsDelta, null, ['reason' => $reason]);
    }

    public function attachCustomerByReferral(PosOrder $order, string $referralCode, ?string $phone = null): Customer
    {
        $code = strtoupper(trim($referralCode));
        $referrer = Customer::query()
            ->where('referral_code', $code)
            ->first();

        if ($referrer === null) {
            throw new \InvalidArgumentException('Invalid referral code.');
        }

        if ($phone !== null) {
            $existing = Customer::query()->where('phone', $phone)->first();
            if ($existing !== null) {
                if ($existing->id === $referrer->id) {
                    throw new \InvalidArgumentException('Self-referral is not allowed.');
                }

                return $existing;
            }
        }

        $customer = Customer::query()->create([
            'name' => $phone ?? __('customers.guest'),
            'phone' => $phone,
            'referral_code' => strtoupper(Str::random(8)),
            'referred_by_customer_id' => $referrer->id,
            'points_balance' => 0,
        ]);

        $order->forceFill(['customer_id' => $customer->id])->save();

        return $customer;
    }

    private function maybeAwardReferrer(Customer $customer, PosOrder $order, LoyaltyConfig $config): void
    {
        if ($customer->referred_by_customer_id === null) {
            return;
        }

        $priorPaid = PosOrder::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->where('id', '<', $order->id)
            ->exists();

        if ($priorPaid) {
            return;
        }

        $bonus = $config->referrerBonusPoints();
        if ($bonus <= 0) {
            return;
        }

        $referrer = Customer::query()->find($customer->referred_by_customer_id);
        if ($referrer === null) {
            return;
        }

        $this->record($referrer, 'referral', $bonus, $order, [
            'referred_customer_id' => $customer->id,
        ]);
    }

    private function record(
        Customer $customer,
        string $type,
        int $pointsDelta,
        ?PosOrder $order = null,
        array $metadata = [],
    ): LoyaltyTransaction {
        $customer->increment('points_balance', $pointsDelta);

        return LoyaltyTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => $type,
            'points_delta' => $pointsDelta,
            'monetary_value_ex_vat' => $metadata['discount_ex_vat'] ?? null,
            'pos_order_id' => $order?->id,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
