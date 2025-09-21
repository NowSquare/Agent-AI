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

### MVP Scope & Phases

**Phase 1: Inbound/Webhook/Threading/Auth Baseline**
- Postmark webhook with HMAC validation
- Email threading and clean reply extraction
- Passwordless authentication (challenge/verify)
- Basic dashboard and thread list pages
- Database schema (accounts, users, threads, email_messages)

**Phase 2: LLM/MCP/Actions**
- LLM client with Ollama fallback
- Action interpretation and confidence scoring
- Clarification loop (max 2 rounds)
- MCP layer with tool registry
- Action dispatcher and outbound email
- Memory gate and basic decay

**Phase 3: Attachments/Quality/Observability**
- Attachment pipeline (scan, extract, summarize)
- Signed downloads and security
- Horizon monitoring and queues
- i18n language detection
- Testing, linting, and deployment

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
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── PurgeExpiredMemories.php
│   │   │   └── ScanAttachmentsCommand.php
│   │   └── Kernel.php
│   ├── Events/
│   │   ├── ActionCompleted.php
│   │   └── AttachmentScanned.php
│   ├── Exceptions/
│   │   ├── LlmTimeoutException.php
│   │   └── VirusDetectedException.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── ActionController.php
│   │   │   │   └── ThreadController.php
│   │   │   ├── Auth/
│   │   │   │   ├── ChallengeController.php
│   │   │   │   └── VerifyController.php
│   │   │   ├── McpController.php
│   │   │   └── Webhook/
│   │   │       └── PostmarkInboundController.php
│   │   ├── Middleware/
│   │   │   ├── LanguageDetection.php
│   │   │   └── McpAuth.php
│   │   └── Resources/
│   │       └── ThreadResource.php
│   ├── Jobs/
│   │   ├── ProcessInboundEmail.php
│   │   ├── ScanAttachmentJob.php
│   │   ├── ExtractAttachmentJob.php
│   │   └── SummarizeThreadJob.php
│   ├── Listeners/
│   │   ├── SendClarificationEmail.php
│   │   └── UpdateThreadContext.php
│   ├── Mail/
│   │   ├── ClarificationEmail.php
│   │   ├── OptionsEmail.php
│   │   └── WelcomeEmail.php
│   ├── Models/
│   │   ├── Account.php
│   │   ├── Action.php
│   │   ├── AttachmentExtraction.php
│   │   ├── EmailAttachment.php
│   │   ├── EmailInboundPayload.php
│   │   ├── EmailMessage.php
│   │   ├── Memory.php
│   │   ├── Thread.php
│   │   └── User.php
│   ├── Policies/
│   │   ├── ActionPolicy.php
│   │   ├── AttachmentPolicy.php
│   │   └── MemoryPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── McpServiceProvider.php
│   │   └── RouteServiceProvider.php
│   ├── Schemas/
│   │   ├── ActionInterpretationSchema.php
│   │   ├── MemoryExtractionSchema.php
│   │   └── ThreadSummarySchema.php
│   ├── Services/
│   │   ├── AttachmentService.php
│   │   ├── LlmClient.php
│   │   ├── McpRouter.php
│   │   └── ThreadResolver.php
│   └── Mcp/
│       ├── ToolRegistry.php
│       ├── Tools/
│       │   ├── ProcessAttachmentTool.php
│       │   ├── SendEmailTool.php
│       │   └── StoreMemoryTool.php
│       └── ToolSchemas/
│           ├── ProcessAttachmentSchema.php
│           ├── SendEmailSchema.php
│           └── StoreMemorySchema.php
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

### MCP Layer

#### ToolRegistry & Guard

```php
// app/Providers/McpServiceProvider.php
class McpServiceProvider extends ServiceProvider {
  public function register(): void {
    $this->app->bind(ToolRegistry::class, fn() => new ToolRegistry([
      'send_email' => app(\App\Mcp\Tools\SendEmailTool::class),
      'store_memory' => app(\App\Mcp\Tools\StoreMemoryTool::class),
      'process_attachment' => app(\App\Mcp\Tools\ProcessAttachmentTool::class),
    ]));
  }
}

// app/Mcp/ToolRegistry.php
class ToolRegistry {
  public function __construct(private array $tools) {}
  public function run(string $tool, array $payload): array {
    abort_unless(isset($this->tools[$tool]), 404, 'Unknown tool');
    return $this->tools[$tool]->execute($payload);
  }
}
```

Auth: use a **custom token guard** that matches `api_tokens.token_hash` (SHA-256) and checks abilities.

#### Example Tool Schema & SSRF Prevention

```php
// app/Mcp/Tools/ProcessAttachmentTool.php
class ProcessAttachmentTool implements Tool {
  public function schema(): array {
    return [
      'attachment_id' => 'required|exists:email_attachments,id',
      'action' => 'required|in:extract_text,summarize,analyze_csv'
    ];
  }
  public function execute(array $p): array {
    $att = EmailAttachment::findOrFail($p['attachment_id']);
    Authorization::checkAttachmentAccess($att); // prevents IDOR
    // No external URLs in input; only Storage::read() on whitelisted disk
    return app(AttachmentService::class)->process($att, $p['action']);
  }
}
```

### LLM Client

#### Config

```php
// config/llm.php
return [
  'provider' => env('LLM_PROVIDER','ollama'), // openai|anthropic|groq|ollama
  'timeouts' => ['connect'=>1.0,'read'=>4.0],
  'retry' => 1,
  'models' => [
    'action' => env('LLM_ACTION_MODEL','llama3'),
    'memory' => env('LLM_MEMORY_MODEL','llama3'),
  ],
  'caps' => ['input_tokens'=>2000,'summary_tokens'=>500,'output_tokens'=>300],
  'confidence' => [
    'scale' => '0_1',
    'auto' => 0.75,
    'confirm' => 0.50,
    'provider_scales' => [
      'openai' => 1.00,
      'anthropic' => 1.00,
      'ollama' => 1.00
    ]
  ],
];
```

#### Service

```php
// app/Services/LlmClient.php
class LlmClient {
  public function __construct(private HttpClient $http, private LoggerInterface $log) {}
  public function json(string $purpose, array $payload, int $timeoutMs = 4000): array {
    $cfg = config('llm');
    $provider = $cfg['provider'];
    try {
      $resp = $this->callProvider($provider, $purpose, $payload, $timeoutMs);
    } catch (\Throwable $e) {
      $this->log->warning('llm.primary.failed', ['p'=>$provider,'err'=>$e->getMessage()]);
      if ($provider !== 'ollama') {
        $resp = $this->callProvider('ollama', $purpose, $payload, $timeoutMs);
      } else {
        throw $e;
      }
    }
    return $this->validateJson($resp);
  }
  // callProvider() maps to OpenAI/Anthropic/Groq/Ollama endpoints and enforces caps (truncate context).
}
```

### i18n: Language Detection

Use an on-prem library on the **clean reply**. Example:

```php
// composer: "patrickschur/language-detection"
$detector = new LanguageDetection\Language();
$lang = array_key_first($detector->detect($cleanText)->limit(1)->close());
$locale = in_array($lang, ['nl','en','fr','de']) ? $lang : 'en';
App::setLocale($locale);
```

### Attachments — Processing

#### Storage and Limits

* Allowed MIME: `text/*`, `application/pdf`, `text/csv`, `application/vnd.ms-excel` (csv), `application/json`.
* Size: default ≤ **25MB** per file; total per email ≤ **40MB**.
* Disk: `attachments` (local or S3). Path: `attachments/{ulid}/{filename}`.
* Signed downloads: `GET /attachments/{id}?signature=...` (temporary, nonce).

#### Scan and Extraction

```php
// app/Jobs/ScanAttachmentJob.php
class ScanAttachmentJob implements ShouldQueue {
  public function __construct(public string $attachmentId) {}
  public function handle(AttachmentService $svc): void {
    $svc->scanOrFail($this->attachmentId); // ClamAV clamdscan/INSTREAM
  }
}

// app/Jobs/ExtractAttachmentJob.php
class ExtractAttachmentJob implements ShouldQueue {
  public function __construct(public string $attachmentId) {}
  public function handle(AttachmentService $svc): void {
    $svc->extractAndCacheText($this->attachmentId); // txt/md/csv direct; pdf via poppler/spatie
  }
}
```

