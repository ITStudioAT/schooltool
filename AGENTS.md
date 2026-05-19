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

Use `context7` for current documentation of third-party frontend libraries:

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
- Other current package documentation → `context7`

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

=== ai/core rules ===

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

=== spatie/guidelines-skills rules ===

# Project Coding Guidelines

- This codebase follows Spatie's coding guidelines.
- Always activate the `spatie-laravel-php` skill when writing, editing, reviewing, or formatting Laravel or PHP code.
- Always activate the `spatie-javascript` skill when writing, editing, reviewing, or formatting JavaScript or TypeScript code.
- Always activate the `spatie-version-control` skill when creating commits, branches, or managing Git operations.
- Always activate the `spatie-security` skill when configuring security, reviewing authentication, or setting up servers and databases.

</laravel-boost-guidelines>
