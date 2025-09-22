# Agent AI – Technical Design Document

## Executive Summary

Agent AI is an email-centered automation system built on Laravel 12. It links incoming emails to threads, interprets free text with a Large Language Model (LLM), and executes actions via signed links or controlled tool calls. Tool calls run through a **custom MCP layer** (Model Context Protocol) that enforces JSON schemas and exposes SSRF-safe tools. The system supports **attachments** (txt, md, csv, pdf, etc.) including virus scanning, extraction, and summarization. Passwordless login, Flowbite/Tailwind UI, and a future-proof data model reduce friction. An LLM is always available; when intent is unclear, the system asks follow-up questions until intent is clear (maximum 2 rounds). Redis/Horizon deliver asynchronous reliability. PostgreSQL guarantees integrity and versioned “memories” with TTL/decay. Postmark handles inbound/outbound email with robust RFC threading. The design is self-hostable with Docker.

## Project Overview

### Business Context

* Target audience: small teams and self-hosters who want email as the UI.
* Value: fewer tools, low learning curve, clear audit trail.
* Compliance: GDPR-first, EU hosting option, data minimization.

### Technical Scope

* Inbound via Postmark webhook (HMAC + IP allowlist).
* Threading with `Message-ID`, `In-Reply-To`, `References`.
* LLM-first interpretation and follow-up loop with signed links.
* **MCP layer**: custom Laravel component with schema-driven tools.
* Passwordless auth (magic link + code).
* Blade/Flowbite/Tailwind UI with i18n.
* Queues via Redis; Horizon for monitoring.
* Docker Compose for self-hosting.
* **Attachments**: storage, virus scan, extraction, and LLM context.

### Key Stakeholders

* Product Owner: prioritization and action taxonomy.
* Engineering Team: Laravel 12 backend, Blade UI, MCP.
* DevOps: containers, secrets, monitoring, backups.
* Compliance/Legal: DPIA, data processing agreement.
* End Users: email recipients and web confirmers.

### Assumptions

* Postmark is available.
* External LLM provider is configured; local fallback via Ollama.
* SMTP/IMAP inbound is out of scope for MVP.
* One region; multi-region later.

### Current Development Status

**✅ COMPLETED: Phase 1A - Database & Models**
- Complete database schema with 29 migrations
- 21 Eloquent models with full relationships
- ULID primary keys, JSONB fields, PostgreSQL optimizations
- ThreadResolver service and ProcessInboundEmail job
- Postmark webhook controller with HMAC validation

**🔄 IN PROGRESS: Phase 1B - Auth & UI Foundation**
- Passwordless authentication (ChallengeController, VerifyController)
- Basic Blade/Flowbite dashboard and thread pages
- i18n middleware and language detection

**📋 NEXT: Phase 2 - LLM & MCP**
- LLM client with Ollama fallback and provider support
- MCP layer (ToolRegistry, McpController, tool schemas)
- Action interpretation and clarification loop
- Memory gate with TTL/decay

**📋 FUTURE: Phase 3 - Attachments & Quality**
- Attachment pipeline (ClamAV scan, text extraction, summarization)
- Signed downloads and security hardening
- Horizon monitoring, testing, and deployment

**Roles & Permissions**
- **Recipient**: Email interactions, signed link confirmations
- **User**: Full web access after email upgrade, profile management
- **Admin**: Account settings, memories management, user administration
- **Operator**: Horizon monitoring, queue management, system diagnostics

### Tech Stack & Versions (Exact)

| Component | Technology | Version | Notes |
|-----------|------------|---------|-------|
| Framework | Laravel | 12.x | Latest stable |
| PHP Runtime | PHP | 8.4 | LTS, performance optimized |
| Database | PostgreSQL | 17+ | JSONB, constraints, indexes |
| Cache/Queue | Redis | 7.x | Reliable async operations |
| Mail Service | Postmark | Latest | Inbound JSON + deliverability |
| UI Framework | Blade + Tailwind + Flowbite | Tailwind ^4.0, Flowbite ^2.0 | Responsive, accessible |
| Icons | Lucide | latest | Accessible, SVG-based icons |
| LLM | Ollama + Providers | Ollama latest, OpenAI/Anthropic APIs | Fallback architecture |
| AV Scanner | ClamAV | Latest | Virus/malware detection |
| PDF Processing | spatie/pdf-to-text | Latest | Text extraction |
| Testing | PHPUnit | ^11.0 | Laravel default (no Pest conflicts) |
| Code Quality | PHPStan | Level 8 | Static analysis |
| Code Style | Laravel Pint | Latest | PSR-12 compliant |
| JavaScript Linting | ESLint | ^9.0 | With Prettier integration |
| Code Formatting | Prettier | ^3.0 | Consistent JS/CSS formatting |
| Container | Docker/Compose | Latest | Self-hosting |
| Development | Laravel Herd | Latest | Local HTTPS server |

### Coding Conventions & Patterns

**Database Conventions**
- All primary keys: ULID (`HasUlids` trait)
- Column names: snake_case (e.g., `thread_id`, `account_id`)
- Foreign keys: `{table}_id` pattern
- JSON columns: `{name}_json` suffix, stored as JSONB
- Boolean columns: `{action}_at` for timestamps, `{is/has}_feature` for flags

**Code Organization**
- **Controllers**: Thin, only routing and response formatting
- **Services**: Business logic (e.g., `LlmClient`, `AttachmentService`)
- **Jobs**: Asynchronous operations (e.g., `ProcessInboundEmail`)
- **Policies**: Authorization logic (e.g., `ActionPolicy`)
- **Models**: Data access with casts and relationships
- **Requests**: Form validation (`FormRequest` classes)

**Frontend Patterns**
- Blade components for reusable UI elements
- Tailwind utility classes, Flowbite components
- i18n with `__()` helper and language files
- Form requests for validation, CSRF protection
- Signed links for secure actions (15-60 min expiry)

**Security Patterns**
- Passwordless auth with timed challenges
- HMAC validation for webhooks
- SSRF prevention in MCP tools
- Signed downloads with nonce
- Input sanitization and validation

**Naming Conventions**
- Classes: PascalCase (e.g., `ProcessInboundEmail`)
- Methods: camelCase (e.g., `processInboundEmail()`)
- Variables/Properties: camelCase (e.g., `$cleanReply`)
- Constants: UPPER_SNAKE_CASE
- Routes: kebab-case (e.g., `/webhooks/postmark-inbound`)

### Project Structure (Complete Directory Tree)

