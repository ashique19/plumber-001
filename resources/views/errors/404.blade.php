@include('errors.partials.http', [
    'code' => '404',
    'heading' => __('errors.not_found_heading'),
    'message' => __('errors.not_found_lead'),
])
