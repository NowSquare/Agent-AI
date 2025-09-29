# Agent AI – Technical Design Document (Single Source of Truth)

> This is the **authoritative spec** for the Agent-AI project. If something is **not** defined here, it is **out of scope**.
> This edition integrates the current `.env.example`, Cursor development prompts, and the requirement that **all structured LLM outputs are enforced via tool calls** (no “please return JSON” instructions). Content here aligns with the Cursor prompts pack used during development. 

## 0) What Agent-AI Is (Plain)

**Plain:** You email Agent-AI like a coworker. It reads your message and attachments, checks your past context, and prepares the next right action. If unclear, it asks once or twice—then proceeds safely. It is **email-first**, **evidence-grounded**, and **polite by default**.

**Key ideas in one line each**

* **Email is the UI:** Threaded, human-friendly control surface.
* **Tool-enforced JSON:** Structured outputs via **model tool calling** (function schemas), never “freeform JSON”.
* **Grounding:** pgvector retrieves evidence from your own data before answering.
* **Multi-agent, but simple:** Planner → Workers → Critic → **Plan validation** → Arbiter → Reply.
* **Attachments:** Virus scan → extract → summarize → safe download links.
* **Signed links:** One-click, idempotent approvals with expiry.

## 1) Executive Summary

Agent-AI (Laravel 12, PHP 8.4) ingests inbound emails through **Postmark**, threads them, and interprets free text via an **LLM**. It executes **only** through server-controlled tools or user-confirmed signed links. LLM responses that must be structured are **always** produced by **tool calls** (function schemas) exposed by a custom **MCP layer**. Attachments are scanned (ClamAV), extracted (txt/md/csv/pdf), summarized, and grounded in answers. A **clarification loop** (≤ 2 rounds) runs when intent is unclear. **Redis/Horizon** provide background reliability. **PostgreSQL 17 + pgvector** power grounded retrieval over emails, attachments, and memories. Passwordless login, Flowbite/Tailwind UI, and clear, commented config keep the system human-friendly. Lightweight **AgentOps** logging and internal **multi-agent delegation** improve observability and reliability.

## 2) Tech Stack (Pinned)

| Component   | Technology                           | Version         | Notes                                                                           |
| ----------- | ------------------------------------ | --------------- | ------------------------------------------------------------------------------- |
| Framework   | Laravel                              | 12.x            | Jobs, Mail, Validation, Horizon                                                |
| PHP Runtime | PHP                                  | 8.4             | Performance, typing (Nov 2024), supported through Dec 2026                      |
| Database    | PostgreSQL                           | 18+             | JSONB, constraints, indexes, **pgvector**; new AIO improves retrieval latency   |
| Queue/Cache | Redis                                | 7.2.4-138       | Reliable async; API enhancements and diagnostic logging (Sep 2025)              |
| Mail        | Postmark                             | latest          | Inbound JSON; Data Removal API (Jun/Aug 2025); avoid malicious 'postmark-mcp'   |
| UI          | Blade + Tailwind + Flowbite          | Tailwind ^4.0   | Accessible, fast; Flowbite ^2.1.0 adds RTL and JS API enhancements              |
| Icons       | Lucide                               | latest          | SVG icons                                                                       |
| LLM         | Ollama + (OpenAI/Anthropic optional) | latest          | Local-first; improved scheduling (Sep 2025) reduces OOM; cloud fallback optional |
| Laravel MCP | Laravel MCP Framework                | ^0.x            | Structured, error-resistant tool I/O                                           |
| AV Scan     | ClamAV (daemon)                      | 1.0.6+ LTS      | 0.103 EOL Sep 2025; upgrade for signatures                                     |
| PDF text    | poppler-utils / spatie/pdf-to-text   | latest          | Extraction                                                                      |
| Container   | Docker/Compose                       | latest          | Self-hosting                                                                    |

### Version Upgrade Considerations

- PostgreSQL 18: AIO enabled by default on many platforms; monitor IO utilization. Vector extension unchanged; keep `shared_preload_libraries=vector` where needed.
- Redis 7.2.4-138: Enable enhanced command introspection only in staging/prod; check changelog for keyspace notifications.
- ClamAV 1.0.6+: Ensure freshclam updates are scheduled; remove legacy 0.103 configs.
- Flowbite ^2.1.0: Verify Tailwind v4 integration; RTL utilities available.

## 3) System Architecture

### 3.1 High-Level Flow

1. **Inbound**: Postmark webhook → `/webhooks/postmark-inbound` (HTTP Basic + HMAC).
2. **Persist**: Encrypted payload; queue `ProcessWebhookPayload` → `ProcessInboundEmail`.
3. **Thread**: RFC 5322 threading (`Message-ID`, `In-Reply-To`, `References`).
4. **Attachments**: Scan (ClamAV) → Extract → Summarize (LLM tool) on `attachments` queue.
5. **Interpret**: `action_interpret` (tool-enforced JSON) classifies intent + confidence + parameters.
6. **Grounding**: pgvector KNN retrieves context from emails/attachments/memories.
7. **Routing**: If hit-rate ≥ `LLM_GROUNDING_HIT_MIN` → **GROUNDED**, else **SYNTH**; or force SYNTH if tokens ≥ `LLM_SYNTH_COMPLEXITY_TOKENS`.
8. **Clarify**: Confidence gates: ≥0.75 execute; 0.50–0.74 clarify (≤ 2 rounds); <0.50 options email.
9. **Multi-Agent (when complex)**: Planner → **Plan Validation** → Workers → Critic → Arbiter → Coordinator synthesizes reply.
10. **Deliver**: Postmark outbound; signed links for approvals; comprehensive trace in `agent_steps`.

Text diagram

```
Inbound → Webhook (/webhooks/postmark-inbound)
  → Queue: ProcessWebhookPayload → ProcessInboundEmail
    → Threading (RFC headers + X-Thread-ID) → Store Email/Attachments
    → Scan (ClamAV) → Extract → Summarize
    → Grounding (pgvector KNN) → LLM Routing (CLASSIFY/GROUNDED/SYNTH)
      → Clarify (if medium) → Execute/Options → Send Mail
    → Log steps in agent_steps → Update thread/version/metadata
```

### 3.2 Security Guarantees

* **Tool-enforced JSON**: Any structured output must be a model tool call with a server-owned JSON schema.
* **No arbitrary fetch**: MCP tools are SSRF-guarded; http/https only; no private networks.
* **Attachments**: Never processed before ClamAV pass; infected → quarantine + incident email.
* **Signed links**: Short expiry (15–60 min), nonce, idempotent, no PII in URL.
* **Auth**: passwordless challenges + magic links; rate-limited.
* **Postmark compliance**: Use Postmark's Data Removal API (2025) for erasure requests.
* **Supply chain**: Avoid the malicious `postmark-mcp` npm package (reported Sep 25, 2025).

## 4) .env.example (Current, Annotated)

> **Plain:** These are the switches that make the app work locally. Each line is explained so non-engineers can follow what’s happening.

