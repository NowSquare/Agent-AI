# Agent AI – Technical Design Document

## Executive Summary

Agent AI is an email-centered automation system built on Laravel 12. It links incoming emails to threads, interprets free text with a Large Language Model (LLM), and executes actions via signed links or controlled tool calls. Tool calls run through a **custom MCP layer** (Model Context Protocol) that enforces JSON schemas and exposes SSRF-safe tools. The system supports **attachments** (txt, md, csv, pdf, etc.) including virus scanning, extraction, and summarization. Passwordless login, Flowbite/Tailwind UI, and a future-proof data model reduce friction. An LLM is always available; when intent is unclear, the system asks follow-up questions until intent is clear (maximum 2 rounds). Redis/Horizon deliver asynchronous reliability. PostgreSQL guarantees integrity and versioned "memories" with TTL/decay. Postmark handles inbound/outbound email with robust RFC threading. The design is self-hostable with Docker. We add lightweight reliability features: **grounded retrieval via pgvector**, **AgentOps logs/evaluation**, **simple dynamic model selection**, and **internal multi-agent delegation**, optimized for small-business deployments without extra services.

## Project Overview

### Business Context

* Target audience: small teams and self-hosters who want email as the UI.
* Value: fewer tools, low learning curve, clear audit trail.
* Compliance: GDPR-first, EU hosting option, data minimization.

### Technical Scope

* Inbound via Postmark webhook (HTTP Basic Auth with shared secret).
* Threading with `Message-ID`, `In-Reply-To`, `References`.
* LLM-first interpretation and follow-up loop with signed links.
* **MCP layer**: custom Laravel component with schema-driven tools.
* Passwordless auth (magic link + code).
* Blade/Flowbite/Tailwind UI with i18n.
* Queues via Redis; Horizon for monitoring.
* Docker Compose for self-hosting.
* **Attachments**: storage, virus scan, extraction, and LLM context.
* Grounding via **pgvector**: embedding search over threads, attachments, and memories for fact retrieval; no external vector service.

### Key Stakeholders

* Product Owner: prioritization and action taxonomy.
* Engineering Team: Laravel 12 backend, Blade UI, MCP.
* DevOps: containers, secrets, monitoring, backups.
* Compliance/Legal: DPIA, data processing agreement.
* End Users: email recipients and web confirmers.

## Lightweight Refinements (Small-Business Friendly)

This project avoids heavy infra. The following refinements improve reliability and trust without new services.

### 1) Grounding with pgvector
- Use `pgvector` in PostgreSQL.
- Embed email bodies, attachment text, and memories.
- Store vectors in existing tables (`email_messages`, `attachment_extractions`, `memories`).
- KNN search for top-k context; tag snippets with provenance.
 - Routing: CLASSIFY → run KNN over embeddings → if hit-rate ≥ threshold, answer via GROUNDED; else SYNTH.

### 2) AgentOps Logs & Evaluation
- `agent_steps` includes role, provider, model, tokens, latency, confidence, and full JSON I/O.
- Multi-agent protocol fields: `agent_role` (Planner|Worker|Critic|Arbiter), `round_no`, optional `coalition_id`, `vote_score`, `decision_reason`.
- Log every LLM/tool call for traceability.

### Phase 2 — Multi-Agent Protocol Details
- **Allocation (auction)**: utility = `w_cap*capability_match + w_cost*(1/cost_hint) + w_rel*reliability`. Top‑K workers selected per task via `AgentRegistry::topKForTask`. Allocation shortlist is logged (Planner step).
- **Debate (K rounds + minority report)**: Critics score groundedness/completeness/risk each round; retain candidates within ε of top as a minority report. Voting aggregates Critic + Worker self‑scores using `config/agents.php` weights. Tie‑breakers: higher groundedness → lower expected cost → oldest.
- **Typed Memories**: Curator writes `Decision|Insight|Fact` memories with `provenance_ids[]` and a stable `content_hash` to deduplicate.
- **Metrics**: `agent:metrics --since --limit` prints rounds, per‑role counts/latency, groundedness %, and win distribution.

### How to Add a New Agent (Developer)
1. Add capability tags (`keywords`, `domains`, `expertise`, `action_types`) and `cost_hint` on the `Agent`.
2. Ensure the agent is available for the account (seed or UI). Reliability updates over time from wins.
3. Workers run via `AgentProcessor` (prompting uses role/capabilities); Critics leverage groundedness inputs; no extra wiring required for basic participation.

### 3) Simple Dynamic Model Selection
- Small model for classification/short tasks.
- GROUNDED default model: `gpt-oss:20b` (local-first), configurable via .env.
- SYNTH default model: `gpt-oss:120b` (heavier reasoning), configurable via .env.
- Record chosen model in `agent_steps`.

### 4) Internal Multi-Agent Delegation
- Agents may invoke other agents through the Coordinator (no external protocol).
- Record delegations as `agent_steps` with step_type="route".

### 5) Evaluation Checklist
- Golden-path tests of email→action flows.
- Grounding hit-rate monitoring.
- Non-regression tests with cached prompts.

### Tuning & Troubleshooting (Quick Guide)
- Tuning Playbook:
  - If answers hallucinate → lower `LLM_GROUNDING_HIT_MIN` or improve the embeddings model.
  - If everything routes to SYNTH → decrease `LLM_SYNTH_COMPLEXITY_TOKENS` or improve retrieval (`k`↑).
  - If latency too high → pick a smaller GROUNDED model or reduce `k`; consider disabling reasoning for GROUNDED.
