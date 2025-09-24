<?php
/**
 * Core LLM settings: routing, providers, embeddings, and token caps.
 * Plain: This tells the app which AI to use for small vs big jobs,
 *        and how we store/search memory.
 * For engineers:
 * - Routing thresholds pick between CLASSIFY/GROUNDED/SYNTH
 * - Embeddings.dim must match the chosen embedding model
 * - Provider blocks help local/dev swaps without code changes
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Core Request Settings
    |--------------------------------------------------------------------------
    */
    'timeout_ms' => (int) env('LLM_TIMEOUT_MS', 120000), // HOW LONG to wait before declaring a timeout
    'retry' => [
        'max' => (int) env('LLM_RETRY_MAX', 1),
        'on'  => [408, 429, 500, 502, 503, 504], // WHEN to retry (network/timeouts/rate limits)
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence Calibration (optional)
    |--------------------------------------------------------------------------
    */
    'calibration' => [
        'openai'    => (float) env('LLM_CAL_OPENAI', 1.00),
        'anthropic' => (float) env('LLM_CAL_ANTHROPIC', 0.97),
        'ollama'    => (float) env('LLM_CAL_OLLAMA', 0.92),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing Policy (CLASSIFY → GROUND → SYNTH)
    |--------------------------------------------------------------------------
    | mode: auto | single
    | thresholds:
    |   - grounding_hit_min: minimum hit-rate/quality to consider retrieval “good”
    |   - synth_complexity_tokens: if input tokens exceed this, go SYNTH
    | Engineer note: tune cautiously; affects latency and accuracy.
    */
    'routing' => [
        'mode' => env('LLM_ROUTING_MODE', 'auto'),

        'thresholds' => [
            'grounding_hit_min'      => (float) env('LLM_GROUNDING_HIT_MIN', 0.35),
            'synth_complexity_tokens'=> (int) env('LLM_SYNTH_COMPLEXITY_TOKENS', 1200),
            'max_agent_steps'        => (int) env('LLM_MAX_AGENT_STEPS', 10),
        ],

        // Role bindings (provider/model). Change via .env without code edits.
        'roles' => [
            'CLASSIFY' => [
                'provider'  => env('LLM_CLASSIFY_PROVIDER', 'ollama'),
                'model'     => env('LLM_CLASSIFY_MODEL', 'mistral-small3.2:24b'),
                'tools'     => (bool) env('LLM_CLASSIFY_TOOLS', true),
                'reasoning' => (bool) env('LLM_CLASSIFY_REASONING', false),
            ],
            'GROUNDED' => [
                'provider'  => env('LLM_GROUNDED_PROVIDER', 'ollama'),
                'model'     => env('LLM_GROUNDED_MODEL', 'gpt-oss:20b'),
                'tools'     => (bool) env('LLM_GROUNDED_TOOLS', true),
                'reasoning' => (bool) env('LLM_GROUNDED_REASONING', false),
            ],
            'SYNTH' => [
                'provider'  => env('LLM_SYNTH_PROVIDER', 'ollama'),
                'model'     => env('LLM_SYNTH_MODEL', 'gpt-oss:120b'),
                'tools'     => (bool) env('LLM_SYNTH_TOOLS', true),
                'reasoning' => (bool) env('LLM_SYNTH_REASONING', true),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings (pgvector)
    |--------------------------------------------------------------------------
    | dim must match the chosen embedding model’s output length.
    */
    'embeddings' => [
        'provider'   => env('EMBEDDINGS_PROVIDER', 'ollama'),
        'model'      => env('EMBEDDINGS_MODEL', 'mxbai-embed-large'),
        'dim'        => (int) env('EMBEDDINGS_DIM', 1024),
            'distance'   => env('EMBEDDINGS_DISTANCE', 'cosine'), // WHY cosine: good default for semantic similarity
            'index_lists'=> (int) env('EMBEDDINGS_INDEX_LISTS', 100), // IVFFlat lists: bigger = faster lookup, more memory
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'chat_models' => [
                // Local-first examples; adjust based on what you have pulled in Ollama
                'mistral-small3.2:24b', 'qwen3:14b', 'qwen2.5:14b', 'gpt-oss:20b', 'gpt-oss:120b', 'llama4', 'llama3.1:8b',
            ],
            'embedding_models' => [
                // Pick one and set EMBEDDINGS_DIM accordingly
                'mxbai-embed-large', 'nomic-embed-text', 'embeddinggemma', 'bge-m3',
            ],
            // Optional hints (fallback to .env if unsure)
            'dims' => [
                'mxbai-embed-large' => 1024,
                'nomic-embed-text'  => 768,
                // For others, set EMBEDDINGS_DIM in .env explicitly if different
            ],
        ],

        'openai' => [
            'api_key'  => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'chat_models' => [
                // Examples: map any of these to CLASSIFY/GROUNDED/SYNTH by .env
                'gpt-5', 'gpt-5-mini', 'gpt-4o', 'gpt-4.1', 'gpt-4.1-mini',
            ],
            'embedding_models' => [
                'text-embedding-3-small', 'text-embedding-3-large',
            ],
        ],

        'anthropic' => [
            'api_key'  => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'chat_models' => [
                'claude-3.5-sonnet', 'claude-3.5-haiku',
                'claude-4-sonnet', 'claude-4-opus',
            ],
            'embedding_models' => [
                // Anthropic typically via partner services; leave empty unless available.
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Caps (kept from your original)
    |--------------------------------------------------------------------------
    */
    'caps' => [
        'input_tokens'   => (int) env('LLM_CAP_INPUT_TOKENS', 2000),
        'summary_tokens' => (int) env('LLM_CAP_SUMMARY_TOKENS', 500),
        'output_tokens'  => (int) env('LLM_CAP_OUTPUT_TOKENS', 1000),
    ],
];
