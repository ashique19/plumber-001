<?php

return [
    'auth_failed' => 'Z-Heart rejected the API key. Check the key in admin admin or rotate it in Z-Heart.',
    'ip_not_allowed' => 'This server IP is not on the Z-Heart API key allowlist. Ask the operator to add your egress IP.',
    'client_suspended' => 'The Z-Heart client is suspended. Contact the admin operator.',
    'client_not_active' => 'The Z-Heart client is not active.',
    'validation_failed' => 'Z-Heart rejected the invoice payload. Review line items and buyer fields, then try again.',
    'csid_required' => 'Z-Heart needs a compliance CSID (onboarding) before report/clear. Complete signing service onboarding with request id :id.',
    'standard_buyer_vat_required' => 'Standard invoices require the buyer VAT number before submitting to Z-Heart.',
    'submission_failed' => 'Z-Heart could not report or clear this invoice with signing service.',
    'server_error' => 'Z-Heart returned a server error. Retry later or contact support with request id :id.',
    'generic_failure' => 'Z-Heart signing failed. Check configuration and try again.',
    'not_configured' => 'Z-Heart base URL or API key is missing for this business.',
    'health_ok' => 'Z-Heart health check passed.',
    'health_failed' => 'Z-Heart health check failed.',
];