- Troubleshooting:
  - Vector dim mismatch → check `EMBEDDINGS_DIM` vs actual model; re-run migrations and `php artisan embeddings:backfill`.
  - Missing model tags → change role provider/model or pull tags in Ollama.
  - No matches in retrieval → verify embeddings present; run `php artisan embeddings:backfill`; inspect stopwords/cleanup.

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
- Add **pgvector grounding**, **AgentOps logs**, **simple model routing**, **internal delegation**, plus an evaluation checklist.
 - LLM routing: CLASSIFY → (pgvector retrieval) → GROUNDED or SYNTH, with config-driven thresholds and role bindings.

**✅ COMPLETED: Phase 3 - Attachments Pipeline**
- Full attachment pipeline: MIME/size limits, ClamAV scan, text extraction, LLM summarization
- Signed downloads with expiry and nonce validation
- Async processing on dedicated queue, LLM context integration
- Security hardening and comprehensive logging

**📋 FUTURE: Phase 4 - Quality & Production**
- Comprehensive testing and monitoring
- Performance optimization and production deployment
- Advanced features and polish

**Multi-Agent Protocol (Plan → Allocate → Work → Debate → Decide → Curate)**
- Planner: builds task plan (tasks[], deps[])
- Workers: execute tasks (parallel/sequential)
- Critics: run K debate rounds (default 2)
- Arbiter: selects winner, records `vote_score` and `decision_reason`
- Memory Curator: persists final outcome summary with provenance

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
- Blade components for reusable UI elements:
  - `<x-thread-metadata>` - Thread info, metadata, and version history
  - `<x-action-status>` - Action state indicators
- Tailwind utility classes, Flowbite components
- Dark mode support with `dark:` variants
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

Agent-AI/
  - app/
    - Console/
      - Commands/
        - EmbeddingsBackfill.php    # php artisan embeddings:backfill
        - LlmRoutingDryRun.php      # php artisan llm:routing-dry-run
        - PruneMemories.php
      - Kernel.php
    - Http/
      - Controllers/
        - ActivityController.php    # Activity UI listing/detail
        - AttachmentDownloadController.php
        - DashboardController.php
        - ActionConfirmationController.php
        - Auth/
          - ChallengeController.php
          - LoginController.php
          - VerifyController.php
        - Api/ [3 files: *.php]
        - Webhook/
          - PostmarkInboundController.php
      - Middleware/ [2 files: *.php]
      - Requests/ [5 files: *.php]
      - Resources/ [3 files: *.php]
    # Core Business Logic - Listed Explicitly
    - Jobs/
      - ExtractAttachmentText.php
      - ProcessInboundEmail.php
      - ProcessWebhookPayload.php
      - ScanAttachment.php
      - SendActionResponse.php
      - SendClarificationEmail.php
      - SendOptionsEmail.php
      - SummarizeAttachment.php
    - Mail/
      - ActionClarificationMail.php
      - ActionOptionsMail.php
      - ActionResponseMail.php
      - AuthChallengeEmail.php
      - AuthMagicLinkEmail.php
    - Mcp/
      - Prompts/ [2 files: *.php]
      - Servers/ [1 file: *.php]
      - Tools/ [3 files: *.php]
    # Domain Models - Listed Explicitly
    - Models/
      - Account.php
      - Action.php
      - Agent.php
      - AgentSpecialization.php
      - ApiToken.php
      - Attachment.php
      - AttachmentExtraction.php
      - AuthChallenge.php
      - AvailabilityPoll.php
      - AvailabilityVote.php
      - Contact.php
      - ContactLink.php
      - EmailInboundPayload.php
      - EmailMessage.php
      - Event.php
      - EventParticipant.php
      - Membership.php
      - Memory.php
      - Task.php
      - Thread.php
      - ThreadMetadata.php
      - User.php
      - UserIdentity.php
      - AgentStep.php
    - Providers/
      - AppServiceProvider.php
      - HorizonServiceProvider.php
    # Core Services - Listed Explicitly
    - Services/
      - ActionDispatcher.php
      - AgentProcessor.php
      - AgentRegistry.php
      - AttachmentService.php
      - AuthService.php
      - ContactLinkService.php
      - Coordinator.php
      - Embeddings.php
      - EnsureDefaultAccount.php
      - GroundingService.php
      - LanguageDetector.php
      - LlmClient.php
      - MemoryService.php
      - ModelRouter.php
      - MultiAgentOrchestrator.php
      - ReplyCleaner.php
      - ThreadResolver.php
      - ThreadSummarizer.php
    - View/
      - Components/ [1 file: *.php]
  # Framework & Config
  - artisan
  - bootstrap/
    - app.php
    - providers.php
  - composer.json
  - composer.lock
  # Configuration - Listed Explicitly
  - config/
    - app.php
    - attachments.php
    - auth.php
    - cache.php
    - database.php
    - filesystems.php
    - horizon.php
    - language.php
    - llm.php                  # Routing roles + embeddings block
    - logging.php
    - mail.php
    - memory.php
    - prompts.php
    - queue.php
    - services.php
    - session.php
  # Documentation
  - CLAUDE.md
  - CURSOR-PROMPTS.md
  - CURSOR-README.md
  - README.md
  # Database
  - database/
    - factories/ [5 files: *.php]
    - migrations/ [32+ files: *.php]
      - 2025_09_21_011500_create_agent_steps_table.php   # Trace store
      - pgvector enabled; embedding cols on email_messages/attachment_extractions/memories
    - seeders/ [1 file: *.php]
  # Docker
  - docker/
    - entrypoint.sh
  - docker-compose.yml
  - Dockerfile
  # Frontend / Public
  - package.json
  - package-lock.json
  - phpunit.xml
  - public/
    - favicon.ico
    - index.php
    - robots.txt
  # Resources
  - resources/
    - css/
      - app.css
    - js/
      - app.js
      - bootstrap.js
    - lang/
      - en/ [3 files: *.php]
      - nl/ [3 files: *.php]
    - views/
      - action/ [3 files: *.php]
      - activity/
        - index.blade.php
        - show.blade.php
      - auth/ [2 files: *.php]
      - components/ [1 file: *.php]
      - dashboard.blade.php
      - emails/ [7 files: *.php]
      - layouts/ [2 files: *.php]
      - threads/ [1 file: *.php]
      - welcome.blade.php
  # Routes
  - routes/
    - api.php
    - console.php
    - web.php                 # /activity list/detail routes
  # Storage & Logs (trimmed)
  - storage/
    - app/...
    - framework/...
    - logs/...
  # Tests
  - tests/
    - Feature/ [14+ files: *.php]
      - GroundedAnswerTest.php
      - SynthAnswerTest.php
    - Unit/ [3+ files: *.php]
      - ModelRouterTest.php
      - GroundingServiceTest.php
      - EmbeddingsTest.php
    - TestCase.php
  - vite.config.js



