<?php

return [
    'debug' => [
        'enabled' => filter_var(env('LOCATION_SUGGESTIONS_DEBUG_ENABLED', env('APP_DEBUG', true)), FILTER_VALIDATE_BOOL),
    ],

    'rate_limits' => [
        'submit_per_minute' => (int) env('LOCATION_SUGGESTIONS_SUBMIT_PER_MINUTE', 20),
        'submit_per_hour' => (int) env('LOCATION_SUGGESTIONS_SUBMIT_PER_HOUR', 100),
        'status_polls_per_minute' => (int) env('LOCATION_SUGGESTIONS_STATUS_POLLS_PER_MINUTE', 120),
    ],

    'openai' => [
        'video_chunk_size' => (int) env('LOCATION_SUGGESTIONS_OPENAI_VIDEO_CHUNK_SIZE', 8),
        'single_video_image_detail' => env('LOCATION_SUGGESTIONS_OPENAI_SINGLE_VIDEO_IMAGE_DETAIL', 'high'),
        'chunked_video_image_detail' => env('LOCATION_SUGGESTIONS_OPENAI_CHUNKED_VIDEO_IMAGE_DETAIL', 'low'),
    ],

    'async' => [
        'enabled' => filter_var(env('LOCATION_SUGGESTIONS_ASYNC_ENABLED', true), FILTER_VALIDATE_BOOL),
        'ttl_minutes' => (int) env('LOCATION_SUGGESTIONS_ASYNC_TTL_MINUTES', 1440),
        'auto_route_long_videos' => filter_var(env('LOCATION_SUGGESTIONS_AUTO_ROUTE_LONG_VIDEOS', true), FILTER_VALIDATE_BOOL),
        'auto_route_video_seconds' => (int) env('LOCATION_SUGGESTIONS_AUTO_ROUTE_VIDEO_SECONDS', 45),
    ],

    'video_processing' => [
        'enabled' => filter_var(env('LOCATION_SUGGESTIONS_VIDEO_ENABLED', true), FILTER_VALIDATE_BOOL),
        'yt_dlp_path' => env('YTDLP_PATH', ''),
        'yt_dlp_cookies_path' => env('YTDLP_COOKIES_PATH', ''),
        'yt_dlp_js_runtimes' => env('YTDLP_JS_RUNTIMES', ''),
        'ffmpeg_path' => env('FFMPEG_PATH', ''),
        'frame_divisor_seconds' => (int) env('LOCATION_SUGGESTIONS_FRAME_DIVISOR_SECONDS', 2),
        'max_video_seconds' => (int) env('LOCATION_SUGGESTIONS_MAX_VIDEO_SECONDS', 45),
        'frame_interval_seconds' => (int) env('LOCATION_SUGGESTIONS_FRAME_INTERVAL_SECONDS', 3),
        'max_frames' => (int) env('LOCATION_SUGGESTIONS_MAX_FRAMES', 8),
    ],
];