```php
// app/Services/AttachmentService.php (sketch)
class AttachmentService {
  public function scanOrFail(string $id): void { /* ClamAV call, mark status */ }
  public function extractAndCacheText(string $id): void { /* produce preview text and store in cache/table */ }
  public function process(EmailAttachment $att, string $action): array { /* summarize/analyze_csv via LLM */ }
}
```

#### Inbound Pipeline in `ProcessInboundEmail`

1. Register attachments from the Postmark payload (filename, MIME, size).
2. Store the file via `Storage::putFileAs()`.
3. Queue `ScanAttachmentJob` → when “clean,” queue `ExtractAttachmentJob`.
4. Add a **short excerpt** (first N KB of text) to the LLM context with a **token cap**.

#### Purge & Retention

* Purge files along with `email_messages` or via TTL (default 30 days for raw, 180 days for attachments).
* Cron jobs: purge orphaned files, remove caches.

### API Endpoints

#### Public/External API

| Method | Path                       | Auth   | Purpose                 | Request JSON                                | Response JSON                                                                |
| ------ | -------------------------- | ------ | ----------------------- | ------------------------------------------- | ---------------------------------------------------------------------------- |
| POST   | /webhooks/postmark-inbound | HMAC   | Receive inbound email   | Postmark payload                            | `{"queued":true,"payload_id":"01H..."}`                                      |
| GET    | /a/{action}                | signed | One-click action        | –                                           | `{"status":"completed","action_id":"01H..."}` or `{"status":"already_done"}` |
| GET    | /login/{token}             | signed | Magic link login        | –                                           | `{"authenticated":true,"user_id":"01H..."}`                                  |
| POST   | /auth/challenge            | none   | Request code/magic link | `{"identifier":"user@example.com"}`         | `{"challenge_id":"01H..."}`                                                  |
| POST   | /auth/verify               | none   | Verify code             | `{"challenge_id":"01H...","code":"123456"}` | `{"authenticated":true}`                                                     |
| GET    | /attachments/{id}          | signed | **Download attachment** | –                                           | binary (Content-Disposition)                                                 |

#### Internal/UI and MCP API

| Method | Path                  | Auth   | Purpose             | Response JSON                                                           |
| ------ | --------------------- | ------ | ------------------- | ----------------------------------------------------------------------- |
| ANY    | /mcp/agent            | token  | MCP server endpoint | tool-specific JSON I/O                                                  |
| POST   | /api/actions/dispatch | bearer | UI form → action    | `{"queued":true,"action_id":"01H..."}`                                  |
| GET    | /api/threads/{id}     | bearer | Thread detail       | `{"thread":{...},"messages":[...],"actions":[...],"attachments":[...]}` |

**Error handling**: 401/403 for auth, 422 on schema validation, 409 on idempotency conflict, 429 on rate limit.

### Laravel-Specific Patterns

#### Policies

```php
Gate::define('actions.execute', [ActionPolicy::class, 'execute']);
Gate::define('memories.purge', [MemoryPolicy::class, 'purge']);
Gate::define('attachments.view', [AttachmentPolicy::class, 'view']);
```

#### Jobs & Chaining

```php
Bus::chain([
  new ProcessInboundEmail($payloadId),
  new SummarizeThreadJob($threadId),
])->onQueue('inbound');
```

#### Auth & Rate Limiting

```php
RateLimiter::for('auth-challenge', fn($r) => Limit::perMinutes(15, 5)->by($r->input('identifier')));
RateLimiter::for('signed-downloads', fn($r) => Limit::perMinute(30)->by($r->ip()));
```

#### Threading Headers (RFC)

See existing snippet; unchanged.

## Non-Functional Requirements

### Performance

* LLM: P50 < 2 s; P95 < 4 s; timeout 4 s.
* Inbound → action ≤ 5 s P95 (without heavy extraction).
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
  mailpit:
    image: axllent/mailpit
    ports: ["8025:8025"]
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
    'timeout_ms' => 4000,
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
* **Latency**: P50 < 2s, P95 < 4s; `timeout_ms=4000`, retry once on 5xx/timeout.
* **Golden set**: ≥100 examples per action; measure precision/recall; tune `temperature` and calibration.
* **A/B testing**: keep `options_email_draft` variants per language short and consistent.

## Notes on i18n & Attachments

* **Language**: library-based detection first; prompt `language_detect` is fallback only.
* **Attachments**: text extraction handled **outside the LLM** (PDF-to-text, CSV parser). Prompt `attachment_summarize` is for **short gists**. For large files → use excerpt (e.g., first 16–32 KB) + link to full text on disk.

## What We Explicitly Do Not Do

* No “manager/agent orchestration” prompts: orchestration is server-side (MCP + dispatcher).
* No chain-of-thought or “think aloud” instructions: we request **final JSON only**.
* No direct tool-calls by the model: the model outputs intent/parameters; the server decides and validates.

## Appendix H — Migrations and Indexes

All migrations are **PostgreSQL-optimized**, PSR-12, using **ULID** as the primary key for all domain tables. String fields with enumerations are documented with clear English comments. JSON columns are **jsonb**; GIN indexes are created where needed. Framework tables use Laravel defaults.

### 0. Extensions and Framework/Infra

Includes enabling `pg_trgm`, jobs, failed_jobs, job_batches, sessions, notifications, password_reset_tokens, personal_access_tokens, cache, and cache_locks.

### 1. Domain

Covers **accounts, users, user_identities, memberships, contacts, contact_links, threads, email_messages, actions, memories, agents, tasks, events, event_participants, availability_polls, availability_votes, auth_challenges, api_tokens, email_inbound_payloads, email_attachments, attachment_extractions**.

### 2. Alter Migrations

* `alter_threads_add_starter_fk` – adds FK after `email_messages` table exists
* `alter_actions_add_clarification_state` – tracks clarification loop

### 3. Post-Migrate Indexes

Optional indexes, e.g. `actions_type_idx`.

### 4. Enum Values & Meanings

Exhaustive mapping of all string enums across tables, e.g. `users.status`, `actions.type`, `memories.scope`, `email_attachments.scan_status`, etc.

## Appendix I — Full Migrations and Indexes (PostgreSQL, ULID Primary Keys)

All migrations are **PostgreSQL-optimized**, PSR-12, and use **ULID** as the primary key for all domain tables. String fields with enumerations include clear English comments. JSON columns are **jsonb**; GIN indexes are created where appropriate. Framework tables keep Laravel defaults for compatibility.

### 0. Extensions and Framework/Infra

```php
<?php
// 2025_01_01_000000_enable_pg_extensions.php
// Enables optional but recommended PostgreSQL extensions used by indexes.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Trigram index support (used for fast LIKE/ILIKE on message_id etc.)
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        // GIN indexes on jsonb are supported natively; btree_gin is not required for these migrations.
    }
    public function down(): void {
        // Do not drop extensions on down; they may be shared by other apps.
    }
};
```

```php
<?php
// 2025_01_01_000010_create_jobs_table.php
// Laravel queue jobs (default schema). Keep as-is for Horizon compatibility.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
    public function down(): void { Schema::dropIfExists('jobs'); }
};
```

```php
<?php
// 2025_01_01_000020_create_failed_jobs_table.php
// Default Laravel failed jobs table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('failed_jobs'); }
};
```

```php
<?php
// 2025_01_01_000030_create_job_batches_table.php
// Default Laravel job batches (Bus::batch).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('job_batches'); }
};
```

```php
<?php
// 2025_01_01_000040_create_sessions_table.php
// Session storage (database driver). user_id matches ULID format for domain users.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->char('user_id', 26)->nullable()->index(); // ULID of users.id
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
    public function down(): void { Schema::dropIfExists('sessions'); }
};
```

```php
<?php
// 2025_01_01_000050_create_notifications_table.php
// Default Laravel notifications table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->string('notifiable_id')->index();
            $table->text('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
        });
    }
    public function down(): void { Schema::dropIfExists('notifications'); }
};
```

```php
<?php
// 2025_01_01_000060_create_password_reset_tokens_table.php
// Optional: only used if classic password reset flows are enabled.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('password_reset_tokens'); }
};
```

```php
<?php
// 2025_01_01_000070_create_personal_access_tokens_table.php
// Optional: required only if using Sanctum. Not used by MCP custom guard.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('personal_access_tokens'); }
};
```

```php
<?php
// 2025_01_01_000080_create_cache_tables.php
// Optional: if using database cache/locks.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }
    public function down(): void {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
```

