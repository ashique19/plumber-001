<?php

use App\Models\StaffUser;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'staff'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'staff_users'),
    ],

    'guards' => [
        'staff' => [
            'driver' => 'session',
            'provider' => 'staff_users',
        ],
    ],

    'providers' => [
        'staff_users' => [
            'driver' => 'eloquent',
            'model' => StaffUser::class,
        ],
    ],

    'passwords' => [
        'staff_users' => [
            'provider' => 'staff_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
