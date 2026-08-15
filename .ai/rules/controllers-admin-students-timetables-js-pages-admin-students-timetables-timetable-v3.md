---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3*,app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3*,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
---

# Controllers Admin Students Timetables Js Pages Admin Students Timetables Timetable V3

## Keep V3 option counts faceted
V3 result filters operate read-only on compact persisted timetable metrics. Each displayed option count substitutes only its own candidate while preserving every other active filter. Freie Tage uses an exact nullable value (`Egal`); keep its buttons stable from the aggregate-wide maximum down to 1 and render backend-provided counts in Vue.

## Hide unavailable V3 filter choices
This supersedes the requirement to display every free-day button. Keep the backend's complete faceted count contract, but in the Optionen card render only Saturday and Freie-Tage choices whose current count is greater than zero. Before calculation success, keep the initial Saturday controls visible.
