<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LanguageDetection\Language;

class LanguageDetector
{
    private Language $detector;

    private LlmClient $llmClient;

    public function __construct(LlmClient $llmClient)
    {
        $this->detector = new Language;
        $this->llmClient = $llmClient;
    }

    /**
     * Set the language detector instance (for testing).
     */
    public function setDetector(Language $detector): void
    {
        $this->detector = $detector;
    }

    /**
     * Detect language from text with library and LLM fallback.
     */
    public function detect(string $text, bool $useLlmFallback = true): string
    {
        if (empty($text)) {
            return config('app.fallback_locale', 'en_US');
        }

        // Try cache first
        $cacheKey = 'lang_detect:'.md5($text);
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            // Use language detection library first
            $result = $this->detector->detect($text);

            Log::debug('Language detection result', [
                'text' => $text,
                'result' => $result->close(),
                'detector_class' => get_class($this->detector),
            ]);

            $scores = (array) $result->close();
            if (! empty($scores)) {
                $lang = array_key_first($scores);
                $confidence = $scores[$lang];

                Log::debug('Language detection confidence', [
                    'lang' => $lang,
                    'confidence' => $confidence,
                    'text' => $text,
                    'result' => $scores,
                    'mapped_locale' => $this->mapToLocale($lang),
                ]);

                $minConfidence = config('language.detection.min_confidence', 0.8);
                if ($confidence > $minConfidence) {
                    $locale = $this->mapToLocale($lang);
                    $cacheTtl = config('language.detection.cache_ttl', 24);
                    Cache::put($cacheKey, $locale, now()->addHours($cacheTtl));

                    return $locale;
                }
            }

            // If library fails or low confidence, try LLM
            if ($useLlmFallback && config('language.detection.use_llm_fallback', true)) {
                return $this->detectWithMcp($text);
            }

        } catch (\Throwable $e) {
            Log::warning('Language detection failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            if ($useLlmFallback) {
                return $this->detectWithMcp($text);
            }
        }

        return config('app.fallback_locale', 'en_US');
    }

    /**
     * Detect language using LLM as fallback.
     */
    private function detectWithMcp(string $text): string
    {
        try {
            // Route via MCP tool for strict schema validation
            $result = app(\App\Mcp\Tools\LanguageDetectTool::class)
                ->runReturningArray(substr($text, 0, 200));

            Log::debug('LLM language detection result', [
                'text' => substr($text, 0, 200),
                'result' => $result,
            ]);

            if (isset($result['language']) && isset($result['confidence'])) {
                $locale = $this->mapToLocale($result['language']);

                Log::debug('LLM language detection confidence', [
                    'language' => $result['language'],
                    'confidence' => $result['confidence'],
                    'locale' => $locale,
                ]);

                if ($result['confidence'] > 0.8) {
                    Cache::put('lang_detect:'.md5($text), $locale, now()->addHours(24));

                    return $locale;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('LLM language detection failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return config('app.fallback_locale', 'en_US');
    }

    /**
     * Map ISO language code to locale.
     */
    private function mapToLocale(string $lang): string
    {
        // Normalize to lowercase and replace hyphens with underscores
        $lang = str_replace('-', '_', strtolower($lang));

        // Get supported locales from config
        $supported = config('language.supported_locales', []);

        // Handle both ISO codes and already mapped locales
        if (isset($supported[$lang])) {
            return $supported[$lang];
        }

        // If it's already a valid locale format, verify and return
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang)) {
            $parts = explode('_', $lang);
            if (isset($supported[$parts[0]])) {
                return $lang;
            }
        }

        // Default to configured fallback
        return config('app.fallback_locale', 'en_US');
    }
}
