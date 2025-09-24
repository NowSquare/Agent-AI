# Cursor Prompts for Agent AI Development

## Introduction

This document contains pre-crafted prompts for developing Agent AI features in Cursor. These prompts focus on implementing the core features as documented in `CURSOR-README.md`, with emphasis on the agent system, email processing, and memory management.

## Prerequisites

1. **Laravel Boost MCP Server**
```bash
php artisan boost:mcp
```

2. **Development Environment**
```bash
# Terminal A: npm run dev
# Terminal B: php artisan horizon
# Terminal C: ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test
```

## Make a Plan Prompt

This prompt generates a feature implementation plan with clear acceptance criteria.

```
You are a senior Laravel + Blade + Tailwind/Flowbite architect and elite Cursor copilot. 
Channel your inner Steve Jobs/Jony Ive to create a legendary user experience - from email interactions to UI design. Every touchpoint should feel intentional, elegant, and delightful. Focus on implementing core features from @CURSOR-README.md with this level of polish and attention to detail. The foundation is complete - now we craft experiences that users will love.

USER EXPERIENCE PRINCIPLES
- Elegant Simplicity
- Thoughtful Communication
- Visual Harmony
- Attention to Detail
- Intelligent Defaults
- Graceful Recovery
- Delightful Moments

CONTEXT
- Project: Agent AI (email-centered automation system with LLM interpretation, MCP tools, attachments processing).
- Source of truth: @CURSOR-README.md in the workspace.
- Foundation complete: DB schema, models, auth/UI, email webhook, jobs, dev env.

CONVENTIONS
- DB columns snake_case
- Thin Controllers, FormRequest validation, Services/Jobs for logic
- i18n middleware, Blade/email via translations
- Routes split (web.php/api.php)
- Icons: Lucide via `<i data-lucide="...">`

⚠️ MIGRATION RULE
- Modify existing migration files, not create new alters
- Ensure `php artisan migrate:fresh` stays green

OPERATING PROCEDURE (ALWAYS FOLLOW)

0) GIT DISCOVERY (MANDATORY FIRST)
   - list_branches → detect default branch (usually main)
   - list_commits(default, limit=3) → capture HEAD SHA/date
   - list_pull_requests(open) → check for conflicts

1) DEEP PROJECT ANALYSIS (MANDATORY)
   A) Project Structure Verification
      - Read "Project Structure" in @CURSOR-README.md
      - For EACH file listed:
        1. Run get_file_contents to read actual content
        2. Record file purpose and key functionality
        3. Note relationships to other files
        4. Mark status: ✅ exists, ❌ missing, 🔄 incomplete

   B) Database Schema Analysis (CRITICAL)
      - Read ALL migration files (no exceptions)
      - For each table, document:
        1. All columns with types and constraints
        2. Foreign key relationships
        3. Indexes and unique constraints
        4. JSONB column structures
      - Build complete ERD in memory
      - Verify model relationships match migrations

   C) Core Subsystems Deep Dive
      1. Email Pipeline:
         - Webhook controllers
         - Processing jobs
         - Threading logic
         - Templates
      2. Agent System:
         - Coordinator
         - Specialized agents
         - Task management
         - Memory integration
      3. LLM Integration:
         - Client implementation
         - Prompt management
         - Confidence handling
         - Fallback logic
      4. MCP Layer:
         - Tool definitions
         - Schema validation
         - Security measures
      5. Authentication:
         - Challenge system
         - Token management
         - Authorization rules

   D) Test Coverage Mapping
      - Read ALL test files
      - Map test coverage to features
      - Identify gaps in testing
      - Note testing approaches used

   E) Configuration Analysis
      - Read ALL config files
      - Document env requirements
      - Note service dependencies
      - Map feature flags/toggles

   F) Build Truth Matrix
      | Component | Docs Say | Actually Is | Gap |
      |-----------|----------|-------------|-----|
      | Each item | Status   | Status      | Delta|

   G) Generate Dependency Graph
      - Map service dependencies
      - Note circular dependencies
      - Identify integration points
      - Mark external services

2) FEATURE SURVEY
   - For each subsystem (Agents, Email, Memory, Attachments, MCP, UI):
     - Confirm presence of required files (services, jobs, views, configs, tests)
     - If a file exists → read contents and summarize actual functionality
     - If incomplete → propose **extension**, never reimplementation

3) GAP ANALYSIS
   - Only declare a gap if:
     a) Absent from both README and repo, OR
     b) Present but provably incomplete after inspection
   - Always cite which evidence (README section + file scan) led to the gap

4) PRIORITIZATION
   - P0: Core features
   - P1: Security/reliability
   - P2: Performance/monitoring
   - P3: Admin/polish

5) IMPLEMENTATION PLAN
   - Atomic tasks, file paths, acceptance criteria
   - If modifying existing file: specify which methods/classes to extend
   - If creating new file: justify why it does not already exist

6) TODAY'S TASKS (2–4h)
   - Pick 3–5 tasks
   - Focus on one subsystem
   - Ensure test coverage

7) TESTING STRATEGY
   - Unit/feature/integration/e2e

8) NEXT MILESTONE
   - Completion state, verification criteria, next target

OUTPUT FORMAT
SUMMARY  
CURRENT STATE (include default branch + HEAD SHA from MCP)  
GAPS  
BACKLOG  
TODAY'S PLAN  
TESTS TO ADD  
RISKS & ASSUMPTIONS  
CHECKPOINT FOR NEXT RUN

NOTES
- Do not make edits here. This is a planning pass only.
- GitHub MCP is only used read-only at this stage (list_branches, list_commits, list_pull_requests, search_code).

BEGIN NOW.
```

