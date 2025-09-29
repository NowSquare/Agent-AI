# CURSOR-PROMPTS.md — Agent-AI Development Playbook (Copy/Paste Ready)

> Single source of truth for **how to drive Cursor** against this repo.
> These prompts are optimized to:
> ① keep `migrate:fresh` green, ② enforce **tool-called JSON** everywhere, ③ protect security/I18N, ④ surface invisible dependencies, ⑤ produce small, reviewable commits with tests.

---

## 0) Mindset & Rules (read once)

**Mindset (systems):** see the whole → map feedback loops → validate plans → gate risky steps → log every decision.

**Non-negotiables**

* **Never add alter migrations.** If schema changes are needed, **edit create migrations** so `php artisan migrate:fresh` passes.
* **Structured LLM output == tool call.** No “please return JSON” prompts. Always add/update a **tool schema** + enforce via `LlmClient::json()` or model tool-calling.
* **Small diffs + tests.** One logical unit per commit. Add tests, logging, and docs with every feature.
* **Docs are runtime.** Keep `CURSOR-README.md` **in sync** with reality (project tree, counts, files).
* **Security posture:** SSRF-safe MCP tools only, signed links, ClamAV before extraction, rate limits, i18n.

---

## 1) Prereqs & Local Runners (quick copy)

**Laravel Boost MCP (gives Cursor deep project context)**

```bash
php artisan boost:mcp
```

**Terminals you typically need**

```bash
# A: Frontend
npm run dev
# B: Workers
php artisan horizon
# C: Webhook tunnel (dev)
ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test
# D: AV daemon
brew services start clamav   # macOS
```

**Reset (clean room)**

```bash
# Preferred single command (if available in this repo):
php artisan clear:all --force || true

# Manual:
php artisan horizon:terminate || true
brew services stop clamav || true
php artisan optimize:clear
php artisan queue:clear
php artisan horizon:clear
redis-cli FLUSHALL
php artisan migrate:fresh --seed
: > storage/logs/laravel.log
brew services start clamav
php artisan horizon
```

---

## 2) Prompt Pack (copy/paste blocks)

> Use in this order: **Plan → Execute (main)** *or* **Execute (feature+PR)** → **Demo & Verify** → **Fix-it**.
> Extra auxiliaries at the end: **Doc Sync**, **New MCP Tool**, **Prompt QA**, **Release Prep**.

---

### A) **MAKE A PLAN** — Deep scan → truth matrix → small, testable plan

