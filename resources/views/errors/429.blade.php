@include('errors.ug-error', [
    'ugCode' => 429,
    'ugHeading' => 'Too many requests',
    'ugMessage' => 'We received too many requests from this session in a short period. Please wait a moment and try again.',
])
