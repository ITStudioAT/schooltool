## Laravel Boost (MCP) default behavior

For any Laravel/project questions, DO NOT guess. Always inspect the real project state first using Laravel Boost MCP tools and/or local project commands.

## Encoding and text handling

- Treat the repository as UTF-8 without BOM.
- Preserve German umlauts and other non-ASCII characters exactly as stored.
- Never replace correct characters with placeholders, replacement characters, or similar mojibake.
- If text appears corrupted, only repair strings that are clearly reconstructable.

Use Boost tools by default for:

- routing (route definitions, middleware stacks, route groups)
- configuration/env/runtime state
- container bindings and service providers
- middleware registration and aliases
- database/migrations/models/policies
- auth (Sanctum, guards, policies)

When using Boost Tinker, ALWAYS force output:

- prefer `dump(<expr>)` / `var_dump(<expr>)`
- or `echo <expr>;`
  Never send bare expressions because the wrapper can return empty output.

When reporting findings, include:

- exact file path(s)
- line numbers when possible
- the exact code snippet (1-3 lines)

## Frontend conventions (resources/js) ? Vue 3 + Pinia + Vue Router + Vuetify

### Architecture

- Vue 3 Composition API with `<script setup>`.
- Keep domain logic in Pinia stores; pages/components stay thin.
- Do not invent endpoints. Inspect Laravel routes first via Boost tools (or `php artisan route:list --path=api`).

### Folder structure (this repo)

- `resources/js/lib/api.ts` ? single Axios instance + interceptors.
- `resources/js/stores/*` ? Pinia stores (one store per domain, e.g. `useTasksStore`).
- `resources/js/router/index.ts` + `resources/js/router/guards/*` ? routes + guards.
- `resources/js/pages/*` (or `views/*` if that's what exists) ? route-level components.
- `resources/js/components/*` ? reusable Vuetify components.

### API client rules

- Use one Axios instance everywhere (no ad-hoc fetch in components).
- If Sanctum SPA auth is used: `withCredentials: true`; handle 419 by re-fetching CSRF once and retrying.
- Always normalize Laravel validation errors `{ errors: { field: [msg] } }` into `{ [field]: string }`.

### Vuetify rules

- Forms: `v-form` + `v-text-field`/`v-select` etc.
- Field errors: `:error-messages="errors.field ? [errors.field] : []"`.
- Use `v-snackbar` for success/error notifications.
- Show loading state (`:loading`, disable submit) during requests.
- Prefer Vuetify components, props, and variants over custom styling or hand-built UI logic whenever they can satisfy the requirement.

### Router + auth

- Use a single auth store (`useAuthStore`) with `init()` that fetches current user.
- Router guards enforce auth; redirect unauthenticated users to login.
- 401 ? redirect/login flow; 419 ? CSRF refresh + retry once.

### Output requirements

- When generating code, include exact file paths and diffs.
- Reuse existing patterns in `resources/js` (don't create new conventions unless asked).

## UI design consistency (Vuetify)

When creating new pages or redesigning existing pages in `resources/js/pages/admin`, ALWAYS base the layout and styling on the reference page:

- Reference page for layout, background card-sytyling: resources\js\pages\admin\tutoring\Tutoring.vue
- Reference page for the header: resources\js\pages\admin\groups\Groups.vue
- Reference page for Cards, when show lists of items with menu:
  resources\js\pages\admin\tutoring\components\Users.vue

Reference for Objects creating or editing: use persisent Dialogs:
resources\js\pages\admin\groups\Groups.vue
