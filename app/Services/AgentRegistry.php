<?php
/**
 * What this file does — Keeps track of available agents and scores who should work on a task.
 * Plain: A phonebook of agents with a simple “who fits best” calculator.
 * How this fits in:
 * - Orchestrator asks for the top K workers per task
 * - Coordinator uses it for simple/one‑agent cases
 * - Reliability is updated over time from wins
 * Key terms:
 * - capability_match: how well the agent’s tags match the task
 * - cost_hint: a rough cost/time signal; cheaper is better
 * - reliability: moving average success rate (0..1)
 *
 * For engineers:
 * - Inputs: account, action/task description
 * - Outputs: ranked agents with utility scores
 * - Side effects: updateReliability()
 */

namespace App\Services;

use App\Models\Agent;
use App\Models\Account;
use Illuminate\Support\Collection;

/**
 * Purpose: Score and shortlist agents for a task.
 * Responsibilities:
 * - Compute utility using weights from config/agents.php
 * - Return top‑K agents per task
 * - Maintain rolling reliability
 * Collaborators: Agent model, Orchestrator
 */
class AgentRegistry
{
    /**
     * Compute capability match score [0,1] based on task keywords vs agent capability tags.
     */
    private function capabilityMatchScore(Agent $agent, array $task): float
    {
        $caps = $agent->capabilities_json ?? [];
        $tags = array_map('strtolower', array_merge(
            $caps['keywords'] ?? [],
            $caps['domains'] ?? [],
            $caps['expertise'] ?? [],
            $caps['action_types'] ?? []
        ));

        $taskText = strtolower(($task['description'] ?? '') . ' ' . ($task['question'] ?? ''));
        $hits = 0; $total = max(1, count($tags));
        foreach ($tags as $t) { if ($t && str_contains($taskText, $t)) { $hits++; } }
        return min(1.0, $hits / $total);
    }

    /**
     * Score a bid (utility) for an agent on a given task.
     * utility = w_cap * capability_match + w_cost * (1/cost_hint_norm) + w_rel * reliability
     */
    public function scoreBid(Agent $agent, array $task): float
    {
        $w = config('agents.scoring.weights');
        $cap = $this->capabilityMatchScore($agent, $task);
        $costHint = max(1, (int)($agent->cost_hint ?? 100));
        $costNorm = min(1.0, 1000 / $costHint); // cheaper => closer to 1
        $rel = max(0.0, min(1.0, (float)($agent->reliability ?? 0.8)));

        $utility = ($w['capability_match'] * $cap)
                 + ($w['expected_cost']   * $costNorm)
                 + ($w['reliability']     * $rel);
        return round($utility, 4);
    }

    /**
     * Update rolling reliability using a simple moving average.
     * $won = 1.0 for winner, 0.0 for loss; $samples caps the window implicitly.
     */
    public function updateReliability(Agent $agent, float $won): void
    {
        $n = (int)($agent->reliability_samples ?? 0);
        $r = (float)($agent->reliability ?? 0.8);
        $newR = (($r * $n) + $won) / max(1, $n + 1);
        $agent->update([
            'reliability' => round(max(0.0, min(1.0, $newR))),
            'reliability_samples' => $n + 1,
        ]);
    }
    /**
     * Get all available agents for an account.
     */
    public function getAvailableAgents(Account $account): Collection
    {
        return $account->agents()->get();
    }

    /**
     * Find the best agent for a given action and context.
     */
    public function findBestAgentForAction(Account $account, array $actionData, array $context = []): ?Agent
    {
        $agents = $this->getAvailableAgents($account);

        if ($agents->isEmpty()) {
            return $this->getDefaultAgent($account);
        }

        // Score each agent based on relevance to the action
        $scoredAgents = $agents->map(function (Agent $agent) use ($actionData, $context) {
            return [
                'agent' => $agent,
                'score' => $this->calculateAgentScore($agent, $actionData, $context),
            ];
        });

        // Return the highest scoring agent
        $bestMatch = $scoredAgents->sortByDesc('score')->first();

        return $bestMatch ? $bestMatch['agent'] : $this->getDefaultAgent($account);
    }

    /**
     * Calculate how well an agent matches an action.
     */
    private function calculateAgentScore(Agent $agent, array $actionData, array $context): float
    {
        $score = 0.0;
        $capabilities = $agent->capabilities_json ?? [];

        // Action type matching
        $actionType = $actionData['type'] ?? '';
        if (in_array($actionType, $capabilities['action_types'] ?? [])) {
            $score += 2.0;
        }

        // Keyword/domain matching
        $keywords = $this->extractKeywords($actionData, $context);
        foreach ($keywords as $keyword) {
            if ($this->agentHasKeyword($agent, $keyword)) {
                $score += 1.0;
            }
        }

        // Role/expertise matching
        $role = $agent->role ?? '';
        if ($this->roleMatchesAction($role, $actionData)) {
            $score += 1.5;
        }

        // Language matching
        $detectedLocale = $context['locale'] ?? 'en';
        if (in_array($detectedLocale, $capabilities['languages'] ?? ['en'])) {
            $score += 0.5;
        }

        return $score;
    }