```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Webhook/
│   │   │       └── PostmarkInboundController.php
│   │   └── Middleware/
│   ├── Jobs/
│   │   └── ProcessInboundEmail.php
│   ├── Models/
│   │   ├── Account.php
│   │   ├── Action.php
│   │   ├── Agent.php
│   │   ├── ApiToken.php
│   │   ├── Attachment.php
│   │   ├── AttachmentExtraction.php
│   │   ├── AuthChallenge.php
│   │   ├── AvailabilityPoll.php
│   │   ├── AvailabilityVote.php
│   │   ├── Contact.php
│   │   ├── ContactLink.php
│   │   ├── EmailInboundPayload.php
│   │   ├── EmailMessage.php
│   │   ├── Event.php
│   │   ├── EventParticipant.php
│   │   ├── Memory.php
│   │   ├── Membership.php
│   │   ├── Task.php
│   │   ├── Thread.php
│   │   ├── User.php
│   │   ├── UserIdentity.php
│   │   └── [21 total models]
│   └── Services/
│       └── ThreadResolver.php
├── bootstrap/
│   ├── app.php
│   └── cache/
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── database.php
│   ├── filesystems.php
│   ├── llm.php
│   ├── logging.php
│   ├── mail.php
│   ├── mcps.php
│   ├── prompts.php
│   ├── queue.php
│   ├── services.php
│   └── session.php
├── database/
│   ├── factories/
│   │   ├── UserFactory.php
│   │   └── ThreadFactory.php
│   ├── migrations/
│   │   └── [all migration files from Appendix I]
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── AccountSeeder.php
├── public/
│   ├── index.php
│   └── [assets]
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.js
│   │   └── bootstrap.js
│   └── lang/
│       ├── en_US/
│       │   ├── actions.php
│       │   ├── auth.php
│       │   ├── messages.php
│       │   └── validation.php
│       └── nl_NL/
│           ├── actions.php
│           ├── auth.php
│           ├── messages.php
│           └── validation.php
├── routes/
│   ├── api.php
│   ├── channels.php
│   ├── console.php
│   └── web.php
├── storage/
│   ├── app/
│   │   └── attachments/
│   ├── framework/
│   │   ├── cache/
│   │   ├── sessions/
│   │   ├── testing/
│   │   └── views/
│   └── logs/
├── tests/
│   ├── Feature/
│   │   ├── Api/
│   │   │   ├── ActionDispatchTest.php
│   │   │   └── WebhookTest.php
│   │   ├── Auth/
│   │   │   ├── ChallengeTest.php
│   │   │   └── PasswordlessLoginTest.php
│   │   └── Mcp/
│   │       └── ToolExecutionTest.php
│   ├── Unit/
│   │   ├── Jobs/
│   │   │   ├── ProcessInboundEmailTest.php
│   │   │   └── ScanAttachmentTest.php
│   │   ├── Services/
│   │   │   ├── AttachmentServiceTest.php
│   │   │   ├── LlmClientTest.php
│   │   │   └── ThreadResolverTest.php
│   │   └── Mcp/
│   │       └── ToolRegistryTest.php
│   └── TestCase.php
├── docker/
│   ├── Dockerfile
│   ├── docker-compose.yml
│   └── nginx.conf
├── .env.example
├── .vscode/
│   ├── extensions.json
│   └── tasks.json
├── artisan
├── composer.json
├── composer.lock
├── package.json
├── package-lock.json
├── tailwind.config.js
├── postcss.config.js
├── phpstan.neon
├── pint.json
├── .eslintrc.js
├── .prettierrc
├── docker-compose.yml
├── README.md
├── .gitignore
└── .gitattributes
```

## What's Actually Built (Current State)

This README reflects the **current implementation** as of our development session. Many sections below describe future features not yet implemented.

**✅ CURRENTLY IMPLEMENTED:**
- **Database**: Complete PostgreSQL schema with 29 migrations, 21 Eloquent models
- **Webhook**: Postmark inbound controller with HMAC validation and encrypted payload storage
- **Threading**: RFC 5322 email threading via ThreadResolver service
- **Jobs**: ProcessInboundEmail job with email parsing, threading, and reply cleaning
- **Models**: All domain models with ULID PKs, JSONB casts, and comprehensive relationships
- **Authentication**: Passwordless auth with email codes and magic links
- **UI**: Basic Blade/Flowbite dashboard and auth pages

**📋 NOT YET IMPLEMENTED:**
- LLM client and providers
- MCP layer and tools
- Attachment processing pipeline
- Clarification loop and action dispatcher
- Memory gate with TTL/decay
- UI dashboard and thread views

## System Architecture

### High-level Architecture Diagram

```mermaid
flowchart LR
  subgraph Email
    U[Recipient] --> PM[Postmark Inbound]
  end

  PM -->|HMAC Webhook| API[/Laravel /webhooks/postmark-inbound/]
  API --> Q[Redis Queue]

  Q --> J1[ProcessInboundEmail]
  J1 --> T[Thread Resolver]
  J1 --> CL[Clean Reply Extractor]
  J1 --> A1[Attachment Registrar]
  A1 --> VS[ClamAV Scan]
  VS -->|ok| AX[Attachment Extractor]
  AX --> ATX[Text/Summary Cache]
  J1 --> L1[LLM: Action Interpreter]

  L1 -->|Action JSON| ACT[Action Dispatcher]
  ACT --> MCP[MCP Layer (Tools/Prompts/Resources)]
  MCP --> DB[(PostgreSQL)]
  ACT --> OUT[Mailer (Postmark Outbound)]
  OUT --> U

  subgraph Web UI
    U2[Browser] --> SL[Signed Link /a/{action}]
    SL --> ACT
    U2 --> APP[Blade/Flowbite Forms]
    APP --> ACT
    U2 --> DLS[Signed Download /attachments/{id}]
  end

  J1 --> L2[LLM: Memory Gate]
  L2 --> MEM[memories]
  DB <--> APP

  L1 -. timeout .-> ALT[Fallback: local LLM]
  ALT -. failure .-> OPT[Options mail with signed links]
```

### Component Descriptions

* **Webhook Controller**: validates HMAC/IP, stores payload encrypted, queues processing.
* **ProcessInboundEmail**: resolves thread, extracts clean reply, registers attachments, triggers scan/extraction, calls LLMs, writes memories.
* **Attachment Pipeline**: ClamAV scan, MIME/size checks, extraction (txt/md/csv direct; pdf via pdf-to-text), signed downloads.
* **Action Dispatcher**: idempotent execution, MCP tool calls, outbound in the same thread.
* **MCP layer**: internal router with JSON schemas for tools, prompts, and resources; ToolRegistry with explicit bindings.
* **LLM Client**: provider + fallback, timeouts/retry, token caps, confidence calibration.
* **Auth**: passwordless challenges (codes and magic links).
* **UI**: Blade/Flowbite wizards, i18n middleware.
* **Observability**: Horizon, optional Pulse/Sentry, LLM call logging.

### Technology Stack (Laravel 12)

| Component   | Technology                       | Version | Rationale                       |
| ----------- | -------------------------------- | ------- | ------------------------------- |
| Framework   | Laravel                          | 12.x    | Jobs, Mail, Validation, Horizon |
| PHP Runtime | PHP                              | 8.4     | Performance, typing             |
| Database    | PostgreSQL                       | 17+     | JSONB, constraints, indexes     |
| Queue/Cache | Redis                            | 7.x     | Reliable async                  |
| Mail        | Postmark                         | n/a     | Inbound JSON + deliverability   |
| UI          | Blade + Tailwind + Flowbite      | latest  | Fast, accessible                |
| Icons       | Lucide                           | latest  | Accessible, SVG-based icons     |
| LLM         | Ollama + provider                | n/a     | Fallback and flexibility        |
| MCP         | Custom Laravel component         | n/a     | Safe schema-enforced calls      |
| AV Scan     | ClamAV (daemon)                  | latest  | Virus/malware detection         |
| PDF text    | poppler-utils/spatie/pdf-to-text | latest  | Extraction                      |
| Container   | Docker/Compose                   | latest  | Self-hosting                    |

## Functional Requirements

### User Stories

1. As a recipient, I want to approve via a one-click signed link so that I confirm fast.
2. As a recipient, I want to reply in natural language so that the system interprets my intent across languages.
3. As an owner, I want contacts to become users after the first valid action so that collaboration is seamless.
4. As an admin, I want to manage memories (view, purge, export) so that compliance is maintained.
5. As an operator, I want observability on queues and LLM calls so that I can diagnose issues quickly.
6. As a user, I want UI and emails in my language so that I understand actions clearly.
7. **As a recipient, I want to send attachments so that the system can use their content in actions.**

### Acceptance Criteria

* Story 1: Signed link with 15–60 min expiry; second click is idempotent; confirmation in the same thread.
* Story 2: Multilingual interpretation; when confidence < 0.75, max 2 clarification rounds; otherwise options email.
* Story 3: On click or reply ≥ 0.75: user + identity + membership are created; login email sent.
* Story 4: Memories have TTL/decay; supersede; admin can export/purge; provenance visible.
* Story 5: Horizon visible; logs show provider, model, latency, tokens, confidence, outcome.
* Story 6: Language detected; UI/emails in detected language; EN fallback; `Content-Language` set.
* **Story 7: Attachments ≤ 25MB each (default), safe MIME whitelist, mandatory ClamAV scan, extraction and summary available to the LLM, signed downloads.**

## Technical Implementation

### Database Schema

