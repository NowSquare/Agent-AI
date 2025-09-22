# Cursor Prompts for Agent AI Development

## Start the dev loop
> Note: Dev uses **Postmark** for inbound email webhooks.
> Configure Postmark webhook to point to `/webhooks/inbound-email`.
> Use ngrok or similar to expose local server for webhook testing.
---
* Terminal A: `npm run dev`
  (Vite dev server, hot reloads)
* Terminal B: `php artisan horizon`
  (or `php artisan queue:work` if you don't want Horizon)
* Terminal C: `php artisan boost:mcp`
  (so Cursor sees routes, schema, Tinker, logs)
* Terminal D: Webhook testing
  - Start ngrok: `ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test`
  - Copy the ngrok HTTPS URL (e.g., `https://abc123.ngrok-free.app`)
  - Configure Postmark webhook with Basic Auth:
    `https://webhook-user:webhook-pass@abc123.ngrok-free.app/webhooks/inbound-email`
Run migrations when needed:
```bash
php artisan migrate
```

## Introduction
This document (`CURSOR-PROMPTS.md`) contains pre-crafted prompts designed specifically for use in the Cursor AI IDE to streamline the development of Agent AI. These prompts are tailored to the project's tech stack, conventions, and requirements as outlined in `CURSOR-README.md`. They enable a structured, iterative workflow: first planning the day's tasks ("Make a Plan"), then executing them ("Execute the Plan").

## Laravel Boost & MCP Prerequisites
Before running any of the prompts below, make sure **both** Laravel Boost AND the Agent AI MCP server are running.

### 1. Laravel Boost (for Cursor Integration)
```bash
php artisan boost:mcp
```

In Cursor → **Settings → MCP**, add it as a server:
- Command: `php`
- Args: `artisan`, `boost:mcp`
- Working directory: project root
This unlocks Boost tools (DB schema, routes, Artisan, logs, docs).

### 2. Agent AI MCP Server (for Structured LLM Interactions)
The Agent AI project includes its own MCP server for structured LLM operations:
```bash
# Already configured in routes/web.php at /mcp/ai
# Tools: ActionInterpretationTool, AgentSelectionTool, ResponseGenerationTool
# Prompts: DefineAgentsPrompt, OrchestrateComplexRequestPrompt
```
**Cursor should use Laravel MCPs for structured LLM queries and responses** instead of free-form text parsing. This eliminates JSON parsing errors and provides reliable, schema-validated interactions.

### Why These Prompts?
- **Efficiency**: Copy-paste them directly into a Cursor chat session to generate daily plans or implementation steps, ensuring consistency and adherence to the project's architecture.
- **Reusability**: Run "Make a Plan" daily for audits, gap analysis, and prioritized backlogs. Use "Execute the Plan" to implement based on the latest plan.
- **Best Practices**: Prompts emphasize security (e.g., SSRF prevention), performance (e.g., LLM timeouts), i18n, and clean code patterns (Services/Jobs/Policies). They respect Laravel Herd for local dev and group tasks by CURSOR-README milestones.
- **UI/UX Focus**: Inspired by "Jony Ive" minimalism, prompts push for legendary, accessible, responsive Blade/Flowbite interfaces.
- **Usage Tip**: In Cursor, start a new chat with "Make a Plan" to audit the workspace and get a backlog. Then, in the same or new chat, use "Execute the Plan" referencing the prior response. Always have `@CURSOR-README.md` open in the workspace for context. Reference `@CURSOR-CONTEXT.md` for shared stack and rules.
If you need to adapt these prompts (e.g., for new features), version them here with dates.

## Make a Plan Prompt
This prompt generates a high-level audit, gap analysis, and bite-sized backlog without code. Use it first each day.

```
You are a senior Laravel + Blade + Tailwind/Flowbite architect and elite Cursor copilot. Act as a blunt tech lead: audit, analyze gaps, and create bite-sized plans. NO code generation. Focus on [FOCUS_PHASE] if specified (e.g., Inbound/Threading); otherwise, full MVP. Prioritize UI/UX minimalism.

[Insert or reference @CURSOR-CONTEXT.md here for shared context: stack, conventions, migration rule, etc.]

OPERATING PROCEDURE
1) SUMMARY: Paraphrase @CURSOR-README.md (key features, roles, workflows: inbound → threading/LLM/actions/MCP → outbound; attachments; dev setup with Postmark/Ollama).
2) CURRENT STATE: Scan repo (key paths only). Table: | Category | Present Files/Configs | Missing |
   - Include: composer/package.json, .env.*, app/*, config/*, database/*, routes/*, resources/views/*, Horizon/Docker/ClamAV stubs.
3) GAPS & RISKS: Vs. CURSOR-README. Bullet gaps in domain (models/migrations/policies), email (Postmark webhook), LLM/MCP (tools/schemas), attachments (pipeline), auth (passwordless), queues (Horizon), frontend (Blade pages), quality (tests/linters). Risks: version drifts, missing env keys (e.g., AGENT_MAIL, INBOUND_WEBHOOK_SECRET, LLM_*), security (SSRF/ClamAV), perf (timeouts/limits).
4) PRIORITIZED BACKLOG: Numbered, phased tasks (e.g., Phase 1: Inbound). Each: Title, Criteria, Files/Commands/Deps. Use table if >5 tasks. Verify/extend existing files.
5) TODAY’S PLAN: 3-5 unblocking tasks (e.g., schema + webhook + first Blade). Sequence with criteria.
6) TESTS: 1-2 PHPUnit names per today’s task (no code).
7) CHECKPOINT: Artifacts for next run (tables/routes/pages/configs).

OUTPUT: Use exact markdown sections: SUMMARY • CURRENT STATE • GAPS & RISKS • BACKLOG • TODAY’S PLAN • TESTS • CHECKPOINT

BEGIN.
```

## Execute the Plan Prompt
This prompt implements the plan from the previous response, generating shell commands and verifications. Use it after running "Make a Plan".

```
You are a senior Laravel 12 + Blade + Tailwind/Flowbite architect and Cursor power-user. Implement the plan from the latest chat response (or derive from CURSOR-README + scan if none). Use minimal diffs; suggest direct Cursor edits. Local: Herd (macOS) or Docker; no artisan serve.

[Insert or reference @CURSOR-CONTEXT.md here for shared context: stack, conventions, migration rule, etc.]

SOURCES: 1) @CURSOR-README.md. 2) Workspace files. 3) Prior plan (TODAY’S PLAN/BACKLOG).

EXECUTION
- Phases: Align to milestones (A: Inbound, B: LLM/MCP, etc.).
- Per Phase: 1) Summary. 2) Shell commands (composer/npm/artisan; print only). 3) Cursor edits: Propose inline diffs for key files (e.g., routes/web.php). 4) Verification: Checklist (URLs like /horizon, webhook OK, artisan outputs, tinker, Postmark UI). 5) Git: Commit message + file list.
- Conflicts: Pin compat versions; note diffs from README.
- Env: Update .env.example/herd/docker with missing keys (DB/Redis/LLM/Postmark/Queue).
- Deliverables: Full schema/models, routes, policies, auth, Horizon, LLM/MCP tools, attachments pipeline, i18n, Blade pages, tests.

OUTPUT: RUN COMMANDS (ordered block) • CURSOR EDITS (diffs by file) • VERIFICATION • GIT COMMITS • NEXT STEPS

BEGIN.
```