Note: File extension counts do not include files ignored by .gitignore.

### Developer Guide (Read Me First)
- **Data Flow (Email → Contact → User)**
  - Inbound email creates/updates a Contact and attaches it to a Thread. On first-ever contact, an Account is auto-created from `APP_NAME`.
  - First web login with the same email: if no User, create User, link via `contact_links`, send challenge; after verification the user sees their Dashboard and Activity.
  - Users see the full trace for their own threads only. A thread is yours if it involves a contact linked to your user via `contact_links`.
- **LLM Routing (CLASSIFY → Retrieval → GROUNDED | SYNTH)**
  - Defaults: GROUNDED=`gpt-oss:20b`, SYNTH=`gpt-oss:120b`, CLASSIFY=`mistral-small3.2:24b` (tune in `.env`).
  - Thresholds: `LLM_GROUNDING_HIT_MIN`, `LLM_SYNTH_COMPLEXITY_TOKENS`. See `config/llm.php` and `.env` comments.
- **Grounding with pgvector**
  - Embeddings on `email_messages.body_embedding`, `attachment_extractions.text_embedding`, `memories.content_embedding`.
  - Cosine KNN retrieval; snippets carry provenance. Backfill embeddings with `php artisan embeddings:backfill`.
- **Agent Steps (Traceability)**
  - Every LLM/tool call is logged in `agent_steps` (role, provider, model, tokens in/out/total, latency_ms, confidence, and full `input_json`/`output_json`).
  - Activity UI (`/activity`) shows the full trace for your own threads; other users cannot access it.
  - Additional fields for multi-agent: `agent_role`, `round_no`, optional `coalition_id`, `vote_score`, `decision_reason`.
- **Conventions & Hygiene**
  - Keep controllers thin; put logic in Services/Jobs; validate with Form Requests.
  - Migrations: edit existing files; keep `php artisan migrate:fresh` green.
  - Blade + Flowbite UI; i18n-ready copy; tests for new services/routes (unit + feature).
- **Tuning & Troubleshooting**
  - Too many SYNTH routes → lower `LLM_SYNTH_COMPLEXITY_TOKENS` or improve grounding.
  - No matches → run embeddings backfill; check `EMBEDDINGS_DIM` and local model tags.
  - Missing model tags → switch provider/model per role in `.env` or pull tags in Ollama.

Small maintenance checklist:
- After adding/moving files: update this tree, explicit lists, and [N files] counts.
- When adding a new subsystem: add a one-liner here and a note in the Developer Guide.
- Verify examples and env defaults still match `config/llm.php`.

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
- Comprehensive testing suite
- UI dashboard and thread views with attachment previews
- Advanced memory analytics and visualization

## Agent Coordination Flow with Laravel MCP

Agent AI uses an intelligent **Coordinator + Specialized Agents** architecture with confidence-based processing. The system automatically routes requests based on complexity and confidence levels, ensuring optimal handling of each interaction.

### Processing Paths

#### 1. Simple Queries (Fast Path, ≥0.75 confidence)
```
User Email → LLM Analysis (ActionInterpretationTool) → Confidence Check →
  → AgentRegistry (Best Match) → Single Agent Processing → Immediate Response

Example: "What's a good pasta recipe?"
1. LLM interprets as info_request (confidence: 0.92)
2. AgentRegistry matches Chef Mario (keywords: recipe, pasta)
3. Chef Mario generates authentic Italian recipe
4. Single email response with thread continuity
```

#### 2. Complex Queries (Orchestration Path)
```
User Email → Complexity Detection → Multi-Agent Orchestrator →
  → LLM Agent Planning → Task Dependencies → Coordinated Execution →
  → Response Synthesis → Single Unified Response

Example: "Plan an Italian anniversary dinner with wine pairings"
1. Detected as complex (keywords: plan, multiple aspects)
2. MultiAgentOrchestrator creates task plan:
   - Chef Mario: Menu planning
   - Sommelier: Wine pairings
   - Event Planner: Timeline & atmosphere
3. Tasks execute with dependencies
4. Coordinator synthesizes one elegant response
```

#### 3. Medium Confidence (0.50-0.74)
```
User Email → LLM Analysis → Medium Confidence →
  → Clarification Email → User Confirms/Adjusts →
  → Normal Processing Path

Example: "Can you help with the sauce?"
1. LLM uncertain about specific sauce (confidence: 0.65)
2. Sends clarification: "Are you asking about pasta sauce or..."
3. User confirms → Routes to Chef Mario
```

