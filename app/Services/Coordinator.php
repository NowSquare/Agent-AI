<?php

/**
 * What this file does — Decides simple vs multi‑agent path and runs it.
 * Plain: Chooses whether one agent can answer or if we need several.
 * How this fits in:
 * - Called after an Action is interpreted
 * - For simple work: pick one agent and process
 * - For complex work: hand off to MultiAgentOrchestrator
 * Key terms: complex indicators (keywords/length), simple vs complex path
 *
 * For engineers:
 * - Inputs: Action (with payload)
 * - Outputs: Updates Action status/payload; creates Tasks and AgentSteps
 * - Failure modes: missing thread/account; task failure marks action failed
 */

namespace App\Services;

use App\Models\Account;
use App\Models\Action;
use App\Models\Agent;
use App\Models\Task;
use App\Models\Thread;
use Illuminate\Support\Facades\Log;

/**
 * Purpose: Top-level traffic cop for actions.
 * Responsibilities:
 * - Detect complexity
 * - Route to simple agent or multi-agent orchestrator
 * Collaborators: AgentRegistry, AgentProcessor, MultiAgentOrchestrator
 */
class Coordinator
{
    public function __construct(
        private AgentRegistry $agentRegistry,
        private AgentProcessor $agentProcessor,
        private MultiAgentOrchestrator $multiAgentOrchestrator,
    ) {}

    /**
     * Coordinate processing of an action - either simple (direct agent) or complex (multi-agent orchestration).
     */
    public function processAction(Action $action): void
    {
        Log::info('Coordinator: Processing action', [
            'action_id' => $action->id,
            'type' => $action->type,
        ]);

        $thread = $action->thread;
        $account = $action->account;

        if (! $thread || ! $account) {
            throw new \Exception('Action missing required thread or account relationship');
        }

        // Determine if this needs simple or complex processing
        if ($this->shouldUseMultiAgentOrchestration($action)) {
            $this->multiAgentOrchestrator->orchestrateComplexRequest($action, $thread, $account);
        } else {
            $this->processSimpleAgentResponse($action, $thread, $account);
        }
    }

    /**
     * Determine if action needs multi-agent orchestration vs simple response.
     */
    private function shouldUseMultiAgentOrchestration(Action $action): bool
    {
        $payload = $action->payload_json;

        // Complex indicators
        $question = $payload['question'] ?? '';
        $complexIndicators = [
            'multiple', 'several', 'plan', 'organize', 'schedule',
            'budget', 'compare', 'research', 'find', 'create',
        ];

        // Attachment/workflow indicators: treat as complex so we can validate/repair a plan
        $attachmentIndicators = ['attach', 'attachment', 'pdf', 'file', 'image', 'scan', 'extract', 'summarize'];

        $isComplex = false;
        foreach ($complexIndicators as $indicator) {
            if (str_contains(strtolower($question), $indicator)) {
                $isComplex = true;
                break;
            }
        }

        if ($isComplex === false) {
            foreach ($attachmentIndicators as $indicator) {
                if (str_contains(strtolower($question), $indicator)) {
                    $isComplex = true; // WHY: These usually require ordered steps (scan → extract → summarize)
                    break;
                }
            }
        }

        // Length indicator (>140 chars suggests complex request)
        if (strlen($question) > 140) {
            $isComplex = true;
        }

        return $isComplex;
    }

    /**
     * Process simple action with single best agent.
     */
    private function processSimpleAgentResponse(Action $action, Thread $thread, Account $account): void
    {
        // Find the best agent for this action
        $agent = $this->agentRegistry->findBestAgentForAction(
            $account,
            $action->toArray(),
            $this->buildContext($action, $thread)
        );

        Log::info('Coordinator: Simple processing - assigned to agent', [
            'action_id' => $action->id,
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
        ]);

        // Create a task for the agent
        $task = $this->createTaskForAgent($action, $agent, $thread, $account);

        // Process the task with the agent
        $this->agentProcessor->processTask($task);

        // Update the action with results
        $this->updateActionFromTask($action, $task);
    }

    /**
     * Build context information for agent selection.
     */
    private function buildContext(Action $action, Thread $thread): array
    {
        return [
            'thread_summary' => $thread->context_json['summary'] ?? '',
            'action_payload' => $action->payload_json,
            'locale' => 'en', // TODO: Detect from user/thread context
            'recent_messages' => $this->getRecentMessages($thread),
        ];
    }

