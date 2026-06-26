<laravel-boost-guidelines>
=== .ai/laravel-php rules ===

# Project Guidelines — Laravel, Livewire, Integrations, AI and Multi-tenancy

## General code instructions

- Don't generate code comments above the methods or code blocks if they are obvious. Don't add docblock comments when defining variables, unless instructed to, like `/** @var \App\Models\User $currentUser */`. Generate comments only for something that needs extra explanation for the reasons why that code was written.
- For new features, you MUST generate Pest automated tests.
- To run tests, ALWAYS delegate to the `test-runner` subagent via Task tool. Do not run `php artisan test` directly — it pollutes your context with long output.
- For library documentation, if some library is not available in Laravel Boost `search-docs`, always use context7. Automatically use the Context7 MCP tools to resolve library id and get library docs without me having to explicitly ask.
- If you made changes to CSS/Javascript files or added new Tailwind classes in Blade, run `npm run build` after all front-end changes are finished.

## Memory instructions (mem0)

This project uses the mem0 skill and MCP for persistent memory across sessions.
ACTIVELY use mem0 — do not wait to be asked. The skill handles the API mechanics.

---

## Backend API integration

This frontend consumes an external REST API. Treat the OpenAPI spec as the source of truth — never invent endpoints, payload shapes, or status codes.

- **Base URL:** `http://localhost/api`
- **OpenAPI spec:** `http://localhost/api/openapi.yaml`
- **Workflow before integrating any endpoint:**
    1. Fetch the spec section for the endpoint with `WebFetch` — don't load the whole JSON into context unless necessary.
    2. Confirm: HTTP method, path, required/optional params, request body schema, response schema, auth requirements, and error responses.
    3. Mirror field names and types exactly — do not rename or reshape on the client unless there's a documented mapping reason.
- **HTTP client:** use Laravel's `Http` facade (`Http::baseUrl(...)`, `Http::withToken(...)`).
- **Configuration:** API base URL and credentials must come from `config/services.php` (`services.backend_api.*`) backed by `.env` vars — never hardcode.
- **Errors:** Services should throw typed exceptions on non-2xx responses; let the Component/Controller decide how to present them to the user.
- **DTOs:** when a response shape is reused in multiple places, create a typed DTO/Data object instead of passing raw arrays around.
- **If the spec is unreachable** — API down, network issue — stop and ask the user. Do not guess the contract from memory or prior code.

### Internal Services vs External Integrations

- `app/Services/` is reserved for reusable **business rules and domain logic**.
- Service classes in `app/Services/` MUST NOT be responsible for directly consuming external APIs.
- When consuming external APIs, create a dedicated integration folder using this structure:

```text
app/
└── Integrations/
    └── NomeDaFerramenta/
        ├── NomeDaFerramentaClient.php
        └── NomeDaFerramentaService.php
```

- `NomeDaFerramentaClient` is responsible for the direct HTTP communication with the external API:
    - base URL
    - authentication
    - headers
    - request payload
    - raw response handling
    - non-2xx detection
- `NomeDaFerramentaService` is responsible for rules and transformations that are specific to that external tool:
    - preparing data before sending to the API
    - mapping external response data into DTOs/Data objects
    - handling tool-specific workflows
    - normalizing errors into typed exceptions
- Controllers and Livewire Components MUST NOT call `Client` classes directly. They should call an application Service from `app/Services/` or an Integration Service from `app/Integrations/NomeDaFerramenta/` depending on the use case.
- Business rules that belong to the product/domain should stay in `app/Services/`.
- Rules that exist only because of a specific third-party API behavior should stay inside the corresponding `app/Integrations/NomeDaFerramenta/` folder.

Example:

```text
app/
├── Services/
│   └── DocumentIngestionService.php
└── Integrations/
    └── BackendApi/
        ├── BackendApiClient.php
        └── BackendApiService.php
```

Recommended responsibility split:

- `DocumentIngestionService`: decides the product/domain workflow.
- `BackendApiClient`: performs HTTP calls to the external API.
- `BackendApiService`: handles Backend API-specific rules, payload preparation, DTO mapping, and typed exceptions.

---

## PHP instructions

- In PHP, use `match` operator over `switch` whenever possible.
- Generate Enums always in the folder `app/Enums`, not in the main `app/` folder, unless instructed differently.
- Always use Enum value as the default in the migration if column values are from the enum. Always cast this column to the enum type in the Model.
- Don't create temporary variables like `$currentUser = auth()->user()` if that variable is used only one time.
- Always use Enum where possible instead of hardcoded string values, if Enum class exists. For example, in Blade files, and in the tests when creating data if field is casted to Enum then use that Enum instead of hardcoding the value.