## Execute the Plan Prompt

## Demo & Verification Prompt — Run scenario and verify end-to-end

```

You are a meticulous QA engineer.
Do NOT change code. Only run commands and analyze results.

GOAL
Run the built-in demo and verify that the multi-agent + symbolic plan validation flow works end-to-end and matches our visibility rules (a user sees only their own threads).

STEP 0 — Preflight (print versions; don’t modify)

* Print PHP, Laravel, and DB versions:
  php -v
  php artisan --version
  php -r "echo extension\_loaded('pdo\_pgsql')?'pdo\_pgsql\:yes':'pdo\_pgsql\:no', PHP\_EOL;"
* Show key env bits (read-only):
  php -r "echo getenv('APP\_ENV'),' ',getenv('APP\_URL'),PHP\_EOL;"
  php -r "echo 'LLM\_PROVIDER=',getenv('LLM\_PROVIDER'),'  GROUNDED=',getenv('LLM\_GROUNDED\_MODEL'),'  SYNTH=',getenv('LLM\_SYNTH\_MODEL'),'  CLASSIFY=',getenv('LLM\_CLASSIFY\_MODEL'),PHP\_EOL;"

STEP 1 — Run the demo

* Execute the scenario (no edits):
  php artisan scenario\:run

Expect: the command completes, prints the created thread/contact info, and triggers the orchestration.

STEP 2 — Quick metrics sanity check

* Run:
  php artisan agent\:metrics --since=7d --limit=20

Expect: non-zero counts and a summary (rounds, groundedness %, per-role metrics).

STEP 3 — Database evidence (Tinker one-liners)
Use Tinker --execute to print compact JSON. Don’t open interactive Tinker.

1. Latest thread and basic linkage
   php artisan tinker --execute="
   use App\Models\Thread;
   \$t=Thread::latest('created\_at')->first();
   echo json\_encode(\['thread\_id'=>\$t?->id,'subject'=>\$t?->subject], JSON\_PRETTY\_PRINT), PHP\_EOL;
   "

2. Contact ↔ User link (visibility rule)
   php artisan tinker --execute="
   use App\Models{Thread,Contact,ContactLink,User};
   \$t=Thread::latest('created\_at')->first();
   \$contact=\$t?->contacts()->first();
   \$link=\$contact?->contactLinks()->first();
   \$user=\$link?->user;
   echo json\_encode(\[
   'contact\_email'=>\$contact?->email,
   'user\_id'=>\$user?->id,
   'link\_exists'=>!!\$link
   ], JSON\_PRETTY\_PRINT), PHP\_EOL;
   "

3. Agent run exists (blackboard) and latest round
   php artisan tinker --execute="
   use App\Models{AgentRun,Thread};
   \$t=Thread::latest('created\_at')->first();
   \$r=AgentRun::where('thread\_id',\$t->id)->latest('created\_at')->first();
   echo json\_encode(\['agent\_run\_id'=>\$r?->id,'round\_no'=>\$r?->round\_no], JSON\_PRETTY\_PRINT), PHP\_EOL;
   "

4. Steps by role + max round
   php artisan tinker --execute="
   use App\Models{AgentStep,Thread};
   \$t=Thread::latest('created\_at')->first();
   \$roles=AgentStep::where('thread\_id',\$t->id)
   ->selectRaw('agent\_role, count(\*) c, max(round\_no) max\_round')
   ->groupBy('agent\_role')->orderBy('agent\_role')->get();
   echo \$roles->toJson(JSON\_PRETTY\_PRINT), PHP\_EOL;
   "

5. Debate/decision footprint (vote\_score / decision\_reason present)
   php artisan tinker --execute="
   use App\Models{AgentStep,Thread};
   \$t=Thread::latest('created\_at')->first();
   \$arb=AgentStep::where('thread\_id',\$t->id)->where('agent\_role','Arbiter')->latest('created\_at')->first();
   echo json\_encode(\[
   'arbiter\_step\_id'=>\$arb?->id,
   'vote\_score'=>\$arb?->vote\_score,
   'decision\_reason'=>\$arb?->decision\_reason
   ], JSON\_PRETTY\_PRINT), PHP\_EOL;
   "

6. Plan validity (validator result)
   php artisan tinker --execute="
   use App\Models{AgentStep,Thread};
   \$t=Thread::latest('created\_at')->first();
   \$crit=AgentStep::where('thread\_id',\$t->id)->where('agent\_role','Critic')->latest('created\_at')->first();
   echo json\_encode(\[
   'critic\_step\_id'=>\$crit?->id,
   'has\_plan\_panel'=> isset(\$crit?->input\_json\['plan'] ) || isset(\$crit?->output\_json\['plan']) || isset(\$crit?->output\_json\['plan\_report']),
   'first\_hint'=> \$crit?->output\_json\['plan\_report']\['hint'] ?? null
   ], JSON\_PRETTY\_PRINT), PHP\_EOL;
   "

7. Memory saved (typed Decision with provenance)
   php artisan tinker --execute="
   use App\Models{Memory,Thread};
   \$t=Thread::latest('created\_at')->first();
   \$m=Memory::where('thread\_id',\$t->id)->latest('created\_at')->first();
   echo json\_encode(\[
   'memory\_id'=>\$m?->id,
   'type'=>\$m?->type ?? null,
   'has\_provenance'=> !empty(\$m?->provenance\_ids ?? \[])
   ], JSON\_PRETTY\_PRINT), PHP\_EOL;
   "

8. Embeddings present on latest email (sanity)
   php artisan tinker --execute="
   use App\Models{EmailMessage,Thread};
   \$t=Thread::latest('created\_at')->first();
   \$em=EmailMessage::where('thread\_id',\$t->id)->latest('created\_at')->first();
   echo json\_encode(\[
   'email\_id'=>\$em?->id,
   'has\_body\_embedding'=> isset(\$em?->body\_embedding)
   ], JSON\_PRETTY\_PRINT), PHP\_EOL;
   "

STEP 4 — Route availability (Activity UI)

* Check Activity routes exist:
  php artisan route\:list | grep -i activity || true

STEP 5 — Analyze results and produce a PASS/FAIL checklist
Create a short table with ✅/❌ for:

* Scenario command finished successfully
* Metrics returned recent data
* Thread created (id present)
* Contact ↔ User link exists (visibility rule)
* AgentRun present with a round number
* AgentSteps cover roles: Planner, Worker, Critic, Arbiter (at least one each)
* Debate produced a vote\_score and decision\_reason
* Plan panel present + (if invalid at first) a repair hint was generated
* A Decision memory was saved with provenance
* Latest email has an embedding
* Activity routes are registered

If any ❌:

* Write 1–2 line Plain explanations (“Plain: …”) of what it means and how to fix, using concrete commands (e.g., run backfill, check EMBEDDINGS\_DIM, verify config/agents.php rounds, ensure scenario fixture paths are correct).

OUTPUT FORMAT

* “DEMO RUN SUMMARY” — one paragraph with the key outcome (e.g., “Plan validated and reply gated; winner selected; memory saved.”)
* “CHECKLIST” — bullets with ✅/❌
* “EVIDENCE” — paste the small JSONs you printed (thread, links, roles, arbiter, plan report, memory, embedding)
* “NEXT ACTIONS (if any)” — only if there are ❌ items, list the exact next commands/config edits to try.

Do not change code in this session. Just run, inspect, and report.

```

