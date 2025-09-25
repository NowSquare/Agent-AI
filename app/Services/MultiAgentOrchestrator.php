<?php

/**
 * What this file does — Runs the plan→allocate→work→debate→decide→curate cycle.
 * Plain: Coordinates several small agents to plan, write, check, pick, and save.
 * How this fits in:
 * - Called for complex actions that need multiple steps
 * - Writes AgentSteps so Activity shows rounds and votes
 * - Saves a decision memory after choosing a winner
 * Key terms: Planner/Worker/Critic/Arbiter roles; round_no; vote_score
 *
 * For engineers:
 * - Inputs: Action, Thread, Account
 * - Outputs: Action updated with final_response; AgentSteps; Memory saved
 * - Failure modes: LLM/provider issues → fewer candidates; still picks best available
 */

namespace App\Services;

use App\Models\Account;
use App\Models\Action;
use App\Models\Agent;
use App\Models\Task;
use App\Models\Thread;
use Illuminate\Support\Facades\Log;

class MultiAgentOrchestrator
{
    /**
     * MultiAgentOrchestrator coordinates the full multi-agent protocol flow.
     *
     * Phases (kept lightweight and testable):
     *  - Plan:     Planner produces a task graph (tasks, deps)
     *  - Allocate: Registry scores utility and selects top-K workers per task
     *  - Work:     Workers produce drafts (can be parallel/sequential)
     *  - Debate:   Critics run K rounds scoring groundedness/completeness/risk
     *  - Decide:   Arbiter aggregates votes (reliability-weighted) with tie-breaks
     *  - Curate:   Persist a typed memory (Decision/Insight/Fact) with provenance
     */
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
    /**
     * Orchestrate a complex request across multiple agents using the protocol.
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
        $provenanceStepIds = [];
        $plannerStep = \App\Models\AgentStep::create([
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
        $provenanceStepIds[] = (string) $plannerStep->id;

        Log::info('MultiAgentOrchestrator: Plan created', [
            'action_id' => $action->id,
            'task_count' => count($plan['tasks'] ?? []),
        ]);

        // Step 2: Check if user confirmation is needed (disabled by default)
        if ($this->shouldAskForConfirmation($plan)) {
            $this->sendConfirmationEmail($action, $thread, $plan);

            return; // Wait for user response
        }

        // Step 2: Allocate → Work (dispatch workers by capability / utility scoring)
        $candidates = $this->dispatchWorkers($action, $thread, $account, $plan);

        // Optional validation+repair pass using symbolic plan emitted by Planner/Workers
        $planValidator = app(\App\Services\PlanValidator::class);
        $initialFacts = $this->extractInitialFacts($action, $thread);

        // Prefer a candidate that carries a plan; fall back to Planner plan
        $symbolicPlan = $plan['plan'] ?? null;
        foreach ($candidates as $c) {
            if (! empty($c['plan'])) {
                $symbolicPlan = $c['plan'];
                break;
            }
        }
        if ($symbolicPlan) {
            $report = $planValidator->validate($symbolicPlan, $initialFacts);
            $validatorStep = \App\Models\AgentStep::create([
                'account_id' => $account->id,
                'thread_id' => $thread->id,
                'action_id' => $action->id,
                'role' => 'CLASSIFY',
                'provider' => 'internal',
                'model' => 'plan-validator',
                'step_type' => 'route',
                'input_json' => ['plan' => $symbolicPlan, 'initial_facts' => $initialFacts],
                'output_json' => ['report' => $report],
                'latency_ms' => 0,
                'agent_role' => 'Critic',
                'round_no' => 0,
            ]);
            $provenanceStepIds[] = (string) $validatorStep->id;

            if (! $report['valid']) {
                // Try simple auto-repair based on failed precondition
                $repaired = $this->repairPlan($symbolicPlan, (string) ($report['error'] ?? ''));
                if ($repaired) {
                    $symbolicPlan = $repaired;
                    $report = $planValidator->validate($symbolicPlan, $initialFacts);
                }

                // If still invalid, feed hint to debate once and retry best plan
                if (! $report['valid']) {
                    $decision = $this->debate->runKRounds($candidates, evidence: [['type' => 'plan_hint', 'hint' => $report['hint']]], rounds: 1);
                    $winner = $decision['winner'];
                    if (! empty($winner['plan'])) {
                        $symbolicPlan = $winner['plan'];
                        $report = $planValidator->validate($symbolicPlan, $initialFacts);
                    }
                }
            }

            // Gate SendReply on valid plan; else fallback to clarification/options path
            if (isset($symbolicPlan['steps'])) {
                $allowsSend = $report['valid'] === true;
                // Append gating info to action payload for downstream use
                $action->update(['payload_json' => array_merge($action->payload_json ?? [], [
                    'plan_report' => $report,
                    'plan_valid' => $allowsSend,
                ])]);

                // If plan is not valid after repair/debate hint, branch to safer fallback and stop
                if (! $allowsSend) {
                    \Log::info('MultiAgentOrchestrator: Plan invalid after validation; routing to options/clarification', [
                        'action_id' => $action->id,
                    ]);
                    $action->update(['status' => 'awaiting_input']);
                    \App\Jobs\SendOptionsEmail::dispatch($action);

                    return;
                }
            }
        }

        // Step 3: Debate (Critic rounds = 2) → Decide (Arbiter)
        $decision = $this->debate->runKRounds($candidates, evidence: [], rounds: 2);
        $winner = $decision['winner'];

        // Log Arbiter decision
        if ($winner) {
            $arbiterStep = \App\Models\AgentStep::create([
                'account_id' => $account->id,
                'thread_id' => $thread->id,
                'action_id' => $action->id,
                'role' => 'SYNTH',
                'provider' => 'internal',
                'model' => 'arbiter',
                'step_type' => 'route',
                'input_json' => ['candidates' => array_map(fn ($c) => ['id' => $c['id'], 'score' => $c['score']], $candidates)],
                'output_json' => ['winner_id' => $winner['id'] ?? null, 'votes' => $decision['votes']],
                'latency_ms' => 0,
                'agent_role' => 'Arbiter',
                'round_no' => count($decision['votes']),
                'vote_score' => isset($winner['score']) ? round((float) $winner['score'], 2) : null,
                'decision_reason' => $decision['reasons'][array_key_last($decision['reasons'])] ?? null,
            ]);
            $provenanceStepIds[] = (string) $arbiterStep->id;
        }

        // Step 4: Curate memory of the outcome
        $finalAnswer = (string) ($winner['text'] ?? '');
        $this->curator->persistOutcome($run->id, $thread, $account, $finalAnswer, provenanceIds: $provenanceStepIds);

        // Mark action as completed with final answer
        $action->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payload_json' => array_merge($action->payload_json, [
                'final_response' => $finalAnswer,
                'processing_type' => 'multi_agent_protocol',
            ]),
        ]);

        // Queue outbound response email to the original sender
        try {
            \App\Jobs\SendActionResponse::dispatch($action);
        } catch (\Throwable $e) {
            Log::warning('MultiAgentOrchestrator: Failed to dispatch SendActionResponse job', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Summary: Extract initial facts from action/thread for planning.
     */
    private function extractInitialFacts(Action $action, Thread $thread): array
    {
        $question = (string) ($action->payload_json['question'] ?? '');
        $hasAttachmentHeuristic = stripos($question, 'attach') !== false;

        return [
            'received' => true,
            'has_attachment' => $thread->emailMessages()->with('attachments')->get()->pluck('attachments')->flatten()->isNotEmpty() || $hasAttachmentHeuristic,
            'clamav_ready' => true, // assuming daemon available per config
            'scanned' => false,
            'extracted' => false,
            'text_available' => false,
            'summary_ready' => false,
            'classified' => true, // classify already done upstream
            'retrieval_done' => false,
            'confidence' => (float) ($action->payload_json['confidence'] ?? 0.5),
        ];
    }