```
You are a senior Laravel 12 + Blade + Tailwind/Flowbite architect and an elite Cursor co-pilot.
Your job now is ONLY planning. No code edits. No file writes.

GOAL
Produce a precise, risk-aware implementation plan for 1 subsystem (choose the most valuable) of Agent-AI,
strictly following @CURSOR-README.md as the source of truth.

OPERATING MODE (MANDATORY)
0) GIT DISCOVERY
   - list_branches
   - list_commits(default, limit=3) → record HEAD SHA/time
   - list_pull_requests(open) → note drift/conflicts

1) DEEP PROJECT ANALYSIS
   A) Project Structure Verification
      - Read “Project Structure” section in @CURSOR-README.md
      - For EACH listed path:
        get_file_contents (if file) or list_dir (if dir)
        Record purpose, main responsibilities, dependencies.
        Mark status: ✅ exists, ❌ missing, 🔄 incomplete.

   B) Database Schema (CRITICAL)
      - Read ALL create migrations (no exceptions).
      - For each table: columns, constraints, FKs, indexes, JSONB shapes.
      - Build a mental ERD. Verify Models reflect relations (casts, ulids).

   C) Core Subsystems (choose depth as needed)
      1. Email Pipeline (Webhook → ProcessInboundEmail → attachments → LLM interpret)
      2. Agent System (Coordinator, Orchestrator, Plan Validator, Arbiter)
      3. LLM Layer (LlmClient, routing, tool-enforced JSON, retries/timeouts)
      4. MCP Layer (Tool classes, SSRF guard, schemas)
      5. Auth/I18N (passwordless, DetectLanguage middleware, views)

   D) Test Coverage Map
      - Read all tests; map features → tests; note gaps.

   E) Config Walk
      - Read ALL config/* files, note env requirements, feature flags.

   F) TRUTH MATRIX (Docs vs Reality)
      | Component | Docs Say | Actually Is | Gap |
      Fill diligently with citations (file paths, migration file names).

   G) Dependency Graph (short)
      - Services ↔ Jobs ↔ Controllers ↔ Models ↔ Config ↔ Views
      - Note any cycles or fragile edges.

2) GAP RULES
   - A gap exists only if:
     (a) Not in README AND not in the repo, OR
     (b) Present but functionally incomplete after inspection.
   - Cite evidence (README section + file path + line if helpful).

3) PRIORITIZE
   P0 Core; P1 Security/Reliability; P2 Perf/Monitoring; P3 UI polish.

4) IMPLEMENTATION PLAN (2–4 hours of work)
   - 3–5 atomic tasks with acceptance criteria.
   - For existing files: list exact classes/methods to extend.
   - For new files: justify why it doesn’t already exist (point to scans).
   - Include tests to add (feature/unit/integration) and data fixtures.

5) RISK LOG (top 3)
   - Name the risk, the trigger, and your mitigation.

OUTPUT FORMAT (copy exactly)
SUMMARY
CURRENT STATE (default branch + HEAD SHA)
TRUTH MATRIX (top 10 lines)
GAPS (with evidence)
TODAY’S PLAN (3–5 tasks, each with AC)
TESTS TO ADD
RISKS & ASSUMPTIONS
CHECKPOINT FOR NEXT RUN

Begin.
```

---

### B1) **EXECUTE THE PLAN (main branch)** — For small, safe changes

```
You are a senior Laravel 12 + Blade + Tailwind/Flowbite engineer and Cursor power-user.
Implement TODAY’S PLAN with elegance, tests, and docs. Keep changes small. Commit often.

SOURCES OF TRUTH
1) @CURSOR-README.md (specs, flows)
2) Planning output from the last run
3) Actual files in repo

HARD RULES
- No new alter migrations. Edit create migrations only. `migrate:fresh` must remain green.
- Any structured LLM output must be produced via a **tool call** with a server-side schema.
- Maintain i18n + SSRF safety.

PRE-FLIGHT
- list_branches; list_commits(default, limit=1) → confirm HEAD matches planning
- If drift: STOP and print next steps (rebase/refresh plan).

PROJECT STRUCTURE HYGIENE
- When adding/modifying files:
  1) list_dir on changed dirs
  2) Update the Project Structure in @CURSOR-README.md (paths, counts, comments)
  3) Commit doc update separately: "docs: project structure sync"

IMPLEMENTATION STEPS (loop per atomic task)
- get_file_contents → create_or_update_file
- Add rich code comments for complex logic, e.g.:
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
 * - Auto-repair: insert a prerequisite action that makes the failed condition true
 * - Gate: persist plan_report + plan_valid; only run the gated final step when plan_valid=true
 * - Log: emit an activity/trace step containing the plan and the validator report
 */

TESTS + VERIFY (after each logical unit)
- php artisan test
- php artisan migrate:fresh --seed (if schema touched)
- Minimal Demo: php artisan scenario:run (ok to run once per feature)
- Log quick metrics: php artisan agent:metrics --since=7d --limit=10

COMMITS (after each unit)
<scope>: short summary
WHAT: what changed
WHY: why it matters
TESTS: how verified

OUTPUT (concise)
RUN COMMANDS
VERIFICATION (test results)
GIT COMMITS (subjects only)
NEXT STEP (next unit or done)

Begin.
```

---

### B2) **EXECUTE THE PLAN (feature branch + PR)** — For medium/large changes

