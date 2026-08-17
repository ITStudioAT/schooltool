---
paths:
  - 'resources/views/pdfs/students-timetable-overview.blade.php,tests/Feature/StudentsTimetablesModuleTest.php'
---

# Pdfs Feature

## Expand sparse manual PDF timetable rows
For manual timetable PDF page 2, keep the compact base height at 15 rows. When fewer than 15 booked hour rows are present, increase row height from the available page space, capped at 20mm so sparse timetables are easier to read without creating oversized single rows.

## Enlarge manual PDF typography without Saturday
When the manual PDF omits the Saturday column, slightly increase timetable body, header, detail, and course-label font sizes. Keep the compact font sizes whenever Saturday is present so all six weekday columns remain readable.

## Avoid duplicate manual PDF timetable heading
On manual timetable PDF page 2, render the main page title and metadata only. Suppress the semester label so “Stundenplan” is not repeated immediately above the table; keep semester headings for non-manual overview PDFs.
