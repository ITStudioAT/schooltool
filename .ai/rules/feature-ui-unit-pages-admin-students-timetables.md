---
paths:
  - '{resources/js/pages/admin/studentsTimetables/timetableV3/**,resources/views/pdfs/students-timetable-overview.blade.php,tests/Feature/StudentsTimetablesModuleTest.php,tests/ui/unit/pages/admin/studentsTimetables/**}'
---

# Feature Ui Unit Pages Admin Students Timetables

## Keep the exported timetable on PDF page 2
The manual-editor PDF uses only the visible term “Stundenplan”; never emit “Manueller” in its PDF payload or rendered content. Disable the course-list appendix and apply the DOMPDF-specific compact timetable class so all periods 1–15 remain together on page 2.
