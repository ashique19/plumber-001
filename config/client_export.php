<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Single-tenant client deployment
    |--------------------------------------------------------------------------
    |
    | When enabled, this build is a portable export for one client: the app
    | boots against database/seed/client-tenant.json and "/" redirects to the
    | client's public storefront (café theme) or tenant slug path.
    |
    */

    'enabled' => filter_var(env('SINGLE_TENANT_MODE', false), FILTER_VALIDATE_BOOL),

    'tenant_slug' => env('CLIENT_TENANT_SLUG', ''),

    'bundle_version' => 1,

];
