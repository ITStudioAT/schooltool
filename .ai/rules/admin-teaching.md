---
paths:
  - '{app/Jobs/Teaching/Import116Job.php,app/Http/Controllers/Admin/Teaching/FileUploadController.php}'
---

# Admin Teaching

## Reject partial Import 116 data before mutation
Stage and semantically validate the whole Import 116 workbook before mutating active data. Missing required headers, any non-empty row missing Klasse/Schülerkennzahl/Familienname/Vorname, or any invalid Test-V3 school level on the students-timetables upload must fail the entire import and preserve the prior dataset; error messages must identify missing fields or sample Excel rows.