### 1. Domain

```php
<?php
// 2025_01_01_010000_create_accounts_table.php
// Tenant (organization) container. One account has many threads, actions, etc.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('accounts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('name'); // Human-readable account name.
            $t->json('settings_json')->nullable(); // jsonb: per-account config (retention, locales, etc.)
            $t->timestampsTz();
            $t->index('name');
        });
    }
    public function down(): void { Schema::dropIfExists('accounts'); }
};
```

```php
<?php
// 2025_01_01_010100_create_users_table.php
// Platform users (may belong to multiple accounts via memberships).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('display_name')->nullable();
            $t->string('locale', 12)->default('en'); // e.g., "en", "nl", "fr".
            $t->string('timezone', 64)->default('Europe/Amsterdam'); // IANA TZ name.
            $t->string('status', 32)->default('active'); // "active" | "disabled"
            $t->timestampsTz();
            $t->index('locale');
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};
```

```php
<?php
// 2025_01_01_010200_create_user_identities_table.php
// Login/contact identities. "type"+"identifier" must be globally unique.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_identities', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 32); // "email" | "phone" | "oidc"
            $t->string('identifier'); // e.g., email address.
            $t->timestampTz('verified_at')->nullable(); // Set when ownership was confirmed.
            $t->boolean('primary')->default(false); // True if default identity for user.
            $t->timestampsTz();
            $t->unique(['type','identifier']);
        });
    }
    public function down(): void { Schema::dropIfExists('user_identities'); }
};
```

```php
<?php
// 2025_01_01_010300_create_memberships_table.php
// Mapping users to accounts with a role.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('memberships', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $t->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('role', 32)->default('member'); // "admin" | "member" | "guest"
            $t->timestampsTz();
            $t->unique(['account_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('memberships'); }
};
```

```php
<?php
// 2025_01_01_010400_create_contacts_table.php
// Ad-hoc participants under an account; may later link to a real user.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contacts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $t->string('email'); // Canonicalized lower-case.
            $t->string('name')->nullable();
            $t->json('meta_json')->nullable(); // jsonb: free-form metadata.
            $t->timestampsTz();
            $t->unique(['account_id','email']); // One contact per email within an account.
        });
    }
    public function down(): void { Schema::dropIfExists('contacts'); }
};
```

```php
<?php
// 2025_01_01_010500_create_contact_links_table.php
// Links a contact to a user when an upgrade happens (or is blocked).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contact_links', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $t->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('status', 32)->default('linked'); // "linked" | "blocked"
            $t->timestampsTz();
            $t->unique(['contact_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('contact_links'); }
};
```

```php
<?php
// 2025_01_01_010600_create_threads_table.php
// Conversation container. Subject lives here; individual messages may override if needed.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('threads', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $t->string('subject'); // Canonical subject (normalized).
            $t->ulid('starter_message_id')->nullable(); // FK added in a later migration (email_messages created after).
            $t->json('context_json')->nullable(); // jsonb: rolling summary, state, counters.
            $t->timestampsTz();
            $t->index('account_id');
        });
        DB::statement('CREATE INDEX threads_context_gin ON threads USING GIN ((context_json))');
    }
    public function down(): void { Schema::dropIfExists('threads'); }
};
```

```php
<?php
// 2025_01_01_010700_create_email_messages_table.php
// Individual email messages within a thread. Stores addressing and delivery metadata.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('email_messages', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();

            $t->string('direction', 16); // "inbound" | "outbound"
            $t->string('message_id')->unique(); // RFC 5322 Message-ID.
            $t->string('in_reply_to')->nullable()->index();
            $t->string('references', 2048)->nullable(); // Space-separated list.

            // Addressing; arrays serialized as jsonb for consistency.
            $t->string('from_email')->nullable(); // Lower-case email.
            $t->string('from_name')->nullable();
            $t->json('to_json')->nullable();   // jsonb: array of {"email","name"}
            $t->json('cc_json')->nullable();   // jsonb
            $t->json('bcc_json')->nullable();  // jsonb

            $t->string('subject')->nullable(); // Optional per-message subject override.
            $t->json('headers_json')->nullable(); // jsonb: selected headers for quick access.

            // Provider metadata (mainly for outbound delivery status).
            $t->string('provider_message_id')->nullable(); // e.g. Postmark MessageID.
            $t->string('delivery_status', 32)->nullable(); // "queued" | "sent" | "bounced" | "failed" | null for inbound.
            $t->json('delivery_error_json')->nullable();   // jsonb: provider error payload.

            // Bodies
            $t->longText('text_body')->nullable();
            $t->longText('html_body')->nullable();

            // Misc
            $t->string('x_thread_id')->nullable(); // Internal hint header.
            $t->unsignedBigInteger('raw_size_bytes')->nullable();

            $t->timestampsTz();
            $t->index('thread_id');
            $t->index(['direction','delivery_status']);
            $t->index('from_email');
        });

        // Optional trigram index for fast substring search on message_id.
        DB::statement('CREATE INDEX IF NOT EXISTS email_messages_message_id_trgm ON email_messages USING GIN (message_id gin_trgm_ops)');
    }
    public function down(): void { Schema::dropIfExists('email_messages'); }
};
```

```php
<?php
// 2025_01_01_010800_create_actions_table.php
// User/system actions related to a thread; executed via signed links or MCP tools.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('actions', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $t->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();

            $t->string('type', 64);
            // Allowed: "approve","reject","revise","select_option","provide_value",
            // "schedule_propose_times","schedule_confirm","unsubscribe","info_request","stop"

            $t->json('payload_json')->nullable(); // jsonb: action parameters as validated by schema.
            $t->string('status', 32)->default('pending'); // "pending" | "completed" | "cancelled" | "failed"
            $t->timestampTz('expires_at')->nullable();   // Signed link expiry if applicable.
            $t->timestampTz('completed_at')->nullable();
            $t->json('error_json')->nullable(); // jsonb: failure diagnostics.

            $t->timestampsTz();
            $t->index(['thread_id','status']);
            $t->index('type');
        });

        DB::statement('CREATE INDEX actions_payload_gin ON actions USING GIN ((payload_json))');
    }
    public function down(): void { Schema::dropIfExists('actions'); }
};
```

```php
<?php
// 2025_01_01_010900_create_memories_table.php
// Versioned "memory" items used for personalization and state recall.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('memories', function (Blueprint $t){
            $t->ulid('id')->primary();

            $t->string('scope', 32); // "conversation" | "user" | "account"
            $t->char('scope_id', 26); // ULID of the scope owner.
            $t->string('key'); // Memory key (namespaced string, e.g. "locale.preference").
            $t->json('value_json'); // jsonb: arbitrary structured value.

            $t->float('confidence'); // Range [0,1]
            $t->string('ttl_category', 32); // "volatile" | "seasonal" | "durable" | "legal"
            $t->timestampTz('expires_at')->nullable();

            $t->integer('version')->default(1);
            $t->char('superseded_by', 26)->nullable(); // Points to newer memory ULID when superseded.
            $t->string('provenance')->nullable(); // e.g., email_message_id or tool reference.

            $t->timestampsTz();
            $t->index(['scope','scope_id','key']);
        });

        DB::statement('CREATE INDEX memories_value_gin ON memories USING GIN ((value_json))');
    }
    public function down(): void { Schema::dropIfExists('memories'); }
};
```

```php
<?php
// 2025_01_01_011000_create_agents_table.php
// Virtual "agents" that execute tasks (MCP-backed or internal).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('agents', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();

            $t->string('name');
            $t->string('role')->nullable(); // Free-form descriptor (e.g., "scheduler", "notifier")
            $t->json('capabilities_json')->nullable(); // jsonb: advertised tool abilities etc.

            $t->timestampsTz();
            $t->index('account_id');
        });
    }
    public function down(): void { Schema::dropIfExists('agents'); }
};
```

```php
<?php
// 2025_01_01_011100_create_tasks_table.php
// Work units executed by agents; may carry structured inputs and outputs.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tasks', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $t->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();
            $t->foreignUlid('agent_id')->constrained('agents')->cascadeOnDelete();

            $t->string('status', 32)->default('pending'); // "pending" | "running" | "succeeded" | "failed" | "cancelled"
            $t->json('input_json')->nullable();  // jsonb
            $t->json('result_json')->nullable(); // jsonb
            $t->timestampTz('started_at')->nullable();
            $t->timestampTz('finished_at')->nullable();

            $t->timestampsTz();
            $t->index(['account_id','thread_id']);
        });

        DB::statement('CREATE INDEX tasks_input_gin ON tasks USING GIN ((input_json))');
    }
    public function down(): void { Schema::dropIfExists('tasks'); }
};
```

