@include('errors.partials.http', [
    'code' => '500',
    'heading' => __('errors.server_heading'),
    'message' => __('errors.server_lead'),
])
