<?php

namespace App\Mcp\Prompts;

use App\Services\LlmClient;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class DefineAgentsPrompt extends Prompt
{
    /**
     * The prompt's description.
     */
    protected string $description = 'Analyzes complex user requests and defines specialized agents with clear tasks and responsibilities for coordinated multi-agent processing.';

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
        $conversationSubject = $request->string('conversation_subject');
        $conversationContent = $request->string('conversation_plaintext_content');
        $goal = $request->string('goal', 'Process this user request effectively');
        $availableTools = $request->array('available_tools', []);

        $promptContent = $this->buildAgentDefinitionPrompt(
            $conversationSubject,
            $conversationContent,
            $goal,
            $availableTools
        );

        // Use LLM to define agents and tasks
        try {
            $result = $this->llmClient->call('define_agents', [
                'conversation_subject' => $conversationSubject,
                'conversation_plaintext_content' => $conversationContent,
                'goal' => $goal,
                'available_tools' => json_encode($availableTools),
            ]);

            // Parse the response (would be JSON in a real implementation)
            $orchestrationPlan = $this->parseAgentDefinitionResponse($result);

            return Response::json($orchestrationPlan);
        } catch (\Exception $e) {
            // Return fallback structure
            return Response::json($this->createFallbackAgentStructure($goal, $conversationContent));
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
                name: 'conversation_subject',
                description: 'The subject line of the conversation thread.',
                required: true,
            ),
            new Argument(
                name: 'conversation_plaintext_content',
                description: 'The main content of the user\'s message that needs processing.',
                required: true,
            ),
            new Argument(
                name: 'goal',
                description: 'The primary goal or objective for processing this request.',
                required: false,
            ),
            new Argument(
                name: 'available_tools',
                description: 'List of available tools that agents can use.',
                required: false,
            ),
        ];
    }

    /**
     * Build the agent definition prompt.
     */
    private function buildAgentDefinitionPrompt(string $subject, string $content, string $goal, array $availableTools): string
    {
        $toolsList = empty($availableTools) ? 'No special tools available' : implode(', ', $availableTools);

        return "# Agent Definition for Complex Request

## Context
**Subject:** {$subject}
**User Request:** {$content}

## Goal
{$goal}

## Available Tools
{$toolsList}

## Instructions
Break down this user request into 2-4 specialized agents, each with:
- A clear role and expertise area
- Specific tasks they should perform
- Dependencies between tasks (if any)
- Tools they should use (if applicable)

Return a structured plan for coordinated multi-agent processing.

## Output Format
Provide a JSON response with this structure:
{
  \"goal\": \"Clear restatement of the processing goal\",
  \"agents\": [
    {
      \"name\": \"AgentName\",
      \"role\": \"Specialized role description\",
      \"capabilities\": [\"capability1\", \"capability2\"],
      \"tasks\": [
        {
          \"description\": \"What this agent should do\",
          \"tool\": \"tool_name or null\",
          \"dependencies\": [\"task_id_1\", \"task_id_2\"] or []
        }
      ]
    }
  ]
}";
    }

    /**
     * Parse the LLM response into structured agent definition.
     */
    private function parseAgentDefinitionResponse(string $result): array
    {
        // In a real implementation, this would parse JSON
        // For now, return a structured response
        return [
            'goal' => 'Process complex user request through coordinated agents',
            'agents' => [
                [
                    'name' => 'CoordinatorAgent',
                    'role' => 'Orchestration Coordinator',
                    'capabilities' => ['planning', 'coordination', 'synthesis'],
                    'tasks' => [
                        [
                            'description' => 'Analyze request and coordinate agent responses',
                            'tool' => null,
                            'dependencies' => [],
                        ],
                    ],
                ],
                [
                    'name' => 'SpecialistAgent',
                    'role' => 'Domain Specialist',
                    'capabilities' => ['analysis', 'expertise'],
                    'tasks' => [
                        [
                            'description' => 'Provide specialized analysis and recommendations',
                            'tool' => null,
                            'dependencies' => ['task_1'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Create fallback agent structure when LLM fails.
     */
    private function createFallbackAgentStructure(string $goal, string $content): array
    {
        return [
            'goal' => $goal,
            'agents' => [
                [
                    'name' => 'FallbackCoordinator',
                    'role' => 'General Coordinator',
                    'capabilities' => ['general_assistance'],
                    'tasks' => [
                        [
                            'description' => 'Process the user request and provide assistance',
                            'tool' => null,
                            'dependencies' => [],
                        ],
                    ],
                ],
            ],
        ];
    }
}
