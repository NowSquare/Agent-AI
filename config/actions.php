<?php
/**
 * What this file does — Defines a tiny, editable action schema with preconditions/effects.
 * Plain: A checklist of “when you can do this action” and “what changes after”.
 * For engineers: Strings use simple operators (=, <, >, <=, >=). Facts are key=value pairs.
 */

return [
    'ScanAttachment' => [
        'pre' => ['has_attachment=true','clamav_ready=true','scanned=false'],
        'eff' => ['scanned=true'],
    ],
    'ExtractText' => [
        'pre' => ['scanned=true','extracted=false'],
        'eff' => ['extracted=true','text_available=true'],
    ],
    'Summarize' => [
        'pre' => ['text_available=true'],
        'eff' => ['summary_ready=true'],
    ],
    'AskClarification' => [
        'pre' => ['confidence<LLM_MIN_CONF'],
        'eff' => ['clarification_sent=true'],
    ],
    'SendReply' => [
        'pre' => ['summary_ready=true','confidence>=LLM_MIN_CONF'],
        'eff' => ['reply_ready=true'],
    ],
    'OptionsEmail' => [
        'pre' => ['confidence<LLM_MIN_CONF'],
        'eff' => ['options_sent=true'],
    ],
    'MemoryUpdate' => [
        'pre' => ['summary_ready=true'],
        'eff' => ['memory_updated=true'],
    ],
    'Classify' => [
        'pre' => ['received=true'],
        'eff' => ['classified=true'],
    ],
    'Retrieve' => [
        'pre' => ['classified=true'],
        'eff' => ['retrieval_done=true'],
    ],
    'GroundedAnswer' => [
        'pre' => ['retrieval_done=true','text_available=true'],
        'eff' => ['summary_ready=true','confidence+=0.1'],
    ],
];


