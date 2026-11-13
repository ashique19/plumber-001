<?php

return [
    'supported' => [
        'en' => [
            'name' => 'English',
            'dir' => 'ltr',
            'enabled' => true,
        ],
        'ar' => [
            'name' => 'العربية',
            'dir' => 'rtl',
            'enabled' => true,
        ],
        'ur' => ['name' => 'اردو', 'dir' => 'rtl', 'enabled' => false],
        'bn' => ['name' => 'বাংলা', 'dir' => 'ltr', 'enabled' => true],
        'hi' => ['name' => 'हिन्दी', 'dir' => 'ltr', 'enabled' => false],
    ],
    'default' => env('APP_LOCALE', 'en'),
];
