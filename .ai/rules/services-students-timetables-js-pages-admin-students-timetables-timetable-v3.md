---
paths:
  - '{app/Services/StudentsTimetables/StudentTimetableOverviewService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
  - '{app/Services/StudentsTimetables/StudentTimetableV3TimetableService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
  - '{app/Services/StudentsTimetables/StudentTimetableV3TimetableFilterService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
  - '{app/Services/StudentsTimetables/StudentTimetableV3StudentInformationService.php,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
  - '{app/Services/StudentsTimetables/**,resources/js/pages/admin/studentsTimetables/timetableV3/**}'
---

# Services Students Timetables Js Pages Admin Students Timetables Timetable V3

## Hide whole-semester date ranges in V3 timetable cells
Classify whole-semester courses in StudentTimetableOverviewService with the existing 14-day semester-edge tolerance. In V3 timetable cells, keep the recurrence label but omit only the from-to date range when is_full_semester is true; partial and one-off courses retain their dates.

## Distribute V3 calculation progress across the full pipeline
Reserve 5-20% for completed preparation milestones, map combination checking across 20-80%, then use 85/90/95/100 for materializing, compacting, persisting, and complete. Keep the streamed percentages monotone so the 20-segment LED strip advances before combination checking begins.

## Count V3 free days only from Monday to Friday
In the V3 Optionen card, `free_days` means additional free weekdays from Monday through Friday. A free Saturday is represented only by the separate Saturday facet and must not increase the Freie Tage value. Keep the shared Robot six-day metric unchanged for legacy/V2 behavior; reinterpret it in the V3 filter by subtracting one when Saturday is free, and cap V3 free-day input at five.

## Show exact dates for V3 one-off Unterricht
Append the exact dd.mm.yyyy date to each V3 Unterricht schedule line when that underlying schedule series has exactly one date. Count a grouped course card’s Termine from the union of its distinct course-group dates, so multi-day blocks are not mislabeled as one Termin.

## Mark manual Hauptmodule outside Studienauswahl
For the manual Hauptmodule catalog, calculate selection intention in the backend from the current religion, language, branch, and arts subject. Return is_intended_for_selection on each concrete module and show a red “Nicht vorgesehen!” badge only when it is false; Vue must not duplicate the eligibility rules.
