---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableOverviewService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
---

# Services Students Timetables Js Pages Admin Students Timetables Timetable V3

## Hide whole-semester date ranges in V3 timetable cells
Classify whole-semester courses in StudentTimetableOverviewService with the existing 14-day semester-edge tolerance. In V3 timetable cells, keep the recurrence label but omit only the from-to date range when is_full_semester is true; partial and one-off courses retain their dates.
