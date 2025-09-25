# Agent AI

**Email your AI employee. It remembers, coordinates agents with tools, and gets work done.**

## Table of Contents
- Why this matters
- What it does (highlights)
- How it works (architecture)
- Quickstart (Herd & Docker)
- Configure LLMs
- Data model essentials
- Develop & test
- Security & privacy
- License

## Why this matters
Work starts in email—quotes, questions, decisions, approvals. But context is scattered, and generic chatbots don’t know your business. Agent AI is a personal assistant you can simply email. It builds long-term memory from your conversations and documents, then replies with grounded, practical help you can trust.

## What it does (Feature Highlights)
- **Email‑native assistant** — Send questions, tasks, and documents by email. Agent AI replies in your thread.
- **Multi‑agent orchestration (with tools)** — Specialized agents collaborate (e.g., extract, research, plan, draft, review) and call tools when needed.
- **Compounding memory** — A growing knowledge base from your emails and attachments that improves answers over time.
- **Grounded responses via pgvector** — Searches your own data (emails, attachments, memories) before it speaks.
- **Transparent activity** — Full step‑by‑step trace so you see how decisions were made.
- **Local‑first models (Ollama), optional cloud** — Private by default; role‑based model routing.

> Note: On the first inbound contact, an Account is auto-created from `APP_NAME`. Single-tenant by default, future-proof for multi-tenant.

## How it works (detailed)
At a high level: Email → Thread → Plan → Work → Debate → Decide → Memory → Reply.

1) You send an email. A Contact and Thread are ensured. Attachments (txt, md, csv, pdf) are stored.
2) Attachments are scanned (ClamAV). Clean files continue to extraction/summarization; infected files trigger an incident email.
3) The message is cleaned (quoted text/signatures removed), then interpreted into a structured Action via a schema‑validated tool.
4) The Coordinator chooses a simple single‑agent path or a multi‑agent orchestration based on complexity.
5) Retrieval (pgvector) fetches relevant snippets from prior emails, attachment text, and memories.
6) An agent drafts a response (or a team does: Workers produce drafts, Critics check, Arbiter picks the best).
7) The Curator saves a compact memory of what was decided, with provenance to the steps that led there.
8) An email reply is sent only when there’s substantive content (or a personalized incident/clarification/options email).

LLM routing is explicit:
- CLASSIFY → cheap/fast intent & complexity detection.
- Retrieval → cosine KNN over embeddings to fetch relevant snippets.
- GROUNDED or SYNTH → grounded response when retrieval is strong; otherwise synthesize with a larger model.

Grounding lives in Postgres (pgvector):
- Embeddings in `email_messages.body_embedding`, `attachment_extractions.text_embedding`, `memories.content_embedding`.
- Retrieval uses cosine KNN across these tables, with provenance kept for each snippet.

## Email pipeline (end‑to‑end)
1) Inbound webhook receives the email and stores a normalized `EmailMessage` with headers/body/attachments.
2) Attachments pipeline: scan → (if clean) extract → summarize; summaries feed interpretation and retrieval.
3) Action interpretation produces `{type, parameters, confidence}` with schemas enforced.
4) Coordinator routes: simple (single agent) vs complex (multi‑agent with plan validation and auto‑repair).
5) Response generation: agent(s) write a helpful reply using retrieval context; Critics check groundedness; Arbiter decides.
6) Memory curation: decision/insight facts are saved with provenance and TTL.
7) Email dispatch: only substantive content. Incident email if infected files; clarification/options when confidence is low.

## Multi‑agent flow
- **Coordinator**: decides simple vs complex, selects agents, manages orchestration.
- **Planner**: proposes a symbolic plan (steps with preconditions/effects). Validator checks and can auto‑repair the plan.
- **Workers**: create drafts, call tools, use retrieval context.
- **Critics**: score groundedness/completeness/risk over K rounds.
- **Arbiter**: picks the winner, records a short decision reason.
- **Curator**: writes memories with provenance for traceability.

## Memory model
- Scope: `conversation`, `user`, `account` with priority `conversation > user > account`.
- TTL categories: `volatile`, `seasonal`, `durable`, `legal` with decay over time.
- Read logic prefers newer, higher‑confidence memories after decay.

## Quickstart
> Warning: Enable `pgvector` in Postgres (CREATE EXTENSION IF NOT EXISTS vector) before first run.

### macOS (Herd)
```bash
cp .env.herd .env
composer install
npm install
php artisan migrate
npm run dev
php artisan horizon
php artisan boost:mcp
```

