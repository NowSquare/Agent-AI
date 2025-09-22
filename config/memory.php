<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Memory Gate Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the memory gate's behavior for persisting and retrieving memories.
    | This includes confidence thresholds, TTL settings, and PII rules.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Confidence Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure minimum confidence levels for memory operations.
    |
    */
    'min_confidence_to_persist' => env('MEMORY_MIN_CONFIDENCE', 0.60),
    'include_threshold' => env('MEMORY_INCLUDE_THRESHOLD', 0.45),

    /*
    |--------------------------------------------------------------------------
    | TTL Settings (in days)
    |--------------------------------------------------------------------------
    |
    | Configure expiry periods for different memory categories.
    |
    */
    'ttl_days' => [
        'volatile' => env('MEMORY_TTL_VOLATILE', 30),
        'seasonal' => env('MEMORY_TTL_SEASONAL', 120),
        'durable' => env('MEMORY_TTL_DURABLE', 730),
        'legal' => null, // No expiry
    ],

    /*
    |--------------------------------------------------------------------------
    | Decay Function Parameters
    |--------------------------------------------------------------------------
    |
    | Configure how memory relevance decays over time.
    |
    */
    'decay' => [
        'half_life_multiplier' => env('MEMORY_DECAY_MULTIPLIER', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scope Boost Factors
    |--------------------------------------------------------------------------
    |
    | Configure relevance boost factors for different memory scopes.
    |
    */
    'scope_boosts' => [
        'conversation' => env('MEMORY_BOOST_CONVERSATION', 1.4),
        'user' => env('MEMORY_BOOST_USER', 1.2),
        'account' => env('MEMORY_BOOST_ACCOUNT', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Excerpt Settings
    |--------------------------------------------------------------------------
    |
    | Configure how memory excerpts are generated for prompts.
    |
    */
    'max_excerpt_chars' => env('MEMORY_MAX_EXCERPT_CHARS', 1200),
    'max_excerpt_items' => env('MEMORY_MAX_EXCERPT_ITEMS', 6),

    /*
    |--------------------------------------------------------------------------
    | PII Rule Set
    |--------------------------------------------------------------------------
    |
    | Configure patterns and rules for PII detection and redaction.
    | Each rule can have a regex pattern and/or exact matches to look for.
    |
    */
    'pii_rule_set' => [
        [
            'type' => 'email',
            'pattern' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        ],
        [
            'type' => 'phone',
            'pattern' => '/(\+\d{1,3}[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/',
        ],
        [
            'type' => 'credit_card',
            'pattern' => '/\b(?:\d[ -]*?){13,16}\b/',
        ],
        [
            'type' => 'ssn',
            'pattern' => '/\b\d{3}[-.]?\d{2}[-.]?\d{4}\b/',
        ],
        [
            'type' => 'ip_address',
            'pattern' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
        ],
        [
            'type' => 'password',
            'matches' => [
                'password',
                'passwd',
                'pwd',
                'secret',
                'token',
            ],
        ],
        [
            'type' => 'financial',
            'matches' => [
                'bank account',
                'routing number',
                'swift code',
                'iban',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning Settings
    |--------------------------------------------------------------------------
    |
    | Configure how and when memories are pruned.
    |
    */
    'pruning' => [
        'batch_size' => env('MEMORY_PRUNE_BATCH_SIZE', 1000),
        'min_age_days' => env('MEMORY_PRUNE_MIN_AGE', 7),
        'min_score' => env('MEMORY_PRUNE_MIN_SCORE', 0.2),
    ],
];
