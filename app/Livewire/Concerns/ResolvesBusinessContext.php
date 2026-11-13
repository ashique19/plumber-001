<?php

namespace App\Livewire\Concerns;

use App\Business\BusinessContext;
use App\Models\BusinessSetting;

trait ResolvesBusinessContext
{
    public BusinessSetting $business;

    protected function resolveBusiness(): BusinessSetting
    {
        $business = BusinessContext::get() ?? BusinessSetting::query()->find(1);
        if ($business === null) {
            abort(503, __('business.not_configured'));
        }
        BusinessContext::set($business);
        $this->business = $business;

        return $business;
    }
}