#### PK Strategy and ULID Rationale

* All domain tables: **ULID** as PK (`HasUlids`).
* Framework tables: Laravel defaults (jobs, failed_jobs, etc.).
* **Constraint additions** (delta vs previous version):

  * `threads.starter_message_id` → FK to `email_messages(id)`.
  * `email_attachments(email_message_id)` → FK (already present).
  * `memories` remains polymorphic via `scope/scope_id` (no FK); integrity via service layer.

#### Core Tables, Indexes, and Relations

Add casts where relevant:

```php
// app/Models/Action.php
protected $casts = ['payload_json' => 'array'];

// app/Models/Task.php
protected $casts = ['input_json' => 'array', 'result_json' => 'array'];
```

### Clarification Loop State

Add fields to `actions` via **alter migration**:

```php
// 2025_01_01_012000_alter_actions_add_clarification_state.php
Schema::table('actions', function (Blueprint $t) {
  $t->unsignedTinyInteger('clarification_rounds')->default(0);
  $t->unsignedTinyInteger('clarification_max')->default(2);
  $t->timestampTz('last_clarification_sent_at')->nullable();
});
```

Use these fields to reliably control the follow-up loop.

### MCP Layer (Planned)

**Status**: Not yet implemented. Will provide schema-driven tool calls with SSRF protection.

**Future Implementation:**
- ToolRegistry with explicit bindings
- Custom token guard for `api_tokens` table
- JSON schema validation for tool parameters
- Authorization checks preventing IDOR attacks
- No external fetch - only internal Storage access

### LLM Client (Implemented)

**Status**: Implemented with gpt-oss:20b model and Ollama fallback.

**Future Implementation:**
- Multi-provider support (OpenAI, Anthropic) with automatic failover
- Enhanced confidence score calibration and fallback logic
- Dynamic token limit adjustment based on model capabilities

### i18n: Language Detection (Planned)

**Status**: Not yet implemented. Will use on-prem language detection library.

**Future Implementation:**
- Language detection on clean reply text
- Supported locales: en_US, nl_NL, fr_FR, de_DE
- Fallback to en_US for unsupported languages
- Language-specific email templates and UI strings

### Attachments Processing (Planned)

**Status**: Models exist, but processing pipeline not yet implemented.

