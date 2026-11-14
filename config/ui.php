<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Desktop admin sidebar
    |--------------------------------------------------------------------------
    |
    | When false (default), the left sidebar and mobile tab bar / "More" drawer are
    | hidden at every breakpoint; dashboard quick actions are the main navigation.
    | Set UI_ADMIN_SIDEBAR=true to restore sidebar (lg+) and mobile bottom nav.
    |
    */
    'admin_sidebar' => (bool) env('UI_ADMIN_SIDEBAR', false),

];