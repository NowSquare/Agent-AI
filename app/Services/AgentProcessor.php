<?php

/**
 * What this file does — Runs a task with a chosen agent and logs the step.
 * Plain: Given a job and an agent, it asks the AI for a draft and stores the result.
 * How this fits in:
 * - Called by Coordinator/Orchestrator after an agent is selected
 * - Uses Grounding + Router to keep answers relevant and sized
 * - Writes an AgentStep so Activity shows the trace
 * Key terms: task (work unit), grounding (find evidence), role (CLASSIFY/GROUNDED/SYNTH)
 *
 * For engineers:
 * - Inputs: Task model
 * - Outputs: Task.result_json is populated; AgentStep row created
 * - Failure modes: provider error → task failed; falls back to simple text
 */

namespace App\Services;

use App\Models\Agent;
use App\Models\Memory;
use App\Models\Task;
use Illuminate\Support\Facades\Log;

/**
 * Purpose: Execute a single agent task and persist the outcome.
 * Responsibilities:
 * - Build prompts, retrieve grounding, route to model, call LLM
 * - Update the task status/results and log an AgentStep
 * Collaborators: LlmClient, MemoryService, GroundingService, ModelRouter
 */
class AgentProcessor
{
    public function __construct(
        private LlmClient $llmClient,
        private MemoryService $memoryService,
        private GroundingService $grounding,
        private ModelRouter $router,
    ) {}

