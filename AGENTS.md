# AGENTS.md

## Project Context

This project is a Laravel, Vue 3, and Vuetify 3 application.

Main stack:

- PHP 8.3
- Laravel 13
- Laravel Boost v2
- Laravel MCP
- Laravel AI SDK
- Laravel Sanctum
- Pest v4
- PHPUnit v12
- Vue 3
- Vuetify 3
- Laravel Echo / Echo Vue

Follow these instructions for every task in this repository.

---

## Operating Mode

Work efficiently, directly, and with minimal unnecessary confirmation prompts.

The operator prefers that Codex proceeds with direct actions when the task intent is clear.

Do not repeatedly ask for confirmation before ordinary, safe development actions such as:

- reading files
- searching files
- inspecting routes
- inspecting schemas
- running read-only database queries
- using MCP documentation tools
- creating or editing code within the requested scope
- creating or updating tests
- running targeted tests
- running formatters
- fixing errors clearly caused by the requested change

Ask for confirmation only when the action is potentially destructive, irreversible, security-sensitive, or outside the requested scope.

Always ask before:

- deleting database records
- dropping tables
- truncating tables
- running destructive migrations on non-local data
- deleting important files
- changing dependencies
- changing environment files or secrets
- performing production deployment actions
- making broad architectural rewrites not explicitly requested
- creating new base directories or major new subsystems
- removing tests
- changing authentication or authorization behavior in a way that could reduce security

Database inspection must be read-only unless the user explicitly requests a mutation.

Never perform destructive database mutations, especially deletes, without explicit task intent.

### Windows UTF-8 Safety

This repository uses UTF-8 without BOM. Local development stays Windows-native.

- Windows PowerShell 5.1 must read text files with `Get-Content -Encoding UTF8`.
- Never copy text from a terminal when umlauts or punctuation appear with the typical mojibake prefixes U+00C3, U+00C2, U+00E2, U+00F0, or the replacement character U+FFFD; reread the source explicitly as UTF-8 first.
- Continue to use `apply_patch` for source edits. Do not rewrite source files with PowerShell output cmdlets.
- Run `php scripts/check-encoding.php` before committing. The repository hook enforces this automatically.

---

## Multi-Agent Workflow

Codex may use sub-agents whenever they are useful, without asking for confirmation first.

Prefer multi-agent work for complex tasks when Codex subagents are available.
If the operator asks to use sub-agents or says not to ask for routine development work, treat that as an explicit preference for future complex investigations and proceed without additional confirmation.

Complex tasks include:

- debugging across multiple files
- Laravel/Vue/Pinia/Vuetify data-flow analysis
- permission or security review
- route/controller/store/component investigations
- larger refactorings
- test-suite expansion
- framework-specific changes requiring documentation lookup
- tasks involving Laravel Boost, Vue MCP, Vuetify MCP, or current package documentation
- AI SDK implementation or debugging
- queue, event, policy, gate, middleware, or API-resource work

For small, local, obvious changes, a single-agent workflow is acceptable.

When using multiple agents, prefer this structure:

1. `explorer`
    - Find relevant files, routes, controllers, models, policies, requests, resources, Vue components, Pinia stores, router guards, jobs, events, tests, and related configuration.
    - Do not change code.

2. `docs_researcher`
    - Use Laravel Boost, Vue MCP, Vuetify MCP, and other available documentation tools.
    - Confirm current, version-specific framework behavior.
    - Do not change code.

3. `laravel_reviewer`
    - Review backend code, Laravel conventions, validation, policies, gates, middleware, Eloquent usage, resources, queues, events, jobs, and tests.
    - Do not change code.

4. `vue_reviewer`
    - Review Vue 3, Composition API, Pinia, Vue Router, Vuetify usage, props, emits, slots, forms, layouts, dialogs, and reactivity.
    - Do not change code.

5. `security_reviewer`
    - Review authentication, authorization, Spatie permissions, server-side enforcement, data exposure, validation, mass assignment, database safety, and risky user input.
    - Do not change code.

6. `test_reviewer`
    - Identify missing, weak, or broken Pest/PHPUnit tests.
    - Suggest the smallest useful test coverage.
    - Do not delete tests.

7. `worker`
    - Implement only the agreed or clearly requested changes.
    - Make the smallest safe patch.
    - Avoid unrelated refactors.

