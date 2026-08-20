---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetablesStudentOverviewService.php,resources/js/pages/homepage/studentsTimetables/overviewV2/**,tests/Feature/StudentsTimetablesStudentLoginTest.php}'
---

# Students Timetables Overview V2 Feature

## Show the published timetable number to its student
Include the server-generated published timetable `name` only inside the authenticated student's already school/year/student-scoped `published_timetable` overview payload. On OverviewV2, show it as `Nr. xxYYY` beside “Stundenplan der Lehrperson” when present; legacy null names remain hidden. Treat it as a display reference, not an access secret.

## Place the published number in the availability line
This supersedes displaying the number beside the “Stundenplan der Lehrperson” title. Keep the title plain and append the available name to the status as `Stundenplan der Lehrperson verfügbar · Nr. xxYYY`; omit the suffix for legacy null names.
