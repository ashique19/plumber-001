<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Desktop admin sidebar
    |--------------------------------------------------------------------------
    |
    | When false (default), the fixed left sidebar is hidden on lg+ and main
    | content is full width. Mobile bottom tab bar and "More" sheet are unchanged.
    | Set UI_ADMIN_SIDEBAR=true to restore the desktop sidebar.
    |
    */
    'admin_sidebar' => (bool) env('UI_ADMIN_SIDEBAR', false),

];