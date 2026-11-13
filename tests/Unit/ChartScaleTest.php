<?php

use App\Support\ChartScale;

it('returns floor when values are empty', function () {
    expect(ChartScale::floorMax(1, []))->toBe(1)
        ->and(ChartScale::floorMax(1.0, []))->toBe(1.0);
});

it('returns floor max of values when present', function () {
    expect(ChartScale::floorMax(1, [0, 5, 2]))->toBe(5)
        ->and(ChartScale::floorMax(10, [3, 7]))->toBe(10);
});
