# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SchoolTool is a German-language Laravel application providing administrative tools for schools. The primary features include:

- **Registration System (Anmeldetool)**: Allows students/parents to register for school dates/events
- **Tutoring System**: Manages tutoring services
- **User Management**: Multi-role system with super-admin, admin, and regular users
- **School Administration**: Multi-school support with school-specific licensing

## Tech Stack

- **Backend**: Laravel 12.34 (PHP 8.2+)
- **Frontend**: Vue 3 with Vuetify 3, Pinia for state management, Vue Router 4
- **Build Tool**: Vite 7 with Tailwind CSS 4
- **Testing**: Pest 4 (PHPUnit wrapper)
- **Authentication**: Laravel Sanctum with optional 2FA
- **Custom Package**: `itstudioat/spa` (v0.3.3) - custom Laravel SPA framework by ITStudio.at

## Development Commands

### Setup

```bash
composer setup  # Runs: install, .env copy, key:generate, migrate, npm ci, npm run build
```

### Development Server

```bash
composer dev    # Runs 3 services concurrently: artisan serve, queue:listen, vite dev
```

Individual services:

```bash
php artisan serve              # Laravel development server
php artisan queue:listen --tries=1  # Queue worker
npm run dev                    # Vite dev server (port 5173)
```

### Testing

```bash
composer test                  # Runs full Pest test suite
php artisan test               # Alternative test command
php artisan test --filter=TestName  # Run specific test
```

Warning: this repository's tests use the dedicated MySQL database `pest_test` from `phpunit.xml` and may drop/recreate it. Do not run destructive test commands unless the repository owner explicitly approves it in the current conversation.

### Building

```bash
npm run build                  # Production build with Vite
```

### Code Quality

```bash
./vendor/bin/pint              # Laravel Pint (code formatting)
```

## Application Architecture

### Three Separate SPAs

The application consists of three independent Single Page Applications:

1. **Admin SPA** (`/admin/*`): Main administration interface with authentication
2. **Homepage SPA** (`/homepage/*`, `/`): Public-facing registration and tutoring interfaces
3. **Application SPA** (`/application/*`): Additional application features

Each SPA has its own:

- Entry point: `resources/js/apps/{admin,homepage,application}.js`
- Router: `resources/routes/{admin,homepage,application}.js`
- Vuetify config: `resources/plugins/{admin,homepage,application}.js`
- Root component: `resources/js/pages/{admin,homepage,application}/App.vue`
- CSS: `resources/css/{admin,homepage,application}.css`

### Backend Structure

Controllers are organized by area:

- `app/Http/Controllers/Admin/*`: Admin-only features
- `app/Http/Controllers/Homepage/*`: Public registration/tutoring
- `app/Http/Controllers/Spa/*`: SPA framework features (routes, install/update)
- `app/Http/Controllers/User/*`: User profile management

Services (`app/Services/*`) contain business logic and are heavily tested. Each service typically has a corresponding test in `tests/Unit/` or `tests/Feature/Services/`.

Key models:

- `User`: With Spatie roles/permissions, Sanctum auth, 2FA support
- `School`: Multi-tenancy support
- `Schoolyear`: Academic year management
- `Register`: Registration events
- `RegisterDate`: Specific dates for registrations
- `RegisterDateBooking`: User bookings for dates
- `Licence`/`SchoolLicence`: School licensing
- `SchoolTool`: New tutoring feature model

### Frontend Structure

**Stores** (`resources/js/stores/`):

- Use Pinia for state management
- `ResourceStore.js`: Factory pattern for creating standard CRUD stores
- Separate stores by SPA: `admin/*`, `homepage/*`, `application/*`
- `NotificationStore.js`: Global notification system

**Components**:

- Shared components in `resources/js/pages/components/`
- Custom components: `ItsTable`, `ItsGridBox`, `ItsMenuButton`, `ItsOverlayBox`, `ItsInfoBox`, `ItsNotification`, `FileUpload`, `SearchField`, `Pagination`
- Vuetify components are auto-imported

**Naming Convention**: German is used throughout (routes, variables, UI text)

### API Architecture

All API routes in `routes/api.php` follow `/api/{area}/{resource}` pattern:

- Protected by global and API throttling (600 req/min per user)
- Most admin routes require `auth:sanctum` middleware
- Role-based access via `api-allowed` middleware
- CSRF protection via Sanctum

Public routes:

- `/api/homepage/register/*`: Registration system
- `/api/homepage/tutoring/*`: Tutoring system

Admin routes grouped by required roles:

- `api-allowed:user,admin,register_admin`: User profile operations
- `api-allowed:admin,register_admin`: School/register management
- `api-allowed:admin`: User/role administration

### Custom SPA Package (`itstudioat/spa`)

This package provides:

