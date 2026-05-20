<?php

return [

    'google' => [
        'places_api_key'              => env('GOOGLE_MAPS_API_KEY'),
        'places_base_url'             => env('GOOGLE_PLACES_BASE_URL', 'https://places.googleapis.com/v1'),
        'vision_api_key'              => env('GOOGLE_VISION_API_KEY'),
        // Video Intelligence requires Service Account OAuth2 — API keys are NOT supported by Google
        'service_account_json'        => env('GOOGLE_CLOUD_SERVICE_ACCOUNT_JSON'),
        'token_uri'                   => env('GOOGLE_CLOUD_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
        'video_intelligence_base_url' => 'https://videointelligence.googleapis.com/v1',
    ],

    'video' => [
        'max_download_bytes' => (int) env('LOCATION_INTELLIGENCE_MAX_VIDEO_BYTES', 52428800), // 50 MB
        'download_timeout'   => (int) env('LOCATION_INTELLIGENCE_VIDEO_DOWNLOAD_TIMEOUT', 60),
        'poll_timeout'       => (int) env('LOCATION_INTELLIGENCE_VIDEO_POLL_TIMEOUT', 60),
        'poll_interval'      => (int) env('LOCATION_INTELLIGENCE_VIDEO_POLL_INTERVAL', 5),
    ],

    'rate_limiting' => [
        'key'          => 'location-intelligence',
        'max_attempts' => (int) env('LOCATION_INTELLIGENCE_RATE_MAX', 20),
        'per_minutes'  => (int) env('LOCATION_INTELLIGENCE_RATE_MINUTES', 1),
    ],

];
