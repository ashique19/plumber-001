<?php

$en = require __DIR__.'/../en/stock.php';

return array_replace_recursive($en, [
    'title' => 'পণ্য স্টক',
    'subtitle' => 'হাতে থাকা পরিমাণ, খরচ ও চলাচলের ইতিহাস খুঁজুন।',
    'search' => 'খুঁজুন',
    'search_placeholder' => 'নাম বা SKU',
    'low_stock_only' => 'শুধু কম স্টক',
    'product' => 'পণ্য',
    'on_hand' => 'হাতে আছে',
    'cost' => 'গড় খরচ (বিএট ছাড়া)',
    'track_stock' => 'স্টক পরিমাণ ট্র্যাক করুন',
    'low_stock_threshold' => 'কম স্টক সীমা',
    'low_stock_badge' => 'কম',
    'empty' => 'ফিল্টারের সাথে মিলে এমন কোনো ট্র্যাকড পণ্য নেই।',
    'view_movements' => 'চলাচল',
    'movements_heading' => 'স্টক চলাচলের লগ',
    'clear_filter' => 'সব দেখুন',
    'movement_when' => 'সময়',
    'movement_type' => 'ধরন',
    'movement_delta' => 'পরিমাণ পরিবর্তন',
    'no_movements' => 'এখনও কোনো চলাচল লগ হয়নি।',
    'movement_types' => [
        'opening' => 'আরম্ভিক স্টক',
        'purchase' => 'ক্রয়',
        'invoice_issue' => 'ইনভয়েস',
        'credit_note' => 'ক্রেডিট নোট',
    ],
]);