```env
# ==============================================================================
# Agent-AI — Local Development (macOS with Herd)
# ==============================================================================
APP_NAME="Agent AI"                     # Shown in emails/UI; also used for default account seeds
APP_ENV=local                           # local | staging | production
APP_DEBUG=true                          # Show detailed errors (keep true only on local)
APP_URL="http://localhost"              # Base URL for signed links
APP_TIMEZONE="Europe/Amsterdam"         # Affects scheduling and timestamps in UI/emails

# PostgreSQL (local defaults for Herd)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agent_ai
DB_USERNAME=postgres
DB_PASSWORD=

# Redis (queues, cache, sessions)
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Postmark (outbound + inbound)
MAIL_MAILER="postmark"
POSTMARK_TOKEN="your-real-postmark-server-token"  # Server token from Postmark
POSTMARK_MESSAGE_STREAM_ID="outbound"             # Select the outbound stream you use
MAIL_FROM_ADDRESS="noreply@agent-ai.test"         # From header for outbound email
MAIL_FROM_NAME="Agent AI"                         # Friendly name in mail clients

# Inbound webhook (Postmark → Basic Auth)
AGENT_MAIL="<hash>@inbound.postmarkapp.com"       # Your unique inbound mailbox at Postmark
WEBHOOK_USER="postmark"                           # Basic Auth username (Postmark will call with this)
WEBHOOK_PASS="your-very-long-random-password-here"# Basic Auth password (keep secret)

# --------------------------
# LLM — Routing & Roles
# --------------------------
## What is routing?
##  - CLASSIFY: quick, cheap intent detection.
##  - GROUNDED: answers with retrieved facts (pgvector) if hits are good.
##  - SYNTH: larger model for reasoning/synthesis if grounding is weak or input is long.
## How it works:
##  1) We embed the user query and run vector KNN search over emails/attachments/memories.
##  2) If the hit-rate ≥ LLM_GROUNDING_HIT_MIN → GROUNDED; else → SYNTH.
##  3) If tokens_in ≥ LLM_SYNTH_COMPLEXITY_TOKENS, force SYNTH.
LLM_TIMEOUT_MS=120000           # Max request time (ms). Larger = more tolerant to slow models.
LLM_RETRY_MAX=1                 # Retries on 408/429/5xx.
LLM_ROUTING_MODE=auto           # auto | single (single disables routing and uses LLM_PROVIDER/LLM_MODEL)
LLM_GROUNDING_HIT_MIN=0.35      # 0–1.0. Raise if you only want very strong retrieval.
LLM_SYNTH_COMPLEXITY_TOKENS=1200# If input ≥ this estimated token count, go SYNTH.
LLM_MAX_AGENT_STEPS=10          # Safety cap for internal multi-step delegations.

# Role bindings (local-first)
LLM_CLASSIFY_PROVIDER=ollama    # Local-first: fast classifier model
LLM_CLASSIFY_MODEL="mistral-small3.2:24b"  # Example local tag
LLM_CLASSIFY_TOOLS=true
LLM_CLASSIFY_REASONING=false

LLM_GROUNDED_PROVIDER=ollama
LLM_GROUNDED_MODEL="gpt-oss:20b"
LLM_GROUNDED_TOOLS=true
LLM_GROUNDED_REASONING=false

LLM_SYNTH_PROVIDER=ollama
LLM_SYNTH_MODEL="gpt-oss:120b"
LLM_SYNTH_TOOLS=true
LLM_SYNTH_REASONING=true

## Embeddings (pgvector)
##  - Used for grounding (retrieval). Choose a model and set matching DIM.
##  - Examples: mxbai-embed-large → 1024, nomic-embed-text → 768
EMBEDDINGS_PROVIDER=ollama
EMBEDDINGS_MODEL="mxbai-embed-large"
EMBEDDINGS_DIM=1024
EMBEDDINGS_DISTANCE=cosine      # cosine | l2 | ip (must match pgvector ops used by indexes)
EMBEDDINGS_INDEX_LISTS=100      # ivfflat lists (increase for larger datasets)

## Providers
##  - Ollama: local models; pull tags you configure above.
##  - OpenAI/Anthropic: set API keys to route roles to cloud.
OLLAMA_BASE_URL="http://localhost:11434"

## Optional Cloud (leave empty if local-only)
##  - Leave these empty to run fully local via Ollama.
##  - To route any role to cloud, set PROVIDER=openai/anthropic and model accordingly.
OPENAI_API_KEY=
OPENAI_BASE_URL="https://api.openai.com/v1"
ANTHROPIC_API_KEY=
ANTHROPIC_BASE_URL="https://api.anthropic.com"

# ClamAV on host (macOS: brew services start clamav)
CLAMAV_HOST=127.0.0.1
CLAMAV_PORT=3310

# Attachments
ATTACH_MAX_SIZE_MB=25
ATTACH_TOTAL_MAX_SIZE_MB=40
FILESYSTEM_DISK=local           # local disk in development; use s3 in production

## Tuning & Troubleshooting (Plain)
## Tuning:
##  - If answers hallucinate → lower LLM_GROUNDING_HIT_MIN or improve embeddings model.
##  - If everything routes to SYNTH → decrease LLM_SYNTH_COMPLEXITY_TOKENS or improve retrieval (k↑).
##  - If latency too high → pick a smaller GROUNDED model or reduce k; disable reasoning for GROUNDED.
## Troubleshooting:
##  - Vector dim mismatch → check EMBEDDINGS_DIM vs actual model; re-run migrations/backfill.
##  - Missing model tags → change role provider/model or pull tags in Ollama.
##  - No matches in retrieval → verify embeddings present; run embeddings:backfill; inspect stopwords/cleanup.
```

## 5) Project Structure (Full, canonical)

> **Contract:** Keep this tree accurate. Update it with any file additions/removals.
> Comments explain **why** each piece exists (non-tech friendly).

