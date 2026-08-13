---
paths:
  - 'app/Services/StudentsTimetables/StudentTimetableV3*|app/Models/StudentTimetableV3*'
---

# Models

## Persist generated V3 timetable sets separately
Keep the V3 wizard state as draft input only. Generated V3 timetable sets use the tenant-, schoolyear-, user-, and context-scoped StudentTimetableV3Timetable aggregate. Resolve module/course selections against the current server-side V3 catalog before generation; V3 course hashes must never be trusted as subject keys. Complete materialization is bounded (500 plans and 8 MiB) and serialized per context with a cache lock.

## Persist only possible V3 timetable variants
V3 generation materializes and persists only full_green and green timetables; conflict variants remain represented in summary counts. Apply the 500-plan materialization bound to possible variants, while retaining the backend's global 200,000-combination safety guard.
