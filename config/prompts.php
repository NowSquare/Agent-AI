<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Prompt Templates
    |--------------------------------------------------------------------------
    |
    | Structured prompt templates for different LLM tasks.
    | All prompts enforce JSON-only output with schema validation.
    |
    */

    'action_interpret' => [
        'temperature' => 0.2,
        'backstory' => 'You convert a user email reply into exactly one allowed action with parameters. Output JSON only.',
        'template' => 'You are a strict JSON generator. Detect exactly ONE action from the whitelist below based on the user\'s reply and context. Return JSON matching the schema. No prose, no explanations.

ALLOWED action_type:
- "approve"
- "reject"
- "revise"
- "select_option"
- "provide_value"
- "schedule_propose_times"
- "schedule_confirm"
- "unsubscribe"
- "info_request"
- "stop"

PARAMETERS by action_type (all strings unless noted):
- approve:       { "reason": (optional, ≤120 chars) }
- reject:        { "reason": (required if present in text, ≤200 chars) }
- revise:        { "changes": [string,...] } // list concrete requested changes
- select_option: { "option_id": string | "label": string } // prefer option_id if visible in thread
- provide_value: { "key": string, "value": string } // e.g. "budget":"under 500 EUR"
- schedule_propose_times: { "duration_min": number, "timezone": string, "window_start": ISO8601?, "window_end": ISO8601?, "constraints": string? }
- schedule_confirm:       { "selected_start": ISO8601, "duration_min": number, "timezone": string }
- unsubscribe:   { "scope": "thread"|"account"|"all" } // thread = this conversation only
- info_request:  { "question": string }
- stop:          { "reason": string? }

SCORING:
- confidence in [0,1]; be conservative.
- If insufficient info: choose the closest action and set needs_clarification true with a short prompt.

INPUT:
- locale: :detected_locale
- thread_summary: :thread_summary
- clean_reply: :clean_reply
- attachments_excerpt: :attachments_excerpt  // may be empty
- recent_memories: :recent_memories          // relevant subset

OUTPUT JSON SCHEMA:
{
  "action_type": "approve|reject|revise|select_option|provide_value|schedule_propose_times|schedule_confirm|unsubscribe|info_request|stop",
  "parameters": { ... },
  "scope_hint": "conversation|user|account|null",
  "confidence": 0.0-1.0,
  "needs_clarification": true|false,
  "clarification_prompt": "string or null"
}',
    ],

    'clarify_question' => [
        'temperature' => 0.3,
        'backstory' => 'You write one concise clarification question matching the user\'s language.',
        'template' => 'Write ONE short question to disambiguate the action below. Be specific, ≤140 chars, match locale.

locale: :detected_locale
candidate_action: :action_json
clean_reply: :clean_reply

Return JSON:
{ "question": "string (≤140 chars)" }',
    ],

    'options_email_draft' => [
        'temperature' => 0.4,
        'backstory' => 'You draft a brief options email in the user\'s language.',
        'template' => 'Write a brief email offering 2–4 likely actions with friendly tone. Use locale.
Insert the provided placeholder tokens as-is for signed links.

locale: :detected_locale
subject_base: :base_subject
suggested_options: [
  { "label": "Approve", "token": "{{LINK_APPROVE}}" },
  { "label": "Reject",  "token": "{{LINK_REJECT}}"  },
  { "label": "Revise",  "token": "{{LINK_REVISE}}"  }
]

Return JSON:
{
  "subject": "string (≤80 chars)",
  "text": "plain text body (≤600 chars)",
  "html": "basic HTML body (p, ul/li, a) (≤800 chars)"
}',
    ],

    'memory_extract' => [
        'temperature' => 0.2,
        'backstory' => 'Extract non-sensitive, useful facts as key-value memories.',
        'template' => 'Extract relevant, non-sensitive facts. Decide scope and ttl_category. JSON only.

ALLOWED:
- scope: "conversation"|"user"|"account"
- ttl_category: "volatile"|"seasonal"|"durable"|"legal"
- confidence: [0,1]

Reject PII/sensitive data (health, politics, etc).

INPUT:
locale: :detected_locale
clean_reply: :clean_reply
thread_summary: :thread_summary
attachments_excerpt: :attachments_excerpt

OUTPUT:
{ "items": [
  { "key":"string_snake_case", "value":any, "scope":"conversation|user|account",
    "ttl_category":"volatile|seasonal|durable|legal", "confidence":0.0-1.0, "provenance":"email_message_id:<id>" }
]}',
    ],

    'thread_summarize' => [
        'temperature' => 0.3,
        'backstory' => 'Summarize a thread for fast recall.',
        'template' => 'Summarize the thread concisely in locale. ≤120 words.

INPUT:
locale: :detected_locale
last_messages: :last_messages   // array of recent message snippets
key_memories: :key_memories     // small set

Return JSON:
{
  "summary": "string",
  "key_entities": ["strings..."],
  "open_questions": ["strings..."]
}',
    ],

    'language_detect' => [
        'temperature' => 0.0,
        'backstory' => 'Return language code only.',
        'template' => 'Detect the primary language (BCP-47 like "nl" or "en-GB") of the given text.

text: :sample_text

Return JSON: { "language": "bcp47", "confidence": 0.0-1.0 }',
    ],

    'attachment_summarize' => [
        'temperature' => 0.3,
        'backstory' => 'Summarize attachment text for decision-making.',
        'template' => 'Summarize the attachment in locale. Be concise. No chain-of-thought.

INPUT:
locale: :detected_locale
filename: :filename
mime: :mime
text_excerpt: :text_excerpt   // truncated; may be partial

OUTPUT:
{
  "title": "short title (≤60 chars)",
  "gist": "≤120 words",
  "key_points": ["3-6 bullets"],
  "table_hint": { "has_tabular_data": true|false, "likely_headers": ["..."] }
}',
    ],

    'csv_schema_detect' => [
        'temperature' => 0.2,
        'backstory' => 'Infer simple CSV schema from a small sample.',
        'template' => 'Infer CSV schema from sample lines. Do NOT output data, only schema.

INPUT:
filename: :filename
sample_lines: :sample_lines

OUTPUT:
{
  "delimiter": ","|"|"|";"|"\t",
  "has_header": true|false,
  "columns": [
    {"name":"string","type":"string|number|date|datetime|boolean","nullable":true|false}
  ]
}',
    ],

    'clarify_email_draft' => [
        'temperature' => 0.4,
        'backstory' => 'Draft a short clarification email.',
        'template' => 'Draft a brief email asking exactly ONE clarification question (≤140 chars). Include both text and HTML.

locale: :detected_locale
question: :question

OUTPUT:
{ "subject": "string (≤80 chars)", "text": "string (≤400 chars)", "html": "string (≤600 chars)" }',
    ],

    'poll_email_draft' => [
        'temperature' => 0.4,
        'backstory' => 'Draft an availability poll email.',
        'template' => 'Draft a short availability poll email in locale with options list.
Use given placeholders as-is for signed links.

locale: :detected_locale
event_title: :event_title
options: [ { "label":"Tue 14:00", "token":"{{LINK_OPT_1}}" }, ... ]

OUTPUT:
{ "subject":"string (≤80 chars)", "text":"string (≤600 chars)", "html":"string (≤800 chars)" }',
    ],
];
