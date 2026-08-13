---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3*,app/Services/StudentsTimetables/RobotTimetableBackendSetupService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
  - '{app/Services/StudentsTimetables/StudentTimetableV3TimetableService.php,app/Services/StudentsTimetables/RobotTimetableBackendSetupService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
---

# Pages Admin Students Timetables Timetable V3

## Calculate only usable V3 variants
The V3 Creation `Los!` flow treats full_green and green combinations as possible schedules. Materialize and persist only those usable variants; keep regular-conflict combinations only as summary counts/explanation. The Saturday option maps server-side to Mon–Fri or Mon–Sat, and Vue displays backend counts without recomputing timetable business rules.

## Offer a zero-result V3 solution plan
When strict V3 generation finds zero possible timetables, calculate a read-only leave-one-module-out solution plan. Remove exactly one complete module per scenario, preserve every other selected Unterricht and option, and count full_green plus green without materializing trial timetables. The analysis never mutates the user's selection; return every scenario in backend-ranked order for Vue to render and offer through the explicit apply action below.

## Apply a V3 solution only by explicit click
The zero-result leave-one-module-out analysis itself never changes the draft. A calculated scenario with a positive possible count is an explicit accessible action: resolve its exact selection_key in the current catalog, remove that whole module and its underlying selected course keys, persist through the serialized state queue, then run the normal strict V3 calculation. Zero or limit-exceeded scenarios stay non-actionable.
