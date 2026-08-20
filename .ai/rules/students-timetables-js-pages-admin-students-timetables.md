---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/**,app/Services/StudentsTimetables/**,resources/js/pages/admin/studentsTimetables/**}'
---

# Students Timetables Js Pages Admin Students Timetables

## Confirm timetable TXT previews before importing
A timetable TXT upload creates a school/personal-schoolyear-scoped preview with analyzed summary data. Do not dispatch the import job or write active timetable entries until the user confirms it. Deleting a preview removes only its archived source and preview row; previews are excluded from imported history and replay.
