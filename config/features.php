<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product features (single-tenant toggles)
    |--------------------------------------------------------------------------
    |
    | Plumber ships with loyalty disabled (Fancy Coffee heritage). Enable via
    | FEATURE_LOYALTY=true when deploying a client that needs points/referrals.
    |
    */

    'loyalty' => (bool) env('FEATURE_LOYALTY', false),

];