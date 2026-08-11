---
paths:
  - '{app/Services/StudentsTimetables,resources/js/pages/admin/studentsTimetables}/**'
---

# Students Timetables

## Derive instruction type from the class name
Kompaktunterricht is determined from the relevant class-name segment: a segment that starts with digits and contains Q, R, S, T, U, or V is compact (for example 3R or 5RU). Every other class is Normalunterricht. Keep frontend labels aligned with StudentTimetableOverviewService.

## Keep timetable business calculations in the backend
Calculate student timetable business rules only in backend services, including instruction type, semester, and religion/language/branch/arts selections. Frontend components receive ready-to-display values and must not duplicate class-name or course-selection calculations.

## Group imported module results in the backend
For V3 study information, categorize imported module results server-side: B is exempt, grades 1–4 are passed, and N or 5 are failed. Ignore other result codes unless the product rule changes; Vue only renders the ready-made groups.

## Do not classify Kompaktunterricht as Fernunterricht
Kompaktunterricht schedules only the in-school contact half; the other half is self-study at home, not a scheduled FU course. Never infer or display FU for course groups marked is_kompaktunterricht, and exclude them from distance-learning quality metrics.