```php
<?php
// 2025_01_01_011200_create_events_table.php
// Calendar event objects created from schedule_confirm actions.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('events', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();

            $t->string('title');
            $t->text('description')->nullable();
            $t->string('location')->nullable();
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at')->nullable();

            $t->timestampsTz();
            $t->index('starts_at');
        });
    }
    public function down(): void { Schema::dropIfExists('events'); }
};
```

```php
<?php
// 2025_01_01_011300_create_event_participants_table.php
// Participants per event; either a user OR a contact (mutually exclusive).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_participants', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();

            $t->string('type', 16); // "user" | "contact"
            $t->char('user_id', 26)->nullable();
            $t->char('contact_id', 26)->nullable();
            $t->string('response', 16)->nullable(); // "accepted" | "declined" | "tentative" | null

            $t->timestampsTz();
            $t->index('event_id');
        });

        // Partial unique constraints to prevent duplicate participation.
        DB::statement("CREATE UNIQUE INDEX event_participants_user_unique ON event_participants (event_id, user_id) WHERE type = 'user' AND user_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX event_participants_contact_unique ON event_participants (event_id, contact_id) WHERE type = 'contact' AND contact_id IS NOT NULL");
    }
    public function down(): void { Schema::dropIfExists('event_participants'); }
};
```

```php
<?php
// 2025_01_01_011400_create_availability_polls_table.php
// Polls produced by schedule_propose_times to collect availability.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('availability_polls', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('thread_id')->constrained('threads')->cascadeOnDelete();

            $t->json('options_json'); // jsonb: list of ISO8601 datetimes (UTC).
            $t->string('status', 32)->default('open'); // "open" | "closed"
            $t->timestampTz('closed_at')->nullable();

            $t->timestampsTz();
            $t->index('thread_id');
        });
    }
    public function down(): void { Schema::dropIfExists('availability_polls'); }
};
```

```php
<?php
// 2025_01_01_011500_create_availability_votes_table.php
// Votes per poll; voter is either a user OR a contact (not both).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('availability_votes', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('poll_id')->constrained('availability_polls')->cascadeOnDelete();

            $t->string('type', 16); // "user" | "contact"
            $t->char('user_id', 26)->nullable();
            $t->char('contact_id', 26)->nullable();
            $t->json('choices_json'); // jsonb: either indices or ISO8601 set.

            $t->timestampsTz();
            $t->index('poll_id');
        });

        // Ensure one vote per (poll, user) or (poll, contact).
        DB::statement("CREATE UNIQUE INDEX availability_votes_user_unique ON availability_votes (poll_id, user_id) WHERE type = 'user' AND user_id IS NOT NULL");
        DB::statement("CREATE UNIQUE INDEX availability_votes_contact_unique ON availability_votes (poll_id, contact_id) WHERE type = 'contact' AND contact_id IS NOT NULL");
    }
    public function down(): void { Schema::dropIfExists('availability_votes'); }
};
```

```php
<?php
// 2025_01_01_011600_create_auth_challenges_table.php
// Passwordless login challenges (code + optional magic-link token).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('auth_challenges', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('user_identity_id')->nullable()->constrained('user_identities')->cascadeOnDelete();

            $t->string('identifier'); // Email or phone used to initiate the challenge.
            $t->string('channel', 16); // "email" | "sms"
            $t->string('code_hash');   // Hash of the verification code.
            $t->string('token')->nullable(); // Opaque magic-link token (signed in URL).
            $t->timestampTz('expires_at');
            $t->timestampTz('consumed_at')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0); // Incremented on verify attempts.
            $t->ipAddress('ip')->nullable();

            $t->timestampsTz();
            $t->index(['identifier','expires_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('auth_challenges'); }
};
```

```php
<?php
// 2025_01_01_011700_create_api_tokens_table.php
// Simple token store for MCP "auth:token" guard (not Sanctum).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_tokens', function (Blueprint $t){
            $t->ulid('id')->primary();

            $t->string('name'); // Friendly name for the token (e.g., "MCP Agent A").
            $t->string('token_hash', 64)->unique(); // SHA-256 hex of the token.
            $t->json('abilities')->nullable(); // jsonb: list of allowed tool names/actions.

            // Optional scoping of a token to a user and/or an account.
            $t->char('user_id', 26)->nullable();
            $t->char('account_id', 26)->nullable();

            $t->timestampTz('last_used_at')->nullable();
            $t->timestampTz('expires_at')->nullable();
            $t->timestampsTz();

            $t->index(['user_id','account_id']);
        });

        // Enforce at least one scope (user or account) if your policy requires it:
        // DB::statement("ALTER TABLE api_tokens ADD CONSTRAINT api_tokens_scope_chk CHECK (user_id IS NOT NULL OR account_id IS NOT NULL)");
    }
    public function down(): void { Schema::dropIfExists('api_tokens'); }
};
```

```php
<?php
// 2025_01_01_011800_create_email_inbound_payloads_table.php
// Encrypted raw inbound payloads for audit and reprocessing; short retention.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('email_inbound_payloads', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->string('provider', 32)->default('postmark'); // Source provider identifier.
            $t->binary('ciphertext'); // Encrypted JSON payload (application-level encryption).
            $t->json('meta_json')->nullable(); // jsonb: signature status, IP, headers subset.
            $t->boolean('signature_verified')->default(false);
            $t->string('remote_ip', 45)->nullable();
            $t->unsignedBigInteger('content_length')->nullable();

            $t->timestampTz('received_at')->index();
            $t->timestampTz('purge_after')->index(); // Usually now()+30 days.

            $t->timestampsTz();
        });
    }
    public function down(): void { Schema::dropIfExists('email_inbound_payloads'); }
};
```

```php
<?php
// 2025_01_01_011900_create_email_attachments_table.php
// File attachments for messages; scanned and optionally text-extracted.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('email_attachments', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('email_message_id')->constrained('email_messages')->cascadeOnDelete();

            $t->string('filename'); // Original filename.
            $t->string('mime', 128)->nullable(); // e.g., "text/plain","application/pdf","text/csv".
            $t->unsignedBigInteger('size_bytes')->nullable(); // Raw byte size as received.

            $t->string('storage_disk')->default('attachments'); // Laravel disk name.
            $t->string('storage_path'); // Relative path on the disk.

            // Scanning & extraction lifecycle
            $t->string('scan_status', 16)->default('pending'); // "pending" | "clean" | "infected" | "error"
            $t->string('scan_virus')->nullable(); // Virus signature name if infected.
            $t->timestampTz('scanned_at')->nullable();

            $t->string('extract_status', 16)->nullable(); // "queued" | "done" | "error" | null (not applicable)
            $t->timestampTz('extracted_at')->nullable();

            $t->timestampsTz();
            $t->index('email_message_id');
            $t->index(['scan_status','extract_status']);
        });
    }
    public function down(): void { Schema::dropIfExists('email_attachments'); }
};
```

```php
<?php
// 2025_01_01_012000_create_attachment_extractions_table.php
// Optional separate store for extracted text/summaries to avoid bloating main table.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('attachment_extractions', function (Blueprint $t){
            $t->ulid('id')->primary();
            $t->foreignUlid('attachment_id')->constrained('email_attachments')->cascadeOnDelete();

            $t->longText('text_excerpt')->nullable(); // First N KB of text for LLM context (truncated).
            $t->string('text_disk')->nullable(); // Disk where the full text is stored (optional).
            $t->string('text_path')->nullable(); // Relative path to full text file.
            $t->unsignedBigInteger('text_bytes')->nullable();
            $t->unsignedInteger('pages')->nullable(); // For PDFs.

            $t->json('summary_json')->nullable(); // jsonb: cached summaries (per locale or purpose).

            $t->timestampsTz();
            $t->index('attachment_id');
        });
    }
    public function down(): void { Schema::dropIfExists('attachment_extractions'); }
};
```

### 2. Alter Migrations and Additions