---

## Laravel instructions

- **Eloquent Observers** should be registered in Eloquent Models with PHP Attributes, and not in AppServiceProvider. Example: `#[ObservedBy([UserObserver::class])]` with `use Illuminate\Database\Eloquent\Attributes\ObservedBy;` on top.
- Aim for slim Controllers/Components and put larger logic pieces in Service classes.
- Use Laravel helpers instead of `use` section classes. Examples: use `auth()->id()` instead of `Auth::id()` and adding `Auth` in the `use` section. Other examples: use `redirect()->route()` instead of `Redirect::route()`, or `str()->slug()` instead of `Str::slug()`.
- Don't use `whereKey()` or `whereKeyNot()`, use specific fields like `id`. Example: instead of `->whereKeyNot($currentUser->getKey())`, use `->where('id', '!=', $currentUser->id)`.
- Don't add `::query()` when running Eloquent `create()` statements. Example: instead of `User::query()->create()`, use `User::create()`.
- When adding columns in a migration, update the model's `$fillable` array to include those new attributes.
- Never chain multiple migration-creating commands, such as `make:model -m` or `make:migration`, with `&&` or `;` — they may get identical timestamps. Run each command separately and wait for completion before running the next.
- Enums: If a PHP Enum exists for a domain concept, always use its cases, or their `->value`, instead of raw strings everywhere — routes, middleware, migrations, seeds, configs, and UI defaults.
- Don't create Controllers with just one method which just returns `view()`. Instead, use `Route::view()` with Blade file directly.
- Always use Laravel's `@session()` directive instead of `@if(session())` for displaying flash messages in Blade templates.
- In Blade files always use `@selected()` and `@checked()` directives instead of `selected` and `checked` HTML attributes.

### Service classes

- Use Service classes to encapsulate reusable business logic, keeping Controllers and Livewire Components slim.
- Service classes MUST be created in the `app/Services/` folder.
- Service classes are for business/domain rules. External API consumption belongs in `app/Integrations/NomeDaFerramenta/`.
- If a Service is used in only ONE method of a Controller or Component, inject it directly into that method via type-hinting. If it is used in MULTIPLE methods, initialize it in the Constructor, or `mount()`/`boot()` for Livewire Components.
- The same injection rule applies to both traditional Controllers and Livewire Components — use `mount()` or `boot()` to inject Services in Components when needed across multiple methods, or inject directly into the action method.
- Services MUST NOT contain presentation logic — views, redirects, flash messages. Return data or throw exceptions, and let the Controller/Component decide how to present the result.
- Services MUST be independently testable — avoid coupling with `request()`, `session()`, or `auth()` directly. Receive those values as parameters instead.

### Model construction rules

- Models MUST define the `$fillable` property correctly for all mass-assignable attributes.
- When adding new columns via migration, you MUST update the corresponding Model `$fillable` array.
- Relationships MUST follow Laravel naming conventions (`user()`, `orders()`, `profile()`, etc.).
- Relationship methods MUST use correct return types (`HasMany`, `BelongsTo`, `HasOne`, etc.).
- All relationships MUST have their inverse defined when applicable.
    - If `User` hasMany `Order`, then `Order` MUST define `belongsTo(User::class)`.
    - If `User` hasOne `Profile`, then `Profile` MUST define `belongsTo(User::class)`.
- Do not assume foreign key naming. Explicitly define foreign keys if they don't follow Laravel conventions.
- If a column represents a domain concept backed by an Enum, the Model MUST cast it using `$casts`.

---

## Livewire 4 instructions

- In Livewire projects, don't use Livewire Volt. Only Livewire class components — single-file or multi-file.
- In Livewire projects, computed properties should be used with PHP attribute `#[Computed]` and not method `getSomethingProperty()`.

### Full-page components (Pages)

- Use Livewire components as full pages instead of traditional Controllers for routes that render interactive views.
- Register full-page component routes with `Route::livewire()` in `routes/web.php`:

```php
Route::livewire('/posts', 'pages::post.index');
Route::livewire('/posts/create', 'pages::post.create');
Route::livewire('/posts/{post}', 'pages::post.show');
Route::livewire('/posts/{post}/edit', 'pages::post.edit');
```

- Page components MUST use the `pages::` prefix for organization. They live in `resources/views/pages/`.
- To create a new page component via Artisan: `php artisan make:livewire pages::post.create`.
- The default layout is located at `resources/views/layouts/app.blade.php`. To use a different layout, use the `#[Layout('layouts::admin')]` attribute on the component class.
- To set a dynamic page title, use the `->title()` fluent method in `render()`.
- Route Model Binding works automatically in full-page components. Define the typed parameter in `mount()`, or simply declare a typed public property with the same name as the route parameter.
- DO NOT create Controllers that only return views with data for interactive pages — use full-page Livewire components instead. Traditional Controllers should only be used for API routes, downloads, redirects, or actions that don't require interactivity.

