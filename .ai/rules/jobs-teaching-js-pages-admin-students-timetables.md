---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/**,app/Http/Controllers/Admin/Teaching/FileUploadController.php,app/Http/Controllers/Admin/Teaching/Import116Controller.php,app/Jobs/Teaching/Import116Job.php,resources/js/pages/admin/studentsTimetables/**}'
---

# Jobs Teaching Js Pages Admin Students Timetables

## Keep import source downloads scoped and immutable
Source downloads must stay private and be scoped to the authenticated user's school and personal schoolyear; resolve files only inside the expected import directory. Archive each new Import 116 upload under a unique school/year path before queueing it. Legacy runs that only stored a basename are unavailable—never serve the mutable current 116 file for an older run.
