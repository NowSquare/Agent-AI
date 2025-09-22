<?php

namespace App\Mcp\Prompts;

use App\Services\LlmClient;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class OrchestrateComplexRequestPrompt extends Prompt
{
    /**
     * The prompt's description.
     */
    protected string $description = 'Coordinates complex multi-agent processing of user requests, managing agent interactions and synthesizing final responses.';

    /**
     * Create a new prompt instance.
     */
    public function __construct(
        protected LlmClient $llmClient,
    ) {}

    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): Response
    {
        $goal = $request->string('goal');
        $definedAgents = $request->string('defined_agents');
        $conversationSubject = $request->string('conversation_subject');
        $conversationContent = $request->string('conversation_plaintext_content');

        // Generate the orchestration prompt
        $promptContent = $this->buildOrchestrationPrompt(
            $goal,
            $definedAgents,
            $conversationSubject,
            $conversationContent
        );

        // Use LLM to generate orchestration response
        try {
            $result = $this->llmClient->call('respond_to_user', [
                'goal' => $goal,
                'defined_agents' => $definedAgents,
                'conversation_subject' => $conversationSubject,
                'conversation_plaintext_content' => $conversationContent,
            ]);

            return Response::text($result);
        } catch (\Exception $e) {
            return Response::text($this->generateFallbackOrchestrationResponse($goal, $definedAgents));
        }
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, \Laravel\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'goal',
                description: 'The primary goal for processing this complex request.',
                required: true,
            ),
            new Argument(
                name: 'defined_agents',
                description: 'JSON string describing the agents and their tasks.',
                required: true,
            ),
            new Argument(
                name: 'conversation_subject',
                description: 'The subject line of the conversation.',
                required: true,
            ),
            new Argument(
                name: 'conversation_plaintext_content',
                description: 'The main content of the user\'s message.',
                required: true,
            ),
        ];
    }

    /**
     * Build the orchestration prompt for coordinating agents.
     */
    private function buildOrchestrationPrompt(string $goal, string $definedAgents, string $subject, string $content): string
    {
        return "# Multi-Agent Coordination

## Goal
{$goal}

## Conversation Context
**Subject:** {$subject}
**Message:** {$content}

## Defined Agents
{$definedAgents}

## Coordination Instructions

You are coordinating multiple AI agents to process this complex request. Your role is to:

1. **Present the Plan**: Explain to the user what agents have been assigned and what they will do
2. **Get Confirmation**: Ask the user if they want to proceed with this approach
3. **Be Professional**: Use clear, helpful language that builds trust

## Response Guidelines
- Start with acknowledging the user's request
- Summarize the agent assignments in a user-friendly way
- Ask for confirmation before proceeding
- Keep the tone helpful and professional
- End with a clear call-to-action

## Example Response
\"I understand you need help with [request]. I've coordinated a team of specialists:

- **Planning Agent**: Will organize the approach
- **Specialist Agent**: Will provide expert recommendations
- **Coordinator Agent**: Will ensure everything works together

Would you like me to proceed with this approach, or would you prefer to modify the plan?\"";
    }

    /**
     * Generate fallback orchestration response when LLM fails.
     */
    private function generateFallbackOrchestrationResponse(string $goal, string $definedAgents): string
    {
        return "I understand your request for: {$goal}

I've assembled a team of AI specialists to help process your request. The team includes:

• **Coordinator Agent**: Manages the overall process
• **Specialist Agent**: Provides expert assistance

Would you like me to proceed with this coordinated approach, or would you prefer a different strategy? Please let me know how you'd like to move forward.";
    }
}
