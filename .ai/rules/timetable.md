---
paths:
  - '{app/Services/StudentsTimetables/TimetableImport*,app/Models/StudentTimetableEntry.php,app/Http/Controllers/Admin/StudentsTimetables/TimetableImportController.php,resources/js/pages/admin/studentsTimetables/timetable/**}'
---

# Timetable

## Separate timetable replacement from validation mode
Persist import_operation (merge/replace) independently of strict/partial validation. Invalid TT rows are skipped in partial mode for BOTH operations; the UI automatically chooses partial when needed, reports skipped counts, and never blocks replacement solely for invalid rows or adds an extra skip acknowledgment. Replace still requires at least one valid row, an explicit schoolyear/semester range, and a reviewed fingerprint rechecked under the schoolyear lock before writes. Superseded rows use superseded_by_import_id separately from manual is_active; every current timetable consumer excludes them. Replay historical operations with persisted bounds and validation mode, preserve manual inactivity, and invalidate the catalog cache after commit. Show concrete removed appointments before one apply action; legacy imports remain merge.
