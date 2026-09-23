---
paths:
  - '{app/Services/SchoolyearService.php,app/Services/AdminService.php,app/Providers/AppServiceProvider.php,app/Http/Controllers/Admin/AdminController.php}'
---

# Controllers Admin

## Default missing personal schoolyears from the own schoolwide selection
For every role, authentication (session and Sanctum token) fills only a missing personal schoolyear from that user's own SchoolTool.active_schoolyear_id, and only if the year belongs to the same school. Persist the assignment and refresh the loaded relation. Preserve existing or concurrently changed personal choices with a same-school whereNull update. Without a valid schoolwide default leave it null; do not substitute date-based/current/latest years. Never backfill another user's data from an admin list.
