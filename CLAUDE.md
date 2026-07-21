# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SchoolTool is a German-language Laravel application providing administrative tools for schools. The primary features include:

- **Registration System (Anmeldetool)**: Allows students/parents to register for school dates/events
- **Tutoring System**: Manages tutoring services
- **User Management**: Multi-role system with super-admin, admin, and regular users
- **School Administration**: Multi-school support with school-specific licensing

## Tech Stack

- **Backend**: Laravel 12/13, PHP 8.2+/8.3
- **Frontend**: Vue 3 with Vuetify 3, Pinia for state management, Vue Router 4
- **Build Tool**: Vite 7 with Tailwind CSS 4
- **Testing**: Pest 4, PHPUnit 12
- **Authentication**: Laravel Sanctum with optional 2FA
- **Custom Package**: `itstudioat/spa` - custom Laravel SPA framework by ITStudio.at
- **AI / MCP Tooling**: Laravel Boost, Vue MCP, Vuetify MCP, Context7

## AI Tool Usage Policy

Claude Code must use the available MCP servers and project skills intentionally.

### Autonomy and Clarification Policy

Claude Code should work independently by default. When a request is clear enough to act on, inspect the project, choose the safest reasonable implementation, make the change, and verify it without stopping for confirmation.

Ask the user only when the decision cannot be recovered from local context and a wrong choice would be risky, destructive, expensive, or would meaningfully change product direction. Prefer stating the assumption you used in the final response over interrupting the work for low-risk details.

Do not ask permission for routine, reversible development actions such as reading files, searching the codebase, editing scoped project files, running formatters, or running targeted tests. Still ask before changing dependencies, deleting user work, making broad architecture changes, touching production data, or performing destructive git operations.

### Tool Priority

Use the following tool priority:

1. **Laravel/backend work** → Laravel Boost / `laravel-boost`
2. **Vue 3 behavior and Composition API** → `vue-docs`
3. **Vuetify 3 components, props, slots, layouts, and UI implementation** → `vuetify-mcp`
4. **Current third-party library docs** → `context7`
5. **Repository-specific patterns** → inspect existing files and sibling components

Do not guess current framework or component APIs when an MCP tool can verify them.

### Laravel Boost

Use Laravel Boost for:

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
- Laravel package documentation
- Laravel conventions

Before making Laravel code changes, use Laravel Boost `search-docs` for version-specific documentation.

Prefer Laravel Boost tools over manual alternatives when available:

- Use `search-docs` before framework/package changes.
- Use `database-schema` before writing migrations, models, or queries.
- Use `database-query` for read-only database inspection.
- Use `get-absolute-url` before giving URLs to the user.

### Vue MCP

Use `vue-docs` for Vue-specific work:

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

When editing Vue code, prefer Vue 3 Composition API and `<script setup>` unless the existing file clearly uses another pattern.

### Vuetify MCP

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

Do not invent Vuetify component names, props, slots, or events. Verify them with `vuetify-mcp`.

Use Vuetify 3 as the primary UI system. Do not introduce another component framework unless the user explicitly asks.

### Context7

Use `context7` for current documentation of third-party frontend libraries:

- Pinia
- Vue Router
- Vite
- Axios
- validation libraries
- chart libraries
- date/time libraries
- utility libraries
- any dependency where current API accuracy matters

Use Context7 when the task depends on current library behavior or API syntax.

## UI Design Instructions

For UI work, improve design using Vuetify-native patterns.

Prioritize:

- clear visual hierarchy
- good spacing
- readable typography
- consistent alignment
- consistent button placement
- responsive layouts
- accessible labels
- useful empty states
- loading states
- error states
- confirmation dialogs for destructive actions
- clear primary and secondary actions
- simple, maintainable layouts

Avoid:

- over-designed layouts
- excessive custom CSS
- mixing UI frameworks
- hidden or ambiguous actions
- dense forms without grouping
- inconsistent spacing
- unclear icon-only buttons without labels, aria labels, or tooltips

Before suggesting custom CSS, first check whether Vuetify provides:

- a component
- a prop
- a utility class
- a density option
- a variant
- a theme token
- a layout primitive

Use Vuetify layout primitives where appropriate:

- `v-app`
- `v-main`
- `v-container`
- `v-row`
- `v-col`
- `v-card`
- `v-sheet`
- `v-toolbar`
- `v-spacer`
- `v-divider`

Use Vuetify form and interaction components where appropriate:

- `v-form`
- `v-text-field`
- `v-textarea`
- `v-select`
- `v-autocomplete`
- `v-checkbox`
- `v-radio-group`
- `v-switch`
- `v-btn`
- `v-icon`
- `v-dialog`
- `v-menu`
- `v-alert`
- `v-snackbar`
- `v-data-table`

When improving a UI, consider:

- What is the primary user action?
- What information should be visually dominant?
- What should happen while data is loading?
- What should the user see when there is no data?
- What should the user see when an error occurs?
- How does this behave on mobile?
- Are labels, errors, and button states accessible?

## Laravel + Vue + Vuetify Integration Rules

For API-backed forms:

- Map Laravel validation errors into Vuetify field error messages.
- Show general server errors using `v-alert` or `v-snackbar`.
- Disable submit buttons during pending requests.
- Preserve user input after validation failures.
- Use clear success and failure feedback.
- Keep authorization enforced server-side.
- Treat frontend permission checks as UX only, not security.
- Prefer Laravel named routes, API resources, and existing API conventions.

When changing frontend code, check whether backend validation, resources, routes, policies, or tests also need updates.

## Development Commands

### Setup

```bash
composer setup
```

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/ai (AI) - v0
- laravel/fortify (FORTIFY) - v1
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
