---
paths:
  - 'app/{Http/Controllers,Services}/StudentsTimetables/**'
---

# Controllers Services Students Timetables

## Page V3 timetable results in fixed 100-item blocks
Persist at most 2,000 V3 timetables, but return only one server-sized page of 100. Calculation and restore start on page 1; later pages use the scoped lookup plus current fingerprint. Never accept a client-controlled page size, and derive totals from stored materialized timetables.
