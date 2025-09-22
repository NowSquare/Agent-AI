<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Models\Agent;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class AgentSelectionTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Selects the most appropriate AI agent based on action requirements, context, and agent capabilities.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $accountId = $request->string('account_id');
        $actionData = $request->array('action_data');
        $context = $request->array('context', []);

        $account = Account::find($accountId);
        if (! $account) {
            return Response::error('Account not found');
        }

        $selectedAgent = $this->selectBestAgent($account, $actionData, $context);

        return Response::json([
            'agent_id' => $selectedAgent->id,
            'agent_name' => $selectedAgent->name,
            'agent_role' => $selectedAgent->role,
            'capabilities' => $selectedAgent->capabilities_json,
            'confidence_score' => $this->calculateConfidenceScore($selectedAgent, $actionData, $context),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->string()
                ->description('The ULID of the account to select agents from.')
                ->required(),

            'action_data' => $schema->object([
                'type' => $schema->string()->description('The action type (e.g., info_request)'),
                'payload_json' => $schema->object()->description('Action parameters and data'),
            ])
                ->description('The action data requiring agent selection.')
                ->required(),

            'context' => $schema->object([
                'thread_summary' => $schema->string()->description('Summary of the conversation thread'),
                'clean_reply' => $schema->string()->description('The cleaned user message'),
                'locale' => $schema->string()->description('User locale (e.g., en_US)'),
                'recent_memories' => $schema->array()->description('Recent conversation memories'),
            ])
                ->description('Context information for agent selection.')
                ->default([]),
        ];
    }

    /**
     * Select the best agent for the given action and context.
     */
    private function selectBestAgent(Account $account, array $actionData, array $context): Agent
    {
        $agents = $account->agents;

        if ($agents->isEmpty()) {
            return $this->createFallbackAgent($account);
        }

        $bestAgent = null;
        $highestScore = -1;

        foreach ($agents as $agent) {
            $score = $this->scoreAgent($agent, $actionData, $context);

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestAgent = $agent;
            }
        }

        return $bestAgent ?? $this->createFallbackAgent($account);
    }

    /**
     * Score an agent based on its capabilities and the action/context.
     */
    private function scoreAgent(Agent $agent, array $actionData, array $context): int
    {
        $score = 0;
        $capabilities = $agent->capabilities_json ?? [];

        // Action Type Match (10 points)
        if (in_array($actionData['type'], $capabilities['action_types'] ?? [])) {
            $score += 10;
        }

        // Keyword Match (5 points each)
        $question = strtolower($actionData['payload_json']['question'] ?? '');
        $threadSummary = strtolower($context['thread_summary'] ?? '');
        $cleanReply = strtolower($context['clean_reply'] ?? '');

        foreach ($capabilities['keywords'] ?? [] as $keyword) {
            if (str_contains($question, $keyword) ||
                str_contains($threadSummary, $keyword) ||
                str_contains($cleanReply, $keyword)) {
                $score += 5;
            }
        }

        // Domain Match (3 points each)
        foreach ($capabilities['domains'] ?? [] as $domain) {
            if (str_contains($question, $domain) ||
                str_contains($threadSummary, $domain)) {
                $score += 3;
            }
        }

        // Expertise Match (2 points each)
        foreach ($capabilities['expertise'] ?? [] as $expertise) {
            if (str_contains($question, $expertise) ||
                str_contains($threadSummary, $expertise)) {
                $score += 2;
            }
        }

        // Language Match (1 point)
        if (in_array($context['locale'] ?? 'en', $capabilities['languages'] ?? ['en'])) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Calculate confidence score for the selected agent.
     */
    private function calculateConfidenceScore(Agent $agent, array $actionData, array $context): float
    {
        $score = $this->scoreAgent($agent, $actionData, $context);
        $maxPossibleScore = 10 + 5 + 3 + 2 + 1; // Maximum theoretical score

        return min(1.0, $score / $maxPossibleScore);
    }

    /**
     * Create a generic fallback agent if no suitable agent is found.
     */
    private function createFallbackAgent(Account $account): Agent
    {
        return Agent::firstOrCreate(
            ['account_id' => $account->id, 'name' => 'Fallback Agent'],
            [
                'role' => 'General Assistant',
                'capabilities_json' => [
                    'action_types' => ['info_request', 'options_fallback'],
                    'keywords' => ['help', 'question', 'support'],
                    'domains' => ['general'],
                    'expertise' => ['general_knowledge'],
                    'languages' => ['en'],
                    'personality' => 'helpful, polite',
                    'experience' => 'basic assistance',
                ],
            ]
        );
    }
}