### Forms

- For forms, ALWAYS use **Livewire Form Objects** when the component has more than 2 form fields.
- Create Form Objects with the command: `php artisan make:livewire-form PostForm`.
- Form Objects live in `app/Livewire/Forms/` and extend `Livewire\Form`.
- Use the `#[Validate]` attribute to define validation rules directly on Form Object properties.
- In the component, declare the Form Object as a public property and use `wire:model="form.field"` in the template.
- For simple forms — 1 or 2 fields — it is acceptable to use public properties directly on the component with `#[Validate]`.
- Use `wire:model` without `.live` by default. Use `wire:model.live` or `wire:model.live.blur` only when real-time validation is needed.
- For edit forms, populate the Form Object in `mount()` using `$this->form->fill($model->toArray())` or by setting properties individually.
- Heavy persistence logic inside `save()` should be delegated to a Service class, keeping the component slim.

### Component structure

- Livewire components should follow the same slim Controllers philosophy: business logic goes into Services, the component only handles binding, validation, and orchestration.
- For actions involving complex business logic, inject the Service directly into the method.
- Use `wire:navigate` on links between Livewire pages for SPA-like navigation without full page reloads.

---

## UI / Flux & Dashboard instructions

- The UI is built with **Flux UI free tier** + Tailwind 4. Prefer Flux components (`flux:button`, `flux:input`, `flux:select`, `flux:modal`, etc.) over hand-rolled markup for standard UI.
- Do NOT assume Flux Pro components are available — tables, charts, date pickers, rich text editors are Pro. If a Pro component would be needed, build it with Tailwind or ask before adding the Pro dependency.
- **Simple bar dashboards** MUST be built with plain Tailwind + Blade, using a proportional-width `div` per bar, NOT a charting library.
- **Rich/interactive charts** — tooltips, multiple series, time axis — use **ApexCharts via Alpine.js** with `wire:ignore` on the chart container so Livewire re-renders don't destroy the canvas. Pass data PHP → JSON into the Alpine component.
- NEVER introduce a separate JS framework — React/Vue — for a dashboard or any sub-view. The stack is Livewire-first; charts are the only place a JS library is acceptable, scoped to the chart itself.

---

## Multi-tenancy (Organization scoping) instructions

This project is multi-tenant via **Organizations**, single-database, with NO tenancy package. Tenant isolation is enforced through an `organization_id` column + a global scope. A user can belong to N organizations and switches between them via an active organization.

### Core model

- The tenant entity is the `Organization` model. The Cashier `Billable` trait lives on `Organization`, not `User`.
- The `User` <-> `Organization` relationship is many-to-many via the `organization_user` pivot, which carries a `role` column.
- `role` MUST be backed by a PHP Enum (`App\Enums\Role`) in `app/Enums`, cast on the pivot, and used instead of raw strings everywhere — migrations, seeds, middleware, Blade, tests.

### Active organization

- The active organization is stored in a `current_organization_id` column on the `users` table, FK to `organizations`, mirroring the starter kit's `current_team_id` pattern. Do NOT store it in the session.
- `User` MUST define a `currentOrganization()` `belongsTo(Organization::class)` relationship.
- A user's `current_organization_id` MAY point to an organization they no longer belong to. Reads MUST validate membership via the `organization_user` pivot before trusting it.

### The BelongsToOrganization trait + global scope

- Every model that holds tenant data — for example, `Agent`, `Document`, `Conversation`, `UsageRecord` — MUST use the `App\Models\Concerns\BelongsToOrganization` trait.
- The trait MUST:
    - Add a global scope filtering by the active organization's id.
    - Auto-fill `organization_id` on model creation from the active organization.
    - Define the `organization()` `belongsTo(Organization::class)` relationship.
- `organization_id` MUST be in each model's `$fillable` and have its FK + index in the migration.
- NEVER write ad-hoc `where('organization_id', ...)` clauses in Components/Controllers/Services — rely on the trait's global scope. The scope is the single source of truth for isolation.

```php
namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\ActiveOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if ($organizationId = app(ActiveOrganization::class)->id()) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $organizationId);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->organization_id) && $organizationId = app(ActiveOrganization::class)->id()) {
                $model->organization_id = $organizationId;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
```

### Resolving the active organization

