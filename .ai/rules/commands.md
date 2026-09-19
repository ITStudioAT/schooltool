---
paths:
  - '{app/**/FeaturePreview*,app/Console/Commands/CheckFeaturePreviewCommand.php,config/schooltool.php,routes/console.php,scripts/*preview*,tests/**/*Preview*}'
---

# Commands

## Keep preview admission and runtime separate from production
Preview uses its own MySQL database, APP_KEY, private/public file copies, local sessions/cache and synchronous jobs. No live SQL credentials or shared live storage are allowed. Admission, current permissions, credential fingerprints and parent relations come from the main-only stateless HTTPS control endpoint; dedicated private-keyfile HMAC binds raw request/response bytes, timestamps and nonces, with atomic local-file replay/rate protection. No positive cross-request cache or stale fallback. Main exports with its own restricted-schema account in a separate consistent READ ONLY transaction without reconnects/events; preview verifies the SSH-authenticated complete archive SHA256 and fresh control source identity before backup/import. First snapshot follows immutable feature lifecycle; later deploys retain data unless RefreshData is confirmed. Feature migrations target only preview. Explicit authentication mail may reach currently granted users/parents; other integrations stay blocked. Preview grants never grant module/admin rights; management is live-only. Configuration and complete preview:check must pass before activation. Failure stays closed; never expose public/storage or start preview workers/schedules.
