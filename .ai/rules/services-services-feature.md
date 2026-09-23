---
paths:
  - '{app/Services/FeaturePreviewDatabaseGuard.php,app/Services/FeaturePreviewRuntimeService.php,tests/Feature/FeaturePreviewSnapshotTest.php}'
---

# Services Services Feature

## Test snapshot imports with the installed preview runtime perimeter
The snapshot target opens a separate PDO to the captured default preview database, under preview_snapshot_target and with stricter PDO options. Runtime must compare that exact derived configuration before connecting; never whitelist arbitrary aliases, changed credentials or weakened options. Keep a real disposable MySQL import/backup/restore test with runtime installed: separate guard mocks miss this boundary. A failed import before snapshot-pending.json exists has not replaced tables; inspect phase and installed code before recovery, never restore an unrelated old backup.
