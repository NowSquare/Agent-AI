<?php

namespace App\Mcp\Tools;

use App\Schemas\LanguageDetectSchema;
use App\Services\LlmClient;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class LanguageDetectTool extends Tool
{
    protected string $description = 'Detect the primary language (BCP-47) of a short text with confidence, returning strict JSON.';

    public function __construct(protected LlmClient $llmClient) {}

    public function handle(Request $request): Response
    {
        $text = $request->string('sample_text');

        $result = $this->runReturningArray(substr($text, 0, 500));

        return Response::json($result);
    }

    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'sample_text' => $schema->string()
                ->description('Input text to detect language for (<= 500 chars)')
                ->required(),
        ];
    }

    /**
     * Programmatic entrypoint returning validated array (no MCP Response wrapper).
     */
    public function runReturningArray(string $sampleText): array
    {
        $result = $this->llmClient->json('language_detect', [
            'sample_text' => $sampleText,
        ]);

        // Normalize common variants from different models
        if (! isset($result['language'])) {
            if (isset($result['lang'])) {
                $result['language'] = $result['lang'];
                unset($result['lang']);
            } elseif (isset($result['code'])) {
                $result['language'] = $result['code'];
            } elseif (isset($result['language_code'])) {
                $result['language'] = $result['language_code'];
            }
        }

        // Map friendly names like "English" or "French" to locale codes
        if (isset($result['language'])) {
            $langRaw = (string) $result['language'];
            $nameToCode = [
                'english' => 'en', 'french' => 'fr', 'german' => 'de', 'spanish' => 'es', 'italian' => 'it',
                'dutch' => 'nl', 'portuguese' => 'pt', 'russian' => 'ru', 'chinese' => 'zh', 'japanese' => 'ja',
                'korean' => 'ko', 'arabic' => 'ar', 'hindi' => 'hi', 'turkish' => 'tr', 'polish' => 'pl',
            ];
            $lower = strtolower(trim($langRaw));
            if (isset($nameToCode[$lower])) {
                $result['language'] = $nameToCode[$lower];
            }
        }

        // Default confidence if missing
        if (! isset($result['confidence'])) {
            $result['confidence'] = 0.9;
        }

        // Map string confidence levels (from reasoning models) to numeric
        if (isset($result['confidence']) && is_string($result['confidence'])) {
            $map = ['low' => 0.3, 'medium' => 0.6, 'high' => 0.9];
            $lc = strtolower(trim($result['confidence']));
            $result['confidence'] = $map[$lc] ?? 0.9;
        }

        // Clamp numeric confidence into [0,1]
        if (isset($result['confidence']) && is_numeric($result['confidence'])) {
            $result['confidence'] = max(0.0, min(1.0, (float) $result['confidence']));
        }

        // Fallback: if still no language, derive from library mapping of sample text
        if (! isset($result['language']) || $result['language'] === '' || $result['language'] === null) {
            $mapped = app(\App\Services\LanguageDetector::class)->detect((string) $sampleText, useLlmFallback: false);
            $result['language'] = strtolower(explode('_', $mapped)[0]);
        }

        $validator = Validator::make($result, LanguageDetectSchema::rules());
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $result;
    }
}