```php
<?php
// 2025_01_01_020000_alter_threads_add_starter_fk.php
// Adds FK for threads.starter_message_id after email_messages exists.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('threads', function (Blueprint $t){
            // Set FK to email_messages(id); on delete set null to keep the thread if the message is removed.
            $t->foreign('starter_message_id')->references('id')->on('email_messages')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('threads', function (Blueprint $t){
            $t->dropForeign(['starter_message_id']);
        });
    }
};
```

```php
<?php
// 2025_01_01_020010_alter_actions_add_clarification_state.php
// Tracks the clarification loop state for low-confidence interpretations.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('actions', function(Blueprint $t){
            $t->unsignedTinyInteger('clarification_rounds')->default(0)->after('error_json'); // Number of clarification messages sent.
            $t->unsignedTinyInteger('clarification_max')->default(2)->after('clarification_rounds'); // Upper bound (usually 2).
            $t->timestampTz('last_clarification_sent_at')->nullable()->after('clarification_max');
        });
    }
    public function down(): void {
        Schema::table('actions', function(Blueprint $t){
            $t->dropColumn(['clarification_rounds','clarification_max','last_clarification_sent_at']);
        });
    }
};
```

### 3. Post-Migrate Indexes and Checks

```php
<?php
// 2025_01_01_030000_post_migrate_indexes.php
// Optional performance indexes applied after base schema exists.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Fast filter by action type.
        DB::statement('CREATE INDEX IF NOT EXISTS actions_type_idx ON actions (type)');
        // Already created: email_messages_message_id_trgm.
    }
    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS actions_type_idx');
    }
};
```

### 4. Enum Values and Meanings (Authoritative Mapping)

To avoid ambiguity, below are all string fields with fixed values and their exact meaning:

* `users.status`: `"active"` user can log in; `"disabled"` access blocked.
* `user_identities.type`: `"email"` email address; `"phone"` telephone number; `"oidc"` OpenID Connect subject.
* `memberships.role`: `"admin"` full rights within account; `"member"` standard; `"guest"` minimal access.
* `email_messages.direction`: `"inbound"` received via webhook; `"outbound"` sent by system.
* `email_messages.delivery_status`: `"queued"` scheduled at provider; `"sent"` delivered; `"bounced"` provider bounce; `"failed"` permanently failed; `null` for inbound.
* `actions.type`: `"approve"|"reject"|"revise"|"select_option"|"provide_value"|"schedule_propose_times"|"schedule_confirm"|"unsubscribe"|"info_request"|"stop"`.
* `actions.status`: `"pending"` awaiting execution/confirmation; `"completed"` successful; `"cancelled"` canceled/expired; `"failed"` executed with error.
* `memories.scope`: `"conversation"` bound to thread; `"user"` bound to user; `"account"` bound to account.
* `memories.ttl_category`: `"volatile"` short-lived; `"seasonal"` medium-lived; `"durable"` long-lived; `"legal"` policy-bound.
* `agents.role`: free text, descriptive only (no logic bound).
* `tasks.status`: `"pending"|"running"|"succeeded"|"failed"|"cancelled"`.
* `event_participants.type`: `"user"` refers to users.id; `"contact"` refers to contacts.id.
* `event_participants.response`: `"accepted"|"declined"|"tentative"` or `null` unknown.
* `availability_polls.status`: `"open"` voting allowed; `"closed"` closed; `closed_at` set on transition.
* `availability_votes.type`: `"user"` or `"contact"` with the corresponding id.
* `auth_challenges.channel`: `"email"` via email; `"sms"` via SMS.
* `email_attachments.scan_status`: `"pending"` not scanned yet; `"clean"` no malware; `"infected"` malware detected; `"error"` scan could not be performed.
* `email_attachments.extract_status`: `"queued"` extraction scheduled; `"done"` text extracted; `"error"` extraction failed; `null` not applicable (e.g., unreadable binary).

### Deployment Notes

* Run migrations **exactly in filename order** (2025_01_01_000000_*, 2025_01_01_010000_*, etc.) to avoid FK errors.
* Ensure the `attachments` disk exists in `config/filesystems.php`.
* Configure a **retention scheduler** for `email_inbound_payloads.purge_after` and orphaned attachment files.
* The ClamAV daemon must be reachable for scan jobs **before** extraction starts.

## Getting Started (Developer Setup)

This section provides a complete setup guide to get Agent AI running locally for development.

### Prerequisites

- **PHP 8.4+** with extensions: `pdo_pgsql`, `redis`, `bcmath`, `mbstring`, `intl`, `zip`
- **PostgreSQL 17+**
- **Redis 7+**
- **Node.js 18+** and npm
- **Composer** (PHP dependency manager)
- **Docker & Docker Compose** (for services)
- **Git**

### 1. Project Initialization

```bash
# Clone or create the project
git clone <repository-url> agent-ai
cd agent-ai

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 2. Environment Configuration

Create `.env` with the following configuration:

```env
# Application
APP_NAME=AgentAI
APP_ENV=local
APP_KEY=base64:your-generated-key-here
APP_DEBUG=true
APP_TIMEZONE=Europe/Amsterdam
APP_URL=http://localhost:8000

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agent_ai
DB_USERNAME=agent_user
DB_PASSWORD=your_secure_password
DB_SCHEMA=public

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=redis

# Mail (Postmark for development/testing)
MAIL_MAILER=postmark
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Agent AI"
POSTMARK_TOKEN=your-postmark-token
POSTMARK_MESSAGE_STREAM_ID=outbound

# LLM Configuration
LLM_PROVIDER=ollama
LLM_MODEL=llama3
OLLAMA_BASE_URL=http://localhost:11434

# Security
BCRYPT_ROUNDS=12
SANCTUM_STATEFUL_DOMAINS=localhost:8000

# File Storage
FILESYSTEM_DISK=local
ATTACHMENTS_DISK=attachments

# Localization
APP_LOCALE=en_US
APP_FALLBACK_LOCALE=en_US
```

### 3. Database Setup

```bash
# Create database
createdb agent_ai

# Run migrations
php artisan migrate

# (Optional) Seed with test data
php artisan db:seed
```

### 4. External Services Setup

Start required Docker services:

```yaml
# docker-compose.yml (create in project root)
version: '3.8'

services:
  postgres:
    image: postgres:17
    environment:
      POSTGRES_DB: agent_ai
      POSTGRES_USER: agent_user
      POSTGRES_PASSWORD: your_secure_password
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

  mailpit:
    image: axllent/mailpit
    ports:
      - "8025:8025"
      - "1025:1025"
    environment:
      MP_SMTP_AUTH_ACCEPT_ANY: 1

  clamav:
    image: clamav/clamav:latest
    ports:
      - "3310:3310"
    volumes:
      - clamav_data:/var/lib/clamav

  ollama:
    image: ollama/ollama:latest
    ports:
      - "11434:11434"
    volumes:
      - ollama_data:/root/.ollama

volumes:
  postgres_data:
  redis_data:
  clamav_data:
  ollama_data:
```

```bash
# Start services
docker-compose up -d

# Install Ollama models
docker-compose exec ollama ollama pull llama3
```

### 5. Postmark Webhook Setup (Development)

For local development, you can use tools like ngrok or Cloudflare Tunnel to expose your local server:

```bash
# Install ngrok
npm install -g ngrok

# Expose local server
ngrok http 8000

# Configure webhook URL in Postmark dashboard:
# https://your-ngrok-url.ngrok.io/webhooks/postmark-inbound
```

### 6. Build Assets & Start Development Server

```bash
# Build frontend assets
npm run dev

# In another terminal, start the Laravel server
php artisan serve

# Optional: Start queue worker
php artisan queue:work

# Optional: Start Horizon dashboard
php artisan horizon
```

### 7. Verify Setup

Visit these URLs to verify everything works:

- **Application**: http://localhost:8000
- **Mailpit (email testing)**: http://localhost:8025
- **Horizon (queue monitoring)**: http://localhost:8000/horizon
- **Test Webhook**: Send a test email to your Postmark inbound address

## Environment Configuration

### .env.example (Complete Template)

Create a `.env.example` file in your project root with this complete template:

```env
# Application
APP_NAME=AgentAI
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Europe/Amsterdam
APP_URL=http://localhost:8000

# Database (PostgreSQL - comment out for SQLite dev)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agent_ai
DB_USERNAME=agent_user
DB_PASSWORD=your_secure_password
DB_SCHEMA=public