**Future Implementation:**
- MIME whitelist: text/*, application/pdf, text/csv, application/json
- Size limits: 25MB per file, 40MB total per email
- ClamAV virus scanning before extraction
- Text extraction: direct for txt/md/csv, spatie/pdf-to-text for PDFs
- Signed downloads with temporary URLs
- LLM summarization of attachment content

### API Endpoints

#### Currently Implemented

| Method | Path                       | Auth   | Purpose                 | Status |
| ------ | -------------------------- | ------ | ----------------------- | ------ |
| POST   | /webhooks/postmark-inbound | HMAC   | Receive inbound email   | ✅ Implemented |

#### Planned Implementation

**Public/External API:**
- `GET /a/{action}` - One-click action confirmations (signed links)
- `GET /login/{token}` - Magic link login verification
- `POST /auth/challenge` - Request passwordless authentication
- `POST /auth/verify` - Verify authentication code
- `GET /attachments/{id}` - Signed attachment downloads

**Internal/UI and MCP API:**
- `ANY /mcp/agent` - MCP tool execution endpoint
- `POST /api/actions/dispatch` - UI form action dispatch
- `GET /api/threads/{id}` - Thread detail view

**Error handling**: Standard HTTP status codes with JSON error responses.

### Laravel-Specific Patterns (Current)

**Models**: ULID primary keys with `HasUlids` trait, JSONB casts for flexible data storage.

**Migrations**: PSR-12 compliant, foreign key constraints with cascade deletes, GIN indexes on JSONB fields.

**Jobs**: `ProcessInboundEmail` job with basic structure, ready for LLM integration.

**Routes**: RESTful API design with consistent error handling.

**Future**: Policies, rate limiting, job chaining will be implemented as features are built.

## Non-Functional Requirements

### Performance

* LLM: P50 < 30 s; P95 < 10 min; timeout 10 min (async processing).
* Inbound → action ≤ 15 min P95 (with LLM interpretation).
* PDF extraction async; summarization on-demand or after extract job.

### Security

* SPF/DKIM/DMARC; List-Unsubscribe where needed.
* Webhook HMAC + IP allowlist; throttling.
* Signed links: expiry 15–60 min, nonce; no PII in URL; idempotent.
* Passwordless rate limits; timing-safe compares.
* OWASP: input validation, CSRF, XSS sanitization, **SSRF prevention** in MCP tools (no external fetch).
* **ClamAV** scan required; quarantine on detection; admin alert.

### Reliability

* Uptime 99.5%.
* Queue retries: 3 with backoff 5 s, 30 s, 2 min.
* LLM: 1 retry on 5xx/timeout; then local fallback; afterwards options email.
* Backups: daily; encryption at rest; monthly restore test.

### Scalability

* Horizontal worker scaling.
* Postgres pooling, targeted indexes.
* Redis cluster when >50 concurrent active users.
* Extraction jobs on a separate queue (`attachments`) with dedicated workers.

### Rate Limiting

| Context           | Rule                                        |
| ----------------- | ------------------------------------------- |
| /auth/challenge   | 5 per 15 min per identifier; 20/hour per IP |
| /auth/verify      | 10 per 15 min per identifier                |
| Webhook           | 120/min total; burst 30                     |
| Signed link route | 60/min per IP                               |
| LLM calls         | 10/min per thread; 100/hour per account     |
| Signed downloads  | 30/min per IP                               |

### LLM Token Caps & Confidence

* Input: 2000 tokens; thread summary 500; output 300.
* Confidence scale `[0,1]`. Auto ≥ 0.75; confirm 0.50–0.74; < 0.50 options email.
* Provider calibration via `config/llm.php`.

## Development Planning

### Milestones

1. **Phase 1 (1 week)**: Inbound webhook, threading, signed links, passwordless basics, Flowbite skeleton.
2. **Phase 2 (2 weeks)**: LLM interpretation, clarification loop (max 2), confidence thresholds, memory write gate, MCP skeleton.
3. **Phase 3 (1–2 weeks)**: **Attachments** (scan/extract/download), TTL/decay/purge jobs, observability, docs and demo.

### Dependencies

* Postmark account + webhook secret
* Redis and PostgreSQL in Docker
* LLM provider keys and Ollama
* ClamAV daemon; poppler-utils/spatie/pdf-to-text
* Laravel 12 skeleton, Tailwind/Flowbite setup

### Development Tooling — Laravel Boost

For developers using **Cursor** or other MCP-aware editors:

- Install Boost:
```bash
  composer require laravel/boost --dev
  php artisan boost:install
```

* Run MCP server (keep running in a terminal):

```bash
php artisan boost:mcp
```

* In Cursor, add MCP server:

  * **Command:** `php`
  * **Args:** `artisan boost:mcp`
  * **Working directory:** project root

This gives the AI assistant real-time access to:

* `php artisan route:list`, `db:schema`, `tinker` context
* Laravel 12 documentation search
* Logs, config, and schema info

> This replaces hand-crafted MCP stubs; Boost becomes the preferred integration layer for development prompts.

### Risk Matrix

| Risk                       | Impact | Likelihood | Mitigation                                            |
| -------------------------- | ------ | ---------- | ----------------------------------------------------- |
| LLM JSON invalid           | High   | Medium     | Schema validation; options email; logging             |
| Threading mismatch         | Medium | Low        | RFC headers + subject normalization; X-Thread-ID hint |
| Deliverability issues      | Medium | Medium     | SPF/DKIM/DMARC; Postmark monitoring                   |
| LLM costs/latency          | Medium | Medium     | Local fallback; shorter prompts; summary cache        |
| Privacy/compliance         | High   | Low        | Data minimization; localized storage; DPIA; purge jobs|
| Queue backlog              | Medium | Low        | Autoscaling; idempotent jobs                          |
| Vendor lock-in Postmark    | Medium | Medium     | Transport abstraction; document SMTP alternative      |
| **Malware in attachments** | High   | Low        | Mandatory ClamAV, quarantine, block processing        |
| **Large files/DoS**        | Medium | Medium     | MIME/size limits, rate limits, separate queue         |

## MCP Layer Specification

### Definition and Placement

* **Definition**: internal router that exposes tools, prompts, and resources as JSON schema endpoints.
* **Namespaces**:

  * Schemas: `App\Schemas\*`
  * Tools: `App\Mcp\Tools\*`
  * Tool Schemas: `App\Mcp\ToolSchemas\*`
  * Controller: `App\Http\Controllers\McpController`
  * Provider: `App\Providers\McpServiceProvider`

### Action Whitelist v1

`approve`, `reject`, `revise`, `select_option`, `provide_value`, `schedule_propose_times`, `schedule_confirm`, `unsubscribe`, `info_request`, `stop`

**Parameters per type (core):**

| Action                   | Required Parameters                | Notes                       |
| ------------------------ | ---------------------------------- | --------------------------- |
| approve/reject           | `target_id: ulid`, `note?/reason?` | Idempotent                  |
| revise                   | `target_id`, `fields: object`      | Partial update              |
| select_option           | `target_id`, `option: string`      | Validated against whitelist |
| provide_value           | `key: string`, `value: any`        | Type-checked in schema      |
| schedule_propose_times | `slots:[ISO8601]`, `timezone:IANA` | Creates poll                |
| schedule_confirm        | `slot: ISO8601`                    | Creates event               |
| unsubscribe              | `channel:"marketing"|"all"`        | Compliance required.        |
| info_request            | `topic:string`                     | Sends summary/FAQ           |
| stop                     | `reason?:string`                   | Pauses thread/agent         |

## Memory Policy and Read Logic

### Scopes and Priority

* Priority = conflict winner: `conversation > user > account`
* Ties: highest **decayed confidence**, else most recent

### Decay and Supersede

* TTL:

  * `volatile` 30d (half-life 30d)
  * `seasonal` 120d (half-life 90d)
  * `durable` 730d (half-life 365d)
  * `legal` policy-based
* Decay formula: `confidence(t) = c0 * 0.5^(age_days / half_life_days)`
* Supersede: newer, more certain, or explicitly opposite info creates a new version and links to the previous.

### Weighted Reader

```php
class MemoryReader {
  public function get(string $key, array $ctx): ?Memory {
    return $this->best('conversation', $ctx['thread_id'], $key)
        ?? $this->best('user', $ctx['user_id'], $key)
        ?? $this->best('account', $ctx['account_id'], $key);
  }
  protected function best(string $scope, string $id, string $key): ?Memory {
    $candidates = Memory::where(compact('scope','key'))
      ->where('scope_id',$id)
      ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at','>', now()); })
      ->orderByDesc('created_at')
      ->get();
    $pick = null; $best = -1;
    foreach ($candidates as $m) {
      $hl = match($m->ttl_category){ 'volatile'=>30,'seasonal'=>90,'durable'=>365, default=>365 };
      $age = $m->created_at->diffInDays();
      $decayed = $m->confidence * pow(0.5, $age / $hl);
      if ($decayed > $best) { $best = $decayed; $pick = $m; }
    }
    return $pick;
  }
}
```

## Testing Strategy

### Golden Set and Taxonomy

* ≥ 100 examples per action type (whitelist v1)
* Cover multilingual variants, typos, attachment cases (pdf/csv)
* Monthly precision/recall measurement and threshold tuning

### Types of Tests

* Unit: thread resolver, signed links, passwordless, schema validators, **AttachmentService**
* Integration: webhook ingest with fixtures; MCP tool calls; ClamAV stub
* E2E: email → interpretation → action → confirmation in thread → signed download
* Load: 20–50 concurrent inbound mails; P95 < 4 s (without heavy extraction)

## Appendices

## Appendix A — LLM Prompt Specifications

### Action Interpreter (system prompt)

You are a strict JSON generator. Detect exactly one action from the user’s email reply.
Allowed `action_type`:

* `approve`
* `reject`
* `revise`
* `select_option`
* `provide_value`
* `schedule_propose_times`
* `schedule_confirm`
* `unsubscribe`
* `info_request`
* `stop`

Return **JSON only**.
Always include a `"confidence"` score in the range `[0,1]`.
Request clarification **only if strictly necessary**.

### Action Interpreter (output schema, verbal description)

Fields:

* **action_type**: enumeration of allowed values (see list above).
* **parameters**: object containing the structured arguments relevant to the action.
* **scope_hint**: enumeration, possible values are `conversation`, `user`, `account`.
* **confidence**: floating-point value in the range `0.0–1.0`.
* **needs_clarification**: boolean flag indicating whether further user input is required.
* **clarification_prompt**: short string with a clarification question, or `null`.

### Memory Gate (system prompt)

Extract relevant, **non-sensitive** facts as key-value pairs for personalization and recall.

Rules:

* Decide **scope**: `user`, `conversation`, or `account`.
* Decide **ttl_category** (time-to-live classification).
* Assign a **confidence** score in the range `[0,1]`.
* Explicitly **reject sensitive data** (e.g., health, politics, financial details).
* Output must be an **array of items**, JSON only.

## Appendix B — Clean Reply Extraction

* Strip previous quoted content using the **reply-parser**.
* Detect language-specific quotation markers such as:

  * “On … wrote:”
  * “Op … schreef …”
* Remove signatures using heuristics, e.g.:

  * “-- ”
  * “Sent from …” / “Verzonden vanaf …”
* Normalize whitespace and trim leading/trailing spaces.

### Appendix C — MCP Tool Schemas (Extended)

Addition: **ProcessAttachmentTool** I/O as described in MCP Layer section.
Responses always return `{"ok":true,"data":...}` or `{"ok":false,"error":"..."}`.

### Appendix D — Glossary

* **MCP**: Model Context Protocol; custom Laravel layer for schema-driven tools/prompts/resources.
* **TTL**: Time To Live
* **Decay**: Confidence reduction over time using half-life.
* **Confidence**: Certainty score ∈ [0,1].
* **ULID**: Lexicographically sortable unique ID.
* **P50/P95**: 50th/95th percentile latencies.

### Appendix E — Docker Compose (Example)

```yaml
services:
  app:
    build: .
    env_file: .env
    depends_on: [postgres, redis, clamav, ollama]
    volumes: [".:/var/www/html"]
  postgres:
    image: postgres:17
    environment: { POSTGRES_PASSWORD: secret, POSTGRES_DB: agentai }
    volumes: ["pg:/var/lib/postgresql/data"]
  redis:
    image: redis:7
    volumes: ["redis:/data"]
  clamav:
    image: clamav/clamav:latest
  ollama:
    image: ollama/ollama:latest
    volumes: ["ollama:/root/.ollama"]
volumes: { pg: {}, redis: {}, ollama: {} }
```

### Appendix F — Security Checklist (Attachments)

* [ ] Enforce MIME whitelist and size limits
* [ ] Mandatory ClamAV scan before extraction
* [ ] Signed downloads with short expiries and nonce
* [ ] No external fetch in tools (SSRF safe)
* [ ] Retention policy and logs for data access

## Appendix G — Prompt Pack & Usage

### Goals & Principles

* **JSON-only**: every model output must be strict JSON according to schema (validated server-side).
* **Short outputs**: no internal reasoning; optional mini-explanation in a `note` field (≤ 1 sentence).
* **Language**: model writes in `:detected_locale` (e.g., “nl” or “en-GB”).
* **Token caps** (from NFRs): input ≤ 2000, summary ≤ 500, output ≤ 300.
* **Confidence**: scale [0,1]; thresholds: auto ≥ 0.75, clarification 0.50–0.74, options email < 0.50.

### Integration Overview (where & when)

**In `ProcessInboundEmail` job**

1. **(Non-LLM)** Clean Reply Extractor
2. **Language Detect (fallback)** – only if library fails → Prompt `language_detect`
3. **Attachment Extractions** (async, non-LLM/OCR where possible) → once excerpts ready:
4. **Action Interpreter** → Prompt `action_interpret`
5. **Memory Gate** (parallel to 4) → Prompt `memory_extract`
6. Decision logic:

   * `confidence ≥ 0.75` ⇒ Dispatch action
   * `0.50–0.74` ⇒ **Clarification** → Prompt `clarify_question` (+ `clarify_email_draft` for email)
   * `< 0.50` ⇒ **Options Email** → Prompt `options_email_draft`
7. **Persist Memories** after policy filter

**In `SummarizeThreadJob`**

* **Thread Summarizer** → Prompt `thread_summarize` (writes to `threads.context_json`).

**For scheduling/appointments**

* **Poll Generator** (optional) → Prompt `poll_email_draft`.

**For attachments**

* **Attachment Summarizer** → Prompt `attachment_summarize` (works on text excerpt).
* **CSV Analyzer** (optional) → Prompt `csv_schema_detect`.

> **Important:** all prompts run through `App\Services\LlmClient` with timeouts/retries from NFRs. JSON is validated against provided **schemas** in `App\Schemas\...`. MCP tools are **not** directly invoked by the LLM; the LLM only outputs intent/parameters, server-side executes validated MCP calls.

### Config Layout

#### `config/llm.php` (sketch)

```php
return [
    'provider' => env('LLM_PROVIDER', 'openai'), // openai|anthropic|ollama
    'model' => env('LLM_MODEL', 'gpt-4o-mini'),
    'timeout_ms' => 600000, // 10 minutes for async email processing
    'retry' => ['max' => 1, 'on' => [408, 429, 500, 502, 503, 504]],
    'calibration' => [
        'openai' => 1.00,
        'anthropic' => 0.97,
        'ollama' => 0.92,
    ],
    'caps' => [
        'input_tokens' => 2000,
        'summary_tokens' => 500,
        'output_tokens' => 300,
    ],
];
```

### `config/prompts.php`

> All templates are **short**, **normative**, and enforce **exact JSON fields**. Each prompt specifies **where** it is used.

### 1) Action Interpreter (`action_interpret`)

**Where**: `ProcessInboundEmail` after clean reply & (optional) attachment excerpts.
**Goal**: One action from the whitelist + parameters + confidence.
**Temperature**: 0.2

```php
'action_interpret' => [
  'temperature' => 0.2,
  'backstory' => 'You convert a user email reply into exactly one allowed action with parameters. Output JSON only.',
  'template' => <<<TXT
You are a strict JSON generator. Detect exactly ONE action from the whitelist below based on the user's reply and context. 
Return JSON matching the schema. No prose, no explanations.

ALLOWED action_type:
- "approve"
- "reject"
- "revise"
- "select_option"
- "provide_value"
- "schedule_propose_times"
- "schedule_confirm"
- "unsubscribe"
- "info_request"
- "stop"

PARAMETERS by action_type (all strings unless noted):
- approve:       { "reason": (optional, ≤120 chars) }
- reject:        { "reason": (required if present in text, ≤200 chars) }
- revise:        { "changes": [string,...] } // list concrete requested changes
- select_option: { "option_id": string | "label": string } // prefer option_id if visible in thread
- provide_value: { "key": string, "value": string } // e.g. "budget":"under 500 EUR"
- schedule_propose_times: { "duration_min": number, "timezone": string, "window_start": ISO8601?, "window_end": ISO8601?, "constraints": string? }
- schedule_confirm:       { "selected_start": ISO8601, "duration_min": number, "timezone": string }
- unsubscribe:   { "scope": "thread"|"account"|"all" } // thread = this conversation only
- info_request:  { "question": string }
- stop:          { "reason": string? }

SCORING:
- confidence in [0,1]; be conservative.
- If insufficient info: choose the closest action and set needs_clarification true with a short prompt.

INPUT:
- locale: :detected_locale
- thread_summary: :thread_summary
- clean_reply: :clean_reply
- attachments_excerpt: :attachments_excerpt  // may be empty
- recent_memories: :recent_memories          // relevant subset

OUTPUT JSON SCHEMA:
{
  "action_type": "approve|reject|revise|select_option|provide_value|schedule_propose_times|schedule_confirm|unsubscribe|info_request|stop",
  "parameters": { ... }, 
  "scope_hint": "conversation|user|account|null",
  "confidence": 0.0-1.0,
  "needs_clarification": true|false,
  "clarification_prompt": "string or null"
}
TXT,
],
```

### 2) Clarification Question (`clarify_question`)

**Where**: when `0.50 ≤ confidence < 0.75`.
**Goal**: One short, concrete question in the user’s language.
**Temperature**: 0.3

```php
'clarify_question' => [
  'temperature' => 0.3,
  'backstory' => 'You write one concise clarification question matching the user’s language.',
  'template' => <<<TXT
Write ONE short question to disambiguate the action below. Be specific, ≤140 chars, match locale.

locale: :detected_locale
candidate_action: :action_json
clean_reply: :clean_reply

Return JSON:
{ "question": "string (≤140 chars)" }
TXT,
],
```

### 3) Options Email Draft (`options_email_draft`)

**Where**: when `confidence < 0.50` or fallback failed.
**Goal**: Compact email with options + placeholders for signed links.
**Temperature**: 0.4

```php
'options_email_draft' => [
  'temperature' => 0.4,
  'backstory' => 'You draft a brief options email in the user’s language.',
  'template' => <<<TXT
Write a brief email offering 2–4 likely actions with friendly tone. Use locale.
Insert the provided placeholder tokens as-is for signed links.

locale: :detected_locale
subject_base: :base_subject
suggested_options: [
  { "label": "Approve", "token": "{{LINK_APPROVE}}" },
  { "label": "Reject",  "token": "{{LINK_REJECT}}"  },
  { "label": "Revise",  "token": "{{LINK_REVISE}}"  }
]

Return JSON:
{
  "subject": "string (≤80 chars)",
  "text": "plain text body (≤600 chars)",
  "html": "basic HTML body (p, ul/li, a) (≤800 chars)"
}
TXT,
],
```

### 4) Memory Gate (`memory_extract`)

**Where**: after interpretation (parallel).
**Goal**: Extract non-sensitive, useful facts with scope/TTL/confidence.
**Temperature**: 0.2

```php
'memory_extract' => [
  'temperature' => 0.2,
  'backstory' => 'Extract non-sensitive, useful facts as key-value memories.',
  'template' => <<<TXT
Extract relevant, non-sensitive facts. Decide scope and ttl_category. JSON only.

ALLOWED:
- scope: "conversation"|"user"|"account"
- ttl_category: "volatile"|"seasonal"|"durable"|"legal"
- confidence: [0,1]

Reject PII/sensitive data (health, politics, etc).

INPUT:
locale: :detected_locale
clean_reply: :clean_reply
thread_summary: :thread_summary
attachments_excerpt: :attachments_excerpt

OUTPUT:
{ "items": [
  { "key":"string_snake_case", "value":any, "scope":"conversation|user|account", 
    "ttl_category":"volatile|seasonal|durable|legal", "confidence":0.0-1.0, "provenance":"email_message_id:<id>" }
]}
TXT,
],
```

### 5) Thread Summarizer (`thread_summarize`)

**Where**: `SummarizeThreadJob`.
**Goal**: Concise, workable summary + entities + open questions.
**Temperature**: 0.3

```php
'thread_summarize' => [
  'temperature' => 0.3,
  'backstory' => 'Summarize a thread for fast recall.',
  'template' => <<<TXT
Summarize the thread concisely in locale. ≤120 words.

INPUT:
locale: :detected_locale
last_messages: :last_messages   // array of recent message snippets
key_memories: :key_memories     // small set

Return JSON:
{
  "summary": "string",
  "key_entities": ["strings..."],
  "open_questions": ["strings..."]
}
TXT,
],
```

### 6) Language Detect (fallback) (`language_detect`)

**Where**: only if library detection fails.
**Goal**: BCP-47 code + confidence.
**Temperature**: 0.0

```php
'language_detect' => [
  'temperature' => 0.0,
  'backstory' => 'Return language code only.',
  'template' => <<<TXT
Detect the primary language (BCP-47 like "nl" or "en-GB") of the given text.

text: :sample_text

Return JSON: { "language": "bcp47", "confidence": 0.0-1.0 }
TXT,
],
```

### 7) Attachment Summarizer (`attachment_summarize`)

**Where**: after extraction (text excerpt available).
**Goal**: Gist + useful bullets; optional table/column hint.
**Temperature**: 0.3

```php
'attachment_summarize' => [
  'temperature' => 0.3,
  'backstory' => 'Summarize attachment text for decision-making.',
  'template' => <<<TXT
Summarize the attachment in locale. Be concise. No chain-of-thought.

INPUT:
locale: :detected_locale
filename: :filename
mime: :mime
text_excerpt: :text_excerpt   // truncated; may be partial

OUTPUT:
{
  "title": "short title (≤60 chars)",
  "gist": "≤120 words",
  "key_points": ["3-6 bullets"],
  "table_hint": { "has_tabular_data": true|false, "likely_headers": ["..."] }
}
TXT,
],
```

### 8) CSV Schema Detect (optional) (`csv_schema_detect`)

**Where**: for CSV attachments.
**Goal**: Simple column types + detect delimiter/headers.
**Temperature**: 0.2

```php
'csv_schema_detect' => [
  'temperature' => 0.2,
  'backstory' => 'Infer simple CSV schema from a small sample.',
  'template' => <<<TXT
Infer CSV schema from sample lines. Do NOT output data, only schema.

INPUT:
filename: :filename
sample_lines: :sample_lines

OUTPUT:
{
  "delimiter": ","|"|"|";"|"\t",
  "has_header": true|false,
  "columns": [
    {"name":"string","type":"string|number|date|datetime|boolean","nullable":true|false}
  ]
}
TXT,
],
```

### 9) Clarification Email Draft (`clarify_email_draft`)

**Where**: during clarification (max 2 times).
**Goal**: Short, friendly clarification email.
**Temperature**: 0.4

```php
'clarify_email_draft' => [
  'temperature' => 0.4,
  'backstory' => 'Draft a short clarification email.',
  'template' => <<<TXT
Draft a brief email asking exactly ONE clarification question (≤140 chars). Include both text and HTML.

locale: :detected_locale
question: :question

OUTPUT:
{ "subject": "string (≤80 chars)", "text": "string (≤400 chars)", "html": "string (≤600 chars)" }
TXT,
],
```

### 10) Poll Email Draft (`poll_email_draft`) — optional

**Where**: during `schedule_propose_times`.
**Goal**: Email with poll options (signed link placeholders provided).
**Temperature**: 0.4

```php
'poll_email_draft' => [
  'temperature' => 0.4,
  'backstory' => 'Draft an availability poll email.',
  'template' => <<<TXT
Draft a short availability poll email in locale with options list.
Use given placeholders as-is for signed links.

locale: :detected_locale
event_title: :event_title
options: [ { "label":"Tue 14:00", "token":"{{LINK_OPT_1}}" }, ... ]

OUTPUT:
{ "subject":"string (≤80 chars)", "text":"string (≤600 chars)", "html":"string (≤800 chars)" }
TXT,
],
```

## Server-side JSON Schemas (validation)

Place in `app/Schemas` and bind them in the services. Example: **ActionInterpretationSchema**

```php
// app/Schemas/ActionInterpretationSchema.php
namespace App\Schemas;

final class ActionInterpretationSchema {
    /** Return Laravel validation rules for the model JSON. */
    public static function rules(): array {
        return [
            'action_type' => 'required|string|in:approve,reject,revise,select_option,provide_value,schedule_propose_times,schedule_confirm,unsubscribe,info_request,stop',
            'parameters'  => 'required|array',
            'scope_hint'  => 'nullable|string|in:conversation,user,account',
            'confidence'  => 'required|numeric|min:0|max:1',
            'needs_clarification' => 'required|boolean',
            'clarification_prompt' => 'nullable|string|max:200',
        ];
    }
}
```

> Define similar rule classes for `memory_extract`, `thread_summarize`, `attachment_summarize`, etc.

## Example Usage in Code

```php
// App\Services\PromptRunner.php (sketch)
$result = $llm->json(
    promptKey: 'action_interpret',
    vars: [
        'detected_locale'     => $locale,
        'thread_summary'      => $summary,
        'clean_reply'         => $clean,
        'attachments_excerpt' => $attachmentsExcerpt,  // '' ok
        'recent_memories'     => $memSubset,
    ],
    maxOutputTokens: config('llm.caps.output_tokens')
);