#### 4. Low Confidence (<0.50)
```
User Email → LLM Analysis → Low Confidence →
  → Options Email → User Selects →
  → Normal Processing Path

Example: "It needs more..."
1. LLM cannot determine intent (confidence: 0.35)
2. Sends options: "Did you mean:
   a) Add more ingredients
   b) Increase cooking time
   c) Adjust seasoning"
3. User selects → Clear action proceeds
```

### Complete Email Processing Pipeline

#### Phase 1: Email Ingestion & Analysis
1. **Postmark Webhook** (`POST /webhooks/inbound-email`)
   - Receives inbound email via HTTP Basic Auth
   - Validates HMAC signature
   - Stores encrypted payload in `EmailInboundPayload`

2. **Webhook Processing** (`ProcessWebhookPayload` job)
   - Decrypts and parses email content
   - Extracts headers, subject, body, attachments
   - Creates/updates `EmailMessage` with status `'received'`
   - Dispatches `ProcessInboundEmail` job

3. **Email Parsing** (`ProcessInboundEmail` job)
   - Updates status to `'processing'`
   - Extracts clean reply text (removes quoted content)
   - Resolves email threading (RFC 5322)
   - Processes attachments (scanning, extraction)
   - Calls MCP `ActionInterpretationTool` for structured action interpretation

#### Phase 2: Intelligent Agent Routing

4. **Complexity Detection** (`Coordinator::shouldUseMultiAgentOrchestration()`)
   - Analyzes question for complexity keywords: "plan", "organize", "schedule", "multiple", "research"
   - Checks message length (>100 chars = complex)
   - Routes to appropriate processing path

**Simple Path:**
- Single Agent Selection (`AgentRegistry::findBestAgentForAction()`)
- Agent matching by keywords, expertise, role
- Direct task creation and execution

**Complex Path:**
- Multi-Agent Orchestration (`MultiAgentOrchestrator`)
- MCP `DefineAgentsPrompt` generates structured agent plan and task breakdown
- Coordinator creates tasks with dependency management
- Sequential execution with proper ordering

#### Phase 3: Agent Processing & Response

5. **Task Execution** (`AgentProcessor`)
   - Builds contextual prompts with agent personality
   - Includes thread history, user context, agent expertise
   - Calls MCP `ResponseGenerationTool` with agent context and instructions
   - Handles fallbacks at tool level for processing failures

6. **Response Coordination**
   - Single agent: Direct response generation
   - Multi-agent: Coordinator synthesizes all agent outputs
   - Unified response compilation with proper formatting

7. **Email Dispatch** (`SendActionResponse` job)
   - Generates response email via Postmark
   - Includes thread ID in reply-to header for continuity
   - Updates action status to `'completed'`
   - Comprehensive logging and error handling

### Agent System Architecture

#### Current Specialized Agents
- **Chef Mario**: Italian cuisine expert (25+ years Milan experience)
  - Expertise: recipes, techniques, ingredients, timing
  - Keywords: cooking, pasta, pizza, Italian, food
  - Personality: passionate, authentic, traditional
  - Capabilities: info_request, recipe_creation, ingredient_advice
  - Memory scope: recipes, techniques, ingredient combinations
  - Example tasks: Recipe creation, technique explanation, ingredient substitution

- **Tech Support**: Technical specialist
  - Expertise: troubleshooting, software, hardware, configuration
  - Keywords: error, problem, install, configure, setup
  - Personality: methodical, patient, thorough
  - Capabilities: info_request, troubleshooting, configuration
  - Memory scope: common issues, solutions, system requirements
  - Example tasks: Error diagnosis, setup guidance, compatibility checks

- **CoordinatorAgent**: Dynamic orchestrator
  - Expertise: task breakdown, planning, synthesis
  - Created on-demand for complex requests
  - Manages multi-agent collaborations
  - Capabilities: task_planning, dependency_management, synthesis
  - Memory scope: task patterns, coordination strategies
  - Example tasks: Multi-step planning, agent coordination, response synthesis

#### Agent Implementation Details
1. **Base Capabilities**
   - Natural language understanding
   - Context-aware responses
   - Memory integration
   - Confidence scoring
   - Error recovery

2. **Specialization System**
   ```php
   // Agent specialization structure
   'capabilities_json' => [
       'expertise' => ['italian_cooking', 'recipes'],
       'keywords' => ['pasta', 'pizza', 'cooking'],
       'personality' => 'passionate, authentic',
       'experience' => '25 years Milan restaurants',
       'languages' => ['en', 'it'],
       'action_types' => ['info_request', 'recipe_create'],
   ]
   ```

3. **Memory Integration**
   - Scope-based recall (conversation, domain, global)
   - TTL/decay for freshness
   - Confidence-based supersession
   - PII filtering

4. **Response Generation**
   - Personality-consistent tone
   - Context-aware formatting
   - Multi-step explanation
   - Fallback mechanisms

#### Coordinator Responsibilities
- **Complexity Assessment**: Automatic detection of multi-step requests
- **Agent Selection**: Intelligent routing to domain experts
- **Task Orchestration**: Dependency management and execution ordering
- **Response Synthesis**: Unified output from multiple agent contributions
- **Quality Assurance**: Validation and error recovery

### API Endpoints & Actions

#### Currently Implemented Endpoints

| Method | Path | Purpose | Status |
|--------|------|---------|--------|
| `POST` | `/webhooks/inbound-email` | Postmark webhook receiver | ✅ Implemented |
| `GET` | `/a/{action}` | Action confirmation page | ✅ Implemented |
| `POST` | `/a/{action}` | Execute confirmed action | ✅ Implemented |
| `POST` | `/api/actions/dispatch` | Internal action dispatching | ✅ Implemented |