```
Agent-AI/
├─ app/
│  ├─ Console/
│  │  ├─ Commands/
│  │  │  ├─ EmbeddingsBackfill.php          # php artisan embeddings:backfill (fills vectors for grounding)
│  │  │  ├─ LlmRoutingDryRun.php            # Simulate routing decisions (GROUNDED vs SYNTH) on samples
│  │  │  ├─ PruneMemories.php               # Decay/purge old memories (retention hygiene)
│  │  │  └─ ScenarioRun.php                 # Demo: seed a thread, run orchestration, print checklist hints
│  │  └─ Kernel.php
│  ├─ Exceptions/
│  ├─ Http/
│  │  ├─ Controllers/
│  │  │  ├─ ActivityController.php          # View AgentOps trace (only threads you’re linked to)
│  │  │  ├─ AttachmentDownloadController.php# Signed downloads, nonce, expiry, deny if infected
│  │  │  ├─ DashboardController.php
│  │  │  ├─ ActionConfirmationController.php# Signed approve/reject/select links; idempotent
│  │  │  ├─ Auth/
│  │  │  │  ├─ ChallengeController.php      # Passwordless: send code
│  │  │  │  ├─ LoginController.php          # Magic link endpoint
│  │  │  │  └─ VerifyController.php         # Verify 6-digit code
│  │  │  ├─ Api/
│  │  │  │  ├─ ActionsController.php        # Internal action dispatch (UI forms)
│  │  │  │  └─ ThreadsController.php        # Thread detail API (UI fetch)
│  │  │  └─ Webhook/
│  │  │     └─ PostmarkInboundController.php# Validates Basic Auth + HMAC; stores encrypted payload; enqueues
│  │  ├─ Middleware/
│  │  │  ├─ DetectLanguage.php              # Locale from URL/session/header/content; sets Content-Language
│  │  │  └─ VerifyWebhookSignature.php      # HMAC check for inbound (defense in depth)
│  │  ├─ Requests/                          # FormRequest validators (auth/actions)
│  │  └─ Resources/                         # (optional) API transformers
│  ├─ Jobs/
│  │  ├─ ExtractAttachmentText.php          # After scan, extract text (txt/md/csv/pdf)
│  │  ├─ ProcessInboundEmail.php            # Parse, thread, clean reply, interpret, route, maybe clarify
│  │  ├─ ProcessWebhookPayload.php          # Decrypt, persist EmailMessage, then dispatch ProcessInboundEmail
│  │  ├─ ScanAttachment.php                 # ClamAV (must pass) → else quarantine + incident email
│  │  ├─ SendActionResponse.php             # Outbound mails with thread continuity
│  │  ├─ SendClarificationEmail.php         # Medium confidence (0.50–0.74), ≤2 rounds
│  │  ├─ SendOptionsEmail.php               # Low confidence (<0.50), clickable choices
│  │  └─ SummarizeAttachment.php            # LLM tool: concise gist + bullets (short)
│  ├─ Mail/
│  │  ├─ ActionClarificationMail.php
│  │  ├─ ActionOptionsMail.php
│  │  ├─ ActionResponseMail.php
│  │  ├─ AuthChallengeEmail.php
│  │  └─ AuthMagicLinkEmail.php
│  ├─ Mcp/
│  │  ├─ Prompts/
│  │  │  ├─ PromptCatalog.php               # Central registry of prompt keys and defaults
│  │  │  └─ ToolSchemas.php                 # PHP arrays (JSON Schemas) bound to prompt keys
│  │  ├─ Servers/
│  │  │  └─ McpController.php               # Single endpoint to execute MCP tools (server-side)
│  │  └─ Tools/
│  │     ├─ FetchUrlTool.php                # GET up to 2KB (public http/https only)
│  │     ├─ HttpHeadTool.php                # HEAD request (SSRF-guarded)
│  │     ├─ ResolveRedirectTool.php         # Follows redirects safely
│  │     ├─ ExtractMetadataTool.php         # <title> and meta description from HTML
│  │     └─ GetDatetimeTool.php             # Time in timezone/format
│  ├─ Models/
│  │  ├─ Account.php
│  │  ├─ Action.php
│  │  ├─ Agent.php
│  │  ├─ AgentSpecialization.php
│  │  ├─ AgentStep.php                      # AgentOps trace (role, model, tokens, latency, confidence, I/O)
│  │  ├─ ApiToken.php
│  │  ├─ Attachment.php
│  │  ├─ AttachmentExtraction.php
│  │  ├─ AuthChallenge.php
│  │  ├─ Contact.php
│  │  ├─ ContactLink.php
│  │  ├─ EmailInboundPayload.php
│  │  ├─ EmailMessage.php
│  │  ├─ Event.php
│  │  ├─ EventParticipant.php
│  │  ├─ Membership.php
│  │  ├─ Memory.php
│  │  ├─ Task.php
│  │  ├─ Thread.php
│  │  ├─ ThreadMetadata.php
│  │  └─ User.php
│  ├─ Providers/
│  │  ├─ AppServiceProvider.php
│  │  └─ HorizonServiceProvider.php
│  ├─ Schemas/                              # Server-side validators (Laravel Validator rules)
│  │  ├─ ActionInterpretationSchema.php
│  │  ├─ MemoryExtractSchema.php
│  │  ├─ ThreadSummarizeSchema.php
│  │  ├─ AttachmentSummarizeSchema.php
│  │  └─ ClarifyDraftSchema.php
│  ├─ Services/
│  │  ├─ ActionDispatcher.php               # Executes safe actions (server-side)
│  │  ├─ AgentProcessor.php                 # Prompt orchestration per agent + logging
│  │  ├─ AgentRegistry.php                  # Capability tags → top-K agent matching
│  │  ├─ AttachmentService.php              # MIME/size limits, scan, extraction, signed URLs
│  │  ├─ AuthService.php
│  │  ├─ ContactLinkService.php
│  │  ├─ Coordinator.php                    # Complexity detection; simple vs multi-agent routing
│  │  ├─ Embeddings.php                     # Vectorize and store (pgvector)
│  │  ├─ EnsureDefaultAccount.php
│  │  ├─ GroundingService.php               # Retrieval (top-k) with provenance
│  │  ├─ LanguageDetector.php               # URL/session/header/content + LLM fallback
│  │  ├─ LlmClient.php                      # **Tool-enforced** JSON; providers; retries/timeouts
│  │  ├─ MemoryService.php                  # TTL/decay/supersede; scopes (conversation/user/account)
│  │  ├─ ModelRouter.php                    # CLASSIFY → (retrieval) → GROUNDED | SYNTH
│  │  ├─ MultiAgentOrchestrator.php         # Planner/Workers/Critic/Arbiter coordination
│  │  ├─ PlanValidator.php                  # (Optional local checker; see symbolic plan loop)
│  │  ├─ ReplyCleaner.php                   # Strip quotes/signatures
│  │  ├─ ThreadResolver.php                 # RFC threading (Message-ID/References)
│  │  └─ ThreadSummarizer.php               # Periodic thread summaries for fast context
│  └─ View/
│     └─ Components/                        # Blade components (e.g., thread metadata, action status)
├─ bootstrap/
│  ├─ app.php
│  └─ providers.php
├─ config/
│  ├─ actions.php        # Action whitelist + preconditions/effects (symbolic plan)
│  ├─ agents.php         # Capability tags, cost hints, role bindings
│  ├─ app.php
│  ├─ attachments.php
│  ├─ auth.php
│  ├─ cache.php
│  ├─ database.php
│  ├─ filesystems.php
│  ├─ horizon.php
│  ├─ language.php       # Locale detection priorities, supported locales
│  ├─ llm.php            # Providers, routing, caps, role models, timeouts, retries
│  ├─ logging.php
│  ├─ mail.php
│  ├─ memory.php
│  ├─ prompts.php        # Prompt templates, temperatures, role mappings (for docs parity)
│  ├─ queue.php
│  ├─ services.php
│  └─ session.php
├─ database/
│  ├─ factories/
│  ├─ migrations/        # Use create migrations; keep migrate:fresh green (no alter files)
│  │  ├─ 2025_09_21_011500_create_agent_steps_table.php
│  │  └─ … (accounts, users, threads, email_messages, attachments, memories, tasks, agents, etc.)
│  └─ seeders/
├─ docker/
│  └─ entrypoint.sh
├─ public/
│  ├─ favicon.ico
│  ├─ index.php
│  └─ robots.txt
├─ resources/
│  ├─ css/app.css
│  ├─ js/{app.js, bootstrap.js}
│  ├─ lang/
│  │  ├─ en/{auth.php, emails.php, messages.php}
│  │  └─ nl/{auth.php, emails.php, messages.php}
│  └─ views/
│     ├─ action/{confirm.blade.php, options.blade.php, clarify.blade.php}
│     ├─ activity/{index.blade.php, show.blade.php}
│     ├─ auth/{challenge.blade.php, verify.blade.php}
│     ├─ components/{…}
│     ├─ emails/{…}
│     ├─ layouts/{app.blade.php, guest.blade.php}
│     ├─ threads/show.blade.php
│     └─ dashboard.blade.php
├─ routes/
│  ├─ api.php
│  ├─ console.php
│  └─ web.php
├─ storage/… (runtime)
├─ tests/
│  ├─ Feature/
│  │  ├─ GroundedAnswerTest.php
│  │  ├─ SynthAnswerTest.php
│  │  ├─ WebhookInboundTest.php
│  │  ├─ SignedLinksTest.php
│  │  └─ ClarificationLoopTest.php
│  ├─ Unit/
│  │  ├─ ModelRouterTest.php
│  │  ├─ GroundingServiceTest.php
│  │  ├─ EmbeddingsTest.php
│  │  └─ PlanValidatorTest.php
│  └─ TestCase.php
├─ .env.example
├─ artisan
├─ composer.json
├─ composer.lock
├─ CURSOR-README.md        # This file (single source of truth)
├─ CURSOR-PROMPTS.md       # Cursor prompts used during dev (planning, execute, QA)  ← read during dev
├─ README.md               # Public, shorter overview
├─ docker-compose.yml
├─ phpunit.xml
└─ vite.config.js
```

## 6) HTTP Endpoints

| Method | Path                           | Auth              | Purpose                               |
| -----: | ------------------------------ | ----------------- | ------------------------------------- |
|   POST | `/webhooks/postmark-inbound`   | HTTP Basic + HMAC | Receive inbound emails (JSON)         |
|    GET | `/a/{action}`                  | **Signed**        | One-click confirmation page           |
|   POST | `/a/{action}`                  | **Signed**        | Execute confirmed action (idempotent) |
|    GET | `/attachments/{id}`            | **Signed**        | Download clean attachment             |
|   POST | `/auth/challenge`              | rate-limited      | Passwordless challenge                |
|   POST | `/auth/verify`                 | rate-limited      | Verify code / magic link              |
|    ANY | `/mcp/ai`                      | Auth (internal)   | MCP tool execution gateway            |
|   POST | `/api/actions/dispatch`        | Auth              | UI form action dispatch               |
|    GET | `/api/threads/{id}`            | Auth              | Thread detail (UI fetch)              |
|    GET | `/activity` / `/activity/{id}` | Auth              | View AgentOps trace                   |

