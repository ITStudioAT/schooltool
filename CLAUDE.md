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
