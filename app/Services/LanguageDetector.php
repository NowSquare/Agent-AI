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
        $this->detector = new Language();
        $this->llmClient = $llmClient;
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
            $result = $this->detector->detect($text)->close();
            
            if (!empty($result)) {
                $lang = array_key_first($result);
                $confidence = $result[$lang];
                
                if ($confidence > 0.8) {
                    $locale = $this->mapToLocale($lang);
                    Cache::put($cacheKey, $locale, now()->addHours(24));
                    return $locale;
                }
            }

            // If library fails or low confidence, try LLM
            if ($useLlmFallback) {
                return $this->detectWithLlm($text);
            }

        } catch (\Throwable $e) {
            Log::warning('Language detection failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            if ($useLlmFallback) {
                return $this->detectWithLlm($text);
            }
        }

        return config('app.fallback_locale', 'en_US');
    }

    /**
     * Detect language using LLM as fallback.
     */
    private function detectWithLlm(string $text): string
    {
        try {
            $result = $this->llmClient->json('language_detect', [
                'sample_text' => substr($text, 0, 200), // Limit sample size
            ]);

            if (isset($result['language']) && isset($result['confidence'])) {
                $locale = $this->mapToLocale($result['language']);
                
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
        // Normalize to lowercase
        $lang = strtolower($lang);

        // Direct mappings for supported locales
        $localeMap = [
            'en' => 'en_US',
            'nl' => 'nl_NL',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'it' => 'it_IT',
            'es' => 'es_ES',
        ];

        // Handle both ISO codes and already mapped locales
        if (isset($localeMap[$lang])) {
            return $localeMap[$lang];
        }

        // If it's already a valid locale format, verify and return
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang)) {
            $parts = explode('_', $lang);
            if (isset($localeMap[$parts[0]])) {
                return $lang;
            }
        }

        // Default to configured fallback
        return config('app.fallback_locale', 'en_US');
    }
}