#### Action Types & Flows

**info_request** (Most Common)
- User asks question → LLM interprets → Routes to best agent → Agent generates response → Single email reply

**Complex Orchestration**
- User requests planning → Multi-agent detection → LLM agent planning → Coordinator execution → Synthesized response

**Confirmation Flow** (Future)
- Action created → User receives confirmation email → Signed link → Action execution

### Data Flow & State Management

#### Database Entities
- `EmailMessage`: Raw email data, processing status, threading
- `Thread`: Conversation container, context, participants
- `Action`: User intent interpretation, execution status, results
- `Task`: Agent-specific work units, dependencies, results
- `Agent`: Specialized AI assistants, capabilities, personalities
- `Memory`: User preferences, context learning, TTL management

#### Processing States
```
EmailMessage: received → processing → processed
Action: pending → processing → completed/failed
Task: pending → processing → completed/failed
```

#### Error Handling
- LLM failures → Fallback responses with reduced confidence
- Agent processing errors → Graceful degradation to basic responses
- Email delivery failures → Comprehensive logging, retry logic
- Thread continuity → Reply-to headers maintain conversation context

## 🔄 Clarification Loop Implementation

### Confidence Thresholds
- **≥0.75 High Confidence**: Auto-dispatch action immediately
- **0.50–0.74 Medium Confidence**: Send clarification email with Confirm/Cancel buttons
- **<0.50 Low Confidence**: Send options email with 2–4 clickable choices

### Email Templates
- **Clarification Email** (`resources/views/emails/clarification.blade.php`):
  - Shows interpreted action summary
  - Confirm/Cancel buttons with signed URLs (72h expiry)
  - Reply-to includes thread ID for continuity

- **Options Email** (`resources/views/emails/options.blade.php`):
  - Contextual options based on original request
  - Signed links for each option (72h expiry)
  - Fallback reply link for manual clarification

### Signed Link Security
- All links use `URL::signedRoute()` with 72-hour expiry
- CSRF protection not required (public endpoints)
- Action status prevents double-processing
- Expired links show `action.expired` view

### Database Changes
- Actions get new statuses: `awaiting_confirmation`, `awaiting_input`
- `meta_json` tracks: `clarification_sent_at`, `options_sent_at`
- Idempotent job execution prevents duplicate emails

### Jobs & Controllers
- **`SendClarificationEmail`**: Queued job with idempotence checks
- **`SendOptionsEmail`**: Queued job with contextual options
- **`ActionConfirmationController`**: Extended with `cancel` and `chooseOption` methods
- Routes: `/a/{action}/cancel`, `/a/{action}/choose/{key}`

### Testing Coverage
- **Feature Tests**: Medium confidence, low confidence options, expired URLs
- **Idempotence Tests**: Multiple job dispatches don't send duplicate emails
- **Integration Test**: End-to-end flow from email to final response

## System Architecture

### High-level Architecture Diagram

```mermaid
flowchart LR
  subgraph Email
    U[Recipient] --> PM[Postmark Inbound]
  end

  PM -->|HMAC Webhook| API[/Laravel /webhooks/postmark-inbound/]
  API --> Q[Redis Queue]

  Q --> J1[ProcessWebhookPayload]
  J1 --> J2[ProcessInboundEmail]

  J2 --> T[Thread Resolver]
  J2 --> CL[Clean Reply Extractor]
  J2 --> A1[Attachment Processor]
  J2 --> L1[LLM: Action Interpreter]

  L1 -->|Action Intent| C[Coordinator]
  C -->|Simple| SA[Single Agent Router]
  C -->|Complex| MA[Multi-Agent Orchestrator]

  SA --> AR[Agent Registry]
  AR --> AP[Agent Processor]

  MA --> LAP[LLM Agent Planner]
  LAP --> TC[Task Coordinator]
  TC --> AP

  AP -->|Response| ACT[Action Dispatcher]
  ACT --> OUT[Mailer (Postmark Outbound)]
  OUT --> U

  subgraph Agent System
    CM[Chef Mario<br/>Italian Cuisine]
    TS[Tech Support<br/>Technical Help]
    DA[Dynamic Agents<br/>On-Demand]
  end

  subgraph Web UI
    U2[Browser] --> SL[Signed Link /a/{action}]
    SL --> ACT
    U2 --> APP[Blade/Flowbite Forms]
    APP --> ACT
    U2 --> DLS[Signed Download /attachments/{id}]
  end

  J2 --> L2[LLM: Memory Gate]
  L2 --> MEM[memories]
  DB[(PostgreSQL)] <--> APP

  L1 -. timeout .-> ALT[Fallback Responses]
  MA -. failure .-> FBA[Fallback Agent]
```

### Component Descriptions