## Frontend Wireframes & Pages

Authentication (passwordless)

Challenge `/auth/challenge`

```
┌─────────────────────────────────────┐
│           Agent AI                  │
│                                     │
│  Welcome back!                      │
│                                     │
│  ┌─────────────────────────────┐    │
│  │ Email Address              │    │
│  └─────────────────────────────┘    │
│                                     │
│  [Send Login Code]                  │
│                                     │
│  By continuing, you agree to our    │
│  Terms of Service & Privacy Policy  │
└─────────────────────────────────────┘
```

Verify `/auth/verify/{id}`: 6‑digit input, resend link, error state.

Main

- Dashboard `/dashboard`: header/nav, recent threads list, search bar.
- Thread Detail `/threads/{id}`: header, timeline (messages, attachments), action buttons.

Actions

- Signed Action `/a/{ulid}`: summary, confirm/reject.
- Options Selection `/a/options/{ulid}`: list of options with radio/select and submit.

Attachments

- Download `/attachments/{id}`: preview (when safe), filename, size, scan status, signed link.

Settings

- Account `/settings/account`: account info, locale.
- Profile `/settings/profile`: name, language.

Emails

- Branding for codes, confirmations, clarifications; use `__()` for i18n; Flowbite supports RTL.

Notes

- All forms CSRF-protected; dark mode supported via Tailwind v4 `dark:` utilities.

## 7) LLM Routing, Tools & Prompts

### 7.1 Non-negotiable rule

> **Structured outputs must be produced via a model tool call**.
> The tool’s **argument schema is the contract**. We never rely on “Please answer as JSON”.

Defaults (2025 baseline):

- LLM_GROUNDING_HIT_MIN: 0.35 (raise for stricter grounding)
- LLM_SYNTH_COMPLEXITY_TOKENS: 1200 (lower for smaller hardware)
- Ollama scheduling (Sep 2025): better multi-model queuing; reduce concurrent large models to avoid OOM.

**LlmClient** (summary):

* `json($promptKey, $vars)` → chooses provider for role (CLASSIFY/GROUNDED/SYNTH), registers the **tool function** with the correct schema for `$promptKey`, sets `tool_choice=required`, and returns **validated args**.
* Retries on {408, 429, 5xx} ≤ `LLM_RETRY_MAX`.
* Timeouts from `LLM_TIMEOUT_MS`.

**Provider bindings** (env):

* `LLM_*_PROVIDER` = `ollama|openai|anthropic`
* `LLM_*_MODEL` per role; tools on/off; reasoning on/off per role.

Temperatures & limits (see `config/prompts.php`): role-specific temps, input/output token caps, and deterministic seeds for CI.

### 7.2 Core Prompt Keys (tool-enforced)

* `action_interpret` → `{ action_type, parameters, scope_hint?, confidence, needs_clarification, clarification_prompt? }`
* `clarify_question` → `{ question }`
* `clarify_email_draft` / `options_email_draft` → `{ subject, text, html }`
* `memory_extract` → `{ items:[{ key,value,scope,ttl_category,confidence,provenance }] }`
* `thread_summarize` → `{ summary, key_entities[], open_questions[] }`
* `attachment_summarize` → `{ title, gist, key_points[], table_hint{...} }`
* `csv_schema_detect` → `{ delimiter, has_header, columns[] }`
* **Multi-agent**

  * `define_agents_plan` → `{ agents[], tasks[], deps[]? }`
  * `plan_symbolic_check` → `{ dag_ok, problems[], repair_suggestions[] }`
  * `critic_review_step` → `{ verdict, issues[], risk, proposed_fix? }`
  * `arbiter_select` → `{ winner_id, scores[], reason?, rework{needed,hint?} }`
  * `coord_synthesize_reply` → `{ reply_type, subject?, text?, html?, attachments[] }`

**Temperatures & limits** live in `config/prompts.php` (documented values only; do not fork elsewhere).

## 8) Symbolic Plan Validation (gating loop)

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

**Where enforced:** Multi-agent flow **must** validate `define_agents_plan` with `plan_symbolic_check` (prompt tool) and optionally with local `PlanValidator` for CI determinism. The “SendReply” step is **gated** by `plan_valid=true`.

**Action rules** live in `config/actions.php` (preconditions/effects). Keep them short and composable:

* Pre: `scanned=true`, `confidence>=0.75`
* Eff:  `text_available=true`, `confidence+=0.1`

Agent roles (examples)

- Planner: methodical; creates DAG of steps from user intent.
- Critic: analytical; finds issues in plan/outputs.
- Arbiter: impartial; selects the best response.
- Coordinator: breaks tasks into agents; manages debate rounds (default 2).
- Chef Mario: Italian cuisine; keywords recipe/food; personality passionate.
- Tech Support: troubleshooting focus; methodical and concise.

Allocation hint (utility): weight(cost_hint, reliability, latency).

## 9) Clarification Loop (strict gates)

* **≥ 0.75** → auto-execute (no user friction).
* **0.50–0.74** → ask **one** clear question (≤ 2 rounds total).
* **< 0.50** → options email with 2–4 safe, likely choices.

`actions` table has:

* `clarification_rounds` (default 0, max 2)
* `last_clarification_sent_at`
* `status`: `awaiting_confirmation|awaiting_input|processing|completed|failed`

## 10) Retrieval (pgvector)

* Embed: latest emails, attachment extractions, and relevant memories.
* KNN: cosine by default, `top_k` from config (sane default 6).
* Route: if hit-rate ≥ `LLM_GROUNDING_HIT_MIN` → GROUNDED, else SYNTH; or force SYNTH if `tokens_in ≥ LLM_SYNTH_COMPLEXITY_TOKENS`.
* Backfill: `php artisan embeddings:backfill`

## 11) Attachments Pipeline (security-first)

1. **Scan** with ClamAV (required).
2. **Extract** text (txt/md/csv direct; pdf → pdf-to-text).
3. **Summarize** via `attachment_summarize` tool (short gist + bullets).
4. **Expose** signed downloads (15–60 min expiry, nonce). Infected files are blocked with a polite incident email.

**Limits**: `ATTACH_MAX_SIZE_MB=25` (per file), `ATTACH_TOTAL_MAX_SIZE_MB=40` (per email).
**Never** fetch attachments over the network from the model.

## 12) Memory Policy

* **Scopes:** conversation > user > account (priority).
* **TTL categories:** `volatile` (30d), `seasonal` (90d), `durable` (365d), `legal` (policy).
* **Decay:** `confidence(t) = c0 * 0.5^(age_days / half_life_days)`
* **Sensitive data:** rejected at extraction; redact before echoing (`safety_redact_pii` tool if needed).

## 13) UI & i18n

* Blade + Tailwind + Flowbite; Lucide icons; dark mode.
* Languages: `en_US`, `nl_NL` (extendable via `config/language.php`).
* `DetectLanguage` middleware sets locale and `Content-Language`.

**Pages**

* Auth (challenge / verify)
* Dashboard (threads + quick stats)
* Thread detail (timeline, attachments, pending actions)
* Action confirmation (signed links)
* Activity trace (AgentOps, your threads only)

## 14) Ops: Running & Verifying

### 14.1 Local (Herd on macOS)

```bash
# queues & horizon
php artisan horizon
php artisan queue:work --queue=default,attachments

# ngrok for webhook (dev)
ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test
# Configure Postmark inbound to:
# https://WEBHOOK_USER:WEBHOOK_PASS@abc123.ngrok-free.app/webhooks/postmark-inbound
```

**ClamAV**
`brew services start clamav` → ensure daemon listens on `127.0.0.1:3310`.

### 14.2 Docker (self-hosting)

See `docker-compose.yml` (Postgres 17, Redis 7, ClamAV, Ollama). Start workers + horizon in the app container.

