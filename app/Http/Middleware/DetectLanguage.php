<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\LanguageDetector;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class DetectLanguage
{
    private LanguageDetector $detector;

    public function __construct(LanguageDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);
        App::setLocale($locale);

        // Add Content-Language header to response
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        // Update session if available
        try {
            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
                $request->session()->save();
            }
        } catch (\RuntimeException $e) {
            // Session not available, ignore
        }

        return $response;
    }

    /**
     * Determine the locale to use for this request.
     */
    private function determineLocale(Request $request): string
    {
        // Get detection priority from config
        $priority = config('language.detection_priority', ['url', 'session', 'header', 'content']);

        foreach ($priority as $source) {
            $locale = match ($source) {
                'url' => $this->detectFromUrl($request),
                'session' => $this->detectFromSession($request),
                'header' => $this->detectFromHeader($request),
                'content' => $this->detectFromContent($request),
                default => null,
            };

            if ($locale) {
                return $locale;
            }
        }

        // Fallback to default
        return config('app.fallback_locale', 'en_US');
    }

    private function detectFromUrl(Request $request): ?string
    {
        if ($request->has('lang')) {
            return $this->validateLocale($request->get('lang'));
        }
        return null;
    }

    private function detectFromSession(Request $request): ?string
    {
        try {
            if ($request->hasSession() && $request->session()->has('locale')) {
                return $this->validateLocale($request->session()->get('locale'));
            }
        } catch (\RuntimeException $e) {
            // Session not available
        }
        return null;
    }

    private function detectFromHeader(Request $request): ?string
    {
        $acceptLanguage = $request->getPreferredLanguage();
        if ($acceptLanguage) {
            return $this->validateLocale($acceptLanguage);
        }
        return null;
    }

    private function detectFromContent(Request $request): ?string
    {
        if (!$request->isJson() && !$request->has('text')) {
            return null;
        }

        $text = $request->input('text');
        if (empty($text) && $request->isJson()) {
            $content = json_decode($request->getContent(), true);
            $text = $content['text'] ?? null;
        }

        if (empty($text)) {
            return null;
        }

        return $this->detector->detect($text);
    }

    /**
     * Validate and normalize locale string.
     */
    private function validateLocale(?string $locale): ?string
    {
        if (empty($locale)) {
            return null;
        }

        // Normalize to lowercase and replace hyphens/spaces with underscores
        $locale = str_replace(['-', ' '], '_', strtolower($locale));

        // Get supported locales from config
        $supported = config('language.supported_locales', []);

        return $supported[$locale] ?? null;
    }
}
