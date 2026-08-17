---
paths:
  - '{app/Http/Controllers/Admin/StudentsTimetables/**,resources/js/pages/admin/studentsTimetables/timetableV3/**,resources/views/pdfs/students-timetable-overview.blade.php,tests/Feature/StudentsTimetablesModuleTest.php,tests/ui/unit/pages/admin/studentsTimetables/**}'
---

# Ui Unit Pages Admin Students Timetables

## Export manual timetable with a warning cover
The manual V3 timetable card downloads through the existing validated overview PDF endpoint with manual_cover=true. Prepend an A4-landscape information page containing the fixed German no-warranty disclaimer and the prominent reminder “Buchen nicht vergessen!”. Export all periods 1–15 with configured times and preserve exact date/time overlap semantics.

## Include the study selection on the manual PDF cover
The manual timetable PDF payload includes compactPlanningSelectionItems as validated study_selections label/value pairs. Render them in a DOMPDF-compatible two-column table on page 1, before the fixed disclaimer and booking reminder.
