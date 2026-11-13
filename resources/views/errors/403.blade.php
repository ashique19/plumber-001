@include('errors.partials.http', [
    'code' => '403',
    'heading' => __('errors.forbidden_heading'),
    'message' => __('errors.forbidden_lead'),
])
