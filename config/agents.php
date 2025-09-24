<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Agent Protocol Defaults
    |--------------------------------------------------------------------------
    |
    | These settings control the multi-agent orchestration behavior.
    | Keep values conservative by default to stay fast and predictable.
    */

    // Number of debate/refinement rounds (K)
    'rounds' => env('AGENTS_ROUNDS', 2),

    // Top-K workers to allocate per subtask (auction shortlist)
    'workers_topk' => env('AGENTS_WORKERS_TOPK', 2),

    // Minimum groundedness score required for acceptance (0..1)
    'min_groundedness' => env('AGENTS_MIN_GROUNDEDNESS', 0.60),

    // Minority report: include candidates within epsilon of the top score
    'minority_epsilon' => env('AGENTS_MINORITY_EPSILON', 0.05),

    // Utility scoring weights used during allocation (auction heuristic)
    'scoring' => [
        // Capability match weight: how strongly to prefer skill alignment
        'weights' => [
            'capability_match' => env('AGENTS_W_CAPABILITY', 0.60),
            'expected_cost'   => env('AGENTS_W_COST', 0.20),
            'reliability'     => env('AGENTS_W_RELIABILITY', 0.20),
        ],
    ],

    // Voting aggregation weights for Critic, Workers (self-score), and Arbiter
    'vote' => [
        'weights' => [
            'critic_weight'  => env('AGENTS_W_CRITIC', 0.60),
            'worker_weight'  => env('AGENTS_W_WORKER', 0.30),
            'arbiter_weight' => env('AGENTS_W_ARBITER', 0.10),
        ],
    ],
];


