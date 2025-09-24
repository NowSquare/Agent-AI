<?php

namespace App\Mcp\Tools;

use App\Schemas\ThreadSummarizeSchema;
use App\Services\LlmClient;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ThreadSummarizeTool extends Tool
{
    protected string $description = 'Summarize a thread context into a concise JSON with summary, key_entities, and open_questions.';

    public function __construct(protected LlmClient $llmClient) {}

    public function handle(Request $request): Response
    {
        $locale = $request->string('locale', app()->getLocale());
        $lastMessages = $request->string('last_messages');
        $keyMemories = $request->string('key_memories', '');

        $result = $this->runReturningArray($locale, $lastMessages, $keyMemories);

        return Response::json($result);
    }

    public function schema(\Illuminate\JsonSchema\JsonSchema $schema): array
    {
        return [
            'locale' => $schema->string()->default(app()->getLocale()),
            'last_messages' => $schema->string()->description('Recent messages context')->required(),
            'key_memories' => $schema->string()->default(''),
        ];
    }

    public function runReturningArray(string $locale, string $lastMessages, string $keyMemories = ''): array
    {
        $result = $this->llmClient->json('thread_summarize', [
            'detected_locale' => $locale,
            'last_messages' => $lastMessages,
            'key_memories' => $keyMemories,
        ]);

        // Some models may return capitalized keys; normalize
        if (isset($result['Summary']) && ! isset($result['summary'])) {
            $result['summary'] = $result['Summary'];
        }
        if (isset($result['entities']) && ! isset($result['key_entities'])) {
            $result['key_entities'] = $result['entities'];
        }
        if (isset($result['questions']) && ! isset($result['open_questions'])) {
            $result['open_questions'] = $result['questions'];
        }

        $validator = Validator::make($result, ThreadSummarizeSchema::rules());
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $result;
    }
}
