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

        $providers = $this->getProviderPriority();
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

        $providers = $requestedProvider ? [$requestedProvider] : $this->getProviderPriority();
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
                    // Prefer tool-calling for structured outputs when enabled for the prompt's role
                    $roleConfig = $this->config['routing']['roles'][$this->getRoleForPrompt($promptKey)] ?? [];
                    $useTools = (bool) ($roleConfig['tools'] ?? false);

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

                // Validate JSON response
                $json = json_decode($result, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON response: '.json_last_error_msg());
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

    private function getToolFunctionForPrompt(string $promptKey): array
    {
        switch ($promptKey) {
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
            default:
                return [$promptKey, ['type' => 'object']];
        }
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
