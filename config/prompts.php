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
        'backstory' => 'You analyze user emails and determine the appropriate action.',
        'template' => 'Analyze this user email and determine what action they want. Answer with just the action type from the list below.

User message: :clean_reply
Context: :thread_summary

Available actions:
- info_request (asking for information/recipes/help)
- approve (approving something)
- reject (declining something)
- revise (wanting to change something)
- stop (wanting to end the conversation)

What is the main action they want? Answer with just one word from the list above:',
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
        'template' => 'What is the primary language of this text? Answer with just the language code (like "en", "nl", "fr", "de").

Text: :sample_text

Language code:',
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
  "delimiter": ","|"|";"|"\t",
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

    'agent_response' => [
        'temperature' => 0.7,
        'backstory' => 'Generate a response from a specialized agent with specific role and expertise.',
        'template' => ':prompt

IMPORTANT: Your response must be valid JSON. Do not include any text before or after the JSON.

{
  "response": "Your detailed response here, based on your expertise and role",
  "confidence": 0.8,
  "reasoning": "Brief explanation of your approach (optional)"
}',
    ],

    'clarification_subject' => [
        'temperature' => 0.3,
        'backstory' => 'Generate a clear, concise subject line for clarification emails.',
        'template' => 'Create a subject line for an email asking the user to confirm their request. Keep it under 60 characters.

Request type: :action_type
Summary: :action_summary

Subject line:',
    ],

    'clarification_body_intro' => [
        'temperature' => 0.4,
        'backstory' => 'Generate a brief, professional introduction for clarification emails.',
        'template' => 'Write a 2-3 sentence introduction for an email asking the user to confirm their interpreted request.

Action: :action_type
Summary: :action_summary
Context: :thread_context

Introduction text:',
    ],

    'options_subject' => [
        'temperature' => 0.3,
        'backstory' => 'Generate a clear subject line for options/clarification emails.',
        'template' => 'Create a subject line for an email offering the user multiple options to clarify their request. Keep it under 60 characters.

Context: We interpreted their request but need clarification.

Subject line:',
    ],

    'options_body_intro' => [
        'temperature' => 0.4,
        'backstory' => 'Generate a brief, helpful introduction for options emails.',
        'template' => 'Write a 2-3 sentence introduction explaining that we need clarification and offering options.

Original request: :original_question
Options available: :available_options

Introduction text:',
    ],

    'define_agents' => [
        'temperature' => 0.4,
        'backstory' => 'Break down user requests into specialized agents with clear, actionable tasks.',
        'template' => ':prompt',
    ],

    'respond_to_user' => [
        'temperature' => 0.5,
        'backstory' => 'Present proposed agents and tasks to user for confirmation.',
        'template' => '# :goal

You are processing a response to the user\'s query:
**Subject:** ":conversation_subject"
**Message:** ":conversation_plaintext_content"

**Goal:**
:goal

**Defined Agents:**
:defined_agents

**Instructions:**
- Verify the interpreted goal aligns with the user\'s request and restate it succinctly.
- Summarize the defined agents and their tasks in a user-friendly list.
- Use the user\'s language and tone for consistency.
- Prompt the user to confirm or suggest changes with a direct question.
- Format using Markdown for readability.

**Example Output:**
# Plan Your Weekend Event
I understood your goal as organizing a fun weekend event. Here\'s the plan:
- **TimeAgent**: Gets the current date and time.
- **CalendarAgent**: Finds free slots this weekend.
- **EventAgent**: Suggests activities under $50.
Can we continue with this, or do you want to change anything?',
    ],

    'handle_response' => [
        'temperature' => 0.4,
        'backstory' => 'Classify whether user confirms proceeding or requests changes.',
        'template' => '### Classify User Response

**Instructions:**
- Analyze the user\'s message to determine intent.
- Return **"YES"** if the user explicitly agrees or uses positive phrases.
- Return **"NO"** if the user requests changes or expresses uncertainty.
- Ignore unrelated content and focus on confirmation intent.

**User Message:**
":conversation_plaintext_content"

**Output:**
"YES" or "NO"',
    ],

    // New: Incident/problem email draft
    'incident_email_draft' => [
        'temperature' => 0.3,
        'backstory' => 'Write a concise, polite problem notification in the user\'s language explaining issues with their email or attachments, including filenames and reasons.',
        'template' => 'Write a short email in :detected_locale explaining there was an issue processing their message.
If files are problematic, list each filename with a brief reason. Keep a friendly, helpful tone.
Include a clear next step (e.g., resend clean PDFs or share a safe link). No chain-of-thought.

Context:
- issue: :issue
- subject: :original_subject
- file_list (one per line, like "filename: reason"):
:file_list
- original_message (may be empty): :user_message

Return JSON with:
{
  "subject": "string (≤80 chars)",
  "text": "plain text body (≤600 chars)",
  "html": "basic HTML (≤800 chars)"
}',
    ],
];