- Resolve the active organization through a single class (`App\Support\ActiveOrganization`) bound as a scoped singleton. It reads the authenticated user's `current_organization_id` and exposes `id()` / `organization()`.
- Services MUST NOT read the active organization from `session()`/`auth()` directly. Receive the `Organization`, or its id, as a method parameter so Services stay independently testable.

### Membership-validation middleware

- A middleware, such as `EnsureActiveOrganization`, MUST run on all authenticated, organization-scoped routes and verify the user belongs to their `current_organization_id` via the `organization_user` pivot.
- If membership is invalid/missing, the middleware MUST fall back to a valid organization, or redirect to an organization-selection screen. Never proceed with an organization the user doesn't belong to.

### Cross-tenant safety

- Data from different organizations MUST never leak across tenants. The global scope is mandatory on every tenant-owned model.
- For AI agents: each `Agent` has its own dedicated OpenAI vector store (`vector_store_id`). A query MUST confirm:
    1. the agent belongs to the active organization;
    2. only that agent's vector store is queried.
- Never share a vector store across organizations or agents.

### Testing multi-tenancy

- Factories for tenant-owned models MUST create/attach an `Organization` and set `organization_id`.
- ALWAYS write at least one isolation test per tenant-owned model asserting that records from organization A are NOT visible when organization B is active.
- When acting as a user in tests, set their `current_organization_id` and ensure the `organization_user` membership exists so the global scope resolves correctly.
- Use the `Role` enum cases when attaching users to organizations in tests — never hardcode role strings.

```php
it('does not leak records across organizations', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $docA = Document::factory()->for($orgA)->create();

    $user = User::factory()->create(['current_organization_id' => $orgB->id]);
    $user->organizations()->attach($orgB, ['role' => Role::Gestor->value]);

    actingAs($user);

    expect(Document::find($docA->id))->toBeNull();
});
```

---

## AI integration (Laravel AI SDK) instructions

- All AI work uses the **Laravel AI SDK (`laravel/ai`)**. Do NOT call the OpenAI HTTP API directly and do NOT add a Python service.
- Each business `Agent` maps to an SDK Agent class. The persona/rules configured by the client become the agent's `instructions`.
- RAG uses the **OpenAI native Vector Store** via the SDK's `FileSearch` tool, passing the agent's `vector_store_id`. NO pgvector.
- All AI calls MUST be wrapped in Service classes under `app/Services/`, for example `app/Services/Ai/`, never invoked directly from Components/Controllers — consistent with the slim-component rule.
- AI responses MUST be **async + streamed** — SSE / Vercel AI data protocol, native to the SDK and Livewire. Never block a synchronous request waiting on the LLM.
- Document ingestion MUST be async: upload → Horizon job → push file to the agent's OpenAI vector store → update `Document.status`. Never ingest inline in the request.
- Use the SDK's testing fakes for any AI-dependent code so tests don't hit the real API.
- Domain enums, such as `DocumentStatus`, `AgentStatus`, `Role`, live in `app/Enums`, are cast on their models, and are used instead of raw strings everywhere.
- The Laravel AI SDK is in beta — pin the version; if SDK behavior/docs are unclear, use Boost `search-docs` first, then context7.

---

## Testing instructions

### Before Writing Tests

1. **Check database schema** — Use `database-schema` tool to understand:
    - Which columns have defaults
    - Which columns are nullable
    - Foreign key relationship names
2. **Verify relationship names** — Read the model file to confirm:
    - Exact relationship method names, not assumed from column names
    - Return types and related models
3. **Test realistic states** — Don't assume:
    - Empty model = all nulls. Check for defaults.
    - `user_id` foreign key = `user()` relationship. It could be `author()`, `employer()`, etc.
    - When testing form submissions that redirect back with errors, assert that old input is preserved using `assertSessionHasOldInput()`.

### Livewire component testing

- Use `Livewire::test()` to test Livewire components.
- Test Form Objects by verifying validation, reset, and data population.
- For full-page components, test the route with `$this->get('/posts/create')` and verify the component is rendered.

Example test for a component with Form Object:

```php
use Livewire\Livewire;

it('can create a post', function () {
    Livewire::test(PostCreate::class)
        ->set('form.title', 'My Post Title')
        ->set('form.content', 'This is the post content.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('posts.index'));

    expect(Post::where('title', 'My Post Title')->exists())->toBeTrue();
});

it('validates required fields', function () {
    Livewire::test(PostCreate::class)
        ->call('save')
        ->assertHasErrors(['form.title', 'form.content']);
});
```

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- laravel/ai (AI) - v0
- laravel/cashier (CASHIER) - v16
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/telescope (TELESCOPE) - v5
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
