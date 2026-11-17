<?php

$en = require __DIR__.'/../en/purchases.php';

return array_replace_recursive($en, [
    'create_title' => 'تسجيل شراء',
    'create_subtitle' => 'استلام مخزون من المورد وتحديث متوسط التكلفة.',
    'supplier_heading' => 'المورد',
    'supplier_search' => 'البحث عن مورد',
    'clear_supplier' => 'إزالة المورد',
    'supplier_selected' => 'المحدد: :name',
    'supplier' => 'مورد موجود',
    'supplier_new' => 'مورد جديد',
    'supplier_name' => 'اسم المورد',
    'supplier_vat' => 'الرقم الضريبي',
    'supplier_phone' => 'الهاتف',
    'notes' => 'ملاحظات',
    'lines_heading' => 'البنود',
    'line_product' => 'منتج',
    'choose_product' => 'اختر منتجاً',
    'quantity' => 'الكمية',
    'unit_cost_ex_vat' => 'تكلفة الوحدة (بدون ضريبة)',
    'add_line' => 'إضافة بند',
    'remove_line' => 'إزالة بند',
    'submit' => 'حفظ الشراء',
    'recorded' => 'تم تسجيل الشراء :number وتحديث المخزون.',
]);
