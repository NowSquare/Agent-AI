<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | This array maps ISO language codes to their full locale identifiers.
    | The keys can be either ISO 639-1 codes (e.g., 'en') or full locale
    | identifiers (e.g., 'en_US'). The values must be full locale identifiers.
    |
    */

    'supported_locales' => [
        // English
        'en' => 'en_US',
        'en_us' => 'en_US',

        // Dutch
        'nl' => 'nl_NL',
        'nl_nl' => 'nl_NL',

        // French
        'fr' => 'fr_FR',
        'fr_fr' => 'fr_FR',

        // German
        'de' => 'de_DE',
        'de_de' => 'de_DE',
    ],

    /*
    |--------------------------------------------------------------------------
    | Language Detection
    |--------------------------------------------------------------------------
    |
    | Configuration for the language detection service.
    |
    */

    'detection' => [
        // Confidence threshold for library-based detection
        'min_confidence' => 0.8,

        // Cache duration for detected languages (in hours)
        'cache_ttl' => 24,

        // Whether to use LLM fallback when library detection fails
        'use_llm_fallback' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Detection Sources Priority
    |--------------------------------------------------------------------------
    |
    | The order in which different sources are checked for language detection.
    | Available sources: 'url', 'session', 'header', 'content'
    |
    */

    'detection_priority' => [
        'url',      // URL parameter (?lang=)
        'session',  // Session storage
        'header',   // Accept-Language header
        'content',  // Content-based detection
    ],
];