### Docker
```bash
cp .env.docker .env
composer install
npm install
docker compose up -d
php artisan migrate
```

First run checklist:
- Postgres `vector` extension enabled
- `.env` routing defaults okay for your machine
- Inbound webhook configured (Postmark) or seed test data

## Configure LLMs
Routing keys (see `.env` for comments):
- `LLM_ROUTING_MODE` (auto | single)
- `LLM_GROUNDING_HIT_MIN` (0–1.0)
- `LLM_SYNTH_COMPLEXITY_TOKENS`

Role bindings (defaults):
- CLASSIFY → `mistral-small3.2:24b`
- GROUNDED → `gpt-oss:20b`
- SYNTH → `gpt-oss:120b`

Embeddings:
- `EMBEDDINGS_MODEL=mxbai-embed-large`
- `EMBEDDINGS_DIM=1024`

Tip: If a tag isn’t local, flip the provider/model for that role in `.env` (or pull the tag in Ollama).

## Data model essentials
- `agent_steps` (trace): logs role, provider, model, tokens (in/out/total), latency_ms, confidence, and full `input_json`/`output_json`. The full trace is visible to the user for their own threads; other users cannot access it. Steps relate to `account`, `thread`, optional `email_message`/`action`, and optional `contact`/`user`.
  - Multi-agent protocol fields: `agent_role` (Planner|Worker|Critic|Arbiter), `round_no`, optional `coalition_id`, `vote_score`, `decision_reason`.
- Embeddings live in Postgres columns and power retrieval:
  - `email_messages.body_embedding`
  - `attachment_extractions.text_embedding`
  - `memories.content_embedding`
  Dim is the vector length (e.g., 1024 for `mxbai-embed-large`). Retrieval is cosine KNN with provenance.
### Visibility rules
- **You see the full trace for your own threads only.** A thread is yours if it involves a contact linked to your user (via `contact_links`).
- **No separate admin role today.** (Future-ready: if teams/multi-tenant are introduced later, an account admin could see all threads within that account—without changing the data model.)

## Develop & test
```bash
# Reset database
php artisan migrate:fresh

# Backfill embeddings (optional)
php artisan embeddings:backfill --limit=1000

# Routing dry-run (no LLM call)
php artisan llm:routing-dry-run --text="Find the July invoice from Anna"

# Run tests
php artisan test
```
Guidance: put business logic in Services/Jobs, not Controllers. Write unit and feature tests for new services, routes, and data flows.

## Security & privacy
- Multi-agent protocol (Plan → Allocate → Work → Debate → Decide → Curate): Planner creates tasks; Workers produce drafts; Critics debate for K rounds (2 by default); Arbiter selects a winner, logging `vote_score` and `decision_reason`; Memory Curator persists a summary with provenance.
- Because this is your own data, Agent AI shows the full content of steps for your threads.
- Tenant boundary is enforced by `accounts` and `memberships`.

## Multi-agent enhancements
- Allocation (auction heuristic): utility = w_cap*capability_match + w_cost*(1/cost_hint) + w_rel*reliability. Picks top‑K workers per subtask; allocation is logged in `agent_steps` with `agent_role=Planner`.
- Structured debate (K rounds + minority report): Critics score groundedness/completeness/risk; near‑top candidates are retained as a minority report (ε). Weighted voting aggregates Critic + Worker self‑scores; tie‑breakers prefer higher groundedness, then lower expected cost, then oldest candidate.
- Typed memories: `Decision|Insight|Fact` with `provenance_ids[]` and a stable content hash to deduplicate outcomes. TTL/decay rules continue to apply.
- Metrics & tooling: `php artisan agent:metrics --since=... --limit=...` prints rounds, per‑role activity, groundedness %, and win distribution.

### Add a new agent (mini‑guide)
1) Define capability tags and a rough `cost_hint` on the `Agent` (`capabilities_json.keywords|domains|expertise|action_types`).
2) Register/seed the agent for the account; reliability will update automatically from wins.
3) Provide a Worker handler implicitly via `AgentProcessor` prompts; optionally shape Critic policy by improving groundedness inputs.

## Roadmap & contributions
Near‑term polish:
- More precise token/latency capture in `agent_steps`
- Additional Activity filters and export
- Seed data and live fixtures for demos

Contributions welcome: small, focused PRs with tests. Follow existing conventions and keep `migrate:fresh` green.

## License
SPDX-License-Identifier: Apache-2.0

Copyright (c) 2025 NowSquare and contributors.