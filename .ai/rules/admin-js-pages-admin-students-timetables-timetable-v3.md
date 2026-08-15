---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3*,app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3*,app/Http/Requests/Admin/*StudentTimetableV3*,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
---

# Admin Js Pages Admin Students Timetables Timetable V3

## Keep V3 generation Mon-Sat and filter persisted results
This supersedes earlier Saturday-option rules. Automatic V3 generation always canonicalizes available weekdays to Monday-Saturday. Creation options are read-only filters over already persisted materialized timetables, applied to compact entries before pagination; they must never recalculate, delete, rewrite the fingerprint, or alter the generation summary. Default include_saturday is true; timetables_meta.total is the filtered count and timetables_meta.unfiltered_total is the stored count.
