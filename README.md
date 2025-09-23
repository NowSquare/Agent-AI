# Agent-AI

Agent-AI is a Laravel-based application for building practical, privacy-respecting agent workflows: This build adds lightweight reliability features: grounded retrieval via **pgvector**, **AgentOps** logs/evaluation, simple **model selection**, and internal **multi-agent delegation**—all without extra services.

* Ingest emails & attachments
* Trigger background jobs and tasks
* Run LLM prompts/tools
* Keep an auditable trail of actions and outputs

This README explains **how to run Agent-AI** locally with **Laravel Herd** (recommended on macOS), with **Docker** (Windows/Linux/macOS), or via **Artisan**. It also shows how to integrate **Laravel Boost** so Cursor can use Laravel-aware tools during development.

**Current Status**: Full email automation pipeline active with intelligent agent coordination and memory management. Inbound emails are processed asynchronously with LLM interpretation, specialized agent routing, and coordinated multi-agent responses. Features intelligent complexity detection, agent specialization (Italian Chef, Tech Support), professional single-email responses with thread continuity, and a robust memory system with TTL/decay for contextual learning.

---

## 🚀 Quick start

**Pick your mode first:**

* macOS devs → **Herd** → `cp .env.herd .env`
* Windows/Linux/macOS devs → **Docker** → `cp .env.docker .env`
* Fallback (any OS) → **Artisan-only** with your own Postgres + Redis

> In all environments, **Postmark handles outbound and inbound mail**.
>
> * Outbound: Laravel sends via Postmark API.
> * Inbound: Postmark POSTs emails to `/webhooks/inbound-email` with HTTP Basic Auth.

---

## 1) What you get

* **Laravel 12** (PHP 8.3/8.4 ready), Vite, Tailwind v4, Flowbite UI
* **PostgreSQL** database (Herd or Docker)
* **Redis** queues with **Laravel Horizon** dashboard
* **Mail**: Postmark for all email (outbound + inbound webhooks)
* **Laravel MCP Framework**: Structured LLM interactions via Model Context Protocol
* **MCP Server**: RESTful API at `/mcp/ai` with tools and prompts for reliable LLM operations
* **LLM Integration**: Local Ollama (gpt-oss:20b) with fallback mechanisms
* **Agent System**: Intelligent coordination with specialized agents (Italian Chef, Tech Support, etc.)
* **Smart Routing**: Automatic complexity detection for single-agent vs multi-agent processing
* **Clarification Loop**: Confidence-based user confirmation for medium/low confidence interpretations
* **Thread Continuity**: Reply-to headers with thread IDs for conversation persistence
* File scanning (ClamAV) & PDF text extraction (Spatie + poppler)
* **Laravel Boost**: MCP server for AI-assisted development in Cursor
* **Grounded retrieval (pgvector)** in PostgreSQL for facts from threads, attachments, and memories (no external vector DB).
* **AgentOps logs & evaluation** (`agent_steps` table) for tracing LLM/tool calls, tokens, latency, confidence.
* **Simple dynamic model selection** (small model for classification, larger for synthesis; choice recorded in logs).
* **Internal multi-agent delegation** (agents can route to other agents via the Coordinator).

## 🔄 Clarification Loop

The system uses confidence thresholds to ensure accurate action interpretation:

- **≥0.75 High Confidence**: Auto-execute immediately
- **0.50–0.74 Medium Confidence**: Send clarification email with Confirm/Cancel buttons
- **<0.50 Low Confidence**: Send options email with 2–4 clickable choices

### Signed Links & Security
- All clarification links are signed and expire in 72 hours
- Links include action IDs for secure, idempotent processing
- CSRF protection and authentication not required (public links)

### Testing Locally
```bash
# Run clarification tests
php artisan test --filter=Clarification

# Check action states in database
php artisan tinker
>>> \App\Models\Action::whereIn('status', ['awaiting_confirmation', 'awaiting_input'])->get()
```

---



## 2) Postmark setup

1. **Verify a sender domain or email** in Postmark.
   This allows outbound mail from `MAIL_FROM_ADDRESS`.

2. **Get your Inbound Address** from Postmark.
   Example:

   ```
   <hash>@inbound.postmarkapp.com
   ```

   Put this in `.env` as `AGENT_MAIL`.

3. **Expose your app with ngrok** for inbound testing:

   ```bash
   ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test
   ```

   Herd will serve your site at `http://agent-ai.test`.
   Ngrok makes it reachable at:
   `https://abc123.ngrok-free.app`

4. **Set up Postmark webhook** in your inbound stream settings:

   ```
   https://WEBHOOK_USER:WEBHOOK_PASS@abc123.ngrok-free.app/webhooks/inbound-email
   ```

5. **Update `.env`** with your credentials:

   ```ini
   MAIL_MAILER=postmark
   POSTMARK_TOKEN=pm_xxx
   POSTMARK_MESSAGE_STREAM_ID=outbound
   AGENT_MAIL=42381384ac472a1ed1d56274e88b4e00@inbound.postmarkapp.com

   WEBHOOK_USER=postmark
   WEBHOOK_PASS=your-long-random-password
   ```

