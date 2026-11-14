<?php

return [

  'base_url' => rtrim((string) env('ZHEART_BASE_URL', ''), '/'),

  'api_key' => env('ZHEART_API_KEY'),

  'timeout_sign_seconds' => (int) env('ZHEART_TIMEOUT_SIGN', 45),

  'timeout_submit_seconds' => (int) env('ZHEART_TIMEOUT_SUBMIT', 120),

  'timeout_health_seconds' => (int) env('ZHEART_TIMEOUT_HEALTH', 5),

  'retry_times' => (int) env('ZHEART_RETRY_TIMES', 2),

  'retry_sleep_ms' => (int) env('ZHEART_RETRY_SLEEP_MS', 500),

  /*
   | When true, simplified invoices are reported and standard invoices cleared via Z-Heart after sign.
   | ZHEART_SUBMIT_TO_ZATCA is canonical; ZHEART_AUTO_SUBMIT is a legacy alias.
   */
  'auto_submit_signed' => filter_var(
      env('ZHEART_SUBMIT_TO_ZATCA', env('ZHEART_AUTO_SUBMIT', false)),
      FILTER_VALIDATE_BOOL
  ),

  'allow_seller_override' => filter_var(env('ZHEART_ALLOW_SELLER_OVERRIDE', false), FILTER_VALIDATE_BOOL),
];