After subagents finish, synthesize their findings before changing code.

When practical, wait for all relevant subagents before implementing changes.

If subagents are not available in the current Codex environment, still follow the same logical workflow internally:

- explore
- check documentation
- review risks
- implement minimally
- test
- summarize

Do not use multi-agent work as an excuse to over-engineer simple tasks.

---

## Required MCP / Tool Usage

Use the available MCP servers intentionally.

### Laravel

Use `laravel-boost` for Laravel-specific work:

- routes
- controllers
- form requests
- validation
- Eloquent models
- migrations
- policies
- gates
- queues
- events
- API resources
- tests
- Laravel conventions
- Laravel package documentation

Before making Laravel code changes, use Laravel Boost `search-docs` for version-specific documentation.

Prefer Laravel Boost tools over manual alternatives when available:

- Use `search-docs` before framework/package changes.
- Use `database-schema` before writing migrations, models, or queries.
- Use `database-query` for read-only database inspection.
- Use `get-absolute-url` before giving URLs to the user.

### Vue

Use `vue-mcp` for Vue-specific work:

- Vue 3 Composition API
- `<script setup>`
- refs
- computed values
- watchers
- props
- emits
- component patterns
- lifecycle hooks
- reactivity
- slots
- composables

Do not guess Vue APIs when the answer depends on current documentation.

### Vuetify

Use `vuetify-mcp` for Vuetify-specific work:

- Vuetify 3 components
- component props
- slots
- events
- layouts
- forms
- validation UI
- dialogs
- menus
- navigation
- data tables
- theming
- spacing
- density
- variants
- responsive design

Do not invent Vuetify component names, props, slots, or events. Check `vuetify-mcp` before writing or changing Vuetify component code.

### General frontend libraries

Use `context7` if it is available for current documentation of third-party frontend libraries:

- Pinia
- Vue Router
- Vite
- Axios
- validation libraries
- chart libraries
- date/time libraries
- utility libraries
- any dependency where API accuracy matters

### Tool choice

Use this priority:

- Laravel/backend behavior → `laravel-boost`
- Vue behavior → `vue-mcp`
- Vuetify components/API/design implementation → `vuetify-mcp`
- Other current package documentation → `context7`, if available

If a task touches both frontend and backend, use the relevant tool for each part.

---

## Skills Activation

This project has domain-specific skills available in `**/skills/**`.

Activate the relevant skill whenever working in that domain. Do not wait until stuck.

Always activate:

- `spatie-laravel-php` when writing, editing, reviewing, or formatting Laravel or PHP code.
- `spatie-javascript` when writing, editing, reviewing, or formatting JavaScript, TypeScript, Vue, or frontend code.
- `spatie-version-control` when creating commits, branches, or managing Git operations.
- `spatie-security` when configuring security, reviewing authentication, authorization, servers, dependencies, or databases.
- `developing-with-ai-sdk` when working with Laravel AI SDK features.

---

## General Coding Rules

- Follow existing code conventions in this application.
- Before creating new files or components, check sibling files for structure, naming, and patterns.
- Reuse existing components, composables, helpers, requests, resources, tests, and services where appropriate.
- Use descriptive variable and method names, for example `isRegisteredForDiscounts`, not `discount`.
- Make the smallest safe change.
- Do not perform unrelated refactors.
- Do not rewrite files unnecessarily.
- Do not change dependencies without user approval.
- Do not create documentation files unless explicitly requested by the user.
- Keep replies concise and focused on what matters.
- Prefer concrete file paths, class names, method names, and test names in explanations.
- When something cannot be verified, say so clearly.

---

## Laravel Rules

Use Laravel conventions.

- Use `php artisan make:` commands to create Laravel files.
- Use `php artisan list` to discover commands.
- Use `php artisan [command] --help` before using unfamiliar command options.
- Pass `--no-interaction` to Artisan commands.
- Prefer named routes and the `route()` function.
- For APIs, default to Eloquent API Resources and API versioning unless the existing project uses another convention.
- Keep authorization enforced server-side.
- Frontend permission checks are UX only, not security.
- When creating models, create useful factories and seeders unless the existing project convention differs.
- Ask before creating extra related files beyond the requested scope.

### Artisan

Useful commands:

