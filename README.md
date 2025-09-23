# Agent‑AI

A grounded, email‑first agent that turns messy inbox threads into clear actions and transparent results.

## Table of Contents
- Why this matters
- What it does (highlights)
- How it works (architecture)
- Quickstart (Herd & Docker)
- Configure LLMs
- Data model essentials
- Develop & test
- Security & privacy
- Roadmap & contributions
- License

## Why this matters
Email is where work really happens—but context is scattered, follow‑ups get lost, and no one trusts “AI” without seeing how it thinks.

Agent‑AI brings an agent into your inbox without ceremony: it grounds answers in your emails, attachments, and memories, then shows the full trace of every step it took.

## What it does (Feature Highlights)
- **Email‑first agent orchestration**: interpret, plan, and respond inside your existing threads.
- **Grounded answers via pgvector**: search your emails, attachments, and memories for facts before responding.
- **Transparent Activity (full trace)**: every step is logged—role, model, tokens, latency, and JSON I/O—so you can audit the process.
- **Passwordless login from a contact**: email first, then log in; your user is created from your contact automatically.
- **Local‑first models (Ollama) with optional cloud**: run entirely local by default; flip roles to cloud if you need to.

> Note: On the very first inbound contact, an Account is auto‑created from `APP_NAME`. Single‑tenant by default, future‑proof for multi‑tenant.

## How it works (Architecture at a glance)
SEND EMAIL → Contact created (Account auto‑created from `APP_NAME` if none) → Thread attached → First login creates User and `contact_link` → Activity shows full Agent Steps trace.

LLM routing is explicit:
- CLASSIFY → cheap/fast intent & complexity detection.
- Retrieval → cosine KNN over embeddings to fetch relevant snippets.
- GROUNDED or SYNTH → grounded response when retrieval is strong; otherwise synthesize with a larger model.

Grounding lives in Postgres (pgvector):
- Embeddings in `email_messages.body_embedding`, `attachment_extractions.text_embedding`, `memories.content_embedding`.
- Retrieval uses cosine KNN across these tables, with provenance kept for each snippet.

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
- `agent_steps` (trace): logs role, provider, model, tokens (in/out/total), latency_ms, confidence, and `input_json`/`output_json` (with basic secret scrubbing; user content is not redacted). Steps relate to `account`, `thread`, optional `email_message`/`action`, and optional `contact`/`user`.
- Embeddings live in Postgres columns and power retrieval:
  - `email_messages.body_embedding`
  - `attachment_extractions.text_embedding`
  - `memories.content_embedding`
  Dim is the vector length (e.g., 1024 for `mxbai-embed-large`). Retrieval is cosine KNN with provenance.
- Visibility rules:
  - A user sees steps for threads that involve any of their linked contacts (within their account).
  - Admins see all steps in their account.

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
- We scrub obvious secrets in `input_json`/`output_json` but do not redact end‑user content; the point is traceability.
- Full trace is visible to the user for their threads; admins see all within their account.
- Tenant boundary is enforced by `accounts` and `memberships`.

## Roadmap & contributions
Near‑term polish:
- More precise token/latency capture in `agent_steps`
- Additional Activity filters and export
- Seed data and live fixtures for demos

Contributions welcome: small, focused PRs with tests. Follow existing conventions and keep `migrate:fresh` green.

## License
SPDX-License-Identifier: Apache-2.0

Copyright (c) 2025 NowSquare and contributors.