```
You are a senior Laravel 12 + Blade + Tailwind/Flowbite engineer and Cursor power-user.
Implement TODAY’S PLAN on a feature branch with a PR and CI-style verification.

RULES (same as main) + branching workflow:
- Branch: feat/<scope>-<yyyymmdd>
- PR body includes: Context, Changes, Tests, Risks, Rollback plan, Doc sync diff

FLOW
- Preflight: list_branches; list_commits(default,1) (confirm)
- create_branch feat/<scope>-<yyyymmdd> from default
- Implement in small units (see EXECUTE main)
- After each unit:
  - php artisan test
  - push_files with commit messages
- create_pull_request (base=default, head=feature)
- get_pull_request_status until green (or provide next actions)
- Before merge: doc sync check vs @CURSOR-README.md; commit docs if mismatched
- merge_pull_request (squash)
- Print merge SHA and post-merge sanity (route:list | grep activity)

OUTPUT
RUN COMMANDS
PR LINK/STATUS
COMMITS
MERGE (SHA)
NEXT STEPS

Begin.
```

---

### C) **DEMO & VERIFICATION** — Run the scenario and prove end-to-end

```
You are a meticulous QA engineer. Do NOT change code. Only run and analyze.

GOAL
Run the demo. Verify multi-agent + symbolic plan validation end-to-end. Confirm visibility rules.

STEPS
0) Preflight
   php artisan optimize:clear

1) Scenario
   php artisan scenario:run

2) Metrics snapshot
   php artisan agent:metrics --since=7d --limit=20

3) Evidence via Tinker one-liners (print compact JSON; no interactive mode)

# Thread
php artisan tinker --execute='
use App\Models\Thread;
$t=Thread::latest("created_at")->first();
echo json_encode(["thread_id"=>$t?->id,"subject"=>$t?->subject], JSON_PRETTY_PRINT), PHP_EOL;'

# Contact ↔ User link
php artisan tinker --execute='
use App\Models\{Thread,EmailMessage,Contact};
$t=Thread::latest("created_at")->first();
$em=$t?->emailMessages()->latest("created_at")->first();
$contact = $t ? Contact::where("account_id",$t->account_id)->where("email",$em?->from_email)->first() : null;
$link=$contact?->contactLinks()->first();
echo json_encode(["contact_email"=>$contact?->email,"user_linked"=>(bool)$link], JSON_PRETTY_PRINT), PHP_EOL;'

# Agent run + roles
php artisan tinker --execute='
use App\Models\{AgentStep,Thread};
$t=Thread::latest("created_at")->first();
$roles=AgentStep::where("thread_id",$t->id)->selectRaw("agent_role,count(*) c,max(round_no) max_round")->groupBy("agent_role")->get();
echo $roles->toJson(JSON_PRETTY_PRINT), PHP_EOL;'

# Arbiter decision
php artisan tinker --execute='
use App\Models\{AgentStep,Thread};
$t=Thread::latest("created_at")->first();
$arb=AgentStep::where("thread_id",$t->id)->where("agent_role","Arbiter")->latest("created_at")->first();
echo json_encode(["vote_score"=>$arb?->vote_score,"decision_reason"=>$arb?->decision_reason], JSON_PRETTY_PRINT), PHP_EOL;'

# Plan validity (presence + hint)
php artisan tinker --execute='
use App\Models\{AgentStep,Thread};
$t=Thread::latest("created_at")->first();
$crit=AgentStep::where("thread_id",$t->id)->where("agent_role","Critic")->latest("created_at")->first();
$oj=$crit?->output_json ?? [];
echo json_encode(["plan_panel"=> isset($oj["plan"])||isset($oj["plan_report"]), "first_hint"=>$oj["plan_report"]["hint"]??null], JSON_PRETTY_PRINT), PHP_EOL;'

# Decision memory
php artisan tinker --execute='
use App\Models\{Memory,Thread};
$t=Thread::latest("created_at")->first();
$m=Memory::where("thread_id",$t->id)->latest("created_at")->first();
echo json_encode(["memory_type"=>$m?->type,"has_provenance"=>!empty($m?->provenance_ids??[])], JSON_PRETTY_PRINT), PHP_EOL;'

# Embedding presence
php artisan tinker --execute='
use App\Models\{EmailMessage,Thread};
$t=Thread::latest("created_at")->first();
$em=EmailMessage::where("thread_id",$t->id)->latest("created_at")->first();
echo json_encode(["email_id"=>$em?->id,"has_body_embedding"=> isset($em?->body_embedding)], JSON_PRETTY_PRINT), PHP_EOL;'

4) Routes check
php artisan route:list | grep -i activity || true

OUTPUT FORMAT
DEMO RUN SUMMARY — 2–3 sentences
CHECKLIST — ✅/❌ bullets for:
- Scenario finished
- Metrics non-zero
- Thread created
- Contact↔User link exists
- Roles present: Planner/Worker/Critic/Arbiter
- Arbiter vote_score + decision_reason present
- Plan panel present (+ hint if invalid first pass)
- Decision memory saved with provenance
- Latest email has embedding
- Activity routes registered
EVIDENCE — paste the JSON blobs
NEXT ACTIONS — only if ❌, give 1–2 line Plain fixes with exact commands.

Begin.
```

