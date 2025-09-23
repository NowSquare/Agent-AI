<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LLM Client with provider failover and Ollama fallback.
 *
 * Supports multiple providers with automatic fallback to local Ollama.
 * Handles timeouts, retries, token limits, and confidence calibration.
 */
class LlmClient
{
    public function __construct(
        private array $config = []
    ) {
        $this->config = $config ?: config('llm', []);
    }

    /**
     * Make a plain text completion request.
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
     * Make a JSON-mode completion request.
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
        $requestedModel    = $vars['model'] ?? null;

        $providers = $requestedProvider ? [$requestedProvider] : $this->getProviderPriority();
        $lastError = null;

        foreach ($providers as $provider) {
            try {
                Log::debug('Attempting LLM call', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
                    'input_tokens' => $this->estimateTokens($prompt),
                ]);

                $model = $requestedModel ?: $this->config['model'] ?? null;
                $result = $this->callProvider($provider, $prompt, $maxOutputTokens, $model);

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
                    'confidence' => $json['confidence'] ?? null,
                ]);

                return $json;

            } catch (\Throwable $e) {
                Log::warning('LLM provider failed', [
                    'provider' => $provider,
                    'prompt_key' => $promptKey,
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
            'last_error' => $lastError?->getMessage(),
        ]);

        throw new \RuntimeException('All LLM providers failed: '.$lastError?->getMessage());
    }

    /**
     * Build prompt from template with variable substitution.
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
     * Get provider priority list (external first, Ollama last).
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
     * Call specific LLM provider.
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
     * Call OpenAI API.
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
     * Call Anthropic API.
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
     * Call Ollama API (local).
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
     * Rough token estimation (words / 0.75).
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(str_word_count($text) / 0.75);
    }
}
