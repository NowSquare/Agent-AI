<?php

namespace App\Mcp\Tools;

use App\Schemas\MemoryExtractSchema;
use App\Services\LlmClient;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class MemoryExtractTool extends Tool
{
    protected string $description = 'Extract non-sensitive key-value memories with scope/ttl/confidence from text, returning strict JSON.';

    public function __construct(protected LlmClient $llmClient) {}

    public function handle(Request $request): Response
    {
        $locale = $request->string('locale', app()->getLocale());
        $cleanReply = $request->string('clean_reply');
        $threadSummary = $request->string('thread_summary', '');
        $attachmentsExcerpt = $request->string('attachments_excerpt', '');

        $result = $this->runReturningArray($locale, $cleanReply, $threadSummary, $attachmentsExcerpt);

        return Response::json($result);
    }

    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'locale' => $schema->string()->default(app()->getLocale()),
            'clean_reply' => $schema->string()->required(),
            'thread_summary' => $schema->string()->default(''),
            'attachments_excerpt' => $schema->string()->default(''),
        ];
    }

    public function runReturningArray(string $locale, string $cleanReply, string $threadSummary = '', string $attachmentsExcerpt = ''): array
    {
        $result = $this->llmClient->json('memory_extract', [
            'detected_locale' => $locale,
            'clean_reply' => $cleanReply,
            'thread_summary' => $threadSummary,
            'attachments_excerpt' => $attachmentsExcerpt,
        ]);

        $validator = Validator::make($result, MemoryExtractSchema::rules());
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $result;
    }
}
