---
paths:
  - 'resources/js/pages/admin/studentsTimetables/timetableV3/**,resources/views/pdfs/students-timetable-overview.blade.php,tests/ui/unit/pages/admin/studentsTimetables/**,tests/Feature/StudentsTimetablesModuleTest.php'
---

# Students Timetables Feature

## Keep manual PDF timetable cells compact
On PDF page 2, show only the compact hyphenated course identifier and recurrence inside timetable cells. Do not repeat the module name or time range because the left period column owns the time. When a cell has multiple courses, render them in two equal columns; keep the PDF-specific course label small so periods 1–15 fit.

## Hide standard weekly recurrence in manual PDF cells
Treat `1-wöchig` as the default on manual timetable PDF page 2 and omit it from the course cell. Keep non-standard recurrence labels such as `2-wöchig` visible. Preserve the raw recurrence_label in the payload for scheduling semantics.

## Number every multi-course PDF cell
Number every Stundenplan PDF cell containing more than one course in hour/weekday display order. Exact date/time overlaps use !N and conflict styling; disjoint multi-course cells use plain N with Mehrfachbelegung and remain filled without overlap semantics. Single-course cells stay unnumbered.
