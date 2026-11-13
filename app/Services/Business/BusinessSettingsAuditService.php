<?php

namespace App\Services\Business;

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Schema;

class BusinessSettingsAuditService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(BusinessSetting $business, string $event, array $payload = [], ?StaffUser $actor = null): void
    {
        if (! Schema::hasTable('business_settings_audit_logs')) {
            return;
        }

        \DB::table('business_settings_audit_logs')->insert([
            'staff_user_id' => $actor?->id,
            'tab' => $event,
            'snapshot' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