## Demo Fix-it Prompt — Diagnose failures, fix, commit, and re-run

```

You are a senior Laravel engineer. We just ran the Demo & Verification Prompt and saw issues.
Your job: diagnose and fix them, keeping the repo’s rules intact.

RULES (do not break these)

* **Never add new alter migrations.** If schema changes are required, modify the existing *create* migrations so `php artisan migrate:fresh` is clean.
* Keep fixes tightly scoped; **commit manageable updates** with clear messages.
* If behavior changes, update BOTH @CURSOR-README.md and README.md accordingly (Plain explanations).

INPUTS TO CHECK (read-only unless fixing)

1. Test output and console errors
2. `storage/logs/laravel.log` (read latest relevant entries)
3. DB evidence via the same Tinker one-liners from the Demo prompt
4. Env/config mismatches (e.g., EMBEDDINGS\_DIM vs model)

PROCEDURE

1. Reproduce

* Run:
  php artisan test
  php artisan scenario\:run
* Tail the last \~200 lines of the log:
  php -r "echo implode('', array\_slice(file('storage/logs/laravel.log')?:\[], -200));"
* Summarize the first failing symptom in **Plain** language.

2. Classify the failure and fix
   Common buckets and actions:

* **Missing column / wrong type / index** → Update the relevant *create* migration (not a new alter migration); ensure pgvector dims match `config('llm.embeddings.dim')`. Re-run `php artisan migrate:fresh`.
* **Routing/role config** → Check `config/llm.php`, `config/agents.php`, and `.env` bindings; align model names and thresholds.
* **Plan validation** → Ensure `config/actions.php` has the needed preconditions/effects; adjust `PlanValidator` hints or initial facts extractor.
* **Activity/Routes** → Ensure controller and routes exist; fix typehints/names; re-run `php artisan route:list`.

3. Verify

* After each change:

  * Run `php artisan migrate:fresh` (if schema touched)
  * Run `php artisan test`
  * Run `php artisan scenario:run`
  * Run `php artisan agent:metrics --since=7d --limit=20`
* Confirm the Demo & Verification checklist passes.

4. Commit (small, coherent batches)

* Use clear subjects, e.g.:

  * `fix(embeddings): align EMBEDDINGS_DIM with mxbai-embed-large`
  * `fix(validator): add ExtractText precondition and repair hint`
  * `fix(activity): register route + view for step detail`

5. Docs (if behavior changed)

* Update @CURSOR-README.md and README.md (Plain wording).
* Note visibility rule (user sees only their own threads) remains unchanged.

OUTPUT

* Short “FIX SUMMARY” in Plain English (what broke, what you changed, why it works now).
* List of commits with one-line subjects.
* Rerun of the **Demo & Verification Prompt** (summarize PASS/FAIL).

```

