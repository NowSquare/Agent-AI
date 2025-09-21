# Agent-AI

Agent-AI is a Laravel-based application for building practical, privacy-respecting agent workflows:
- ingest email/attachments,
- trigger tasks and background jobs,
- run LLM prompts/tools,
- keep an auditable trail of actions and outputs.

This README explains **how to run Agent-AI** locally with **Laravel Herd** (recommended for macOS), with **Docker** (Windows/Linux/macOS), or via **Artisan**. It also shows how to integrate **Laravel Boost** so Cursor can use Laravel-aware tools during development.

---

## 🚀 Quick start

**Pick your mode first:**
- macOS devs → **Herd** → `cp .env.herd .env`
- Windows/Linux devs (or macOS without Herd) → **Docker** → `cp .env.docker .env`
- Fallback (any OS) → **Artisan-only** with your own Postgres + Redis

> In development, **Postmark handles inbound webhooks**.
> - Outbound: Laravel sends via Postmark SMTP.
> - Inbound: Postmark sends webhooks to `/webhooks/inbound-email` with HMAC validation.

---

## 1) What you get

- **Laravel 12** (PHP 8.3/8.4 ready), Vite, Tailwind v4, Flowbite UI  
- **PostgreSQL** database (Herd or Docker)  
- **Redis** queues (Herd or Docker) and **Laravel Horizon** dashboard  
- **Mail**: Postmark for both development and production (webhooks + SMTP)  
- **LLM** via **Ollama** (local) or any HTTP provider  
- File scanning (ClamAV) & PDF text extraction (Spatie + poppler)  
- **Laravel Boost**: MCP server for smarter AI-assisted development in Cursor

---

## 2) Prerequisites

Everything in `composer.json` and `package.json` is installed automatically.  
You only need **system-level tools**:

### Option A — macOS with Herd (recommended)
- Herd with PHP 8.3+ and PostgreSQL enabled  
- Node 20.19+ or 22.12+ (we use Node 22)  
- Redis (via Herd, optional)  
- **System deps (Homebrew):**
```bash
brew install poppler clamav
```

> Configure Postmark webhook for inbound emails:
> - Set up Postmark inbound webhook pointing to `/webhooks/inbound-email`
> - Use ngrok or similar: `ngrok http 8080`
> - Webhook URL: `https://your-ngrok-url.ngrok.io/webhooks/inbound-email`

### Option B — Docker (Windows/Linux/macOS)

* Docker & Docker Compose
* Node 20.19+ or 22.12+ (for asset builds if you build on host)

The provided `docker-compose.yml` includes Mailpit (SMTP/UI), Postgres, Redis, Ollama, and ClamAV.

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

3. **Create the database** (TablePlus/Herd)
   Create `agent_ai` on `127.0.0.1:5432` with user `root` (no password).

4. **Migrate**

```bash
php artisan migrate
```

5. **Start Mailpit** (if not already running)
   See the command in the Prerequisites section above.

6. **Start dev processes**

```bash
npm run dev
php artisan horizon    # or: php artisan queue:work
php artisan boost:mcp  # enables Cursor + Boost tools
```

> App: [http://agent-ai.test](http://agent-ai.test)
> Horizon: [http://agent-ai.test/horizon](http://agent-ai.test/horizon)
> Mailpit UI: [http://localhost:8025](http://localhost:8025)

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
docker compose --profile dev up -d
docker compose exec ollama ollama pull gpt-oss:20b
```

4. **Migrate**

```bash
docker compose exec app php artisan migrate
```

> App: [http://localhost:8080](http://localhost:8080)
> Horizon: [http://localhost:8080/horizon](http://localhost:8080/horizon)
> Mailpit UI: [http://localhost:8025](http://localhost:8025)

### Production tips

* Set:

```ini
APP_ENV=production
APP_DEBUG=false
MAIL_MAILER=postmark
INBOUND_EMAIL_DRIVER=postmark
```

* Inside the app container:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

* In Postmark, enable **Inbound** and point the webhook to:
  `https://yourdomain.tld/webhooks/inbound-email`
  Include the shared secret (header `X-Inbound-Token` or `?token=...`).

---

## 5) Quickstart (Artisan-only)

If you do not use Herd or Docker, install PHP/Postgres/Redis/Mailpit manually, then:

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

Run Mailpit pointing its webhook to `http://127.0.0.1:8000/webhooks/inbound-email`.

---

## 6) Inbound Email Webhook (dev & prod)

Agent-AI accepts inbound mail at:

```
POST /webhooks/inbound-email
```

Secure with:

* Header: `X-Inbound-Token: <INBOUND_WEBHOOK_SECRET>`
* or query: `?token=<INBOUND_WEBHOOK_SECRET>`

**Dev:** Mailpit forwards messages to this endpoint.
**Prod:** Postmark Inbound forwards messages to this endpoint.

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

* Tailwind v4 via `@tailwindcss/vite` (`resources/css/app.css` → `@import "tailwindcss";`)
* Flowbite via Vite imports in `resources/js/app.js`:

  ```js
  import 'flowbite'
  import 'flowbite-datepicker'
  ```
* Blade templates with:

  ```blade
  @vite(['resources/css/app.css','resources/js/app.js'])
  ```

---

## 10) Development loop

During development, keep these processes running in separate terminals:

* Terminal A: `npm run dev`  
  (Vite dev server, hot reloads)

* Terminal B: `php artisan horizon`  
  (or `php artisan queue:work` if you don’t want Horizon)

* Terminal C: `php artisan boost:mcp`  
  (so Cursor sees routes, schema, Tinker, logs)

* Terminal D: **Webhook testing**
  - Set up Postmark inbound webhook
  - Use ngrok: `ngrok http 8080`
  - Configure Postmark webhook URL: `https://your-ngrok-url.ngrok.io/webhooks/inbound-email`

---

Run migrations when needed:
```bash
php artisan migrate
```

---

**Mail addresses used**

* `MAIL_FROM_ADDRESS` — **sender identity** for outgoing mail (tech/noreply).
* `AGENT_MAIL` — **public-facing address** users email; in dev it’s delivered via Mailpit; in prod, via Postmark Inbound to the webhook.