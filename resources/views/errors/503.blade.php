@include('errors.partials.http', [
    'code' => '503',
    'heading' => __('errors.unavailable_heading'),
    'message' => __('errors.unavailable_lead'),
])
