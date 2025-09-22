# Agent-AI

Agent-AI is a Laravel-based application for building practical, privacy-respecting agent workflows:

* Ingest emails & attachments
* Trigger background jobs and tasks
* Run LLM prompts/tools
* Keep an auditable trail of actions and outputs

This README explains **how to run Agent-AI** locally with **Laravel Herd** (recommended on macOS), with **Docker** (Windows/Linux/macOS), or via **Artisan**. It also shows how to integrate **Laravel Boost** so Cursor can use Laravel-aware tools during development.

**Current Status**: Email processing pipeline is active with LLM interpretation using gpt-oss:20b. Inbound emails are processed asynchronously (10-minute LLM timeout, 15-minute queue timeout) for comprehensive analysis and action generation.

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
* **LLM**: Local Ollama (gpt-oss:20b) or remote HTTP providers
* File scanning (ClamAV) & PDF text extraction (Spatie + poppler)
* **Laravel Boost**: MCP server for AI-assisted development in Cursor

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

## 7) Laravel Boost (AI-assisted development)

Run the MCP server so Cursor can use Boost tools:

```bash
php artisan boost:mcp
```

In Cursor → **Settings → MCP**:

* Command: `php`
* Args: `artisan boost:mcp`
* Working directory: project root

---

## 8) Common tasks

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