<?php

use App\Support\Validation\FieldValidationRules;
use Illuminate\Support\Facades\Validator;

it('requires vat or cr for standard buyer fields', function () {
    $rules = [
        'buyerVat' => array_merge(FieldValidationRules::vatOptional(), ['required_without:buyerCr']),
        'buyerCr' => array_merge(FieldValidationRules::crNumberOptional(), ['required_without:buyerVat']),
    ];

    $validator = Validator::make(['buyerVat' => '', 'buyerCr' => ''], $rules);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->keys())->toContain('buyerVat', 'buyerCr');
});

it('accepts cr without vat', function () {
    $rules = [
        'buyerVat' => array_merge(FieldValidationRules::vatOptional(), ['required_without:buyerCr']),
        'buyerCr' => array_merge(FieldValidationRules::crNumberOptional(), ['required_without:buyerVat']),
    ];

    $validator = Validator::make(['buyerVat' => '', 'buyerCr' => '1010998899'], $rules);

    expect($validator->fails())->toBeFalse();
});