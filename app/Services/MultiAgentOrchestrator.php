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
        private Planner $planner,
        private DebateCoordinator $debate,
        private MemoryCurator $curator,
    ) {}

    /**
     * Orchestrate multiple agents for complex requests.
     */
    public function orchestrateComplexRequest(Action $action, Thread $thread, Account $account): void
    {
        Log::info('MultiAgentOrchestrator: Starting complex orchestration', [
            'action_id' => $action->id,
        ]);

        // Create or update AgentRun blackboard
        $run = \App\Models\AgentRun::create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'state' => [
                'phase' => 'init',
                'plan' => null,
                'candidates' => [],
                'votes' => [],
            ],
            'round_no' => 0,
        ]);

        // Step 1: Plan (Planner role)
        $plan = $this->planner->plan($action, $thread);
        $run->update(['state->plan' => $plan, 'state->phase' => 'planned']);
        \App\Models\AgentStep::create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'action_id' => $action->id,
            'role' => 'CLASSIFY', // keep routing taxonomy
            'provider' => 'internal',
            'model' => 'planner',
            'step_type' => 'route',
            'input_json' => ['question' => $action->payload_json['question'] ?? ''],
            'output_json' => ['plan' => $plan],
            'latency_ms' => 0,
            'agent_role' => 'Planner',
            'round_no' => 0,
        ]);

        Log::info('MultiAgentOrchestrator: Plan created', [
            'action_id' => $action->id,
            'task_count' => count($plan['tasks'] ?? []),
        ]);

        // Step 2: Check if user confirmation is needed
        if ($this->shouldAskForConfirmation($orchestrationPlan)) {
            $this->sendConfirmationEmail($action, $thread, $orchestrationPlan);
            return; // Wait for user response
        }

        // Step 2: Allocate → Work (dispatch workers by capability)
        $candidates = $this->dispatchWorkers($action, $thread, $account, $plan);

        // Step 3: Debate (Critic rounds = 2) → Decide (Arbiter)
        $decision = $this->debate->runKRounds($candidates, evidence: [] , rounds: 2);
        $winner = $decision['winner'];

        // Log Arbiter decision
        if ($winner) {
            \App\Models\AgentStep::create([
                'account_id' => $account->id,
                'thread_id' => $thread->id,
                'action_id' => $action->id,
                'role' => 'SYNTH',
                'provider' => 'internal',
                'model' => 'arbiter',
                'step_type' => 'route',
                'input_json' => ['candidates' => array_map(fn($c) => ['id'=>$c['id'],'score'=>$c['score']], $candidates)],
                'output_json' => ['winner_id' => $winner['id'] ?? null, 'votes' => $decision['votes']],
                'latency_ms' => 0,
                'agent_role' => 'Arbiter',
                'round_no' => count($decision['votes']),
                'vote_score' => isset($winner['score']) ? round((float)$winner['score'], 2) : null,
                'decision_reason' => $decision['reasons'][array_key_last($decision['reasons'])] ?? null,
            ]);
        }

        // Step 4: Curate memory of the outcome
        $finalAnswer = (string)($winner['text'] ?? '');
        $this->curator->persistOutcome($run->id, $thread, $account, $finalAnswer, provenanceIds: []);

        // Mark action as completed with final answer
        $action->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payload_json' => array_merge($action->payload_json, [
                'final_response' => $finalAnswer,
                'processing_type' => 'multi_agent_protocol',
            ]),
        ]);
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
     * Execute tasks in proper dependency order with coordinator oversight.
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

        Log::info('MultiAgentOrchestrator: Starting coordinated task execution', [
            'action_id' => $action->id,
            'total_tasks' => $tasks->count(),
        ]);

        while ($tasks->where('status', 'pending')->count() > 0 && $iteration < $maxIterations) {
            $iteration++;

            foreach ($tasks as $task) {
                if ($task->status !== 'pending') {
                    continue;
                }

                // Check if dependencies are met
                if ($this->areDependenciesMet($task, $executedTasks)) {
                    Log::info('MultiAgentOrchestrator: Executing task', [
                        'task_id' => $task->id,
                        'agent_id' => $task->agent_id,
                        'description' => $task->input_json['task_description'] ?? 'unknown',
                    ]);

                    $this->agentProcessor->processTask($task);
                    $executedTasks[] = $task->id;
                }
            }
        }

        Log::info('MultiAgentOrchestrator: Task execution complete, compiling final response', [
            'action_id' => $action->id,
            'executed_tasks' => count($executedTasks),
            'remaining_tasks' => $tasks->where('status', 'pending')->count(),
        ]);

        // Compile results from all tasks into single coordinated response
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
     * Compile final response from all completed tasks - COORDINATOR SYNTHESIS.
     */
    private function compileFinalResponse(Action $action, $tasks): void
    {
        $completedTasks = $tasks->where('status', 'completed');
        $results = [];

        foreach ($completedTasks as $task) {
            $results[] = [
                'agent' => $task->agent->name,
                'role' => $task->agent->role,
                'task' => $task->input_json['task_description'] ?? '',
                'result' => $task->result_json['response'] ?? '',
                'confidence' => $task->result_json['confidence'] ?? 0,
            ];
        }

        Log::info('MultiAgentOrchestrator: Synthesizing agent responses', [
            'action_id' => $action->id,
            'agents_contributed' => collect($results)->pluck('agent')->unique()->values(),
            'total_tasks' => $completedTasks->count(),
        ]);

        // Coordinator synthesizes all agent outputs into single coherent response
        $finalResponse = $this->compileResultsIntoResponse($results);

        $action->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payload_json' => array_merge($action->payload_json, [
                'final_response' => $finalResponse,
                'task_results' => $results,
                'processing_type' => 'multi_agent_orchestration',
                'coordinator_synthesis' => true,
            ]),
        ]);

        Log::info('MultiAgentOrchestrator: Orchestration complete - single coordinated response sent', [
            'action_id' => $action->id,
            'synthesized_from_agents' => count($results),
            'response_length' => strlen($finalResponse),
        ]);
    }

    /**
     * Allocate and execute workers based on plan; return candidate drafts.
     */
    private function dispatchWorkers(Action $action, Thread $thread, Account $account, array $plan): array
    {
        $candidates = [];

        foreach ($plan['tasks'] as $taskDef) {
            $agent = $this->agentRegistry->findBestAgentForAction($account, $action->toArray(), [
                'thread_summary' => $thread->context_json['summary'] ?? '',
            ]);

            $task = \App\Models\Task::create([
                'account_id' => $account->id,
                'thread_id' => $thread->id,
                'agent_id' => $agent->id,
                'status' => 'pending',
                'input_json' => [
                    'action_id' => $action->id,
                    'action_type' => $action->type,
                    'action_payload' => $action->payload_json,
                    'thread_context' => [
                        'thread_id' => $thread->id,
                        'account_id' => $account->id,
                        'subject' => $thread->subject,
                        'summary' => $thread->context_json['summary'] ?? '',
                        'recent_messages' => [],
                    ],
                    'agent_instructions' => [
                        'role' => $agent->role,
                        'task' => $taskDef['description'],
                    ],
                    'round_no' => 1,
                ],
            ]);

            $this->agentProcessor->processTask($task);

            $candidates[] = [
                'id' => (string)$task->id,
                'text' => (string)($task->result_json['response'] ?? ''),
                'score' => (float)($task->result_json['confidence'] ?? 0.0),
                'evidence' => [],
            ];

            // Log worker step explicitly (already logged in AgentProcessor, but include coalition/round fields if needed)
        }

        return $candidates;
    }

    /**
     * Compile individual task results into coherent final response - COORDINATED SYNTHESIS.
     */
    private function compileResultsIntoResponse(array $results): string
    {
        if (empty($results)) {
            return "I've processed your request but encountered some issues. Please try again.";
        }

        // Group results by agent for better organization
        $agentGroups = collect($results)->groupBy('agent');

        $response = "I've coordinated a comprehensive response to your request:\n\n";

        foreach ($agentGroups as $agentName => $agentResults) {
            $response .= "**{$agentName}**:\n";

            foreach ($agentResults as $result) {
                // Clean up and format the agent response
                $cleanedResult = $this->cleanAgentResponse($result['result']);
                $response .= "{$cleanedResult}\n\n";
            }
        }

        $response .= "---\n";
        $response .= "*This response was coordinated by multiple AI agents working together.*";

        return $response;
    }

    /**
     * Clean and format agent responses for better readability.
     */
    private function cleanAgentResponse(string $response): string
    {
        // Remove any duplicate headers or formatting
        $response = preg_replace('/^#+\s*.+$/m', '', $response); // Remove markdown headers
        $response = trim($response);

        // Ensure proper formatting
        if (!str_ends_with($response, '.')) {
            $response .= '.';
        }

        return $response;
    }
}
