<?php

return [
    'loyalty' => filter_var(env('FEATURE_LOYALTY', true), FILTER_VALIDATE_BOOL),
];