```bash
php artisan route:list
php artisan route:list --method=GET
php artisan route:list --name=users
php artisan route:list --path=api
php artisan config:show app.name
php artisan config:show database.default
```

---

## Testing and Verification

Every code change must be tested programmatically when practical.

Prefer the smallest useful verification:

- Run focused Pest/PHPUnit tests for the changed area.
- Add or update a test when the behavior is new or previously untested.
- Run only broader test suites when necessary.
- Run formatters after changing PHP files.

Use:

```bash
php artisan test --compact
php artisan test --compact --filter=testName
vendor/bin/pint --dirty --format agent
```

Do not delete tests without approval.

Do not create throwaway verification scripts when proper tests cover the behavior.

---

## Frontend Rules

- Follow existing Vue, Pinia, Vue Router, Vite, and Vuetify conventions.
- Prefer `<script setup>` when existing files use it.
- Reuse existing components and composables.
- Do not invent Vuetify props, slots, events, or component names.
- Check Vuetify MCP before changing non-trivial Vuetify UI.
- If a frontend change is not visible, consider that the user may need to run:

```bash
npm run build
npm run dev
composer run dev
```

Ask the user to run these only when necessary.

---

## Security and Permissions

Security is primarily server-side.

Frontend permission checks are only UX hints.

Always review:

- policies
- gates
- Spatie permissions
- middleware
- request validation
- mass assignment
- model casts and fillable/guarded properties
- API resource exposure
- ownership checks
- authenticated user assumptions
- admin-only behavior
- database mutations

Do not weaken authorization without explicit user instruction.

Do not expose secrets, tokens, credentials, or private environment values.

---

## AI SDK Rules

This application uses the Laravel AI SDK (`laravel/ai`) for AI functionality.

Activate `developing-with-ai-sdk` when building, editing, updating, debugging, or testing:

- AI agents
- text generation
- chat
- streaming
- structured output
- tools
- image generation
- audio
- transcription
- embeddings
- reranking
- vector stores
- files
- conversation memory
- provider integrations such as OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, or OpenRouter

---

## Response Style

Keep responses concise, technical, and action-oriented.

For completed work, summarize:

- what changed
- which files changed
- which tests or checks ran
- any remaining risk or follow-up needed

For investigation work, summarize:

- relevant files found
- root cause or likely cause
- evidence
- recommended fix
- tests to run

Do not provide long generic explanations when the code or task result is more important.

---

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystem packages and versions are below. You are an expert with them all. Ensure you abide by these specific packages and versions.

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

## Conventions

- You must follow all existing code conventions used in this application.
- When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work.
- Unit and feature tests are more important.

## Application Structure and Architecture