    /**
     * Get recent messages from the thread for context.
     */
    private function getRecentMessages(Thread $thread, int $limit = 3): array
    {
        return $thread->emailMessages()
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($message) {
                return [
                    'direction' => $message->direction,
                    'subject' => $message->subject,
                    'content_preview' => substr($message->clean_body ?? '', 0, 200),
                ];
            })
            ->toArray();
    }

    /**
     * Create a task for the assigned agent.
     */
    private function createTaskForAgent(Action $action, Agent $agent, Thread $thread, Account $account): Task
    {
        $task = Task::create([
            'account_id' => $account->id,
            'thread_id' => $thread->id,
            'agent_id' => $agent->id,
            'status' => 'pending',
            'input_json' => [
                'action_id' => $action->id,
                'action_type' => $action->type,
                'action_payload' => $action->payload_json,
                'thread_context' => $this->buildThreadContext($thread),
                'agent_instructions' => $this->buildAgentInstructions($agent, $action),
            ],
        ]);

        Log::info('Coordinator: Created task for agent', [
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'action_id' => $action->id,
        ]);

        return $task;
    }

    /**
     * Build thread context for the agent.
     */
    private function buildThreadContext(Thread $thread): array
    {
        return [
            'thread_id' => $thread->id,
            'subject' => $thread->subject,
            'summary' => $thread->context_json['summary'] ?? '',
            'recent_messages' => $this->getRecentMessages($thread, 5),
            'key_entities' => $thread->context_json['key_entities'] ?? [],
            'open_questions' => $thread->context_json['open_questions'] ?? [],
        ];
    }

    /**
     * Build specific instructions for the agent based on their role and the action.
     */
    private function buildAgentInstructions(Agent $agent, Action $action): array
    {
        $capabilities = $agent->capabilities_json ?? [];
        $baseInstructions = $capabilities['instructions'] ?? [];

        $actionSpecificInstructions = match ($action->type) {
            'info_request' => [
                'Analyze the user\'s question carefully',
                'Provide detailed, accurate information based on your expertise',
                'Use your specialized knowledge and experience',
                'Structure your response clearly and helpfully',
                'Maintain your defined personality and communication style',
            ],
            'approve' => [
                'Review the request carefully',
                'Provide approval with any relevant conditions',
                'Explain your reasoning clearly',
            ],
            'reject' => [
                'Review the request carefully',
                'Provide clear reasoning for rejection',
                'Suggest alternatives if appropriate',
            ],
            'revise' => [
                'Review the current state and requested changes',
                'Provide specific recommendations',
                'Explain the rationale for your suggestions',
            ],
            default => [
                'Process the request according to your expertise',
                'Provide a helpful and appropriate response',
            ],
        };

        return array_merge($baseInstructions, $actionSpecificInstructions, [
            'role' => $agent->role,
            'expertise_areas' => $capabilities['expertise'] ?? [],
            'personality' => $capabilities['personality'] ?? 'professional and helpful',
            'experience' => $capabilities['experience'] ?? '',
        ]);
    }

    /**
     * Update the action with results from the completed task.
     */
    private function updateActionFromTask(Action $action, Task $task): void
    {
        if ($task->status === 'completed' && $task->result_json) {
            $result = $task->result_json;

            // Update action with agent response
            $action->update([
                'status' => 'completed',
                'completed_at' => now(),
                'payload_json' => array_merge($action->payload_json, [
                    'agent_response' => $result['response'] ?? '',
                    'agent_id' => $task->agent_id,
                    'processing_details' => $result,
                ]),
            ]);

            Log::info('Coordinator: Action completed with agent response', [
                'action_id' => $action->id,
                'agent_id' => $task->agent_id,
                'response_length' => strlen($result['response'] ?? ''),
            ]);

            // Send response email to the original sender
            try {
                \App\Jobs\SendActionResponse::dispatch($action);
            } catch (\Throwable $e) {
                Log::warning('Coordinator: Failed to dispatch SendActionResponse job', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            // Task failed
            $action->update([
                'status' => 'failed',
                'error_json' => ['task_failed' => true, 'task_status' => $task->status],
            ]);

            Log::error('Coordinator: Task failed for action', [
                'action_id' => $action->id,
                'task_id' => $task->id,
                'task_status' => $task->status,
            ]);
        }
    }
}
