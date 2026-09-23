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