- Stick to the existing directory structure.
- Do not create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user does not see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`.

## Documentation Files

- Only create documentation files if explicitly requested by the user.

## Replies

- Be concise in explanations.
- Focus on what is important rather than explaining obvious details.

---

## Laravel Boost Tools

Laravel Boost is an MCP server with tools designed specifically for this application.

Prefer Boost tools over manual alternatives like shell commands or file reads when available.

- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations, models, or queries.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs.
- Always use `get-absolute-url` before sharing a URL with the user.

---

## Searching Documentation

Always use `search-docs` before making Laravel framework or Laravel package code changes.

Do not skip this step.

It returns version-specific documentation based on installed packages automatically.

Pass a `packages` array to scope results when you know which packages are relevant.

Use multiple broad, topic-based queries, for example:

```text
rate limiting
routing rate limiting
routing
```

Expect the most relevant results first.

Do not add package names to queries because package information is already shared.

Use:

```text
test resource table
```

not:

```text
filament 4 test resource table
```

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both `rate` and `limit`.
2. Use quoted phrases for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

---

## Artisan

Run Artisan commands directly via the command line.

Use:

```bash
php artisan route:list
php artisan route:list --method=GET
php artisan route:list --name=users
php artisan route:list --path=api
php artisan route:list --except-vendor
php artisan route:list --only-vendor
php artisan config:show app.name
php artisan config:show database.default
```

Use `php artisan list` to discover available commands.

Use `php artisan [command] --help` to check parameters.

---

## Tinker

Execute PHP in app context for debugging and testing code only when appropriate.

Do not create models without user approval.

Prefer tests with factories instead.

Prefer existing Artisan commands over custom tinker code.

Always use single quotes to prevent shell expansion:

```bash
php artisan tinker --execute 'User::where("active", true)->count();'
```

---

## PHP Rules

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion:

```php
public function __construct(public GitHub $github)
{
}
```

- Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters:

```php
function isAccessible(User $user, ?string $path = null): bool
```

- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments.
- Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

---

## Deployment

Laravel can be deployed using Laravel Cloud, which is the fastest way to deploy and scale production Laravel applications.

Do not perform production deployment actions without explicit user instruction.

---

## Test Enforcement

Every change must be programmatically tested.

Write a new test or update an existing test when behavior changes.

Run the affected tests to make sure they pass.

Run the minimum number of tests needed to ensure code quality and speed.

Use:

```bash
php artisan test --compact
php artisan test --compact --filter=testName
```

Do not delete tests without approval.

---

## Laravel AI SDK

This application uses the Laravel AI SDK (`laravel/ai`) for all AI functionality.

Activate the `developing-with-ai-sdk` skill when building, editing, updating, debugging, or testing AI agents, text generation, chat, streaming, structured output, tools, image generation, audio, transcription, embeddings, reranking, vector stores, files, conversation memory, or any AI provider integration.

---

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files such as migrations, controllers, models, requests, resources, policies, jobs, events, and tests.
- Use `php artisan list` to discover available Artisan commands.
- Use `php artisan [command] --help` before using unfamiliar command options.
- If creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands.
- Pass the correct options to ensure correct behavior.

### Model Creation

When creating new models, create useful factories and seeders too unless the existing project convention differs.

Ask the user before creating extra related files beyond the requested scope.

### APIs and Eloquent Resources

For APIs, default to using Eloquent API Resources and API versioning unless existing API routes use another convention.

### URL Generation

When generating links to other pages, prefer named routes and the `route()` function.

### Testing

When creating models for tests, use factories.

Check if the factory has custom states before manually setting up the model.

Use Faker methods such as:

```php
$this->faker->word()
fake()->randomDigit()
```

Follow existing conventions whether to use `$this->faker` or `fake()`.

When creating tests, use:

```bash
php artisan make:test --pest SomeFeatureTest
php artisan make:test --pest SomeUnitTest --unit
```

Most tests should be feature tests.

### Vite Error

If you receive this error:

```text
Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest
```

Run or ask the user to run:

```bash
npm run build
npm run dev
composer run dev
```

---

## Laravel Pint Code Formatter

If you modified any PHP files, run:

```bash
vendor/bin/pint --dirty --format agent
```

before finalizing changes.

Do not run:

```bash
vendor/bin/pint --test --format agent
```

Simply run Pint to fix formatting issues.

---

## Pest

This project uses Pest for testing.

Create tests with:

```bash
php artisan make:test --pest SomeFeatureTest
php artisan make:test --pest SomeUnitTest --unit
```

The test name should not include the test-suite directory.

Use:

```bash
php artisan make:test --pest SomeFeatureTest
```

not:

```bash
php artisan make:test --pest Feature/SomeFeatureTest
```

Run tests with:

```bash
php artisan test --compact
php artisan test --compact --filter=testName
```

Do not delete tests without approval.

---

## Spatie Guidelines and Skills

This codebase follows Spatie's coding guidelines.

Always activate:

- `spatie-laravel-php` when writing, editing, reviewing, or formatting Laravel or PHP code.
- `spatie-javascript` when writing, editing, reviewing, or formatting JavaScript or TypeScript code.
- `spatie-version-control` when creating commits, branches, or managing Git operations.
- `spatie-security` when configuring security, reviewing authentication, authorization, servers, dependencies, or databases.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

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

## Project Rules

- This project keeps committed, area-grouped rules in `.ai/rules` (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

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

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

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

=== spatie/guidelines-skills/core rules ===

# Project Coding Guidelines

- This codebase follows Spatie's coding guidelines.
- Always activate the `spatie-laravel-php` skill when writing, editing, reviewing, or formatting Laravel or PHP code.
- Always activate the `spatie-javascript` skill when writing, editing, reviewing, or formatting JavaScript or TypeScript code.
- Always activate the `spatie-version-control` skill when creating commits, branches, or managing Git operations.
- Always activate the `spatie-security` skill when configuring security, reviewing authentication, or setting up servers and databases.

</laravel-boost-guidelines>
