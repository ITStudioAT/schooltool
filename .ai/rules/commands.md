---
paths:
  - '{app/**/FeaturePreview*,app/Console/Commands/CheckFeaturePreviewCommand.php,config/schooltool.php,routes/console.php,scripts/*preview*,tests/**/*Preview*}'
---

# Commands

## Keep preview admission and runtime separate from production
Preview requires an explicit grant on an active confirmed admin-shell account, including super admins; recheck on every request and before consuming login/2FA codes. Preview deployments never migrate or seed the shared schema, run workers, or register application schedules. Run the read-only preview:check before activation; require separate local sessions/cache, host-only secure cookies, no live queue/broadcasting, and no publicly served storage. Apply compatible schema changes through the separately approved main migration first.