Validator::make($result, \App\Schemas\ActionInterpretationSchema::rules())->validate();
$result['confidence'] *= config("llm.calibration.$provider", 1.0);
```

## Parameter Mapping per Action (unambiguous)

* **approve**: `{reason?}` — optional human-friendly explanation.
* **reject**: `{reason?}` — optional; include only if explicitly present.
* **revise**: `{changes: string[]}` — concrete bullet-style changes (“move to 14:00”, “add CC: x\@y”).
* **select_option**: `{option_id | label}` — prefer `option_id` from thread/context; fallback to `label`.
* **provide_value**: `{key, value}` — free key-value (“budget”, “under 500 EUR”).
* **schedule_propose_times**: `{duration_min, timezone, window_start?, window_end?, constraints?}`.
* **schedule_confirm**: `{selected_start, duration_min, timezone}` — ISO8601 start.
* **unsubscribe**: `{scope}` — `"thread"` (this conversation only), `"account"` (sender/tenant), `"all"` (everything).
* **info_request**: `{question}` — explicit user question.
* **stop**: `{reason?}` — user wants to end the conversation/automation.

## Prompt QA & Evaluation

* **JSON validation**: every call → server-side `Validator`.
* **Latency**: P50 < 30s, P95 < 10min; `timeout_ms=600000` (10min), retry once on 5xx/timeout.
* **Golden set**: ≥100 examples per action; measure precision/recall; tune `temperature` and calibration.
* **A/B testing**: keep `options_email_draft` variants per language short and consistent.

## Notes on i18n & Attachments

* **Language**: library-based detection first; prompt `language_detect` is fallback only.
* **Attachments**: text extraction handled **outside the LLM** (PDF-to-text, CSV parser). Prompt `attachment_summarize` is for **short gists**. For large files → use excerpt (e.g., first 16–32 KB) + link to full text on disk.

## What We Explicitly Do Not Do

* No “manager/agent orchestration” prompts: orchestration is server-side (MCP + dispatcher).
* No chain-of-thought or “think aloud” instructions: we request **final JSON only**.
* No direct tool-calls by the model: the model outputs intent/parameters; the server decides and validates.

## Database Schema (Current Implementation)

### Schema Overview

All domain tables use **ULID primary keys** with `HasUlids` trait. PostgreSQL **JSONB** columns store structured data. Foreign keys use ULID format. GIN indexes optimize JSONB queries.

### Core Tables

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `accounts` | Multi-tenant containers | `name`, `settings_json` |
| `users` | Platform users | `display_name`, `locale`, `timezone`, `status` |
| `user_identities` | Login identities (email/phone/OIDC) | `type`, `identifier`, `verified_at` |
| `memberships` | User-account relationships | `role` |
| `threads` | Email conversation containers | `subject`, `context_json` |
| `email_messages` | Individual messages | `direction`, `message_id`, `headers_json` |
| `actions` | User/system actions | `type`, `payload_json`, `status` |
| `memories` | Versioned context data | `scope`, `key`, `value_json`, `confidence` |
| `contacts` | Ad-hoc email participants | `email`, `name`, `meta_json` |
| `email_inbound_payloads` | Encrypted webhook storage | `ciphertext`, `signature_verified` |

### Migration Files

```bash
# Run all migrations
php artisan migrate

