---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3TimetableController.php,app/Services/StudentsTimetables/StudentTimetableV3TimetableService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
  - '{app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3StateController.php,app/Services/StudentsTimetables/StudentTimetableV3StateService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
  - '{app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3{State,Timetable}Controller.php,app/Services/StudentsTimetables/StudentTimetableV3{State,Timetable}Service.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
---

# Js Pages Admin Students Timetables Timetable V3

## Restore persisted V3 creation results read-only
On F5, load the generated timetable aggregate through the GET timetable endpoint for the authenticated user's exact school, current schoolyear, planning mode, and conditional student code. Return `{data: result|null}` in the generation result shape without recalculation or mutation; the frontend may restore it only when its persisted modules, course keys, options, and planning values still match the current draft.

## Reject stale V3 algorithms during reload
Persist `algorithm_version` in every generated result summary. The read-only F5 endpoint must return null when the stored version is missing or differs from the service's current algorithm version, so previously incorrect calculations stay hidden until Los recalculates them.

## Revalidate the authoritative V3 fingerprint on reload
Algorithm version alone is insufficient for F5 restoration. Rebuild the exact generation fingerprint from the persisted module/course selection, context, options, and selection values plus current student information, subject rows/mappings, and selected active course groups; return null on mismatch or resolution validation. This reload check is read-only: never run Robot, persist, or seed compact-plan rows.

## Scope V3 state and results per tab workspace
Assign every browser tab a UUID workspace_id before loading V3 state. Preserve it in every V3 route and state/timetable GET/PUT, and key drafts, generated aggregates, and locks by that workspace so tabs may use the same student with different selections without overwriting each other.