# Database (SQLite - uncomment for local dev)
# DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/database/database.sqlite

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=redis

# Mail (Postmark)
MAIL_MAILER=postmark
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Agent AI"
POSTMARK_TOKEN=your-postmark-token
POSTMARK_MESSAGE_STREAM_ID=outbound

# LLM Configuration
LLM_PROVIDER=ollama
LLM_MODEL=llama3
OLLAMA_BASE_URL=http://localhost:11434

# OpenAI (optional fallback)
# OPENAI_API_KEY=your-openai-key

# Anthropic (optional fallback)
# ANTHROPIC_API_KEY=your-anthropic-key

# Security
BCRYPT_ROUNDS=12
SANCTUM_STATEFUL_DOMAINS=localhost:8000

# File Storage
FILESYSTEM_DISK=local
ATTACHMENTS_DISK=attachments

# Localization
APP_LOCALE=en_US
APP_FALLBACK_LOCALE=en_US

# Webhook Security
POSTMARK_WEBHOOK_SECRET=your-webhook-secret

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Development Tools
TELESCOPE_ENABLED=false
HORIZON_MEMORY=128
```

### Complete .env.example File

Create a `.env.example` file in your project root with this complete template:

```env
# Application
APP_NAME=AgentAI
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Europe/Amsterdam
APP_URL=http://localhost:8000

# Database (PostgreSQL - comment out for SQLite dev)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=agent_ai
DB_USERNAME=agent_user
DB_PASSWORD=your_secure_password
DB_SCHEMA=public

# Database (SQLite - uncomment for local dev)
# DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/database/database.sqlite

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=redis

# Mail (Postmark)
MAIL_MAILER=postmark
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Agent AI"
POSTMARK_TOKEN=your-postmark-token
POSTMARK_MESSAGE_STREAM_ID=outbound

# LLM Configuration
LLM_PROVIDER=ollama
LLM_MODEL=llama3
OLLAMA_BASE_URL=http://localhost:11434

# OpenAI (optional fallback)
# OPENAI_API_KEY=your-openai-key

# Anthropic (optional fallback)
# ANTHROPIC_API_KEY=your-anthropic-key

# Security
BCRYPT_ROUNDS=12
SANCTUM_STATEFUL_DOMAINS=localhost:8000

# File Storage
FILESYSTEM_DISK=local
ATTACHMENTS_DISK=attachments

# Localization
APP_LOCALE=en_US
APP_FALLBACK_LOCALE=en_US

# Webhook Security
POSTMARK_WEBHOOK_SECRET=your-webhook-secret

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Development Tools
TELESCOPE_ENABLED=false
HORIZON_MEMORY=128
```

## Code Quality & Testing

### Testing Framework

**PHPUnit Setup** (Laravel default, version ^11.0 - no Pest conflicts)

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="CACHE_DRIVER" value="array"/>
    </php>
</phpunit>
```

**Test Structure & Naming**
- `tests/Feature/`: Integration tests (webhooks, auth flows, API endpoints)
- `tests/Unit/`: Unit tests (services, jobs, utilities)
- Test files: `{ClassName}Test.php`
- Test methods: `test_{method_name}()` or `it_{describes_behavior}()`

### Code Quality Tools

**PHPStan** (Static Analysis, Level 8)

```neon
# phpstan.neon
parameters:
    level: 8
    paths:
        - app
        - config
        - database
        - routes
    excludePaths:
        - app/Mcp/Tools/*
        - database/migrations/*
    ignoreErrors:
        - '#Access to an undefined property#'
        - '#Cannot call method#'
    reportUnmatchedIgnoredErrors: false
```

**Laravel Pint** (Code Style, PSR-12)

```json
# pint.json
{
    "preset": "psr12",
    "rules": {
        "align_multiline_comment": true,
        "array_indentation": true,
        "array_syntax": { "syntax": "short" },
        "binary_operator_spaces": true,
        "blank_line_after_namespace": true,
        "blank_line_before_statement": {
            "statements": ["return"]
        },
        "cast_spaces": true,
        "class_attributes_separation": {
            "elements": { "const": "one", "method": "one", "property": "one" }
        },
        "concat_space": { "spacing": "one" },
        "declare_equal_normalize": true,
        "function_typehint_space": true,
        "include": true,
        "lowercase_cast": true,
        "magic_constant_casing": true,
        "method_argument_space": true,
        "native_function_casing": true,
        "no_blank_lines_after_class_opening": true,
        "no_blank_lines_after_phpdoc": true,
        "no_empty_comment": true,
        "no_empty_phpdoc": true,
        "no_empty_statement": true,
        "no_extra_blank_lines": true,
        "no_leading_import_slash": true,
        "no_leading_namespace_whitespace": true,
        "no_mixed_echo_print": { "use": "echo" },
        "no_multiline_whitespace_around_double_arrow": true,
        "no_short_bool_cast": true,
        "no_singleline_whitespace_before_semicolons": true,
        "no_spaces_around_offset": true,
        "no_trailing_comma_in_singleline": true,
        "no_unneeded_control_parentheses": true,
        "no_unused_imports": true,
        "no_whitespace_before_comma_in_array": true,
        "no_whitespace_in_blank_line": true,
        "normalize_index_brace": true,
        "object_operator_without_whitespace": true,
        "php_unit_fqcn_annotation": true,
        "php_unit_method_casing": true,
        "phpdoc_align": true,
        "phpdoc_annotation_without_dot": true,
        "phpdoc_indent": true,
        "phpdoc_inline_tag_normalizer": true,
        "phpdoc_no_access": true,
        "phpdoc_no_package": true,
        "phpdoc_no_useless_inheritdoc": true,
        "phpdoc_return_self_reference": true,
        "phpdoc_scalar": true,
        "phpdoc_separation": true,
        "phpdoc_single_line_var_spacing": true,
        "phpdoc_summary": true,
        "phpdoc_tag_casing": true,
        "phpdoc_tag_type": true,
        "phpdoc_to_comment": true,
        "phpdoc_trim": true,
        "phpdoc_types": true,
        "phpdoc_var_without_name": true,
        "psr_autoloading": true,
        "return_type_declaration": true,
        "short_scalar_cast": true,
        "single_blank_line_before_namespace": true,
        "single_class_element_per_statement": true,
        "single_import_per_statement": true,
        "single_line_after_imports": true,
        "single_quote": true,
        "space_after_semicolon": true,
        "standardize_not_equals": true,
        "switch_case_semicolon_to_colon": true,
        "switch_case_space": true,
        "ternary_operator_spaces": true,
        "trailing_comma_in_multiline": true,
        "trim_array_spaces": true,
        "unary_operator_spaces": true,
        "visibility_required": true,
        "whitespace_after_comma_in_array": true
    }
}
```

**ESLint & Prettier** (JavaScript/CSS)

```js
// .eslintrc.js
module.exports = {
    env: {
        browser: true,
        es2021: true,
    },
    extends: [
        'eslint:recommended',
        '@vue/eslint-config-prettier',
    ],
    parserOptions: {
        ecmaVersion: 12,
        sourceType: 'module',
    },
    rules: {
        'no-unused-vars': 'warn',
        'no-console': 'off',
    },
};
```

```json
// .prettierrc
{
    "semi": true,
    "trailingComma": "es5",
    "singleQuote": true,
    "printWidth": 100,
    "tabWidth": 4,
    "useTabs": false
}
```

### Quality Tools Configuration Files

**phpstan.neon** (Static Analysis, Level 8)

```neon
parameters:
    level: 8
    paths:
        - app
        - config
        - database
        - routes
    excludePaths:
        - app/Mcp/Tools/*
        - database/migrations/*
    ignoreErrors:
        - '#Access to an undefined property#'
        - '#Cannot call method#'
    reportUnmatchedIgnoredErrors: false
```

**pint.json** (Code Style, PSR-12)