    /**
     * Try to repair a plan by inserting a prerequisite action whose effect satisfies the failed condition.
     * Plain: If a step needs something that isn't true yet, add the step that makes it true.
     */
    private function repairPlan(array $plan, string $error): ?array
    {
        if (! preg_match('/Precondition failed:\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*(<=|>=|=|<|>)\s*([a-zA-Z0-9_.-]+)/', $error, $m)) {
            return null;
        }
        $key = $m[1];
        $op = $m[2];
        $val = $m[3];
        if ($op !== '=') {
            return null; // only boolean equality auto-repair for simplicity
        }
        $target = $key.'='.$val;
        $schema = config('actions');
        $actionName = null;
        foreach ($schema as $name => $def) {
            foreach ($def['eff'] as $eff) {
                if ($eff === $target) {
                    $actionName = $name;
                    break 2;
                }
            }
        }
        if (! $actionName) {
            return null;
        }

        $steps = $plan['steps'] ?? [];
        $steps[] = [
            'state' => [$key.'='.(($val === 'true') ? 'false' : 'false')],
            'action' => ['name' => $actionName, 'args' => []],
            'next_state' => [$target],
        ];
        $plan['steps'] = $steps;

        return $plan;
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
        return 'define_agents, web_search, calendar_access, email_composition';
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
            ->where('role', 'LIKE', '%'.$agentData['role'].'%')
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
            if (! in_array($dependency, $executedTasks)) {
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
            $k = (int) config('agents.workers_topk', 2);

            // Rank candidate agents by utility for this task (auction heuristic)
            $shortlist = $this->agentRegistry->topKForTask($account, [
                'description' => $taskDef['description'] ?? '',
                'question' => $action->payload_json['question'] ?? '',
            ], $k);

            // Log allocation shortlist as Planner step for observability
            \App\Models\AgentStep::create([
                'account_id' => $account->id,
                'thread_id' => $thread->id,
                'action_id' => $action->id,
                'role' => 'CLASSIFY',
                'provider' => 'internal',
                'model' => 'allocator',
                'step_type' => 'route',
                'input_json' => ['task' => $taskDef],
                'output_json' => [
                    'shortlist' => array_map(function ($row) {
                        return [
                            'agent_id' => (string) $row['agent']->id,
                            'name' => $row['agent']->name,
                            'utility' => $row['utility'],
                            'reliability' => $row['agent']->reliability,
                            'cost_hint' => $row['agent']->cost_hint,
                        ];
                    }, $shortlist),
                ],
                'latency_ms' => 0,
                'agent_role' => 'Planner',
                'round_no' => 0,
            ]);

            // Execute each shortlisted worker for this task
            foreach ($shortlist as $row) {
                $agent = $row['agent'];

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
                    'id' => (string) $task->id,
                    'agent_id' => (string) $agent->id,
                    'text' => (string) ($task->result_json['response'] ?? ''),
                    'score' => (float) ($task->result_json['confidence'] ?? 0.0),
                    'evidence' => [],
                    'cost_hint' => (int) ($agent->cost_hint ?? 100),
                    'reliability' => (float) ($agent->reliability ?? 0.8),
                ];
            }
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
        $response .= '*This response was coordinated by multiple AI agents working together.*';

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
        if (! str_ends_with($response, '.')) {
            $response .= '.';
        }

        return $response;
    }
}
