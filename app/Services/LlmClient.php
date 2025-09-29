<?php

/**
 * What this file does — One client for talking to different LLM providers.
 * Plain: A single gateway that sends prompts to AI models (local or cloud) and returns answers.
 * How this fits in:
 * - Called by services to generate text or JSON
 * - Follows routing defaults from config/llm.php
 * - Falls back to local Ollama when externals fail
 * Key terms defined here:
 * - Provider: which AI backend to call (ollama/openai/anthropic)
 * - Prompt template: a text with placeholders (in config/prompts.php)
 * - Calibration: small multiplier to normalize confidence across providers
 *
 * For engineers:
 * - Inputs/Outputs: call()/json() take a prompt key + variables; return string/array
 * - Side effects: logs, HTTP requests; no DB writes
 * - Failure modes: timeout, HTTP error, invalid JSON → throws RuntimeException
 */

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LLM Client with provider failover and Ollama fallback.
 *
 * Supports multiple providers with automatic fallback to local Ollama.
 * Handles timeouts, retries, token limits, and confidence calibration.
 */
/**
 * Purpose: Provide a simple, provider-agnostic API for text and JSON completions.
 * Responsibilities:
 * - Build prompts from templates
 * - Select providers and fallback on failure
 * - Enforce timeouts/retries and normalize confidence
 * Collaborators:
 * - Grounding/Router services decide when/how to call LLM
 * - config/llm.php defines models and thresholds
 */
class LlmClient
{
    public function __construct(
        private array $config = []
    ) {
        $this->config = $config ?: config('llm', []);
    }

    /**
     * Summary: Make a plain text completion request.
     *
     * @param  string  $promptKey  Name of prompt template in config/prompts.php
     * @param  array  $vars  Values to substitute into the template
     * @param  int|null  $maxOutputTokens  Hard cap for output length
     * @return string Model response as plain text
     *
     * @throws \RuntimeException If all providers fail or HTTP/timeout issues occur
     *                           Example:
     *                           $text = $llm->call('thread_summarize', ['last_messages' => '...']);
     */
    public function call(
        string $promptKey,
        array $vars = [],
        ?int $maxOutputTokens = null
    ): string {
        $prompt = $this->buildPrompt($promptKey, $vars);
        $maxOutputTokens = $maxOutputTokens ?: $this->config['caps']['output_tokens'];

        $providers = $this->getRolePreferredProviders($promptKey);
        $lastError = null;

        foreach ($providers as $provider) {
            try {
                Log::debug('Attempting LLM call', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
                    'input_tokens' => $this->estimateTokens($prompt),
                ]);

                $result = $this->callProvider($provider, $prompt, $maxOutputTokens);

                Log::debug('LLM call successful', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
                    'output_length' => strlen($result),
                ]);

                return $result;

            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('LLM provider failed', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        throw new \RuntimeException(
            "All LLM providers failed: {$lastError?->getMessage()}",
            0,
            $lastError
        );
    }

    /**
     * Summary: Make a JSON-mode completion request and validate/adjust confidence.
     *
     * @param  string  $promptKey  Name of JSON prompt template
     * @param  array  $vars  Values to substitute (can include provider/model override)
     * @param  int|null  $maxOutputTokens  Output token limit
     * @return array Decoded JSON array from the model
     *
     * @throws \RuntimeException On invalid JSON or provider failures
     *                           Example:
     *                           $out = $llm->json('action_interpret', ['clean_reply' => '...']);
     */
    public function json(
        string $promptKey,
        array $vars = [],
        ?int $maxOutputTokens = null
    ): array {
        $prompt = $this->buildPrompt($promptKey, $vars);
        $maxOutputTokens = $maxOutputTokens ?: $this->config['caps']['output_tokens'];

        // Allow per-call provider/model override (for routing)
        $requestedProvider = $vars['provider'] ?? null;
        $requestedModel = $vars['model'] ?? null;

        $providers = $requestedProvider ? [$requestedProvider] : $this->getRolePreferredProviders($promptKey);
        $lastError = null;

        $lastModel = null;
        foreach ($providers as $provider) {
            try {
                $model = $requestedModel ?: $this->selectModelForJsonPrompt($promptKey);
                $lastModel = $model;

                Log::debug('Attempting LLM call', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
                    'model' => $model,
                    'input_tokens' => $this->estimateTokens($prompt),
                ]);

                // For Ollama, use chat API with strict JSON mode and no streaming
                if ($provider === 'ollama') {
                    // Prefer tool-calling for prompts with dedicated schemas (including agent_response)
                    $roleConfig = $this->config['routing']['roles'][$this->getRoleForPrompt($promptKey)] ?? [];
                    $useTools = $this->hasToolForPrompt($promptKey);

                    $result = $this->callOllamaChatJson($prompt, $maxOutputTokens, $model, $promptKey, $useTools);
                } else {
                    $result = $this->callProvider($provider, $prompt, $maxOutputTokens, $model);
                }

                // Log raw response for debugging before JSON parse
                Log::debug('LLM raw response', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
                    'model' => $model,
                    'raw_preview' => mb_substr($result, 0, 400),
                ]);

                // Validate JSON response (with salvage for agent_response)
                $json = json_decode($result, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    if ($promptKey === 'agent_response') {
                        // Salvage non-JSON content as a valid response to avoid apologetic fallbacks
                        $json = [
                            'response' => is_string($result) ? trim($result) : '',
                        ];
                        Log::warning('LLM JSON parse failed; salvaged raw content for agent_response', [
                            'provider' => $provider,
                            'model' => $model,
                            'error' => json_last_error_msg(),
                        ]);
                    } else {
                        throw new \RuntimeException('Invalid JSON response: '.json_last_error_msg());
                    }
                }

                // Apply confidence calibration
                if (isset($json['confidence'])) {
                    $json['confidence'] *= $this->config['calibration'][$provider] ?? 1.0;
                    $json['confidence'] = min(1.0, max(0.0, $json['confidence']));
                }

                Log::info('LLM call successful', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
                    'model' => $model,
                    'confidence' => $json['confidence'] ?? null,
                ]);

                return $json;

            } catch (\Throwable $e) {
                Log::warning('LLM provider failed', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
                    'model' => $lastModel,
                    'error' => $e->getMessage(),
                ]);
                $lastError = $e;