## 15) Cursor-Driven Development (prompts)

> Use **Laravel Boost MCP** and the included **Cursor prompts** to plan/execute/QA changes. Prompts codify the repo rules: migrations must stay green with `migrate:fresh`, tests for new features, and **Project Structure** kept accurate. 

**Setup**

```bash
composer require laravel/boost --dev
php artisan boost:install
php artisan boost:mcp
```

**Prompts (from `CURSOR-PROMPTS.md`)**

* **Make a Plan Prompt**: deep repo scan, ERD, gaps, TODAY’S PLAN, and risks.
* **Execute the Plan Prompt**: implement with tests, doc updates, i18n, logging, and commit hygiene.
* **Demo & Verification Prompt**: run scenario, print evidence JSONs (thread, roles, arbiter, plan, memory, embeddings), produce PASS/FAIL checklist.
* **Demo Fix-it Prompt**: tight fixes, no alter migrations, rerun and document.

**Golden rule in prompts**: Add detailed doc comments and keep **symbolic plan validation** in place for complex flows.

## 16) Testing Strategy

* **Unit**: Model casts/relations, GroundingService, PlanValidator.
* **Feature**: Inbound webhook flow, signed links idempotence, clarification loop transitions.
* **Integration**: LlmClient `json()` tool enforcement, MCP tools SSRF guard, ClamAV stub path.
* **E2E**: `php artisan scenario:run` then **Demo & Verification Prompt** checklist.

**Never** test with SQLite; always use PostgreSQL (pgvector, JSONB).

## 17) Reliability, Limits & Tuning

* **LLM**: P50 < 30s; timeout ≤ `LLM_TIMEOUT_MS` (120s default dev).
* **Retries**: ≤ `LLM_RETRY_MAX` on transient errors.
* **Queues**: dedicated `attachments` queue; scale workers under load.
* **Rate limits**:

  * `/auth/challenge`: 5/15m per email; 20/h per IP
  * `/auth/verify`: 10/15m per email
  * Webhook total: 120/min
  * Signed links: 60/min per IP
  * LLM: 10/min per thread; 100/h per account

## 18) Troubleshooting (Plain answers)

* **“Vector dim mismatch”** → `EMBEDDINGS_DIM` must match model; fix `.env`; `php artisan migrate:fresh && php artisan embeddings:backfill`.
* **“Missing model tags”** (Ollama) → pull the tag you configured; or switch role provider/model in `.env`.
* **“No retrieval matches”** → ensure embeddings exist; increase `top_k`; lower `LLM_GROUNDING_HIT_MIN`.
* **“ClamAV refused / not found”** → start daemon, verify host/port; check logs for `clamd` readiness.
* **“Webhook HMAC failed”** → verify Basic Auth & raw body use; check Postmark settings; re-post sample.
* **“Thread splits / dupes”** → inspect headers; normalize subject; use X-Thread-ID if available.

## 19) Action Whitelist v1 (server executes only these)

| type                     | parameters (validated server-side)                                   |           |         |
| ------------------------ | -------------------------------------------------------------------- | --------- | ------- |
| `approve`                | `{reason?}`                                                          |           |         |
| `reject`                 | `{reason?}`                                                          |           |         |
| `revise`                 | `{changes: string[]}`                                                |           |         |
| `select_option`          | `{option_id? string, label? string}` (prefer `option_id`)            |           |         |
| `provide_value`          | `{key: string, value: string}`                                       |           |         |
| `schedule_propose_times` | `{duration_min, timezone, window_start?, window_end?, constraints?}` |           |         |
| `schedule_confirm`       | `{selected_start, duration_min, timezone}`                           |           |         |
| `unsubscribe`            | `{scope: "thread" \| "account" \| "all"}`                           |           |         |
| `info_request`           | `{question: string}`                                                 |           |         |
| `stop`                   | `{reason?: string}`                                                  |           |         |

**Note:** The LLM only **proposes** structured intent via `action_interpret`. The server executes after validation and gating.

## 20) Development Workflow (quick start)

```bash
# 1) Install deps and set up env
cp .env.example .env
# Fill Postmark, AGENT_MAIL, WEBHOOK creds, and LLM/embeddings vars

# 2) DB + assets
php artisan key:generate
php artisan migrate
npm install && npm run dev

# 3) Workers and horizon
php artisan horizon
php artisan queue:work --queue=default,attachments

# 4) Webhook tunnel (dev)
ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test
```

**Run the demo**

```bash
php artisan optimize:clear
php artisan scenario:run
```

Then follow the **Demo & Verification Prompt** to validate end-to-end behavior.

## 21) Glossary (Plain)

* **MCP**: Model Context Protocol; our server layer exposing **safe**, schema-bound tools/prompts.
* **Tool-enforced JSON**: Structured output produced by model tool calling, not by instruction.
* **Grounding**: Using your own data (emails/files/memories) as evidence for answers.
* **pgvector**: PostgreSQL extension for vector search (fast similarity).
* **Clarification loop**: Ask up to 2 short questions before acting when confidence is medium.
* **Signed links**: One-click confirmations (expiring, tamper-proof).

## 22) Compliance & Privacy (summary)

* GDPR-first: minimal retention, export/purge controls (memories/attachments), EU hosting viable.
* Sensitive data: never stored as memories; redaction available.
* All access logged; user visibility limited to their linked threads.

## 23) Non-Goals (v1)

* IMAP ingestion (SMTP/POP) – **out of scope** for MVP.
* Unbounded model web browsing – **disallowed** (only SSRF-safe MCP tools).
* Human-invisible auto-actions – **never** without threshold/pass or signed link.

## 24) Change Management

* Update **this file** alongside any new subsystem or config switches.
* Keep **Project Structure** accurate—add/remove paths here as you change the repo.
* Migrations must remain **fresh-green** (`php artisan migrate:fresh`).

### Appendix A — Plan JSON (example, Plain)

```json
{
  "steps": [
    { "state": ["received=true","scanned=false"],
      "action": {"name":"ScanAttachment","args":{}},
      "next_state": ["scanned=true"] },

    { "state": ["scanned=true","extracted=false"],
      "action": {"name":"ExtractText","args":{}},
      "next_state": ["text_available=true"] },

    { "state": ["text_available=true","summary_ready=false"],
      "action": {"name":"SummarizeAttachment","args":{"max_words":120}},
      "next_state": ["summary_ready=true"] }
  ]
}
```

> Gate: Only allow `SendReply` when `summary_ready=true` **and** `confidence>=0.75`.

### Appendix B — Minimal `config/llm.php` expectations (descriptive)

* `timeout_ms`, `retry.max`
* `routing.roles`: { CLASSIFY, GROUNDED, SYNTH } each mapping to provider+model; booleans for `tools` and `reasoning`.
* `caps`: `input_tokens`, `output_tokens`
* `providers`: openai/anthropic/ollama endpoints + keys.

### Appendix C — Cursor Prompts (mapping)

* **Make a Plan** → deep repo scan, DB ERD, gaps table, TODAY’S PLAN.
* **Execute the Plan** → implement with tests/logging/i18n; maintain Project Structure; commit discipline.
* **Demo & Verification** → run `scenario:run`, `agent:metrics`, tinker one-liners to print evidence; produce ✅/❌ checklist.
* **Fix-it** → diagnose from logs + evidence; modify create-migrations only; rerun.

> Keep the **symbolic plan validation** doc block in any new complex feature (for future readers). 

## Database Schema Index (Generated)

Source: runtime DB introspection (preferred) or migrations parsing. Includes pgvector details. Nullability and defaults reflect the live database.