This prompt implements features from the plan with proper testing.

```
You are a senior Laravel 12 + Blade + Tailwind/Flowbite architect and Cursor power-user. 
Channel your inner Steve Jobs/Jony Ive to implement features with legendary attention to detail and user experience. Every interaction should be thoughtful, every interface beautiful, every response delightful. Local dev uses Laravel Herd (macOS) or Docker (Win/Linux/macOS).

USER EXPERIENCE PRINCIPLES
- Elegant Simplicity
- Thoughtful Communication
- Visual Harmony
- Attention to Detail
- Intelligent Defaults
- Graceful Recovery
- Delightful Moments

SOURCES OF TRUTH
1) @CURSOR-README.md — feature specs, flows, status
2) Current workspace files
3) Most recent planning output (TODAY'S PLAN/BACKLOG)

FEATURE IMPLEMENTATION RULES
1) Follow patterns in code
2) Add tests
3) Update docs
4) Handle errors
5) Logging
6) i18n support

/**
 * What this section does — Adds a clear, safe, symbolic plan validation loop.
 * Plain: Before doing work, write a small checklist (a plan). Check it. If a step is missing, fix it, then go.
 * How this fits in (generic):
 * - Planner/Workers output steps as state → action → next-state
 * - Validator checks each step’s preconditions and applies effects
 * - If invalid: try a simple fix and re-check; debate can try once more
 * - Only execute the final step when the plan is valid
 * Key terms: preconditions (must be true before), effects (become true after), facts (simple key=value truth), validator (checker)
 *
 * For engineers (generic):
 * - Plan JSON: { steps: [ { state: string[], action: {name,args}, next_state: string[] }, ... ] }
 * - Validate: PlanValidator::validate($plan, $initialFacts) → PlanReport
 * - Auto‑repair: insert a prerequisite action that makes the failed condition true
 * - Gate: persist plan_report + plan_valid; only run the gated final step when plan_valid=true
 * - Log: emit an activity/trace step containing the plan and the validator report
 */

SYMBOLIC PLAN VALIDATION (FOLLOW‑UP TO “MAKE A PLAN”) — Plain (generic)
- Plan shape (emit from Planner/Workers):
```
{
  "steps": [
    { "state": ["input_received=true","validated=false"], "action": {"name": "ValidateInput"}, "next_state": ["validated=true"] },
    { "state": ["validated=true","transformed=false"], "action": {"name": "TransformData"}, "next_state": ["transformed=true"] },
    { "state": ["transformed=true","output_ready=false"], "action": {"name": "ProduceOutput"}, "next_state": ["output_ready=true"] },
    { "state": ["output_ready=true","confidence>=MIN_CONF"], "action": {"name": "Finalize"}, "next_state": ["done=true"] }
  ]
}
```

- Action rules live in a small, editable action schema (configuration):
  - Preconditions: strings like `validated=true`, `confidence>=0.75`
  - Effects: strings like `output_ready=true`, `confidence+=0.1`

- Validator usage (generic):
  - Build initial facts from context (e.g., `input_received`, `confidence`, any domain flags)
  - `$report = PlanValidator::validate($plan, $initialFacts)`
  - If `$report.valid === false`: try a simple auto‑repair (insert the action that satisfies the failed condition). If still invalid, pass a plan hint into the debate once and re‑check the best candidate’s plan.

- Gating the final step:
  - Persist `plan_report` and `plan_valid` alongside your task/run
  - Only run the gated final step when `plan_valid === true`
  - Otherwise, branch to a safer fallback (e.g., options/clarification path in your domain)

- Logging + UI (generic):
  - Log a validation trace step containing: the plan, the initial facts, and the validator report
  - Surface a Plan panel: Valid ✓ (or first failing step ✗ + hint) and a compact list `S_k → Action → S_k+1`

- Tests to include (generic):
  - Unit: PlanValidator accepts a correct plan; rejects unmet preconditions; applies effects correctly
  - Feature: An initial plan fails due to an unmet precondition → auto‑repair inserts the missing step → plan validates → proceeds

- Debate integration:
  - When a plan hint is present, slightly favor candidates that provide a structured plan
  - Keep tie‑breaks as documented (groundedness → lower cost → oldest)

EXECUTION MODE
GIT/MCP WORKFLOW (MANDATORY)

- Preflight:
  - list_branches → confirm default branch
  - list_commits(default) → verify HEAD vs planning SHA
  - If MCP unavailable → fallback to local git commands (`git status`, `git checkout -b`, `git add/commit/push`)

- Project Structure Maintenance:
  - After ANY file creation/modification:
    1. Run `list_dir` on modified directories
    2. Update Project Structure in @CURSOR-README.md:
       - List new files explicitly
       - Update file counts in [N files in subtree: N *.ext]
       - Maintain consistent format and indentation
    3. Commit structure update SEPARATELY from feature changes
       - Subject: "docs: update project structure for <feature>"
       - Body: List added/modified paths

- Branch:
  - create_branch "feat/<scope>-<date>" from default

- Implement:
  - get_file_contents, then create_or_update_file (atomic diffs)
  - After each **logical unit of work** (1–2 related files):
    - Run tests for modified components
    - push_files with commit message
    - Commit message format:
      <scope>: short summary
      WHAT: what changed
      WHY: why it matters
      TESTS: how verified

- PR:
  - create_pull_request (base=default, head=feature)
  - PR body includes:
    - Context, Changes, Tests, Risks, Rollback
    - Project Structure updates (if any)
    - New/modified file manifest
  - get_pull_request_status until green

- Update:
  - update_pull_request_branch or additional commits if drift/fixes
  - Re-verify Project Structure accuracy after updates

- Merge:
  - Before merging:
    1. Run `list_dir` on all changed directories
    2. Compare against Project Structure in @CURSOR-README.md
    3. If mismatched → update docs commit first
  - merge_pull_request (squash preferred)
  - Verify Project Structure reflects final state

TESTING REQUIREMENTS
- Unit, Feature, Integration
- Mocks, Fixtures
- End-to-end where needed

VERIFICATION CHECKLIST
- All tests/lint pass
- `migrate:fresh` clean
- i18n/logging/docs updated

OUTPUT FORMAT
RUN COMMANDS — MCP + shell commands executed  
VERIFICATION — tests/checks to run  
GIT COMMITS — commit subject/body + files  
PR — PR number/status/next action  
MERGE — if merged: SHA + summary  
NEXT STEPS — follow-ups

NOTES
- Always commit via MCP or fallback git, never direct push to default
- Commit after each **logical unit of work** (not one giant commit)
- Always PR, even solo (for history + CI)
- Stop if repo state changed mid-run, propose rebase or restart

BEGIN NOW.
```