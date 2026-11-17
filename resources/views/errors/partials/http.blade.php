@include('errors.layout', [
    'code' => $code ?? null,
    'heading' => $heading ?? null,
    'message' => $message ?? null,
    'title' => $title ?? null,
])
