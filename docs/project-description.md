# CLAUDE.md

Guidance for AI coding agents working in this repository. For the full product vision and architecture rationale, see `docs/documentacao-projeto.md`.

---

## Project summary

Multi-organization SaaS where each client organization creates and configures **N AI agents**. Each agent has its own persona, behavior rules, and dedicated knowledge base. Agents answer employees' questions based **exclusively** on the organization's uploaded documents (RAG), always citing the source and explicitly saying when an answer isn't found in the knowledge base.

- **MVP (Phase 1):** AI knowledge base — document upload, agents, RAG chat with streaming.
- **Phase 2:** WhatsApp conversation quality analysis (out of MVP scope).

---

## Stack

- **Laravel 13**, PHP 8.4
- **Livewire 4 + Flux UI** (Tailwind 4) — started from the official Laravel Livewire starter kit
- **Laravel Fortify** for auth (comes with the starter kit)
- **Laravel AI SDK (`laravel/ai`)** for all AI work — agents, streaming, FileSearch, conversation memory
- **OpenAI** for LLM + native Vector Store (via the SDK's `FileSearch` tool)
- **PostgreSQL** (relational data + references to OpenAI files/vector stores; NO pgvector in MVP)
- **Redis + Horizon** for queues and async processing
- **Stripe via Laravel Cashier** for billing (Billable lives on `Organization`)
- **Laravel Boost** installed to improve AI-assisted coding

Do **not** introduce: a Python microservice, pgvector, or a heavy multi-tenancy package. The Laravel AI SDK and the starter kit's Teams feature already cover these needs for the MVP.

---

## Inviolable rules (security — never break these)

1. **Tenant isolation.** Every data query MUST be scoped by `organization_id` = the active organization. Enforce via global scopes, never ad-hoc `where` clauses in controllers.
2. **Active-organization validation.** A central middleware MUST verify, on every request, that the authenticated user actually belongs to the active organization before any operation.
3. **Per-agent vector store.** Each agent has its own dedicated OpenAI vector store. A query resolves: (a) does the user belong to the active org? (b) does the agent belong to the active org? — and only then queries that agent's vector store via `FileSearch`.
4. **No cross-tenant / cross-agent vector store sharing.** Documents from different organizations or agents NEVER share a vector store.
5. **Anti-hallucination.** Agents answer only from their knowledge base. When no supporting content exists, the agent explicitly states it couldn't find the information. Always surface the source document for an answer.

---

## Multi-tenancy model

- Based on the starter kit's **Teams**, renamed/adapted to **Organization**. The kit's "current team" becomes the **active organization** (stored in session).
- URL is **neutral** (`app.domain.com`) — it does NOT identify the tenant. The active organization does.
- A user can belong to **N organizations** and switch between them.
- A user's role (`gestor` / `colaboradora`) is **per organization**, via the `organization_user` pivot — not global.

---

## Core data model

```
users
organizations          (Cashier Billable lives here)
organization_user      (pivot: user_id, organization_id, role)
agents                 (organization_id, name, instructions/persona, model, vector_store_id, status)
documents              (agent_id, name, format, status, openai_file_ids, chunks_count, version)
conversations/messages (employee <-> agent; may reuse the SDK's agent_conversations tables)
usage_records          (organization_id, agent_id, type, quantity, timestamp)
```

Isolation hierarchy: **Organization → Agents → each Agent has its own Vector Store.**

> The `Agent` entity exists from the MVP, even if each organization starts with a single default agent. Documents and conversations hang off the **agent**, not directly off the organization. Adding the agent layer later would be a painful migration.

---

## AI integration (Laravel AI SDK)

- The business `Agent` entity maps to an SDK Agent class (`make:agent`). The persona/rules the client configures become the agent's `instructions` at runtime.
- **Vector Store:** pass the agent's `vector_store_id` into the `FileSearch` tool. Supports metadata filtering.
- **Streaming:** native SSE (Vercel AI data protocol), works with Livewire out of the box. AI responses are **always async + streamed** — never block a sync HTTP request waiting on the LLM.
- **Conversation memory:** use the `RemembersConversations` trait; the SDK persists/retrieves history.
- **Provider failover:** start with OpenAI; the SDK allows adding/switching providers (Anthropic, Gemini) via config without rewriting business logic.
- The SDK is **beta** — pin versions, cover AI flows with the SDK's testing fakes.

---

## Billing (Stripe + Cashier)

- `Billable` trait on `Organization` (not `User`). Each organization has its own subscription.
- Model: **fixed + overage**. Fixed monthly subscription includes a usage allowance; overage billed via Stripe metered billing.
- **Usage measurement is mandatory from day one** (both for limits and for billing). Record consumption per organization/agent in `usage_records`.
- Bill overage in a **client-understandable unit** (e.g. "questions"/"messages"), NOT raw tokens.
- Activate overage charging **only once real consumption data exists** — price it from data, not guesses.
- A subscription-check middleware gates access per organization on non-payment.

---

## Roles

- **Platform admin** (us): manages tenants, plans, system.
- **Organization manager** (`gestor`): manages documents and agents, sees reports/usage, manages employees.
- **Employee** (`colaboradora`): uses the agent chat.

---

## Conventions

- Async-first for anything touching the LLM or external APIs (Horizon jobs). Document upload → job → push to OpenAI vector store; never inline in the request.
- Keep tenant scoping centralized (global scopes / a base model or trait), never duplicated per query.
- Prefer the starter kit's existing patterns (Fortify, Flux UI, Teams) over custom reimplementations.
- Write Pest tests; use the AI SDK's fakes for AI-dependent code.
- PHP 8.4 features and strict types where reasonable.

---

## Out of scope for MVP (do not build yet)

- WhatsApp capture/analysis (Phase 2) — Evolution API → webhook → Postgres → nightly Horizon analysis job. Carries ToS (ban) and LGPD risks; those are tenant responsibilities documented in terms of use.
- Guided agent builder UI (Phase 1.5) — structured context-engineering form (persona, scope, tone, behavior rules, citation instructions) that assembles the system prompt. MVP ships a simple agent config form (name + instructions + documents).
- pgvector migration, sophisticated document versioning, rich dashboards.

> Note: "training an agent" in this product is NOT fine-tuning. It's context configuration (system prompt/persona) + knowledge base (RAG via vector store). Keep that distinction in code and naming.