    /**
     * Summary: Process a task with the assigned agent and persist results.
     *
     * @param  Task  $task  The work unit to execute (contains inputs/context)
     * @return void Updates the Task and writes an AgentStep
     *
     * @throws \Exception If the task has no agent or LLM call irrecoverably fails
     *                    Example:
     *                    $processor->processTask($task);
     */
    public function processTask(Task $task): void
    {
        $agent = $task->agent;

        if (! $agent) {
            throw new \Exception('Task has no assigned agent');
        }

        Log::info('AgentProcessor: Starting task processing', [
            'task_id' => $task->id,
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
        ]);

        // Mark task as started
        $task->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            // Generate agent response
            $response = $this->generateAgentResponse($agent, $task);

            // Mark task as completed
            $task->update([
                'status' => 'completed',
                'result_json' => $response,
                'finished_at' => now(),
            ]);

            Log::info('AgentProcessor: Task completed successfully', [
                'task_id' => $task->id,
                'agent_id' => $agent->id,
                'response_length' => strlen($response['response'] ?? ''),
            ]);

        } catch (\Throwable $e) {
            Log::error('AgentProcessor: Task failed', [
                'task_id' => $task->id,
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            $task->update([
                'status' => 'failed',
                'result_json' => ['error' => $e->getMessage()],
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Summary: Generate an agent response via LLM using grounding and routing.
     *
     * @param  Agent  $agent  The selected agent
     * @param  Task  $task  The task being processed
     * @return array ['response','confidence','agent_id','agent_name','processing_time','model_used']
     */
    private function generateAgentResponse(Agent $agent, Task $task): array
    {
        $input = $task->input_json;
        $capabilities = $agent->capabilities_json ?? [];

        // Build the agent prompt
        $prompt = $this->buildAgentPrompt($agent, $input);

        // Routing: tokens + grounding
        $tokensIn = strlen($prompt) / 4; // rough estimate
        $results = $this->grounding->retrieveTopK($input['action_payload']['question'] ?? ($input['user_query'] ?? ''), 8);
        $hitRate = $this->grounding->hitRate($results);
        $topSim = $this->grounding->topSimilarity($results);
        $role = $this->router->chooseRole((int) $tokensIn, $hitRate, $topSim);
        $modelCfg = $this->router->resolveProviderModel($role);

        // If GROUNDED, prepend retrieved snippets
        if ($role === 'GROUNDED' && ! empty($results)) {
            $ctx = "\n\nContext (retrieved):\n";
            foreach (array_slice($results, 0, 6) as $r) {
                $ctx .= "- [{$r['src']}:{$r['id']}] {$r['text']}\n";
            }
            $prompt .= $ctx;
        }

        // Make LLM call with agent-specific configuration
        try {
            $llmResponse = $this->llmClient->json('agent_response', [
                'prompt' => $prompt,
                'role' => $role,
                'provider' => $modelCfg['provider'],
                'model' => $modelCfg['model'],
                'tools' => $modelCfg['tools'],
                'reasoning' => $modelCfg['reasoning'],
            ]);

            // Log agent step
            \App\Models\AgentStep::create([
                'account_id' => $task->thread?->account_id,
                'thread_id' => $task->thread_id,
                'action_id' => null,
                'role' => $role,
                'provider' => $modelCfg['provider'],
                'model' => $modelCfg['model'],
                'step_type' => 'chat',
                'input_json' => ['prompt' => mb_substr($prompt, 0, 4000)],
                'output_json' => ['response' => $llmResponse['response'] ?? null],
                'tokens_input' => 0,
                'tokens_output' => 0,
                'tokens_total' => 0,
                'latency_ms' => 0,
                'confidence' => $llmResponse['confidence'] ?? null,
                'agent_role' => 'Worker',
                'round_no' => (int) ($input['round_no'] ?? 0),
            ]);
        } catch (\Exception $e) {
            Log::warning('Agent LLM JSON parsing failed, using fallback response', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            // Generate a helpful clarification/options message instead of an apology
            try {
                $clarify = $this->llmClient->json('clarify_email_draft', [
                    'detected_locale' => 'en_US',
                    'question' => 'Could you clarify details so we can proceed (goal, constraints, files/links)?',
                ]);
                $fallbackText = (string) ($clarify['text'] ?? 'Could you clarify your request so we can proceed?');
            } catch (\Throwable $te) {
                $fallbackText = 'Could you clarify your request so we can proceed? Please add the goal, constraints, and any needed files or links.';
            }

            $llmResponse = [
                'response' => $fallbackText,
                'confidence' => 0.6,
                'reasoning' => 'Fallback clarification due to LLM processing error',
            ];
        }

        // If empty response, still return a clarification text
        if (! isset($llmResponse['response']) || trim((string) $llmResponse['response']) === '') {
            try {
                $clarify = $this->llmClient->json('clarify_email_draft', [
                    'detected_locale' => 'en_US',
                    'question' => 'Could you clarify details so we can proceed (goal, constraints, files/links)?',
                ]);
                $llmResponse['response'] = (string) ($clarify['text'] ?? 'Could you clarify your request so we can proceed?');
                $llmResponse['confidence'] = $llmResponse['confidence'] ?? 0.6;
            } catch (\Throwable $te) {
                $llmResponse['response'] = 'Could you clarify your request so we can proceed? Please add the goal, constraints, and any needed files or links.';
                $llmResponse['confidence'] = $llmResponse['confidence'] ?? 0.6;
            }
        }

        return [
            'response' => $llmResponse['response'] ?? '',
            'confidence' => $llmResponse['confidence'] ?? 0.8,
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'processing_time' => now()->diffInSeconds($task->started_at),
            'model_used' => $modelCfg['model'],
        ];
    }

    /**
     * Generate a fallback response when LLM processing fails.
     */
    private function generateFallbackResponse(Agent $agent, array $input): string
    {
        $question = $input['action_payload']['question'] ?? 'your inquiry';
        $capabilities = $agent->capabilities_json ?? [];

        $response = "Hello! I'm {$agent->name}, {$agent->role}.";

        if (isset($capabilities['experience'])) {
            $response .= " With {$capabilities['experience']}.";
        }

        $response .= "\n\nI apologize for any technical difficulties I'm experiencing right now. ";

        // Provide some basic helpful response based on agent type
        if (str_contains(strtolower($agent->role), 'chef') || str_contains(strtolower($agent->role), 'cook')) {
            if (str_contains(strtolower($question), 'recipe') || str_contains(strtolower($question), 'cook')) {
                $response .= "For cooking questions, I always recommend focusing on fresh, quality ingredients and proper technique. Feel free to ask me about specific recipes or cooking methods when I'm back to full capacity!";
            } else {
                $response .= "I'm passionate about food and cooking. Please try your question again or ask me about Italian cuisine!";
            }
        } elseif (str_contains(strtolower($agent->role), 'technical') || str_contains(strtolower($agent->role), 'support')) {
            $response .= "For technical questions, I recommend checking documentation first and providing specific error messages when possible. I'll be happy to help troubleshoot when I'm fully operational!";
        } else {
            $response .= "I'm here to help with your question: '{$question}'. Please try again or rephrase your request.";
        }

        return $response;
    }

    /**
     * Build a comprehensive prompt for the agent.
     */
    private function buildAgentPrompt(Agent $agent, array $input): string
    {
        $capabilities = $agent->capabilities_json ?? [];
        $instructions = $input['agent_instructions'];
        $threadContext = $input['thread_context'];

        $prompt = "You are {$agent->name}, {$agent->role}.\n\n";

        // Add experience/background if available
        if (isset($capabilities['experience'])) {
            $prompt .= "With {$capabilities['experience']}.\n\n";
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
        $instructions = $input['agent_instructions'] ?? [];
        $prompt .= "INSTRUCTIONS:\n";
        foreach ($instructions as $key => $instruction) {
            if (is_string($instruction)) {
                $prompt .= "- {$instruction}\n";
            } elseif (is_string($key) && is_array($instruction)) {
                $prompt .= "- {$key}: ".implode(', ', $instruction)."\n";
            } elseif (is_string($key) && is_string($instruction)) {
                $prompt .= "- {$key}: {$instruction}\n";
            }
        }
        $prompt .= "\n";

        // Add thread context
        $threadContext = $input['thread_context'];
        $prompt .= "THREAD CONTEXT:\n";
        $prompt .= "Subject: {$threadContext['subject']}\n";
        if (! empty($threadContext['summary'])) {
            $prompt .= "Summary: {$threadContext['summary']}\n";
        }

        // Add memory context if available
        $memoryExcerpt = $this->getMemoryContext($threadContext['thread_id'], $threadContext['account_id']);
        if (! empty($memoryExcerpt)) {
            $prompt .= "\nRELEVANT MEMORIES:\n{$memoryExcerpt}\n";
        }

        $prompt .= "\nRecent conversation:\n";
        foreach ($threadContext['recent_messages'] as $message) {
            $direction = $message['direction'] === 'inbound' ? 'User' : 'Assistant';
            $prompt .= "{$direction}: {$message['content_preview']}\n";
        }
        $prompt .= "\n";

        // Add the specific task
        $prompt .= "CURRENT TASK:\n";
        $prompt .= "Action Type: {$input['action_type']}\n";

        if (isset($input['action_payload']['question'])) {
            $prompt .= "User Question: {$input['action_payload']['question']}\n";
        }

        $prompt .= "\nPlease provide a helpful, detailed response based on your expertise and role.\n";
        $prompt .= "Respond in a natural, conversational tone that matches your personality.\n";
        $prompt .= "Draw from your experience and knowledge to provide valuable insights.\n\n";

        $prompt .= 'RESPONSE:';

        return $prompt;
    }

    /**
     * Get relevant memory context for the prompt.
     */
    private function getMemoryContext(string $threadId, string $accountId): string
    {
        // Get memories from all scopes, ordered by relevance
        $memories = collect([]);

        // Thread-specific memories
        $threadMemories = $this->memoryService->retrieve(
            Memory::SCOPE_CONVERSATION,
            $threadId,
            null,
            3
        );
        $memories = $memories->merge($threadMemories);

        // Account-level memories
        $accountMemories = $this->memoryService->retrieve(
            Memory::SCOPE_ACCOUNT,
            $accountId,
            null,
            3
        );
        $memories = $memories->merge($accountMemories);

        // Format memories for prompt context
        if ($memories->isEmpty()) {
            return '';
        }

        $excerpt = '';
        foreach ($memories as $memory) {
            $value = is_array($memory->value_json) ? json_encode($memory->value_json) : $memory->value_json;
            $excerpt .= "- {$memory->key}: {$value}\n";
        }

        // Truncate if too long (keep under typical token limits)
        if (strlen($excerpt) > config('memory.max_excerpt_chars', 1200)) {
            $excerpt = substr($excerpt, 0, config('memory.max_excerpt_chars', 1200))."...\n";
        }

        return $excerpt;
    }
}
