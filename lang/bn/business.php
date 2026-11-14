<?php

$en = require __DIR__.'/../en/business.php';

$en['nav'] = 'ব্যবসা';
$en['legal']['email'] = 'ইনভয়েস ইমেইল';
$en['legal']['mobile'] = 'মোবাইল';
$en['legal']['mobile_secondary'] = 'মোবাইল ২';
$en['legal']['short_national'] = 'সংক্ষিপ্ত জাতীয় ঠিকানা';
$en['legal']['na_street_ar'] = 'সড়ক (আরবি)';
$en['legal']['na_district_ar'] = 'জেলা (আরবি)';
$en['legal']['na_city_ar'] = 'শহর (আরবি)';
$en['legal']['na_country'] = 'দেশ (ISO কোড)';
$en['legal']['optional_hint'] = 'সব ক্ষেত্র ঐচ্ছিক। শুধু পূরণ করা মান ইনভয়েসে দেখাবে।';
$en['legal']['logo_on_brand'] = 'লোগো ব্র্যান্ড ট্যাবে আপলোড করুন।';
$en['invoicing']['payment_terms_days'] = 'পেমেন্ট শর্ত (দিন)';
$en['nav'] = 'ব্যবসা';
$en['fields'] = [
    'vat_number' => 'ভ্যাট নম্বর',
    'cr_number' => 'বাণিজ্যিক নিবন্ধন (CR)',
    'building' => 'বিল্ডিং নং',
    'street' => 'রাস্তা',
    'district' => 'এলাকা',
    'city' => 'শহর',
    'postal' => 'পোস্টাল কোড',
    'additional' => 'অতিরিক্ত নং',
    'validation' => [
        'vat_format' => 'ভ্যাট নম্বর ১৫ অঙ্কের হতে হবে এবং ৩ দিয়ে শুরু ও শেষ হতে হবে।',
    ],
];

return $en;