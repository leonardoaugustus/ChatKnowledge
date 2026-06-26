# Plataforma de Agentes de IA com Treinamento Curado — Project Phases

> Build plan for the platform (Laravel 13 + Livewire 4 + Flux UI, web only). Phases are numbered so they can be referenced individually (e.g. "implement Phase 4.2"). Each task lists the automated **Pest** feature tests that must accompany it. Status legend: `[ ]` = pending, `[x]` = already implemented.
>
> Sources of truth: `docs/project-description.md`, `.ai/guidelines/laravel-php.md`, OpenAI Vector Store + Laravel AI SDK docs (via Boost `search-docs`, then context7).
>
> **Test conventions (apply to every test bullet)**
> - Pest 4, feature tests under `tests/Feature/...` unless explicitly marked `Browser`, `Unit`, or `Arch`.
> - OpenAI / Laravel AI SDK calls are faked with the SDK's testing fakes — tests NEVER hit the real API.
> - Livewire component tests use `Livewire::test(...)`.
> - Every tenant-owned model MUST have at least one cross-organization isolation test (records from org A invisible when org B is active).
> - When acting as a user, set `current_organization_id` and ensure the `organization_user` membership exists so the global scope resolves.
> - Use the `Role`, `DocumentStatus`, `CurationStatus`, `AgentStatus` enum cases in tests — never hardcode strings.
>
> **General assumptions:** single-database multi-tenancy via Organizations (no tenancy package); active organization from `users.current_organization_id`; each agent has its own OpenAI Vector Store; nothing reaches the Vector Store without human approval; AI responses are always async + streamed.
>
> **Core principle (inviolable):** No raw document is ever sent directly to the Vector Store. All material MUST pass through the mandatory flow:
>
> `Material Bruto → Agente Extrator → Knowledge Items → Curadoria Humana → Aprovação → Publicação → OpenAI Vector Store`
>
> Human curation is mandatory and is one of the pillars of the platform.

---

## Como executar (workflow por item + `/clear`)

> Este documento é a memória do projeto — o contexto do chat é descartável. Trabalhe **um item numerado por vez** para não estourar o limite de tokens. Cada sessão começa "limpa" e relê este arquivo.

**Regras do fluxo:**

1. **Um sub-item por sessão**, não a fase inteira. Peça `implementa a 4.1.1`, não `executa a Phase 4`. Cada item já traz escopo + testes definidos abaixo e cabe num contexto.
2. **`/clear` entre itens.** Ao terminar (código + testes + commit), rode `/clear` antes do próximo item. Não acumule fases no mesmo chat — cada turno recarrega todo o histórico e encarece o token.
3. **Abra cada sessão apontando este doc:** _"Leia `docs/project-phases.md` e implemente o item **X.Y.Z**. Siga o `CLAUDE.md` e os testes listados nesse item."_
4. **Siga a numeração.** As fases têm dependências reais (0 → 1 → 2 …) e os sub-itens dentro de cada fase são sequenciais. Não pule.
5. **Testes via subagent.** Sempre delegue a execução dos testes ao subagent `test-runner` (não rode `php artisan test` direto — polui o contexto).
6. **Marque o progresso aqui.** Ao concluir um item, troque seu `[ ]` por `[x]` neste arquivo — ele é o tracker de progresso entre sessões.

**Ciclo por item:** `/clear` → "implementa a X.Y.Z" → código + testes Pest → testes via `test-runner` → revisão + commit → marca `[x]` → próximo item.

---

## Phase 0 — Foundations & Tooling

- [x] **0.1** Project bootstrapped from the official Livewire starter kit (Laravel 13 + Livewire 4 + Flux UI free + Fortify), with **Teams enabled** and **Laravel Boost** installed. Rename the Teams concept to **Organization** throughout.
  - **Tests:** _none — scaffolding only._
- [x] **0.2** Configure services: `config/services.php` blocks for `openai` (`api_key`) and `stripe`. Add `.env.example` entries. Postgres + Redis + Horizon configured.
  - **Tests:** _none — configuration only._
- [x] **0.3** Define domain enums in `app/Enums` (all `final`): `Role` (`Admin`, `Colaborador`), `AgentStatus` (`Draft`, `Published`), `DocumentStatus` (`Uploaded`, `Processing`, `Extracted`, `PendingCuration`, `Approved`, `Publishing`, `Published`, `Failed`), `CurationStatus` (`Pending`, `Approved`, `Rejected`), `KnowledgeType` (`Procedure`, `Rule`, `Policy`, `Faq`, `IdealAnswer`, `Exception`, `Glossary`, `Flow`, `OperationalStep`).
  - **Tests:** `tests/Unit/Enums/EnumsTest.php` — `it exposes label/color tokens for each case`; `it maps extractor output strings to KnowledgeType`.
