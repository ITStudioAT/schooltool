---
paths:
  - '{app/Services/FeaturePreviewSnapshotService.php,database/migrations/*tutoring*.php}'
---

# Migrations

## Preserve retired tutoring migration history in preview imports
Tutoring is retired. Keep shared users and non-tutoring permissions/licences intact. Existing installations retain the 13 removed migration ledger entries; preview imports accept only that exact retired list alongside current migrations and still reject unknown entries. Retain token redaction for legacy tutoring snapshot tables until old source databases have been cleaned. Cleanup migrations are intentionally irreversible and must not run on user data without explicit execution authorization.
