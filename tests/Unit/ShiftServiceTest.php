<?php

use App\Models\Branch;
use App\Services\Operations\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('prevents two open shifts on the same branch', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier] = posTenantSetup();
    $svc = app(ShiftService::class);

    $svc->openShift($branch, $cashier, 100.0);

    $svc->openShift($branch, $cashier, 50.0);
})->throws(ValidationException::class);