---

### D) **DEMO FIX-IT** — Minimal surgical fixes, then re-run

```
You are a senior Laravel engineer. We ran the Demo and saw ❌ items.
You will perform the smallest change(s) needed, then re-verify.

RULES
- No alter migrations. Edit create migrations and keep `migrate:fresh` green.
- Tight scope. Each fix → tests, logs, docs updated.
- Keep security & i18n intact.

INPUTS TO INSPECT
- storage/logs/laravel.log (last ~200 lines)
- Test output
- The same Tinker probes from the Demo

PROCEDURE
1) Reproduce
   php artisan optimize:clear
   php artisan test
   php artisan scenario:run

2) Classify the first failing symptom (Plain 1–2 lines)

3) Fix by bucket
   - Schema/columns: update the relevant *create* migration; `php artisan migrate:fresh`.
   - LLM routing/tools: align `config/llm.php`, `.env` role models; ensure tool schemas exist.
   - Plan validation: adjust `config/actions.php` pre/eff or `PlanValidator` hinting.
   - Activity/Routes: ensure controllers and routes registered; fix typehints.

4) Verify after each change
   php artisan migrate:fresh --seed (if schema touched)
   php artisan test
   php artisan scenario:run
   php artisan agent:metrics --since=7d --limit=20

5) Commit per fix
   fix(<scope>): short summary
   WHAT/WHY/TESTS

6) Docs
   - Sync @CURSOR-README.md (project tree + behavior notes)
   - If user-visible change: update README.md (Plain)

OUTPUT
FIX SUMMARY (Plain)
COMMITS (subjects)
DEMO CHECKLIST (re-run, now ✅)

Begin.
```

---

## 3) Auxiliary Prompts (drop-in when needed)

### A1) **DOC SYNC** — Keep `CURSOR-README.md` 100% accurate

```
You are the repository librarian. No code edits beyond docs.

GOAL
Reconcile the actual file tree with the “Project Structure (Full)” section in @CURSOR-README.md:
- Add missing paths, remove stale ones, fix counts/comments.
- Keep wording brief and plain.

STEPS
- list_dir recursively on app/, config/, database/migrations/, resources/, routes/, tests/
- Compare to README tree
- Prepare an updated tree block with inline comments (why each path exists)
- Also update “Currently Implemented / In Progress / Next” bullets if diverged

OUTPUT
- New tree block
- Bullet list of deltas (Added/Removed/Renamed)
- Commit: "docs: sync project structure (librarian pass)"

Begin.
```

---

### A2) **NEW MCP TOOL** — Add a safe, schema-bound tool (with tests)

