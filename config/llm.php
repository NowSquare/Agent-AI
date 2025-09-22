<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Primary provider with automatic fallback to Ollama.
    | Supported providers: openai, anthropic, ollama
    |
    */

    'provider' => env('LLM_PROVIDER', 'ollama'),
    'model' => env('LLM_MODEL', 'llama3.2'),

    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    */

    'timeout_ms' => 4000,
    'retry' => [
        'max' => 1,
        'on' => [408, 429, 500, 502, 503, 504], // HTTP status codes to retry
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence Calibration
    |--------------------------------------------------------------------------
    |
    | Multipliers applied to LLM-reported confidence scores.
    | Used to normalize confidence across different providers.
    |
    */

    'calibration' => [
        'openai' => 1.00,
        'anthropic' => 0.97,
        'ollama' => 0.92,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Limits
    |--------------------------------------------------------------------------
    |
    | Maximum tokens for different types of requests.
    | These are conservative limits to control costs and latency.
    |
    */

    'caps' => [
        'input_tokens' => 2000,     // Clean reply + context
        'summary_tokens' => 500,    // Thread summaries
        'output_tokens' => 300,     // JSON responses
    ],
];
