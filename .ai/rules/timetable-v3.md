---
paths:
  - 'app/Services/StudentsTimetables/**,resources/js/pages/admin/studentsTimetables/timetableV3/**'
---

# Timetable V3

## Passed modules retain failed attempts
For V3 study information, a module with both a passing grade (1–4) and failures (N/5) belongs only to the passed group. Render every earlier failed attempt, including repeated identical grades, as additional badges on the same passed-module row. This grouping and attempt preservation must be calculated by the backend; Vue only renders the supplied grade list.

## Treat repeated course names as one V3 choice
Within a V3 module, timetable groups with the same normalized displayed course name are one logical selectable course. The backend must retain every underlying timetable-group key and schedule label; the persistent course dialog may select or deselect all courses only within the opened module and persists the underlying keys.

## Derive V3 course hours and Fernunterricht server-side
Course cards receive the subject-plan hours label from the backend. Mark a recurring non-compact course as Fernunterricht when its scheduled weekly slot load is exactly half its required hours; explicit FU flags are honored, occasional groups are excluded, and Kompaktunterricht is never labeled FU.

## Show scheduled versus usual V3 course hours
Course cards must receive scheduled_hours, usual_hours, and an explicit `x von y Std.` comparison label. Mark Fernunterricht only when the recurring scheduled weekly hours are exactly half the usual course hours. Never mark Kompaktunterricht as Fernunterricht, even when the values have a half-hours relationship. This supersedes deriving the label from imported FU flags or showing only subject-plan hours.

## Keep V3 recurrence labels on their schedule lines
For grouped same-name V3 courses, append each course group's recurrence label to that group's own day/time schedule label, e.g. `Montag · 09:50–10:40 · 1-wöchig`. Do not flatten multiple recurrence values into one course-level label or render recurrence separately from its corresponding schedule line.
