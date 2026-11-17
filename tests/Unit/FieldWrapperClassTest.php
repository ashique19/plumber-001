<?php

use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    view()->share('errors', new ViewErrorBag);
});

it('forwards wrapper class from field input to form label', function () {
    $html = \Illuminate\Support\Facades\Blade::render(
        '<x-field.input label="L" name="n" wrapper-class="form-field--span-2" />'
    );

    expect($html)->toContain('class="form-field form-field--span-2"');
});

it('forwards wrapper class from field select to form label', function () {
    $html = \Illuminate\Support\Facades\Blade::render(
        '<x-field.select label="L" name="n" wrapper-class="form-field--span-2"><option>x</option></x-field.select>'
    );

    expect($html)->toContain('class="form-field form-field--span-2"');
});