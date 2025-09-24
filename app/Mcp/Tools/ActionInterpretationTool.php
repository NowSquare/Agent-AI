<?php

namespace App\Mcp\Tools;

use App\Services\LlmClient;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ActionInterpretationTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Analyzes user email content and determines the appropriate action type and parameters for automated processing.';

    /**
     * Create a new tool instance.
     */
    public function __construct(
        protected LlmClient $llmClient,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $cleanReply = $request->string('clean_reply');
        $threadSummary = $request->string('thread_summary', '');
        $attachmentsExcerpt = $request->string('attachments_excerpt', '');
        $recentMemories = $request->array('recent_memories', []);

        // Use the LLM to interpret the action
        $interpretation = $this->interpretAction($cleanReply, $threadSummary, $attachmentsExcerpt, $recentMemories);

        // Basic shape validation to ensure consistent contract
        $validator = Validator::make($interpretation, [
            'action_type' => ['required', 'string'],
            'parameters' => ['required', 'array'],
            'confidence' => ['required', 'numeric'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return Response::json($interpretation);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'clean_reply' => $schema->string()
                ->description('The cleaned text content of the user\'s email reply.')
                ->required(),

            'thread_summary' => $schema->string()
                ->description('Summary of the conversation thread for context.')
                ->default(''),

            'attachments_excerpt' => $schema->string()
                ->description('Text excerpt from any email attachments.')
                ->default(''),

            'recent_memories' => $schema->array()
                ->description('Array of recent conversation memories for context.')
                ->default([]),
        ];
    }

    /**
     * Interpret the action using LLM with structured prompt.
     */
    private function interpretAction(string $cleanReply, string $threadSummary, string $attachmentsExcerpt, array $recentMemories): array
    {
        $prompt = $this->buildInterpretationPrompt($cleanReply, $threadSummary, $attachmentsExcerpt, $recentMemories);

        try {
            $result = $this->llmClient->call('action_interpret', [
                'clean_reply' => $cleanReply,
                'thread_summary' => $threadSummary,
                'attachments_excerpt' => $attachmentsExcerpt,
                'recent_memories' => json_encode($recentMemories),
            ]);

            // Parse the simple response into structured data
            return $this->parseActionResponse($result, $cleanReply);
        } catch (\Exception $e) {
            // Fallback to safe defaults
            return [
                'action_type' => 'info_request',
                'parameters' => ['question' => $cleanReply],
                'confidence' => 0.5,
                'needs_clarification' => false,
                'clarification_prompt' => null,
            ];
        }
    }

    /**
     * Programmatic entrypoint for services to get the JSON array directly.
     */
    public function runReturningArray(string $cleanReply, string $threadSummary = '', string $attachmentsExcerpt = '', array $recentMemories = []): array
    {
        $interpretation = $this->interpretAction($cleanReply, $threadSummary, $attachmentsExcerpt, $recentMemories);

        $validator = Validator::make($interpretation, [
            'action_type' => ['required', 'string'],
            'parameters' => ['required', 'array'],
            'confidence' => ['required', 'numeric'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $interpretation;
    }

    /**
     * Build the interpretation prompt.
     */
    private function buildInterpretationPrompt(string $cleanReply, string $threadSummary, string $attachmentsExcerpt, array $recentMemories): string
    {
        $context = '';

        if (! empty($threadSummary)) {
            $context .= "Thread Summary: {$threadSummary}\n\n";
        }

        if (! empty($attachmentsExcerpt)) {
            $context .= "Attachments: {$attachmentsExcerpt}\n\n";
        }

        if (! empty($recentMemories)) {
            $context .= 'Recent Memories: '.implode(', ', $recentMemories)."\n\n";
        }

        return "Analyze this user email and determine what action they want. Respond with only the action type from this list:

Available actions:
- info_request (asking for information/recipes/help)
- approve (approving something)
- reject (declining something)
- revise (wanting to change something)
- stop (wanting to end the conversation)

User message: {$cleanReply}

{$context}
What is the main action they want? Answer with just one word from the list above.";
    }

    /**
     * Parse the LLM response into structured action data.
     */
    private function parseActionResponse(string $result, string $cleanReply): array
    {
        $actionType = trim(strtolower($result));

        // Validate against allowed actions
        $allowedActions = ['info_request', 'approve', 'reject', 'revise', 'stop'];
        if (! in_array($actionType, $allowedActions)) {
            $actionType = 'info_request'; // Safe fallback
        }

        // Build parameters based on action type
        $parameters = $this->buildActionParameters($actionType, $cleanReply);

        return [
            'action_type' => $actionType,
            'parameters' => $parameters,
            'confidence' => 0.8, // Default high confidence for MCP responses
            'needs_clarification' => false,
            'clarification_prompt' => null,
        ];
    }

    /**
     * Build action parameters based on action type.
     */
    private function buildActionParameters(string $actionType, string $cleanReply): array
    {
        switch ($actionType) {
            case 'info_request':
                return ['question' => $cleanReply];
            case 'approve':
                return ['reason' => 'Approved via email'];
            case 'reject':
                return ['reason' => 'Declined via email'];
            case 'revise':
                return ['changes' => ['Request for changes via email']];
            case 'stop':
                return ['reason' => 'Conversation ended via email'];
            default:
                return ['question' => $cleanReply];
        }
    }
}