* **Webhook Controller**: validates HMAC/IP, stores payload encrypted, queues processing.
* **ProcessInboundEmail**: resolves thread, extracts clean reply, registers attachments, triggers scan/extraction, calls LLMs for action interpretation, routes to coordinator.
* **Coordinator**: Intelligent complexity detection, agent selection, orchestration management.
* **Agent Registry**: Manages specialized agents (Chef Mario, Tech Support), intelligent matching by expertise and keywords.
* **Multi-Agent Orchestrator**: Handles complex requests with LLM agent planning, task dependency management, coordinated execution.
* **Agent Processor**: Executes agent tasks with personality-driven prompts, contextual responses, fallback handling.
* **Action Dispatcher**: Routes processed actions to response generation and email delivery.
* **Attachment Pipeline**: ClamAV scan, MIME/size checks, extraction (txt/md/csv direct; pdf via pdf-to-text), signed downloads.
* **Memory System**: Learns user preferences, maintains context across conversations with TTL management.
* **LLM Client**: provider + fallback, timeouts/retry, token caps, confidence calibration.
* **Laravel MCP Framework**: Structured tools and prompts for error-resistant LLM interactions.
* **Auth**: passwordless challenges (codes and magic links).
* **UI**: Blade/Flowbite wizards, i18n middleware.
* **Observability**: Horizon, comprehensive logging, LLM call tracking, agent performance metrics.

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
| Laravel MCP | Laravel MCP Framework            | ^0.x    | Structured, error-resistant LLM interactions |
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
* Story 2: Multilingual interpretation; when confidence < 0.75, max 2 clarification rounds; otherwise options email. When grounding hits, replies include evidence from retrieved snippets.
* Story 3: On click or reply ≥ 0.75: user + identity + membership are created; login email sent.
* Story 4: Memories have TTL/decay; supersede; admin can export/purge; provenance visible.
* Story 5: Horizon visible; logs show provider, model, latency, tokens, confidence, outcome.
* Story 6: Language detected; UI/emails in detected language; EN fallback; `Content-Language` set.
* **Story 7: Attachments ≤ 25MB each (default), safe MIME whitelist, mandatory ClamAV scan, extraction and summary available to the LLM, signed downloads.**

**Grounding/Evaluation**
- Top-k retrieval used for all answers.
- Log every LLM/tool call in `agent_steps`.
- Model choice recorded per call.

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

### Authentication & User Management System

**Status**: Core system implemented with passwordless authentication and contact-user relationships.

**Architecture**:

1. **Core Entities**:
   - **Contact**: Email participant (created from inbound emails)
     ```php
     contacts: ulid, email, name, meta_json, created_at, updated_at
     ```
   - **User**: Authenticated web user (created upon first login)
     ```php
     users: ulid, email, display_name, locale, timezone, status
     ```
   - **ContactLink**: Maps contacts to authenticated users
     ```php
     contact_links: contact_id, user_id, status (linked|blocked)
     ```

2. **Authentication Flow**:
   ```mermaid
   graph TD
     A[Email Participant] -->|Sends Email| B[System]
     B -->|Creates| C[Contact Record]
     
     A -->|Visits Website| D[/auth/challenge]
     D -->|Enter Email| E[Send Code]
     E -->|Verify Code| F[/auth/verify]
     F -->|Success| G[Create/Find User]
     G -->|Link| H[ContactLink Record]
     H -->|Redirect| I[Dashboard]
   ```

3. **Components**:
   - `ChallengeController`: Handles initial login request
   - `VerifyController`: Verifies the 6-digit code
   - `LoginController`: Processes magic link login
   - `AuthService`: Core authentication logic
   - `ContactLinkService`: Manages contact-user relationships

4. **Features**:
   - Passwordless authentication (email codes)
   - Magic link login support
   - Multiple emails per user (through ContactLinks)
   - Shared inbox support (multiple users per contact)
   - Rate limiting on auth endpoints
   - Session-based authentication
   - Remember-me functionality
   - Automatic contact linking

5. **Security**:
   - Rate limiting: 5/15min per email for challenges
   - Code expiry: 15 minutes
   - Magic links: 60-minute expiry with nonce
   - Session security: HTTP-only cookies
   - CSRF protection on all forms
   - No password storage/management needed

6. **User Dashboard**:
   - View all threads from linked contacts
   - Manage contact relationships
   - Update profile (name, locale, timezone)
   - View action history
   - Access attachments
   - Manage API tokens (future)

7. **Implementation Details**:
   ```php
   // Rate Limiting
   'auth.challenge' => '5,15' // 5 attempts per 15 minutes
   'auth.verify' => '10,15'   // 10 attempts per 15 minutes

   // Session Config
   'session.lifetime' => 120  // 2 hours
   'session.expire_on_close' => false
   'session.secure' => true   // HTTPS only
   ```

8. **Future Enhancements**:
   - OAuth provider support
   - Account recovery flow
   - Multi-factor authentication
   - Team/organization support
   - Role-based access control
   - API token management

### LLM Client & Laravel MCP Framework (Implemented)

**Status**: Fully implemented with Laravel MCP framework for structured, error-resistant LLM interactions.

**Architecture:**
- **Laravel MCP Server** at `/mcp/ai` providing RESTful API for structured operations
- **Schema-driven tools** with JSON validation and error handling
- **Structured prompts** for complex multi-agent orchestration
- **Fallback mechanisms** at tool and prompt levels

**MCP Tools (Structured Operations):**
- **`ActionInterpretationTool`**: Email content → validated action JSON (type, parameters, confidence)
  - Input: `clean_reply`, `thread_summary`, `attachments_excerpt`, `recent_memories`
  - Output: Structured JSON with `action_type`, `parameters`, `confidence`
- **`AgentSelectionTool`**: Context analysis → optimal agent selection
  - Input: `account_id`, `action_data`, `context` (thread, locale, memories)
  - Output: `agent_id`, `agent_name`, `capabilities`, `confidence_score`
- **`ResponseGenerationTool`**: Agent expertise → formatted response
  - Input: `agent_id`, `user_query`, `context` (thread, instructions, locale)
  - Output: `response`, `confidence`, `processing_time`, `model_used`

**MCP Prompts (Complex Orchestration):**
- **`DefineAgentsPrompt`**: Complex requests → agent breakdown with tasks & dependencies
  - Arguments: `conversation_subject`, `conversation_plaintext_content`, `goal`, `available_tools`
  - Returns: JSON with agent definitions, roles, capabilities, and task orchestrations
