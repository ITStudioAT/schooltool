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
Course cards receive their visible imported weekly slot load from the backend. Mark a recurring non-compact course as Fernunterricht when its scheduled weekly slot load is exactly half the module's Normalstudium hours; occasional groups are excluded, and Kompaktunterricht is never labeled FU.

## Show imported weekly hours on V3 course cards
Course cards must receive `scheduled_hours`, `usual_hours`, and `regular_hours`. The visible hours label shows `scheduled_hours`, calculated from the imported recurring slots and their recurrence intervals, and falls back to `usual_hours` only when no recurring load is available. Keep `usual_hours` tied to the selected study program, but compare non-compact timetable options with `regular_hours` from Normalstudium: exactly half is Fernunterricht. Never mark Kompaktunterricht as Fernunterricht, even when the values have a half-hours relationship.

## Keep V3 recurrence labels on their schedule lines
For grouped same-name V3 courses, append each course group's recurrence label to that group's own day/time schedule label, e.g. `Montag · 09:50–10:40 · 1-wöchig`. Do not flatten multiple recurrence values into one course-level label or render recurrence separately from its corresponding schedule line.

## Show canonical hours for Fernunterricht
Keep imported scheduled_hours as the raw recurring load used to detect Fernunterricht. When a non-compact Unterricht has exactly half the Normalstudium regular_hours, render hours_label from regular_hours and add Fernunterricht; do not display the halved imported load as its course hours. Kompaktunterricht remains excluded and displays its scheduled contact hours.

## Use Normalstudium subject-plan hours on every V3 card
The hours maintained under subjects-overview/subject-plan for Normalstudium are the canonical course hours. V3 module cards and every Unterricht card must display these regular_hours regardless of imported scheduled load or the student's compact plan. Keep scheduled_hours only for Fernunterricht detection; a non-compact half-load shows the full regular hours plus Fernunterricht. This supersedes rules that display scheduled or compact-plan hours on V3 cards.