    /**
     * Rank agents for a concrete subtask and return the top-K by utility.
     * Each item: ['agent' => Agent, 'utility' => float]
     */
    public function topKForTask(Account $account, array $task, int $k): array
    {
        $agents = $this->getAvailableAgents($account);
        $ranked = $agents->map(function (Agent $agent) use ($task) {
            return [
                'agent' => $agent,
                'utility' => $this->scoreBid($agent, $task),
            ];
        })->sortByDesc('utility')->take($k)->values()->all();

        return $ranked;
    }

    /**
     * Extract relevant keywords from action and context.
     */
    private function extractKeywords(array $actionData, array $context): array
    {
        $keywords = [];

        // From action payload
        if (isset($actionData['payload_json'])) {
            $payload = $actionData['payload_json'];
            if (isset($payload['question'])) {
                $keywords = array_merge($keywords, $this->extractKeywordsFromText($payload['question']));
            }
        }

        // From context
        if (isset($context['thread_summary'])) {
            $keywords = array_merge($keywords, $this->extractKeywordsFromText($context['thread_summary']));
        }

        return array_unique($keywords);
    }

    /**
     * Extract keywords from text using simple heuristics.
     */
    private function extractKeywordsFromText(string $text): array
    {
        $text = strtolower($text);

        // Common domain keywords
        $domainKeywords = [
            'recipe', 'cooking', 'chef', 'food', 'italian', 'pasta', 'pizza',
            'legal', 'lawyer', 'contract', 'court', 'lawsuit',
            'programming', 'code', 'developer', 'software', 'tech',
            'health', 'medical', 'doctor', 'patient', 'treatment',
            'finance', 'money', 'investment', 'banking', 'tax',
        ];

        return array_filter($domainKeywords, function ($keyword) use ($text) {
            return str_contains($text, $keyword);
        });
    }

    /**
     * Check if agent has a specific keyword in capabilities.
     */
    private function agentHasKeyword(Agent $agent, string $keyword): bool
    {
        $capabilities = $agent->capabilities_json ?? [];

        $keywords = array_merge(
            $capabilities['keywords'] ?? [],
            $capabilities['domains'] ?? [],
            $capabilities['expertise'] ?? []
        );

        return in_array(strtolower($keyword), array_map('strtolower', $keywords));
    }

    /**
     * Check if agent role matches the action.
     */
    private function roleMatchesAction(string $role, array $actionData): bool
    {
        $role = strtolower($role);
        $actionType = strtolower($actionData['type'] ?? '');

        $roleMappings = [
            'chef' => ['info_request'],
            'cook' => ['info_request'],
            'lawyer' => ['info_request', 'approve', 'reject'],
            'developer' => ['info_request'],
            'doctor' => ['info_request'],
            'financial advisor' => ['info_request'],
        ];

        return in_array($actionType, $roleMappings[$role] ?? []);
    }

    /**
     * Get or create a default agent for the account.
     */
    private function getDefaultAgent(Account $account): Agent
    {
        return Agent::firstOrCreate(
            ['account_id' => $account->id, 'name' => 'General Assistant'],
            [
                'role' => 'General Assistant',
                'capabilities_json' => [
                    'action_types' => ['info_request', 'approve', 'reject', 'revise'],
                    'keywords' => ['general', 'help', 'information'],
                    'languages' => ['en'],
                    'expertise' => ['general_assistance'],
                ],
            ]
        );
    }

    /**
     * Create a sample Italian chef agent for testing.
     */
    public function createSampleAgents(Account $account): void
    {
        Agent::firstOrCreate(
            ['account_id' => $account->id, 'name' => 'Chef Mario'],
            [
                'role' => 'Master Italian Chef',
                'capabilities_json' => [
                    'action_types' => ['info_request'],
                    'keywords' => ['recipe', 'cooking', 'italian', 'pasta', 'pizza', 'chef', 'food'],
                    'domains' => ['culinary', 'italian_cuisine', 'cooking'],
                    'expertise' => ['italian_cooking', 'recipes', 'food_preparation'],
                    'languages' => ['en', 'it'],
                    'personality' => 'passionate, authentic, traditional Italian cooking',
                    'experience' => '25 years as head chef in Milan restaurants',
                ],
            ]
        );

        Agent::firstOrCreate(
            ['account_id' => $account->id, 'name' => 'Tech Support'],
            [
                'role' => 'Technical Support Specialist',
                'capabilities_json' => [
                    'action_types' => ['info_request'],
                    'keywords' => ['technical', 'support', 'software', 'hardware', 'computer', 'programming'],
                    'domains' => ['technology', 'software', 'hardware'],
                    'expertise' => ['technical_support', 'troubleshooting', 'programming'],
                    'languages' => ['en'],
                    'personality' => 'helpful, patient, methodical',
                ],
            ]
        );
    }
}
