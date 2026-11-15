<?php

$en = require __DIR__.'/../en/purchases.php';

return array_replace_recursive($en, [
    'create_title' => 'ক্রয় রেকর্ড',
    'create_subtitle' => 'সরবরাহকারীর কাছ থেকে স্টক গ্রহণ ও গড় খরচ আপডেট।',
    'supplier_heading' => 'সরবরাহকারী',
    'supplier' => 'বিদ্যমান সরবরাহকারী',
    'supplier_new' => 'নতুন সরবরাহকারী',
    'supplier_name' => 'সরবরাহকারীর নাম',
    'supplier_vat' => 'ভ্যাট নম্বর',
    'supplier_phone' => 'ফোন',
    'lines_heading' => 'লাইন আইটেম',
    'choose_product' => 'পণ্য বেছে নিন',
    'quantity' => 'পরিমাণ',
    'unit_cost_ex_vat' => 'একক খরচ (বিএট ছাড়া)',
    'add_line' => 'লাইন যোগ',
    'remove_line' => 'লাইন সরান',
    'submit' => 'ক্রয় সংরক্ষণ',
    'recorded' => 'ক্রয় :number সংরক্ষিত এবং স্টক আপডেট হয়েছে।',
]);
