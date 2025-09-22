<?php

namespace App\Services;

use App\Models\Action;
use App\Models\Agent;
use App\Models\Task;
use App\Models\Thread;
use App\Models\Account;
use Illuminate\Support\Facades\Log;

class MultiAgentOrchestrator
{
    public function __construct(
        private LlmClient $llmClient,
        private AgentRegistry $agentRegistry,
        private AgentProcessor $agentProcessor,
    ) {}

    /**
     * Orchestrate multiple agents for complex requests.
     */
    public function orchestrateComplexRequest(Action $action, Thread $thread, Account $account): void
    {
        Log::info('MultiAgentOrchestrator: Starting complex orchestration', [
            'action_id' => $action->id,
        ]);

        // Step 1: Define agents and tasks using LLM
        $orchestrationPlan = $this->defineAgentsAndTasks($action, $thread);

        Log::info('MultiAgentOrchestrator: Generated orchestration plan', [
            'action_id' => $action->id,
            'agent_count' => count($orchestrationPlan['agents'] ?? []),
            'task_count' => count($orchestrationPlan['tasks'] ?? []),
        ]);

        // Step 2: Check if user confirmation is needed
        if ($this->shouldAskForConfirmation($orchestrationPlan)) {
            $this->sendConfirmationEmail($action, $thread, $orchestrationPlan);
            return; // Wait for user response
        }

        // Step 3: Execute the orchestration plan
        $this->executeOrchestrationPlan($action, $thread, $account, $orchestrationPlan);
    }