```json
{
    "preset": "psr12",
    "rules": {
        "align_multiline_comment": true,
        "array_indentation": true,
        "array_syntax": { "syntax": "short" },
        "binary_operator_spaces": true,
        "blank_line_after_namespace": true,
        "blank_line_before_statement": {
            "statements": ["return"]
        },
        "cast_spaces": true,
        "class_attributes_separation": {
            "elements": { "const": "one", "method": "one", "property": "one" }
        },
        "concat_space": { "spacing": "one" },
        "declare_equal_normalize": true,
        "function_typehint_space": true,
        "include": true,
        "lowercase_cast": true,
        "magic_constant_casing": true,
        "method_argument_space": true,
        "native_function_casing": true,
        "no_blank_lines_after_class_opening": true,
        "no_blank_lines_after_phpdoc": true,
        "no_empty_comment": true,
        "no_empty_phpdoc": true,
        "no_empty_statement": true,
        "no_extra_blank_lines": true,
        "no_leading_import_slash": true,
        "no_leading_namespace_whitespace": true,
        "no_mixed_echo_print": { "use": "echo" },
        "no_multiline_whitespace_around_double_arrow": true,
        "no_short_bool_cast": true,
        "no_singleline_whitespace_before_semicolons": true,
        "no_spaces_around_offset": true,
        "no_trailing_comma_in_singleline": true,
        "no_unneeded_control_parentheses": true,
        "no_unused_imports": true,
        "no_whitespace_before_comma_in_array": true,
        "no_whitespace_in_blank_line": true,
        "normalize_index_brace": true,
        "object_operator_without_whitespace": true,
        "php_unit_fqcn_annotation": true,
        "php_unit_method_casing": true,
        "phpdoc_align": true,
        "phpdoc_annotation_without_dot": true,
        "phpdoc_indent": true,
        "phpdoc_inline_tag_normalizer": true,
        "phpdoc_no_access": true,
        "phpdoc_no_package": true,
        "phpdoc_no_useless_inheritdoc": true,
        "phpdoc_return_self_reference": true,
        "phpdoc_scalar": true,
        "phpdoc_separation": true,
        "phpdoc_single_line_var_spacing": true,
        "phpdoc_summary": true,
        "phpdoc_tag_casing": true,
        "phpdoc_tag_type": true,
        "phpdoc_to_comment": true,
        "phpdoc_trim": true,
        "phpdoc_types": true,
        "phpdoc_var_without_name": true,
        "psr_autoloading": true,
        "return_type_declaration": true,
        "short_scalar_cast": true,
        "single_blank_line_before_namespace": true,
        "single_class_element_per_statement": true,
        "single_import_per_statement": true,
        "single_line_after_imports": true,
        "single_quote": true,
        "space_after_semicolon": true,
        "standardize_not_equals": true,
        "switch_case_semicolon_to_colon": true,
        "switch_case_space": true,
        "ternary_operator_spaces": true,
        "trailing_comma_in_multiline": true,
        "trim_array_spaces": true,
        "unary_operator_spaces": true,
        "visibility_required": true,
        "whitespace_after_comma_in_array": true
    }
}
```

### Quality Gates

**Pre-commit Hooks** (using husky)

```bash
npm install --save-dev husky lint-staged
npx husky install
```

```json
// package.json
{
    "scripts": {
        "prepare": "husky install",
        "lint": "eslint resources/js --ext .js,.vue",
        "lint:fix": "eslint resources/js --ext .js,.vue --fix",
        "format": "prettier --write resources/js/**/*.{js,vue}",
        "test": "php artisan test",
        "test:coverage": "php artisan test --coverage",
        "stan": "phpstan analyse",
        "pint": "pint"
    },
    "lint-staged": {
        "*.php": [
            "pint --test",
            "phpstan analyse"
        ],
        "*.{js,vue}": [
            "eslint",
            "prettier --check"
        ]
    }
}
```

## Development Tools

### VS Code Configuration

**.vscode/extensions.json**

```json
{
    "recommendations": [
        "ms-vscode.vscode-json",
        "bmewburn.vscode-intelephense-client",
        "bradlc.vscode-tailwindcss",
        "formulahendry.auto-rename-tag",
        "christian-kohler.path-intellisense",
        "ms-vscode.vscode-eslint",
        "esbenp.prettier-vscode",
        "ms-vscode.test-adapter-converter",
        "hbenl.vscode-test-explorer",
        "recca0120.vscode-phpunit",
        "cweijan.vscode-mysql-client2",
        "ms-vscode-remote.remote-containers"
    ]
}
```

**.vscode/tasks.json**

```json
{
    "version": "2.0.0",
    "tasks": [
        {
            "label": "Start Herd Server",
            "type": "shell",
            "command": "echo",
            "args": ["'Laravel Herd serves https://agent-ai.test - no artisan serve needed'"],
            "group": "build",
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        },
        {
            "label": "Start Docker Services",
            "type": "shell",
            "command": "docker-compose",
            "args": ["up", "-d"],
            "group": "build",
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        },
        {
            "label": "Run Tests",
            "type": "shell",
            "command": "php",
            "args": ["artisan", "test"],
            "group": {
                "kind": "test",
                "isDefault": true
            },
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        },
        {
            "label": "Run PHPStan",
            "type": "shell",
            "command": "./vendor/bin/phpstan",
            "args": ["analyse"],
            "group": "test",
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        },
        {
            "label": "Run Pint",
            "type": "shell",
            "command": "./vendor/bin/pint",
            "group": "test",
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        },
        {
            "label": "Start Queue Worker",
            "type": "shell",
            "command": "php",
            "args": ["artisan", "queue:work"],
            "group": "build",
            "isBackground": true,
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        },
        {
            "label": "Start Horizon",
            "type": "shell",
            "command": "php",
            "args": ["artisan", "horizon"],
            "group": "build",
            "isBackground": true,
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        },
        {
            "label": "Build Assets",
            "type": "shell",
            "command": "npm",
            "args": ["run", "dev"],
            "group": "build",
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        }
    ]
}
```

### CI/CD Pipeline

**GitHub Actions** (.github/workflows/ci.yml)

```yaml
name: CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:17
        env:
          POSTGRES_PASSWORD: postgres
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

      redis:
        image: redis:7
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
    - uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.4'
        extensions: pdo, pdo_pgsql, redis, bcmath, mbstring, intl
        tools: composer:v2

    - name: Setup Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'
        cache: 'npm'

    - name: Install PHP dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Install NPM dependencies
      run: npm ci

    - name: Copy environment file
      run: cp .env.ci .env

    - name: Generate application key
      run: php artisan key:generate

    - name: Run migrations
      run: php artisan migrate --force

    - name: Build assets
      run: npm run build

    - name: Run tests
      run: php artisan test --coverage

    - name: Run PHPStan
      run: ./vendor/bin/phpstan analyse

    - name: Run Pint
      run: ./vendor/bin/pint --test

    - name: Run ESLint
      run: npm run lint
```

### Development Scripts

**composer.json** scripts:

```json
{
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "check": [
            "@test",
            "@stan",
            "@pint",
            "@lint"
        ],
        "test": "php artisan test",
        "test:coverage": "php artisan test --coverage",
        "stan": "./vendor/bin/phpstan analyse",
        "pint": "./vendor/bin/pint",
        "lint": "npm run lint",
        "format": "npm run format"
    }
}
```

**package.json** scripts:

```json
{
    "scripts": {
        "dev": "vite",
        "build": "vite build",
        "preview": "vite preview",
        "lint": "eslint resources/js --ext .js,.ts,.vue",
        "lint:fix": "eslint resources/js --ext .js,.ts,.vue --fix",
        "format": "prettier --write resources/js/**/*.{js,ts,vue}",
        "format:check": "prettier --check resources/js/**/*.{js,ts,vue}"
    }
}
```

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

## Configuration Files

### Complete Config Examples

#### `config/llm.php`
```php
<?php

return [
    'provider' => env('LLM_PROVIDER', 'ollama'),
    'model' => env('LLM_MODEL', 'gpt-4o-mini'),
    'timeout_ms' => 4000,
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

#### `config/prompts.php`
```php
<?php

