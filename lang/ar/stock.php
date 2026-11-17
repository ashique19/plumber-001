<?php

$en = require __DIR__.'/../en/stock.php';

return array_replace_recursive($en, [
    'title' => 'مخزون المنتجات',
    'subtitle' => 'ابحث عن الكميات والتكاليف وسجل الحركات.',
    'search' => 'بحث',
    'search_placeholder' => 'الاسم أو SKU',
    'low_stock_only' => 'مخزون منخفض فقط',
    'product' => 'المنتج',
    'on_hand' => 'المتوفر',
    'cost' => 'متوسط التكلفة (بدون ضريبة)',
    'track_stock' => 'تتبع كمية المخزون',
    'low_stock_threshold' => 'حد المخزون المنخفض',
    'low_stock_badge' => 'منخفض',
    'empty' => 'لا توجد منتجات مطابقة.',
    'view_movements' => 'الحركات',
    'movements_heading' => 'سجل حركة المخزون',
    'clear_filter' => 'عرض الكل',
    'movement_when' => 'الوقت',
    'movement_type' => 'النوع',
    'movement_delta' => 'تغير الكمية',
    'no_movements' => 'لا توجد حركات بعد.',
    'movement_types' => [
        'opening' => 'رصيد افتتاحي',
        'purchase' => 'شراء',
        'invoice_issue' => 'إصدار فاتورة',
        'credit_note' => 'إشعار دائن',
    ],
]);