6. **Configure attachments** (optional, for production):

   ```ini
# Attachment processing
ATTACH_MAX_SIZE_MB=25
ATTACH_TOTAL_MAX_SIZE_MB=40
CLAMAV_HOST=127.0.0.1
CLAMAV_PORT=3310

# Memory system configuration
MEMORY_MIN_CONFIDENCE=0.60
MEMORY_INCLUDE_THRESHOLD=0.45
MEMORY_TTL_VOLATILE=30
MEMORY_TTL_SEASONAL=120
MEMORY_TTL_DURABLE=730
MEMORY_DECAY_MULTIPLIER=0.5
MEMORY_MAX_EXCERPT_CHARS=1200
   ```

   > Attachments are processed asynchronously on the `attachments` queue. Files are scanned with ClamAV, text extracted, and summarized by LLM for action context. Signed downloads expire in 15-60 minutes.

---

## 3) Quickstart (Herd — macOS)

1. **Clone & install**

```bash
git clone <repo-url> Agent-AI
cd Agent-AI
composer install
npm install
```

2. **Select env**

```bash
cp .env.herd .env
```

3. **Create the database**
   Use TablePlus/Herd to create `agent_ai` on `127.0.0.1:5432` with user `root`.
   PostgreSQL must have the `pgvector` extension enabled; see CURSOR-README for schema notes.

4. **Migrate**

```bash
php artisan migrate
```

5. **Start processes**

```bash
npm run dev
php artisan horizon     # or php artisan queue:work
php artisan boost:mcp   # enables Cursor Boost tools
```

6. **Run ngrok for inbound testing**

```bash
ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test
```

> App: [http://agent-ai.test](http://agent-ai.test)
> Horizon: [http://agent-ai.test/horizon](http://agent-ai.test/horizon)
> Postmark Inbound Webhook → `https://abc123.ngrok-free.app/webhooks/inbound-email`

---

## 4) Quickstart (Docker)

1. **Clone & install**

```bash
git clone <repo-url> Agent-AI
cd Agent-AI
composer install
npm install
```

2. **Select env**

```bash
cp .env.docker .env
```

3. **Run services**

```bash
docker compose up -d
docker compose exec ollama ollama pull gpt-oss:20b  # LLM model for email processing
```

4. **Migrate**

```bash
docker compose exec app php artisan migrate
```

5. **Run ngrok for inbound testing**

```bash
ngrok http --url=abc123.ngrok-free.app 8080
```

> App: [http://localhost:8080](http://localhost:8080)
> Horizon: [http://localhost:8080/horizon](http://localhost:8080/horizon)
> Postmark Inbound Webhook → `https://abc123.ngrok-free.app/webhooks/inbound-email`

---

## 5) Quickstart (Artisan-only)

If you do not use Herd or Docker, install PHP/Postgres/Redis manually, then:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work
```

Run ngrok to expose Laravel for Postmark inbound:

```bash
ngrok http 8000
```

---

## 6) Inbound Email Webhook

Agent-AI accepts inbound mail at:

```
POST /webhooks/inbound-email
```

Secured with **HTTP Basic Auth**.
Set `WEBHOOK_USER` and `WEBHOOK_PASS` in `.env`.
Postmark should call your ngrok URL with these credentials.

---

## 7) Agent Coordination System

Agent-AI uses an intelligent **Coordinator + Specialized Agents** architecture that processes emails through specialized agents like Chef Mario (Italian cuisine) and Tech Support. The system automatically handles:

- Email threading with RFC 5322
- Language detection and i18n
- Confidence-based processing
- Multi-agent orchestration
- Memory with TTL/decay

For detailed documentation of the agent system, including flow diagrams, implementation details, and current status, see [CURSOR-README.md](CURSOR-README.md#agent-coordination-flow-with-laravel-mcp).

### Lightweight Refinements (Small-Business Friendly)
These refinements improve trust and debuggability without adding infra.
- **pgvector grounding**: embed email bodies, extracted attachment text, and stable memories; use KNN to fetch top-k context for answers.
- **AgentOps logs**: each LLM/tool call writes to `agent_steps` with tokens, latency, confidence, and (optionally) chosen model.
- **Model selection**: fast model for short classification/routing; larger model for synthesis/planning.
- **Internal delegation**: Coordinator can route tasks between specialized agents when complexity demands it.
> See technical details in [CURSOR-README.md → Lightweight Refinements](CURSOR-README.md#lightweight-refinements-small-business-friendly).

---

## 8) Laravel Boost (AI-assisted development)

Run the MCP server so Cursor can use Boost tools:

```bash
php artisan boost:mcp
```

In Cursor → **Settings → MCP**:

* Command: `php`
* Args: `artisan boost:mcp`
* Working directory: project root

---

## 9) Common tasks

* Dev server: `npm run dev`
* Prod build: `npm run build`
* Migrations: `php artisan migrate`
* Queues: `php artisan horizon` (or `php artisan queue:work`)
* Env key: `php artisan key:generate`
* Storage link: `php artisan storage:link`

---

## 9) Front-end stack

* Tailwind v4 via `@tailwindcss/vite`
* Flowbite via Vite imports in `resources/js/app.js`:

  ```js
  import 'flowbite'
  import 'flowbite-datepicker'
  ```
* Lucide icons via:

  ```js
  import { createIcons, Mail } from 'lucide'
  createIcons({ icons: { Mail } })
  ```

---

## 10) Development loop

Keep these processes running:

* Terminal A: `npm run dev`
* Terminal B: `php artisan horizon`
* Terminal C: `php artisan boost:mcp`
* Terminal D: `ngrok http --url=abc123.ngrok-free.app 80 --host-header=agent-ai.test`

Run migrations when needed:

```bash
php artisan migrate
```

---

**Mail addresses used**

* `MAIL_FROM_ADDRESS` — **sender identity** (used by Laravel for outbound mail).
* `AGENT_MAIL` — **public-facing address** users write to (Postmark inbound → webhook).