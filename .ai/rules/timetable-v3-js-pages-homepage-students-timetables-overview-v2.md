---
paths:
  - '{resources/js/pages/admin/studentsTimetables/timetableV3/**,resources/js/pages/homepage/studentsTimetables/overviewV2/**}'
---

# Timetable V3 Js Pages Homepage Students Timetables Overview V2

## Preserve module numbers in canonical display names
Student V2 and admin V3 must use canonicalTimetableModuleName for visible module names. When a canonical mapped code contains a number, preserve it after the expanded name: Rev2 → Religion evangelisch 2, ET1 → Ethik 1, RIS/RK/ROR likewise. Unnumbered codes and ordinary backend names remain unchanged.