```
TABLE: accounts
  columns:
    - id bpchar [not null]
    - name varchar [not null]
    - settings_json json [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
  primary_key: id
  indexes:
    - accounts_name_index (name) USING btree
  foreign_keys:

TABLE: users
  columns:
    - id bpchar [not null]
    - name varchar [not null]
    - display_name varchar [nullable]
    - email varchar [not null]
    - email_verified_at timestamp [nullable]
    - password varchar [nullable]
    - locale varchar [nullable]
    - timezone varchar [nullable]
    - status varchar [nullable]
    - remember_token varchar [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
  primary_key: id
  indexes:
    - users_email_unique (email) USING btree [unique]
    - users_locale_index (locale) USING btree
  foreign_keys:

TABLE: memberships
  columns:
    - id bpchar [not null]
    - account_id bpchar [not null]
    - user_id bpchar [not null]
    - role varchar [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
  primary_key: id
  indexes:
    - memberships_account_id_user_id_unique (account_id,user_id) USING btree [unique]
  foreign_keys:
    - account_id → accounts.id [onDelete=cascade] [onUpdate=no action]
    - user_id → users.id [onDelete=cascade] [onUpdate=no action]

TABLE: contacts
  columns:
    - id bpchar [not null]
    - account_id bpchar [not null]
    - email varchar [not null]
    - name varchar [nullable]
    - meta_json json [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
  primary_key: id
  indexes:
    - contacts_account_id_email_unique (account_id,email) USING btree [unique]
  foreign_keys:
    - account_id → accounts.id [onDelete=cascade] [onUpdate=no action]

TABLE: contact_links
  columns:
    - id bpchar [not null]
    - contact_id bpchar [not null]
    - user_id bpchar [not null]
    - status varchar [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
  primary_key: id
  indexes:
    - contact_links_contact_id_user_id_unique (contact_id,user_id) USING btree [unique]
  foreign_keys:
    - contact_id → contacts.id [onDelete=cascade] [onUpdate=no action]
    - user_id → users.id [onDelete=cascade] [onUpdate=no action]

TABLE: threads
  columns:
    - id bpchar [not null]
    - account_id bpchar [not null]
    - subject varchar [nullable]
    - starter_message_id bpchar [nullable]
    - context_json json [nullable]
    - version int4 [not null default=0]
    - version_history json [nullable]
    - last_activity_at timestamptz [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
  primary_key: id
  indexes:
    - threads_account_id_index (account_id) USING btree
    - threads_last_activity_at_index (last_activity_at) USING btree
  foreign_keys:
    - account_id → accounts.id [onDelete=cascade] [onUpdate=no action]
    - starter_message_id → email_messages.id [onDelete=set null] [onUpdate=no action]

TABLE: email_messages
  columns:
    - id bpchar [not null]
    - thread_id bpchar [not null]
    - direction varchar [not null]
    - processing_status varchar [nullable]
    - message_id varchar [not null]
    - in_reply_to varchar [nullable]
    - references varchar [nullable]
    - from_email varchar [nullable]
    - from_name varchar [nullable]
    - to_json json [nullable]
    - cc_json json [nullable]
    - bcc_json json [nullable]
    - subject varchar [nullable]
    - headers_json json [nullable]
    - provider_message_id varchar [nullable]
    - delivery_status varchar [nullable]
    - delivery_error_json json [nullable]
    - body_text text [nullable]
    - body_html text [nullable]
    - x_thread_id varchar [nullable]
    - raw_size_bytes int8 [nullable]
    - processed_at timestamptz [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
    - body_embedding VECTOR(1024) [nullable]
  primary_key: id
  indexes:
    - email_messages_body_embedding_idx (body_embedding vector_cosine_ops) USING IVFFlat(lists=100)
    - email_messages_direction_delivery_status_index (direction,delivery_status) USING btree
    - email_messages_direction_processing_status_index (direction,processing_status) USING btree
    - email_messages_from_email_index (from_email) USING btree
    - email_messages_in_reply_to_index (in_reply_to) USING btree
    - email_messages_message_id_trgm (message_id) USING gin
    - email_messages_message_id_unique (message_id) USING btree [unique]
    - email_messages_thread_id_index (thread_id) USING btree
  foreign_keys:
    - thread_id → threads.id [onDelete=cascade] [onUpdate=no action]

TABLE: email_attachments
  columns:
    - id bpchar [not null]
    - email_message_id bpchar [not null]
    - filename varchar [nullable]
    - mime varchar [nullable]
    - size_bytes int8 [nullable]
    - storage_disk varchar [nullable]
    - storage_path varchar [nullable]
    - scan_status varchar [nullable]
    - scan_result varchar [nullable]
    - scanned_at timestamptz [nullable]
    - extract_status varchar [nullable]
    - extract_result_json json [nullable]
    - extracted_at timestamptz [nullable]
    - summary_text text [nullable]
    - summarized_at timestamptz [nullable]
    - meta_json json [nullable]
    - summarize_json json [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
  primary_key: id
  indexes:
    - email_attachments_email_message_id_index (email_message_id) USING btree
    - email_attachments_scan_status_extract_status_index (scan_status,extract_status) USING btree
  foreign_keys:
    - email_message_id → email_messages.id [onDelete=cascade] [onUpdate=no action]

TABLE: attachment_extractions
  columns:
    - id bpchar [not null]
    - attachment_id bpchar [not null]
    - text_excerpt text [nullable]
    - text_disk varchar [nullable]
    - text_path varchar [nullable]
    - text_bytes int8 [nullable]
    - pages int4 [nullable]
    - summary_json json [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
    - text_embedding VECTOR(1024) [nullable]
  primary_key: id
  indexes:
    - attachment_extractions_attachment_id_index (attachment_id) USING btree
    - attachment_extractions_text_embedding_idx (text_embedding vector_cosine_ops) USING IVFFlat(lists=100)
  foreign_keys:
    - attachment_id → email_attachments.id [onDelete=cascade] [onUpdate=no action]

TABLE: memories
  columns:
    - id bpchar [not null]
    - scope varchar [not null]
    - scope_id bpchar [nullable]
    - key varchar [not null]
    - value_json json [nullable]
    - confidence float8 [nullable]
    - ttl_category varchar [nullable]
    - expires_at timestamptz [nullable]
    - version int4 [not null default=1]
    - supersedes_id bpchar [nullable]
    - provenance varchar [nullable]
    - first_seen_at timestamptz [nullable]
    - last_seen_at timestamptz [nullable]
    - last_used_at timestamptz [nullable]
    - usage_count int4 [not null default=0]
    - meta json [nullable]
    - email_message_id varchar [nullable]
    - thread_id bpchar [nullable]
    - created_at timestamptz [nullable]
    - updated_at timestamptz [nullable]
    - deleted_at timestamp [nullable]
    - content_embedding VECTOR(1024) [nullable]
  primary_key: id
  indexes:
    - memories_content_embedding_idx (content_embedding vector_cosine_ops) USING IVFFlat(lists=100)
    - memories_last_used_at_usage_count_index (last_used_at,usage_count) USING btree
    - memories_scope_scope_id_key_index (scope,scope_id,key) USING btree
    - memories_ttl_category_expires_at_index (ttl_category,expires_at) USING btree
  foreign_keys:

... (Other tables elided here are unchanged framework/queue tables and small join tables; see migrations for full details.)
```

## Eloquent Model & Relationship Map (Generated)

Discovered by scanning `app/Models`. Key type inferred by `HasUlids` and DB types; relationships list explicit FKs when provided.