- [x] **0.4** `App\Support\ActiveOrganization` (scoped singleton) + `App\Models\Concerns\BelongsToOrganization` trait (global scope + auto-fill + `organization()` relation).
  - **Tests:** `tests/Unit/Support/ActiveOrganizationTest.php` — `it resolves the active organization id from the user`; `it returns null when no user/organization`.
  - `tests/Feature/Tenancy/BelongsToOrganizationTest.php` — `it auto-fills organization_id on create`; `it scopes queries to the active organization`; `it never returns records from another organization`.
- [x] **0.5** `EnsureActiveOrganization` middleware — validates the user belongs to `current_organization_id` via the pivot; falls back / redirects otherwise.
  - **Tests:** `tests/Feature/Tenancy/EnsureActiveOrganizationTest.php` — `it passes when membership is valid`; `it falls back when current_organization_id is stale`; `it never proceeds with an organization the user does not belong to`.
- [x] **0.6** Pest base helpers in `tests/Pest.php`: `actingAsManager()`, `actingAsCollaborator()`, `withActiveOrganization()`, `fakeAi()` (binds the Laravel AI SDK fake), `fakeVectorStore()`.
  - **Tests:** _none — used indirectly everywhere._

---

## Phase 1 — Design System & Layout (no tests required)

> UI/CSS scaffolding with Flux UI (free tier) + Tailwind 4. **No automated tests in this phase.**

