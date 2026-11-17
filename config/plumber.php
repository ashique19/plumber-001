<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plumber product feature flags
    |--------------------------------------------------------------------------
    |
    | Defaults suit the plumber single-client deployment (modifiers and loyalty
    | UI off). Enable per environment for café-style catalog behaviour.
    |
    */
    'features' => [
        'loyalty' => (bool) env('FEATURE_LOYALTY', false),
        'modifiers' => (bool) env('FEATURE_PRODUCT_MODIFIERS', false),
    ],

];