# Agent AI Context

## Stack & Tools
- Backend: PHP 8.4, Laravel 12.x, Horizon, Redis 7.x, PostgreSQL 17+ (Herd/Docker; no SQLite unless specified)
- Frontend: Blade + Tailwind 4.x + Flowbite latest, Vite
- LLM: Ollama (local) + optional remotes
- Security/Docs: ClamAV latest, spatie/pdf-to-text
- Email: Postmark for send/receive (dev: SMTP 1025/UI 8025; inbound webhook to /webhooks/inbound-email with secret). Prod same.

## Conventions & Rules
- DB: snake_case columns, ULID PKs, JSONB with array casts
- Architecture: FormRequest validation; thin Controllers; Services/Jobs for logic; Policies for authZ
- Routes: web.php (UI/webhooks/signed), api.php (MCP/internal)
- i18n: Middleware for detection; translations in Blade/emails
- Icons: Lucide via <i data-lucide="...">; init in resources/js/app.js with createIcons(); recall on DOM updates
- Migrations: Modify existing files only—no new add/alter; ensure migrate:fresh works
- Dev: Never php artisan serve; use Herd/Docker. Env keys: AGENT_MAIL, INBOUND_EMAIL_DRIVER, INBOUND_WEBHOOK_SECRET, MAIL_*, LLM_*, QUEUE_CONNECTION
- Dependencies: Scan composer.json/package.json before suggesting; idempotent changes
- UI/UX: Minimal, accessible, responsive (Jony Ive-inspired)
- User experience: Steve Jobs-inspired

## MCP Integration
- Use @boost:mcp for Laravel ops (schema/routes/logs)
- Use @agent-ai:mcp for AI (tools: ActionInterpretationTool, etc.; prompts: DefineAgentsPrompt)
- Schema-driven; no manual JSON parsing