return [
    'action_interpret' => [
        'temperature' => 0.2,
        'backstory' => 'You convert a user email reply into exactly one allowed action with parameters. Output JSON only.',
        'template' => 'You are a strict JSON generator... [full template from Appendix G]',
    ],
    'clarify_question' => [
        'temperature' => 0.3,
        'backstory' => 'You write one concise clarification question matching the user's language.',
        'template' => 'Write ONE short question to disambiguate... [full template]',
    ],
    'options_email_draft' => [
        'temperature' => 0.4,
        'backstory' => 'You draft a brief options email in the user's language.',
        'template' => 'Write a brief email offering 2-4 likely actions... [full template]',
    ],
    'memory_extract' => [
        'temperature' => 0.2,
        'backstory' => 'Extract non-sensitive, useful facts as key-value memories.',
        'template' => 'Extract relevant, non-sensitive facts... [full template]',
    ],
    'thread_summarize' => [
        'temperature' => 0.3,
        'backstory' => 'Summarize a thread for fast recall.',
        'template' => 'Summarize the thread concisely... [full template]',
    ],
    'language_detect' => [
        'temperature' => 0.0,
        'backstory' => 'Return language code only.',
        'template' => 'Detect the primary language... [full template]',
    ],
    'attachment_summarize' => [
        'temperature' => 0.3,
        'backstory' => 'Summarize attachment text for decision-making.',
        'template' => 'Summarize the attachment in locale... [full template]',
    ],
    'csv_schema_detect' => [
        'temperature' => 0.2,
        'backstory' => 'Infer simple CSV schema from a small sample.',
        'template' => 'Infer CSV schema from sample lines... [full template]',
    ],
    'clarify_email_draft' => [
        'temperature' => 0.4,
        'backstory' => 'Draft a short clarification email.',
        'template' => 'Draft a brief email asking exactly ONE... [full template]',
    ],
    'poll_email_draft' => [
        'temperature' => 0.4,
        'backstory' => 'Draft an availability poll email.',
        'template' => 'Draft a short availability poll email... [full template]',
    ],
];
```

#### `config/mcps.php`
```php
<?php

return [
    'tools' => [
        'send_email' => \App\Mcp\Tools\SendEmailTool::class,
        'store_memory' => \App\Mcp\Tools\StoreMemoryTool::class,
        'process_attachment' => \App\Mcp\Tools\ProcessAttachmentTool::class,
    ],
    'auth' => [
        'guard' => 'mcp_token',
        'token_table' => 'api_tokens',
    ],
];
```

### Filesystem Configuration (`config/filesystems.php`)

Add the attachments disk:

```php
'disks' => [
    // ... existing disks

    'attachments' => [
        'driver' => 'local',
        'root' => storage_path('app/attachments'),
        'throw' => false,
    ],
],
```

### Language Files Structure

```
resources/lang/
├── en_US/
│   ├── auth.php
│   ├── actions.php
│   ├── validation.php
│   ├── pagination.php
│   └── messages.php
└── nl_NL/
    ├── auth.php
    ├── actions.php
    ├── validation.php
    ├── pagination.php
    └── messages.php
```

Example `resources/lang/en_US/actions.php`:
```php
<?php

return [
    'approve' => 'Approve',
    'reject' => 'Reject',
    'revise' => 'Revise',
    'select_option' => 'Select Option',
    'provide_value' => 'Provide Value',
    'schedule_propose_times' => 'Schedule Meeting',
    'schedule_confirm' => 'Confirm Meeting',
    'unsubscribe' => 'Unsubscribe',
    'info_request' => 'Request Information',
    'stop' => 'Stop',
];
```

## LLM Testing Data (Golden Set Examples)

### Action Interpretation Examples

#### Example 1: Approve Action (High Confidence)
**Input**:
```
Subject: Re: Meeting Request
From: user@example.com

Yes, that works for me. Let's go ahead with the meeting.
```

**Expected Output**:
```json
{
  "action_type": "approve",
  "parameters": {},
  "scope_hint": "conversation",
  "confidence": 0.95,
  "needs_clarification": false,
  "clarification_prompt": null
}
```

#### Example 2: Reject Action (High Confidence)
**Input**:
```
Subject: Re: Invoice Review
From: user@example.com

I cannot approve this invoice. The amounts are incorrect.
```

**Expected Output**:
```json
{
  "action_type": "reject",
  "parameters": {
    "reason": "The amounts are incorrect"
  },
  "scope_hint": "conversation",
  "confidence": 0.90,
  "needs_clarification": false,
  "clarification_prompt": null
}
```

#### Example 3: Schedule Proposal (Medium Confidence - Needs Clarification)
**Input**:
```
Subject: Re: Project Discussion
From: user@example.com

Let's meet sometime next week. I'm free on Tuesday or Wednesday.
```

**Expected Output**:
```json
{
  "action_type": "schedule_propose_times",
  "parameters": {
    "duration_min": 60,
    "timezone": "Europe/Amsterdam",
    "constraints": "Tuesday or Wednesday next week"
  },
  "scope_hint": "conversation",
  "confidence": 0.65,
  "needs_clarification": true,
  "clarification_prompt": "What time would work best for you on Tuesday or Wednesday?"
}
```

#### Example 4: Provide Value (Low Confidence - Options Email)
**Input**:
```
Subject: Re: Budget Request
From: user@example.com

I think we can do $5000 for this project.
```

**Expected Output**:
```json
{
  "action_type": "provide_value",
  "parameters": {
    "key": "budget",
    "value": "$5000"
  },
  "scope_hint": "conversation",
  "confidence": 0.45,
  "needs_clarification": false,
  "clarification_prompt": null
}
```

### Memory Extraction Examples

#### Example Memory Extraction
**Input**: Same as above examples

**Expected Output**:
```json
{
  "items": [
    {
      "key": "meeting_preference",
      "value": "Tuesday or Wednesday",
      "scope": "user",
      "ttl_category": "seasonal",
      "confidence": 0.85,
      "provenance": "email_message_id:01H..."
    }
  ]
}
```

### Thread Summarization Examples

**Input**: Thread with 3 messages about meeting scheduling

**Expected Output**:
```json
{
  "summary": "User requested to schedule a meeting, system asked for clarification, user provided availability preferences.",
  "key_entities": ["meeting", "Tuesday", "Wednesday", "next week"],
  "open_questions": ["What specific times work best?"]
}
```

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
- Verify `POSTMARK_WEBHOOK_SECRET` in `.env` matches Postmark dashboard
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

## Deployment

### Docker Production Setup

#### `docker-compose.prod.yml`
```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: docker/Dockerfile.prod
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_HOST=postgres
      - REDIS_HOST=redis
      - LLM_PROVIDER=ollama
      - OLLAMA_BASE_URL=http://ollama:11434
    volumes:
      - ./storage:/var/www/html/storage
    depends_on:
      - postgres
      - redis
      - clamav
      - ollama
    networks:
      - agentai

  postgres:
    image: postgres:17
    environment:
      POSTGRES_DB: agent_ai_prod
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_prod:/var/lib/postgresql/data
    networks:
      - agentai

  redis:
    image: redis:7-alpine
    volumes:
      - redis_prod:/data
    networks:
      - agentai

  clamav:
    image: clamav/clamav:latest
    volumes:
      - clamav_prod:/var/lib/clamav
    networks:
      - agentai

  ollama:
    image: ollama/ollama:latest
    volumes:
      - ollama_prod:/root/.ollama
    networks:
      - agentai

volumes:
  postgres_prod:
  redis_prod:
  clamav_prod:
  ollama_prod:

networks:
  agentai:
    driver: bridge
```

#### `docker/Dockerfile.prod`
```dockerfile
FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath zip intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Install Node dependencies and build assets
RUN npm ci && npm run build

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# Expose port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
```

### Production Environment Variables

```env
# Production .env
APP_NAME=AgentAI
APP_ENV=production
APP_KEY=base64:your-generated-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_LOCALE=en_US
APP_FALLBACK_LOCALE=en_US

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=agent_ai_prod
DB_USERNAME=agent_user
DB_PASSWORD=your_secure_password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true

# Queue
QUEUE_CONNECTION=redis

# Mail (Postmark)
MAIL_MAILER=postmark
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Agent AI"
POSTMARK_TOKEN=your-production-token
POSTMARK_MESSAGE_STREAM_ID=outbound

# LLM (Production - consider external provider)
LLM_PROVIDER=ollama
OLLAMA_BASE_URL=http://ollama:11434
LLM_MODEL=llama3

# Security
BCRYPT_ROUNDS=12
SANCTUM_STATEFUL_DOMAINS=your-domain.com

# File Storage (consider S3 for production)
FILESYSTEM_DISK=local
ATTACHMENTS_DISK=attachments
```

### SSL & Security Setup

```bash
# Install Certbot for Let's Encrypt
sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com

# Configure Nginx (example config)
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    root /var/www/agent-ai/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
}
```

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

This completes the turn-key development setup for Agent AI. The documentation now provides everything needed to get started with development, including setup instructions, wireframes, configuration examples, testing data, troubleshooting guides, and production deployment instructions.