- **`OrchestrateComplexRequestPrompt`**: Multi-agent coordination → user confirmation flow
  - Arguments: `goal`, `defined_agents`, `conversation_subject`, `conversation_plaintext_content`
  - Returns: Formatted coordination message for user approval

**Error Prevention:**
- **Schema validation** prevents malformed requests/responses
- **Tool-level fallbacks** when LLM calls fail
- **Structured JSON** eliminates text parsing errors
- **Dependency injection** for clean, testable code

**Future Enhancements:**
- Multi-provider support (OpenAI, Anthropic) with automatic failover and role-based routing (CLASSIFY/GROUNDED/SYNTH)
- Tool chaining for complex multi-step operations
- MCP resource integration for external data sources
- Enhanced confidence score calibration and fallback logic
- Dynamic token limit adjustment based on model capabilities

### i18n: Internationalization System

**Status**: Fully implemented with language detection, translations, and email templates.

**Translation Conventions**:
1. **File Structure**:
   ```
   resources/lang/
   ├── en/
   │   ├── auth.php    - Authentication messages
   │   ├── emails.php  - Email template text
   │   └── messages.php - General UI text
   └── nl/
       ├── auth.php    - Dutch authentication
       ├── emails.php  - Dutch email templates
       └── messages.php - Dutch UI text
   ```

2. **String Format**:
   - Use double quotes for all keys and values
   - No escaping needed for apostrophes
   - Example:
     ```php
     return [
         "auth" => [
             "title" => "Your Login Code",
             "message" => "Don't forget your code!",
         ],
     ];
     ```

3. **Organization**:
   - Hierarchical structure with dot notation
   - Group by feature (auth, emails, etc.)
   - Consistent keys across languages
   - Clear, descriptive key names

4. **Supported Languages**:
   - English (en_US) - Default
   - Dutch (nl_NL)
   - French (fr_FR) - Planned
   - German (de_DE) - Planned

### i18n: Language Detection System

**Status**: Implemented with configurable detection sources and fallback. Content-based detection needs further testing.

**Architecture**:

1. **Configuration** (`config/language.php`):
   - Supported locales mapping (ISO codes to full locales)
   - Detection settings (confidence threshold, cache TTL)
   - Detection source priority (URL, session, header, content)
   - LLM fallback settings

2. **Components**:
   - `LanguageDetector` service: Core detection using library + LLM fallback
   - `DetectLanguage` middleware: Request-level locale handling

3. **Detection Flow**:
   ```
   Request → DetectLanguage Middleware
   ↓
   Check sources in configured priority:
   1. URL parameter (?lang=)
   2. Session storage
   3. Accept-Language header
   4. Content-based detection (experimental):
      → LanguageDetector service
      → Language detection library
      → LLM fallback if needed
   ↓
   Set App locale + Content-Language header
   ```

4. **Features**:
   - Configurable locale mapping via `config/language.php`
   - Confidence-based detection with thresholds
   - 24-hour detection caching
   - Graceful fallback chain
   - LLM backup for complex cases
   - Session persistence
   - Content-Language headers
   - Comprehensive test coverage for URL/session/header detection

5. **Supported Locales**:
   - English (en_US)
   - Dutch (nl_NL)
   - French (fr_FR)
   - German (de_DE)
   - Easily extensible via config

6. **Adding New Locales**:
   ```php
   // config/language.php
   'supported_locales' => [
       'es' => 'es_ES',     // Add Spanish
       'es_es' => 'es_ES',  // With full locale
   ]
   ```

7. **Known Issues**:
   - Content-based language detection needs further testing
   - Service container binding issues with middleware in tests
   - See `tests/Feature/DetectLanguageTest.php` for details

### Attachments Processing (Implemented)

**Status**: Fully implemented end-to-end attachment pipeline with security and async processing.

**Implementation Details:**
- **MIME whitelist**: text/plain, text/markdown, text/csv, application/pdf
- **Size limits**: 25MB per file (configurable), 40MB total per email (configurable)
- **Security**: Mandatory ClamAV scan before any extraction; infected files quarantined
- **Text extraction**: Direct for txt/md/csv; spatie/pdf-to-text for PDFs with timeout guards
- **LLM summarization**: attachment_summarize prompt generates concise gist + 3-6 bullets
- **Async processing**: Scan → Extract → Summarize chain on dedicated 'attachments' queue
- **Signed downloads**: GET /attachments/{id} with 15-60min expiry, nonce validation, infected denial
- **LLM integration**: attachments_excerpt assembled from summaries for action interpretation context

**Environment Variables:**
```env
ATTACH_MAX_SIZE_MB=25
ATTACH_TOTAL_MAX_SIZE_MB=40
CLAMAV_HOST=127.0.0.1
CLAMAV_PORT=3310
```

**Flow Diagram:**
1. Email received → ProcessInboundEmail stores attachments + dispatches ScanAttachment
2. ScanAttachment (queue=attachments) → calls ClamAV → clean: ExtractAttachmentText, infected: stop
3. ExtractAttachmentText → extracts text → SummarizeAttachment
4. SummarizeAttachment → calls LLM → stores summary in summarize_json
5. ProcessInboundEmail::getAttachmentsExcerpt() → assembles context for ActionInterpretationTool

**Security Features:**
- ClamAV mandatory scanning prevents malware processing
- Signed URLs with short expiry prevent unauthorized access
- Nonce validation prevents replay attacks
- MIME whitelist prevents dangerous file types
- Size limits prevent DoS attacks
- Comprehensive logging for audit trails

### API Endpoints

#### Currently Implemented