- Base authentication views and routes
- Role and permission management (via Spatie)
- Email verification system
- Configuration in `config/spa.php`
- Views in vendor package: `spa::admin`, `spa::homepage`, `spa::application`

## Testing Strategy

The project uses Pest for testing with good coverage:

- **Unit tests**: Service classes (`tests/Unit/*ServiceTest.php`)
- **Feature tests**: Controllers (`tests/Feature/*ControllerTest.php`)
- **Jobs tests**: Queue jobs (`tests/Unit/*JobTest.php`)
- Test setup in `tests/Pest.php` and `tests/TestCase.php`

Note: Feature tests extend `Tests\TestCase` which provides database access.
Additional safety rule: never run `php artisan test`, `composer test`, `pest`, `phpunit`, or `php artisan migrate:fresh` here without explicit user approval, because the configured test database can be reset during test execution.

## Configuration Notes

### Throttling

Configured in `config/spa.php`:

- Web: 200 req/min per user
- API: 600 req/min per user
- Global: 400 req/min total

### Token Management

- Email verification tokens expire after 120 minutes (configurable)
- Sanctum tokens for API authentication
- Optional 2FA with time-limited tokens

### Multi-tenancy

Users belong to a school (`school_id`) and can switch schools if they have permissions. Active school/schoolyear/register stored in user session.

## Important Development Notes

- German is the primary language for code comments, UI text, and variable names
- Windows development environment (note path separators in configs)
- Uses queues for PDF generation and email sending
- For document extraction, parsing, or conversion tasks, prefer the already installed packages when they fit the problem: `phpoffice/phpword`, `smalot/pdfparser`, `spatie/laravel-pdf`, and `spatie/pdf-to-text`.
- File uploads handled via `FileUploadService`
- The project excludes `vendor/itstudioat/spa/src/Facades/Spa.php` from autoloading
- Source maps enabled in production builds for debugging
- Puppeteer configured in `.puppeteerrc.cjs` for PDF generation

## Recent Development (from README)

Current work on `tutoring` branch:

- Welcome screen for users (18.11.2025)
- User management for super-admin with role selection, CRUD operations
- Extensive Pest test coverage added (v3.2.10-3.2.11)
- Registration system with user overview and cleanup features
- Optional 2FA authentication (v3.2.8)

## Database

Migrations in `database/migrations/`. Recent additions include `school_tools` table for tutoring feature.

Migration safety rule: this codebase must tolerate existing customer databases. New table migrations should guard with `Schema::hasTable(...)`, additive column migrations should guard with `Schema::hasColumn(...)`, and `up()` migrations must not drop/recreate populated tables as a shortcut.

## File Aliases

Vite configured with `@` alias pointing to `resources/js/` for cleaner imports.

## ABA Subproject Activation

If the user says `ABA`, `we are working on ABA`, or otherwise clearly indicates that the current task belongs to the ABA subproject, treat that as an activation signal for the ABA workflow.

When ABA is activated, do not stay at the level of abstract analysis, generic advice, or detached specification if implementation in the repository is possible. First inspect the relevant repository context, then make concrete changes in the existing Laravel/Vue codebase.

### ABA Working Mode

When ABA is active, follow this order:

1. Inspect the existing repository structure relevant to the task.
2. Identify the Laravel backend and Vue frontend integration points.
3. Reuse existing architecture, conventions, services, components, stores, and rendering patterns.
4. Implement concrete code changes in the repository.
5. Add or update tests.
6. Run the minimum relevant checks.
7. Report changed files, implemented logic, verification steps, and any remaining limitations.

### ABA Baseline Assumptions

Unless the user explicitly says otherwise, assume the following for ABA tasks:

- ABA work happens inside this existing Laravel/Vue application.
- Backend work must fit the current Laravel 12 structure already used in the repository.
- Frontend work must fit the existing Vue 3 / Vuetify / Pinia patterns already used in the repository.
- Do not create a parallel architecture for ABA unless explicitly requested.
- Prefer extending existing import, parsing, normalization, rendering, preview, asset, or document-processing flows over inventing new ones.
- Do not answer with only a conceptual target structure if the repository can be changed directly.

### ABA Typical Task Areas

ABA tasks commonly involve one or more of the following:

- document import or extraction
- normalization of imported text
- correction of OCR or conversion artifacts
- Pandoc-compatible transformation
- preservation or repair of semantic formatting
- structured content preparation for backend/frontend use
- Vue preview or rendering of imported content
- UI consistency with backend-normalized content
- extraction or handling of page-related assets such as logos
- regression-safe fixes with tests

### ABA Repository Check

Before coding for ABA, always check:

- where relevant Laravel controllers, services, actions, DTOs, resources, models, or jobs are located
- where relevant Vue pages, components, stores, or composables are located
- whether there is existing logic for document import, parsing, Pandoc, OCR cleanup, preview rendering, or asset handling
- which tests already cover nearby functionality
- whether the change belongs primarily in backend normalization, shared transformation logic, persistence, or UI rendering

