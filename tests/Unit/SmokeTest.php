<?php

it('bootstraps autoload', function () {
    expect(class_exists(\App\Models\BusinessSetting::class))->toBeTrue();
});