```
You are implementing a new SSRF-safe MCP tool with strict JSON schema and tests.

REQUIREMENTS
- Location: app/Mcp/Tools/<Name>Tool.php
- Register in MCP server; SSRF guard; http/https only; 2KB body cap (if fetch)
- Provide PHP array schema in Mcp/Prompts/ToolSchemas.php (or dedicated class)
- Add unit tests for:
  - Schema validation success/failure
  - SSRF block (private IPs)
  - Max body limit (if fetch)

TASK TEMPLATE
1) Design tool: name, inputs, outputs, failure modes
2) Write schema (arguments + return shape)
3) Implement Tool class (guarded I/O)
4) Bind in MCP server
5) Tests (unit + feature if needed)
6) Update @CURSOR-README.md (Available Safe Tools list)

OUTPUT
File diffs, tests added, and a short doc snippet (Plain).

Begin.
```

---

### A3) **PROMPT QA** — Ensure tool-enforced JSON everywhere

```
You are the Prompt QA gate.

GOAL
Audit prompts and LlmClient usage. Ensure every structured output prompt is wired to a tool function with a schema and tool_choice=required.

STEPS
- Grep for prompt keys in code (config/prompts.php, Services, Mcp/Prompts/*)
- For each key that outputs JSON (e.g., action_interpret, memory_extract, attachment_summarize, thread_summarize, clarify_email_draft, csv_schema_detect, define_agents_plan, plan_symbolic_check, critic_review_step, arbiter_select, coord_synthesize_reply):
  - Verify a corresponding schema exists and is registered with LlmClient::json()
  - Verify provider/role mapping has tools=true
  - If missing, create schema + tests

OUTPUT
- Table: Prompt Key | Schema Exists | Tools Enabled | Needs Fix
- Minimal diffs to fix any “Needs Fix”
- Commit message(s)

Begin.
```

---

### A4) **RELEASE PREP** — Cut a clean, test-backed release

```
You are preparing a release.

CHECKLIST
- Run:
  php artisan optimize:clear
  php artisan test
  php artisan migrate:fresh --seed
  php artisan scenario:run
  php artisan agent:metrics --since=7d --limit=20
  php artisan route:list | grep -i activity || true

- Confirm:
  - All tests pass
  - Scenario checklist all ✅
  - README files updated (Plain notes for users)
  - .env.example comments still accurate
  - Docker compose versions unchanged or documented

OUTPUT
RELEASE NOTES (Plain)
CHANGES (bulleted)
KNOWN LIMITS (bulleted)
UPGRADE NOTES (if schema changed)
TAG SUGGESTION (e.g., v0.3.0)

Begin.
```

---

## 4) Snippets you’ll reuse (paste into code when implementing)

### 4.1 Symbolic Plan docblock (use verbatim in complex services)

```php
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
 * - Auto-repair: insert a prerequisite action that makes the failed condition true
 * - Gate: persist plan_report + plan_valid; only run the gated final step when plan_valid=true
 * - Log: emit an activity/trace step containing the plan and the validator report
 */
```

### 4.2 Tool-enforced JSON call (sketch)

```php
// Example of enforcing tool-called JSON for a prompt key:
$result = $llm->json(
    promptKey: 'action_interpret',      // schema lives in Mcp/Prompts/ToolSchemas
    vars: [
        'detected_locale'     => $locale,
        'thread_summary'      => $summary,
        'clean_reply'         => $clean,
        'attachments_excerpt' => $attachmentsExcerpt,
        'recent_memories'     => $memSubset,
    ]
);
// $result is validated args (not free text).
```

---

## 5) Quick failure playbook (Plain cheatsheet)

* **Vector dim mismatch** → `.env EMBEDDINGS_DIM` ↔ actual model; `migrate:fresh && embeddings:backfill`.
* **Model tag missing (Ollama)** → pull the tag or switch role provider/model.
* **No retrieval hits** → lower `LLM_GROUNDING_HIT_MIN` or `top_k ↑`; ensure embeddings exist.
* **ClamAV refused** → ensure daemon running on `127.0.0.1:3310`; check logs.
* **Webhook HMAC failed** → verify Basic Auth + raw body; reconfigure Postmark.
* **Split threads** → inspect headers; subject normalization; X-Thread-ID if available.
* **Invalid JSON from LLM** → you forgot tool-calling; add schema + `tool_choice=required`.