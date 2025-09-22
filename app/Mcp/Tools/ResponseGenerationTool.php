<?php

namespace App\Mcp\Tools;

use App\Models\Agent;
use App\Services\LlmClient;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ResponseGenerationTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Generates intelligent responses from AI agents based on their role, expertise, and the user query.';

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
        $agentId = $request->string('agent_id');
        $userQuery = $request->string('user_query');
        $context = $request->array('context', []);

        $agent = Agent::find($agentId);
        if (! $agent) {
            return Response::error('Agent not found');
        }

        $response = $this->generateAgentResponse($agent, $userQuery, $context);

        return Response::json([
            'response' => $response['response'],
            'confidence' => $response['confidence'],
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'processing_time' => $response['processing_time'] ?? null,
            'model_used' => $response['model_used'] ?? null,
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
            'agent_id' => $schema->string()
                ->description('The ULID of the agent to generate response from.')
                ->required(),

            'user_query' => $schema->string()
                ->description('The user\'s question or request that the agent should respond to.')
                ->required(),

            'context' => $schema->object([
                'thread_summary' => $schema->string()->description('Summary of the conversation thread'),
                'recent_messages' => $schema->array()->description('Recent messages in the conversation'),
                'agent_instructions' => $schema->array()->description('Specific instructions for the agent'),
                'locale' => $schema->string()->description('User locale for localization'),
            ])
                ->description('Contextual information for response generation.')
                ->default([]),
        ];
    }

    /**
     * Generate a response from the agent using LLM.
     */
    private function generateAgentResponse(Agent $agent, string $userQuery, array $context): array
    {
        $capabilities = $agent->capabilities_json ?? [];

        // Build the agent prompt
        $prompt = $this->buildAgentPrompt($agent, $userQuery, $context);

        // Make LLM call with agent-specific configuration
        try {
            $llmResponse = $this->llmClient->json('agent_response', [
                'prompt' => $prompt,
            ]);
        } catch (\Exception $e) {
            // Use fallback response when LLM fails
            $llmResponse = [
                'response' => $this->generateFallbackResponse($agent, $userQuery, $context),
                'confidence' => 0.5,
                'reasoning' => 'Fallback due to LLM processing error',
            ];
        }

        return [
            'response' => $llmResponse['response'] ?? '',
            'confidence' => $llmResponse['confidence'] ?? 0.8,
            'processing_time' => now()->diffInSeconds(now()), // Would need to track start time
            'model_used' => 'gpt-oss:20b',
        ];
    }

    /**
     * Build a comprehensive prompt for the agent.
     */
    private function buildAgentPrompt(Agent $agent, string $userQuery, array $context): string
    {
        $capabilities = $agent->capabilities_json ?? [];
        $instructions = $context['agent_instructions'] ?? $this->getDefaultInstructions($agent);

        $prompt = "You are {$agent->name}, {$agent->role}.\n\n";

        // Add experience/background if available
        if (isset($capabilities['experience'])) {
            $prompt .= "Your experience: {$capabilities['experience']}.\n";
        }

        // Add personality
        if (isset($capabilities['personality'])) {
            $prompt .= "Your personality: {$capabilities['personality']}.\n\n";
        }

        // Add expertise areas
        if (isset($capabilities['expertise']) && is_array($capabilities['expertise'])) {
            $prompt .= 'Your areas of expertise: '.implode(', ', $capabilities['expertise']).".\n\n";
        }

        // Add specific instructions
        $prompt .= "INSTRUCTIONS:\n";
        foreach ($instructions as $instruction) {
            $prompt .= "- {$instruction}\n";
        }
        $prompt .= "\n";

        // Add thread context
        if (! empty($context['thread_summary'])) {
            $prompt .= "THREAD CONTEXT:\n{$context['thread_summary']}\n\n";
        }

        // Add recent messages
        if (! empty($context['recent_messages'])) {
            $prompt .= "RECENT CONVERSATION:\n";
            foreach ($context['recent_messages'] as $message) {
                $direction = $message['direction'] === 'inbound' ? 'User' : 'Assistant';
                $prompt .= "{$direction}: {$message['content_preview']}\n";
            }
            $prompt .= "\n";
        }

        // Add the specific task
        $prompt .= "CURRENT TASK:\n";
        $prompt .= "User Query: {$userQuery}\n\n";

        $prompt .= "Please provide a helpful, detailed response based on your expertise and role.\n";
        $prompt .= "Respond in a natural, conversational tone that matches your personality.\n";
        $prompt .= "Draw from your experience and knowledge to provide valuable insights.\n\n";

        return $prompt;
    }

    /**
     * Generate a fallback response when LLM processing fails.
     */
    private function generateFallbackResponse(Agent $agent, string $userQuery, array $context): string
    {
        $capabilities = $agent->capabilities_json ?? [];

        $response = "Hello! I'm {$agent->name}, {$agent->role}.";

        if (isset($capabilities['experience'])) {
            $response .= " With {$capabilities['experience']}.";
        }

        $response .= "\n\nI apologize for any technical difficulties I'm experiencing right now. ";

        // Provide some basic helpful response based on agent type
        if (str_contains(strtolower($agent->role), 'chef') || str_contains(strtolower($agent->role), 'cook')) {
            if (str_contains(strtolower($userQuery), 'recipe') || str_contains(strtolower($userQuery), 'cook')) {
                $response .= "For cooking questions, I always recommend focusing on fresh, quality ingredients and proper technique. Feel free to ask me about specific recipes or cooking methods when I'm back to full capacity!";
            } else {
                $response .= "I'm passionate about food and cooking. Please try your question again or ask me about Italian cuisine!";
            }
        } elseif (str_contains(strtolower($agent->role), 'technical') || str_contains(strtolower($agent->role), 'support')) {
            $response .= "For technical questions, I recommend checking documentation first and providing specific error messages when possible. I'll be happy to help troubleshoot when I'm fully operational!";
        } else {
            $response .= "I'm here to help with your question: '{$userQuery}'. Please try again or rephrase your request.";
        }

        return $response;
    }

    /**
     * Get default instructions for an agent if none provided.
     */
    private function getDefaultInstructions(Agent $agent): array
    {
        return [
            "Analyze the user's question carefully",
            'Provide detailed, accurate information based on your expertise',
            'Use your specialized knowledge and experience',
            'Structure your response clearly and helpfully',
            'Maintain your defined personality and communication style',
        ];
    }
}