- [x] **1.1** Design tokens in `resources/css/app.css` (palette, typography, spacing). Tailwind 4 `@theme` extension.
- [x] **1.2** Base layouts: `layouts/guest` (auth screens) and `layouts/app` (authenticated, with sidebar/nav, organization switcher, plan-usage indicator).
- [x] **1.3** Shared UI partials built on Flux: page header, empty states, toast/flash via `@session`, confirmation modal, status badges (document/curation/agent), simple bar widget (Tailwind only — no chart library).
- [x] **1.4** Organization switcher component in the nav (lists the user's organizations, switches `current_organization_id`).
- [x] **1.5** Static `/_dev/ui-kit` route (local env only) rendering every partial.

---

## Phase 2 — Portal SaaS (Organizations, Auth, Members)

### 2.1 — Organizations & Membership
- [x] **2.1.1** `Organization` model (Cashier `Billable`), `organization_user` pivot with `role`, `User` relations (`organizations()`, `currentOrganization()`). Migrations + factories. _(Cashier `Billable` deferred to 2.5, where billing/Stripe lands.)_
  - **Tests:** `tests/Feature/Organization/OrganizationTest.php` — `it creates an organization and attaches the creator as Admin`; `it relates users and organizations many-to-many with a role`; `a user can belong to multiple organizations`.
- [x] **2.1.2** Onboarding: on first login/registration create the user's first organization and set it active.
  - **Tests:** `tests/Feature/Organization/OnboardingTest.php` — `it creates a default organization on first registration`; `it sets current_organization_id to the new organization`.

### 2.2 — Authentication (Fortify)
- [x] **2.2.1** Confirm Fortify flows (register, login, password reset, verification) work with the Organization onboarding hook.
  - **Tests:** `tests/Feature/Auth/AuthTest.php` — `it registers and lands authenticated with an organization`; `it logs in an existing user`; `it requires authentication for app routes`.

### 2.3 — Organization Switching
- [x] **2.3.1** Action to switch active organization (updates `current_organization_id` after membership check).
  - **Tests:** `tests/Feature/Organization/SwitchOrganizationTest.php` — `it switches to an organization the user belongs to`; `it rejects switching to an organization the user does not belong to`; `it re-scopes data after switching`.

### 2.4 — Member Invitations
- [x] **2.4.1** Invite a user by email to the active organization with a `Role`; accept flow attaches them via the pivot.
  - **Tests:** `tests/Feature/Organization/InvitationTest.php` — `it invites a user by email with a role`; `it attaches the user on acceptance`; `only an Admin can invite`; `an invitation is scoped to one organization`.

### 2.5 — Billing (Stripe + Cashier, fixed US$18/mo)

> The platform offers a single plan at US$18/mo. The plan has **limits configurable by the platform administrator** (users, agents, questions, storage, documents, etc.) defined via configuration — not hardcoded. There is **no overage billing in V1**, only consumption measurement.

- [x] **2.5.1** Subscribe the active organization to the single fixed plan (US$18/mo) via Cashier checkout; handle Stripe webhooks (created/updated/canceled). Plan limits are read from configuration (admin-configurable), not hardcoded.
  - **Tests:** `tests/Feature/Billing/SubscriptionTest.php` — `it subscribes the organization to the fixed plan`; `it reflects subscription status from webhooks`; `the Billable lives on Organization not User`; `it reads plan limits from configuration`.
- [x] **2.5.2** `EnsureSubscribed` middleware gating app features when not active (read-only / blocked per policy).
  - **Tests:** `tests/Feature/Billing/EnsureSubscribedTest.php` — `it blocks gated routes without an active subscription`; `it allows access while subscribed`.
- [x] **2.5.3** Usage measurement: `usage_records` (org + agent + type + quantity + timestamp), written on each AI question/extraction. _No overage billing in V1 — measurement only._
  - **Tests:** `tests/Feature/Billing/UsageRecordTest.php` — `it records a usage row per AI question`; `usage is scoped to the organization`.

---

## Phase 3 — Agents & Personality Builder

### 3.1 — Agent Model & Config Files
- [x] **3.1.1** `Agent` model (`BelongsToOrganization`, `AgentStatus`, `vector_store_id`) + `agent_configs` storing the OpenClaw-style markdown sections (`identity`, `soul`, `user`, `bootstrap`, `heartbeat`, `tools`) as DB fields rendered as Markdown. Add a `compiled_system_prompt` field, generated automatically from the composition of all sections (`identity`, `soul`, `user`, `bootstrap`, `heartbeat`, `tools`). Its purpose is to ease debugging, auditing, and visualizing the final prompt sent to the model.
  - **Tests:** `tests/Feature/Agent/AgentTest.php` — `it creates an agent scoped to the active organization`; `it stores all personality sections`; `agents from another organization are not visible`; `it caps agents per organization if a limit is set`; `it recompiles compiled_system_prompt when any section changes`; `compiled_system_prompt matches the composition of the sections`.

### 3.2 — Personality Builder UI
- [x] **3.2.1** Livewire full-page builder (`pages::agent.edit`) with structured sections (Identity, Personality/Soul, Objective, Tone, Rules, Heartbeat, Bootstrap, User, Tools) — Form Object, no raw-prompt textarea. Assembles the system prompt from sections and persists it to `compiled_system_prompt` on save.
  - **Tests:** `tests/Feature/Livewire/Agent/BuilderTest.php` — `it validates required sections`; `it persists each section`; `it assembles a system prompt from the sections`; `it renders sections as markdown preview`; `it recompiles compiled_system_prompt after editing a section`.

### 3.3 — Vector Store Provisioning
- [x] **3.3.1** On agent creation, async Horizon job provisions a dedicated OpenAI Vector Store and stores `vector_store_id`. Deleting an agent cleans up the store.
  - **Tests:** `tests/Feature/Agent/VectorStoreProvisioningTest.php` (faked) — `it provisions a vector store on agent creation`; `it stores the returned vector_store_id`; `each agent gets its own store`; `it deletes the store when the agent is deleted`.

---

## Phase 4 — Training: Upload & AI Extraction

### 4.1 — Document Upload
- [ ] **4.1.1** Upload screen accepting PDF, DOCX, TXT, Markdown, and plain text. Creates a `Document` (`DocumentStatus::Uploaded`) scoped to agent + organization. _No audio support in V1._
  - **Tests:** `tests/Feature/Livewire/Training/UploadTest.php` — `it accepts each supported format`; `it rejects unsupported types`; `it creates a document scoped to the agent and organization`; `it stores the raw material`.

### 4.2 — Extractor Agent (async)
- [ ] **4.2.1** Horizon job runs the Extractor via the Laravel AI SDK over raw material, producing structured knowledge items typed by `KnowledgeType` (procedures, rules, policies, FAQ, ideal answers, exceptions, glossary, flows, steps). Saved as `knowledge_items` with `CurationStatus::Pending`. Updates `DocumentStatus`. The `knowledge_items` table includes the fields: `title`, `content`, `summary`, `source_document_id`, `source_excerpt`, `confidence_score`, `approved_by`, `approved_at`, `published_at`, `vector_file_id`, `metadata`, `version`, `publication_status`.
  - **Tests:** `tests/Feature/Training/ExtractorTest.php` (faked) — `it produces structured knowledge items from raw material`; `it tags each item with a KnowledgeType`; `it marks items Pending for curation`; `it sets DocumentStatus to Extracted on success and Failed on error`; `extraction is async (queued)`; `it populates title, content, summary, source_document_id and source_excerpt`; `it sets an initial version and publication_status`.

---

## Phase 5 — Curation (human-in-the-loop)

### 5.1 — Curation Queue
- [ ] **5.1.1** Livewire page listing `knowledge_items` with `CurationStatus::Pending` for the active organization/agent.
  - **Tests:** `tests/Feature/Livewire/Curation/QueueTest.php` — `it lists only pending items for the active organization`; `it groups items by KnowledgeType`; `items from another organization are not visible`.

### 5.2 — Curation Actions
- [ ] **5.2.1** Edit / complement / remove / approve / reject a knowledge item. **Nothing is published to the Vector Store here** — approval only flags items as ready.
  - **Tests:** `tests/Feature/Livewire/Curation/ActionsTest.php` — `it edits an item`; `it approves an item (CurationStatus::Approved)`; `it rejects an item`; `it removes an item`; `only an Admin can curate`; `approval does not push to the vector store yet`.
- [ ] **5.2.2** Manual FAQ entry (create approved knowledge directly, still requires explicit approval state).
  - **Tests:** `tests/Feature/Livewire/Curation/ManualFaqTest.php` — `it creates a manual FAQ item`; `it is scoped to agent and organization`.

---

## Phase 6 — Publishing to OpenAI Vector Store

> **Publishing rules:** Only **approved** items are sent to the Vector Store. Publishing is **incremental** — an update synchronizes only the changed item, never re-sending the whole document to the Vector Store.

### 6.1 — Publish Approved Knowledge
- [ ] **6.1.1** Async Horizon job pushes **only approved** knowledge items to the agent's Vector Store (with metadata: `knowledge_type`, `source`, `approved_by`). Marks items as published.
  - **Tests:** `tests/Feature/Publishing/PublishTest.php` (faked) — `it pushes only approved items`; `it never pushes pending or rejected items`; `it attaches knowledge_type and source metadata`; `it targets the agent's own vector store`; `publishing is incremental (one item at a time)`; `it never re-sends the whole document`; `publishing is async`.
- [ ] **6.1.2** Re-publish / update: editing an approved+published item re-syncs only that item.
  - **Tests:** `it re-syncs an edited published item`; `it removes the item from the store when deleted`.
- [ ] **6.1.3** Simple versioning of Knowledge Items: when an already-published item is edited, create a new version, keep the history, and republish **only that item** — never re-send the whole document to the Vector Store.
  - **Tests:** `tests/Feature/Publishing/KnowledgeItemVersioningTest.php` (faked) — `it creates a new version when a published item is edited`; `it keeps the previous versions in history`; `it republishes only the edited item`; `it never re-sends the whole document to the vector store`.

---

## Phase 7 — Chat (RAG with streaming)

### 7.1 — Chat Shell & Streaming
- [ ] **7.1.1** Livewire chat page for collaborators using **Laravel AI SDK Native Streaming**, querying the agent's Vector Store through `FileSearch`. Persists conversation + messages. Never synchronous. While streaming, whenever the SDK exposes execution events the UI MUST show execution progress, e.g.: querying documents, searching the Vector Store, running File Search, using Tools, generating the answer.
  - **Tests:** `tests/Feature/Livewire/Chat/ChatTest.php` (faked) — `it answers using the agent's vector store`; `it confirms the agent belongs to the active organization before querying`; `it persists the conversation and messages`; `it requires authentication`; `responses are streamed via native streaming, not synchronous`; `it surfaces execution progress events while streaming`.

### 7.2 — Sources & Anti-Hallucination
- [ ] **7.2.1** Each answer cites its source knowledge items. When no sufficient knowledge is found, respond exactly: "Não encontrei essa informação na base de conhecimento deste agente."
  - **Tests:** `tests/Feature/Livewire/Chat/AntiHallucinationTest.php` (faked) — `it renders the source(s) for an answer`; `it returns the no-knowledge message when FileSearch finds nothing`; `it never answers from outside the vector store`.

### 7.3 — Unanswered → Curation Queue
- [ ] **7.3.1** Questions with no sufficient answer are logged and pushed automatically into the curation queue as gaps.
  - **Tests:** `tests/Feature/Chat/UnansweredToCurationTest.php` — `it records an unanswered question`; `it creates a curation gap item scoped to the agent`; `it does not duplicate identical gaps`.

---

## Phase 9 — Tools (configurable HTTP tools)

> Tools remain part of V1: the CRUD and current architecture are kept. The architecture MUST stay prepared for integration with ERP, CRM, external APIs, and internal systems, without limiting future integrations.

### 9.1 — Tool Definitions
- [ ] **9.1.1** `agent_tools` (name, endpoint, method, headers, auth, input/output schemas) scoped to agent. CRUD UI.
  - **Tests:** `tests/Feature/Tools/ToolDefinitionTest.php` — `it creates an HTTP tool scoped to the agent`; `it validates the schema`; `tools from another organization are not visible`.

### 9.2 — Tool Execution
- [ ] **9.2.1** The agent can call a configured tool during a chat (via the AI SDK tool interface), passing validated input and parsing output per schema. Calls wrapped in a Service using the `Http` facade.
  - **Tests:** `tests/Feature/Tools/ToolExecutionTest.php` (Http::fake) — `it invokes the configured endpoint with the right method/headers`; `it validates input against the schema`; `it surfaces tool errors without crashing the chat`; `it never executes a tool from another organization`.

---

## Phase 10 — Dashboard

### 10.1 — Overview
- [ ] **10.1.1** Dashboard for the active organization: counts of agents, documents, processings, pending curations, questions, unanswered questions, and plan usage. Adds indicators for: items pending publication, published documents, plan consumption, usage per agent, and processing failures. Simple bar widgets (Tailwind, no chart library).
  - **Tests:** `tests/Feature/Livewire/Dashboard/DashboardTest.php` — `it shows counts scoped to the active organization`; `it shows pending curation and unanswered counts`; `it shows plan usage`; `it shows items pending publication and published documents`; `it shows usage per agent`; `it shows processing failures`; `it never aggregates across organizations`.

---

## Phase 11 — Cross-Cutting & Hardening

- [ ] **11.1** Global error handling for AI/OpenAI failures (extraction, publishing, chat) — user-facing messages, retriable jobs, logged error codes.
  - **Tests:** `tests/Feature/ErrorHandling/AiFailureTest.php` — `it surfaces a friendly message on extraction failure`; `it retries the publish job on transient failure`; `it logs but does not display raw provider errors`.
- [ ] **11.2** Authorization policies (Admin vs Collaborator) across agents, training, curation, tools, billing.
  - **Tests:** `tests/Feature/Authorization/PolicyTest.php` — `a collaborator cannot curate, manage agents, or change billing`; `a collaborator can chat`; `an admin can do all of the above`.
- [ ] **11.3** Architecture pass with `pest --arch`.
  - **Tests:** `tests/Arch/ArchTest.php` — `Livewire components live under App\Livewire and are final`; `Services live under App\Services and do not read session/auth directly`; `Enums are final`; `tenant-owned models use BelongsToOrganization`.
- [ ] **11.4** Tenant-isolation audit: every tenant-owned model has a cross-organization leak test.
  - **Tests:** `tests/Arch/TenantIsolationTest.php` — `every model using BelongsToOrganization has a corresponding isolation test`.
- [ ] **11.5** AI audit logs: record logs for `extraction`, `publishing`, `chat`, and `tool execution`, capturing `latency`, `tokens`, `estimated cost`, and `errors`. These logs serve auditing and debugging purposes.
  - **Tests:** `tests/Feature/Audit/AiLogTest.php` — `it logs an extraction event with latency, tokens and estimated cost`; `it logs a publishing event`; `it logs a chat event`; `it logs a tool execution event`; `it logs errors`.
- [ ] **11.6** Full-suite green run: `php artisan test --compact` (delegated to the `test-runner` subagent) must pass before release.
  - **Tests:** _N/A — gate._

---

## Not in V1

- Continuous learning / long-term memory (Agent Memories, MemoryService, memory injection) — deferred to a future version
- WhatsApp / Evolution API
- Automatic conversation-quality evaluation
- Overage billing (fixed US$18/mo only)
- Tools marketplace
- Fine-tuning
- pgvector (OpenAI Vector Store only)

---

## Appendix A — Module → Phase coverage

| Módulo (V1 doc) | Phase |
|---|---|
| Portal SaaS / Organizations / Auth / Billing | 2 |
| Usuários (Admin/Colaborador) | 2.4, 11.2 |
| Agentes + Builder de Personalidade | 3 |
| Treinamento (upload + extrator) | 4 |
| Curadoria | 5 |
| Vector Store (publicação) | 6 |
| Chat (streaming/fontes/anti-alucinação) | 7 |
| Tools | 9 |
| Dashboard | 10 |

## Appendix B — Snapshot of what exists

Project starts from the official Livewire starter kit (Laravel 13, Livewire 4, Flux UI, Fortify, Teams, Boost) plus Pest 4 and Tailwind 4. Default `User` model + Teams scaffolding exist. No platform-specific agents, training, curation, chat, or tests are present yet, so every task above is `[ ]`. Re-run this check at the start of each phase and tick boxes as work lands.