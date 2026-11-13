<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\LoyaltyTransaction;
use App\Support\Loyalty\LoyaltyConfig;
use Illuminate\Console\Command;

class LoyaltyExpirePointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Expire loyalty points past configured expiry (ledger note only).';

    public function handle(): int
    {
        $business = BusinessSetting::query()->find(1);
        if ($business === null) {
            return self::SUCCESS;
        }

        $days = LoyaltyConfig::for($business)->pointsExpiryDays();
        if ($days === null) {
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $customerIds = LoyaltyTransaction::query()
            ->where('created_at', '<', $cutoff)
            ->pluck('customer_id')
            ->unique();

        $this->line("Business {$business->name}: {$customerIds->count()} customers with stale activity (review manually).");

        return self::SUCCESS;
    }
}