```
\App\Models\Account [table=accounts] [key=ulid] [timestamps=on]
  casts: [settings_json=array]
  relationships:
    - users : belongsToMany(\App\Models\User, pivot=memberships)
    - threads : hasMany(\App\Models\Thread, fk=account_id)
    - emailMessages : hasMany(\App\Models\EmailMessage, fk=account_id)
    - actions : hasMany(\App\Models\Action, fk=account_id)
    - attachments : hasMany(\App\Models\Attachment, fk=account_id)
    - memories : hasMany(\App\Models\Memory, fk=account_id)
    - memberships : hasMany(\App\Models\Membership, fk=account_id)
    - contacts : hasMany(\App\Models\Contact, fk=account_id)
    - agents : hasMany(\App\Models\Agent, fk=account_id)
    - tasks : hasMany(\App\Models\Task, fk=account_id)
    - events : hasMany(\App\Models\Event, fk=account_id)

\App\Models\User [table=users] [key=ulid] [timestamps=on]
  casts: [email_verified_at=datetime, password=hashed]
  relationships:
    - accounts : belongsToMany(\App\Models\Account, pivot=memberships)
    - memberships : hasMany(\App\Models\Membership, fk=user_id)
    - identities : hasMany(\App\Models\UserIdentity, fk=user_id)
    - contactLinks : hasMany(\App\Models\ContactLink, fk=user_id)

\App\Models\Thread [table=threads] [key=ulid] [timestamps=on]
  casts: [context_json=array, version_history=array, last_activity_at=datetime]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=account_id)
    - starterMessage : belongsTo(\App\Models\EmailMessage, fk=starter_message_id)
    - emailMessages : hasMany(\App\Models\EmailMessage, fk=thread_id)
    - actions : hasMany(\App\Models\Action, fk=thread_id)
    - memories : hasMany(\App\Models\Memory, fk=thread_id)
    - metadata : hasMany(\App\Models\ThreadMetadata, fk=thread_id)

\App\Models\EmailMessage [table=email_messages] [key=ulid] [timestamps=on]
  casts: [to_json=array, cc_json=array, bcc_json=array, headers_json=array, delivered_at=datetime, processed_at=datetime]
  relationships:
    - thread : belongsTo(\App\Models\Thread, fk=thread_id)
    - attachments : hasMany(\App\Models\Attachment, fk=email_message_id)

\App\Models\Attachment [table=email_attachments] [key=ulid] [timestamps=on]
  casts: [extract_result_json=array, meta_json=array, summarize_json=array]
  relationships:
    - emailMessage : belongsTo(\App\Models\EmailMessage, fk=email_message_id)

\App\Models\AttachmentExtraction [table=attachment_extractions] [key=ulid] [timestamps=on]
  casts: [summary_json=array]
  relationships:
    - attachment : belongsTo(\App\Models\Attachment, fk=attachment_id)

\App\Models\Memory [table=memories] [key=ulid] [timestamps=on]
  casts: [value_json=array, meta=array]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=scope_id)
    - thread : belongsTo(\App\Models\Thread, fk=scope_id)

\App\Models\Action [table=actions] [key=ulid] [timestamps=on]
  casts: [payload_json=array, expires_at=datetime, completed_at=datetime, last_clarification_sent_at=datetime]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=account_id)
    - thread : belongsTo(\App\Models\Thread, fk=thread_id)

\App\Models\Agent [table=agents] [key=ulid] [timestamps=on]
  casts: [capabilities_json=array, cost_hint=integer, reliability=float, reliability_samples=integer]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=account_id)
    - tasks : hasMany(\App\Models\Task, fk=agent_id)
    - specializations : hasMany(\App\Models\AgentSpecialization, fk=agent_id)

\App\Models\AgentRun [table=agent_runs] [key=ulid] [timestamps=on]
  casts: [state=array, round_no=integer]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=account_id)
    - thread : belongsTo(\App\Models\Thread, fk=thread_id)

\App\Models\AgentStep [table=agent_steps] [key=ulid] [timestamps=on]
  casts: [input_json=array, output_json=array, confidence=float, vote_score=float]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=account_id)
    - thread : belongsTo(\App\Models\Thread, fk=thread_id)
    - emailMessage : belongsTo(\App\Models\EmailMessage, fk=email_message_id)
    - action : belongsTo(\App\Models\Action, fk=action_id)
    - contact : belongsTo(\App\Models\Contact, fk=contact_id)
    - user : belongsTo(\App\Models\User, fk=user_id)

\App\Models\AgentSpecialization [table=agent_specializations] [key=ulid] [timestamps=on]
  casts: [capabilities=array, confidence_threshold=float, is_active=boolean]
  relationships:
    - agent : belongsTo(\App\Models\Agent, fk=agent_id)

\App\Models\AvailabilityPoll [table=availability_polls] [key=ulid] [timestamps=on]
  casts: [options_json=array, closed_at=datetime]
  relationships:
    - thread : belongsTo(\App\Models\Thread, fk=thread_id)
    - votes : hasMany(\App\Models\AvailabilityVote, fk=poll_id)

\App\Models\AvailabilityVote [table=availability_votes] [key=ulid] [timestamps=on]
  casts: [choices_json=array]
  relationships:
    - poll : belongsTo(\App\Models\AvailabilityPoll, fk=poll_id)
    - user : belongsTo(\App\Models\User, fk=user_id)
    - contact : belongsTo(\App\Models\Contact, fk=contact_id)

\App\Models\AuthChallenge [table=auth_challenges] [key=ulid] [timestamps=on]
  casts: [expires_at=datetime, consumed_at=datetime]
  relationships:
    - userIdentity : belongsTo(\App\Models\UserIdentity, fk=user_identity_id)

\App\Models\Contact [table=contacts] [key=ulid] [timestamps=on]
  casts: [meta_json=array]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=account_id)
    - contactLinks : hasMany(\App\Models\ContactLink, fk=contact_id)

\App\Models\ContactLink [table=contact_links] [key=ulid] [timestamps=on]
  relationships:
    - contact : belongsTo(\App\Models\Contact, fk=contact_id)
    - user : belongsTo(\App\Models\User, fk=user_id)

\App\Models\Event [table=events] [key=ulid] [timestamps=on]
  casts: [starts_at=datetime, ends_at=datetime]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=account_id)
    - participants : hasMany(\App\Models\EventParticipant, fk=event_id)

\App\Models\EventParticipant [table=event_participants] [key=ulid] [timestamps=on]
  relationships:
    - event : belongsTo(\App\Models\Event, fk=event_id)
    - user : belongsTo(\App\Models\User, fk=user_id)
    - contact : belongsTo(\App\Models\Contact, fk=contact_id)

\App\Models\Task [table=tasks] [key=ulid] [timestamps=on]
  casts: [input_json=array, result_json=array, started_at=datetime, finished_at=datetime]
  relationships:
    - account : belongsTo(\App\Models\Account, fk=account_id)
    - thread : belongsTo(\App\Models\Thread, fk=thread_id)
    - agent : belongsTo(\App\Models\Agent, fk=agent_id)

\App\Models\ThreadMetadata [table=thread_metadata] [key=ulid] [timestamps=on]
  casts: [value=array]
  relationships:
    - thread : belongsTo(\App\Models\Thread, fk=thread_id)

\App\Models\ApiToken [table=api_tokens] [key=ulid] [timestamps=on]
  casts: [abilities=array, last_used_at=datetime, expires_at=datetime]
  relationships:
    - user : belongsTo(\App\Models\User, fk=user_id)
    - account : belongsTo(\App\Models\Account, fk=account_id)

\App\Models\EmailInboundPayload [table=email_inbound_payloads] [key=ulid] [timestamps=on]
  casts: [meta_json=array, signature_verified=boolean, received_at=datetime, purge_after=datetime]
  relationships:
```

## Postmark Setup (Inbound & Threading)

1) Create an Inbound Stream in Postmark and set the Webhook URL to:
   - `https://WEBHOOK_USER:WEBHOOK_PASS@your-domain.test/webhooks/postmark-inbound`
   - Use the exact Basic Auth creds from `.env` (`WEBHOOK_USER`, `WEBHOOK_PASS`).

2) Enable HMAC signing in Postmark. Server must verify against the raw request body.
   - Our `VerifyWebhookSignature` middleware validates Basic Auth + HMAC (raw body required).

3) Threading pattern for replies:
   - Set Reply-To as: `local+<thread_id>@inbound.postmarkapp.com`.
   - We also read RFC headers `Message-ID`, `In-Reply-To`, `References` and optional `X-Thread-ID`.

4) Quick test via Postmark’s webhook tester:
   - Post sample JSON to the URL above.
   - Expected logs: enqueued `ProcessWebhookPayload`, created `email_messages` row, optional `attachments` queued.

## Troubleshooting (Plain)

