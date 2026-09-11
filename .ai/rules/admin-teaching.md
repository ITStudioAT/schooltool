---
paths:
  - '{app/Jobs/Teaching/Import116Job.php,app/Http/Controllers/Admin/Teaching/FileUploadController.php}'
---

# Admin Teaching

## Reject partial Import 116 data before mutation
Stage and semantically validate the whole Import 116 workbook before mutating active data. Missing required headers, any non-empty row missing Klasse/Schülerkennzahl/Familienname/Vorname, or any invalid Test-V3 school level on the students-timetables upload must fail the entire import and preserve the prior dataset; error messages must identify missing fields or sample Excel rows.

## Import 116 school-level issues are non-blocking warnings
Supersedes 'Reject partial Import 116 data before mutation' for school-level plausibility issues: import structurally complete student rows with their original school levels even when Tests V3 flags them. Persist warning_rows and sample Excel-row warnings in the run report and expose them in the UI. Missing required headers/identity fields and files without importable student records still fail before active-data mutation. Never skip school-level-warning rows: missing-row cleanup would otherwise delete those students.