| Method | Path                       | Auth   | Purpose                 | Status |
| ------ | -------------------------- | ------ | ----------------------- | ------ |
| POST   | /webhooks/postmark-inbound | HMAC   | Receive inbound email   | ✅ Implemented |
| GET    | /api/agent-specializations | Auth   | List specializations    | ✅ Implemented |
| POST   | /api/agent-specializations | Auth   | Create specialization   | ✅ Implemented |
| GET    | /api/agent-specializations/{id} | Auth | Get specialization   | ✅ Implemented |
| PUT    | /api/agent-specializations/{id} | Auth | Update specialization| ✅ Implemented |
| DELETE | /api/agent-specializations/{id} | Auth | Delete specialization| ✅ Implemented |

#### Implemented Endpoints

**Public/External API:**
- `GET /a/{action}` - One-click action confirmations (signed links)
- `GET /login/{token}` - Magic link login verification
- `POST /auth/challenge` - Request passwordless authentication
- `POST /auth/verify` - Verify authentication code
- `GET /attachments/{attachment}` - Signed attachment downloads (clean files only, 15-60min expiry)

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
* Webhook HTTP Basic Auth (Postmark standard); throttling.
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

You are a strict JSON generator. Detect exactly one action from the user's email reply.
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

  * "On … wrote:"
  * "Op … schreef …"
* Remove signatures using heuristics, e.g.:

  * "-- "
  * "Sent from …" / "Verzonden vanaf …"
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
* **Language**: model writes in `:detected_locale` (e.g., "nl" or "en-GB").
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
**Goal**: One short, concrete question in the user's language.
**Temperature**: 0.3

```php
'clarify_question' => [
  'temperature' => 0.3,
  'backstory' => 'You write one concise clarification question matching the user's language.',
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
  'backstory' => 'You draft a brief options email in the user's language.',
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
* **revise**: `{changes: string[]}` — concrete bullet-style changes ("move to 14:00", "add CC: x\@y").
* **select_option**: `{option_id | label}` — prefer `option_id` from thread/context; fallback to `label`.
* **provide_value**: `{key, value}` — free key-value ("budget", "under 500 EUR").
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

* No "manager/agent orchestration" prompts: orchestration is server-side (MCP + dispatcher).
* No chain-of-thought or "think aloud" instructions: we request **final JSON only**.
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
| `threads` | Email conversation containers | `subject`, `context_json`, `version`, `version_history`, `last_activity_at` |
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
- Postmark webhook integration with HMAC validation and thread continuity
- RFC 5322 email threading via ThreadResolver service with reply-to thread IDs
- ULID primary keys, JSONB storage, comprehensive relationships
- LLM client with gpt-oss:20b model and Ollama fallback
- Intelligent Agent Coordination System with specialized agents
- ProcessInboundEmail job with LLM interpretation and action generation
- Multi-Agent Orchestrator for complex query handling
- Email processing status tracking and async timeouts (10min LLM, 15min queue)
- Action dispatching with signed links and confirmation flows
- Agent Registry with intelligent routing (Chef Mario, Tech Support, dynamic agents)
- Memory Gate with TTL/decay and confidence scoring
- Memory retrieval with scope-based relevance and recency decay
- Memory pruning with configurable thresholds and TTL enforcement
- Memory API endpoints for forget/preview with signed URLs

🚧 **In Development**:
- Passwordless authentication system
- Blade/Flowbite UI foundation
- MCP layer and tool execution

📋 **Planned Features**:
- MCP layer for schema-driven tool calls with SSRF protection
- Action interpretation and clarification loops
- Attachment processing pipeline (ClamAV, extraction, summarization)
- Memory gate with TTL/decay and user preference learning
- i18n language detection and multilingual UI

**Tech Stack**: Laravel 12, PHP 8.4, PostgreSQL 17+, Redis 7, Postmark, Ollama, ClamAV, Tailwind/Flowbite.

**Development Status**: Phase 1A (Database/Models) complete. Ready for Phase 1B (Auth/UI) and Phase 2 (LLM/MCP).

# Agent-AI — How it Works (Plain)

## Big picture

Agent‑AI is an email‑native assistant. You email it like a coworker. It reads what you send, looks up relevant facts from your own emails and files, and drafts a helpful reply. It is not a chat toy; it's a steady teammate that works from your inbox.

Email is where most work starts: requests, approvals, files, and decisions. Agent‑AI stays close to that flow. It makes a memory from what you send and what it learns, so it can help better next time.

Behind the scenes, multiple small "agents" cooperate with simple rules. Some plan, some do the work, some critique, and one makes the final call. Tools and retrieval keep it grounded in your own data.

## A guided tour: From email to answer
1) You send an email → a Contact is created/updated (on the very first contact, an Account is auto‑created from APP_NAME).
2) The message attaches to a Thread.
3) Your first web login with that email creates a User and links it to the Contact via `contact_links` (passwordless code).
4) The Coordinator plans the work; Workers fetch/write drafts; the Critic checks evidence; the Arbiter picks the best; Memory saves the outcome.
5) You see the full trace (Activity) for your own threads.

```
You → Email → Thread → Plan → Work → Debate → Decide → Memory → Reply
```

## Key ideas (Plain)
- Agent: a small specialist that does one job (plan, write, check, decide).
- Tool: a safe function the agent can call (e.g., summarize attachment).
- Retrieval: finding relevant bits from your past emails/files.
- Embedding: turning text into numbers for fast search.
- pgvector: Postgres plugin that stores those numbers.
- Cosine similarity: a measure of "closeness" between two embeddings.
- Routing: CLASSIFY → retrieval → GROUNDED | SYNTH (small model vs big model choice).
- Token: the chunk size of text for the model; affects cost and time.
- Latency: how long a step takes; Confidence: how sure the model is.