### ABA Implementation Rules

When ABA is active:

- Prefer implementation over explanation.
- Do not stop at a mock JSON structure, sample output, or conceptual description if code changes are possible.
- Integrate into existing application flow instead of creating isolated one-off logic.
- Keep business and normalization logic out of Vue when it belongs in backend or shared transformation layers.
- Keep frontend rendering consistent with backend output.
- Make the smallest production-appropriate change that cleanly solves the problem.
- Preserve existing behavior outside the affected ABA case as much as possible.

### ABA Content Processing Rules

For ABA text/document-processing tasks:

- Preserve the semantic structure of the original source whenever possible.
- Prefer the original source over faulty imported, OCR, or Pandoc-converted output.
- Remove false formatting introduced by OCR/import/conversion.
- Keep only formatting that is actually present in the source material.
- Treat section markers or subhead-like labels consistently.
- Remove artificial line breaks inside continuous sentences.
- Preserve real paragraph boundaries.
- Keep citations and references as continuous units.
- Ensure normalized backend output and Vue rendering remain consistent.
- Add regression coverage for broken formatting cases once fixed.

### ABA Done Definition

An ABA task is not done until, where applicable:

- code has been changed in the existing Laravel/Vue codebase
- the solution follows existing project conventions
- affected tests have been added or updated
- the minimum relevant tests/checks have been run
- formatting has been applied where required
- the final response includes:
    - changed files
    - what was implemented
    - what was verified
    - any remaining limitations or follow-up items

### ABA Guardrails

- Do not replace repository-specific pipelines with generic text rewriting if the project already has structured processing.
- Do not silently invent document structure that is unsupported by the existing code.
- Do not move backend/business normalization into the UI without a strong reason.
- Do not add documentation files unless explicitly requested.
- Do not change dependencies without approval.

### ABA Trigger Interpretation

The standalone message `ABA` should be interpreted as:
“Activate ABA project rules, inspect the relevant repository context first, and then implement changes in the existing codebase.”

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- vue (VUE) - v3
- @laravel/echo-vue (ECHO_VUE) - v2
- laravel-echo (ECHO) - v2

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `echo-vue-development` — Develops real-time broadcasting in Vue applications with Laravel Echo. Activates when configuring Echo in Vue (configureEcho); using composables (useEcho, useEchoPublic, useEchoPresence, useEchoModel, useEchoNotification, useConnectionStatus); listening for broadcast events in Vue components; implementing client events (whisper) in Vue; or when the user mentions Echo with Vue, real-time Vue composables, or broadcasting in Vue components.
- `echo-development` — Develops real-time broadcasting with Laravel Echo. Activates when setting up broadcasting (Reverb, Pusher, Ably); creating ShouldBroadcast events; defining broadcast channels (public, private, presence, encrypted); authorizing channels; configuring Echo; listening for events; implementing client events (whisper); setting up model broadcasting; broadcasting notifications; or when the user mentions broadcasting, Echo, WebSockets, real-time events, Reverb, or presence channels.
- `ai-sdk-development` — Builds AI agents, generates text and chat responses, produces images, synthesizes audio, transcribes speech, generates vector embeddings, reranks documents, and manages files and vector stores using the Laravel AI SDK (laravel/ai). Supports structured output, streaming, tools, conversation memory, middleware, queueing, broadcasting, and provider failover. Use when building, editing, updating, debugging, or testing any AI functionality, including agents, LLMs, chatbots, text generation, image generation, audio, transcription, embeddings, RAG, similarity search, vector stores, prompting, structured output, or any AI provider (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).
- `laravel-pdf` — Generate PDFs from Blade views or HTML using spatie/laravel-pdf. Covers creating, formatting, saving, downloading, and testing PDFs with the Browsershot, Cloudflare, or DOMPDF driver.
- `laravel-api` — Build production-grade Laravel REST APIs using opinionated architecture patterns with Laravel best practices. Use when building, scaffoling, or reviewing Laravel APIs with specifications for stateless design, versioned endpoints, invokable controllers, Form Request DTOs, Action classes, JWT authentication, and PSR-12 code quality standards. Triggers on "build a Laravel API", "create Laravel endpoints", "add API authentication", "review Laravel API code", "refactor Laravel API", or "improve Laravel code quality".

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
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

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

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== laravel/ai rules ===

## Laravel AI SDK

- This application uses the Laravel AI SDK (`laravel/ai`) for all AI functionality.
- Activate the `developing-with-ai-sdk` skill when building, editing, updating, debugging, or testing AI agents, text generation, chat, streaming, structured output, tools, image generation, audio, transcription, embeddings, reranking, vector stores, files, conversation memory, or any AI provider integration (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

</laravel-boost-guidelines>
