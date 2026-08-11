---
paths:
  - '{app,resources/js,tests}/**/*Student*Timetable*'
---

# Jstests

## Keep timetable V2 and V3 independently reversible
Schülerstundenplan V3 uses its own route, component, service, model, endpoint, and persisted state. Keep V2 directly reachable and unchanged; the per-school students_timetables_admin_version flag only selects the default admin entry so switching back to V2 is immediate.