# Available migrations (29 total):
# Framework: jobs, sessions, cache, notifications, etc.
# Domain: accounts, users, threads, email_messages, actions, memories, etc.
# Security: auth_challenges, api_tokens
# Features: agents, tasks, events, availability_polls, attachments
```

### Key Design Decisions

* **ULID PKs**: Distributed ID generation, lexicographically sortable
* **JSONB fields**: Flexible storage for `*_json` columns (settings, payloads, metadata)
* **Foreign keys**: All FKs use ULID format, cascade on delete where appropriate
* **Indexes**: GIN indexes on JSONB, trigram on `message_id`, standard BTREE on lookups
* **Constraints**: Check constraints on enums, unique constraints on business keys

## Postmark & Webhook Setup

### Postmark Configuration

Agent AI uses **Postmark** for all email handling. Follow these steps to set up email processing:

1. **Create a Postmark account** and verify a sender domain/email for outbound mail.

2. **Get your Inbound Address** from Postmark:
   ```
   <hash>@inbound.postmarkapp.com
   ```

3. **Configure environment variables** in your `.env` file:
   ```env
   MAIL_MAILER=postmark
   POSTMARK_TOKEN=pm_xxx
   POSTMARK_MESSAGE_STREAM_ID=outbound
   AGENT_MAIL=<your-inbound-address>@inbound.postmarkapp.com

   WEBHOOK_USER=postmark
   WEBHOOK_PASS=your-long-random-password
   ```

4. **Expose your local app for webhook testing** using ngrok:
   ```bash
   # For Herd (macOS)
   ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test

   # For Docker
   ngrok http --url=abc123.ngrok-free.app 8080
   ```

5. **Configure Postmark webhook** in your inbound stream settings:
   ```
   https://WEBHOOK_USER:WEBHOOK_PASS@abc123.ngrok-free.app/webhooks/inbound-email
   ```

**URLs for testing:**
- **Herd**: https://abc123.ngrok-free.app/webhooks/inbound-email
- **Docker**: https://abc123.ngrok-free.app/webhooks/inbound-email

Send test emails to your `AGENT_MAIL` address to verify webhook processing.
## Frontend Wireframes & Pages

Agent AI uses a clean, email-first interface built with Blade templates, Tailwind CSS, and Flowbite components. All pages support `en_US` and `nl_NL` locales.

### Authentication Flow

#### 1. Passwordless Login Challenge Page (`/auth/challenge`)
**Purpose**: Request access via email address.

**Layout**:
- Clean centered form with Agent AI branding
- Email input field with validation
- "Send Login Code" button
- Links to privacy policy and terms

**Functionality**:
- POST to `/auth/challenge` with `{identifier: email}`
- Shows success message: "Check your email for a login code"
- Rate limited (5 per 15min per email)
- Redirects to verification page

**Wireframe**:
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

#### 2. Code Verification Page (`/auth/verify/{challenge_id}`)
**Purpose**: Enter the received code to authenticate.

**Layout**:
- Centered form with email confirmation
- 6-digit code input field
- "Verify Code" button
- "Resend Code" link

**Functionality**:
- POST to `/auth/verify` with `{challenge_id, code}`
- Auto-focus on code input
- Shows error for invalid/expired codes
- Rate limited (10 per 15min per identifier)

**Wireframe**:
```
┌─────────────────────────────────────┐
│           Agent AI                  │
│                                     │
│  Check your email                  │
│                                     │
│  We sent a 6-digit code to:         │
│  user@example.com                   │
│                                     │
│  ┌─────────────────────────────┐    │
│  │ Enter Code: _____ _____     │    │
│  └─────────────────────────────┘    │
│                                     │
│  [Verify Code]                      │
│                                     │
│  Didn't receive code? [Resend]      │
└─────────────────────────────────────┘
```

### Main Application Pages

#### 3. Dashboard (`/dashboard`)
**Purpose**: Overview of recent threads and actions.

**Layout**:
- Navigation header with user menu
- Recent threads list
- Quick stats (active threads, pending actions)
- Search bar

**Functionality**:
- Shows last 10 threads with status
- Links to thread detail pages
- User dropdown with logout option

**Wireframe**:
```
┌─────────────────────────────────────┐
│ [≡] Dashboard | Threads | Settings │
│                                   👤 │
├─────────────────────────────────────┤
│ Search threads... [🔍]             │
├─────────────────────────────────────┤
│ 📧 Recent Threads                   │
│ ├─ Meeting Request (2h ago)        │
│ │  └─ ✅ Approved                  │
│ ├─ Invoice Review (1d ago)         │
│ │  └─ ⏳ Pending                    │
│ ├─ Support Ticket (3d ago)         │
│ │  └─ ❌ Rejected                  │
└─────────────────────────────────────┘
```

#### 4. Thread Detail Page (`/threads/{id}`)
**Purpose**: View conversation thread with messages and actions.

**Layout**:
- Thread header with subject and status
- Message timeline
- Action buttons (if applicable)
- Attachment list

**Functionality**:
- Shows all messages in chronological order
- Displays pending actions with buttons
- Shows attachment previews/links
- Auto-refreshes for new messages

**Wireframe**:
```
┌─────────────────────────────────────┐
│ ← Back | 📧 Meeting Request        │
│ Status: Active                     │
├─────────────────────────────────────┤
│ Alice (2h ago)                     │
│ Can we schedule a call tomorrow?   │
│                                    │
│ System (1h ago)                    │
│ 🤖 I detected a scheduling request.│
│ Please confirm your availability:  │
│ ├─ Tomorrow 10:00 AM              │
│ ├─ Tomorrow 2:00 PM               │
│ └─ Friday 11:00 AM                │
│                                    │
│ [📎 meeting_notes.pdf]             │
├─────────────────────────────────────┤
│ Reply via email or use buttons:    │
│ [✓ Confirm 10:00 AM] [✗ Decline]   │
└─────────────────────────────────────┘
```

#### 5. Action Confirmation Pages

##### Signed Action Page (`/a/{action_ulid}`)
**Purpose**: One-click confirmation of actions via signed links.

**Layout**:
- Action summary
- Confirm/Reject buttons
- Context from original request

**Functionality**:
- Validates signature and expiry (15-60 min)
- Shows action details and parameters
- Idempotent - clicking again shows "already processed"

**Wireframe**:
```
┌─────────────────────────────────────┐
│           Agent AI                  │
│                                     │
│  📧 Action Confirmation             │
│                                     │
│  Meeting Request from Alice         │
│  "Can we meet tomorrow at 10 AM?"   │
│                                     │
│  Proposed: Tomorrow, 10:00 AM       │
│  Duration: 1 hour                   │
│                                     │
│  [✓ Confirm Meeting] [✗ Decline]    │
│                                     │
│  This link expires in 30 minutes    │
└─────────────────────────────────────┘
```

##### Options Selection Page (`/a/options/{action_ulid}`)
**Purpose**: When LLM confidence is low (<50%), show multiple options.

**Layout**:
- Question from clarification email
- Multiple choice options
- Context from thread

**Functionality**:
- Each option links to signed action
- Options based on LLM suggestions

#### 6. Attachment Download Page (`/attachments/{id}`)
**Purpose**: Secure download of attachments via signed links.

**Layout**:
- File preview (if possible)
- Download button
- File metadata (size, type, scan status)

**Functionality**:
- Validates signature and expiry
- Serves file with proper headers
- Logs access for compliance

### Admin/Configuration Pages

#### 7. Account Settings (`/settings/account`)
**Purpose**: Manage account-level settings.

**Layout**:
- Account information
- Default locale settings
- Retention policies
- API token management

#### 8. User Profile (`/settings/profile`)
**Purpose**: Personal user settings.

**Layout**:
- Display name and identities
- Language preference (en_US/nl_NL)
- Timezone settings

### Email Templates

All emails follow a consistent design with the Agent AI branding:

1. **Login Code Email**: 6-digit code with expiry notice
2. **Action Confirmation Email**: Summary with signed link
3. **Clarification Email**: Question with options
4. **Options Email**: Multiple choices when unclear

## Development Workflow

### Local Development Commands

```bash
# Start development server (Laravel Herd handles this)
# Visit http://agent-ai.test

