# AGENTS.md

## Project and priorities

Schooltool uses Laravel, Vue 3, Vuetify 3, Pest, Pinia and Laravel AI SDK.
Read installed versions from composer.lock/package.json when an API depends on them.
Follow existing sibling files and reuse existing components, services and tests.

The operator prioritizes short turnaround and proportional verification.
The development workflow below supersedes blanket requirements to research,
delegate, build or run tests before every edit, including generated guidelines.
A local change and a published release are separate tasks.

## Fast development workflow

Choose the smallest sufficient path from the actual change, not file count alone:

| Change | Work and verification |
| --- | --- |
| Text, alignment, spacing, color or an existing CSS/Vuetify class | One agent; edit the relevant file directly and check the result in the existing preview when available. Vite/HMR already compiles the changed component; no separate test command is required. If there is no usable preview, use a focused compilation check only when useful. No PHP suite, full UI suite, coverage, new implementation-mirroring test or release build. |
| Local component/store/controller behavior | Read its direct callers and applicable rules. Add/update a regression test when behavior changes; run the affected test file(s), without coverage. |
| Shared behavior, authentication, permissions, database, queues or deployment tooling | Inspect the affected boundaries and run focused regression/integration tests. Broaden only for a concrete uncovered risk. |
| Explicit full validation, preview publication or release | Follow the existing checked Git/release workflow. Keep its mandatory gates and database ownership checks. |

- Start with a targeted search and the relevant files. Avoid repository-wide audits for local changes.
- Read each relevant skill/rule once per task; reuse known context.
- Use one agent by default. Delegate only a concrete independent subtask whose benefit exceeds coordination cost; no standing reviewer roster.
- Consult framework documentation when adding/changing framework behavior or when an API is uncertain. Existing-class/style/text edits do not require documentation lookup.
- Pick the smallest useful verification before editing; for cosmetic changes this can be the existing preview alone. Once it passes, stop unless a new change or failure justifies another check.
- Do not run the same suite locally and in CI merely for reassurance.
- Do not run full coverage, all PHP/UI tests or E2E for a cosmetic change.
- Do not add tests that merely assert an incidental CSS class or duplicate the implementation. Prefer observable behavior for functional changes.
- Use the existing Vite dev server/HMR. Rebuild only for production artifacts, build-specific changes/errors, or when no usable preview exists.
- Do not reinstall dependencies, regenerate unchanged routes, restart healthy services, clear broad caches or create a worktree for every small edit.
- Deliver a short result: what changed, the focused check, and a real limitation if any.
- If the scope grows beyond a small fix, explain the concrete reason rather than silently expanding the task.

## Authorization and data safety

Proceed without repeated confirmation for ordinary reads, searches, scoped edits,
focused tests, formatting and fixes caused by the requested change.

Explicit authorization is required for destructive database changes, deleting
important files/tests, dependency changes, environment/secrets changes,
production deployment, new base directories/major subsystems, broad unrelated
rewrites or reducing authentication/authorization protections.
Existing explicit authorization persists; do not ask again for the same action.

- Database inspection is read-only unless mutation is explicitly requested.
- Never run tests against production, preview or the operator's normal database.
- Use the existing owned disposable database workflow and receipts where required.
- Keep authorization enforced server-side; frontend visibility is only UX.
- Protect secrets, private data and credentials.
- Preserve unrelated changes, feature reservations, previews and recovery refs.
- Do not publish, deploy or run migrations merely because a local edit is complete.

## Repository rules and skills

Before editing, use `.ai/rules/index.md` to find rules covering the touched paths.
Read matching files and perform one targeted keyword search for relevant traps.
Do not read every area rule or unrelated subsystem. Rules already read remain valid
unless changed. Record durable project decisions with Boost `record-rule`.

Activate only skills relevant to the work:
- PHP/Laravel: `spatie-laravel-php`, `laravel-best-practices`.
- JavaScript/TypeScript/Vue: `spatie-javascript`.
- Creating/editing Pest tests: `pest-testing`.
- Git commits/branches/publication: `spatie-version-control`.
- Security/authentication/database configuration: `spatie-security`, plus auth skills as applicable.
- AI features: the installed `ai-sdk-development` skill (also described as developing-with-ai-sdk).
- Frontend calls to backend routes/controllers: `wayfinder-development`.
- Other domain skills only when their domain is actually in scope.

## Documentation and tools