LLM

- Timeouts: ensure Ollama is running; increase `LLM_TIMEOUT_MS`; reduce model size or tokens.
- Slow: switch role to smaller model; reduce `LLM_SYNTH_COMPLEXITY_TOKENS`.

Vectors

- Dim mismatch: verify `EMBEDDINGS_DIM`; run migrations; backfill embeddings.

Attachments

- Scan failed: check ClamAV container logs; ensure daemon on `127.0.0.1:3310`.
- Upload issues: verify disk permissions and free space.

Webhook

- Signature fails: check `.env` creds; ensure raw body used for HMAC.

Queue

- Backlogs: scale workers; monitor Horizon; prioritize `attachments` queue.

Database

- Connection errors: verify credentials; `psql` into container to confirm.

Localization

- Missing translations: add to `resources/lang/*/*.php`; `php artisan optimize:clear`.

Performance

- High memory: chunk processing, limit concurrency; Ollama scheduling helps.

Backup Strategy

```bash
# Nightly DB + files backup to S3
pg_dump --no-owner "$DATABASE_URL" | gzip | aws s3 cp - s3://your-bucket/backups/db-$(date +%F).sql.gz
tar -czf attachments-$(date +%F).tgz storage/app/public && aws s3 cp attachments-$(date +%F).tgz s3://your-bucket/backups/
```

## pgvector: Enablement & Indexes (copy-paste SQL)

Align DIM and distance with `.env` (`EMBEDDINGS_DIM=1024`, `EMBEDDINGS_DISTANCE=cosine`, `EMBEDDINGS_INDEX_LISTS=100`). PostgreSQL 18 AIO improves bulk index builds; still keep ANALYZE.

```sql
-- Enable extensions
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Columns
ALTER TABLE email_messages         ADD COLUMN IF NOT EXISTS body_embedding    vector(1024);
ALTER TABLE attachment_extractions ADD COLUMN IF NOT EXISTS text_embedding    vector(1024);
ALTER TABLE memories               ADD COLUMN IF NOT EXISTS content_embedding vector(1024);

-- IVFFlat indexes (cosine)
CREATE INDEX IF NOT EXISTS email_messages_body_embedding_idx
  ON email_messages USING ivfflat (body_embedding vector_cosine_ops) WITH (lists = 100);
CREATE INDEX IF NOT EXISTS attachment_extractions_text_embedding_idx
  ON attachment_extractions USING ivfflat (text_embedding vector_cosine_ops) WITH (lists = 100);
CREATE INDEX IF NOT EXISTS memories_content_embedding_idx
  ON memories USING ivfflat (content_embedding vector_cosine_ops) WITH (lists = 100);

-- Maintain stats
ANALYZE email_messages; ANALYZE attachment_extractions; ANALYZE memories;
```

## What’s Actually Built (Status)

| Area                         | Status |
|----------------------------- | ------ |
| Inbound webhook (Postmark)   | ✅     |
| RFC threading + X-Thread-ID  | ✅     |
| Attachments pipeline         | ✅     |
| LlmClient tool-enforced JSON | ✅     |
| Clarification loop           | ✅     |
| Multi-agent orchestration    | ✅     |
| Plan validator               | ✅     |
| Activity UI                  | ✅     |
| Metrics command              | ✅     |
| i18n (en, nl)                | ✅     |
| Golden-set eval              | ✅     |

## Roles & Permissions (Plain)

- Recipient: sees threads linked via `ContactLink` to their contacts.
- User: sees only their own account’s data; activity limited to their threads.
- Admin: full account scope; cannot see other accounts.
- Operator: infra/maintenance; no customer content by default.
- Enforcement: activity trace limited — “you only see traces for your own threads”.

## Symbolic Plan Validator — PlanReport shape

```json
{
  "valid": true,
  "failed_step": null,
  "hint": null,
  "auto_repair_applied": false,
  "facts_after": ["scanned=true","text_available=true","summary_ready=true"]
}
```

## AgentOps Trace Fields (Canonical)

provider, model, tokens_input, tokens_output, tokens_total, latency_ms, confidence, agent_role, round_no, coalition_id?, vote_score?, decision_reason?, input_json, output_json.

## Typed Memories (Decision | Insight | Fact)

- Types: `Decision`, `Insight`, `Fact` (documented usage in `value_json` and `meta`).
- Dedupe: use `content_hash` and optional `provenance_ids[]` inside `meta` (planned extension).

## MCP UrlGuard (Network Safety)

- Schemes: allow http/https only; deny file:// and others.
- DNS: resolve public hosts only; deny RFC1918/localhost/loopback.
- Size: max body ~2KB per fetch tool.
- Redirects: limited, no internal IPs; SSRF guarded.

## Docker Compose (Example)

```yaml
services:
  app:
    build: .
    env_file: .env
    depends_on: [db, redis]
  db:
    image: postgres:18
    environment:
      POSTGRES_DB: agent_ai
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: ""
    command: ["postgres", "-c", "shared_preload_libraries=vector"]
    volumes: [dbdata:/var/lib/postgresql/data]
  redis:
    image: redis:7
  clamav:
    image: clamav/clamav:latest
  ollama:
    image: ollama/ollama:latest
volumes:
  dbdata: {}
```

## Golden-set Evaluation

- Target ≥100 labeled examples per action; measure precision/recall monthly. `php artisan agent:eval --since=30d` (planned command) prints metrics.

## Operational SLOs & Knobs

- LLM timeout/retries: see `.env` (`LLM_TIMEOUT_MS`, `LLM_RETRY_MAX`).
- Retrieval `top_k`: default 6 (see `config/llm.php` / `GroundingService`).
- Hit-rate cutoff: `LLM_GROUNDING_HIT_MIN` (0–1.0).

## Evaluation, Metrics & Compliance

Golden-set

- ≥100 labeled examples per action; monthly precision/recall; `php artisan agent:eval --since=30d` (planned).

SLOs

- Latency: P50 < 30s, P95 < 10m.
- Grounding hit-rate: > 0.35 (raise for stricter grounding).
- Monitor queues (Horizon), DB IO (PostgreSQL 18 AIO), and LLM provider health.

Compliance

- GDPR-first: data minimization; DPIA advised for production deployments.
- Retention: inbound payloads via `purge_after`; attachments via `config/attachments.php`; memories via `config/memory.php`.
- Postmark Data Removal API integrated for erasure on request.

Roles & Permissions

- Recipient/User/Admin/Operator scopes as documented; enforcement via policies and relationship filters.

## Post-incident Flow (Infected Attachments)

- Incident email lists filenames and reasons (localized).
- Downloads blocked with a friendly page; quarantine retained for audit.

## Quickstart Smoke Test

- `php artisan scenario:run` → seeds demo thread; expect success log and dashboard visible entries.
- `php artisan agent:metrics --since=7d --limit=20` → prints recent run metrics (counts, p50 latency).

## Cursor Doc-Sync Prompts

- Project Structure Sync: generate tree excluding vendor/node_modules/etc.
- Doc Sync (tree + schema + relationships): regenerate sections and replace in `CURSOR-README.md`.

## i18n Keys Map

| View area                 | Lang file(s)                         |
|-------------------------- |--------------------------------------|
| Auth pages/emails         | `resources/lang/*/auth.php`          |
| Emails (generic)          | `resources/lang/*/emails.php`        |
| UI messages               | `resources/lang/*/messages.php`      |
| Buttons/labels            | `resources/lang/*/messages.php`      |
| Validation                | `resources/lang/*/validation.php`    |
| RTL support               | Flowbite + Tailwind v4 (`dir="rtl"`) |

## Compliance Retention Defaults

- Inbound payloads: see DB `purge_after` and policy in code.
- Attachments: see `config/attachments.php`.
- Memories: see `config/memory.php`.

## Known Pitfalls

- Ollama tag missing → pull the configured model tag.
- EMBEDDINGS_DIM mismatch → migrate fresh and backfill embeddings.
- ngrok host header must match app URL.