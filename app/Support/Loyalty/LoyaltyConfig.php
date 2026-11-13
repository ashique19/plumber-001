<?php

namespace App\Support\Loyalty;

use App\Models\BusinessSetting;

final class LoyaltyConfig
{
    public function __construct(private readonly BusinessSetting $business) {}

    public static function for(BusinessSetting $business): self
    {
        return new self($business);
    }

    public function earnPointsPerSarExVat(): float
    {
        return (float) data_get($this->business->settings, 'loyalty.earn_rate', 1.0);
    }

    public function redemptionSarExVatPerPoint(): float
    {
        return (float) data_get($this->business->settings, 'loyalty.redemption_value_ex_vat', 0.1);
    }

    public function minimumRedeemPoints(): int
    {
        return (int) data_get($this->business->settings, 'loyalty.minimum_redeem_points', 50);
    }

    public function maxRedeemPercentOfOrder(): float
    {
        return (float) data_get($this->business->settings, 'loyalty.max_redeem_percent', 50);
    }

    public function referrerBonusPoints(): int
    {
        return (int) data_get($this->business->settings, 'loyalty.referrer_bonus_points', 25);
    }

    public function pointsExpiryDays(): ?int
    {
        $days = data_get($this->business->settings, 'loyalty.points_expiry_days');

        return $days === null ? null : (int) $days;
    }
}
