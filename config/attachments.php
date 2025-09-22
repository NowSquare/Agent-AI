<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Attachment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for attachment processing, including size limits,
    | ClamAV settings, and file handling.
    |
    */

    'max_size_mb' => env('ATTACH_MAX_SIZE_MB', 25),

    'total_max_size_mb' => env('ATTACH_TOTAL_MAX_SIZE_MB', 40),

    'clamav' => [
        'host' => env('CLAMAV_HOST', '127.0.0.1'),
        'port' => env('CLAMAV_PORT', 3310),
    ],

    'mime_whitelist' => [
        'text/plain',
        'text/markdown',
        'text/csv',
        'application/pdf',
    ],

    'storage' => [
        'disk' => 'local',
        'path_prefix' => 'attachments',
    ],

    'processing' => [
        'queue' => 'attachments',
        'text_extraction_timeout' => 30, // seconds
        'max_extracted_text_length' => 50000, // characters
        'max_excerpt_length' => 1000, // characters for LLM context
    ],

    'downloads' => [
        'signed_url_expiry' => 60 * 15, // 15 minutes
        'require_nonce' => true,
    ],
];
