---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableV3TimetableService.php,app/Services/StudentsTimetables/RobotTimetableBackendSetupService.php,app/Http/Controllers/Admin/StudentsTimetables/StudentTimetableV3TimetableController.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
---

# Admin Students Timetables Js Pages Admin Students Timetables Timetable V3

## Cap displayed V3 timetables without rejecting the calculation
V3 checks and counts every combination but materializes and compact-persists at most 2,000 possible full_green/green plans, preserving full_green before green. Keep the compact aggregate below 8 MiB, return only fixed 100-item pages (up to 20), and report the total possible count plus truncation state separately.

## Report V3 generation progress by completed phases
Treat V3 progress as overall work, not as the first Cartesian scan alone. Stream preparation, checking, materializing or solution analysis, compacting, persisting, and complete phases; include checked and total combination counts, keep milestones monotone, and emit 100 only after successful persistence. Skip the unused no-Saturday aggregate pass in the V3 creation path.
