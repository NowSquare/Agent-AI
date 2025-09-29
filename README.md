# Agent AI

**Email your AI employee. It remembers, coordinates specialists, uses safe tools, and gets work done.**

## What is Agent AI?

Agent AI turns your inbox into an AI employee that learns to understand your context over time. Send it an email like you would to a coworker: ask questions, share documents, request tasks. It reads your message, checks facts from your email history, proposes a plan, and replies with something you can trust. No new app to learn.

## Why This Matters

Work starts in email: quotes, questions, approvals, files. But context is scattered across threads and attachments, and generic chatbots don't know your business. Agent AI is an email-native teammate that:

* Builds memory from your emails and documents
* Grounds answers in your data (not the public web)
* Plans work like a professional (checklist, validation, safe execution)
* Explains itself with a clear activity trace

You keep using your inbox. Agent AI does the organizing, fact-finding, and drafting so you can approve and move on.

## Core Features

### Email-Native Assistant
Send questions, tasks, and files to a single email address. Agent AI replies in the same thread, maintaining full conversation context.

### Multi-Agent Orchestration
Specialized agents (Planner, Workers, Critics, Arbiter, Curator) collaborate to handle complex requests. Each step is validated before execution.

### Compounding Memory
Learns durable facts and preferences over time with confidence decay and TTL categories. Memory is scoped to thread, user, or account level.

### Grounded Responses
Retrieves relevant snippets from your own emails, attachment text, and memories before answering. No hallucination, just facts from your data.

### Symbolic Plan Validation
Before executing, the system writes a plan (state, action, next-state), validates preconditions and effects, auto-repairs simple gaps, then proceeds.

### Transparency by Design
The Activity screen shows every LLM and tool call: role, model, tokens, latency, decision reasons. Users only see their own threads.

### Local-First Models
Private by default using Ollama. You can mix local and cloud models per role. On first contact, an Account is automatically created from APP_NAME.

## How It Works

```
Email → Thread → Plan → Work → Debate → Decide → Memory → Reply
```

1. You send an email. A Contact and Thread are ensured.
2. Attachments (txt, md, csv, pdf) are virus-scanned before processing.
3. The message is cleaned and interpreted into a structured Action.
4. The Coordinator picks a simple single-agent path or complex multi-agent orchestration.
5. Retrieval fetches context from your messages, attachment text, and memories.
6. Workers draft, Critics check groundedness and risk, the Arbiter picks the best.
7. The Curator saves a short memory of the outcome with provenance.
8. Agent AI emails back when there's substantive value or asks for clarification if confidence is low.

### Why It's Better Than Prompting Yourself

* **Grounded**: Searches your own history first
* **Structured**: Actions and tool calls are schema-validated
* **Safe**: Plans are validated before execution
* **Transparent**: You see how and why decisions were made
* **Persistent**: Remembers outcomes so future answers improve

## Memory Model

Agent AI builds institutional knowledge that compounds over time, making each interaction smarter than the last.

### Scopes & Priority
Memories operate at three levels: **conversation** (thread-specific), **user** (individual preferences), and **account** (organization-wide). When retrieving information, Agent AI prioritizes in that order.

### Intelligent Retention
Not all information stays relevant forever:
* **Volatile** (30 days): Temporary facts like "John is out of office"
* **Seasonal** (90 days): Quarterly targets, project deadlines
* **Durable** (365 days): Standard procedures, key contacts
* **Legal** (policy-based): Compliance records, audit trails

### Confidence & Evolution
Information naturally becomes less certain over time through confidence decay. Fresh, frequently-referenced facts maintain high confidence while older information gradually fades. When facts change, new ones supersede previous versions while maintaining history for audit purposes.

This memory system means Agent AI becomes more valuable over time, learning your business without manual training or configuration.

## Use Cases

### Document Analysis
*"Summarize the key points from these attached contracts"*
Agent AI scans attachments for viruses, extracts text, and provides a summary grounded in the actual document content.

### Information Retrieval
*"What did John say about the Q2 budget in his last email?"*
Searches your email history and returns the specific information with source attribution.

