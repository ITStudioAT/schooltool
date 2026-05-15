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