# Compile assets for development
npm run dev

# Compile for production
npm run build

# Run tests
php artisan test

# Run specific test file
php artisan test tests/Feature/AuthTest.php

# Run with coverage
php artisan test --coverage

# Generate test for a class
php artisan make:test ProcessInboundEmailTest
```

### Code Quality & Linting

```bash
# Run PHPStan static analysis
./vendor/bin/phpstan analyse

# Format code with Laravel Pint
./vendor/bin/pint

# Check security vulnerabilities
composer audit

# Run all checks
composer run check
```

### Database Management

```bash
# Create new migration
php artisan make:migration add_field_to_users_table

# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Refresh database (rollback all, migrate, seed)
php artisan migrate:fresh --seed

# Create seeder
php artisan make:seeder UserSeeder
```

### Queue & Background Jobs

```bash
# Start queue worker
php artisan queue:work

# Start with specific queue
php artisan queue:work --queue=attachments

# Monitor queues with Horizon
php artisan horizon

# Clear failed jobs
php artisan queue:failed
php artisan queue:flush
```

### Localization Development

```bash
# Publish language files
php artisan lang:publish

# Create new language file
touch resources/lang/nl_NL/messages.php

# Test locale switching
App::setLocale('nl_NL');
```

## Configuration Overview

**Environment Variables**: See `.env.example` for complete configuration template.

**Key Configs**:
- `config/database.php` - PostgreSQL with JSONB support
- `config/queue.php` - Redis queues for async processing
- `config/mail.php` - Postmark integration
- `config/filesystems.php` - Local storage with attachments disk

**Future Configs** (not yet implemented):
- `config/llm.php` - Provider settings and token limits
- `config/prompts.php` - LLM prompt templates
- `config/mcps.php` - MCP tool registry

## Testing Strategy (Future)

**Unit Tests**: Models, services, jobs with comprehensive coverage.

**Feature Tests**: Webhook processing, authentication flows, API endpoints.

**Integration Tests**: End-to-end email processing with LLM mocks.

**Golden Set**: ≥100 examples per action type for LLM training and validation.

**CI/CD**: GitHub Actions with PostgreSQL, Redis, automated testing.

## Troubleshooting

### Common Issues & Solutions

#### LLM Timeout Errors
```
Error: cURL timeout in LlmClient
```
**Solutions**:
- Check Ollama service: `docker-compose ps | grep ollama`
- Increase timeout in `config/llm.php`: `'timeout_ms' => 8000`
- Switch provider: `LLM_PROVIDER=openai` in `.env`
- Verify model is downloaded: `docker-compose exec ollama ollama list`

#### Attachment Scan Failures
```
ClamAV connection refused
```
**Solutions**:
- Ensure ClamAV container is running: `docker-compose ps | grep clamav`
- Check network connectivity: `docker-compose exec clamav ping postgres`
- Verify clamd is listening: `docker-compose exec clamav netstat -tlnp | grep 3310`
- Check logs: `docker-compose logs clamav`

#### Webhook Signature Verification Failed
```
HMAC verification failed
```
**Solutions**:
- Verify `WEBHOOK_USER` and `WEBHOOK_PASS` in `.env` for HTTP Basic Auth
- Check webhook URL encoding in Postmark (should be your ngrok/localtunnel URL)
- Ensure raw request body is used for HMAC calculation
- Test with Postmark's webhook tester

#### Thread Resolution Issues
```
Multiple threads created for same conversation
```
**Solutions**:
- Check subject normalization in `ThreadResolver`
- Verify RFC headers are parsed correctly (`Message-ID`, `In-Reply-To`, `References`)
- Enable X-Thread-ID header if available in Postmark
- Check database for duplicate threads

#### Memory Decay Not Working
```
Memories not expiring as expected
```
**Solutions**:
- Verify TTL categories in `MemoryReader::best()` method
- Check half-life calculations: `confidence(t) = c0 * 0.5^(age_days / half_life_days)`
- Ensure cron jobs are running: `crontab -l | grep artisan`
- Test decay formula manually in tinker: `php artisan tinker`

#### Queue Backlog Issues
```
Jobs piling up in Redis
```
**Solutions**:
- Start more workers: `php artisan queue:work --max-jobs=1000`
- Scale with multiple processes: `for i in {1..3}; do php artisan queue:work & done`
- Use dedicated queues: `php artisan queue:work --queue=attachments`
- Monitor via Horizon dashboard

#### Database Connection Issues
```
SQLSTATE[08006] [7] connection to server at "localhost" (127.0.0.1), port 5432 failed
```
**Solutions**:
- Ensure PostgreSQL is running: `docker-compose ps postgres`
- Check credentials in `.env`
- Verify database exists: `docker-compose exec postgres psql -U agent_user -l`
- Check connection from app: `php artisan tinker` then `DB::connection()->getPdo()`

#### Localization Not Working
```
Translation strings not showing in correct language
```
**Solutions**:
- Check `APP_LOCALE` in `.env` (use `en_US` or `nl_NL`)
- Ensure language files exist: `ls resources/lang/`
- Clear cache: `php artisan optimize:clear`
- Test in blade: `{{ __('messages.welcome') }}`

#### File Upload Issues
```
Unable to write file to attachments disk
```
**Solutions**:
- Check storage permissions: `chmod -R 755 storage/`
- Verify disk configuration in `config/filesystems.php`
- Ensure directory exists: `mkdir -p storage/app/attachments`
- Check available disk space: `df -h`

### Performance Issues

#### Slow LLM Responses (>4 seconds)
- Switch to external provider (OpenAI/Anthropic)
- Reduce input token limits in `config/llm.php`
- Implement caching for common prompts
- Use smaller models in Ollama

#### High Memory Usage
- Process attachments in chunks
- Clear temporary files after processing
- Monitor with `memory_get_peak_usage(true)`
- Use queue jobs for heavy processing

### Testing Issues

#### Feature Tests Failing
- Ensure database is seeded: `php artisan migrate:fresh --seed`
- Check test database configuration
- Use `RefreshDatabase` trait in tests
- Mock external services (LLM, ClamAV)

### Backup Strategy

```bash
# Database backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
docker-compose exec postgres pg_dump -U agent_user agent_ai_prod > backup_$DATE.sql