### Task Processing
*"Extract all email addresses from the attached CSV and format them for our newsletter"*
Processes the file safely and returns formatted results ready for use.

### Decision Support
*"Based on the attached proposals, which vendor offers the best value?"*
Analyzes documents and provides comparison based on the actual content, not generic assumptions.

## Quick Start

### Prerequisites
* PHP 8.4+
* PostgreSQL 17+ with pgvector
* Redis 7+
* Node.js 18+
* ClamAV daemon
* Ollama for local models (optional: OpenAI/Anthropic API keys)

### Installation

1. **Setup environment**
```bash
git clone https://github.com/yourorg/agent-ai.git
cd agent-ai
cp .env.example .env
composer install
npm install
```

2. **Configure database**
```sql
CREATE EXTENSION IF NOT EXISTS vector;
```
```bash
php artisan migrate
```

3. **Start services**
```bash
npm run dev
php artisan horizon
php artisan serve
```

4. **Configure Postmark**
Set up inbound webhook with Basic Auth credentials from your .env file.

5. **Send first email**
Email your Agent address. Watch it process and reply in thread.

### Docker Alternative
```bash
docker compose up -d
php artisan migrate
```

## Configuration

### Model Routing
Agent AI uses role-based routing for efficiency:

```env
# Classification (fast, small)
LLM_CLASSIFY_MODEL="mistral-small3.2:24b"

# Grounded answers (medium, fact-based)
LLM_GROUNDED_MODEL="gpt-oss:20b"

# Complex reasoning (large, comprehensive)
LLM_SYNTH_MODEL="gpt-oss:120b"

# Embeddings for retrieval
EMBEDDINGS_MODEL="mxbai-embed-large"
EMBEDDINGS_DIM=1024
```

### Confidence Thresholds
* High (≥0.75): Auto-execute
* Medium (0.50-0.74): Ask one clarifying question
* Low (<0.50): Present safe options

## Security & Privacy

### Security First
* Mandatory virus scanning for all attachments
* SSRF-guarded network calls
* Signed links with expiry for approvals
* Schema validation for all AI outputs

### Privacy by Design
* Local-first AI option
* User data isolation
* GDPR-compliant controls
* No model training on your data

## Architecture

### Multi-Agent Flow
* **Coordinator**: Detects complexity, selects agents
* **Planner**: Proposes symbolic plan with validation
* **Workers**: Execute focused tasks
* **Critics**: Score groundedness and risk
* **Arbiter**: Selects best response
* **Curator**: Saves compact memory

### Data Model
* Accounts, users, memberships for multi-tenancy
* Threads and messages for email continuity
* Attachments with scan status and extractions
* Memories with scope, TTL, and confidence decay
* Agent steps for complete audit trail

### Technology Stack
Laravel 12, PHP 8.4, PostgreSQL 17 + pgvector, Redis 7, Postmark, Tailwind/Flowbite, Ollama (optional OpenAI/Anthropic), ClamAV, Docker

## Troubleshooting

**Vector dimension mismatch**
Check EMBEDDINGS_DIM matches model. Run `php artisan migrate:fresh` then `php artisan embeddings:backfill`.

**Everything routes to SYNTH**
Lower LLM_SYNTH_COMPLEXITY_TOKENS or improve retrieval settings.

**ClamAV connection refused**
Ensure daemon runs on 127.0.0.1:3310. Check container logs.

**Webhook authentication failing**
Verify WEBHOOK_USER/PASS and that raw request body is used for HMAC.

## Roadmap

### Near Term
* Enhanced metrics and activity filters
* Expanded tool library
* Additional language support

### Future
* Calendar integration
* Slack/Teams connectors
* Workflow automation
* Plugin ecosystem

## Contributing

PRs should be small, focused, and include tests. Keep `php artisan migrate:fresh` green. Document changes in README.md and CURSOR-README.md when behavior changes.

## License

SPDX-License-Identifier: **Apache-2.0**
Copyright (c) 2025 **NowSquare**

---

**Start with the Quickstart**, send an email to your Agent address, and watch it reply in the same thread, grounded in your own history. That's the whole point.