---
paths:
  - '{app/Services/StudentsTimetables/**,app/Http/Controllers/Admin/StudentsTimetables/**,app/Models/StudentTimetablePublishedTimetable.php,resources/js/pages/admin/studentsTimetables/timetableV3/**,tests/Feature/StudentTimetablePublishedTimetableTest.php}'
---

# Students Timetables Timetable V3 Feature

## Keep published timetable names school-unique and stable
Published student timetables receive a server-generated five-character name: the selected schoolyear start as two digits plus three uppercase A-Z letters (for example 26ABC). Enforce uniqueness with the database key on (school_id, name), never accept the name from the browser, retry suffix collisions, and preserve the existing name when the same student's timetable is saved again. Return the name from both publish/read endpoints and show it beside “Manueller Stundenplan”.