Use available domain tools intentionally, with queries scoped to the uncertainty:
- Laravel/package behavior: Laravel Boost `search-docs`.
- Schema/migrations/models/queries: inspect with `database-schema` first.
- Database inspection: read-only `database-query`.
- Vue APIs/reactivity: Vue MCP.
- New/non-trivial Vuetify API usage: Vuetify MCP; never invent props/slots/events.
- Other library APIs: Context7 if available.
- Resolve application URLs with Boost `get-absolute-url` when available.

Do not repeatedly rediscover tools or reload documentation already sufficient
for the task. If a tool is unavailable, use installed source or official docs
and state only a limitation that affects the result.

## Implementation conventions

- Make the smallest scoped patch; no unrelated refactors or documentation files.
- Check siblings before creating files; stay within the existing structure.
- Use descriptive names, explicit PHP parameter/return types and braces.
- Prefer constructor property promotion, PHPDoc and useful array shapes.
- Follow existing enums, Eloquent, validation, authorization and resource patterns.
- Use Artisan generators for Laravel classes/tests, with `--no-interaction`.
  Check `--help` for unfamiliar options.
- Prefer named routes and Wayfinder generated frontend route functions.
- Use factories/states in tests. Do not create database records through Tinker
  when proper tests cover the behavior.
- Keep Vue Composition API, Pinia and Vuetify patterns consistent with siblings.
- Use Laravel AI SDK for this application's AI functionality.

## Focused checks

- PHP: `php artisan test --compact path/to/Test.php` or a specific filter.
- UI: `npm run test:ui -- path/to/Component.test.ts`; the filename is mandatory
  for a focused run. Do not accidentally invoke the full suite.
- PHP formatting after PHP changes: `vendor/bin/pint --dirty --format agent`.
- Before a commit: `php scripts/check-encoding.php` (also enforced by the hook).
- Full local checks are only for explicitly requested full validation, not ordinary publication.
- A successful check need not be rerun unless relevant code/configuration changes.
- Do not hide failures, delete tests, skip failed cases or call unverified work tested.

## Git and publication

Follow the existing gitsave/gitpreview/gitrelease/gitdeploy helpers.
Use `codex/` for task branches unless the user requests another name.
Do not force-push, rewrite published history or change dependencies without scope.

- Main gitsave builds a source-bound release and starts nonblocking background CI;
  do not add optional `-Full` unless requested or justified by a concrete issue.
- Preview/feature-release require build and package preflight, not full local suites.
- Production deployment requires explicit intent, the bounded isolated runtime smoke,
  pinned source/frontend identity and the existing LIVE confirmation. Full CI is
  asynchronous and must not block save, preview, release or live deployment.
- Never bypass a gate with bare deployment scripts or reuse unrelated test logs.
- Destructive release migrations require the authorized target and a validated backup.
- Keep tests of local changes separate from publication; do not start publication
  just to verify a small edit.

## Windows and encoding

Development stays Windows-native; source files are UTF-8 without BOM.
- PowerShell 5.1 must read text with `Get-Content -Encoding UTF8`.
- Use `apply_patch` for source edits, not PowerShell output cmdlets.
- If text appears corrupted, reread explicitly as UTF-8; never copy mojibake.
- Verify exact paths before recursive deletion; never follow junctions into shared data.
- Preserve owned-test receipts and use their existing cleanup helpers.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

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

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel-ai/core rules ===

## Laravel AI SDK

- This application uses the Laravel AI SDK (`laravel/ai`) for all AI functionality.
- Activate the `developing-with-ai-sdk` skill when building, editing, updating, debugging, or testing AI agents, text generation, chat, streaming, structured output, tools, image generation, audio, transcription, embeddings, reranking, vector stores, files, conversation memory, or any AI provider integration (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

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

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== spatie/guidelines-skills/core rules ===

# Project Coding Guidelines

- This codebase follows Spatie's coding guidelines.
- Always activate the `spatie-laravel-php` skill when writing, editing, reviewing, or formatting Laravel or PHP code.
- Always activate the `spatie-javascript` skill when writing, editing, reviewing, or formatting JavaScript or TypeScript code.
- Always activate the `spatie-version-control` skill when creating commits, branches, or managing Git operations.
- Always activate the `spatie-security` skill when configuring security, signing commits, reviewing authentication, or setting up servers and databases.

</laravel-boost-guidelines>