    /**
     * Use LLM to define agents and tasks for complex requests.
     */
    private function defineAgentsAndTasks(Action $action, Thread $thread): array
    {
        $question = $action->payload_json['question'] ?? '';

        $prompt = $this->buildAgentDefinitionPrompt($action, $thread);

        try {
            $response = $this->llmClient->json('define_agents', [
                'conversation_subject' => $thread->subject,
                'conversation_plaintext_content' => $question,
                'available_tools' => $this->getAvailableToolsList(),
                'goal' => $this->extractGoalFromQuestion($question),
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::warning('MultiAgentOrchestrator: LLM failed for agent definition, using fallback', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback: create basic agent structure
            return $this->createFallbackAgentStructure($action, $thread);
        }
    }

    /**
     * Create fallback agent structure when LLM fails.
     */
    private function createFallbackAgentStructure(Action $action, Thread $thread): array
    {
        $question = $action->payload_json['question'] ?? '';

        return [
            'goal' => $this->extractGoalFromQuestion($question),
            'agents' => [
                [
                    'name' => 'CoordinatorAgent',
                    'role' => 'Event Planning Coordinator',
                    'capabilities' => ['planning', 'organization', 'communication'],
                    'tasks' => [
                        [
                            'description' => 'Analyze the event planning request and break it down into manageable tasks',
                            'tool' => null,
                            'dependencies' => [],
                        ],
                        [
                            'description' => 'Provide organized response with next steps',
                            'tool' => null,
                            'dependencies' => ['task_1'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the agent definition prompt based on user's approach.
     */
    private function buildAgentDefinitionPrompt(Action $action, Thread $thread): string
    {
        $question = $action->payload_json['question'] ?? '';

        return "
# Agent and Task Creation

## Objective
Transform the user's request into a set of specialized agents with clear, actionable tasks, optimized for tool-based execution while ensuring logical flow and minimal redundancy.

## Instructions
- **Query Analysis**:
  - Extract the primary goal and any sub-goals from the user's message.
  - Identify explicit requirements and constraints.
  - For vague inputs, make minimal logical assumptions.
- **Agent Definition**:
  - Define agents with distinct, specialized roles.
  - Ensure each agent has a single, focused purpose.
  - Assign clear success criteria.
- **Task Specification**:
  - Break work into atomic tasks with one specific outcome.
  - Limit each task to one tool or no tools if self-contained.
  - Ensure task completion is verifiable.
  - Order tasks by dependencies.
- **Task Sequencing**:
  - Validate prerequisite data collection.
  - Check common sequences and avoid circular dependencies.
- **Communication Standards**:
  - Mirror the user's language style and terms.

## Context
**Subject:** {$thread->subject}
**Message:** {$question}

## Resources
**Available Tools:** {$this->getAvailableToolsList()}

Return a JSON structure with:
{
  \"goal\": \"extracted primary goal\",
  \"agents\": [
    {
      \"name\": \"AgentName\",
      \"role\": \"Specialized Role\",
      \"capabilities\": [\"capability1\", \"capability2\"],
      \"tasks\": [
        {
          \"description\": \"Task description\",
          \"tool\": \"tool_name\",
          \"dependencies\": [\"previous_task_id\"]
        }
      ]
    }
  ]
}
        ";
    }

    /**
     * Get list of available tools for agents.
     */
    private function getAvailableToolsList(): string
    {
        // TODO: Implement proper tool registry
        return "define_agents, web_search, calendar_access, email_composition";
    }

    /**
     * Extract primary goal from question.
     */
    private function extractGoalFromQuestion(string $question): string
    {
        // Simple goal extraction - could be enhanced with LLM
        if (str_contains(strtolower($question), 'plan') ||
            str_contains(strtolower($question), 'organize')) {
            return 'Planning and organization';
        }

        if (str_contains(strtolower($question), 'schedule') ||
            str_contains(strtolower($question), 'book')) {
            return 'Scheduling and booking';
        }

        if (str_contains(strtolower($question), 'find') ||
            str_contains(strtolower($question), 'search')) {
            return 'Information gathering';
        }

        return 'General assistance';
    }

    /**
     * Determine if user confirmation is needed.
     */
    private function shouldAskForConfirmation(array $orchestrationPlan): bool
    {
        // For now, skip confirmation for testing - proceed directly
        // TODO: Make this configurable and implement confirmation flow
        return false;
    }

    /**
     * Send confirmation email to user.
     */
    private function sendConfirmationEmail(Action $action, Thread $thread, array $orchestrationPlan): void
    {
        // Create a new action for confirmation
        $confirmationAction = Action::create([
            'account_id' => $action->account_id,
            'thread_id' => $action->thread_id,
            'type' => 'confirmation_pending',
            'payload_json' => [
                'original_action_id' => $action->id,
                'orchestration_plan' => $orchestrationPlan,
                'question' => 'Please confirm this plan before we proceed.',
            ],
            'status' => 'pending',
        ]);

        Log::info('MultiAgentOrchestrator: Sent confirmation request', [
            'action_id' => $action->id,
            'confirmation_action_id' => $confirmationAction->id,
        ]);

        // TODO: Send actual confirmation email
        // For now, just mark original action as waiting
        $action->update(['status' => 'waiting_confirmation']);
    }

    /**
     * Execute the approved orchestration plan.
     */
    private function executeOrchestrationPlan(Action $action, Thread $thread, Account $account, array $orchestrationPlan): void
    {
        $agents = $orchestrationPlan['agents'] ?? [];

        foreach ($agents as $agentData) {
            // Find or create agent for this role
            $agent = $this->findOrCreateAgentForRole($account, $agentData);

            // Create tasks for this agent
            foreach ($agentData['tasks'] ?? [] as $taskData) {
                $task = Task::create([
                    'account_id' => $account->id,
                    'thread_id' => $thread->id,
                    'agent_id' => $agent->id,
                    'status' => 'pending',
                    'input_json' => [
                        'action_id' => $action->id,
                        'task_description' => $taskData['description'],
                        'tool' => $taskData['tool'] ?? null,
                        'dependencies' => $taskData['dependencies'] ?? [],
                        'agent_instructions' => $this->buildAgentInstructions($agent, $taskData),
                    ],
                ]);

                Log::info('MultiAgentOrchestrator: Created task', [
                    'task_id' => $task->id,
                    'agent_id' => $agent->id,
                    'description' => $taskData['description'],
                ]);
            }
        }

        // Execute tasks in dependency order
        $this->executeTasksInOrder($action, $thread, $account);
    }

    /**
     * Find existing agent or create new one for the role.
     */
    private function findOrCreateAgentForRole(Account $account, array $agentData): Agent
    {
        // Try to find existing agent with similar role
        $existingAgent = $account->agents()
            ->where('role', 'LIKE', '%' . $agentData['role'] . '%')
            ->first();

        if ($existingAgent) {
            return $existingAgent;
        }

        // Create new agent
        return Agent::create([
            'account_id' => $account->id,
            'name' => $agentData['name'] ?? $agentData['role'],
            'role' => $agentData['role'],
            'capabilities_json' => $agentData['capabilities'] ?? [],
        ]);
    }

    /**
     * Build specific instructions for the agent task.
     */
    private function buildAgentInstructions(Agent $agent, array $taskData): array
    {
        $capabilities = $agent->capabilities_json ?? [];

        return [
            'role' => $agent->role,
            'task' => $taskData['description'],
            'tool' => $taskData['tool'] ?? null,
            'expertise_areas' => $capabilities['expertise'] ?? [],
            'personality' => $capabilities['personality'] ?? 'professional and helpful',
            'experience' => $capabilities['experience'] ?? '',
        ];
    }

    /**
     * Execute tasks in proper dependency order.
     */
    private function executeTasksInOrder(Action $action, Thread $thread, Account $account): void
    {
        $tasks = Task::where('thread_id', $thread->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        $executedTasks = [];
        $maxIterations = 10; // Prevent infinite loops
        $iteration = 0;

        while ($tasks->where('status', 'pending')->count() > 0 && $iteration < $maxIterations) {
            $iteration++;

            foreach ($tasks as $task) {
                if ($task->status !== 'pending') {
                    continue;
                }

                // Check if dependencies are met
                if ($this->areDependenciesMet($task, $executedTasks)) {
                    $this->agentProcessor->processTask($task);
                    $executedTasks[] = $task->id;
                }
            }
        }

        // Compile results from all tasks
        $this->compileFinalResponse($action, $tasks);
    }

    /**
     * Check if task dependencies are met.
     */
    private function areDependenciesMet(Task $task, array $executedTasks): bool
    {
        $dependencies = $task->input_json['dependencies'] ?? [];

        foreach ($dependencies as $dependency) {
            if (!in_array($dependency, $executedTasks)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compile final response from all completed tasks.
     */
    private function compileFinalResponse(Action $action, $tasks): void
    {
        $completedTasks = $tasks->where('status', 'completed');
        $results = [];

        foreach ($completedTasks as $task) {
            $results[] = [
                'agent' => $task->agent->name,
                'task' => $task->input_json['task_description'] ?? '',
                'result' => $task->result_json['response'] ?? '',
            ];
        }

        // Compile into final response
        $finalResponse = $this->compileResultsIntoResponse($results);

        $action->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payload_json' => array_merge($action->payload_json, [
                'final_response' => $finalResponse,
                'task_results' => $results,
                'processing_type' => 'multi_agent_orchestration',
            ]),
        ]);

        Log::info('MultiAgentOrchestrator: Completed orchestration', [
            'action_id' => $action->id,
            'tasks_completed' => $completedTasks->count(),
        ]);
    }

    /**
     * Compile individual task results into coherent final response.
     */
    private function compileResultsIntoResponse(array $results): string
    {
        if (empty($results)) {
            return "I've processed your request but encountered some issues. Please try again.";
        }

        $response = "Here's what I've accomplished:\n\n";

        foreach ($results as $result) {
            $response .= "**{$result['agent']}**: {$result['result']}\n\n";
        }

        return $response;
    }
}
