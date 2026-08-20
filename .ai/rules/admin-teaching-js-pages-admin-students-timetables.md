---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/**,app/Http/Controllers/Admin/Teaching/FileUploadController.php,resources/js/pages/admin/studentsTimetables/**}'
---

# Admin Teaching Js Pages Admin Students Timetables

## Scope timetable imports to the personal schoolyear
Stundenplan-, Anrechnungs- und Sokrates-116-Importe use the authenticated user’s selected personal schoolyear, never the schoolwide active schoolyear. Capture the personal schoolyear ID when a queued import is created, and keep import history, deletion, dataset metadata, and UI labels in the same scope.
