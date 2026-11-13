<?php

use App\Business\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesZHeartSigning;
use Tests\TestCase;

require_once __DIR__.'/Support/PosTestHelpers.php';

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->use(FakesZHeartSigning::class)
    ->beforeEach(function () {
        $this->fakeZHeartSigning();
    })
    ->afterEach(function () {
        BusinessContext::clear();
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