# File backup
tar -czf attachments_$DATE.tar.gz storage/app/attachments/

# Upload to S3 (example)
aws s3 cp backup_$DATE.sql s3://your-backup-bucket/
aws s3 cp attachments_$DATE.tar.gz s3://your-backup-bucket/
```

## Summary

**Agent AI** is an email-centered automation system built with Laravel 12, featuring:

✅ **Currently Implemented**:
- Complete PostgreSQL schema with 29 migrations and 21 Eloquent models
- Postmark webhook integration with HMAC validation
- RFC 5322 email threading via ThreadResolver service
- ULID primary keys, JSONB storage, comprehensive relationships
- LLM client with gpt-oss:20b model and Ollama fallback
- ProcessInboundEmail job with LLM interpretation and action generation
- Email processing status tracking and async timeouts (10min LLM, 15min queue)

🚧 **In Development**:
- Passwordless authentication system
- Blade/Flowbite UI foundation
- MCP layer and tool execution

📋 **Planned Features**:
- MCP layer for schema-driven tool calls
- Action interpretation and clarification loops
- Attachment processing pipeline (ClamAV, extraction, summarization)
- Memory gate with TTL/decay
- i18n language detection and multilingual UI

**Tech Stack**: Laravel 12, PHP 8.4, PostgreSQL 17+, Redis 7, Postmark, Ollama, ClamAV, Tailwind/Flowbite.

**Development Status**: Phase 1A (Database/Models) complete. Ready for Phase 1B (Auth/UI) and Phase 2 (LLM/MCP).