                // Continue to next provider
                continue;
            }
        }

        // All providers failed
        Log::error('All LLM providers failed', [
            'prompt_key' => $promptKey,
            'providers_attempted' => $providers,
            'last_model' => $lastModel,
            'last_error' => $lastError?->getMessage(),
        ]);

        throw new \RuntimeException('All LLM providers failed: '.$lastError?->getMessage());
    }

    /**
     * Summary: Choose an appropriate model for a JSON prompt based on routing roles.
     */
    private function selectModelForJsonPrompt(string $promptKey): ?string
    {
        $roles = $this->config['routing']['roles'] ?? [];

        // Default fallbacks if roles are missing
        $classify = $roles['CLASSIFY']['model'] ?? ($this->config['model'] ?? null);
        $grounded = $roles['GROUNDED']['model'] ?? ($this->config['model'] ?? null);
        $synth = $roles['SYNTH']['model'] ?? ($this->config['model'] ?? null);

        return match ($promptKey) {
            'language_detect', 'thread_summarize', 'memory_extract' => $grounded,
            'action_interpret' => $classify,
            default => $synth,
        };
    }

    /**
     * Summary: Build a prompt from a named template by replacing :placeholders.
     *
     * @param  string  $key  Template key under config/prompts.php
     * @param  array  $vars  Replacement vars (':name' → value)
     * @return string The final prompt string
     *
     * @throws \InvalidArgumentException If template is missing
     */
    private function buildPrompt(string $key, array $vars): string
    {
        $template = config("prompts.{$key}.template");

        if (! $template) {
            throw new \InvalidArgumentException("Prompt template '{$key}' not found");
        }

        // Simple variable substitution
        foreach ($vars as $var => $value) {
            $template = str_replace(":{$var}", $value, $template);
        }

        return $template;
    }

    /**
     * Summary: Get provider priority list. Defaults to configured provider then Ollama fallback.
     *
     * @return array<string> Provider slugs in order
     */
    private function getProviderPriority(): array
    {
        // Default to local-first provider if not explicitly configured at root
        $provider = $this->config['provider'] ?? 'ollama';
        $providers = [$provider];

        // Add Ollama as fallback if not already primary
        if ($provider !== 'ollama') {
            $providers[] = 'ollama';
        }

        return $providers;
    }

    /**
     * Prefer the role's configured provider first, then fall back to global order.
     */
    private function getRolePreferredProviders(string $promptKey): array
    {
        $roles = $this->config['routing']['roles'] ?? [];
        $role = $this->getRoleForPrompt($promptKey);
        $preferred = $roles[$role]['provider'] ?? null;

        $list = $this->getProviderPriority();
        if (is_string($preferred) && $preferred !== '') {
            // Move preferred to the front, keep unique order
            $list = array_values(array_unique(array_merge([$preferred], $list)));
        }
        return $list;
    }

    /**
     * Summary: Dispatch to a specific provider implementation.
     *
     * @param  string  $provider  'openai'|'anthropic'|'ollama'
     * @param  string  $prompt  Full prompt text
     * @param  int  $maxTokens  Output token limit
     * @param  string|null  $model  Preferred model name
     * @return string Raw response text
     */
    private function callProvider(string $provider, string $prompt, int $maxTokens, ?string $model = null): string
    {
        return match ($provider) {
            'openai' => $this->callOpenAI($prompt, $maxTokens, $model),
            'anthropic' => $this->callAnthropic($prompt, $maxTokens, $model),
            'ollama' => $this->callOllama($prompt, $maxTokens, $model),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }

    /**
     * Summary: Call OpenAI chat completions.
     */
    private function callOpenAI(string $prompt, int $maxTokens, ?string $model): string
    {
        $response = Http::timeout($this->config['timeout_ms'] / 1000)
            ->withToken(config('llm.providers.openai.api_key'))
            ->post(rtrim(config('llm.providers.openai.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', [
                'model' => $model ?: $this->config['model'],
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $maxTokens,
                'temperature' => config('prompts.action_interpret.temperature', 0.2),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI API error: {$response->status()} {$response->body()}");
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Summary: Call Anthropic messages API.
     */
    private function callAnthropic(string $prompt, int $maxTokens, ?string $model): string
    {
        $response = Http::timeout($this->config['timeout_ms'] / 1000)
            ->withToken(config('llm.providers.anthropic.api_key'))
            ->post(rtrim(config('llm.providers.anthropic.base_url', 'https://api.anthropic.com'), '/').'/v1/messages', [
                'model' => $model ?: $this->config['model'],
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Anthropic API error: {$response->status()} {$response->body()}");
        }

        $data = $response->json();

        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Summary: Call Ollama local API.
     */
    private function callOllama(string $prompt, int $maxTokens, ?string $model): string
    {
        $base = rtrim(config('llm.providers.ollama.base_url', 'http://localhost:11434'), '/');
        $response = Http::timeout($this->config['timeout_ms'] / 1000)
            ->post($base.'/api/generate', [
                'model' => $model ?: 'llama3.1:8b',
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'num_predict' => $maxTokens,
                    'temperature' => config('prompts.action_interpret.temperature', 0.2),
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama API error: {$response->status()} {$response->body()}");
        }

        $data = $response->json();

        return $data['response'] ?? '';
    }

    /**
     * Summary: Call Ollama chat API with strict JSON mode for JSON prompts.
     */
    private function callOllamaChatJson(string $prompt, int $maxTokens, ?string $model, string $promptKey, bool $useTools = false): string
    {
        $base = rtrim(config('llm.providers.ollama.base_url', 'http://localhost:11434'), '/');
        $body = [
            'model' => $model ?: 'llama3.1:8b',
            'stream' => false,
            'messages' => [],
            'options' => [
                'num_predict' => $maxTokens,
                'temperature' => 0,
                'top_p' => 1,
            ],
        ];

        if ($useTools) {
            [$toolName, $parameters] = $this->getToolFunctionForPrompt($promptKey);
            $body['tools'] = [[
                'type' => 'function',
                'function' => [
                    'name' => $toolName,
                    'description' => 'Return structured JSON via function arguments for this task.',
                    'parameters' => $parameters,
                ],
            ]];
            $body['messages'] = [
                ['role' => 'system', 'content' => "If appropriate, call the {$toolName} function exactly once with the correct arguments. Do not include extra text."],
                ['role' => 'user',   'content' => $prompt],
            ];
        } else {
            $body['format'] = 'json';
            $body['messages'] = [
                ['role' => 'system', 'content' => 'Respond ONLY with a single valid JSON object. No prose, no code fences.'],
                ['role' => 'user',   'content' => $prompt],
            ];
        }

        $response = Http::timeout($this->config['timeout_ms'] / 1000)
            ->post($base.'/api/chat', $body);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama API error: {$response->status()} {$response->body()}");
        }

        $data = $response->json();

        if ($useTools && isset($data['message']['tool_calls']) && ! empty($data['message']['tool_calls'])) {
            $call = $data['message']['tool_calls'][0];
            $args = $call['function']['arguments'] ?? [];

            return is_string($args) ? $args : json_encode($args);
        }

        return $data['message']['content'] ?? '';
    }

    /**
     * What this section does — Define function schemas for model-side tool-calling.
     * Plain: For prompts that must return structured JSON, expose a single function schema; the model returns the
     *         function arguments as the JSON payload, avoiding free-form prose.
     * How this fits in:
     * - Prevents invalid JSON by letting the model fill a schema instead of following natural-language instructions
     * - Central place to add/adjust schemas when prompts evolve
     * - Tied to hasToolForPrompt() to enable tool-calling on supported prompts
     * Key terms: tool-calling, function schema, prompt key, structured JSON
     *
     * For engineers:
     * - Add a case per prompt key that returns [toolName, jsonSchema]
     * - Include required fields and brief descriptions; avoid over-constraining optional fields
     * - When adding a new JSON prompt: add a schema here and whitelist the key in hasToolForPrompt()
     */
    private function getToolFunctionForPrompt(string $promptKey): array
    {
        switch ($promptKey) {
            case 'action_interpret':
                return ['action_interpret', [
                    'type' => 'object',
                    'properties' => [
                        'action_type' => [
                            'type' => 'string',
                            'enum' => [
                                'approve', 'reject', 'revise', 'select_option', 'provide_value',
                                'schedule_propose_times', 'schedule_confirm', 'unsubscribe', 'info_request', 'stop',
                            ],
                        ],
                        'parameters' => ['type' => 'object'],
                        'scope_hint' => ['type' => ['string', 'null'], 'enum' => ['conversation', 'user', 'account', null]],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'needs_clarification' => ['type' => 'boolean'],
                        'clarification_prompt' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['action_type', 'parameters', 'confidence', 'needs_clarification'],
                ]];
            case 'language_detect':
                return ['language_detect', [
                    'type' => 'object',
                    'properties' => [
                        'language' => ['type' => 'string', 'description' => 'BCP-47 code or language name'],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    ],
                    'required' => ['language'],
                ]];
            case 'thread_summarize':
                return ['thread_summarize', [
                    'type' => 'object',
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'key_entities' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'open_questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['summary'],
                ]];
            case 'memory_extract':
                return ['memory_extract', [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array', 'items' => [
                            'type' => 'object',
                            'properties' => [
                                'key' => ['type' => 'string'],
                                'value' => ['type' => ['string', 'number', 'boolean', 'object', 'array', 'null']],
                                'scope' => ['type' => 'string', 'enum' => ['conversation', 'user', 'account']],
                                'ttl_category' => ['type' => 'string', 'enum' => ['volatile', 'seasonal', 'durable', 'legal']],
                                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                                'provenance' => ['type' => 'string'],
                            ],
                            'required' => ['key', 'scope', 'ttl_category'],
                        ]],
                    ],
                    'required' => ['items'],
                ]];
            case 'incident_email_draft':
                return ['incident_email_draft', [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Email subject line (≤80 chars)'],
                        'text' => ['type' => 'string', 'description' => 'Plain text body (≤600 chars)'],
                        'html' => ['type' => 'string', 'description' => 'Basic HTML body (≤800 chars)'],
                    ],
                    'required' => ['subject', 'text', 'html'],
                ]];
            case 'clarify_email_draft':
                return ['clarify_email_draft', [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Email subject line (≤80 chars)'],
                        'text' => ['type' => 'string', 'description' => 'Plain text body (≤400 chars)'],
                        'html' => ['type' => 'string', 'description' => 'Basic HTML body (≤600 chars)'],
                    ],
                    'required' => ['subject', 'text', 'html'],
                ]];
            case 'options_email_draft':
                return ['options_email_draft', [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Email subject line (≤80 chars)'],
                        'text' => ['type' => 'string', 'description' => 'Plain text body (≤600 chars)'],
                        'html' => ['type' => 'string', 'description' => 'Basic HTML body (≤800 chars)'],
                    ],
                    'required' => ['subject', 'text', 'html'],
                ]];
            case 'poll_email_draft':
                return ['poll_email_draft', [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'description' => 'Email subject line (≤80 chars)'],
                        'text' => ['type' => 'string', 'description' => 'Plain text body (≤600 chars)'],
                        'html' => ['type' => 'string', 'description' => 'Basic HTML body (≤800 chars)'],
                    ],
                    'required' => ['subject', 'text', 'html'],
                ]];
            case 'csv_schema_detect':
                return ['csv_schema_detect', [
                    'type' => 'object',
                    'properties' => [
                        'delimiter' => ['type' => 'string', 'enum' => [',', '|', ';', "\t"]],
                        'has_header' => ['type' => 'boolean'],
                        'columns' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'type' => ['type' => 'string', 'enum' => ['string', 'number', 'date', 'datetime', 'boolean']],
                                    'nullable' => ['type' => 'boolean'],
                                ],
                                'required' => ['name', 'type', 'nullable'],
                            ],
                        ],
                    ],
                    'required' => ['delimiter', 'has_header', 'columns'],
                ]];
            case 'clarify_question':
                return ['clarify_question', [
                    'type' => 'object',
                    'properties' => [
                        'question' => ['type' => 'string', 'description' => 'A single clarification question (≤140 chars)'],
                    ],
                    'required' => ['question'],
                ]];
            case 'attachment_summarize':
                return ['attachment_summarize', [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Short title (≤60 chars)'],
                        'gist' => ['type' => 'string', 'description' => '≤120 words summary'],
                        'key_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'table_hint' => [
                            'type' => 'object',
                            'properties' => [
                                'has_tabular_data' => ['type' => 'boolean'],
                                'likely_headers' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ],
                        ],
                    ],
                    'required' => ['title', 'gist', 'key_points'],
                ]];
            case 'agent_response':
                return ['agent_response', [
                    'type' => 'object',
                    'properties' => [
                        'response' => ['type' => 'string', 'description' => 'Final assistant response text (Markdown allowed)'],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    ],
                    'required' => ['response'],
                ]];
            default:
                return [$promptKey, ['type' => 'object']];
        }
    }

    /**
     * Summary: Determine if a prompt key has a dedicated tool schema.
     */
    private function hasToolForPrompt(string $promptKey): bool
    {
        return in_array($promptKey, [
            'action_interpret',
            'language_detect',
            'thread_summarize',
            'memory_extract',
            'incident_email_draft',
            'clarify_email_draft',
            'options_email_draft',
            'poll_email_draft',
            'csv_schema_detect',
            'clarify_question',
            'attachment_summarize',
            'agent_response',
        ], true);
    }

    private function getRoleForPrompt(string $promptKey): string
    {
        return match ($promptKey) {
            'language_detect', 'thread_summarize', 'memory_extract' => 'GROUNDED',
            'action_interpret' => 'CLASSIFY',
            default => 'SYNTH',
        };
    }

    /**
     * Summary: Rough token estimation as words / 0.75.
     *
     * @param  string  $text  Text to estimate
     * @return int Approximate token count
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(str_word_count($text) / 0.75);
    }
}
